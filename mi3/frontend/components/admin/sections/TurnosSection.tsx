'use client';

import { useEffect, useState, useMemo, useRef, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { formatMonthES, cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Loader2, Plus, X } from 'lucide-react';
import type { ApiResponse } from '@/types';

/* ── Interfaces ── */

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

interface WorkerOption {
  id: number;
  nombre: string;
  rol: string;
  foto_url?: string | null;
  foto_rotation?: number;
}

interface WorkerPhoto {
  foto_url: string | null;
  foto_rotation: number;
  nombre: string;
  rol: string;
}

/* ── Helpers ── */

const DAY_NAMES = ['LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM'];
const TIPO_OPTIONS = [
  { value: 'normal', label: 'Normal' },
  { value: 'reemplazo', label: 'Reemplazo' },
  { value: 'seguridad', label: 'Seguridad' },
  { value: 'reemplazo_seguridad', label: 'Reemplazo Seguridad' },
];

function getMonthStr(offset: number) {
  const d = new Date();
  d.setMonth(d.getMonth() + offset);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function getTodayStr() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** Returns 0=Mon … 6=Sun for a Date */
function isoWeekday(d: Date) {
  return (d.getDay() + 6) % 7;
}

function dateToStr(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function rolBorderColor(rol: string | undefined, tipo?: string): string {
  if (tipo === 'seguridad' || tipo === 'reemplazo_seguridad') return 'border-red-500';
  if (rol?.includes('planchero')) return 'border-green-500';
  return 'border-amber-500';
}

/* ── Worker Avatar ── */

function WorkerAvatar({
  name,
  fotoUrl,
  fotoRotation,
  rol,
  tipo,
  isReemplazo,
}: {
  name: string;
  fotoUrl?: string | null;
  fotoRotation?: number;
  rol?: string;
  tipo?: string;
  isReemplazo?: boolean;
}) {
  const initials = name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .slice(0, 2)
    .toUpperCase() || '?';
  const border = rolBorderColor(rol, tipo);

  return (
    <div className="flex flex-col items-center gap-1">
      <div
        className={cn(
          'h-14 w-14 rounded-full border-[3px] overflow-hidden flex items-center justify-center',
          border,
          !fotoUrl && 'bg-gray-200'
        )}
      >
        {fotoUrl ? (
          <img
            src={fotoUrl}
            alt={name}
            className="h-full w-full object-cover"
            style={{ transform: `rotate(${fotoRotation || 0}deg)` }}
          />
        ) : (
          <span className="text-sm font-bold text-gray-600">{initials}</span>
        )}
      </div>
      <span className="text-xs font-medium text-gray-700 text-center leading-tight max-w-[60px] truncate">
        {name.split(' ')[0]}
      </span>
      {isReemplazo && (
        <span className="text-[10px] text-amber-600">↔ Reemplazo</span>
      )}
    </div>
  );
}


/* ── Assign Modal ── */

function AssignModal({
  fecha,
  workers,
  onClose,
  onSaved,
}: {
  fecha: string;
  workers: WorkerOption[];
  onClose: () => void;
  onSaved: () => void;
}) {
  const [personalId, setPersonalId] = useState('');
  const [tipo, setTipo] = useState('normal');
  const [fechaFin, setFechaFin] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [err, setErr] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!personalId) return;
    setSubmitting(true);
    setErr('');
    try {
      await apiFetch<ApiResponse<unknown>>('/admin/shifts', {
        method: 'POST',
        body: JSON.stringify({
          personal_id: Number(personalId),
          fecha,
          fecha_fin: fechaFin || undefined,
          tipo,
        }),
      });
      onSaved();
      onClose();
    } catch (error: unknown) {
      const msg = error instanceof Error ? error.message : 'Error al asignar turno';
      setErr(msg);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label="Asignar turno"
    >
      <div
        className="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-gray-800">Asignar Turno</h2>
          <button onClick={onClose} aria-label="Cerrar">
            <X className="h-5 w-5 text-gray-400" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-3">
          <div>
            <label htmlFor="assign-fecha" className="block text-xs font-medium text-gray-500 mb-1">Fecha inicio</label>
            <input
              id="assign-fecha"
              type="date"
              value={fecha}
              readOnly
              className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label htmlFor="assign-fecha-fin" className="block text-xs font-medium text-gray-500 mb-1">Fecha fin (opcional)</label>
            <input
              id="assign-fecha-fin"
              type="date"
              value={fechaFin}
              onChange={(e) => setFechaFin(e.target.value)}
              min={fecha}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label htmlFor="assign-worker" className="block text-xs font-medium text-gray-500 mb-1">Trabajador</label>
            <select
              id="assign-worker"
              value={personalId}
              onChange={(e) => setPersonalId(e.target.value)}
              required
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
            >
              <option value="">Seleccionar…</option>
              {workers.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.nombre} — {w.rol}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="assign-tipo" className="block text-xs font-medium text-gray-500 mb-1">Tipo</label>
            <select
              id="assign-tipo"
              value={tipo}
              onChange={(e) => setTipo(e.target.value)}
              className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
            >
              {TIPO_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </select>
          </div>

          {err && <p className="text-sm text-red-600" role="alert">{err}</p>}

          <button
            type="submit"
            disabled={submitting || !personalId}
            className="w-full rounded-lg bg-amber-500 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 disabled:opacity-50 transition-colors"
          >
            {submitting ? 'Guardando…' : 'Asignar'}
          </button>
        </form>
      </div>
    </div>
  );
}


/* ── Main Component ── */

export default function TurnosSection() {
  const [monthOffset, setMonthOffset] = useState(0);
  const [turnos, setTurnos] = useState<AdminTurno[]>([]);
  const [workers, setWorkers] = useState<WorkerOption[]>([]);
  const [workerPhotos, setWorkerPhotos] = useState<Record<number, WorkerPhoto>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedDate, setSelectedDate] = useState(getTodayStr());
  const [showAssign, setShowAssign] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);

  const mes = getMonthStr(monthOffset);
  const todayStr = useMemo(getTodayStr, []);

  /* ── Data fetching ── */

  const fetchShifts = useCallback(() => {
    setLoading(true);
    apiFetch<ApiResponse<{ turnos: AdminTurno[] }>>(`/admin/shifts?mes=${mes}`)
      .then((res) =>
        setTurnos(
          res.data?.turnos || (Array.isArray(res.data) ? (res.data as unknown as AdminTurno[]) : [])
        )
      )
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [mes]);

  useEffect(() => { fetchShifts(); }, [fetchShifts]);

  useEffect(() => {
    apiFetch<ApiResponse<WorkerOption[]>>('/admin/personal')
      .then((res) => {
        const list = res.data || [];
        setWorkers(list);
        const photos: Record<number, WorkerPhoto> = {};
        (list as unknown as Array<Record<string, unknown>>).forEach((w) => {
          photos[w.id as number] = {
            foto_url: (w.foto_url as string) || null,
            foto_rotation: (w.foto_rotation as number) || 0,
            nombre: w.nombre as string,
            rol: w.rol as string,
          };
        });
        setWorkerPhotos(photos);
      })
      .catch(() => {});
  }, []);

  /* ── Auto-scroll mobile to today ── */

  useEffect(() => {
    if (!loading && scrollRef.current) {
      const todayEl = scrollRef.current.querySelector('[data-today="true"]');
      todayEl?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
  }, [loading, mes]);

  /* ── Derived data ── */

  const ROL_ORDER: Record<string, number> = { cajero: 0, planchero: 1, administrador: 2, seguridad: 3 };

  const workerRolMap = useMemo(() => {
    const map: Record<number, string> = {};
    workers.forEach((w) => { map[w.id] = w.rol || ''; });
    return map;
  }, [workers]);

  const turnosByDate = useMemo(() => {
    const map: Record<string, AdminTurno[]> = {};
    turnos.forEach((t) => {
      if (!map[t.fecha]) map[t.fecha] = [];
      map[t.fecha].push(t);
    });
    Object.keys(map).forEach((fecha) => {
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

  /* Calendar grid days for the month */
  const calendarDays = useMemo(() => {
    const [y, m] = mes.split('-').map(Number);
    const firstDay = new Date(y, m - 1, 1);
    const lastDay = new Date(y, m, 0);
    const startPad = isoWeekday(firstDay);
    const days: (number | null)[] = Array(startPad).fill(null);
    for (let d = 1; d <= lastDay.getDate(); d++) days.push(d);
    while (days.length % 7 !== 0) days.push(null);
    return days;
  }, [mes]);

  /* All days of the month for mobile horizontal scroll */
  const allMonthDays = useMemo(() => {
    const [y, m] = mes.split('-').map(Number);
    const lastDay = new Date(y, m, 0).getDate();
    const days: { day: number; dateStr: string; dayName: string }[] = [];
    for (let d = 1; d <= lastDay; d++) {
      const date = new Date(y, m - 1, d);
      days.push({
        day: d,
        dateStr: dateToStr(date),
        dayName: DAY_NAMES[isoWeekday(date)],
      });
    }
    return days;
  }, [mes]);

  /* Workers for the selected date */
  const selectedTurnos = useMemo(() => {
    return turnosByDate[selectedDate] || [];
  }, [turnosByDate, selectedDate]);

  const isSelectedToday = selectedDate === todayStr;
  const isSelectedPast = selectedDate < todayStr;

  /* Helper to build dateStr from day number */
  const dayToDateStr = useCallback((day: number) => {
    const [y, m] = mes.split('-');
    return `${y}-${m}-${String(day).padStart(2, '0')}`;
  }, [mes]);

  const handleDayClick = useCallback((dateStr: string) => {
    setSelectedDate(dateStr);
  }, []);


  /* ── Loading / Error ── */

  if (loading) {
    return (
      <div className="flex justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-amber-600" aria-label="Cargando turnos" />
      </div>
    );
  }

  if (error) {
    return <div className="rounded-lg bg-red-50 p-4 text-red-600" role="alert">{error}</div>;
  }

  /* ── Render ── */

  return (
    <div className="space-y-4">
      {/* ── Header: Title + Month Navigation ── */}
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-bold text-gray-500 uppercase tracking-wider">Historial Mensual</h2>
        <div className="flex items-center gap-2">
          <button
            onClick={() => setMonthOffset((o) => o - 1)}
            className="rounded-lg p-1.5 hover:bg-gray-100 transition-colors"
            aria-label="Mes anterior"
          >
            <ChevronLeft className="h-5 w-5 text-gray-600" />
          </button>
          <span className="text-sm font-semibold text-gray-800 min-w-[100px] text-center">
            {formatMonthES(mes).toUpperCase()}
          </span>
          <button
            onClick={() => setMonthOffset((o) => o + 1)}
            className="rounded-lg p-1.5 hover:bg-gray-100 transition-colors"
            aria-label="Mes siguiente"
          >
            <ChevronRight className="h-5 w-5 text-gray-600" />
          </button>
        </div>
      </div>

      {/* ══════════════════════════════════════════════ */}
      {/* ── MOBILE: Horizontal Scroll Week View ────── */}
      {/* ══════════════════════════════════════════════ */}
      <div className="md:hidden">
        <div
          ref={scrollRef}
          className="flex gap-2 overflow-x-auto pb-2 snap-x snap-mandatory"
          style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
        >
          <style>{`.mobile-scroll::-webkit-scrollbar { display: none; }`}</style>
          {allMonthDays.map(({ day, dateStr, dayName }) => {
            const count = (turnosByDate[dateStr] || []).length;
            const isTodayCard = dateStr === todayStr;
            const isPast = dateStr < todayStr;
            const isSelected = dateStr === selectedDate;
            return (
              <button
                key={dateStr}
                data-today={isTodayCard || undefined}
                onClick={() => handleDayClick(dateStr)}
                className={cn(
                  'flex-shrink-0 w-16 rounded-xl border-2 p-2 text-center snap-center transition-all',
                  isTodayCard ? 'border-amber-400 bg-amber-50' :
                  isPast ? 'border-gray-200 bg-gray-50' : 'border-gray-200 bg-white',
                  isSelected && 'ring-2 ring-amber-400'
                )}
                aria-label={`${dayName} ${day}, ${count} turnos`}
                aria-pressed={isSelected}
              >
                <span className="text-[9px] font-medium text-gray-400 uppercase block">{dayName}</span>
                <span className={cn('text-2xl font-bold block', isTodayCard ? 'text-amber-700' : 'text-gray-800')}>
                  {day}
                </span>
                <span className={cn('text-xs font-semibold', count > 0 ? 'text-amber-600' : 'text-gray-300')}>
                  {count > 0 ? count : '—'}
                </span>
              </button>
            );
          })}
        </div>
      </div>


      {/* ══════════════════════════════════════════════ */}
      {/* ── DESKTOP: Monthly Calendar Grid ─────────── */}
      {/* ══════════════════════════════════════════════ */}
      <div className="hidden md:block">
        {/* Day-of-week header */}
        <div className="grid grid-cols-7 gap-2 mb-2">
          {DAY_NAMES.map((name) => (
            <div key={name} className="text-center text-[10px] font-semibold text-gray-400 uppercase">
              {name}
            </div>
          ))}
        </div>

        {/* Calendar grid */}
        <div className="grid grid-cols-7 gap-2">
          {calendarDays.map((day, idx) => {
            if (day === null) {
              return <div key={`pad-${idx}`} className="min-h-[100px]" />;
            }
            const dateStr = dayToDateStr(day);
            const count = (turnosByDate[dateStr] || []).length;
            const isTodayCard = dateStr === todayStr;
            const isPast = dateStr < todayStr;
            const isSelected = dateStr === selectedDate;
            const dayDate = new Date(dateStr + 'T12:00:00');
            const dayName = DAY_NAMES[isoWeekday(dayDate)];

            return (
              <button
                key={dateStr}
                onClick={() => handleDayClick(dateStr)}
                className={cn(
                  'rounded-xl border-2 p-3 text-center cursor-pointer transition-all hover:shadow-md min-h-[100px] flex flex-col items-center justify-center',
                  isTodayCard ? 'border-amber-400 bg-amber-50 shadow-md' :
                  isPast ? 'border-gray-200 bg-gray-50' : 'border-gray-200 bg-white',
                  isSelected && 'ring-2 ring-amber-400'
                )}
                aria-label={`${dayName} ${day}, ${count} turnos`}
                aria-pressed={isSelected}
              >
                <span className="text-[10px] font-medium text-gray-400 uppercase">{dayName}</span>
                <span className={cn('text-3xl font-bold', isTodayCard ? 'text-amber-700' : 'text-gray-800')}>
                  {day}
                </span>
                <span className={cn('text-sm font-semibold mt-1', count > 0 ? 'text-amber-600' : 'text-gray-300')}>
                  {count > 0 ? count : '—'}
                </span>
              </button>
            );
          })}
        </div>
      </div>


      {/* ══════════════════════════════════════════════ */}
      {/* ── Selected Day Detail Panel ─────────────── */}
      {/* ══════════════════════════════════════════════ */}
      <div className="rounded-xl border border-gray-200 bg-white p-4">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wide">
            {isSelectedToday
              ? 'Hoy trabajan en R11:'
              : isSelectedPast
                ? 'Este día trabajaron:'
                : 'Turnos asignados:'}
          </h3>
          <button
            onClick={() => setShowAssign(true)}
            className="flex items-center gap-1 rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-200 transition-colors"
            aria-label="Asignar turno"
          >
            <Plus className="h-3.5 w-3.5" />
            <span className="hidden sm:inline">Asignar turno</span>
          </button>
        </div>

        {selectedTurnos.length === 0 ? (
          <p className="text-sm text-gray-400 text-center py-6">Sin turnos asignados</p>
        ) : (
          <div className="flex flex-wrap gap-4 justify-center sm:justify-start">
            {selectedTurnos.map((t) => {
              const photo = workerPhotos[t.personal_id];
              return (
                <WorkerAvatar
                  key={`${t.id}-${t.personal_id}`}
                  name={t.personal_nombre || photo?.nombre || `#${t.personal_id}`}
                  fotoUrl={photo?.foto_url}
                  fotoRotation={photo?.foto_rotation}
                  rol={photo?.rol || t.tipo}
                  tipo={t.tipo}
                  isReemplazo={!!t.reemplazado_por}
                />
              );
            })}
          </div>
        )}
      </div>

      {/* ── Assign Modal ── */}
      {showAssign && (
        <AssignModal
          fecha={selectedDate}
          workers={workers}
          onClose={() => setShowAssign(false)}
          onSaved={fetchShifts}
        />
      )}
    </div>
  );
}
