'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, formatDateES } from '@/lib/utils';
import { Calendar, CreditCard, Bell, Briefcase, Loader2 } from 'lucide-react';
import type { Turno, CreditoR11, Notificacion, ApiResponse } from '@/types';

export default function DashboardPage() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [shifts, setShifts] = useState<Turno[]>([]);
  const [credit, setCredit] = useState<CreditoR11 | null>(null);
  const [notifications, setNotifications] = useState<Notificacion[]>([]);
  const [noLeidas, setNoLeidas] = useState(0);

  useEffect(() => {
    const now = new Date();
    const mes = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    const today = now.toISOString().split('T')[0];

    Promise.allSettled([
      apiFetch<ApiResponse<{ turnos: Turno[] }>>(`/worker/shifts?mes=${mes}`),
      apiFetch<ApiResponse<CreditoR11>>('/worker/credit'),
      apiFetch<{ success: boolean; data: Notificacion[]; no_leidas: number }>('/worker/notifications'),
    ]).then(([shiftsRes, creditRes, notiRes]) => {
      if (shiftsRes.status === 'fulfilled' && shiftsRes.value.data) {
        const todayShifts = shiftsRes.value.data.turnos.filter(t => t.fecha === today);
        setShifts(todayShifts);
      }
      if (creditRes.status === 'fulfilled' && creditRes.value.data) {
        setCredit(creditRes.value.data);
      }
      if (notiRes.status === 'fulfilled') {
        setNotifications(notiRes.value.data?.slice(0, 5) || []);
        setNoLeidas(notiRes.value.no_leidas || 0);
      }
      setLoading(false);
    }).catch(() => {
      setError('Error cargando datos');
      setLoading(false);
    });
  }, []);

  if (loading) return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Inicio</h1>

      <div className="grid gap-4 sm:grid-cols-2">
        {/* Today's shifts */}
        <div className="rounded-xl border bg-white p-5 shadow-sm">
          <div className="flex items-center gap-2 text-amber-700">
            <Calendar className="h-5 w-5" />
            <h2 className="font-semibold">Turnos Hoy</h2>
          </div>
          {shifts.length === 0 ? (
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

        {/* Credit R11 */}
        {credit && credit.activo && (
          <div className="rounded-xl border bg-white p-5 shadow-sm">
            <div className="flex items-center gap-2 text-amber-700">
              <CreditCard className="h-5 w-5" />
              <h2 className="font-semibold">Crédito R11</h2>
            </div>
            {credit.bloqueado && (
              <div className="mt-2 rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600">Crédito bloqueado</div>
            )}
            <div className="mt-3 grid grid-cols-3 gap-2 text-center text-sm">
              <div>
                <p className="text-gray-500">Límite</p>
                <p className="font-semibold">{formatCLP(credit.limite)}</p>
              </div>
              <div>
                <p className="text-gray-500">Usado</p>
                <p className="font-semibold">{formatCLP(credit.usado)}</p>
              </div>
              <div>
                <p className="text-gray-500">Disponible</p>
                <p className="font-semibold text-green-600">{formatCLP(credit.disponible)}</p>
              </div>
            </div>
          </div>
        )}

        {/* Notifications */}
        <div className="rounded-xl border bg-white p-5 shadow-sm sm:col-span-2">
          <div className="flex items-center gap-2 text-amber-700">
            <Bell className="h-5 w-5" />
            <h2 className="font-semibold">Notificaciones</h2>
            {noLeidas > 0 && (
              <span className="rounded-full bg-red-500 px-2 py-0.5 text-xs text-white">{noLeidas}</span>
            )}
          </div>
          {notifications.length === 0 ? (
            <p className="mt-3 text-sm text-gray-500">Sin notificaciones recientes</p>
          ) : (
            <ul className="mt-3 divide-y">
              {notifications.map(n => (
                <li key={n.id} className="flex items-start gap-3 py-2">
                  <div className="flex-1">
                    <p className={`text-sm ${!n.leida ? 'font-semibold' : ''}`}>{n.titulo}</p>
                    <p className="text-xs text-gray-500">{n.mensaje}</p>
                  </div>
                  <span className="whitespace-nowrap text-xs text-gray-400">{new Date(n.created_at).toLocaleDateString('es-CL')}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  );
}
