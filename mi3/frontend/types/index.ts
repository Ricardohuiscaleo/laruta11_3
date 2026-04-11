export interface User {
  id: number;
  personal_id: number;
  nombre: string;
  email: string;
  rol: string;
  is_admin: boolean;
  foto_perfil?: string;
}

export interface Personal {
  id: number;
  nombre: string;
  rol: string;
  user_id: number | null;
  rut: string;
  telefono: string;
  email: string;
  sueldo_base_cajero: number;
  sueldo_base_planchero: number;
  sueldo_base_admin: number;
  sueldo_base_seguridad: number;
  activo: boolean;
}

export interface Turno {
  id: number | string;
  fecha: string;
  tipo: 'normal' | 'reemplazo' | 'seguridad' | 'reemplazo_seguridad';
  is_dynamic: boolean;
  personal_id: number;
  reemplazado_por: number | null;
  reemplazante_nombre: string | null;
  monto_reemplazo: number | null;
  pago_por: 'empresa' | 'empresa_adelanto' | 'personal' | null;
}

export interface Liquidacion {
  mes: string;
  secciones: LiquidacionSeccion[];
  gran_total: number;
}

export interface LiquidacionSeccion {
  centro_costo: 'ruta11' | 'seguridad';
  sueldo_base: number;
  dias_trabajados: number;
  reemplazos_realizados: Record<string, ReemplazoDetalle>;
  reemplazos_recibidos: Record<string, ReemplazoDetalle>;
  ajustes: Ajuste[];
  total: number;
}

export interface ReemplazoDetalle {
  nombre: string;
  dias: number[];
  monto: number;
}

export interface Ajuste {
  id: number;
  concepto: string;
  categoria: string;
  monto: number;
  notas: string;
}

export interface CreditoR11 {
  activo: boolean;
  aprobado: boolean;
  bloqueado: boolean;
  limite: number;
  usado: number;
  disponible: number;
  relacion_r11: string;
  fecha_aprobacion: string;
}

export interface CreditTransaction {
  id: number;
  amount: number;
  type: 'debit' | 'credit' | 'refund';
  description: string;
  order_id: string | null;
  created_at: string;
}

export interface Notificacion {
  id: number;
  tipo: 'turno' | 'liquidacion' | 'credito' | 'ajuste' | 'sistema';
  titulo: string;
  mensaje: string;
  leida: boolean;
  created_at: string;
}

export interface SolicitudCambio {
  id: number;
  fecha_turno: string;
  compañero: { id: number; nombre: string };
  motivo: string;
  estado: 'pendiente' | 'aprobada' | 'rechazada';
  created_at: string;
}

export interface Prestamo {
  id: number;
  monto_solicitado: number;
  monto_aprobado: number | null;
  motivo: string | null;
  cuotas: number;
  cuotas_pagadas: number;
  estado: 'pendiente' | 'aprobado' | 'rechazado' | 'pagado' | 'cancelado';
  fecha_aprobacion: string | null;
  fecha_inicio_descuento: string | null;
  notas_admin: string | null;
  created_at: string;
}

export interface DashboardSummary {
  sueldo: { total: number; mes: string };
  prestamo: {
    tiene_activo: boolean;
    monto_pendiente: number;
    cuotas_restantes: number;
    monto_cuota: number;
  };
  descuentos: {
    total: number;
    por_categoria: Record<string, number>;
  };
  reemplazos: {
    realizados: { cantidad: number; monto: number };
    recibidos: { cantidad: number; monto: number };
  };
}

export interface ReplacementSummary {
  total_ganado: number;
  total_descontado: number;
  balance: number;
}

export interface ReplacementData {
  realizados: Array<{
    fecha: string;
    titular: string;
    monto: number;
    pago_por: string;
  }>;
  recibidos: Array<{
    fecha: string;
    reemplazante: string;
    monto: number;
    pago_por: string;
  }>;
  resumen: ReplacementSummary;
}

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: string;
}
