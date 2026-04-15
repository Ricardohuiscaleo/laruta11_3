'use client';

import { useEffect, useState, useMemo } from 'react';
import { apiFetch } from '@/lib/api';
import { formatMonthES, cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Loader2, Plus, X, Trash2, ArrowRightLeft } from 'lucide-react';
import type { ApiResponse } from '@/types';

interface AdminTurno {
  id: number | string;
  fecha: string;
  tipo: string;
  personal_id: number;
  personal_nombre: string;
  reemplazado_por: number | null;
  reemplazante_nombre: string | null;
  monto_reemplazo: number | null;
  pago_por: string | null;
  is_dynamic: boolean;
}

interface WorkerOption { id: number; nombre: string; rol: string; }

function getMonthStr(offset: number) {
  const d = new Date();
  d.setMonth(d.getMonth() + offset);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

const PERSON_COLORS: Record<number, string> = {
  1: 'bg-pink-100 text-pink-800',
  12: 'bg-yellow-100 text-yellow-800',
  3: 'bg-green-100 text-green-800',
  5: 'bg-red-100 text-red-800',
  10: 'bg-blue-100 text-blue-800',
  18: 'bg-purple-100 text-purple-800',
};

const PERSON_LEGEND_COLORS: Record<number, string> = {
  1: 'bg-pink-200',
  12: 'bg-yellow-200',
  3: 'bg-green-200',
  5: 'bg-red-200',
  10: 'bg-blue-200',
  18: 'bg-purple-200',
};

export default function TurnosSection() {
  const [monthOffset, setMonthOffset] = useState(0);
  const [turnos, setTurnos] = useState<AdminTurno[]>([]);
  const [workers, setWorkers] = useState<WorkerOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState({ personal_id: '', fecha: '', fecha_fin: '', tipo: 'normal', reemplazado_por: '', monto_reemplazo: '', pago_por: 'empresa' });
  const [submitting, setSubmitting] = useState(false);

  const mes = getMonthStr(monthOffset);

  const fetchData = () => {
    setLoading(true);
    apiFetch<ApiResponse<{ turnos: AdminTurno[] }>>(`/admin/shifts?mes=${mes}`)
      .then(res => setTurnos(res.data?.turnos || (Array.isArray(res.data) ? res.data as any : [])))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { fetchData(); }, [mes]);

  useEffect(() => {
    apiFetch<ApiResponse<WorkerOption[]>>('/admin/personal')
      .then(res => setWorkers(res.data || []))
      .catch(() => {});
  }, []);

  const workerColorMap = useMemo(() => {
    const map: Record<number, string> = {};
    workers.forEach(w => {
      map[w.id] = PERSON_COLORS[w.id] || 'bg-gray-100 text-gray-800';
    });
    return map;
  }, [workers]);

  const calendarDays = useMemo(() => {
    const [y, m] = mes.split('-').map(Number);
    const firstDay = new Date(y, m - 1, 1);
    const lastDay = new Date(y, m, 0);
    const startPad = (firstDay.getDay() + 6) % 7;
    const days: (number | null)[] = Array(startPad).fill(null);
    for (let d = 1; d <= lastDay.getDate(); d++) days.push(d);
    while (days.length % 7 !== 0) days.push(null);
    return days;
  }, [mes]);

  const ROL_ORDER: Record<string, number> = { cajero: 0, planchero: 1, administrador: 2, seguridad: 3 };

  const workerRolMap = useMemo(() => {
    const map: Record<number, string> = {};
    workers.forEach(w => { map[w.id] = (w as any).rol || ''; });
    return map;
  }, [workers]);

  const sortedTurnosByDate = useMemo(() => {
    const map: Record<string, AdminTurno[]> = {};
    turnos.forEach(t => {
      if (!map[t.fecha]) map[t.fecha] = [];
      map[t.fecha].push(t);
    });
    Object.keys(map).forEach(fecha => {
      map[fecha].sort((a, b) => {
        const rolA = a.tipo === 'seguridad' ? 'seguridad' : (workerRolMap[a.personal_id] || '');
        const rolB = b.tipo === 'seguridad' ? 'seguridad' : (workerRolMap[b.personal_id] || '');
        const orderA = Object.entries(ROL_ORDER).find(([k]) => rolA.includes(k))?.[1] ?? 9;
        const orderB = Object.entries(ROL_ORDER).find(([k]) => rolB.includes(k))?.[1] ?? 9;
        return orderA - orderB;
      });
    });
    return map;
  }, [turnos, workerRolMap]);

  const todayStr = useMemo(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }, []);

  // Summary: who works today + shifts per person this month
  const todayWorkers = useMemo(() => {
    return (sortedTurnosByDate[todayStr] || []).map(t => t.personal_nombre?.split(' ')[0] || `#${t.personal_id}`);
  }, [sortedTurnosByDate, todayStr]);

  const shiftsPerPerson = useMemo(() => {
    const counts: Record<string, number> = {};
    turnos.forEach(t => {
      const name = t.personal_nombre?.split(' ')[0] || `#${t.personal_id}`;
      counts[name] = (counts[name] || 0) + 1;
    });
    return counts;
  }, [turnos]);

  // Color legend: only people who have shifts this month
  const legendEntries = useMemo(() => {
    const seen = new Set<number>();
    turnos.forEach(t => seen.add(t.personal_id));
    return Array.from(seen).map(id => {
      const turno = turnos.find(t => t.personal_id === id);
      const name = turno?.personal_nombre?.split(' ')[0] || `#${id}`;
      const color = PERSON_LEGEND_COLORS[id] || 'bg-gray-200';
      return { id, name, color };
    });
  }, [turnos]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await apiFetch('/admin/shifts', {
        method: 'POST',
        body: JSON.stringify({
          personal_id: Number(form.personal_id),
          fecha: form.fecha,
          fecha_fin: form.fecha_fin || undefined,
          tipo: form.tipo,
          reemplazado_por: form.reemplazado_por ? Number(form.reemplazado_por) : undefined,
          monto_reemplazo: form.monto_reemplazo ? Number(form.monto_reemplazo) : undefined,
          pago_por: form.pago_por || undefined,
        }),
      });
      setShowModal(false);
      fetchData();
    } catch (err: any) { alert(err.message); }
    finally { setSubmitting(false); }
  };

  const deleteTurno = async (id: number | string) => {
    if (!confirm('¿Eliminar este turno?')) return;
    try {
      await apiFetch(`/admin/shifts/${id}`, { method: 'DELETE' });
      fetchData();
    } catch (err: any) { alert(err.message); }
  };

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Turnos</h1>
        <button onClick={() => setShowModal(true)} className="flex items-center gap-1 rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700">
          <Plus className="h-4 w-4" /> Asignar
        </button>
      </div>

      {/* Summary bar */}
      <div className="rounded-lg bg-white border p-3 space-y-2">
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <span className="font-medium text-gray-700">Hoy trabaja:</span>
          {todayWorkers.length > 0 ? (
            todayWorkers.map((name, i) => (
              <span key={i} className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">{name}</span>
            ))
          ) : (
            <span className="text-gray-400 text-xs">Sin turnos hoy</span>
          )}
        </div>
        <div className="flex flex-wrap gap-2 text-xs text-gray-500">
          {Object.entries(shiftsPerPerson).map(([name, count]) => (
            <span key={name}>{name}: <span className="font-semibold text-gray-700">{count}</span></span>
          ))}
        </div>
      </div>

      {/* Color legend */}
      {legendEntries.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {legendEntries.map(({ id, name, color }) => (
            <div key={id} className="flex items-center gap-1.5 text-xs text-gray-600">
              <span className={cn('h-3 w-3 rounded-full', color)} />
              {name}
            </div>
          ))}
        </div>
      )}

      <div className="flex items-center justify-between">
        <button onClick={() => setMonthOffset(o => o - 1)} className="rounded-lg p-2 hover:bg-gray-100" aria-label="Mes anterior"><ChevronLeft className="h-5 w-5" /></button>
        <span className="font-semibold">{formatMonthES(mes)}</span>
        <button onClick={() => setMonthOffset(o => o + 1)} className="rounded-lg p-2 hover:bg-gray-100" aria-label="Mes siguiente"><ChevronRight className="h-5 w-5" /></button>
      </div>

      {/* Mobile: list view */}
      <div className="block md:hidden space-y-1">
        {calendarDays.filter((d): d is number => d !== null).map(day => {
          const dateStr = `${mes}-${String(day).padStart(2, '0')}`;
          const dayTurnos = sortedTurnosByDate[dateStr] || [];
          if (dayTurnos.length === 0) return null;
          const isToday = dateStr === todayStr;
          return (
            <div key={day} className={cn('rounded-lg border p-2.5', isToday ? 'border-amber-500 border-2 bg-amber-50' : 'bg-white')}>
              <div className="flex items-center gap-2 mb-1">
                <span className={cn('text-sm font-semibold', isToday ? 'text-amber-700' : 'text-gray-700')}>{day}</span>
                {isToday && <span className="relative flex h-2.5 w-2.5"><span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75" /><span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500" /></span>}
              </div>
              <div className="space-y-1">
                {dayTurnos.map(t => (
                  <div key={t.id} className={cn('flex items-center gap-2 rounded px-2 py-1 text-xs', PERSON_COLORS[t.personal_id] || workerColorMap[t.personal_id] || 'bg-gray-100')}>
                    <span className="flex-1 truncate">{t.personal_nombre?.split(' ')[0] || `#${t.personal_id}`}</span>
                    <span className="text-[10px] opacity-70">{t.tipo}</span>
                    {t.reemplazado_por && <ArrowRightLeft className="h-3 w-3 opacity-60" />}
                    {!t.is_dynamic && typeof t.id === 'number' && (
                      <button onClick={() => deleteTurno(t.id)} className="opacity-50 hover:opacity-100" aria-label="Eliminar turno"><Trash2 className="h-3 w-3" /></button>
                    )}
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>

      {/* Desktop: calendar grid */}
      <div className="hidden md:block">
        <div className="grid grid-cols-7 gap-1 text-center text-xs font-medium text-gray-500">
          {['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].map(d => <div key={d}>{d}</div>)}
        </div>
        <div className="grid grid-cols-7 gap-1 mt-1">
          {calendarDays.map((day, i) => {
            if (day === null) return <div key={i} />;
            const dateStr = `${mes}-${String(day).padStart(2, '0')}`;
            const dayTurnos = sortedTurnosByDate[dateStr] || [];
            const isToday = dateStr === todayStr;
            return (
              <div key={i} className={cn('min-h-[80px] rounded-lg border p-1.5 text-xs', isToday ? 'border-amber-500 border-2 bg-amber-50 ring-1 ring-amber-300' : 'bg-white')}>
                <div className="flex items-center gap-1">
                  <span className={cn('font-medium', isToday ? 'text-amber-700 font-bold' : 'text-gray-600')}>{day}</span>
                  {isToday && <span className="relative flex h-2 w-2"><span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75" /><span className="relative inline-flex rounded-full h-2 w-2 bg-amber-500" /></span>}
                </div>
                <div className="mt-0.5 space-y-0.5">
                  {dayTurnos.slice(0, 3).map(t => (
                    <div key={t.id} className={cn('flex items-center justify-between rounded px-1 py-0.5 text-[10px]',
                      PERSON_COLORS[t.personal_id] || workerColorMap[t.personal_id] || 'bg-gray-100')}>
                      <span className="truncate flex items-center gap-0.5">
                        {t.personal_nombre?.split(' ')[0] || `#${t.personal_id}`}
                        {t.reemplazado_por && <ArrowRightLeft className="h-2.5 w-2.5 opacity-60 shrink-0" />}
                      </span>
                      {!t.is_dynamic && typeof t.id === 'number' && (
                        <button onClick={() => deleteTurno(t.id)} className="ml-0.5 opacity-50 hover:opacity-100" aria-label="Eliminar turno"><Trash2 className="h-2.5 w-2.5" /></button>
                      )}
                    </div>
                  ))}
                  {dayTurnos.length > 3 && <span className="text-[10px] text-gray-400">+{dayTurnos.length - 3}</span>}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-bold">Asignar Turno</h2>
              <button onClick={() => setShowModal(false)} aria-label="Cerrar"><X className="h-5 w-5 text-gray-400" /></button>
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
                  <label className="block text-sm font-medium text-gray-700">Fecha inicio</label>
                  <input type="date" required value={form.fecha} onChange={e => setForm(f => ({ ...f, fecha: e.target.value }))}
                    className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">Fecha fin</label>
                  <input type="date" value={form.fecha_fin} onChange={e => setForm(f => ({ ...f, fecha_fin: e.target.value }))}
                    className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm" />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Tipo</label>
                <select value={form.tipo} onChange={e => setForm(f => ({ ...f, tipo: e.target.value }))}
                  className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm">
                  <option value="normal">Normal</option>
                  <option value="reemplazo">Reemplazo</option>
                  <option value="seguridad">Seguridad</option>
                </select>
              </div>
              {form.tipo === 'reemplazo' && (
                <>
                  <div>
                    <label className="block text-sm font-medium text-gray-700">Reemplazante</label>
                    <select value={form.reemplazado_por} onChange={e => setForm(f => ({ ...f, reemplazado_por: e.target.value }))}
                      className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm">
                      <option value="">Seleccionar...</option>
                      {workers.map(w => <option key={w.id} value={w.id}>{w.nombre}</option>)}
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700">Monto</label>
                    <div className="mt-1 flex gap-2">
                      {[20000, 30000].map(val => (
                        <button key={val} type="button"
                          onClick={() => setForm(f => ({ ...f, monto_reemplazo: String(val) }))}
                          className={cn('flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors',
                            form.monto_reemplazo === String(val) ? 'border-amber-500 bg-amber-50 text-amber-700' : 'hover:bg-gray-50')}>
                          ${val.toLocaleString('es-CL')}
                        </button>
                      ))}
                      <input type="number" placeholder="Otro" value={![20000, 30000].map(String).includes(form.monto_reemplazo) ? form.monto_reemplazo : ''}
                        onChange={e => setForm(f => ({ ...f, monto_reemplazo: e.target.value }))}
                        className="w-24 rounded-lg border px-3 py-2 text-sm" />
                    </div>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700">Pago por</label>
                    <select value={form.pago_por} onChange={e => setForm(f => ({ ...f, pago_por: e.target.value }))}
                      className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm">
                      <option value="empresa">Empresa</option>
                      <option value="empresa_adelanto">Empresa (adelanto)</option>
                      <option value="personal">Personal</option>
                    </select>
                  </div>
                </>
              )}
              <button type="submit" disabled={submitting} className="w-full rounded-lg bg-amber-600 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">
                {submitting ? 'Guardando...' : 'Guardar'}
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
