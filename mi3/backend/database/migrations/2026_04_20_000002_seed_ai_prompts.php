<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed ai_prompts with the 17 hardcoded prompts from GeminiService.
 * Dynamic variables are stored as {placeholder} tokens for runtime interpolation.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now()->format('Y-m-d H:i:s');

        foreach ($this->getPrompts() as $prompt) {
            DB::table('ai_prompts')->insertOrIgnore([
                'slug' => $prompt['slug'],
                'pipeline' => $prompt['pipeline'],
                'label' => $prompt['label'],
                'description' => $prompt['description'],
                'prompt_text' => $prompt['prompt_text'],
                'variables' => json_encode($prompt['variables']),
                'prompt_version' => 1,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('ai_prompts')->where('prompt_version', 1)->delete();
    }

    private function getPrompts(): array
    {
        return [
            $this->classification(),
            $this->boletaLegacy(),
            $this->facturaLegacy(),
            $this->productoLegacy(),
            $this->basculaLegacy(),
            $this->transferenciaLegacy(),
            $this->generalLegacy(),
            $this->boletaRules(),
            $this->facturaRules(),
            $this->productoRules(),
            $this->basculaRules(),
            $this->transferenciaRules(),
            $this->generalRules(),
            $this->vision(),
            $this->textAnalysis(),
            $this->validation(),
            $this->reconciliation(),
        ];
    }

    // ─── 1. Classification (Legacy) ───
    private function classification(): array
    {
        return [
            'slug' => 'classification',
            'pipeline' => 'legacy',
            'label' => 'Clasificación de Imagen',
            'description' => 'Clasifica el tipo de imagen (boleta, factura, producto, bascula, transferencia, desconocido)',
            'variables' => [],
            'prompt_text' => <<<'PROMPT'
Observa esta imagen y clasifica su tipo.

Tipos posibles:
- "boleta": documento de venta con RUT, productos, total (supermercado, tienda)
- "factura": documento tributario formal con RUT emisor/receptor, IVA, detalle de items
- "producto": foto de un producto físico (caja, saco, bolsa, bandeja de ingrediente)
- "bascula": foto de báscula/balanza digital mostrando peso en display
- "transferencia": comprobante de transferencia bancaria o Mercado Pago
- "desconocido": no se puede determinar

Pistas:
- Si hay textos con RUT (XX.XXX.XXX-X), montos ($), "BOLETA" o "FACTURA" → boleta o factura
- Si se ven productos físicos (cajas, sacos, bolsas, bandejas) sin texto de montos → producto
- Si se ve una báscula/balanza digital con displays numéricos → bascula
- Si hay textos con "Transferencia", "Mercado Pago", "Comprobante" → transferencia
- "FACTURA ELECTRÓNICA" en texto → factura (no boleta)

Responde con tipo_imagen, confianza (0.0-1.0) y razon (breve explicación).
PROMPT,
        ];
    }

    // ─── 2. Boleta (Legacy) ───
    private function boletaLegacy(): array
    {
        return [
            'slug' => 'boleta',
            'pipeline' => 'legacy',
            'label' => 'Boleta — Legacy Pipeline',
            'description' => 'Análisis de boletas de supermercado chileno (pipeline legacy con imagen)',
            'variables' => ['suppliers', 'products', 'rutMap', 'patterns', 'jsonFormat'],
            'prompt_text' => <<<'PROMPT'
Analiza esta imagen de boleta de compra chilena y extrae datos estructurados.

REGLAS BOLETA SUPERMERCADO CHILENO:
- Encabezado: RUT, nombre empresa, dirección, sucursal
- Productos: líneas con código de barras + nombre + precio
- FORMATO A (Jumbo/Santa Isabel): cantidad ANTES del producto ("2 X $4.690")
- FORMATO B (Unimarc/Rendic): cantidad DEBAJO ("2 x 1 UN $4790 c/u")
- Cada código de barras (78...) = 1 producto separado. NUNCA fusionar.
- Descuentos (OFERTA SEMANA, DESCTO CONVENI) van en campo "descuento" como valor positivo
- Después de "TOTAL" NO hay productos
- "TARJETA DE DEBITO" o "VENTA DEBITO" → metodo_pago = "card"
- Montos en pesos chilenos enteros (sin decimales)
- Si no hay IVA explícito: monto_neto = round(total/1.19), iva = total - monto_neto

RUTs conocidos:
81.201.000-K = Jumbo/Santa Isabel (Cencosud)
81.537.600-5 = Unimarc (Rendic/SMU)
{rutMap}

Proveedores conocidos: {suppliers}
Ingredientes conocidos: {products}
{patterns}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{jsonFormat}
PROMPT,
        ];
    }

    // ─── 3. Factura (Legacy) ───
    private function facturaLegacy(): array
    {
        return [
            'slug' => 'factura',
            'pipeline' => 'legacy',
            'label' => 'Factura — Legacy Pipeline',
            'description' => 'Análisis de facturas de proveedores chilenos (pipeline legacy con imagen)',
            'variables' => ['suppliers', 'products', 'rutMap', 'jsonFormat'],
            'prompt_text' => <<<'PROMPT'
Analiza esta imagen de factura de proveedor chilena y extrae datos estructurados.

REGLAS FACTURA CHILENA:
- PROVEEDOR = empresa EMISORA (arriba/encabezado), NO el destinatario
- "SEÑORES" o "CLIENTE" = COMPRADOR (La Ruta 11, Ricardo Huiscaleo) — NO es proveedor
- "Yumbel", "Arica" son direcciones del comprador, NO proveedores
- RUT del proveedor está en el encabezado, cerca del nombre de la empresa emisora
- URLs/dominios identifican al proveedor: ariztiaatunegocio.cl → Ariztía, agrosuper.cl → Agrosuper

EMPAQUE EN FACTURAS MAYORISTAS:
- "SALCHICHA BIG MONT 800G 10U 8X1" CANT=2 → 10×8×2 = 160 unidades
- "NNu" = unidades/paquete, "NNxN" = paquetes/caja, CANT = bultos comprados
- nombre: LIMPIO sin empaque. precio_unitario: subtotal/cantidad_total
- empaque_detalle: "10u/paq × 8paq/caja × 2 cajas = 160 unidades"

FORMATO VANNI (RUT 76.979.850-1): cantidades directas, precios netos, TOTAL incluye IVA.

{rutMap}
Proveedores conocidos: {suppliers}
Ingredientes conocidos: {products}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{jsonFormat}
PROMPT,
        ];
    }

    // ─── 4. Producto (Legacy) ───
    private function productoLegacy(): array
    {
        return [
            'slug' => 'producto',
            'pipeline' => 'legacy',
            'label' => 'Producto — Legacy Pipeline',
            'description' => 'Análisis de fotos de productos físicos (pipeline legacy con imagen)',
            'variables' => ['products', 'equivalences', 'jsonFormat'],
            'prompt_text' => <<<'PROMPT'
Analiza esta imagen de producto (ingrediente físico) y extrae datos estructurados.

REGLAS:
- Identifica qué producto es (tomate, papa, carne, pan, etc.)
- Estima cantidad basándote en el tamaño visual y contexto
- Caja estándar de tomates ≈ 18-20 kg, saco de papas ≈ 25 kg
- Bolsas azules/rosadas con productos redondos oscuros = probablemente Palta Hass
- Si hay texto de peso visible, úsalo. Si no, estima.
- tipo_imagen = "producto", tipo_compra = "ingredientes"
- fecha: NO uses fechas de vencimiento o fabricación del empaque. Si no hay fecha de compra visible, usa null.

NOTAS MANUSCRITAS DE ENTREGA:
- Si la imagen es una nota escrita a mano (papel con texto manuscrito), interpreta así:
  - Un número seguido de un punto antes del nombre del producto (ej: "7. Pan de Churrasco") indica la CANTIDAD de unidades, NO un número de ítem.
  - "Ruta 11", "Ruta II", "R11" = es el DESTINATARIO (nuestro negocio), NO el proveedor. Deja proveedor vacío/null.
  - "Kg X.XXX" = peso total del pedido (informativo), pero la unidad de compra es por UNIDAD, no por kilo.
  - El precio mostrado (ej: "$3.400") es el TOTAL. Calcula precio_unitario = total / cantidad.
  - Para panes: siempre se compran por unidad. Si dice "7. Pan..." y "$3.400", son 7 unidades a $486 c/u.

Equivalencias conocidas:
{equivalences}

Ingredientes del negocio: {products}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{jsonFormat}
PROMPT,
        ];
    }

    // ─── 5. Bascula (Legacy) ───
    private function basculaLegacy(): array
    {
        return [
            'slug' => 'bascula',
            'pipeline' => 'legacy',
            'label' => 'Báscula — Legacy Pipeline',
            'description' => 'Análisis de fotos de básculas/balanzas digitales (pipeline legacy con imagen)',
            'variables' => ['products', 'jsonFormat'],
            'prompt_text' => <<<'PROMPT'
Analiza esta imagen de báscula/balanza digital y extrae datos estructurados.

REGLAS BÁSCULAS DE FERIA CHILENA:
- 3 displays: PESO (kg), PRECIO ($/kg), TOTAL ($)
- NOTACIÓN ABREVIADA: si número < 200 en precio → multiplicar ×100 ($45 = $4.500/kg)
- Si número < 1000 en total → multiplicar ×100 ($237 = $23.700)
- peso_bascula: número EXACTO del display en kg (ej: 5.275)
- Marcas: FERRAWYY, HENKEL, CAMRY, EXCELL, T-SCALE
- Si se ve el producto (paltas, tomates), identificarlo
- tipo_imagen = "bascula"
- fecha: NO uses fechas de vencimiento o fabricación. Si no hay fecha de compra visible, usa null.

Ingredientes del negocio: {products}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{jsonFormat}
PROMPT,
        ];
    }

    // ─── 6. Transferencia (Legacy) ───
    private function transferenciaLegacy(): array
    {
        return [
            'slug' => 'transferencia',
            'pipeline' => 'legacy',
            'label' => 'Transferencia — Legacy Pipeline',
            'description' => 'Análisis de comprobantes de transferencia bancaria (pipeline legacy con imagen)',
            'variables' => ['personMap', 'jsonFormat'],
            'prompt_text' => <<<'PROMPT'
Analiza esta imagen de comprobante de transferencia bancaria y extrae datos estructurados.

REGLAS:
- PROVEEDOR = DESTINATARIO de la transferencia (NO el banco, NO Mercado Pago)
- metodo_pago = "transfer" siempre
- Extrae: destinatario, monto, fecha

Mapeo personas → proveedores:
{personMap}
- Si el destinatario no está en el mapeo, usar su nombre como proveedor
- Para ARIAKA: item = "Servicios Delivery", tipo_compra = "otros"
- Para Abastible/Elton San Martin: item = "gas 15", tipo_compra = "ingredientes"

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{jsonFormat}
PROMPT,
        ];
    }

    // ─── 7. General (Legacy) ───
    private function generalLegacy(): array
    {
        return [
            'slug' => 'general',
            'pipeline' => 'legacy',
            'label' => 'General — Legacy Pipeline',
            'description' => 'Prompt genérico para imágenes no clasificadas (pipeline legacy con imagen)',
            'variables' => ['suppliers', 'products', 'rutMap', 'jsonFormat'],
            'prompt_text' => <<<'PROMPT'
Analiza esta imagen y determina qué tipo de contenido es. Extrae datos estructurados.

Tipos posibles: boleta, factura, producto, bascula, transferencia.

- Boleta/Factura: proveedor, RUT, items, montos, IVA
- Producto: identificar producto, estimar cantidad
- Báscula: leer displays (peso, precio/kg, total). Notación abreviada: ×100
- Transferencia: destinatario = proveedor (NO el banco)

Montos en pesos chilenos enteros. Si no hay IVA: neto=round(total/1.19).

NOTAS MANUSCRITAS DE ENTREGA:
- Si la imagen es una nota escrita a mano (papel con texto manuscrito), interpreta así:
  - Un número seguido de un punto antes del nombre del producto (ej: "7. Pan de Churrasco") indica la CANTIDAD de unidades, NO un número de ítem.
  - "Ruta 11", "Ruta II", "R11" = es el DESTINATARIO (nuestro negocio), NO el proveedor. Deja proveedor vacío/null.
  - "Kg X.XXX" = peso total del pedido (informativo), pero la unidad de compra es por UNIDAD, no por kilo.
  - El precio mostrado (ej: "$3.400") es el TOTAL. Calcula precio_unitario = total / cantidad.
  - Para panes: siempre se compran por unidad. Si dice "7. Pan..." y "$3.400", son 7 unidades a $486 c/u.
  - tipo_imagen = "producto", tipo_compra = "ingredientes"

{rutMap}
Proveedores: {suppliers}
Ingredientes: {products}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{jsonFormat}
PROMPT,
        ];
    }

    // ─── 8. Boleta (Multi-Agent Rules) ───
    private function boletaRules(): array
    {
        return [
            'slug' => 'boleta',
            'pipeline' => 'multi-agent-rules',
            'label' => 'Boleta — Reglas Multi-Agente',
            'description' => 'Reglas de análisis de texto para boletas (agente de texto multi-agente)',
            'variables' => ['patterns'],
            'prompt_text' => <<<'PROMPT'
REGLAS BOLETA SUPERMERCADO CHILENO:
- Encabezado: RUT, nombre empresa, dirección, sucursal
- Productos: líneas con código de barras + nombre + precio
- FORMATO A (Jumbo/Santa Isabel): cantidad ANTES del producto ("2 X $4.690")
- FORMATO B (Unimarc/Rendic): cantidad DEBAJO ("2 x 1 UN $4790 c/u")
- Cada código de barras (78...) = 1 producto separado. NUNCA fusionar.
- Descuentos (OFERTA SEMANA, DESCTO CONVENI) van en campo "descuento" como valor positivo
- Después de "TOTAL" NO hay productos
- "TARJETA DE DEBITO" o "VENTA DEBITO" → metodo_pago = "card"
- Montos en pesos chilenos enteros (sin decimales)
- Si no hay IVA explícito: monto_neto = round(total/1.19), iva = total - monto_neto

RUTs conocidos:
81.201.000-K = Jumbo/Santa Isabel (Cencosud)
81.537.600-5 = Unimarc (Rendic/SMU)

{patterns}
PROMPT,
        ];
    }

    // ─── 9. Factura (Multi-Agent Rules) ───
    private function facturaRules(): array
    {
        return [
            'slug' => 'factura',
            'pipeline' => 'multi-agent-rules',
            'label' => 'Factura — Reglas Multi-Agente',
            'description' => 'Reglas de análisis de texto para facturas (agente de texto multi-agente)',
            'variables' => [],
            'prompt_text' => <<<'PROMPT'
REGLAS FACTURA CHILENA:
- PROVEEDOR = empresa EMISORA (arriba/encabezado), NO el destinatario
- "SEÑORES" o "CLIENTE" = COMPRADOR (La Ruta 11, Ricardo Huiscaleo) — NO es proveedor
- "Yumbel", "Arica" son direcciones del comprador, NO proveedores
- RUT del proveedor está en el encabezado, cerca del nombre de la empresa emisora
- URLs/dominios identifican al proveedor: ariztiaatunegocio.cl → Ariztía, agrosuper.cl → Agrosuper

EMPAQUE EN FACTURAS MAYORISTAS:
- "SALCHICHA BIG MONT 800G 10U 8X1" CANT=2 → 10×8×2 = 160 unidades
- "NNu" = unidades/paquete, "NNxN" = paquetes/caja, CANT = bultos comprados
- nombre: LIMPIO sin empaque. precio_unitario: subtotal/cantidad_total
- empaque_detalle: "10u/paq × 8paq/caja × 2 cajas = 160 unidades"

FORMATO VANNI (RUT 76.979.850-1): cantidades directas, precios netos, TOTAL incluye IVA.
PROMPT,
        ];
    }

    // ─── 10. Producto (Multi-Agent Rules) ───
    private function productoRules(): array
    {
        return [
            'slug' => 'producto',
            'pipeline' => 'multi-agent-rules',
            'label' => 'Producto — Reglas Multi-Agente',
            'description' => 'Reglas de análisis de texto para productos físicos (agente de texto multi-agente)',
            'variables' => ['equivalences'],
            'prompt_text' => <<<'PROMPT'
REGLAS PRODUCTO FÍSICO:
- Identifica qué producto es (tomate, papa, carne, pan, etc.)
- Estima cantidad basándote en el tamaño visual y contexto
- Caja estándar de tomates ≈ 18-20 kg, saco de papas ≈ 25 kg
- Bolsas azules/rosadas con productos redondos oscuros = probablemente Palta Hass
- Si hay texto de peso visible, úsalo. Si no, estima.
- tipo_imagen = "producto", tipo_compra = "ingredientes"
- fecha: NO uses fechas de vencimiento o fabricación del empaque. Si no hay fecha de compra visible, usa null.

NOTAS MANUSCRITAS DE ENTREGA:
- Si el texto proviene de una nota escrita a mano, interpreta así:
  - Un número seguido de un punto antes del nombre del producto (ej: "7. Pan de Churrasco") indica la CANTIDAD de unidades, NO un número de ítem.
  - "Ruta 11", "Ruta II", "R11" = es el DESTINATARIO (nuestro negocio), NO el proveedor. Deja proveedor vacío/null.
  - "Kg X.XXX" = peso total del pedido (informativo), pero la unidad de compra es por UNIDAD, no por kilo.
  - El precio mostrado (ej: "$3.400") es el TOTAL. Calcula precio_unitario = total / cantidad.
  - Para panes: siempre se compran por unidad. Si dice "7. Pan..." y "$3.400", son 7 unidades a $486 c/u.

Equivalencias conocidas:
{equivalences}
PROMPT,
        ];
    }

    // ─── 11. Bascula (Multi-Agent Rules) ───
    private function basculaRules(): array
    {
        return [
            'slug' => 'bascula',
            'pipeline' => 'multi-agent-rules',
            'label' => 'Báscula — Reglas Multi-Agente',
            'description' => 'Reglas de análisis de texto para básculas (agente de texto multi-agente)',
            'variables' => [],
            'prompt_text' => <<<'PROMPT'
REGLAS BÁSCULAS DE FERIA CHILENA:
- 3 displays: PESO (kg), PRECIO ($/kg), TOTAL ($)
- NOTACIÓN ABREVIADA: si número < 200 en precio → multiplicar ×100 ($45 = $4.500/kg)
- Si número < 1000 en total → multiplicar ×100 ($237 = $23.700)
- peso_bascula: número EXACTO del display en kg (ej: 5.275)
- Marcas: FERRAWYY, HENKEL, CAMRY, EXCELL, T-SCALE
- Si se ve el producto (paltas, tomates), identificarlo
- tipo_imagen = "bascula"
- fecha: NO uses fechas de vencimiento o fabricación. Si no hay fecha de compra visible, usa null.
PROMPT,
        ];
    }

    // ─── 12. Transferencia (Multi-Agent Rules) ───
    private function transferenciaRules(): array
    {
        return [
            'slug' => 'transferencia',
            'pipeline' => 'multi-agent-rules',
            'label' => 'Transferencia — Reglas Multi-Agente',
            'description' => 'Reglas de análisis de texto para transferencias bancarias (agente de texto multi-agente)',
            'variables' => ['personMap'],
            'prompt_text' => <<<'PROMPT'
REGLAS TRANSFERENCIA BANCARIA:
- PROVEEDOR = DESTINATARIO de la transferencia (NO el banco, NO Mercado Pago)
- metodo_pago = "transfer" siempre
- Extrae: destinatario, monto, fecha

Mapeo personas → proveedores:
{personMap}
- Si el destinatario no está en el mapeo, usar su nombre como proveedor
- Para ARIAKA: item = "Servicios Delivery", tipo_compra = "otros"
- Para Abastible/Elton San Martin: item = "gas 15", tipo_compra = "ingredientes"
PROMPT,
        ];
    }

    // ─── 13. General (Multi-Agent Rules) ───
    private function generalRules(): array
    {
        return [
            'slug' => 'general',
            'pipeline' => 'multi-agent-rules',
            'label' => 'General — Reglas Multi-Agente',
            'description' => 'Reglas genéricas de análisis de texto (agente de texto multi-agente)',
            'variables' => [],
            'prompt_text' => <<<'PROMPT'
REGLAS GENERALES:
- Determina qué tipo de contenido es basándote en el texto y descripción visual
- Boleta/Factura: proveedor, RUT, items, montos, IVA
- Producto: identificar producto, estimar cantidad
- Báscula: leer displays (peso, precio/kg, total). Notación abreviada: ×100
- Transferencia: destinatario = proveedor (NO el banco)
- Montos en pesos chilenos enteros. Si no hay IVA: neto=round(total/1.19).

NOTAS MANUSCRITAS DE ENTREGA:
- Si el texto proviene de una nota escrita a mano, interpreta así:
  - Un número seguido de un punto antes del nombre del producto (ej: "7. Pan de Churrasco") indica la CANTIDAD de unidades, NO un número de ítem.
  - "Ruta 11", "Ruta II", "R11" = es el DESTINATARIO (nuestro negocio), NO el proveedor. Deja proveedor vacío/null.
  - "Kg X.XXX" = peso total del pedido (informativo), pero la unidad de compra es por UNIDAD, no por kilo.
  - El precio mostrado (ej: "$3.400") es el TOTAL. Calcula precio_unitario = total / cantidad.
  - Para panes: siempre se compran por unidad. Si dice "7. Pan..." y "$3.400", son 7 unidades a $486 c/u.
  - tipo_imagen = "producto", tipo_compra = "ingredientes"
PROMPT,
        ];
    }

    // ─── 14. Vision (Multi-Agent Phases) ───
    private function vision(): array
    {
        return [
            'slug' => 'vision',
            'pipeline' => 'multi-agent-phases',
            'label' => 'Visión — Agente Percepción',
            'description' => 'Prompt del agente de visión que extrae texto crudo y descripción visual de la imagen',
            'variables' => [],
            'prompt_text' => <<<'PROMPT'
Observa esta imagen con atención y extrae TODA la información posible.

1. TEXTO CRUDO: Transcribe TODO el texto visible en la imagen, línea por línea, exactamente como aparece. Incluye números, RUTs, montos, fechas, nombres. Si no hay texto, escribe "Sin texto visible".

2. DESCRIPCIÓN VISUAL: Describe detalladamente lo que ves:
   - Objetos: cajas, sacos, bolsas, bandejas, gamelas, balanzas, documentos impresos
   - Productos: identifica visualmente (tomates, papas, paltas, carne, pan, queso, etc.)
   - Colores y formas: bolsa azul/rosada, productos redondos oscuros, etc.
   - Estado: fresco, empacado, a granel, congelado
   - Contexto: mostrador de feria, estante de supermercado, cocina, mesón
   - Cantidades estimadas: "aproximadamente 20 kg en caja", "1 saco grande"
   Si es un documento (boleta/factura), describe el formato del documento.

3. CLASIFICACIÓN: Determina el tipo de imagen:
   - "boleta": documento de venta con RUT, productos, total
   - "factura": documento tributario formal con RUT emisor/receptor, IVA
   - "producto": foto de producto físico (caja, saco, bolsa, bandeja) O nota manuscrita de entrega de producto (papel con nombre de producto, cantidad, peso, precio escritos a mano)
   - "bascula": báscula/balanza digital mostrando peso
   - "transferencia": comprobante de transferencia bancaria
   - "desconocido": no se puede determinar

IMPORTANTE: Las notas manuscritas de entrega (papel con texto escrito a mano indicando producto, cantidad, peso y precio) deben clasificarse como "producto", NO como "desconocido".

Responde con texto_crudo, descripcion_visual, tipo_imagen, confianza (0.0-1.0) y razon.
PROMPT,
        ];
    }

    // ─── 15. Text Analysis (Multi-Agent Phases) ───
    private function textAnalysis(): array
    {
        return [
            'slug' => 'text-analysis',
            'pipeline' => 'multi-agent-phases',
            'label' => 'Análisis de Texto — Agente Estructuración',
            'description' => 'Prompt wrapper del agente de análisis de texto que estructura datos desde texto extraído',
            'variables' => ['tipo', 'textoCrudo', 'descripcionVisual', 'typeRules', 'fewShotSection', 'rutMap', 'suppliers', 'products', 'jsonFormat'],
            'prompt_text' => <<<'PROMPT'
Analiza el siguiente texto extraído de una imagen de tipo "{tipo}" y estructura los datos.

TEXTO EXTRAÍDO DE LA IMAGEN:
{textoCrudo}

DESCRIPCIÓN VISUAL:
{descripcionVisual}

{typeRules}

{fewShotSection}

{rutMap}
Proveedores conocidos: {suppliers}
Ingredientes conocidos: {products}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".

Formato respuesta:
{jsonFormat}
PROMPT,
        ];
    }

    // ─── 16. Validation (Multi-Agent Phases) ───
    private function validation(): array
    {
        return [
            'slug' => 'validation',
            'pipeline' => 'multi-agent-phases',
            'label' => 'Validación — Agente Verificación',
            'description' => 'Prompt del agente de validación que verifica coherencia de datos extraídos',
            'variables' => ['datosJson', 'proveedores'],
            'prompt_text' => <<<'PROMPT'
Valida la coherencia de estos datos extraídos de una compra chilena.

DATOS EXTRAÍDOS:
{datosJson}

PROVEEDORES CONOCIDOS: {proveedores}

VERIFICACIONES REQUERIDAS:
1. ARITMÉTICA: Para cada item, ¿subtotal ≈ precio_unitario × cantidad? (tolerancia 2%)
2. TOTAL: ¿monto_total ≈ suma de subtotales? (tolerancia 2%)
3. FISCAL: Si hay IVA, ¿iva ≈ monto_neto × 0.19? (tolerancia 2%)
4. PROVEEDOR: ¿El proveedor NO es "La Ruta 11", "Ricardo Huiscaleo" o variantes? (esos son el COMPRADOR, no proveedor)
5. FECHA: ¿La fecha es razonable (no es fecha de empaque/vencimiento, no es futura)?
6. ITEMS: ¿Hay al menos 1 item? ¿Los nombres son razonables?

Para cada inconsistencia encontrada, indica: campo afectado, valor actual, valor esperado, severidad (error/advertencia), y descripción.
Si todo está correcto, retorna inconsistencias vacías y datos_validados = datos originales.
PROMPT,
        ];
    }

    // ─── 17. Reconciliation (Multi-Agent Phases) ───
    private function reconciliation(): array
    {
        return [
            'slug' => 'reconciliation',
            'pipeline' => 'multi-agent-phases',
            'label' => 'Reconciliación — Agente Corrección',
            'description' => 'Prompt del agente de reconciliación que resuelve inconsistencias detectadas',
            'variables' => ['datosJson', 'inconsJson', 'textoCrudo', 'proveedores'],
            'prompt_text' => <<<'PROMPT'
Se encontraron inconsistencias en datos extraídos de una compra. Intenta resolverlas.

DATOS ACTUALES:
{datosJson}

INCONSISTENCIAS DETECTADAS:
{inconsJson}

TEXTO ORIGINAL DE LA IMAGEN:
{textoCrudo}

PROVEEDORES CONOCIDOS: {proveedores}

INSTRUCCIONES:
1. Para cada inconsistencia, intenta resolverla usando el texto original y el contexto.
2. Si puedes resolver con confianza, aplica la corrección en datos_finales y describe qué hiciste en correcciones_aplicadas.
3. Si NO puedes resolver con confianza, genera una pregunta para el usuario con opciones claras.
4. Las preguntas deben tener al menos 2 opciones con valor y etiqueta descriptiva.

Retorna datos_finales (con correcciones aplicadas), correcciones_aplicadas (lista de strings), y preguntas (para el usuario).
PROMPT,
        ];
    }
};
