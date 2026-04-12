'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP } from '@/lib/utils';
import { Loader2, Receipt, ArrowLeftRight, AlertTriangle, Clock, CheckCircle2, XCircle, Timer } from 'lucide-react';

interface AdminDashData {
  payroll_total: number;
  pending_swaps: number;
  blocked_credits: number;
}

interface CronjobTask {
  app: string;
  name: string;
  frequency: string;
  enabled: boolean;
  last_status: string | null;
  last_run: string | null;
  total_runs: number;
  failures: number;
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'ahora';
  if (mins < 60) return `hace ${mins}m`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `hace ${hrs}h`;
  return `hace ${Math.floor(hrs / 24)}d`;
}

function freqLabel(freq: string): string {
  if (freq === '* * * * *') return 'cada 1 min';
  if (freq === '*/30 * * * *') return 'cada 30 min';
  const m = freq.match(/^(\d+)\s+(\d+)\s+\*\s+\*\s+\*$/);
  if (m) return `${m[2].padStart(2,'0')}:${m[1].padStart(2,'0')} diario`;
  const m2 = freq.match(/^(\d+)\s+(\d+)\s+(\d+)\s+\*\s+\*$/);
  if (m2) return `día ${m2[3]}, ${m2[2].padStart(2,'0')}:${m2[1].padStart(2,'0')}`;
  return freq;
}

async function fetchCronjobs(): Promise<CronjobTask[]> {
  try {
    const res = await fetch('/api/admin/cronjobs');
    if (!res.ok) return [];
    return await res.json();
  } catch { return []; }
}

export default function AdminPage() {
  const [data, setData] = useState<AdminDashData | null>(null);
  const [cronjobs, setCronjobs] = useState<CronjobTask[]>([]);
  const [cronjobsLoading, setCronjobsLoading] = useState(true);
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
      let payroll_total = 0, pending_swaps = 0, blocked_credits = 0;
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

    // Cronjobs load independently (don't block dashboard)
    fetchCronjobs().then(setCronjobs).finally(() => setCronjobsLoading(false));
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

      {/* Cronjobs Status */}
      <div className="rounded-xl border bg-white p-5 shadow-sm">
        <div className="flex items-center gap-2 mb-4">
          <Clock className="h-5 w-5 text-amber-700" />
          <span className="font-semibold text-sm text-amber-700">Cronjobs</span>
        </div>
        {cronjobsLoading ? (
          <div className="flex items-center gap-2 text-gray-400 text-sm py-4"><Loader2 className="h-4 w-4 animate-spin" /> Cargando cronjobs...</div>
        ) : cronjobs.length === 0 ? (
          <p className="text-sm text-gray-400 py-2">No se pudieron cargar los cronjobs</p>
        ) : (
          <div className="space-y-3">
            {cronjobs.map((job, i) => (
              <div key={i} className="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
                <div className="flex items-center gap-3 min-w-0">
                  {job.last_status === 'success' ? (
                    <CheckCircle2 className="h-4 w-4 text-green-500 shrink-0" />
                  ) : job.last_status === 'failed' ? (
                    <XCircle className="h-4 w-4 text-red-500 shrink-0" />
                  ) : (
                    <Timer className="h-4 w-4 text-gray-400 shrink-0" />
                  )}
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">{job.name}</p>
                    <p className="text-xs text-gray-500">{job.app} · {freqLabel(job.frequency)}</p>
                  </div>
                </div>
                <div className="flex items-center gap-4 shrink-0 text-right">
                  <div>
                    <p className="text-sm font-semibold text-gray-900">{job.total_runs}</p>
                    <p className="text-xs text-gray-500">ejecuciones</p>
                  </div>
                  {job.failures > 0 && (
                    <div>
                      <p className="text-sm font-semibold text-red-600">{job.failures}</p>
                      <p className="text-xs text-red-500">fallos</p>
                    </div>
                  )}
                  {job.last_run && (
                    <p className="text-xs text-gray-500">{timeAgo(job.last_run)}</p>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
