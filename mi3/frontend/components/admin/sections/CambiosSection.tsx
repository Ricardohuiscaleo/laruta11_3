'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatDateES, cn } from '@/lib/utils';
import { Loader2, CheckCircle, XCircle } from 'lucide-react';

interface SwapRequest {
  id: number;
  fecha_turno: string;
  solicitante: { id: number; nombre: string };
  compañero: { id: number; nombre: string };
  motivo: string;
  estado: 'pendiente' | 'aprobada' | 'rechazada';
  created_at: string;
}

export default function CambiosSection() {
  const [swaps, setSwaps] = useState<SwapRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [acting, setActing] = useState<number | null>(null);

  const fetchData = () => {
    setLoading(true);
    apiFetch<{ success: boolean; data: SwapRequest[] }>('/admin/shift-swaps')
      .then(res => setSwaps(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { fetchData(); }, []);

  const handleAction = async (id: number, action: 'approve' | 'reject') => {
    setActing(id);
    try {
      await apiFetch(`/admin/shift-swaps/${id}/${action}`, { method: 'POST' });
      fetchData();
    } catch (err: any) { alert(err.message); }
    finally { setActing(null); }
  };

  const statusBadge = (estado: string) => {
    const colors: Record<string, string> = {
      pendiente: 'bg-yellow-100 text-yellow-700',
      aprobada: 'bg-green-100 text-green-700',
      rechazada: 'bg-red-100 text-red-700',
    };
    return <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', colors[estado])}>{estado}</span>;
  };

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  const pending = swaps.filter(s => s.estado === 'pendiente');
  const resolved = swaps.filter(s => s.estado !== 'pendiente');

  return (
    <div className="space-y-4">
      <h1 className="hidden md:block text-2xl font-bold text-gray-900">Solicitudes de Cambio</h1>

      {pending.length > 0 && (
        <div className="space-y-2">
          <h2 className="text-sm font-semibold text-amber-700">Pendientes ({pending.length})</h2>
          {pending.map(s => (
            <div key={s.id} className="rounded-xl border-2 border-yellow-200 bg-yellow-50 p-4 shadow-sm">
              <div className="flex items-start justify-between">
                <div>
                  <p className="font-medium">{s.solicitante.nombre} → {s.compañero.nombre}</p>
                  <p className="text-sm text-gray-600">Turno: {formatDateES(s.fecha_turno)}</p>
                  {s.motivo && <p className="text-xs text-gray-400 mt-1">{s.motivo}</p>}
                </div>
                <div className="flex gap-1">
                  <button onClick={() => handleAction(s.id, 'approve')} disabled={acting === s.id}
                    className="rounded-lg bg-green-500 p-2 text-white hover:bg-green-600 disabled:opacity-50" title="Aprobar">
                    <CheckCircle className="h-4 w-4" />
                  </button>
                  <button onClick={() => handleAction(s.id, 'reject')} disabled={acting === s.id}
                    className="rounded-lg bg-red-500 p-2 text-white hover:bg-red-600 disabled:opacity-50" title="Rechazar">
                    <XCircle className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {pending.length === 0 && (
        <div className="rounded-xl border bg-white p-6 text-center shadow-sm">
          <p className="text-sm text-gray-500">Sin solicitudes pendientes</p>
        </div>
      )}

      {resolved.length > 0 && (
        <div className="space-y-2">
          <h2 className="text-sm font-semibold text-gray-500">Historial</h2>
          {resolved.map(s => (
            <div key={s.id} className="rounded-xl border bg-white p-4 shadow-sm">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">{s.solicitante.nombre} → {s.compañero.nombre}</p>
                  <p className="text-xs text-gray-500">{formatDateES(s.fecha_turno)}</p>
                </div>
                {statusBadge(s.estado)}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
