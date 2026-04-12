'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP } from '@/lib/utils';
import {
  Calendar,
  Bell,
  DollarSign,
  CreditCard,
  TrendingDown,
  Users,
  Loader2,
  ClipboardCheck,
  Sun,
  Moon,
} from 'lucide-react';
import Link from 'next/link';
import type {
  Turno,
  Notificacion,
  DashboardSummary,
  ApiResponse,
} from '@/types';

/* ─── Skeleton card ─── */
function CardSkeleton() {
  return (
    <div className="animate-pulse rounded-xl border bg-white p-5 shadow-sm">
      <div className="h-4 w-24 rounded bg-gray-200" />
      <div className="mt-4 h-7 w-32 rounded bg-gray-200" />
      <div className="mt-3 h-3 w-full rounded bg-gray-100" />
      <div className="mt-2 h-3 w-2/3 rounded bg-gray-100" />
    </div>
  );
}

/* ─── Summary Cards ─── */
function SueldoCard({ data }: { data: DashboardSummary['sueldo'] }) {
  return (
    <div className="rounded-xl border bg-white p-5 shadow-sm">
      <div className="flex items-center gap-2 text-red-600">
        <DollarSign className="h-5 w-5" />
        <h2 className="text-sm font-semibold">Sueldo del Mes</h2>
      </div>
      <p className="mt-3 text-2xl font-bold text-gray-900">
        {formatCLP(data.total)}
      </p>
      <p className="mt-1 text-xs text-gray-500">Liquidación {data.mes}</p>
    </div>
  );
}

function PrestamoCard({ data }: { data: DashboardSummary['prestamo'] }) {
  return (
    <div className="rounded-xl border bg-white p-5 shadow-sm">
      <div className="flex items-center gap-2 text-red-600">
        <CreditCard className="h-5 w-5" />
        <h2 className="text-sm font-semibold">Préstamos</h2>
      </div>
      {data.tiene_activo ? (
        <>
          <p className="mt-3 text-2xl font-bold text-gray-900">
            {formatCLP(data.monto_pendiente)}
          </p>
          <p className="mt-1 text-xs text-gray-500">
            {data.cuotas_restantes} cuota{data.cuotas_restantes !== 1 ? 's' : ''} restante{data.cuotas_restantes !== 1 ? 's' : ''} · {formatCLP(data.monto_cuota)}/cuota
          </p>
        </>
      ) : (
        <>
          <p className="mt-3 text-2xl font-bold text-gray-900">$0</p>
          <p className="mt-1 text-xs text-gray-500">Sin préstamo activo</p>
        </>
      )}
    </div>
  );
}

function DescuentosCard({ data }: { data: DashboardSummary['descuentos'] }) {
  const categorias = Object.entries(data.por_categoria);
  return (
    <div className="rounded-xl border bg-white p-5 shadow-sm">
      <div className="flex items-center gap-2 text-red-600">
        <TrendingDown className="h-5 w-5" />
        <h2 className="text-sm font-semibold">Descuentos</h2>
      </div>
      <p className="mt-3 text-2xl font-bold text-gray-900">
        {formatCLP(Math.abs(data.total))}
      </p>
      {categorias.length > 0 ? (
        <ul className="mt-2 space-y-1">
          {categorias.map(([cat, monto]) => (
            <li key={cat} className="flex justify-between text-xs text-gray-500">
              <span className="capitalize">{cat.replace(/_/g, ' ')}</span>
              <span>{formatCLP(Math.abs(monto))}</span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-1 text-xs text-gray-500">Sin descuentos este mes</p>
      )}
    </div>
  );
}

function ReemplazosCard({ data }: { data: DashboardSummary['reemplazos'] }) {
  return (
    <div className="rounded-xl border bg-white p-5 shadow-sm">
      <div className="flex items-center gap-2 text-red-600">
        <Users className="h-5 w-5" />
        <h2 className="text-sm font-semibold">Reemplazos</h2>
      </div>
      <div className="mt-3 grid grid-cols-2 gap-3">
        <div>
          <p className="text-xs text-gray-500">Realizados</p>
          <p className="text-lg font-bold text-gray-900">{data.realizados.cantidad}</p>
          <p className="text-xs text-green-600">+{formatCLP(data.realizados.monto)}</p>
        </div>
        <div>
          <p className="text-xs text-gray-500">Recibidos</p>
          <p className="text-lg font-bold text-gray-900">{data.recibidos.cantidad}</p>
          <p className="text-xs text-red-500">-{formatCLP(data.recibidos.monto)}</p>
        </div>
      </div>
    </div>
  );
}

/* ─── Main Page ─── */
export default function DashboardPage() {
  const [summaryLoading, setSummaryLoading] = useState(true);
  const [summaryError, setSummaryError] = useState('');
  const [summary, setSummary] = useState<DashboardSummary | null>(null);

  const [shiftsLoading, setShiftsLoading] = useState(true);
  const [shifts, setShifts] = useState<Turno[]>([]);
  const [notifications, setNotifications] = useState<Notificacion[]>([]);
  const [noLeidas, setNoLeidas] = useState(0);
  const [bottomLoading, setBottomLoading] = useState(true);
  const [checklists, setChecklists] = useState<any[]>([]);
  const [checklistLoading, setChecklistLoading] = useState(true);
  const [isDayOff, setIsDayOff] = useState(false);

  useEffect(() => {
    const now = new Date();
    const mes = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    const today = now.toISOString().split('T')[0];

    // Fetch dashboard summary (4 cards)
    apiFetch<ApiResponse<DashboardSummary>>('/worker/dashboard-summary')
      .then((res) => {
        if (res.data) setSummary(res.data);
      })
      .catch(() => setSummaryError('Error cargando resumen'))
      .finally(() => setSummaryLoading(false));

    // Fetch shifts + notifications + checklists
    Promise.allSettled([
      apiFetch<ApiResponse<{ turnos: Turno[] }>>(`/worker/shifts?mes=${mes}`),
      apiFetch<{ success: boolean; data: Notificacion[]; no_leidas: number }>('/worker/notifications'),
      apiFetch<{ success: boolean; data: any[] }>('/worker/checklists'),
    ]).then(([shiftsRes, notiRes, checkRes]) => {
      if (shiftsRes.status === 'fulfilled' && shiftsRes.value.data) {
        const todayShifts = shiftsRes.value.data.turnos.filter(t => t.fecha === today);
        setShifts(todayShifts);
        if (todayShifts.length === 0) setIsDayOff(true);
      } else {
        setIsDayOff(true);
      }
      if (notiRes.status === 'fulfilled') {
        setNotifications(notiRes.value.data?.slice(0, 5) || []);
        setNoLeidas(notiRes.value.no_leidas || 0);
      }
      if (checkRes.status === 'fulfilled') {
        setChecklists(checkRes.value.data || []);
      }
      setShiftsLoading(false);
      setBottomLoading(false);
      setChecklistLoading(false);
    }).catch(() => {
      setShiftsLoading(false);
      setBottomLoading(false);
      setChecklistLoading(false);
    });
  }, []);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Inicio</h1>

      {/* ── 4 Summary Cards ── */}
      {summaryError && (
        <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600">{summaryError}</div>
      )}
      <div className="grid gap-4 sm:grid-cols-2">
        {summaryLoading ? (
          <>
            <CardSkeleton />
            <CardSkeleton />
            <CardSkeleton />
            <CardSkeleton />
          </>
        ) : summary ? (
          <>
            <SueldoCard data={summary.sueldo} />
            <PrestamoCard data={summary.prestamo} />
            <DescuentosCard data={summary.descuentos} />
            <ReemplazosCard data={summary.reemplazos} />
          </>
        ) : null}
      </div>

      {/* ── Existing sections: Turnos + Notificaciones ── */}
      <div className="grid gap-4 sm:grid-cols-2">
        {/* Today's shifts */}
        <div className="rounded-xl border bg-white p-5 shadow-sm">
          <div className="flex items-center gap-2 text-amber-700">
            <Calendar className="h-5 w-5" />
            <h2 className="font-semibold">Turnos Hoy</h2>
          </div>
          {shiftsLoading ? (
            <div className="mt-3 flex items-center gap-2 text-sm text-gray-400">
              <Loader2 className="h-4 w-4 animate-spin" /> Cargando…
            </div>
          ) : shifts.length === 0 ? (
            <p className="mt-3 text-sm text-gray-500">No tienes turnos hoy. Día libre 🎉</p>
          ) : (
            <ul className="mt-3 space-y-2">
              {shifts.map(s => (
                <li key={s.id} className="flex items-center justify-between rounded-lg bg-amber-50 px-3 py-2 text-sm">
                  <span className="font-medium capitalize">{s.tipo}</span>
                  {s.reemplazante_nombre && <span className="text-gray-500">→ {s.reemplazante_nombre}</span>}
                </li>
              ))}
            </ul>
          )}
        </div>

        {/* Notifications */}
        <div className="rounded-xl border bg-white p-5 shadow-sm">
          <div className="flex items-center gap-2 text-amber-700">
            <Bell className="h-5 w-5" />
            <h2 className="font-semibold">Notificaciones</h2>
            {noLeidas > 0 && (
              <span className="rounded-full bg-red-500 px-2 py-0.5 text-xs text-white">{noLeidas}</span>
            )}
          </div>
          {bottomLoading ? (
            <div className="mt-3 flex items-center gap-2 text-sm text-gray-400">
              <Loader2 className="h-4 w-4 animate-spin" /> Cargando…
            </div>
          ) : notifications.length === 0 ? (
            <p className="mt-3 text-sm text-gray-500">Sin notificaciones recientes</p>
          ) : (
            <ul className="mt-3 divide-y">
              {notifications.map(n => (
                <li key={n.id} className="flex items-start gap-3 py-2">
                  <div className="flex-1">
                    <p className={`text-sm ${!n.leida ? 'font-semibold' : ''}`}>{n.titulo}</p>
                    <p className="text-xs text-gray-500">{n.mensaje}</p>
                  </div>
                  <span className="whitespace-nowrap text-xs text-gray-400">
                    {new Date(n.created_at).toLocaleDateString('es-CL')}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  );
}
