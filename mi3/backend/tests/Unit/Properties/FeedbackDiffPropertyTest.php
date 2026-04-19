<?php

declare(strict_types=1);

namespace Tests\Unit\Properties;

use App\Services\Compra\FeedbackService;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Feature: multi-agent-compras-pipeline, Property 15: diff de feedback captura todas las diferencias
 *
 * Para cualquier par de (extracted_data, datos_guardados) con N campos diferentes,
 * el sistema debe crear exactamente N registros en extraction_feedback, cada uno con
 * field_name, original_value, y corrected_value correctos.
 *
 * **Validates: Requirements 6.1, 6.2**
 */
class FeedbackDiffPropertyTest extends TestCase
{
    use TestTrait;

    private FeedbackService $feedbackService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feedbackService = new FeedbackService();
    }

    // ─── Generators ───

    private function proveedorGenerator(): Generator
    {
        return Generator\elements([
            'Distribuidora Central',
            'Frutas del Sur',
            'Carnicería Don Pedro',
            'Verdulería La Vega',
            'Panadería El Trigo',
            'Supermercado Líder',
        ]);
    }

    private function montoGenerator(): Generator
    {
        return Generator\choose(1000, 500000);
    }

    /**
     * Generate a complete scalar data array.
     */
    private function generateScalarData(): array
    {
        $proveedores = ['Distribuidora Central', 'Frutas del Sur', 'Carnicería Don Pedro', 'Verdulería La Vega'];
        $ruts = ['76.123.456-7', '77.888.999-0', '12.345.678-9', '99.100.200-K'];
        $fechas = ['2025-01-15', '2025-02-20', '2025-03-10', '2024-12-01'];
        $metodos = ['efectivo', 'transferencia', 'tarjeta', 'credito'];
        $tipos = ['insumo', 'producto', 'servicio', 'activo'];

        return [
            'proveedor' => $proveedores[array_rand($proveedores)],
            'rut_proveedor' => $ruts[array_rand($ruts)],
            'fecha' => $fechas[array_rand($fechas)],
            'metodo_pago' => $metodos[array_rand($metodos)],
            'tipo_compra' => $tipos[array_rand($tipos)],
            'monto_neto' => rand(1000, 500000),
            'iva' => rand(190, 95000),
            'monto_total' => rand(1190, 595000),
        ];
    }

    /**
     * Generate a single item.
     */
    private function generateItem(string $nombre): array
    {
        $cantidad = rand(1, 20);
        $precioUnitario = rand(100, 50000);

        return [
            'nombre' => $nombre,
            'cantidad' => $cantidad,
            'unidad' => ['kg', 'unidad', 'litro', 'caja'][array_rand(['kg', 'unidad', 'litro', 'caja'])],
            'precio_unitario' => $precioUnitario,
            'subtotal' => $cantidad * $precioUnitario,
        ];
    }

    // ─── Property Tests ───

    /**
     * Property: Identical data produces 0 diffs.
     *
     * For any valid extraction data, computeDiff(data, data) must return an empty array.
     */
    public function testIdenticalDataProducesZeroDiffs(): void
    {
        $this->forAll(
            Generator\choose(1, 100)
        )
        ->withMaxSize(100)
        ->then(function (int $_seed): void {
            $data = $this->generateScalarData();
            $data['items'] = [
                $this->generateItem('Tomate'),
                $this->generateItem('Papa'),
            ];

            $diffs = $this->feedbackService->computeDiff($data, $data);

            $this->assertCount(
                0,
                $diffs,
                'Identical data must produce exactly 0 diffs'
            );
        });
    }

    /**
     * Property: N scalar fields changed produces exactly N diffs.
     *
     * For any number of scalar fields changed (1 to 8), computeDiff must return
     * exactly N diff entries, one per changed field.
     */
    public function testNScalarFieldsChangedProducesNDiffs(): void
    {
        $scalarFields = ['proveedor', 'rut_proveedor', 'fecha', 'metodo_pago', 'tipo_compra', 'monto_neto', 'iva', 'monto_total'];

        $this->forAll(
            Generator\choose(1, 8)
        )
        ->withMaxSize(100)
        ->then(function (int $n) use ($scalarFields): void {
            $original = $this->generateScalarData();
            $final = $original;

            // Pick first N fields to change (deterministic per iteration)
            $fieldsToChange = array_slice($scalarFields, 0, $n);

            foreach ($fieldsToChange as $field) {
                if (in_array($field, ['monto_neto', 'iva', 'monto_total'])) {
                    $final[$field] = (int) $original[$field] + 1000;
                } else {
                    $final[$field] = $original[$field] . '_modified';
                }
            }

            $diffs = $this->feedbackService->computeDiff($original, $final);

            $this->assertCount(
                $n,
                $diffs,
                "Changing {$n} scalar fields must produce exactly {$n} diffs"
            );

            $diffFieldNames = array_column($diffs, 'field_name');
            foreach ($fieldsToChange as $field) {
                $this->assertContains(
                    $field,
                    $diffFieldNames,
                    "Changed field '{$field}' must appear in diffs"
                );
            }
        });
    }

    /**
     * Property: Items with same name but different quantities/prices produce diffs for each changed field.
     *
     * For any item present in both original and final with M fields changed,
     * computeDiff must produce M diff entries for that item.
     */
    public function testItemsWithSameNameDifferentFieldsProduceDiffs(): void
    {
        $itemFields = ['cantidad', 'unidad', 'precio_unitario', 'subtotal'];

        $this->forAll(
            Generator\choose(1, 4)
        )
        ->withMaxSize(100)
        ->then(function (int $fieldsToChangeCount) use ($itemFields): void {
            $item = $this->generateItem('Tomate');
            $original = ['items' => [$item]];
            $finalItem = $item;

            $selectedFields = array_slice($itemFields, 0, $fieldsToChangeCount);

            foreach ($selectedFields as $field) {
                if ($field === 'unidad') {
                    $finalItem[$field] = $finalItem[$field] === 'kg' ? 'unidad' : 'kg';
                } else {
                    $finalItem[$field] = (int) $finalItem[$field] + 100;
                }
            }

            $final = ['items' => [$finalItem]];

            $diffs = $this->feedbackService->computeDiff($original, $final);

            $this->assertCount(
                $fieldsToChangeCount,
                $diffs,
                "Changing {$fieldsToChangeCount} item fields must produce exactly {$fieldsToChangeCount} diffs"
            );

            foreach ($diffs as $diff) {
                $this->assertMatchesRegularExpression(
                    '/^items\.\d+\.\w+$/',
                    $diff['field_name'],
                    'Item diff field_name must follow items.{index}.{field} format'
                );
            }
        });
    }

    /**
     * Property: Items removed produce a diff entry for each removed item.
     *
     * For any N items in original that are not in final, computeDiff must produce
     * N diff entries with corrected_value = null.
     */
    public function testItemsRemovedProduceDiffEntries(): void
    {
        $this->forAll(
            Generator\choose(1, 5)
        )
        ->withMaxSize(100)
        ->then(function (int $itemsToRemove): void {
            $names = ['Tomate', 'Papa', 'Cebolla', 'Lechuga', 'Palta'];
            $items = [];
            for ($i = 0; $i < $itemsToRemove; $i++) {
                $items[] = $this->generateItem($names[$i]);
            }

            $original = ['items' => $items];
            $final = ['items' => []];

            $diffs = $this->feedbackService->computeDiff($original, $final);

            $this->assertCount(
                $itemsToRemove,
                $diffs,
                "Removing {$itemsToRemove} items must produce exactly {$itemsToRemove} diffs"
            );

            foreach ($diffs as $diff) {
                $this->assertNull(
                    $diff['corrected_value'],
                    'Removed item diff must have corrected_value = null'
                );
                $this->assertNotNull(
                    $diff['original_value'],
                    'Removed item diff must have original_value set'
                );
            }
        });
    }

    /**
     * Property: Items added produce a diff entry for each new item.
     *
     * For any N items in final that are not in original, computeDiff must produce
     * N diff entries with original_value = null.
     */
    public function testItemsAddedProduceDiffEntries(): void
    {
        $this->forAll(
            Generator\choose(1, 5)
        )
        ->withMaxSize(100)
        ->then(function (int $itemsToAdd): void {
            $names = ['Tomate', 'Papa', 'Cebolla', 'Lechuga', 'Palta'];
            $items = [];
            for ($i = 0; $i < $itemsToAdd; $i++) {
                $items[] = $this->generateItem($names[$i]);
            }

            $original = ['items' => []];
            $final = ['items' => $items];

            $diffs = $this->feedbackService->computeDiff($original, $final);

            $this->assertCount(
                $itemsToAdd,
                $diffs,
                "Adding {$itemsToAdd} items must produce exactly {$itemsToAdd} diffs"
            );

            foreach ($diffs as $diff) {
                $this->assertNull(
                    $diff['original_value'],
                    'Added item diff must have original_value = null'
                );
                $this->assertNotNull(
                    $diff['corrected_value'],
                    'Added item diff must have corrected_value set'
                );
            }
        });
    }

    /**
     * Property: Each diff has correct field_name, original_value, and corrected_value.
     *
     * For any pair of data with known differences, each diff entry must accurately
     * reflect the original and corrected values for the specified field.
     */
    public function testEachDiffHasCorrectFieldNameAndValues(): void
    {
        $this->forAll(
            $this->proveedorGenerator(),
            $this->proveedorGenerator(),
            $this->montoGenerator(),
            $this->montoGenerator()
        )
        ->withMaxSize(100)
        ->then(function (string $origProveedor, string $finalProveedor, int $origMonto, int $finalMonto): void {
            $original = [
                'proveedor' => $origProveedor,
                'monto_total' => $origMonto,
            ];
            $final = [
                'proveedor' => $finalProveedor,
                'monto_total' => $finalMonto,
            ];

            $diffs = $this->feedbackService->computeDiff($original, $final);

            foreach ($diffs as $diff) {
                $this->assertArrayHasKey('field_name', $diff, 'Each diff must have field_name');
                $this->assertArrayHasKey('original_value', $diff, 'Each diff must have original_value');
                $this->assertArrayHasKey('corrected_value', $diff, 'Each diff must have corrected_value');

                $fieldName = $diff['field_name'];

                if ($fieldName === 'proveedor') {
                    $this->assertSame(
                        (string) $origProveedor,
                        $diff['original_value'],
                        'Proveedor original_value must match original data'
                    );
                    $this->assertSame(
                        (string) $finalProveedor,
                        $diff['corrected_value'],
                        'Proveedor corrected_value must match final data'
                    );
                } elseif ($fieldName === 'monto_total') {
                    $this->assertSame(
                        (string) $origMonto,
                        $diff['original_value'],
                        'Monto original_value must match original data'
                    );
                    $this->assertSame(
                        (string) $finalMonto,
                        $diff['corrected_value'],
                        'Monto corrected_value must match final data'
                    );
                }
            }

            // Verify correct count
            $expectedCount = 0;
            if ($origProveedor !== $finalProveedor) {
                $expectedCount++;
            }
            if ($origMonto !== $finalMonto) {
                $expectedCount++;
            }

            $this->assertCount(
                $expectedCount,
                $diffs,
                "Must produce exactly {$expectedCount} diffs for the fields that are different"
            );
        });
    }

    /**
     * Property: Combined scalar + item diffs produce correct total count.
     *
     * For any combination of scalar field changes and item changes,
     * the total diff count must equal the sum of all individual changes.
     */
    public function testCombinedScalarAndItemDiffsProduceCorrectTotal(): void
    {
        $this->forAll(
            Generator\choose(0, 4),
            Generator\choose(0, 3)
        )
        ->withMaxSize(100)
        ->then(function (int $scalarChanges, int $itemFieldChanges): void {
            $scalarFields = ['proveedor', 'rut_proveedor', 'fecha', 'metodo_pago'];
            $itemFields = ['cantidad', 'precio_unitario', 'subtotal'];

            $original = $this->generateScalarData();
            $original['items'] = [$this->generateItem('Tomate')];
            $final = $original;
            $final['items'] = [$original['items'][0]];

            // Apply scalar changes
            $actualScalarChanges = min($scalarChanges, count($scalarFields));
            for ($i = 0; $i < $actualScalarChanges; $i++) {
                $field = $scalarFields[$i];
                $final[$field] = $original[$field] . '_changed';
            }

            // Apply item field changes
            $actualItemChanges = min($itemFieldChanges, count($itemFields));
            for ($i = 0; $i < $actualItemChanges; $i++) {
                $field = $itemFields[$i];
                $final['items'][0][$field] = (int) $original['items'][0][$field] + 999;
            }

            $diffs = $this->feedbackService->computeDiff($original, $final);

            $expectedTotal = $actualScalarChanges + $actualItemChanges;

            $this->assertCount(
                $expectedTotal,
                $diffs,
                "Combined {$actualScalarChanges} scalar + {$actualItemChanges} item changes must produce {$expectedTotal} total diffs"
            );
        });
    }
}
