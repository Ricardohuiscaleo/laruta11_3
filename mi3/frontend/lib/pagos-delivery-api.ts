import { apiFetch } from '@/lib/api';
import type { DailySettlement, RiderPago } from '@/types/pagos-delivery';

interface PaginatedResponse<T> {
  success: boolean;
  data?: T[];
  settlements?: DailySettlement[];
  pagos?: RiderPago[];
  meta?: { total: number; page: number; per_page: number; pending_count?: number };
}

export const pagosDeliveryApi = {
  getSettlements(params?: { status?: string; from?: string; to?: string; page?: number }) {
    const q = new URLSearchParams();
    if (params?.status) q.set('status', params.status);
    if (params?.from) q.set('from', params.from);
    if (params?.to) q.set('to', params.to);
    if (params?.page) q.set('page', String(params.page));
    return apiFetch<PaginatedResponse<DailySettlement>>(`/admin/pagos-delivery/settlements?${q}`);
  },

  getSettlement(id: number) {
    return apiFetch<{ success: boolean; settlement: DailySettlement }>(`/admin/pagos-delivery/settlements/${id}`);
  },

  uploadVoucher(id: number, file: File) {
    const fd = new FormData();
    fd.append('comprobante', file);
    return apiFetch<{ success: boolean; settlement: DailySettlement }>(
      `/admin/pagos-delivery/settlements/${id}/voucher`,
      { method: 'POST', body: fd },
    );
  },

  generateSettlement(date: string) {
    return apiFetch<{ success: boolean; settlement: DailySettlement }>(
      '/admin/pagos-delivery/settlements/generate',
      { method: 'POST', body: { date } },
    );
  },

  getHistory(params?: { page?: number; per_page?: number }) {
    const q = new URLSearchParams();
    if (params?.page) q.set('page', String(params.page));
    return apiFetch<PaginatedResponse<RiderPago>>(`/admin/pagos-delivery/history?${q}`);
  },
};
