<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Compra\AsistenteCompraService;
use App\Services\Compra\ExtraccionService;
use App\Services\Compra\PipelineService;
use App\Services\Compra\SugerenciaService;
use App\Services\Compra\ValidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtraccionController extends Controller
{
    public function __construct(
        private ExtraccionService $extraccionService,
        private SugerenciaService $sugerenciaService,
        private ValidacionService $validacionService,
        private PipelineService $pipelineService,
        private AsistenteCompraService $asistenteService,
    ) {}

    /**
     * Extract data from an image using IA, then match with suggestions.
     * POST /api/v1/admin/compras/extract
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'image_url' => 'required_without:temp_key|string',
            'temp_key' => 'required_without:image_url|string',
        ]);

        $imageUrl = $request->input('image_url') ?? $request->input('temp_key');

        try {
            $result = $this->extraccionService->extractFromImage($imageUrl);

            if (!($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Extracción falló',
                    'fallback' => 'manual',
                ], 422);
            }

            // Match proveedor and items with suggestions
            $data = $result['data'];
            
            // Post-extraction: force map known persons to suppliers
            $data = $this->mapPersonToSupplier($data);
            
            // Post-extraction: if RUT is detected, try to match proveedor by RUT
            $data = $this->matchProveedorByRut($data);
            
            $proveedorMatch = null;
            $itemsMatch = [];

            if (!empty($data['proveedor'])) {
                $proveedorMatch = $this->sugerenciaService->matchProveedor($data['proveedor']);
                // If proveedor matched, override with the canonical name
                if ($proveedorMatch && $proveedorMatch['score'] >= 70) {
                    $data['proveedor'] = $proveedorMatch['nombre_original'];
                }
            }

            if (!empty($data['items'])) {
                $itemsMatch = $this->sugerenciaService->matchItems($data['items']);
                
                // If proveedor is still unknown/wrong, infer from matched ingredient's supplier
                if ($this->isProveedorSuspect($data['proveedor'])) {
                    $inferredProv = $this->inferProveedorFromItems($itemsMatch);
                    if ($inferredProv) {
                        $data['proveedor'] = $inferredProv;
                        $data['notas_ia'] = ($data['notas_ia'] ?? '') . ' [Proveedor inferido del ingrediente]';
                        // Re-match proveedor with the inferred name
                        $proveedorMatch = $this->sugerenciaService->matchProveedor($inferredProv);
                    }
                }
            }

            // Apply known supplier rules (metodo_pago, tipo_compra)
            $data = $this->applySupplierRules($data);

            if (!empty($data['items'])) {
                $itemsMatch = $this->sugerenciaService->matchItems($data['items']);

                // Apply product equivalences: convert package quantities to individual units
                // e.g., "2 paquetes Big Montina 800GR" → 20 unidades (10 per package)
                foreach ($data['items'] as $idx => &$item) {
                    $itemName = mb_strtolower(trim($item['nombre'] ?? ''));
                    if ($itemName === '') continue;

                    // Normalize: remove accents for matching
                    $itemNorm = $this->removeAccentsSimple($itemName);

                    $equiv = \App\Models\ProductEquivalence::where('nombre_normalizado', $itemName)->first();
                    if (!$equiv) {
                        // Try fuzzy: check if item name contains the equivalence name or vice versa
                        $equiv = \App\Models\ProductEquivalence::get()->first(function ($eq) use ($itemName, $itemNorm) {
                            $eqNorm = $this->removeAccentsSimple(mb_strtolower($eq->nombre_normalizado));
                            return str_contains($itemNorm, $eqNorm)
                                || str_contains($eqNorm, $itemNorm)
                                || str_contains($itemName, mb_strtolower($eq->nombre_normalizado))
                                || str_contains(mb_strtolower($eq->nombre_normalizado), $itemName);
                        });
                    }

                    if ($equiv) {
                        $originalQty = (float) ($item['cantidad'] ?? 1);
                        $item['cantidad'] = $originalQty * (float) $equiv->cantidad_por_unidad;
                        $item['unidad'] = $equiv->unidad_real;
                        if ($item['cantidad'] > 0) {
                            $item['precio_unitario'] = (int) round(($item['subtotal'] ?? 0) / $item['cantidad']);
                        }
                        $item['empaque_detalle'] = "{$originalQty} {$equiv->unidad_visual} × {$equiv->cantidad_por_unidad} {$equiv->unidad_real}/{$equiv->unidad_visual} = {$item['cantidad']} {$equiv->unidad_real}";

                        // Update the match to point to the equivalence's ingredient
                        if (isset($itemsMatch[$idx]) && $equiv->ingrediente_id) {
                            $ing = \App\Models\Ingredient::find($equiv->ingrediente_id);
                            if ($ing) {
                                $itemsMatch[$idx]['match'] = [
                                    'id' => $ing->id,
                                    'name' => $ing->name,
                                    'unit' => $ing->unit,
                                    'cost_per_unit' => $ing->cost_per_unit,
                                    'current_stock' => $ing->current_stock,
                                ];
                                $itemsMatch[$idx]['match_type'] = 'ingredient';
                                $itemsMatch[$idx]['score'] = 100;
                                $itemsMatch[$idx]['pre_selected'] = true;
                            }
                        }
                    }
                }
                unset($item);
            }

            return response()->json([
                'success' => true,
                'extraction_log_id' => $result['extraction_log_id'],
                'data' => $data,
                'confianza' => $result['confianza'],
                'overall_confidence' => $result['overall_confidence'],
                'processing_time_ms' => $result['processing_time_ms'],
                'sugerencias' => [
                    'proveedor' => $proveedorMatch,
                    'items' => $itemsMatch,
                ],
            ]);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'tiempo de espera') ? 408 : 500;
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'fallback' => 'manual',
            ], $status);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error en extracción: ' . $e->getMessage(),
                'fallback' => 'manual',
            ], 500);
        }
    }

    /**
     * Return extraction quality metrics from ai_extraction_logs.
     * GET /api/v1/admin/compras/extraction-quality
     */
    public function quality(): JsonResponse
    {
        $report = $this->validacionService->generarReporte();

        return response()->json([
            'success' => true,
            ...$report,
        ]);
    }

    /**
     * Run the training pipeline.
     * POST /api/v1/admin/compras/pipeline/run
     */
    public function runPipeline(): JsonResponse
    {
        try {
            $result = $this->pipelineService->ejecutar();

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error en pipeline: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return the latest pipeline report.
     * GET /api/v1/admin/compras/pipeline/report
     */
    public function pipelineReport(): JsonResponse
    {
        $report = $this->pipelineService->reporte();

        return response()->json([
            'success' => true,
            ...$report,
        ]);
    }

    /**
     * Post-extraction: map known person names to actual suppliers.
     * The AI sometimes returns the person's name instead of the business name.
     * Also filters out the sender (Ricardo) being detected as supplier.
     */
    private function mapPersonToSupplier(array $data): array
    {
        $personToSupplier = [
            // ARIAKA riders (delivery)
            'karen miranda olmedo' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'karen miranda' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'elcia vilca' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'eliana vilca' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'cecilia rojas hinojosa' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'cecilia rojas' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'maria mondañez mamani' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'maria mondanez mamani' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'giovanna loza salas' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'giovanna loza' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'ariel araya' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'ariel aliro araya villalobos' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'karina roco' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            // Gas (Abastible)
            'elton san martin' => ['proveedor' => 'Abastible', 'item' => 'gas 15', 'tipo_compra' => 'ingredientes'],
            'elton san martín' => ['proveedor' => 'Abastible', 'item' => 'gas 15', 'tipo_compra' => 'ingredientes'],
            // Other known persons
            'karina andrea muñoz ahumada' => ['proveedor' => 'Ariztía (proveedor)', 'item' => null, 'tipo_compra' => 'ingredientes'],
            'karina muñoz' => ['proveedor' => 'Ariztía (proveedor)', 'item' => null, 'tipo_compra' => 'ingredientes'],
            'lucila cacera' => ['proveedor' => 'agro-lucila', 'item' => null, 'tipo_compra' => 'ingredientes'],
            // Sender (NOT a supplier — this is the owner)
            'ricardo huiscaleo' => null,
            'ricardo aníbal huiscaleo' => null,
            'ricardo aníbal huiscaleo llafquén' => null,
            'ricardo anibal huiscaleo llafquen' => null,
        ];

        $proveedor = mb_strtolower(trim($data['proveedor'] ?? ''));

        // Check if the detected "proveedor" is actually a known person
        foreach ($personToSupplier as $person => $mapping) {
            if (str_contains($proveedor, $person) || similar_text($proveedor, $person, $pct) && $pct > 80) {
                if ($mapping === null) {
                    // This is the sender, not a supplier — clear it
                    $data['proveedor'] = null;
                    $data['notas_ia'] = ($data['notas_ia'] ?? '') . ' [IA detectó al emisor como proveedor, corregido]';
                } else {
                    $data['proveedor'] = $mapping['proveedor'];
                    $data['metodo_pago'] = 'transfer';
                    $data['tipo_compra'] = $mapping['tipo_compra'];
                    if ($mapping['item'] && empty($data['items'])) {
                        $montoTotal = (float) ($data['monto_total'] ?? 0);
                        $cantidad = 1;
                        $precioUnitario = $montoTotal;

                        // Special: gas cylinders — estimate quantity from total
                        if (str_contains($mapping['item'], 'gas')) {
                            $precioCilindro = 23500; // precio referencia cilindro 15kg
                            if ($montoTotal >= $precioCilindro * 1.5) {
                                $cantidad = (int) round($montoTotal / $precioCilindro);
                                $precioUnitario = (int) round($montoTotal / $cantidad);
                            }
                        }

                        $data['items'] = [[
                            'nombre' => $mapping['item'],
                            'cantidad' => $cantidad,
                            'unidad' => 'unidad',
                            'precio_unitario' => $precioUnitario,
                            'subtotal' => $data['monto_total'] ?? 0,
                        ]];
                    } elseif ($mapping['item'] && str_contains($mapping['item'], 'gas')) {
                        // Gas: always override items with correct quantity calculation
                        $montoTotal = (float) ($data['monto_total'] ?? 0);
                        $cantidad = 1;
                        $precioCilindro = 23500;
                        if ($montoTotal >= $precioCilindro * 1.5) {
                            $cantidad = (int) round($montoTotal / $precioCilindro);
                        }
                        $precioUnitario = $cantidad > 0 ? (int) round($montoTotal / $cantidad) : $montoTotal;
                        $data['items'] = [[
                            'nombre' => $mapping['item'],
                            'cantidad' => $cantidad,
                            'unidad' => 'unidad',
                            'precio_unitario' => $precioUnitario,
                            'subtotal' => (int) $montoTotal,
                        ]];
                    } elseif ($mapping['item']) {
                        // Replace generic items with the correct one
                        foreach ($data['items'] as &$item) {
                            if (empty($item['nombre']) || mb_strtolower($item['nombre']) === 'transferencia') {
                                $item['nombre'] = $mapping['item'];
                            }
                        }
                    }
                }
                break;
            }
        }

        // Also check "Mercado Pago" as proveedor — it's never the real supplier
        if ($proveedor === 'mercado pago') {
            $data['proveedor'] = null;
            $data['metodo_pago'] = 'transfer';
        }

        // Normalize ARIAKA variants to just "ARIAKA"
        $proveedorNow = mb_strtolower(trim($data['proveedor'] ?? ''));
        if (str_contains($proveedorNow, 'ariaka')) {
            $data['proveedor'] = 'ARIAKA';
            $data['metodo_pago'] = 'transfer';
            $data['tipo_compra'] = 'otros';
            // Ensure item is "Servicios Delivery"
            if (!empty($data['items'])) {
                foreach ($data['items'] as &$item) {
                    $itemName = mb_strtolower($item['nombre'] ?? '');
                    if (empty($itemName) || $itemName === 'transferencia' || str_contains($itemName, 'servicio')) {
                        $item['nombre'] = 'Servicios Delivery';
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Post-extraction: match proveedor by RUT or known text patterns.
     * Fixes cases where the AI reads the buyer's info as the supplier.
     */
    private function matchProveedorByRut(array $data): array
    {
        // Strategy 1: Match by RUT in supplier_index
        $rut = $data['rut_proveedor'] ?? null;
        if (!empty($rut)) {
            $supplier = \App\Models\SupplierIndex::where('rut', $rut)->first();
            if ($supplier) {
                $data['proveedor'] = $supplier->nombre_original;
                $data['notas_ia'] = ($data['notas_ia'] ?? '') . " [Proveedor por RUT {$rut}]";
                return $data;
            }
        }

        // Strategy 2: Detect known supplier names/URLs in the AI notes or raw extraction
        // The AI might put "ariztiaatunegocio.cl" in notas_ia or the proveedor might be wrong
        // but the items/context reveal the real supplier
        $knownPatterns = [
            'ariztia' => 'Ariztía (proveedor)',
            'agrosuper' => 'Agrosuper',
            'ideal' => 'ideal',
            'shipo' => 'Shipo',
            'cencosud' => 'Jumbo',
            'santa isabel' => 'Santa Isabel',
            'jumbo' => 'Jumbo',
            'vanni' => 'vanni',
        ];

        // Check all text fields for supplier clues
        $allText = mb_strtolower(
            ($data['proveedor'] ?? '') . ' ' .
            ($data['notas_ia'] ?? '') . ' ' .
            ($data['rut_proveedor'] ?? '')
        );

        // Also check item names for supplier-specific products
        foreach ($data['items'] ?? [] as $item) {
            $allText .= ' ' . mb_strtolower($item['nombre'] ?? '');
        }

        foreach ($knownPatterns as $pattern => $supplierName) {
            if (str_contains($allText, $pattern)) {
                // Only override if current proveedor doesn't already contain the supplier name
                $currentProv = mb_strtolower($data['proveedor'] ?? '');
                if (!str_contains($currentProv, $pattern)) {
                    $data['proveedor'] = $supplierName;
                    $data['notas_ia'] = ($data['notas_ia'] ?? '') . " [Proveedor detectado por patrón '{$pattern}']";
                }
                break;
            }
        }

        return $data;
    }

    /**
     * Apply known supplier rules: metodo_pago, tipo_compra.
     * Called after proveedor is resolved (by AI, RUT, or fuzzy match).
     */
    private function applySupplierRules(array $data): array
    {
        $proveedor = mb_strtolower(trim($data['proveedor'] ?? ''));
        if ($proveedor === '') {
            return $data;
        }

        // Suppliers that always pay by transfer
        $transferSuppliers = [
            'ariztía', 'ariztia', 'ariztía (proveedor)', 'ariztia (proveedor)',
            'agrosuper', 'agrosuper (proveedor)',
            'ideal', 'agro-lucila', 'ariaka', 'jumboapp', 'vanni',
        ];

        foreach ($transferSuppliers as $ts) {
            if (str_contains($proveedor, $ts)) {
                $data['metodo_pago'] = 'transfer';
                break;
            }
        }

        // Ingredient suppliers
        $ingredientSuppliers = ['ariztía', 'ariztia', 'agrosuper', 'ideal', 'agro-lucila'];
        foreach ($ingredientSuppliers as $is) {
            if (str_contains($proveedor, $is)) {
                $data['tipo_compra'] = 'ingredientes';
                break;
            }
        }

        return $data;
    }

    /**
     * Check if the detected proveedor is suspect (likely wrong).
     * Known buyer names, addresses, or generic names.
     */
    private function isProveedorSuspect(?string $proveedor): bool
    {
        if (empty($proveedor)) return true;
        
        $suspects = [
            'yumbel', 'arica', 'la ruta', 'ruta 11', 'ricardo',
            'proveedor desconocido', 'desconocido', 'generico',
        ];
        $lower = mb_strtolower(trim($proveedor));
        
        foreach ($suspects as $s) {
            if (str_contains($lower, $s)) return true;
        }
        
        // Also suspect if proveedor didn't match anything in supplier_index
        $match = $this->sugerenciaService->matchProveedor($proveedor);
        return !$match || $match['score'] < 60;
    }

    /**
     * Infer proveedor from matched items' supplier field.
     * If most matched items come from the same supplier, use that.
     */
    private function inferProveedorFromItems(array $itemsMatch): ?string
    {
        $supplierCounts = [];
        
        foreach ($itemsMatch as $im) {
            if (!$im['pre_selected'] || !$im['match']) continue;
            
            // Get the ingredient's supplier from DB
            $matchId = $im['match']['id'] ?? null;
            $matchType = $im['match_type'] ?? 'ingredient';
            
            if ($matchType === 'ingredient' && $matchId) {
                $supplier = \App\Models\Ingredient::where('id', $matchId)->value('supplier');
                if (!empty($supplier)) {
                    $supplierCounts[$supplier] = ($supplierCounts[$supplier] ?? 0) + 1;
                }
            }
        }
        
        if (empty($supplierCounts)) return null;
        
        // Return the most common supplier
        arsort($supplierCounts);
        return array_key_first($supplierCounts);
    }

    private function removeAccentsSimple(string $str): string
    {
        return strtr($str, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i',
            'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n', 'Ü' => 'u',
        ]);
    }
}
