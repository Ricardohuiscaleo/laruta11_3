'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { cn } from '@/lib/utils';
import { Loader2, Bell, Calendar, Receipt, CreditCard, SlidersHorizontal, Info, ExternalLink } from 'lucide-react';
import type { Notificacion } from '@/types';

const tipoIcons: Record<string, typeof Bell> = {
  turno: Calendar, liquidacion: Receipt, credito: CreditCard,
  ajuste: SlidersHorizontal, sistema: Info,
};

type FilterTab = 'todos' | 'adelantos' | 'cambios' | 'sistema';

const FILTER_TABS: { key: FilterTab; label: string }[] = [
  { key: 'todos', label: 'Todos' },
  { key: 'adelantos', label: 'Adelantos' },
  { key: 'cambios', label: 'Cambios' },
  { key: 'sistema', label: 'Sistema' },
];

interface NotificacionesSectionProps {
  onNavigate?: (section: string, params?: any) => void;
}

export default function NotificacionesSection({ onNavigate }: NotificacionesSectionProps) {
  const [notifications, setNotifications] = useState<Notificacion[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeTab, setActiveTab] = useState<FilterTab>('todos');

  useEffect(() => {
    apiFetch<{ success: boolean; data: Notificacion[]; no_leidas: number }>('/worker/notifications')
      .then(res => {
        setNotifications(res.data || []);
        if (res.no_leidas > 0) {
          apiFetch('/worker/notifications/read-all', { method: 'POST' }).catch(() => {});
          setNotifications(prev => prev.map(n => ({ ...n, leida: true })));
        }
      })
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  const filterNotifications = (list: Notificacion[]): Notificacion[] => {
    switch (activeTab) {
      case 'adelantos':
        return list.filter(n => n.referencia_tipo === 'prestamo');
      case 'cambios':
        return list.filter(n => n.referencia_tipo === 'cambio_turno');
      case 'sistema':
        return list.filter(n => !n.referencia_tipo || (n.referencia_tipo !== 'prestamo' && n.referencia_tipo !== 'cambio_turno'));
      default:
        return list;
    }
  };

  const filtered = filterNotifications(notifications);

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-red-500" aria-label="Cargando" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600" role="alert">{error}</div>;

  return (
    <div className="space-y-4">
      <h1 className="hidden md:block text-2xl font-bold text-gray-900">Notificaciones</h1>

      {/* Filter tabs */}
      <div className="flex gap-1 overflow-x-auto rounded-lg bg-gray-100 p-1" role="tablist" aria-label="Filtrar notificaciones">
        {FILTER_TABS.map(tab => (
          <button
            key={tab.key}
            role="tab"
            aria-selected={activeTab === tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={cn(
              'min-h-[44px] flex-1 whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition-colors',
              activeTab === tab.key
                ? 'bg-white text-gray-900 shadow-sm'
                : 'text-gray-500 hover:text-gray-700'
            )}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {filtered.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <Bell className="mx-auto h-12 w-12 text-gray-300" />
          <p className="mt-3 text-sm text-gray-500">Sin notificaciones</p>
        </div>
      ) : (
        <div className="space-y-2">
          {filtered.map(n => {
            const Icon = tipoIcons[n.tipo] || Bell;
            const hasAction = n.referencia_tipo === 'prestamo' || n.referencia_tipo === 'cambio_turno';

            return (
              <div key={n.id} className="w-full rounded-xl border bg-white p-4 text-left shadow-sm">
                <div className="flex items-start gap-3">
                  <div className="mt-0.5 shrink-0 rounded-lg bg-gray-100 p-2">
                    <Icon className="h-4 w-4 text-gray-400" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm text-gray-900">{n.titulo}</p>
                    <p className="mt-0.5 text-xs text-gray-500">{n.mensaje}</p>
                    <p className="mt-1 text-xs text-gray-400">{new Date(n.created_at).toLocaleDateString('es-CL')}</p>
                  </div>
                  {hasAction && onNavigate && (
                    <button
                      onClick={() => {
                        if (n.referencia_tipo === 'prestamo') {
                          onNavigate('adelantos', { highlightId: n.referencia_id });
                        } else if (n.referencia_tipo === 'cambio_turno') {
                          onNavigate('cambios', { highlightId: n.referencia_id });
                        }
                      }}
                      className="flex min-h-[44px] min-w-[44px] shrink-0 items-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50"
                      aria-label={n.referencia_tipo === 'prestamo' ? 'Ver adelanto' : 'Ver cambio'}
                    >
                      <ExternalLink className="h-3.5 w-3.5" />
                      <span className="hidden sm:inline">
                        {n.referencia_tipo === 'prestamo' ? 'Ver adelanto' : 'Ver cambio'}
                      </span>
                    </button>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
