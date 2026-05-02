<?php

declare(strict_types=1);

namespace App\Services\Recipe;

use App\Models\ComboComponent;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComboService
{
    /**
     * Category ID that identifies combo products.
     */
    private const COMBO_CATEGORY_ID = 8;

    /**
     * Get all active combos with component counts, cost, and margin.
     *
     * @return Collection
     */
    public function getComboList(): Collection
    {
        $combos = Product::where('category_id', self::COMBO_CATEGORY_ID)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $combos->map(function (Product $combo) {
            $components = ComboComponent::where('combo_product_id', $combo->id)->get();

            $fixedCount = $components->where('is_fixed', true)->count();
            $selectableCount = $components->where('is_fixed', false)->count();

            $price = (float) $combo->price;
            $cost = (float) $combo->cost_price;
            $margin = $price > 0 ? round((1 - $cost / $price) * 100, 1) : null;

            return [
                'id'                  => $combo->id,
                'name'                => $combo->name,
                'price'               => $price,
                'cost_price'          => $cost,
                'margin'              => $margin,
                'image_url'           => $combo->image_url,
                'fixed_count'         => $fixedCount,
                'selectable_count'    => $selectableCount,
                'total_components'    => $components->count(),
            ];
        });
    }

    /**
     * Get combo detail: fixed items + selection groups, filtered by is_active=1.
     *
     * @param  int $productId
     * @return array{fixed_items: array, selection_groups: array}
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getComboDetail(int $productId): array
    {
        Product::where('id', $productId)
            ->where('category_id', self::COMBO_CATEGORY_ID)
            ->firstOrFail();

        $components = ComboComponent::where('combo_product_id', $productId)
            ->join('products', 'products.id', '=', 'combo_components.child_product_id')
            ->where('products.is_active', true)
            ->select(
                'combo_components.*',
                'products.name as product_name',
                'products.price as product_price',
                'products.image_url',
                'products.cost_price'
            )
            ->orderByDesc('combo_components.is_fixed')
            ->orderBy('combo_components.selection_group')
            ->orderBy('combo_components.sort_order')
            ->orderBy('products.name')
            ->get();

        $fixedItems = [];
        $selectionGroups = [];

        foreach ($components as $comp) {
            if ($comp->is_fixed) {
                $fixedItems[] = [
                    'product_id'   => $comp->child_product_id,
                    'product_name' => $comp->product_name,
                    'quantity'     => $comp->quantity,
                    'cost_price'   => (float) $comp->cost_price,
                    'image_url'    => $comp->image_url,
                ];
            } else {
                $group = $comp->selection_group ?? 'Sin grupo';

                if (!isset($selectionGroups[$group])) {
                    $selectionGroups[$group] = [
                        'max_selections' => $comp->max_selections,
                        'options'        => [],
                    ];
                }

                $selectionGroups[$group]['options'][] = [
                    'product_id'       => $comp->child_product_id,
                    'product_name'     => $comp->product_name,
                    'price_adjustment' => (float) $comp->price_adjustment,
                    'cost_price'       => (float) $comp->cost_price,
                    'image_url'        => $comp->image_url,
                ];
            }
        }

        return [
            'fixed_items'      => $fixedItems,
            'selection_groups'  => $selectionGroups,
        ];
    }

    /**
     * Save combo components: delete existing + insert new in a transaction.
     *
     * @param  int   $productId
     * @param  array $components  Each element:
     *   ['child_product_id' => int, 'quantity' => int, 'is_fixed' => bool,
     *    'selection_group' => ?string, 'max_selections' => int, 'price_adjustment' => float, 'sort_order' => int]
     * @return void
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function saveComboComponents(int $productId, array $components): void
    {
        Product::where('id', $productId)
            ->where('category_id', self::COMBO_CATEGORY_ID)
            ->firstOrFail();

        DB::transaction(function () use ($productId, $components) {
            ComboComponent::where('combo_product_id', $productId)->delete();

            $sortOrder = 0;
            foreach ($components as $comp) {
                ComboComponent::create([
                    'combo_product_id'  => $productId,
                    'child_product_id'  => $comp['child_product_id'],
                    'quantity'          => $comp['quantity'] ?? 1,
                    'is_fixed'          => $comp['is_fixed'] ?? true,
                    'selection_group'   => $comp['selection_group'] ?? null,
                    'max_selections'    => $comp['max_selections'] ?? 1,
                    'price_adjustment'  => $comp['price_adjustment'] ?? 0,
                    'sort_order'        => $comp['sort_order'] ?? $sortOrder++,
                ]);
            }

            $this->calculateComboCost($productId);
        });
    }

    /**
     * Calculate combo cost and update products.cost_price.
     *
     * Cost = Σ(cost_price × quantity of fixed items) + Σ(average cost per selection group)
     *
     * For each selection group, the average cost of all active options is used.
     *
     * @param  int $productId
     * @return float The calculated cost
     */
    public function calculateComboCost(int $productId): float
    {
        $components = ComboComponent::where('combo_product_id', $productId)
            ->join('products', 'products.id', '=', 'combo_components.child_product_id')
            ->where('products.is_active', true)
            ->select(
                'combo_components.is_fixed',
                'combo_components.quantity',
                'combo_components.selection_group',
                'products.cost_price'
            )
            ->get();

        $fixedCost = 0.0;

        foreach ($components->where('is_fixed', true) as $item) {
            $fixedCost += (float) $item->cost_price * $item->quantity;
        }

        // Group selectable items by selection_group, average cost per group
        $selectableCost = 0.0;
        $groups = $components->where('is_fixed', false)->groupBy('selection_group');

        foreach ($groups as $groupItems) {
            $avgCost = $groupItems->avg(fn ($item) => (float) $item->cost_price);
            $selectableCost += $avgCost;
        }

        $totalCost = round($fixedCost + $selectableCost, 2);

        Product::where('id', $productId)->update(['cost_price' => $totalCost]);

        Log::info("ComboService: cost recalculated for product #{$productId}: \${$totalCost}");

        return $totalCost;
    }

    /**
     * Delete all combo components for a product.
     *
     * @param  int $productId
     * @return int Number of deleted rows
     */
    public function deleteComboComponents(int $productId): int
    {
        return ComboComponent::where('combo_product_id', $productId)->delete();
    }
}
