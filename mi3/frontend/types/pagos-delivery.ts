export interface DailySettlement {
  id: number;
  settlement_date: string;
  total_orders_delivered: number;
  total_delivery_fees: number;
  settlement_data: SettlementRider[] | null;
  status: 'pending' | 'paid';
  payment_voucher_url: string | null;
  paid_at: string | null;
  paid_by: number | null;
  compra_id: number | null;
  created_at: string;
  updated_at: string;
}

export interface SettlementRider {
  rider_id: number;
  rider_nombre: string;
  orders_count: number;
  total_fees: number;
}

export interface RiderPago {
  id: number;
  rider_id: number;
  order_id: number | null;
  monto: number;
  fecha: string;
  estado: 'pendiente' | 'pagado';
  comprobante_url: string | null;
  notas: string | null;
  rider_nombre: string;
  order_number: string | null;
  delivery_address: string | null;
  delivery_fee: number | null;
}
