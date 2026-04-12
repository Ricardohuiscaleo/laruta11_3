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
            
            $proveedorMatch = null;
            $itemsMatch = [];

            if (!empty($data['proveedor'])) {
                $proveedorMatch = $this->sugerenciaService->matchProveedor($data['proveedor']);
            }

            if (!empty($data['items'])) {
                $itemsMatch = $this->sugerenciaService->matchItems($data['items']);
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
                        $data['items'] = [[
                            'nombre' => $mapping['item'],
                            'cantidad' => 1,
                            'unidad' => 'unidad',
                            'precio_unitario' => $data['monto_total'] ?? 0,
                            'subtotal' => $data['monto_total'] ?? 0,
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
}
