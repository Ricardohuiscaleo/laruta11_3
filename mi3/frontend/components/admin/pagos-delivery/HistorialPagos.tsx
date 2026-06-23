import React, { useState, useEffect, useCallback } from 'react';
import { History, Search, Filter } from 'lucide-react';
import { pagosDeliveryApi } from '@/lib/pagos-delivery-api';
import type { RiderPago } from '@/types/pagos-delivery';

export default function HistorialPagos() {
  const [pagos, setPagos] = useState<RiderPago[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'pendiente' | 'pagado'>('all');

  const load = useCallback(async () => {
    try {
      const res = await pagosDeliveryApi.getHistory();
      if (res.success) setPagos(res.pagos || []);
    } catch (e) {
      console.error('Error loading history:', e);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const filtered = pagos.filter(p => filter === 'all' || p.estado === filter);

  const formatCurrency = (n: number) => `$${n.toLocaleString('es-CL')}`;

  if (loading) return <div className="text-center py-8 text-gray-500">Cargando historial...</div>;

  return (
    <div className="space-y-4 max-w-4xl mx-auto">
      <div className="flex items-center gap-2">
        {(['all', 'pendiente', 'pagado'] as const).map(f => (
          <button
            key={f}
            onClick={() => setFilter(f)}
            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${filter === f ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
          >
            {f === 'all' ? 'Todos' : f === 'pendiente' ? 'Pendientes' : 'Pagados'}
          </button>
        ))}
      </div>

      <div className="grid gap-2">
        {filtered.length === 0 && (
          <div className="text-center py-12 text-gray-400">
            <History size={48} className="mx-auto mb-2 opacity-30" />
            <p>No hay pagos registrados</p>
          </div>
        )}
        {filtered.map(p => (
          <div key={p.id} className="bg-white rounded-lg border border-gray-200 p-3">
            <div className="flex items-start justify-between gap-3">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <span className="font-medium text-sm">🛵 {p.rider_nombre}</span>
                  <span className={`px-1.5 py-0.5 rounded text-xs font-medium ${p.estado === 'pagado' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}`}>
                    {p.estado === 'pagado' ? 'Pagado' : 'Pendiente'}
                  </span>
                </div>
                <div className="text-xs text-gray-600 space-y-0.5">
                  <div>📦 {p.order_number || 'Sin orden'} · {p.delivery_address || 'Sin dirección'}</div>
                  <div className="font-semibold text-gray-900">{formatCurrency(p.monto)}</div>
                  <div>📅 {new Date(p.fecha + 'T12:00:00').toLocaleDateString('es-CL')}</div>
                </div>
              </div>
              {p.comprobante_url && (
                <a
                  href={p.comprobante_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex-shrink-0 text-xs text-blue-600 hover:text-blue-800 underline"
                >
                  Ver comprobante
                </a>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
