// ==========================================
// Admin Credits & Users — RL6, Customers
// ==========================================

/** RL6 Credit User (from GET /admin/credits/rl6) */
export interface RL6CreditUser {
  id: number;
  nombre: string;
  email: string;
  telefono: string;
  rut: string;
  grado_militar: string;
  unidad_trabajo: string;
  limite_credito: number;
  credito_usado: number;
  disponible: number;
  credito_aprobado: boolean;
  credito_bloqueado: boolean;
  fecha_ultimo_pago: string | null;
  es_moroso: boolean;
  dias_mora: number;
  deuda_ciclo_vencido: number;
  pagado_este_mes: number;
  ultimo_email_enviado: string | null;
  ultimo_email_tipo: string | null;
}

/** RL6 Summary (from GET /admin/credits/rl6 → summary) */
export interface RL6Summary {
  total_usuarios: number;
  total_credito_otorgado: number;
  total_deuda_actual: number;
  total_morosos: number;
  total_deuda_morosos: number;
  pagos_del_mes_count: number;
  pagos_del_mes_monto: number;
  tasa_cobro: number;
}

/** Customer User (from GET /admin/users/customers) */
export interface CustomerUser {
  id: number;
  nombre: string;
  email: string;
  telefono: string;
  fecha_registro: string;
  activo: boolean;
  total_orders: number;
  total_spent: number;
  last_order_date: string | null;
}

/** Email Estado types for RL6 collection emails */
export type EmailEstado = 'sin_deuda' | 'recordatorio' | 'urgente' | 'moroso';
