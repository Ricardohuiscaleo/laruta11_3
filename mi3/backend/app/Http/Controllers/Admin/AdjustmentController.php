<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdjustmentRequest;
use App\Models\AjusteCategoria;
use App\Models\AjusteSueldo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdjustmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $mes = $request->query('mes', now()->format('Y-m'));

        $ajustes = AjusteSueldo::with(['personal', 'categoria'])
            ->where('mes', $mes . '-01')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'personal_id' => $a->personal_id,
                'personal_nombre' => $a->personal?->nombre ?? "#{$a->personal_id}",
                'mes' => $a->mes,
                'monto' => $a->monto,
                'concepto' => $a->concepto,
                'categoria' => $a->categoria?->nombre ?? '',
                'notas' => $a->notas,
            ]);

        return response()->json(['success' => true, 'data' => $ajustes]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AjusteCategoria::all(),
        ]);
    }

    public function store(StoreAdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['mes'] = $data['mes'] . '-01';

        $ajuste = AjusteSueldo::create($data);

        return response()->json(['success' => true, 'data' => $ajuste], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $ajuste = AjusteSueldo::findOrFail($id);
        $ajuste->delete();

        return response()->json(['success' => true]);
    }
}
