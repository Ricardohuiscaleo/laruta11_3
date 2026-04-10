'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP } from '@/lib/utils';
import { Loader2, Receipt, ArrowLeftRight, AlertTriangle } from 'lucide-react';

interface AdminDashData {
  payroll_total: number;
  pending_swaps: number;
  blocked_credits: number;
}

export default function AdminPage() {
  const [data, setData] = useState<AdminDashData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const now = new Date();
    const mes = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

    Promise.allSettled([
      apiFetch<{ success: boolean; data: { resumen: { gran_total: number }[] } }>(`/admin/payroll?mes=${mes}`),
      apiFetch<{ success: boolean; data: { id: number }[] }>('/admin/shift-swaps'),
      apiFetch<{ success: boolean; data: { bloqueado: boolean }[] }>('/admin/credits'),
    ]).then(([payrollRes, swapsRes, creditsRes]) => {
      let payroll_total = 0;
      let pending_swaps = 0;
      let blocked_credits = 0;

      if (payrollRes.status === 'fulfilled' && payrollRes.value.data) {
        const workers = payrollRes.value.data as any;
        if (workers.resumen) payroll_total = workers.resumen.reduce((s: number, w: any) => s + (w.gran_total || 0), 0);
        else if (Array.isArray(workers)) payroll_total = workers.reduce((s: number, w: any) => s + (w.gran_total || 0), 0);
      }
      if (swapsRes.status === 'fulfilled') {
        const swaps = swapsRes.value.data;
        pending_swaps = Array.isArray(swaps) ? swaps.filter((s: any) => s.estado === 'pendiente').length : 0;
      }
      if (creditsRes.status === 'fulfilled') {
        const credits = creditsRes.value.data;
        blocked_credits = Array.isArray(credits) ? credits.filter((c: any) => c.bloqueado).length : 0;
      }

      setData({ payroll_total, pending_swaps, blocked_credits });
      setLoading(false);
    }).catch(() => { setError('Error cargando datos'); setLoading(false); });
  }, []);

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Panel Admin</h1>

      <div className="grid gap-4 sm:grid-cols-3">
        <div className="rounded-xl border bg-white p-5 shadow-sm">
          <div className="flex items-center gap-2 text-amber-700"><Receipt className="h-5 w-5" /><span className="font-semibold text-sm">Nómina del Mes</span></div>
          <p className="mt-2 text-2xl font-bold">{formatCLP(data?.payroll_total || 0)}</p>
        </div>
        <div className="rounded-xl border bg-white p-5 shadow-sm">
          <div className="flex items-center gap-2 text-amber-700"><ArrowLeftRight className="h-5 w-5" /><span className="font-semibold text-sm">Cambios Pendientes</span></div>
          <p className="mt-2 text-2xl font-bold">{data?.pending_swaps || 0}</p>
        </div>
        <div className="rounded-xl border bg-white p-5 shadow-sm">
          <div className="flex items-center gap-2 text-red-600"><AlertTriangle className="h-5 w-5" /><span className="font-semibold text-sm">Créditos Bloqueados</span></div>
          <p className="mt-2 text-2xl font-bold">{data?.blocked_credits || 0}</p>
        </div>
      </div>
    </div>
  );
}
