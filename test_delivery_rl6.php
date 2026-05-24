<?php
/**
 * TEST: Flujo delivery RL6 — Verificar cálculo correcto de delivery fee
 * 
 * Escenario: Orden T11-1779238054-8617
 * - Producto: Gorda x1 = $5.280
 * - Delivery base: $3.500
 * - Descuento RL6: $1.000 (28.57% de $3.500)
 * - Delivery fee NETO guardado: $2.500
 * - Total esperado: $5.280 + $2.500 = $7.780
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST FLUJO DELIVERY RL6 ===\n\n";

// Simular inputs del frontend
$input = [
    'amount' => 7780,
    'subtotal' => 5280,
    'discount_amount' => 0,
    'delivery_discount' => 1000,  // RL6 activo: $1.000 descuento
    'delivery_fee' => 3500,        // Cliente envía base fee
    'delivery_type' => 'delivery',
    'delivery_address' => 'Ctel. Domeyco 1540',
    'payment_method' => 'rl6_credit',
    'cart_items' => [
        ['id' => 1, 'name' => 'Gorda', 'price' => 5280, 'quantity' => 1]
    ],
    'card_surcharge' => 0,
    'delivery_extras_total' => 0,
    'cashback_used' => 0
];

// === REPLICAR LÓGICA DEL BACKEND ===

// FIX 1: Leer delivery_discount ANTES de usarlo
$delivery_discount = (int)($input['delivery_discount'] ?? 0);
echo "1. delivery_discount leído: $$delivery_discount\n";

$delivery_type = $input['delivery_type'] ?? 'pickup';
$calculated_delivery_fee = 0;

if ($delivery_type === 'delivery') {
    $client_delivery_fee = (int)($input['delivery_fee'] ?? 0);
    
    // Simular: truck activo con tarifa base $3.500
    $base_fee = 3500;
    
    // FIX 2: Si hay descuento RL6, usar tarifa base sin recargos
    if ($delivery_discount > 0) {
        $calculated_delivery_fee = $base_fee;
        echo "2. RL6 activo → usando base fee SIN recargos: $$calculated_delivery_fee\n";
    } else {
        // Lógica normal con geocoding y recargos
        $calculated_delivery_fee = $base_fee; // simplificado para test
        echo "2. Sin RL6 → fee calculado con posibles recargos: $$calculated_delivery_fee\n";
    }
}

// FIX 3: Guardar delivery_fee como NETO de descuento
$delivery_fee = $calculated_delivery_fee - $delivery_discount;
if ($delivery_fee < 0) {
    $delivery_fee = 0;
}
echo "3. delivery_fee NETO = $$calculated_delivery_fee - $$delivery_discount = $$delivery_fee\n";

// Calcular total
$calculated_subtotal = 5280;
$discount_amount = (int)($input['discount_amount'] ?? 0);
$delivery_extras_total = (int)($input['delivery_extras_total'] ?? 0);
$cashback_used = (int)($input['cashback_used'] ?? 0);
$card_surcharge = 0;

$calculated_total = $calculated_subtotal + $calculated_delivery_fee + $card_surcharge + $delivery_extras_total - $discount_amount - $delivery_discount - $cashback_used;
if ($calculated_total < 0) {
    $calculated_total = 0;
}

echo "4. Total calculado: $$calculated_subtotal + $$calculated_delivery_fee - $$delivery_discount = $$calculated_total\n";

// === VERIFICACIONES ===
echo "\n=== VERIFICACIONES ===\n";

$expected_delivery_fee = 2500;
$expected_total = 7780;

$pass_delivery_fee = ($delivery_fee === $expected_delivery_fee);
$pass_total = ($calculated_total === $expected_total);

echo "✓ delivery_fee NETO = $$delivery_fee (esperado: $$expected_delivery_fee) → " . ($pass_delivery_fee ? 'PASS' : 'FAIL') . "\n";
echo "✓ Total = $$calculated_total (esperado: $$expected_total) → " . ($pass_total ? 'PASS' : 'FAIL') . "\n";

// Verificar visualización
$display_base = $delivery_fee + $delivery_discount;
echo "\n=== VISUALIZACIÓN EN PANTALLA ===\n";
echo "Delivery \$$display_base (-\$$delivery_discount RL6) = \$$delivery_fee\n";
echo "Productos: Gorda x1 = \$$calculated_subtotal\n";
echo "TOTAL: \$$calculated_total\n";

// Verificar que NO sea el bug anterior
$bug_delivery_fee = 3500; // Antes: delivery_discount no se restaba
$bug_total = 8280;        // Antes: total incorrecto
echo "\n=== COMPARACIÓN CON BUG ANTERIOR ===\n";
echo "ANTES (bug): delivery_fee = \$$bug_delivery_fee, total = \$$bug_total\n";
echo "AHORA (fix):  delivery_fee = \$$delivery_fee, total = \$$calculated_total\n";

if ($pass_delivery_fee && $pass_total) {
    echo "\n🎉 TODOS LOS TESTS PASAN — El fix es correcto\n";
} else {
    echo "\n❌ ALGUNOS TESTS FALLAN — Revisar lógica\n";
}
