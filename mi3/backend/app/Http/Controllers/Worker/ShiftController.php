<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Services\Shift\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $personal = $request->get('personal');
        $mes = $request->query('mes', now()->format('Y-m'));

        $turnos = $this->shiftService->getShiftsForPersonal($personal->id, $mes);

        return response()->json([
            'success' => true,
            'data' => [
                'mes' => $mes,
                'turnos' => $turnos,
            ],
        ]);
    }
}
