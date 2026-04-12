'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP } from '@/lib/utils';
import Link from 'next/link';
import {
  DollarSign, CreditCard, TrendingDown, Users, Calendar,
  ClipboardCheck, Loader2, ChevronRight, Sun,
} from 'lucide-react';
import type { Turno, DashboardSummary, ApiResponse } from '@/types';

export default function DashboardPage() {
  const [loading, setLoading] = useState(true);
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [shifts, setShifts] = useState<Turno[]>([]);
  const [checklists, setChecklists] = useState<any[]>([]);
  const [isDayOff, setIsDayOff] = useState(false);

  useEffect(() => {
    const now = new Date();
    const mes = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    const today = now.toISOString().split('T')[0];

    Promise.allSettled([
      apiFetch<ApiResponse<DashboardSummary>>('/worker/dashboard-summary'),
      apiFetch<ApiResponse<{ turnos: Turno[] }>>(`/worker/shifts?mes=${mes}`),
      apiFetch<{ success: boolean; data: any[] }>('/worker/checklists'),
    ]).then(([sumRes, shiftRes, checkRes]) => {
      if (sumRes.status === 'fulfilled' && sumRes.value.data) setSummary(sumRes.value.data);
      if (shiftRes.status === 'fulfilled' && shiftRes.value.data) {
        const todayShifts = shiftRes.value.data.turnos.filter(t => t.fecha === today);
        setShifts(todayShifts);
        if (todayShifts.length === 0) setIsDayOff(true);
      } else {
        setIsDayOff(true);
      }
      if (checkRes.status === 'fulfilled') setChecklists(checkRes.value.data || []);
      setLoading(false);
    });
  }, []);

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;

  const s = summary;
  const turnoHoy = shifts[0];
  const pendientes = checklists.filter((c: any) => c.status === 'pending' || c.status === 'active');
  const hasPendingChecklist = pendientes.length > 0;

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">Inicio</h1>

      {/* ── Card única: resumen compacto ── */}
      <div className="rounded-2xl border bg-white p-5 shadow-sm space-y-4">
        {/* Sueldo (protagonista) */}
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs text-gray-500">Sueldo del Mes</p>
            <p className="text-2xl font-bold text-gray-900">{formatCLP(s?.sueldo?.total ?? 0)}</p>
          </div>
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-green-100">
            <DollarSign className="h-5 w-5 text-green-600" />
          </div>
        </div>

        {/* Línea de detalles */}
        <div className="grid grid-cols-3 gap-3 border-t pt-3">
          <div className="text-center">
            <CreditCard className="mx-auto h-4 w-4 text-gray-400" />
            <p className="mt-1 text-xs text-gray-500">Adelanto</p>
            <p className="text-sm font-semibold text-gray-900">
              {s?.prestamo?.tiene_activo ? formatCLP(s.prestamo.monto_pendiente) : '$0'}
            </p>
          </div>
          <div className="text-center">
            <TrendingDown className="mx-auto h-4 w-4 text-gray-400" />
            <p className="mt-1 text-xs text-gray-500">Descuentos</p>
            <p className="text-sm font-semibold text-gray-900">
              {formatCLP(Math.abs(s?.descuentos?.total ?? 0))}
            </p>
          </div>
          <div className="text-center">
            <Users className="mx-auto h-4 w-4 text-gray-400" />
            <p className="mt-1 text-xs text-gray-500">Reemplazos</p>
            <p className="text-sm font-semibold text-gray-900">
              {(s?.reemplazos?.realizados?.cantidad ?? 0) + (s?.reemplazos?.recibidos?.cantidad ?? 0)}
            </p>
          </div>
        </div>

        {/* Turno hoy */}
        <div className="flex items-center gap-3 rounded-xl bg-amber-50 px-4 py-3">
          <Calendar className="h-5 w-5 text-amber-600" />
          <div className="flex-1">
            <p className="text-sm font-medium text-amber-800">
              {isDayOff ? 'Hoy tienes libre 😊' : `Turno: ${turnoHoy?.tipo || 'Normal'}`}
            </p>
            {turnoHoy?.reemplazante_nombre && (
              <p className="text-xs text-amber-600">Reemplazo: {turnoHoy.reemplazante_nombre}</p>
            )}
          </div>
        </div>
      </div>

      {/* ── Acceso directo: Checklist ── */}
      {isDayOff ? (
        <div className="rounded-2xl border bg-gradient-to-r from-blue-50 to-indigo-50 p-5 text-center">
          <Sun className="mx-auto h-8 w-8 text-amber-400" />
          <p className="mt-2 text-lg font-semibold text-gray-700">Hoy tienes libre 😊</p>
          <p className="text-sm text-gray-500">Descansa y recarga energías</p>
        </div>
      ) : hasPendingChecklist ? (
        <Link href="/dashboard/checklist"
          className="flex items-center gap-4 rounded-2xl border-2 border-amber-200 bg-amber-50 p-5 shadow-sm transition-all hover:border-amber-300 hover:shadow-md active:scale-[0.98]">
          <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500 shadow-sm">
            <ClipboardCheck className="h-6 w-6 text-white" />
          </div>
          <div className="flex-1">
            <p className="text-base font-semibold text-gray-900">Realizar Checklist</p>
            <p className="text-sm text-amber-700">
              {pendientes.length} checklist{pendientes.length > 1 ? 's' : ''} pendiente{pendientes.length > 1 ? 's' : ''}
              {pendientes.map((c: any) => c.type === 'apertura' ? ' 🌅' : ' 🌙').join('')}
            </p>
          </div>
          <ChevronRight className="h-5 w-5 text-amber-400" />
        </Link>
      ) : (
        <div className="rounded-2xl border bg-green-50 p-5 text-center">
          <ClipboardCheck className="mx-auto h-8 w-8 text-green-500" />
          <p className="mt-2 text-sm font-medium text-green-700">Checklists completados ✅</p>
        </div>
      )}
    </div>
  );
}
