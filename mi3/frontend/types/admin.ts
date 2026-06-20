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

/** Payment receipt from tuu_orders */
export interface PaymentReceipt {
  order_number: string;
  user_id: number;
  customer_name: string;
  description: string;
  amount: number;
  payment_method: string;
  payment_status: string;
  receipt_path: string | null;
  receipt_status: 'pending_review' | 'approved' | 'rejected' | null;
  receipt_original_name: string | null;
  receipt_admin_notes: string | null;
  receipt_reviewed_by: number | null;
  receipt_reviewed_at: string | null;
  payment_date: string;
  user_nombre: string;
  user_rut: string | null;
  user_grado: string | null;
}

/** R11 Moroso User (from GET /admin/credits/r11/morosos) */
export interface R11MorosoUser {
  id: number;
  nombre: string;
  email: string;
  telefono: string;
  relacion: string | null;
  limite_credito: number;
  credito_usado: number;
  disponible: number;
  fecha_ultimo_pago: string | null;
  dias_sin_pago: number;
  bloqueado: boolean;
}

/** Pending credit application */
export interface PendingCredit {
  id: number;
  nombre: string;
  email: string;
  telefono: string;
  rut: string | null;
  grado_militar?: string | null;
  unidad_trabajo?: string | null;
  relacion_r11?: string | null;
  tipo: 'RL6' | 'R11';
  fecha_solicitud: string;
}
