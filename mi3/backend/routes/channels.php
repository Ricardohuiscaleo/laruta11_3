<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels — mi3 Delivery Tracking
|--------------------------------------------------------------------------
| Auth: $user es la instancia de App\Models\Usuario (auth:sanctum).
| El rol admin se verifica a través de la relación personal->isAdmin().
*/

// Canal Monitor: solo admins
Broadcast::channel('delivery.monitor', function ($user) {
    return $user->personal && $user->personal->isAdmin();
});

// Canal Pedido: order_number debe existir en tuu_orders
Broadcast::channel('order.{orderNumber}', function ($user, $orderNumber) {
    return \DB::table('tuu_orders')->where('order_number', $orderNumber)->exists();
});

// Canal Rider: el rider autenticado o un admin
Broadcast::channel('rider.{riderId}', function ($user, $riderId) {
    if ($user->personal && $user->personal->isAdmin()) {
        return true;
    }

    return $user->personal && $user->personal->id == $riderId;
});

// Canal Admin: solo el admin con ese personal_id
Broadcast::channel('admin.{id}', function ($user, $id) {
    return $user->personal && $user->personal->isAdmin() && $user->personal->id == $id;
});
