'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, formatMonthES } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Loader2, Plus, X, Trash2 } from 'lucide-react';
import type { ApiResponse } from '@/types';

interface AjusteAdmin {
  id: number;
  personal_id: number;
  personal_nombre: string;
  mes: string;
  monto: number;
  concepto: string;
  categoria: string;
  notas: string;
}

interface Categoria { id: number; nombre: string; slug: string; }
interface WorkerOption { id: number; nombre: string; }

function getMonthStr(offset: number) {
  const d = new Date();
  d.setMonth(d.getMonth() + offset);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function AjustesSection() {
  const [monthOffset, setMonthOffset] = useState(0);
  const [ajustes, setAjustes] = useState<AjusteAdmin[]>([]);
  const [categories, setCategories] = useState<Categoria[]>([]);
  const [workers, setWorkers] = useState<WorkerOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ personal_id: '', monto: '', concepto: '', categoria_id: '', notas: '' });
  const [submitting, setSubmitting] = useState(false);

  const mes = getMonthStr(monthOffset);

  const fetchData = () => {
    setLoading(true);
    apiFetch<ApiResponse<AjusteAdmin[]>>(`/admin/adjustments?mes=${mes}`)
      .then(res => setAjustes(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { fetchData(); }, [mes]);

  useEffect(() => {
    apiFetch<ApiResponse<Categoria[]>>('/admin/adjustments/categories').then(res => setCategories(res.data || [])).catch(() => {});
    apiFetch<ApiResponse<WorkerOption[]>>('/admin/personal').then(res => setWorkers(res.data || [])).catch(() => {});
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await apiFetch('/admin/adjustments', {
        method: 'POST',
        body: JSON.stringify({ personal_id: Number(form.personal_id), mes, monto: Number(form.monto), concepto: form.concepto, categoria_id: form.categoria_id ? Number(form.categoria_id) : undefined, notas: form.notas }),
      });
      setShowForm(false);
      setForm({ personal_id: '', monto: '', concepto: '', categoria_id: '', notas: '' });
      fetchData();
    } catch (err: any) { alert(err.message); }
    finally { setSubmitting(false); }
  };

  const deleteAjuste = async (id: number) => {
    if (!confirm('¿Eliminar este ajuste?')) return;
    try { await apiFetch(`/admin/adjustments/${id}`, { method: 'DELETE' }); fetchData(); }
    catch (err: any) { alert(err.message); }
  };

  const grouped = ajustes.reduce<Record<string, AjusteAdmin[]>>((acc, a) => {
    const key = a.personal_nombre || `#${a.personal_id}`;
    if (!acc[key]) acc[key] = [];
    acc[key].push(a);
    return acc;
  }, {});

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="hidden md:block text-2xl font-bold text-gray-900">Ajustes</h1>
        <button onClick={() => setShowForm(true)} className="flex items-center gap-1 rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700">
          <Plus className="h-4 w-4" /> Crear
        </button>
      </div>

      <div className="flex items-center justify-between">
        <button onClick={() => setMonthOffset(o => o - 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronLeft className="h-5 w-5" /></button>
        <span className="font-semibold">{formatMonthES(mes)}</span>
        <button onClick={() => setMonthOffset(o => o + 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronRight className="h-5 w-5" /></button>
      </div>

      {showForm && (
        <div className="rounded-xl border bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between mb-3">
            <h2 className="font-semibold">Nuevo Ajuste</h2>
            <button onClick={() => setShowForm(false)}><X className="h-4 w-4 text-gray-400" /></button>
          </div>
          <form onSubmit={handleSubmit} className="space-y-3">
            <div>
              <label className="block text-sm font-medium text-gray-700">Trabajador</label>
              <select required value={form.personal_id} onChange={e => setForm(f => ({ ...f, personal_id: e.target.value }))}
                className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm">
                <option value="">Seleccionar...</option>
                {workers.map(w => <option key={w.id} value={w.id}>{w.nombre}</option>)}
              </select>
            </div>
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="block text-sm font-medium text-gray-700">Monto</label>
                <input type="number" required value={form.monto} onChange={e => setForm(f => ({ ...f, monto: e.target.value }))}
                  className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm" placeholder="-50000" />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Categoría</label>
                <select value={form.categoria_id} onChange={e => setForm(f => ({ ...f, categoria_id: e.target.value }))}
                  className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm">
                  <option value="">Sin categoría</option>
                  {categories.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                </select>
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Concepto</label>
              <input required value={form.concepto} onChange={e => setForm(f => ({ ...f, concepto: e.target.value }))}
                className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm" placeholder="Adelanto quincenal" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700">Notas</label>
              <input value={form.notas} onChange={e => setForm(f => ({ ...f, notas: e.target.value }))}
                className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
            <button type="submit" disabled={submitting} className="w-full rounded-lg bg-amber-600 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">
              {submitting ? 'Guardando...' : 'Guardar'}
            </button>
          </form>
        </div>
      )}

      {Object.keys(grouped).length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <p className="text-sm text-gray-500">Sin ajustes para este mes</p>
        </div>
      ) : (
        Object.entries(grouped).map(([nombre, items]) => (
          <div key={nombre} className="rounded-xl border bg-white p-4 shadow-sm">
            <h3 className="font-semibold text-amber-700">{nombre}</h3>
            <div className="mt-2 space-y-1">
              {items.map(a => (
                <div key={a.id} className="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm">
                  <div>
                    <span className="font-medium">{a.concepto}</span>
                    {a.notas && <span className="ml-2 text-xs text-gray-400">{a.notas}</span>}
                  </div>
                  <div className="flex items-center gap-2">
                    <span className={a.monto < 0 ? 'font-semibold text-red-600' : 'font-semibold text-green-600'}>{formatCLP(a.monto)}</span>
                    <button onClick={() => deleteAjuste(a.id)} className="rounded p-1 hover:bg-gray-200"><Trash2 className="h-3.5 w-3.5 text-gray-400" /></button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))
      )}
    </div>
  );
}
