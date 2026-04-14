<?php

namespace App\Services\Delivery;

use App\Events\RiderLocationUpdated;
use App\Models\Personal;
use App\Models\RiderLocation;
use Illuminate\Support\Facades\DB;

class LocationService
{
    /**
     * Límite máximo de posiciones GPS almacenadas por rider.
     */
    private const MAX_LOCATIONS_PER_RIDER = 100;

    /**
     * Persiste la posición GPS del rider, actualiza rider_last_lat/lng en tuu_orders
     * del pedido activo, poda registros antiguos y emite RiderLocationUpdated.
     */
    public function updateRiderLocation(
        int $riderId,
        float $lat,
        float $lng,
        int $precision = 0,
        ?float $speed = null,
        ?float $heading = null
    ): RiderLocation {
        // 1. Persistir en rider_locations
        $location = RiderLocation::create([
            'rider_id'         => $riderId,
            'latitud'          => $lat,
            'longitud'         => $lng,
            'precision_metros' => $precision,
            'velocidad_kmh'    => $speed,
            'heading'          => $heading,
        ]);

        // 2. Buscar pedido activo asignado al rider
        $activeAssignment = DB::table('delivery_assignments as da')
            ->join('tuu_orders as o', 'da.order_id', '=', 'o.id')
            ->where('da.rider_id', $riderId)
            ->whereIn('da.status', ['assigned', 'picked_up'])
            ->select(['da.order_id', 'o.order_number'])
            ->first();

        // 3. Actualizar rider_last_lat/lng en tuu_orders si hay pedido activo
        if ($activeAssignment) {
            DB::table('tuu_orders')
                ->where('id', $activeAssignment->order_id)
                ->update([
                    'rider_last_lat' => $lat,
                    'rider_last_lng' => $lng,
                ]);
        }

        // 4. Podar registros antiguos
        $this->pruneOldLocations($riderId);

        // 5. Emitir evento de broadcast
        $rider = Personal::find($riderId);

        broadcast(new RiderLocationUpdated(
            riderId: $riderId,
            nombre: $rider?->nombre ?? '',
            latitud: $lat,
            longitud: $lng,
            pedidoAsignadoId: $activeAssignment?->order_id,
            pedidoAsignadoOrderNumber: $activeAssignment?->order_number,
        ));

        return $location;
    }

    /**
     * Elimina registros de rider_locations más antiguos si el total supera 100.
     */
    public function pruneOldLocations(int $riderId): void
    {
        $count = DB::table('rider_locations')
            ->where('rider_id', $riderId)
            ->count();

        if ($count > self::MAX_LOCATIONS_PER_RIDER) {
            $excess = $count - self::MAX_LOCATIONS_PER_RIDER;

            // Obtener los IDs más antiguos a eliminar
            $idsToDelete = DB::table('rider_locations')
                ->where('rider_id', $riderId)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit($excess)
                ->pluck('id');

            DB::table('rider_locations')
                ->whereIn('id', $idsToDelete)
                ->delete();
        }
    }
}
