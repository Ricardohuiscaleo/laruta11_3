<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function customers(Request $request): JsonResponse
    {
        $query = DB::table('usuarios')
            ->leftJoin('tuu_orders', function ($join) {
                $join->on('usuarios.id', '=', 'tuu_orders.user_id')
                    ->where('tuu_orders.payment_status', '=', 'paid');
            })
            ->select([
                'usuarios.id',
                'usuarios.nombre',
                'usuarios.email',
                'usuarios.telefono',
                'usuarios.fecha_registro',
                'usuarios.activo',
                DB::raw('COUNT(tuu_orders.id) as total_orders'),
                DB::raw('COALESCE(SUM(tuu_orders.product_price), 0) as total_spent'),
                DB::raw('MAX(tuu_orders.created_at) as last_order_date'),
            ])
            ->groupBy(
                'usuarios.id',
                'usuarios.nombre',
                'usuarios.email',
                'usuarios.telefono',
                'usuarios.fecha_registro',
                'usuarios.activo'
            );

        $search = $request->query('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('usuarios.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('usuarios.email', 'LIKE', "%{$search}%");
            });
        }

        $customers = $query->orderByDesc('usuarios.id')->get();

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }
}
