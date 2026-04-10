'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, CheckCircle, XCircle, DollarSign } from 'lucide-react';

interface CreditUser {
  id: number;
  nombre: string;
  limite: number;
  usado: number;
  disponible: number;
  bloqueado: boolean;
  aprobado: boolean;
}

export default function CreditosAdminPage() {
  const [credits, setCredits] = useState<CreditUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [acting, setActing] = useState<number | null>(null);

  const fetchData = () => {
    setLoading(true);
    apiFetch<{ success: boolean; data: CreditUser[] }>('/admin/credits')
      .then(res => setCredits(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { fetchData(); }, []);

  const action = async (id: number, endpoint: string) => {
    setActing(id);
    try {
      await apiFetch(`/admin/credits/${id}/${endpoint}`, { method: 'POST' });
      fetchData();
    } catch (err: any) { alert(err.message); }
    finally { setActing(null); }
  };

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">Créditos R11</h1>

      {credits.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <p className="text-sm text-gray-500">Sin trabajadores con crédito R11</p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
          <table className="w-full text-sm">
            <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
              <tr>
                <th className="px-4 py-3">Nombre</th>
                <th className="px-4 py-3">Límite</th>
                <th className="px-4 py-3">Usado</th>
                <th className="px-4 py-3">Disponible</th>
                <th className="px-4 py-3">Estado</th>
                <th className="px-4 py-3">Acciones</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {credits.map(c => (
                <tr key={c.id}>
                  <td className="px-4 py-3 font-medium">{c.nombre}</td>
                  <td className="px-4 py-3">{formatCLP(c.limite)}</td>
                  <td className="px-4 py-3">{formatCLP(c.usado)}</td>
                  <td className="px-4 py-3 text-green-600 font-medium">{formatCLP(c.disponible)}</td>
                  <td className="px-4 py-3">
                    <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium',
                      c.bloqueado ? 'bg-red-100 text-red-700' : c.aprobado ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'
                    )}>
                      {c.bloqueado ? 'Bloqueado' : c.aprobado ? 'Activo' : 'Pendiente'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1">
                      {!c.aprobado && (
                        <button onClick={() => action(c.id, 'approve')} disabled={acting === c.id}
                          className="rounded p-1 hover:bg-green-50" title="Aprobar">
                          <CheckCircle className="h-4 w-4 text-green-500" />
                        </button>
                      )}
                      <button onClick={() => action(c.id, 'reject')} disabled={acting === c.id}
                        className="rounded p-1 hover:bg-red-50" title="Rechazar">
                        <XCircle className="h-4 w-4 text-red-500" />
                      </button>
                      <button onClick={() => action(c.id, 'manual-payment')} disabled={acting === c.id}
                        className="rounded p-1 hover:bg-gray-100" title="Pago manual">
                        <DollarSign className="h-4 w-4 text-gray-500" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
