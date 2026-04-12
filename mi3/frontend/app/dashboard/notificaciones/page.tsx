'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { cn } from '@/lib/utils';
import { Loader2, Bell, Calendar, Receipt, CreditCard, SlidersHorizontal, Info } from 'lucide-react';
import type { Notificacion } from '@/types';

const tipoIcons: Record<string, typeof Bell> = {
  turno: Calendar, liquidacion: Receipt, credito: CreditCard,
  ajuste: SlidersHorizontal, sistema: Info,
};

export default function NotificacionesPage() {
  const [notifications, setNotifications] = useState<Notificacion[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    apiFetch<{ success: boolean; data: Notificacion[]; no_leidas: number }>('/worker/notifications')
      .then(res => {
        setNotifications(res.data || []);
        // Auto mark all as read when viewing (like Facebook)
        if (res.no_leidas > 0) {
          apiFetch('/worker/notifications/read-all', { method: 'POST' }).catch(() => {});
          // Update UI immediately
          setNotifications(prev => prev.map(n => ({ ...n, leida: true })));
        }
      })
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-red-500" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">Notificaciones</h1>
      {notifications.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <Bell className="mx-auto h-12 w-12 text-gray-300" />
          <p className="mt-3 text-sm text-gray-500">Sin notificaciones</p>
        </div>
      ) : (
        <div className="space-y-2">
          {notifications.map(n => {
            const Icon = tipoIcons[n.tipo] || Bell;
            return (
              <div key={n.id} className="w-full rounded-xl border bg-white p-4 text-left shadow-sm">
                <div className="flex items-start gap-3">
                  <div className="mt-0.5 rounded-lg p-2 bg-gray-100">
                    <Icon className="h-4 w-4 text-gray-400" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm">{n.titulo}</p>
                    <p className="mt-0.5 text-xs text-gray-500">{n.mensaje}</p>
                    <p className="mt-1 text-xs text-gray-400">{new Date(n.created_at).toLocaleDateString('es-CL')}</p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
