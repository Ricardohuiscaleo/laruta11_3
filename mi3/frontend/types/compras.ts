// --- Extracción IA ---

export interface ExtractionItem {
  nombre: string;
  cantidad: number;
  unidad: string;
  precio_unitario: number;
  subtotal: number;
}

export interface ExtractionResult {
  proveedor: string;
  rut_proveedor: string;
  items: ExtractionItem[];
  monto_neto: number;
  iva: number;
  monto_total: number;
  tipo_imagen: 'boleta' | 'factura' | 'producto' | 'bascula' | 'desconocido';
  peso_bascula: number | null;
  unidad_bascula: string | null;
  notas_ia: string | null;
  confianza: {
    proveedor: number;
    rut: number;
    items: number;
    monto_neto: number;
    iva: number;
    monto_total: number;
    tipo_imagen: number;
    peso_bascula: number;
  };
}

// --- Compras ---

export interface CompraDetalle {
  id: number;
  compra_id: number;
  ingrediente_id: number | null;
  product_id: number | null;
  item_type: 'ingredient' | 'product';
  nombre: string;
  cantidad: number;
  unidad: string;
  precio_unitario: number;
  subtotal: number;
  stock_antes: number;
  stock_despues: number;
}

export interface Compra {
  id: number;
  fecha_compra: string;
  proveedor: string;
  tipo_compra: string;
  metodo_pago: string;
  monto_total: number;
  notas: string | null;
  imagen_respaldo: string[] | null;
  estado: string;
  created_at: string;
  detalles?: CompraDetalle[];
}

// --- Stock ---

export interface StockItem {
  id: number;
  nombre: string;
  tipo: 'ingredient' | 'product';
  stock_actual: number;
  min_stock_level: number;
  unidad: string;
  semaforo: 'rojo' | 'amarillo' | 'verde';
  ultima_cantidad_comprada: number | null;
  vendido_desde_ultima_compra: number | null;
}

// --- KPIs ---

export interface Kpi {
  ventas_mes_anterior: number;
  ventas_mes_actual: number;
  sueldos: number;
  compras_mes_actual: number;
  saldo_disponible: number;
}

// --- Formulario ---

export interface CompraFormItem {
  ingrediente_id: number | null;
  product_id: number | null;
  item_type: 'ingredient' | 'product';
  nombre: string;
  cantidad: number;
  unidad: string;
  precio_unitario: number;
  subtotal: number;
  incluye_iva: boolean;
}

export interface CompraFormData {
  proveedor: string;
  fecha_compra: string;
  tipo_compra: string;
  metodo_pago: string;
  notas: string;
  items: CompraFormItem[];
  temp_image_keys: string[];
}
