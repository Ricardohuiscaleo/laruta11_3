'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatDateES, cn } from '@/lib/utils';
import { Loader2, Plus, X } from 'lucide-react';
import type { SolicitudCambio, ApiResponse, Personal } from '@/types';

export default function CambiosPage() {
  const [solicitudes, setSolicitudes] = useState<SolicitudCambio[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [companions, setCompanions] = useState<{ id: number; nombre: string }[]>([]);
  const [formData, setFormData] = useState({ fecha_turno: '', compañero_id: '', motivo: '' });
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState('');

  const fetchData = () => {
    setLoading(true);
    apiFetch<ApiResponse<SolicitudCambio[]>>('/worker/shift-swaps')
      .then(res => setSolicitudes(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { fetchData(); }, []);

  const openForm = async () => {
    setShowForm(true);
    try {
      const res = await apiFetch<ApiResponse<{ id: number; nombre: string }[]>>('/worker/shift-swaps/companions');
      setCompanions(res.data || []);
    } catch {
      // companions might come from a different endpoint or be embedded
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setFormError('');
    try {
      await apiFetch('/worker/shift-swaps', {
        method: 'POST',
        body: JSON.stringify({
          fecha_turno: formData.fecha_turno,
          compañero_id: Number(formData.compañero_id),
          motivo: formData.motivo || null,
        }),
      });
      setShowForm(false);
      setFormData({ fecha_turno: '', compañero_id: '', motivo: '' });
      fetchData();
    } catch (err: any) {
      setFormError(err.message || 'Error al enviar solicitud');
    } finally {
      setSubmitting(false);
    }
  };

  const statusBadge = (estado: string) => {
    const colors: Record<string, string> = {
      pendiente: 'bg-yellow-100 text-yellow-700',
      aprobada: 'bg-green-100 text-green-700',
      rechazada: 'bg-red-100 text-red-700',
    };
    return <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', colors[estado] || 'bg-gray-100')}>{estado}</span>;
  };

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Solicitudes de Cambio</h1>
        <button onClick={openForm} className="flex items-center gap-1 rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700">
          <Plus className="h-4 w-4" /> Nueva
        </button>
      </div>

      {showForm && (
        <div className="rounded-xl border bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <h2 className="font-semibold">Nueva Solicitud</h2>
            <button onClick={() => setShowForm(false)}><X className="h-4 w-4 text-gray-400" /></button>
          </div>
          {formError && <div className="mb-3 rounded-lg bg-red-50 p-2 text-sm text-red-600">{formError}</div>}
          <form onSubmit={handleSubmit} className="space-y-3">
            <div>
              <label className="block text-sm font-medium text-gray-700">Fecha del turno</label>
              <input type="date" required value={formData.fecha_turno} onChange={e => setFormData(f => ({ ...f, fecha_turno: e.target.value }))}
                className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Compañero</label>
              {companions.length > 0 ? (
                <select required value={formData.compañero_id} onChange={e => setFormData(f => ({ ...f, compañero_id: e.target.value }))}
                  className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                  <option value="">Seleccionar...</option>
                  {companions.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                </select>
              ) : (
                <input type="number" required placeholder="ID del compañero" value={formData.compañero_id}
                  onChange={e => setFormData(f => ({ ...f, compañero_id: e.target.value }))}
                  className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500" />
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Motivo (opcional)</label>
              <input type="text" value={formData.motivo} onChange={e => setFormData(f => ({ ...f, motivo: e.target.value }))}
                className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500" placeholder="Ej: Cita médica" />
            </div>
            <button type="submit" disabled={submitting} className="w-full rounded-lg bg-amber-600 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">
              {submitting ? 'Enviando...' : 'Enviar Solicitud'}
            </button>
          </form>
        </div>
      )}

      {solicitudes.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <p className="text-sm text-gray-500">No tienes solicitudes de cambio</p>
        </div>
      ) : (
        <div className="space-y-2">
          {solicitudes.map(s => (
            <div key={s.id} className="rounded-xl border bg-white p-4 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">{formatDateES(s.fecha_turno)}</span>
                {statusBadge(s.estado)}
              </div>
              <p className="mt-1 text-sm text-gray-600">Compañero: {s.compañero.nombre}</p>
              {s.motivo && <p className="text-xs text-gray-400">{s.motivo}</p>}
              <p className="mt-1 text-xs text-gray-400">Solicitado: {new Date(s.created_at).toLocaleDateString('es-CL')}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
