'use client';

import { useEffect, useState, useMemo, useRef, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { formatMonthES, formatCLP, cn } from '@/lib/utils';
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
  activo?: boolean;
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

function isSeguridad(tipo: string): boolean {
  return tipo === 'seguridad' || tipo === 'reemplazo_seguridad';
}

/* ── Worker Avatar ── */

function WorkerAvatar({
  name,
  fotoUrl,
  fotoRotation,
  rol,
  tipo,
  isReemplazo,
  size = 'md',
}: {
  name: string;
  fotoUrl?: string | null;
  fotoRotation?: number;
  rol?: string;
  tipo?: string;
  isReemplazo?: boolean;
  size?: 'md' | 'lg';
}) {
  const initials = name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .slice(0, 2)
    .toUpperCase() || '?';
  const border = rolBorderColor(rol, tipo);
  const sizeClass = size === 'lg' ? 'h-20 w-20' : 'h-14 w-14';
  const textSize = size === 'lg' ? 'text-lg' : 'text-sm';

  return (
    <div className="flex flex-col items-center gap-1">
      <div
        className={cn(
          'rounded-full border-[3px] overflow-hidden flex items-center justify-center',
          sizeClass,
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
          <span className={cn('font-bold text-gray-600', textSize)}>{initials}</span>
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


/* ── Profile Modal ── */

function ProfileModal({
  turno,
  workerPhotos,
  allTurnos,
  workerRolMap,
  mes,
  workers,
  onClose,
  onReplacementChange,
}: {
  turno: AdminTurno;
  workerPhotos: Record<number, WorkerPhoto>;
  allTurnos: AdminTurno[];
  workerRolMap: Record<number, string>;
  mes: string;
  workers: WorkerOption[];
  onClose: () => void;
  onReplacementChange: () => void;
}) {
  const [showPicker, setShowPicker] = useState(false);
  const [removing, setRemoving] = useState(false);

  const photo = workerPhotos[turno.personal_id];
  const name = turno.personal_nombre || photo?.nombre || `#${turno.personal_id}`;
  const rol = photo?.rol || workerRolMap[turno.personal_id] || turno.tipo;

  const tipoLabel = (() => {
    switch (turno.tipo) {
      case 'normal': return 'Turno normal';
      case 'reemplazo': return 'Reemplazo';
      case 'seguridad': return 'Seguridad';
      case 'reemplazo_seguridad': return 'Reemplazo seguridad';
      default: return turno.tipo;
    }
  })();

  const isSeg = isSeguridad(turno.tipo);

  // Month stats for this worker
  const monthTurnos = allTurnos.filter((t) => t.personal_id === turno.personal_id);
  const totalShifts = monthTurnos.length;
  const replacementsDone = allTurnos.filter((t) => t.reemplazado_por === turno.personal_id).length;
  const replacementsReceived = monthTurnos.filter((t) => !!t.reemplazado_por).length;

  const handleRemoveReplacement = async () => {
    setRemoving(true);
    try {
      await apiFetch<ApiResponse<unknown>>('/admin/shifts', {
        method: 'POST',
        body: JSON.stringify({
          personal_id: turno.personal_id,
          fecha: turno.fecha,
          tipo: isSeg ? 'seguridad' : 'normal',
          reemplazado_por: null,
          monto_reemplazo: null,
          pago_por: null,
        }),
      });
      onReplacementChange();
      onClose();
    } catch {
      setRemoving(false);
    }
  };

  if (showPicker) {
    return (
      <ReplacePicker
        turno={turno}
        workers={workers}
        allTurnos={allTurnos}
        workerPhotos={workerPhotos}
        onBack={() => setShowPicker(false)}
        onReplacementChange={onReplacementChange}
        onClose={onClose}
      />
    );
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 animate-in fade-in duration-200"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label={`Perfil de ${name}`}
    >
      <div
        className="w-full max-w-xs rounded-xl bg-white p-5 shadow-xl"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Large avatar */}
        <div className="flex flex-col items-center mb-4">
          <WorkerAvatar
            name={name}
            fotoUrl={photo?.foto_url}
            fotoRotation={photo?.foto_rotation}
            rol={rol}
            tipo={turno.tipo}
            size="lg"
          />
          <h3 className="mt-2 text-base font-bold text-gray-800">{name}</h3>
          <span className="text-xs text-gray-500 capitalize">{rol}</span>
        </div>

        {/* Shift info */}
        <div className="space-y-2 mb-4">
          <div className="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
            <span className="text-xs text-gray-500">Tipo turno</span>
            <span className="text-xs font-semibold text-gray-700">{tipoLabel}</span>
          </div>
          <div className="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
            <span className="text-xs text-gray-500">Fecha</span>
            <span className="text-xs font-semibold text-gray-700">{turno.fecha}</span>
          </div>
          {turno.reemplazado_por && turno.reemplazante_nombre && (
            <div className="flex items-center justify-between rounded-lg bg-green-50 px-3 py-2">
              <span className="text-xs text-green-600">Reemplazante</span>
              <span className="text-xs font-semibold text-green-700">{turno.reemplazante_nombre}</span>
            </div>
          )}
          {turno.monto_reemplazo && (
            <div className="flex items-center justify-between rounded-lg bg-blue-50 px-3 py-2">
              <span className="text-xs text-blue-600">Monto reemplazo</span>
              <span className="text-xs font-semibold text-blue-700">{formatCLP(turno.monto_reemplazo)}</span>
            </div>
          )}
        </div>

        {/* Month stats */}
        <div className="border-t border-gray-100 pt-3 mb-4">
          <h4 className="text-[10px] font-semibold text-gray-400 uppercase mb-2">
            Estadísticas {formatMonthES(mes)}
          </h4>
          <div className="grid grid-cols-3 gap-2 text-center">
            <div className="rounded-lg bg-gray-50 p-2">
              <span className="text-lg font-bold text-gray-800">{totalShifts}</span>
              <span className="block text-[10px] text-gray-500">Turnos</span>
            </div>
            <div className="rounded-lg bg-green-50 p-2">
              <span className="text-lg font-bold text-green-700">{replacementsDone}</span>
              <span className="block text-[10px] text-green-600">Reemplazos hechos</span>
            </div>
            <div className="rounded-lg bg-amber-50 p-2">
              <span className="text-lg font-bold text-amber-700">{replacementsReceived}</span>
              <span className="block text-[10px] text-amber-600">Reemplazos recibidos</span>
            </div>
          </div>
        </div>

        {/* Replacement action */}
        <div className="border-t border-gray-100 pt-3 mb-4">
          {turno.reemplazado_por ? (
            <div className="space-y-2">
              <p className="text-xs text-center text-amber-700 font-medium">
                Este turno tiene reemplazo
              </p>
              <div className="flex gap-2">
                <button
                  onClick={() => setShowPicker(true)}
                  className="flex-1 rounded-lg bg-amber-100 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-200 transition-colors"
                >
                  Cambiar Reemplazante
                </button>
                <button
                  onClick={handleRemoveReplacement}
                  disabled={removing}
                  className="flex-1 rounded-lg bg-red-50 py-2 text-xs font-semibold text-red-600 hover:bg-red-100 transition-colors disabled:opacity-50"
                >
                  {removing ? 'Eliminando…' : 'Quitar Reemplazo'}
                </button>
              </div>
            </div>
          ) : (
            <button
              onClick={() => setShowPicker(true)}
              className="w-full rounded-lg bg-amber-500 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 transition-colors"
            >
              Asignar Reemplazo
            </button>
          )}
        </div>

        <button
          onClick={onClose}
          className="w-full rounded-lg bg-gray-100 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-200 transition-colors"
        >
          Cerrar
        </button>
      </div>
    </div>
  );
}


/* ── Replace Picker ── */

function ReplacePicker({
  turno,
  workers,
  allTurnos,
  workerPhotos,
  onBack,
  onReplacementChange,
  onClose,
}: {
  turno: AdminTurno;
  workers: WorkerOption[];
  allTurnos: AdminTurno[];
  workerPhotos: Record<number, WorkerPhoto>;
  onBack: () => void;
  onReplacementChange: () => void;
  onClose: () => void;
}) {
  const [search, setSearch] = useState('');
  const [submitting, setSubmitting] = useState<number | null>(null);

  const isSeg = isSeguridad(turno.tipo);

  const sameCategoryIds = useMemo(() => {
    return new Set(
      allTurnos
        .filter((t) => {
          if (t.personal_id === turno.personal_id) return false;
          if (t.fecha !== turno.fecha) return false;
          return isSeg ? isSeguridad(t.tipo) : !isSeguridad(t.tipo);
        })
        .map((t) => t.personal_id)
    );
  }, [allTurnos, turno.personal_id, turno.fecha, isSeg]);

  const filtered = useMemo(() => {
    return workers.filter((w) => {
      if (w.activo === false) return false;
      if (w.id === turno.personal_id) return false;
      if (sameCategoryIds.has(w.id)) return false;
      if (isSeg && !w.rol?.includes('seguridad')) return false;
      if (!isSeg && !w.rol?.includes('cajero') && !w.rol?.includes('planchero')) return false;
      if (search && !w.nombre.toLowerCase().includes(search.toLowerCase())) return false;
      return true;
    });
  }, [workers, turno.personal_id, sameCategoryIds, isSeg, search]);

  const handleSelect = async (replacementId: number) => {
    setSubmitting(replacementId);
    try {
      const monto = isSeg ? 30000 : 20000;
      const tipo = isSeg ? 'reemplazo_seguridad' : 'reemplazo';
      await apiFetch<ApiResponse<unknown>>('/admin/shifts', {
        method: 'POST',
        body: JSON.stringify({
          personal_id: turno.personal_id,
          fecha: turno.fecha,
          tipo,
          reemplazado_por: replacementId,
          monto_reemplazo: monto,
          pago_por: 'empresa',
        }),
      });
      onReplacementChange();
      onClose();
    } catch {
      setSubmitting(null);
    }
  };

  return (
    <div
      className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 animate-in fade-in duration-200"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label="Seleccionar reemplazo"
    >
      <div
        className="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between mb-4">
          <button
            onClick={onBack}
            className="text-sm text-amber-600 hover:text-amber-700 font-semibold"
          >
            ← Volver
          </button>
          <h2 className="text-base font-bold text-gray-800">Reemplazo</h2>
          <button onClick={onClose} aria-label="Cerrar">
            <X className="h-5 w-5 text-gray-400" />
          </button>
        </div>

        <p className="text-xs text-gray-500 mb-3 text-center">
          ¿Quién reemplaza a <strong>{turno.personal_nombre?.split(' ')[0]}</strong> el <strong>{turno.fecha}</strong>?
          <span className="ml-1 font-medium text-amber-600">({formatCLP(isSeg ? 30000 : 20000)})</span>
        </p>

        <input
          type="text"
          placeholder="Buscar trabajador…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-amber-300"
          autoFocus
        />

        <div className="max-h-[300px] overflow-y-auto space-y-1">
          {filtered.map((w) => {
            const photo = workerPhotos[w.id];
            const isLoading = submitting === w.id;
            return (
              <button
                key={w.id}
                onClick={() => handleSelect(w.id)}
                disabled={submitting !== null}
                className="w-full flex items-center gap-3 rounded-lg p-2.5 hover:bg-amber-50 transition-colors disabled:opacity-50"
              >
                <WorkerAvatar
                  name={w.nombre}
                  fotoUrl={photo?.foto_url}
                  fotoRotation={photo?.foto_rotation}
                  rol={w.rol}
                  size="md"
                />
                <div className="flex-1 text-left">
                  <span className="text-sm font-medium text-gray-800">{w.nombre}</span>
                  <span className="ml-2 text-xs text-gray-400 capitalize">{w.rol}</span>
                </div>
                {isLoading && <Loader2 className="h-4 w-4 animate-spin text-amber-500" />}
              </button>
            );
          })}
          {filtered.length === 0 && (
            <p className="text-center text-sm text-gray-400 py-8">
              {search ? 'Sin resultados' : 'No hay trabajadores disponibles este día'}
            </p>
          )}
        </div>
      </div>
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


/* ── Replace Row ── */

function ReplaceRow({
  turno,
  workerPhotos,
  onViewProfile,
  onReplacementChange,
}: {
  turno: AdminTurno;
  workerPhotos: Record<number, WorkerPhoto>;
  onViewProfile: () => void;
  onReplacementChange: () => void;
}) {
  const [deleting, setDeleting] = useState(false);
  const [confirm, setConfirm] = useState(false);

  const handleDelete = async () => {
    setDeleting(true);
    const isSeg = isSeguridad(turno.tipo);
    try {
      await apiFetch<ApiResponse<unknown>>('/admin/shifts', {
        method: 'POST',
        body: JSON.stringify({
          personal_id: turno.personal_id,
          fecha: turno.fecha,
          tipo: isSeg ? 'seguridad' : 'normal',
          reemplazado_por: null,
          monto_reemplazo: null,
          pago_por: null,
        }),
      });
      onReplacementChange();
    } catch {
      setDeleting(false);
      setConfirm(false);
    }
  };

  const reemplazantePhoto = workerPhotos[turno.reemplazado_por!];

  return (
    <tr className="border-b border-gray-50 hover:bg-gray-50 transition-colors">
      <td className="py-2.5 pr-3">
        <button onClick={onViewProfile} className="text-xs text-gray-500 hover:text-amber-600 font-medium">
          {turno.fecha}
        </button>
      </td>
      <td className="py-2.5 pr-3">
        <button onClick={onViewProfile} className="flex items-center gap-2 text-xs">
          <span className="h-5 w-5 rounded-full bg-gray-200 flex items-center justify-center text-[8px] font-bold text-gray-500 overflow-hidden">
            {workerPhotos[turno.personal_id]?.foto_url ? (
              <img src={workerPhotos[turno.personal_id].foto_url!} alt="" className="h-full w-full object-cover"
                style={{ transform: `rotate(${workerPhotos[turno.personal_id].foto_rotation || 0}deg)` }} />
            ) : (
              turno.personal_nombre?.[0]?.toUpperCase() || '?'
            )}
          </span>
          <span className="text-gray-700 truncate max-w-[90px]">{turno.personal_nombre?.split(' ')[0]}</span>
        </button>
      </td>
      <td className="py-2.5 pr-3">
        <div className="flex items-center gap-2 text-xs">
          <span className="h-5 w-5 rounded-full bg-amber-100 flex items-center justify-center text-[8px] font-bold text-amber-700 overflow-hidden">
            {reemplazantePhoto?.foto_url ? (
              <img src={reemplazantePhoto.foto_url} alt="" className="h-full w-full object-cover"
                style={{ transform: `rotate(${reemplazantePhoto.foto_rotation || 0}deg)` }} />
            ) : (
              turno.reemplazante_nombre?.[0]?.toUpperCase() || '?'
            )}
          </span>
          <span className="text-amber-700 font-medium truncate max-w-[90px]">{turno.reemplazante_nombre?.split(' ')[0]}</span>
        </div>
      </td>
      <td className="py-2.5 pr-3 text-right text-xs font-medium text-gray-600">
        {turno.monto_reemplazo != null ? formatCLP(turno.monto_reemplazo) : '—'}
      </td>
      <td className="py-2.5 text-right">
        {confirm ? (
          <div className="flex items-center justify-end gap-1.5">
            <button
              onClick={handleDelete}
              disabled={deleting}
              className="text-[10px] font-semibold text-red-600 hover:text-red-800 disabled:opacity-50"
            >
              {deleting ? '…' : 'Sí'}
            </button>
            <button
              onClick={() => setConfirm(false)}
              className="text-[10px] text-gray-400 hover:text-gray-600"
            >
              No
            </button>
          </div>
        ) : (
          <button
            onClick={(e) => { e.stopPropagation(); setConfirm(true); }}
            className="text-[10px] font-semibold text-red-400 hover:text-red-600 transition-colors"
          >
            Eliminar
          </button>
        )}
      </td>
    </tr>
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

  const [profileModal, setProfileModal] = useState<AdminTurno | null>(null);
  const [successMsg, setSuccessMsg] = useState('');

  const mes = getMonthStr(monthOffset);
  const todayStr = useMemo(getTodayStr, []);

  /* ── Data fetching ── */

  const fetchShifts = useCallback((silent = false) => {
    if (!silent) setLoading(true);
    apiFetch<ApiResponse<{ turnos: AdminTurno[] }>>(`/admin/shifts?mes=${mes}`)
      .then((res) =>
        setTurnos(
          res.data?.turnos || (Array.isArray(res.data) ? (res.data as unknown as AdminTurno[]) : [])
        )
      )
      .catch((e) => { if (!silent) setError(e.message); })
      .finally(() => { if (!silent) setLoading(false); });
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

  // Auto-clear success toast
  useEffect(() => {
    if (!successMsg) return;
    const timer = setTimeout(() => setSuccessMsg(''), 3000);
    return () => clearTimeout(timer);
  }, [successMsg]);

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

  const allMonthDays = useMemo(() => {
    const [y, m] = mes.split('-').map(Number);
    const lastDay = new Date(y, m, 0).getDate();
    const days: { day: number; dateStr: string; dayName: string }[] = [];
    for (let d = 1; d <= lastDay; d++) {
      const date = new Date(y, m - 1, d);
      days.push({ day: d, dateStr: dateToStr(date), dayName: DAY_NAMES[isoWeekday(date)] });
    }
    return days;
  }, [mes]);

  const selectedTurnos = useMemo(() => turnosByDate[selectedDate] || [], [turnosByDate, selectedDate]);

  const isSelectedToday = selectedDate === todayStr;
  const isSelectedPast = selectedDate < todayStr;

  // Split turnos into R11 and Seguridad sections
  const r11Turnos = useMemo(
    () => selectedTurnos.filter((t) => !isSeguridad(t.tipo)),
    [selectedTurnos]
  );
  const seguridadTurnos = useMemo(
    () => selectedTurnos.filter((t) => isSeguridad(t.tipo)),
    [selectedTurnos]
  );

  /* ── Handlers ── */

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
      {/* ── Success toast ── */}
      {successMsg && (
        <div className="fixed top-4 right-4 z-50 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg animate-fade-in" role="status">
          ✓ {successMsg}
        </div>
      )}

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
          {allMonthDays.map(({ day, dateStr, dayName }) => {
            const dayTurnosMobile = turnosByDate[dateStr] || [];
            const count = dayTurnosMobile.length;
            const isTodayCard = dateStr === todayStr;
            const isPast = dateStr < todayStr;
            const isSelected = dateStr === selectedDate;
            const hasReplacementMobile = dayTurnosMobile.some((t) => !!t.reemplazado_por);
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
                {hasReplacementMobile && (
                  <span className="w-1.5 h-1.5 rounded-full bg-orange-400 mx-auto mt-0.5 block" />
                )}
              </button>
            );
          })}
        </div>
      </div>

      {/* ══════════════════════════════════════════════ */}
      {/* ── DESKTOP: Monthly Calendar Grid ─────────── */}
      {/* ══════════════════════════════════════════════ */}
      <div className="hidden md:block">
        <div className="grid grid-cols-7 gap-2 mb-2">
          {DAY_NAMES.map((name) => (
            <div key={name} className="text-center text-[10px] font-semibold text-gray-400 uppercase">
              {name}
            </div>
          ))}
        </div>
        <div className="grid grid-cols-7 gap-2">
          {calendarDays.map((day, idx) => {
            if (day === null) {
              return <div key={`pad-${idx}`} className="min-h-[72px]" />;
            }
            const dateStr = dayToDateStr(day);
            const dayTurnos = turnosByDate[dateStr] || [];
            const count = dayTurnos.length;
            const isTodayCard = dateStr === todayStr;
            const isPast = dateStr < todayStr;
            const isSelected = dateStr === selectedDate;
            const dayDate = new Date(dateStr + 'T12:00:00');
            const dayName = DAY_NAMES[isoWeekday(dayDate)];
            const hasReplacement = dayTurnos.some((t) => !!t.reemplazado_por);

            // Group workers: R11 first, then Seguridad
            const r11Workers = dayTurnos.filter((t) => !isSeguridad(t.tipo));
            const segWorkers = dayTurnos.filter((t) => isSeguridad(t.tipo));
            const allGrouped = [...r11Workers, ...segWorkers];
            const maxVisible = 5;
            const visible = allGrouped.slice(0, maxVisible);
            const overflow = allGrouped.length - maxVisible;
            const separatorIdx = r11Workers.length; // index where seguridad starts

            return (
              <button
                key={dateStr}
                onClick={() => handleDayClick(dateStr)}
                className={cn(
                  'rounded-xl border-2 p-2 text-center cursor-pointer transition-all hover:shadow-md min-h-[72px] flex flex-col items-center justify-center gap-0.5',
                  isTodayCard ? 'border-amber-400 bg-amber-50 shadow-md' :
                  isPast ? 'border-gray-200 bg-gray-50' : 'border-gray-200 bg-white',
                  isSelected && 'ring-2 ring-amber-400',
                  hasReplacement && 'border-l-[3px] border-l-orange-400'
                )}
                aria-label={`${dayName} ${day}, ${count} turnos`}
                aria-pressed={isSelected}
              >
                <div className="flex items-baseline gap-1">
                  <span className="text-[10px] font-medium text-gray-400 uppercase">{dayName}</span>
                  <span className={cn('text-lg font-bold', isTodayCard ? 'text-amber-700' : 'text-gray-800')}>
                    {day}
                  </span>
                </div>
                {count > 0 ? (
                  <div className="flex items-center gap-0.5 flex-wrap justify-center">
                    {visible.map((t, i) => {
                      const photo = workerPhotos[t.personal_id];
                      const initial = (t.personal_nombre || photo?.nombre || '?')[0].toUpperCase();
                      const showSep = separatorIdx > 0 && i === separatorIdx && segWorkers.length > 0;
                      return (
                        <span key={`${t.id}-${t.personal_id}`} className="flex items-center gap-0.5">
                          {showSep && <span className="text-gray-300 text-[10px] mx-0.5">|</span>}
                          {photo?.foto_url ? (
                            <img
                              src={photo.foto_url}
                              alt={initial}
                              className="h-6 w-6 rounded-full object-cover border border-gray-200"
                              style={{ transform: `rotate(${photo.foto_rotation || 0}deg)` }}
                            />
                          ) : (
                            <span className="h-6 w-6 rounded-full bg-gray-200 flex items-center justify-center text-[9px] font-bold text-gray-500 border border-gray-300">
                              {initial}
                            </span>
                          )}
                        </span>
                      );
                    })}
                    {overflow > 0 && (
                      <span className="text-[9px] font-semibold text-gray-400 ml-0.5">+{overflow}</span>
                    )}
                  </div>
                ) : (
                  <span className="text-xs font-semibold text-gray-300">—</span>
                )}
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
          <div className="space-y-5">
            {/* ── 🍔 La Ruta 11 Section ── */}
            {r11Turnos.length > 0 && (
              <div>
                <h4 className="text-xs font-semibold text-amber-700 mb-2 flex items-center gap-1.5">
                  🍔 La Ruta 11
                </h4>
                <div className="flex flex-wrap gap-4 justify-center sm:justify-start">
                  {r11Turnos.map((t) => {
                    const photo = workerPhotos[t.personal_id];
                    return (
                      <div
                        key={`${t.id}-${t.personal_id}`}
                        onClick={() => setProfileModal(t)}
                        className="cursor-pointer transition-transform hover:scale-105 active:scale-95"
                        role="button"
                        tabIndex={0}
                        aria-label={`Ver perfil de ${t.personal_nombre}`}
                        onKeyDown={(e) => { if (e.key === 'Enter') setProfileModal(t); }}
                      >
                        <WorkerAvatar
                          name={t.personal_nombre || photo?.nombre || `#${t.personal_id}`}
                          fotoUrl={photo?.foto_url}
                          fotoRotation={photo?.foto_rotation}
                          rol={photo?.rol || t.tipo}
                          tipo={t.tipo}
                          isReemplazo={!!t.reemplazado_por}
                        />
                        {t.reemplazado_por && t.reemplazante_nombre && (
                          <div className="flex flex-col items-center mt-0.5 max-w-[80px]">
                            <span className="text-[10px] line-through text-gray-400 truncate w-full text-center">
                              {t.personal_nombre?.split(' ')[0]}
                            </span>
                            <span className="text-[10px] text-gray-400">→</span>
                            <span className="text-[10px] font-medium text-gray-700 truncate w-full text-center">
                              {t.reemplazante_nombre.split(' ')[0]}
                            </span>
                            {t.monto_reemplazo != null && (
                              <span className="text-[10px] text-gray-500">{formatCLP(t.monto_reemplazo)}</span>
                            )}
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {/* ── 🛡️ Cam Seguridad Section ── */}
            {seguridadTurnos.length > 0 && (
              <div>
                <h4 className="text-xs font-semibold text-red-700 mb-2 flex items-center gap-1.5">
                  🛡️ Cam Seguridad
                </h4>
                <div className="flex flex-wrap gap-4 justify-center sm:justify-start">
                  {seguridadTurnos.map((t) => {
                    const photo = workerPhotos[t.personal_id];
                    return (
                      <div
                        key={`${t.id}-${t.personal_id}`}
                        onClick={() => setProfileModal(t)}
                        className="cursor-pointer transition-transform hover:scale-105 active:scale-95"
                        role="button"
                        tabIndex={0}
                        aria-label={`Ver perfil de ${t.personal_nombre}`}
                        onKeyDown={(e) => { if (e.key === 'Enter') setProfileModal(t); }}
                      >
                        <WorkerAvatar
                          name={t.personal_nombre || photo?.nombre || `#${t.personal_id}`}
                          fotoUrl={photo?.foto_url}
                          fotoRotation={photo?.foto_rotation}
                          rol={photo?.rol || t.tipo}
                          tipo={t.tipo}
                          isReemplazo={!!t.reemplazado_por}
                        />
                        {t.reemplazado_por && t.reemplazante_nombre && (
                          <div className="flex flex-col items-center mt-0.5 max-w-[80px]">
                            <span className="text-[10px] line-through text-gray-400 truncate w-full text-center">
                              {t.personal_nombre?.split(' ')[0]}
                            </span>
                            <span className="text-[10px] text-gray-400">→</span>
                            <span className="text-[10px] font-medium text-gray-700 truncate w-full text-center">
                              {t.reemplazante_nombre.split(' ')[0]}
                            </span>
                            {t.monto_reemplazo != null && (
                              <span className="text-[10px] text-gray-500">{formatCLP(t.monto_reemplazo)}</span>
                            )}
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      {/* ══════════════════════════════════════════════ */}
      {/* ── Monthly Replacements Summary ──────────── */}
      {/* ══════════════════════════════════════════════ */}
      <div className="rounded-xl border border-gray-200 bg-white p-4">
        <h3 className="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">
          Reemplazos del mes
        </h3>
        {(() => {
          const replacements = turnos.filter((t) => !!t.reemplazado_por);
          if (replacements.length === 0) {
            return <p className="text-sm text-gray-400 text-center py-4">Sin reemplazos este mes</p>;
          }
          return (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-[10px] font-semibold text-gray-400 uppercase border-b border-gray-100">
                    <th className="pb-2 pr-3">Fecha</th>
                    <th className="pb-2 pr-3">Titular</th>
                    <th className="pb-2 pr-3">Reemplazante</th>
                    <th className="pb-2 pr-3 text-right">Monto</th>
                    <th className="pb-2 text-right">Acción</th>
                  </tr>
                </thead>
                <tbody>
                  {replacements.map((r) => (
                    <ReplaceRow
                      key={r.id}
                      turno={r}
                      workerPhotos={workerPhotos}
                      onViewProfile={() => setProfileModal(r)}
                      onReplacementChange={() => fetchShifts(true)}
                    />
                  ))}
                </tbody>
              </table>
            </div>
          );
        })()}
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

      {/* ── Profile Modal ── */}
      {profileModal && (
        <ProfileModal
          turno={profileModal}
          workerPhotos={workerPhotos}
          allTurnos={turnos}
          workerRolMap={workerRolMap}
          mes={mes}
          workers={workers}
          onClose={() => setProfileModal(null)}
          onReplacementChange={() => { setProfileModal(null); fetchShifts(true); }}
        />
      )}
    </div>
  );
}
