<?php
/**
 * Helper centralizado para leer configuración de delivery desde BD.
 * Usado por: get_config.php, get_delivery_fee.php, create_order.php
 *
 * @param PDO $pdo Conexión PDO activa a la BD laruta11
 * @return array Configuración con valores tipados (int para montos, float para factor)
 */
function get_delivery_config(PDO $pdo): array {
    // Defaults de producción — fallback si la tabla no existe o la consulta falla
    $defaults = [
        'tarifa_base'           => 3500,
        'card_surcharge'        => 500,
        'distance_threshold_km' => 6,
        'surcharge_per_bracket' => 1000,
        'bracket_size_km'       => 2,
        'rl6_discount_factor'   => 0.2857,
    ];

    try {
        $stmt = $pdo->query("SELECT config_key, config_value FROM delivery_config");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if (empty($rows)) {
            return $defaults;
        }

        $config = $defaults; // empezar con defaults para claves faltantes
        $int_keys = ['tarifa_base', 'card_surcharge', 'distance_threshold_km', 'surcharge_per_bracket', 'bracket_size_km'];

        foreach ($defaults as $key => $default_value) {
            if (isset($rows[$key]) && is_numeric($rows[$key])) {
                if (in_array($key, $int_keys, true)) {
                    $config[$key] = (int) $rows[$key];
                } else {
                    $config[$key] = (float) $rows[$key];
                }
            }
            // Si no existe en BD o no es numérico, queda el default
        }

        return $config;
    } catch (Exception $e) {
        // Tabla no existe, error de conexión, etc. → usar defaults
        return $defaults;
    }
}
