<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Services\Compra\CompraService;
use App\Services\Compra\ImagenService;
use App\Services\Compra\SugerenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompraController extends Controller
{
    public function __construct(
        private CompraService $compraService,
        private SugerenciaService $sugerenciaService,
        private ImagenService $imagenService,
    ) {}

    /**
     * Registro atómico de compra.
     * POST /api/v1/admin/compras
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_compra' => 'required|date',
            'proveedor'    => 'required|string|max:255',
            'tipo_compra'  => 'required|string|in:ingredientes,insumos,equipamiento,otros',
            'monto_total'  => 'required|numeric|min:0',
            'metodo_pago'  => 'required|string|in:cash,transfer,card,credit',
            'items'        => 'required|array|min:1',
            'items.*.nombre_item'     => 'required|string',
            'items.*.cantidad'        => 'required|numeric|min:0.01',
            'items.*.unidad'          => 'required|string',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'items.*.subtotal'        => 'required|numeric|min:0',
        ]);

        try {
            $result = $this->compraService->registrar($request->all());

            // Move temp images to definitivo if provided
            $imagenes = [];
            $tempKeys = $request->input('temp_keys', []);
            if (!empty($tempKeys)) {
                $imagenes = $this->imagenService->asociarImagenes($result['compra_id'], $tempKeys);
            }

            return response()->json([
                'success'     => true,
                'compra_id'   => $result['compra_id'],
                'saldo_nuevo' => $result['saldo_nuevo'],
                'imagenes'    => $imagenes,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error registrando compra', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->except(['temp_keys']),
            ]);
            return response()->json([
                'success' => false,
                'error'   => 'Error al registrar compra: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Historial paginado de compras con búsqueda.
     * GET /api/v1/admin/compras
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = 50;
        $page = max(1, (int) $request->query('page', 1));
        $search = $request->query('q', '');

        $query = Compra::with('detalles')->orderBy('fecha_compra', 'desc')->orderBy('id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('proveedor', 'LIKE', "%{$search}%")
                  ->orWhere('notas', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();
        $compras = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'success'       => true,
            'compras'       => $compras,
            'total_compras' => $total,
            'page'          => $page,
            'total_pages'   => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Detalle de una compra.
     * GET /api/v1/admin/compras/{id}
     */
    public function show(int $id): JsonResponse
    {
        $compra = Compra::with('detalles')->find($id);

        if (!$compra) {
            return response()->json(['success' => false, 'error' => 'Compra no encontrada'], 404);
        }

        return response()->json(['success' => true, 'compra' => $compra]);
    }

    /**
     * Eliminar compra con rollback de stock.
     * DELETE /api/v1/admin/compras/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->compraService->eliminar($id);

            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Compra no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Error al eliminar compra: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Búsqueda fuzzy de ingredientes y productos.
     * GET /api/v1/admin/compras/items
     */
    public function items(Request $request): JsonResponse
    {
        $query = $request->query('q', '');

        if ($query === '') {
            return response()->json([]);
        }

        $results = $this->compraService->buscarItems($query);

        return response()->json($results);
    }

    /**
     * Autocompletado de proveedores.
     * GET /api/v1/admin/compras/proveedores
     */
    public function proveedores(Request $request): JsonResponse
    {
        $query = $request->query('q');

        $results = $this->compraService->getProveedores($query);

        return response()->json($results);
    }

    /**
     * Crear nuevo ingrediente.
     * POST /api/v1/admin/compras/ingrediente
     */
    public function crearIngrediente(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'unit'     => 'nullable|string|max:50',
        ]);

        try {
            $ingrediente = $this->compraService->crearIngrediente($request->all());

            return response()->json(['success' => true, 'ingrediente' => $ingrediente]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Error al crear ingrediente: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload temporal de imagen (pre-registro).
     * POST /api/v1/admin/compras/upload-temp
     */
    public function uploadTemp(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            $result = $this->imagenService->uploadTemp($request->file('image'));

            return response()->json([
                'success' => true,
                'tempUrl' => $result['tempUrl'],
                'tempKey' => $result['tempKey'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Error al subir imagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subir imagen a compra existente.
     * POST /api/v1/admin/compras/{id}/imagen
     */
    public function uploadImagen(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $compra = Compra::find($id);

        if (!$compra) {
            return response()->json(['success' => false, 'error' => 'Compra no encontrada'], 404);
        }

        try {
            $file = $request->file('image');
            $timestamp = time();
            $key = "compras/respaldo_{$id}_{$timestamp}.jpg";

            $path = Storage::disk('s3')->put($key, file_get_contents($file->getRealPath()));

            $url = Storage::disk('s3')->url($key);

            $imagenes = $compra->imagen_respaldo ?? [];
            $imagenes[] = $url;
            $compra->imagen_respaldo = $imagenes;
            $compra->save();

            return response()->json([
                'success' => true,
                'url'     => $url,
                'imagenes' => $imagenes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Error al subir imagen: ' . $e->getMessage(),
            ], 500);
        }
    }
}
