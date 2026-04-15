<?php

namespace App\Console\Commands;

use App\Events\OrderStatusUpdated;
use App\Events\RiderLocationUpdated;
use App\Models\DeliveryAssignment;
use App\Models\RiderLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateDeliveryCommand extends Command
{
    protected $signature = 'delivery:simulate {--steps=20 : Number of GPS steps per rider}';
    protected $description = 'Simula riders moviéndose hacia destinos de delivery en tiempo real';

    // Arica center and real addresses with coords
    private array $destinations = [
        ['addr' => 'Los Olivos 2264, Arica', 'lat' => -18.4730, 'lng' => -70.3050],
        ['addr' => 'Ginebra 3913, Arica', 'lat' => -18.4850, 'lng' => -70.3180],
        ['addr' => 'Coihueco 550, Arica', 'lat' => -18.4700, 'lng' => -70.3100],
        ['addr' => 'Av Arturo Prat 456, Arica', 'lat' => -18.4760, 'lng' => -70.3140],
        ['addr' => 'Patricio Lynch 540, Arica', 'lat' => -18.4790, 'lng' => -70.3090],
        ['addr' => 'Av Santa Maria 2100, Arica', 'lat' => -18.4810, 'lng' => -70.3220],
        ['addr' => 'Sotomayor 430, Arica', 'lat' => -18.4775, 'lng' => -70.3115],
        ['addr' => 'Baquedano 750, Arica', 'lat' => -18.4740, 'lng' => -70.3070],
        ['addr' => 'Av Diego Portales 1200, Arica', 'lat' => -18.4830, 'lng' => -70.3160],
        ['addr' => 'Av Comandante San Martin 800, Arica', 'lat' => -18.4720, 'lng' => -70.3200],
    ];

    // Food truck location — Yumbel 2629, Arica (from food_trucks table)
    private float $startLat = -18.47141320;
    private float $startLng = -70.28881320;

    public function handle(): int
    {
        $steps = (int) $this->option('steps');
        $this->info("Simulación de delivery iniciada ({$steps} pasos por rider)...");

        // Get riders with role 'rider'
        $riders = DB::table('personal')
            ->where('activo', 1)
            ->where('rol', 'LIKE', '%rider%')
            ->select('id', 'nombre')
            ->get();

        if ($riders->isEmpty()) {
            $this->error('No hay riders activos');
            return self::FAILURE;
        }

        $this->info("Riders: " . $riders->pluck('nombre')->join(', '));

        // Clean up old test data
        DB::table('tuu_orders')->where('order_number', 'LIKE', 'SIM-%')->delete();
        DB::table('delivery_assignments')->where('notes', 'simulation')->delete();

        // Create orders — one per rider, pick random destinations
        $orders = [];
        foreach ($riders as $i => $rider) {
            $dest = $this->destinations[$i % count($this->destinations)];
            $orderNum = 'SIM-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT);

            $orderId = DB::table('tuu_orders')->insertGetId([
                'order_number' => $orderNum,
                'customer_name' => 'Cliente Sim ' . ($i + 1),
                'customer_phone' => '+5691234' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'product_name' => 'Pedido Simulado #' . ($i + 1),
                'product_price' => rand(3000, 12000),
                'installment_amount' => rand(3000, 12000),
                'delivery_fee' => rand(1000, 2500),
                'delivery_type' => 'delivery',
                'delivery_address' => $dest['addr'],
                'order_status' => 'preparing',
                'payment_status' => 'paid',
                'payment_method' => 'webpay',
                'status' => 'completed',
                'rider_id' => $rider->id,
                'created_at' => now(),
            ]);

            // Create assignment
            DeliveryAssignment::create([
                'order_id' => $orderId,
                'rider_id' => $rider->id,
                'assigned_by' => 5, // Ricardo (admin)
                'status' => 'assigned',
                'notes' => 'simulation',
            ]);

            $orders[] = [
                'id' => $orderId,
                'number' => $orderNum,
                'rider_id' => $rider->id,
                'rider_name' => $rider->nombre,
                'dest_lat' => $dest['lat'],
                'dest_lng' => $dest['lng'],
            ];

            $this->info("  Pedido {$orderNum} → {$rider->nombre} → {$dest['addr']}");
        }

        // Broadcast initial status
        foreach ($orders as $o) {
            broadcast(new OrderStatusUpdated(
                orderId: $o['id'],
                orderNumber: $o['number'],
                orderStatus: 'preparing',
                riderId: $o['rider_id'],
                estimatedDeliveryTime: now()->addMinutes(15)->toISOString(),
                updatedAt: now()->toISOString(),
            ));
        }

        $this->info("\nSimulando movimiento GPS ({$steps} pasos, 3s entre cada uno)...");
        $this->info("  Fase 1: Riders → La Ruta 11 (recogida)");
        $this->info("  Fase 2: La Ruta 11 → Destino (entrega)");

        // Each rider starts from a random nearby position
        $riderStarts = [];
        foreach ($orders as $o) {
            $riderStarts[$o['rider_id']] = [
                'lat' => $this->startLat + (rand(-30, 30) / 10000),
                'lng' => $this->startLng + (rand(-30, 30) / 10000),
            ];
        }

        $halfSteps = (int)($steps / 2);

        // Simulate movement
        for ($step = 0; $step < $steps; $step++) {
            $isPhase1 = $step < $halfSteps;
            $phaseProgress = $isPhase1
                ? ($step + 1) / $halfSteps
                : ($step - $halfSteps + 1) / ($steps - $halfSteps);

            foreach ($orders as &$o) {
                $start = $riderStarts[$o['rider_id']];

                if ($isPhase1) {
                    // Phase 1: rider start → La Ruta 11
                    $lat = $start['lat'] + ($this->startLat - $start['lat']) * $phaseProgress;
                    $lng = $start['lng'] + ($this->startLng - $start['lng']) * $phaseProgress;
                } else {
                    // Phase 2: La Ruta 11 → destination
                    $lat = $this->startLat + ($o['dest_lat'] - $this->startLat) * $phaseProgress;
                    $lng = $this->startLng + ($o['dest_lng'] - $this->startLng) * $phaseProgress;
                }

                // Add small random jitter for realism
                $lat += (rand(-3, 3) / 100000);
                $lng += (rand(-3, 3) / 100000);

                // Persist location
                RiderLocation::create([
                    'rider_id' => $o['rider_id'],
                    'latitud' => $lat,
                    'longitud' => $lng,
                    'precision_metros' => rand(5, 20),
                    'velocidad_kmh' => rand(15, 40),
                ]);

                // Update tuu_orders
                DB::table('tuu_orders')->where('id', $o['id'])->update([
                    'rider_last_lat' => $lat,
                    'rider_last_lng' => $lng,
                ]);

                // Broadcast location
                broadcast(new RiderLocationUpdated(
                    riderId: $o['rider_id'],
                    nombre: $o['rider_name'],
                    latitud: $lat,
                    longitud: $lng,
                    pedidoAsignadoId: $o['id'],
                    pedidoAsignadoOrderNumber: $o['number'],
                ));

                // Status transitions based on phases
                if ($step === $halfSteps - 1) {
                    // Arrived at La Ruta 11 → ready for pickup
                    DB::table('tuu_orders')->where('id', $o['id'])->update(['order_status' => 'ready']);
                    broadcast(new OrderStatusUpdated($o['id'], $o['number'], 'ready', $o['rider_id'], null, now()->toISOString()));
                    $this->info("  [{$o['number']}] → ready (arrived at La Ruta 11)");
                }
                if ($step === $halfSteps) {
                    // Picked up → out for delivery
                    DB::table('tuu_orders')->where('id', $o['id'])->update(['order_status' => 'out_for_delivery']);
                    DB::table('delivery_assignments')->where('order_id', $o['id'])->where('notes', 'simulation')->update(['status' => 'picked_up', 'picked_up_at' => now()]);
                    broadcast(new OrderStatusUpdated($o['id'], $o['number'], 'out_for_delivery', $o['rider_id'], null, now()->toISOString()));
                    $this->info("  [{$o['number']}] → out_for_delivery (heading to customer)");
                }
            }

            $this->output->write("\r  Paso " . ($step + 1) . "/{$steps} (" . round($progress * 100) . "%)");
            sleep(3);
        }

        $this->newLine();
        $this->info("\nEntregando pedidos...");

        // Mark all as delivered
        foreach ($orders as $o) {
            DB::table('tuu_orders')->where('id', $o['id'])->update(['order_status' => 'delivered']);
            DB::table('delivery_assignments')->where('order_id', $o['id'])->where('notes', 'simulation')->update(['status' => 'delivered', 'delivered_at' => now()]);
            broadcast(new OrderStatusUpdated($o['id'], $o['number'], 'delivered', $o['rider_id'], null, now()->toISOString()));
            $this->info("  [{$o['number']}] → delivered ✅");
        }

        $this->info("\n✅ Simulación completada.");
        return self::SUCCESS;
    }
}
