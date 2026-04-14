// --- Extracción IA ---

export interface ExtractionItem {
  nombre: string;
  cantidad: number;
  unidad: string;
  precio_unitario: number;
  subtotal: number;
  empaque_detalle?: string | null;
}

export interface ItemSugerencia {
  original: ExtractionItem;
  match: {
    id: number;
    name: string;
    unit: string;
    cost_per_unit?: number;
    current_stock?: number;
    stock_quantity?: number;
    price?: number;
  } | null;
  match_type: 'ingredient' | 'product' | null;
  score: number;
  pre_selected: boolean;
}

export interface ExtractionResult {
  proveedor: string;
  rut_proveedor: string;
  items: ExtractionItem[];
  monto_neto: number;
  iva: number;
  monto_total: number;
  tipo_imagen: 'boleta' | 'factura' | 'producto' | 'bascula' | 'transferencia' | 'desconocido';
  fecha: string | null;
  metodo_pago: string | null;
  tipo_compra: string | null;
  peso_bascula: number | null;
  unidad_bascula: string | null;
  notas_ia: string | null;
  sugerencias?: {
    proveedor: { nombre_normalizado: string; nombre_original: string; rut?: string; score: number } | null;
    items: ItemSugerencia[];
  };
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
  name: string;
  type: 'ingredient' | 'product';
  current_stock: number;
  min_stock_level: number;
  unit: string;
  semaforo: 'rojo' | 'amarillo' | 'verde';
  category?: string;
  cost_per_unit?: number;
  supplier?: string;
  ultima_compra_cantidad?: number | null;
  stock_despues_compra?: number | null;
  fecha_ultima_compra?: string | null;
  vendido_desde_compra?: number | null;
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

// --- Registro (estado persistente entre tabs) ---

export interface RegistroImage {
  tempKey: string;
  tempUrl: string;
  status: 'uploading' | 'extracting' | 'extracted' | 'error';
  extraction?: ExtractionResult;
  sugerencias?: { proveedor: any; items: ItemSugerencia[] };
  error?: string;
}

export interface RegistroItem {
  ingrediente_id: number | null;
  product_id: number | null;
  item_type: 'ingredient' | 'product';
  nombre: string;
  cantidad: number;
  unidad: string;
  precio_unitario: number;
  subtotal: number;
  empaque_detalle?: string | null;
  match_score?: number;
  match_name?: string;
}

export interface RegistroGroup {
  proveedor: string;
  fecha_compra: string;
  metodo_pago: string;
  tipo_compra: string;
  notas: string;
  images: RegistroImage[];
  items: RegistroItem[];
  expanded: boolean;
}
