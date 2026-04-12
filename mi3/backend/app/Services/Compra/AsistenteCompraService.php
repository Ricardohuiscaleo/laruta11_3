<?php

namespace App\Services\Compra;

use App\Models\Ingredient;
use App\Models\ProductEquivalence;
use Illuminate\Support\Facades\DB;

/**
 * Asistente inteligente de compras.
 * 
 * Flujo: Foto → IA detecta producto → sistema pregunta precio y proveedor → auto-completa.
 * No intenta adivinar todo. Pregunta lo que no sabe.
 */
class AsistenteCompraService
{
    /**
     * Procesar resultado de extracción IA y generar preguntas pendientes.
     * 
     * Retorna los datos que ya sabe + las preguntas que necesita del usuario.
     */
    public function procesarExtraccion(array $extractionData): array
    {
        $tipoImagen = $extractionData['tipo_imagen'] ?? 'desconocido';
        $items = $extractionData['items'] ?? [];
        $preguntas = [];
        $itemsResueltos = [];

        foreach ($items as $item) {
            $nombreDetectado = $item['nombre'] ?? $item['nombre_item'] ?? '';
            if (empty($nombreDetectado)) continue;

            // Buscar equivalencia conocida
            $equiv = $this->buscarEquivalencia($nombreDetectado);
            
            // Buscar ingrediente en BD
            $ingrediente = $this->buscarIngrediente($nombreDetectado);

            $itemResuelto = [
                'nombre_detectado' => $nombreDetectado,
                'cantidad_detectada' => $item['cantidad'] ?? $item['cantidad_estimada'] ?? null,
                'unidad_detectada' => $item['unidad'] ?? null,
                'precio_detectado' => $item['precio_unitario'] ?? null,
            ];

            if ($equiv) {
                // Tenemos equivalencia: 1 caja = 6 kg
                $itemResuelto['equivalencia'] = [
                    'id' => $equiv->id,
                    'nombre_ingrediente' => $equiv->nombre_ingrediente,
                    'ingrediente_id' => $equiv->ingrediente_id,
                    'cantidad_por_unidad' => $equiv->cantidad_por_unidad,
                    'unidad_visual' => $equiv->unidad_visual,
                    'unidad_real' => $equiv->unidad_real,
                    'ultimo_precio' => $equiv->ultimo_precio_unidad_visual,
                ];

                // Calcular cantidad real
                $cantidadVisual = $item['cantidad'] ?? $item['cantidad_estimada'] ?? 1;
                $itemResuelto['cantidad_real'] = $cantidadVisual * $equiv->cantidad_por_unidad;
                $itemResuelto['unidad_real'] = $equiv->unidad_real;
                $itemResuelto['ingrediente_id'] = $equiv->ingrediente_id;
                $itemResuelto['nombre_ingrediente'] = $equiv->nombre_ingrediente;

                // Si no tiene precio, preguntar
                if (empty($item['precio_unitario']) && empty($item['subtotal'])) {
                    $preguntas[] = [
                        'tipo' => 'precio',
                        'item_index' => count($itemsResueltos),
                        'pregunta' => "¿Precio por {$equiv->unidad_visual} de {$equiv->nombre_ingrediente}?",
                        'placeholder' => $equiv->ultimo_precio_unidad_visual 
                            ? '$' . number_format($equiv->ultimo_precio_unidad_visual, 0, ',', '.') . ' (último precio)'
                            : '',
                        'ultimo_precio' => $equiv->ultimo_precio_unidad_visual,
                    ];
                }
            } elseif ($ingrediente) {
                // Ingrediente conocido pero sin equivalencia de caja/bolsa
                $itemResuelto['ingrediente_id'] = $ingrediente->id;
                $itemResuelto['nombre_ingrediente'] = $ingrediente->name;
                $itemResuelto['unidad_real'] = $ingrediente->unit;

                if (empty($item['precio_unitario'])) {
                    $ultimoPrecio = $this->getUltimoPrecio($ingrediente->id);
                    $preguntas[] = [
                        'tipo' => 'precio',
                        'item_index' => count($itemsResueltos),
                        'pregunta' => "¿Precio de {$ingrediente->name}?",
                        'placeholder' => $ultimoPrecio 
                            ? '$' . number_format($ultimoPrecio, 0, ',', '.') . '/' . $ingrediente->unit
                            : '',
                        'ultimo_precio' => $ultimoPrecio,
                    ];
                }
            } else {
                // Producto desconocido — preguntar todo
                $preguntas[] = [
                    'tipo' => 'identificar',
                    'item_index' => count($itemsResueltos),
                    'pregunta' => "¿Qué producto es \"{$nombreDetectado}\"?",
                    'sugerencias' => $this->buscarSugerenciasIngrediente($nombreDetectado),
                ];
            }

            $itemsResueltos[] = $itemResuelto;
        }

        // Preguntar proveedor si no se detectó
        $proveedorDetectado = $extractionData['proveedor'] ?? null;
        if (empty($proveedorDetectado) || $tipoImagen === 'producto') {
            // Para fotos de producto, siempre preguntar proveedor
            $proveedoresConocidos = $this->getProveedoresParaItems($itemsResueltos);
            $preguntas[] = [
                'tipo' => 'proveedor',
                'pregunta' => '¿Dónde compraste?',
                'opciones' => $proveedoresConocidos,
            ];
        }

        return [
            'tipo_imagen' => $tipoImagen,
            'items' => $itemsResueltos,
            'proveedor_detectado' => $proveedorDetectado,
            'preguntas' => $preguntas,
            'notas_ia' => $extractionData['notas_ia'] ?? null,
        ];
    }

    /**
     * Buscar equivalencia conocida para un nombre visual.
     * Ej: "caja de tomates" → ProductEquivalence con cantidad_por_unidad=6, unidad_real=kg
     */
    private function buscarEquivalencia(string $nombre): ?ProductEquivalence
    {
        $normalizado = mb_strtolower(trim($nombre));

        // Buscar match exacto primero
        $equiv = ProductEquivalence::where('nombre_normalizado', $normalizado)->first();
        if ($equiv) return $equiv;

        // Buscar match parcial
        $equiv = ProductEquivalence::where('nombre_normalizado', 'LIKE', "%{$normalizado}%")
            ->orWhere(DB::raw("'{$normalizado}'"), 'LIKE', DB::raw("CONCAT('%', nombre_normalizado, '%')"))
            ->orderBy('veces_confirmado', 'desc')
            ->first();

        return $equiv;
    }

    /**
     * Buscar ingrediente por nombre (fuzzy).
     */
    private function buscarIngrediente(string $nombre): ?Ingredient
    {
        $normalizado = mb_strtolower(trim($nombre));

        // Match exacto
        $ing = Ingredient::where('is_active', 1)
            ->whereRaw('LOWER(name) = ?', [$normalizado])
            ->first();
        if ($ing) return $ing;

        // Match parcial
        return Ingredient::where('is_active', 1)
            ->whereRaw('LOWER(name) LIKE ?', ["%{$normalizado}%"])
            ->first();
    }

    /**
     * Buscar sugerencias de ingredientes para un nombre desconocido.
     */
    private function buscarSugerenciasIngrediente(string $nombre): array
    {
        $normalizado = mb_strtolower(trim($nombre));

        return Ingredient::where('is_active', 1)
            ->whereRaw('LOWER(name) LIKE ?', ["%{$normalizado}%"])
            ->limit(5)
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Obtener proveedores conocidos que venden los items detectados.
     * Ordena por frecuencia de compra de esos items.
     */
    private function getProveedoresParaItems(array $items): array
    {
        $ingredienteIds = array_filter(array_map(fn($i) => $i['ingrediente_id'] ?? null, $items));
        $nombres = array_filter(array_map(fn($i) => $i['nombre_ingrediente'] ?? $i['nombre_detectado'] ?? null, $items));

        if (empty($ingredienteIds) && empty($nombres)) {
            // Sin items identificados, retornar proveedores más frecuentes
            return DB::table('compras')
                ->select('proveedor', DB::raw('COUNT(*) as frecuencia'))
                ->whereNotNull('proveedor')
                ->where('proveedor', '!=', '')
                ->groupBy('proveedor')
                ->orderByDesc('frecuencia')
                ->limit(10)
                ->pluck('frecuencia', 'proveedor')
                ->toArray();
        }

        // Proveedores que han vendido estos ingredientes, ordenados por frecuencia
        $query = DB::table('compras as c')
            ->join('compras_detalle as cd', 'c.id', '=', 'cd.compra_id')
            ->select('c.proveedor', DB::raw('COUNT(*) as frecuencia'));

        if (!empty($ingredienteIds)) {
            $query->whereIn('cd.ingrediente_id', $ingredienteIds);
        } elseif (!empty($nombres)) {
            $query->where(function ($q) use ($nombres) {
                foreach ($nombres as $n) {
                    $q->orWhere('cd.nombre_item', 'LIKE', "%{$n}%");
                }
            });
        }

        return $query
            ->whereNotNull('c.proveedor')
            ->where('c.proveedor', '!=', '')
            ->groupBy('c.proveedor')
            ->orderByDesc('frecuencia')
            ->limit(10)
            ->pluck('frecuencia', 'proveedor')
            ->toArray();
    }

    /**
     * Obtener último precio de un ingrediente.
     */
    private function getUltimoPrecio(int $ingredienteId): ?float
    {
        $precio = DB::table('compras_detalle')
            ->where('ingrediente_id', $ingredienteId)
            ->orderByDesc('id')
            ->value('precio_unitario');

        return $precio ? (float) $precio : null;
    }

    /**
     * Confirmar una compra y aprender de ella.
     * Actualiza equivalencias y precios para futuras compras.
     */
    public function confirmarYAprender(array $datosConfirmados): void
    {
        foreach ($datosConfirmados['items'] ?? [] as $item) {
            $nombreDetectado = $item['nombre_detectado'] ?? '';
            $nombreReal = $item['nombre_ingrediente'] ?? $item['nombre_item'] ?? '';
            $ingredienteId = $item['ingrediente_id'] ?? null;
            $cantidadReal = $item['cantidad'] ?? 0;
            $unidadReal = $item['unidad'] ?? 'kg';
            $precioTotal = $item['subtotal'] ?? 0;
            $unidadVisual = $item['unidad_visual'] ?? null;
            $cantidadVisual = $item['cantidad_visual'] ?? 1;

            if (empty($nombreDetectado) || empty($nombreReal)) continue;

            // Si hay unidad visual (caja, bolsa, etc.), actualizar equivalencia
            if ($unidadVisual && $cantidadVisual > 0 && $cantidadReal > 0) {
                $cantidadPorUnidad = $cantidadReal / $cantidadVisual;
                $precioPorUnidadVisual = $cantidadVisual > 0 ? $precioTotal / $cantidadVisual : 0;
                $precioUnitario = $cantidadReal > 0 ? $precioTotal / $cantidadReal : 0;

                $normalizado = mb_strtolower(trim($nombreDetectado));

                ProductEquivalence::updateOrCreate(
                    ['nombre_normalizado' => $normalizado],
                    [
                        'nombre_visual' => $nombreDetectado,
                        'ingrediente_id' => $ingredienteId,
                        'item_type' => $item['item_type'] ?? 'ingredient',
                        'nombre_ingrediente' => $nombreReal,
                        'cantidad_por_unidad' => $cantidadPorUnidad,
                        'unidad_visual' => $unidadVisual,
                        'unidad_real' => $unidadReal,
                        'veces_confirmado' => DB::raw('veces_confirmado + 1'),
                        'ultimo_precio_unidad_visual' => $precioPorUnidadVisual,
                        'ultimo_precio_unitario' => $precioUnitario,
                    ]
                );
            }
        }
    }

    /**
     * Seed initial equivalences from historical purchase data.
     * Analyzes patterns like "Tomate always bought in ~6kg batches from agro-*"
     */
    public function seedEquivalenciasDesdeHistorial(): array
    {
        $created = 0;

        // Find items that are consistently bought in similar quantities
        $patterns = DB::select("
            SELECT 
                cd.nombre_item,
                cd.unidad,
                cd.ingrediente_id,
                ROUND(AVG(cd.cantidad), 1) as cantidad_promedio,
                ROUND(STDDEV(cd.cantidad), 1) as desviacion,
                COUNT(*) as veces,
                ROUND(AVG(cd.precio_unitario), 0) as precio_promedio,
                ROUND(AVG(cd.subtotal), 0) as subtotal_promedio
            FROM compras_detalle cd
            WHERE cd.cantidad > 0
            GROUP BY cd.nombre_item, cd.unidad, cd.ingrediente_id
            HAVING veces >= 3 AND desviacion < cantidad_promedio * 0.3
            ORDER BY veces DESC
        ");

        foreach ($patterns as $p) {
            if (!$p->ingrediente_id) continue;

            $normalizado = mb_strtolower(trim($p->nombre_item));

            // Determine visual unit based on quantity patterns
            $unidadVisual = 'unidad';
            $cantidadPorUnidad = $p->cantidad_promedio;

            if ($p->unidad === 'kg' && $p->cantidad_promedio >= 3) {
                $unidadVisual = 'caja';
            } elseif ($p->unidad === 'unidad' && $p->cantidad_promedio >= 10) {
                $unidadVisual = 'paquete';
            }

            $existing = ProductEquivalence::where('nombre_normalizado', $normalizado)->first();
            if ($existing) continue;

            ProductEquivalence::create([
                'nombre_visual' => $p->nombre_item,
                'nombre_normalizado' => $normalizado,
                'ingrediente_id' => $p->ingrediente_id,
                'item_type' => 'ingredient',
                'nombre_ingrediente' => $p->nombre_item,
                'cantidad_por_unidad' => $cantidadPorUnidad,
                'unidad_visual' => $unidadVisual,
                'unidad_real' => $p->unidad,
                'veces_confirmado' => $p->veces,
                'ultimo_precio_unidad_visual' => $p->subtotal_promedio,
                'ultimo_precio_unitario' => $p->precio_promedio,
            ]);

            $created++;
        }

        return ['created' => $created];
    }
}
