<?php

declare(strict_types=1);

namespace App\Services\CierreDiario;

use App\Models\CapitalTrabajo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CierreDiarioService
{
    /**
     * Calcula y persiste el cierre diario para una fecha.
     * Idempotente: usa updateOrCreate por fecha.
     *
     * @return array{success: bool, data: CapitalTrabajo, warnings: string[]}
     */
    public function cerrar(string $fecha): array
    {
        $warnings = [];
        $fechaCarbon = Carbon::parse($fecha);

        // 1. Saldo inicial = saldo_final del día anterior
        $anterior = CapitalTrabajo::where('fecha', $fechaCarbon->copy()->subDay()->toDateString())->first();
        $saldoInicial = 0.0;
        if ($anterior) {
            $saldoInicial = (float) $anterior->saldo_final;
        } else {
            $warnings[] = 'Sin registro día anterior — saldo_inicial = 0';
        }

        // 2. Ingresos ventas (turno 17:00–04:00 UTC-3 = 20:00–07:00 UTC)
        $ingresos = $this->calcularIngresos($fecha);

        // 3. Egresos compras del día
        $egresosCompras = $this->calcularEgresosCompras($fecha);

        // 4. Egresos gastos (consumos + retiros caja)
        $egresosGastosData = $this->calcularEgresosGastos($fecha);
        $egresosGastos = $egresosGastosData['total'];
        $desgloseGastos = $egresosGastosData['desglose'];

        // 5. Saldo final
        $saldoFinal = $saldoInicial + $ingresos['total'] - $egresosCompras - $egresosGastos;

        // 6. Persistir (idempotente)
        $notas = !empty($warnings) ? implode('; ', $warnings) : null;

        $registro = CapitalTrabajo::updateOrCreate(
            ['fecha' => $fecha],
            [
                'saldo_inicial' => $saldoInicial,
                'ingresos_ventas' => $ingresos['total'],
                'desglose_ingresos' => $ingresos['desglose'],
                'egresos_compras' => $egresosCompras,
                'egresos_gastos' => $egresosGastos,
                'desglose_gastos' => $desgloseGastos,
                'saldo_final' => $saldoFinal,
                'notas' => $notas,
            ]
        );

        return [
            'success' => true,
            'data' => $registro,
            'warnings' => $warnings,
        ];
    }

    /**
     * Resumen mensual de capital de trabajo.
     *
     * @return array{dias: array, totales: array}
     */
    public function getResumenMensual(string $mes): array
    {
        $inicio = Carbon::parse($mes . '-01');
        $fin = $inicio->copy()->endOfMonth();
        $hoy = Carbon::today();

        $registros = CapitalTrabajo::whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->orderBy('fecha')
            ->get()
            ->keyBy(fn (CapitalTrabajo $r) => $r->fecha->toDateString());

        $dias = [];
        $totalIngresos = 0.0;
        $totalEgresosCompras = 0.0;
        $totalEgresosGastos = 0.0;

        $cursor = $inicio->copy();
        while ($cursor->lte($fin) && $cursor->lte($hoy)) {
            $key = $cursor->toDateString();
            if ($registros->has($key)) {
                $r = $registros->get($key);
                $dias[] = [
                    'fecha' => $key,
                    'saldo_inicial' => (float) $r->saldo_inicial,
                    'ingresos_ventas' => (float) $r->ingresos_ventas,
                    'egresos_compras' => (float) $r->egresos_compras,
                    'egresos_gastos' => (float) $r->egresos_gastos,
                    'saldo_final' => (float) $r->saldo_final,
                    'desglose_ingresos' => $r->desglose_ingresos,
                    'desglose_gastos' => $r->desglose_gastos,
                    'status' => 'cerrado',
                ];
                $totalIngresos += (float) $r->ingresos_ventas;
                $totalEgresosCompras += (float) $r->egresos_compras;
                $totalEgresosGastos += (float) $r->egresos_gastos;
            } else {
                $dias[] = [
                    'fecha' => $key,
                    'saldo_inicial' => null,
                    'ingresos_ventas' => null,
                    'egresos_compras' => null,
                    'egresos_gastos' => null,
                    'saldo_final' => null,
                    'desglose_ingresos' => null,
                    'desglose_gastos' => null,
                    'status' => 'sin_cierre',
                ];
            }
            $cursor->addDay();
        }

        $primerSaldo = $registros->first()?->saldo_inicial ?? 0;
        $ultimoSaldo = $registros->last()?->saldo_final ?? 0;

        return [
            'dias' => $dias,
            'totales' => [
                'total_ingresos' => $totalIngresos,
                'total_egresos_compras' => $totalEgresosCompras,
                'total_egresos_gastos' => $totalEgresosGastos,
                'variacion_neta' => $ultimoSaldo - $primerSaldo,
                'saldo_inicial_mes' => $primerSaldo,
                'saldo_final_mes' => $ultimoSaldo,
            ],
        ];
    }

    /**
     * Calcula ingresos del turno: tuu_orders pagadas + ingresos manuales caja.
     * Turno: 17:00–04:00 UTC-3 = 20:00–07:00 UTC del día siguiente.
     *
     * @return array{total: float, desglose: array}
     */
    private function calcularIngresos(string $fecha): array
    {
        // Turno: fecha 20:00 UTC → fecha+1 07:00 UTC
        $turnoInicio = Carbon::parse($fecha)->setTime(20, 0, 0); // 17:00 Chile = 20:00 UTC
        $turnoFin = Carbon::parse($fecha)->addDay()->setTime(7, 0, 0); // 04:00 Chile = 07:00 UTC

        // Órdenes pagadas en el rango del turno
        $ordenes = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$turnoInicio, $turnoFin])
            ->select('payment_method', DB::raw('SUM(installment_amount - COALESCE(delivery_fee, 0)) as total'))
            ->groupBy('payment_method')
            ->get();

        $desglose = [];
        $totalOrdenes = 0.0;
        foreach ($ordenes as $row) {
            $method = $row->payment_method ?? 'otros';
            $desglose[$method] = (float) $row->total;
            $totalOrdenes += (float) $row->total;
        }

        // Ingresos manuales en caja (sin order_reference) del día
        $fechaInicio = Carbon::parse($fecha)->startOfDay();
        $fechaFin = Carbon::parse($fecha)->endOfDay();

        $manualCash = (float) DB::table('caja_movimientos')
            ->where('tipo', 'ingreso')
            ->whereNull('order_reference')
            ->whereBetween('fecha_movimiento', [$fechaInicio, $fechaFin])
            ->sum('monto');

        if ($manualCash > 0) {
            $desglose['manual_cash'] = $manualCash;
        }

        $total = $totalOrdenes + $manualCash;

        return [
            'total' => $total,
            'desglose' => $desglose,
        ];
    }

    /**
     * Calcula egresos por compras del día.
     */
    private function calcularEgresosCompras(string $fecha): float
    {
        return (float) DB::table('compras')
            ->whereDate('fecha_compra', $fecha)
            ->sum('monto_total');
    }

    /**
     * Calcula egresos por gastos: consumos operacionales + retiros de caja.
     *
     * @return array{total: float, desglose: array}
     */
    private function calcularEgresosGastos(string $fecha): array
    {
        $fechaInicio = Carbon::parse($fecha)->startOfDay();
        $fechaFin = Carbon::parse($fecha)->endOfDay();

        // Consumos por categoría de ingrediente
        $consumos = DB::table('inventory_transactions as it')
            ->join('ingredients as i', 'it.ingredient_id', '=', 'i.id')
            ->where('it.transaction_type', 'consumption')
            ->whereBetween('it.created_at', [$fechaInicio, $fechaFin])
            ->select(
                'i.category',
                DB::raw('SUM(ABS(it.quantity) * i.cost_per_unit) as total_cost')
            )
            ->groupBy('i.category')
            ->get();

        $consumoGas = 0.0;
        $consumoLimpieza = 0.0;
        $consumoServicios = 0.0;

        foreach ($consumos as $row) {
            $cost = (float) $row->total_cost;
            match ($row->category) {
                'Gas' => $consumoGas += $cost,
                'Limpieza' => $consumoLimpieza += $cost,
                'Servicios' => $consumoServicios += $cost,
                default => $consumoServicios += $cost,
            };
        }

        // Retiros de caja del día
        $retirosCaja = (float) DB::table('caja_movimientos')
            ->where('tipo', 'retiro')
            ->whereBetween('fecha_movimiento', [$fechaInicio, $fechaFin])
            ->sum('monto');

        $total = $consumoGas + $consumoLimpieza + $consumoServicios + $retirosCaja;

        return [
            'total' => $total,
            'desglose' => [
                'consumo_gas' => $consumoGas,
                'consumo_limpieza' => $consumoLimpieza,
                'consumo_servicios' => $consumoServicios,
                'retiros_caja' => $retirosCaja,
            ],
        ];
    }
}
