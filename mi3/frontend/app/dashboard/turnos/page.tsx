'use client';

import { useEffect, useState, useMemo } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, formatMonthES, cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';
import type { Turno, ApiResponse } from '@/types';

function getMonthStr(offset: number) {
  const d = new Date();
  d.setMonth(d.getMonth() + offset);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function TurnosPage() {
  const [monthOffset, setMonthOffset] = useState(0);
  const [turnos, setTurnos] = useState<Turno[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selected, setSelected] = useState<Turno | null>(null);

  const mes = getMonthStr(monthOffset);

  useEffect(() => {
    setLoading(true);
    setError('');
    apiFetch<ApiResponse<{ turnos: Turno[] }>>(`/worker/shifts?mes=${mes}`)
      .then(res => setTurnos(res.data?.turnos || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, [mes]);

  const turnoMap = useMemo(() => {
    const map: Record<string, Turno[]> = {};
    turnos.forEach(t => {
      if (!map[t.fecha]) map[t.fecha] = [];
      map[t.fecha].push(t);
    });
    return map;
  }, [turnos]);

  const calendarDays = useMemo(() => {
    const [y, m] = mes.split('-').map(Number);
    const firstDay = new Date(y, m - 1, 1);
    const lastDay = new Date(y, m, 0);
    const startPad = (firstDay.getDay() + 6) % 7; // Mon=0
    const days: (number | null)[] = Array(startPad).fill(null);
    for (let d = 1; d <= lastDay.getDate(); d++) days.push(d);
    while (days.length % 7 !== 0) days.push(null);
    return days;
  }, [mes]);

  const dayColor = (day: number) => {
    const dateStr = `${mes}-${String(day).padStart(2, '0')}`;
    const dayTurnos = turnoMap[dateStr];
    if (!dayTurnos || dayTurnos.length === 0) return '';
    const tipos = dayTurnos.map(t => t.tipo);
    if (tipos.some(t => t === 'reemplazo' && dayTurnos.find(x => x.tipo === 'reemplazo')?.reemplazado_por))
      return 'bg-blue-100 text-blue-800';
    if (tipos.includes('reemplazo')) return 'bg-red-100 text-red-800';
    return 'bg-green-100 text-green-800';
  };

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">Mis Turnos</h1>

      <div className="flex items-center justify-between">
        <button onClick={() => setMonthOffset(o => o - 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronLeft className="h-5 w-5" /></button>
        <span className="font-semibold">{formatMonthES(mes)}</span>
        <button onClick={() => setMonthOffset(o => o + 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronRight className="h-5 w-5" /></button>
      </div>

      {loading ? (
        <div className="flex justify-center py-10"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>
      ) : error ? (
        <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>
      ) : (
        <>
          <div className="grid grid-cols-7 gap-1 text-center text-xs font-medium text-gray-500">
            {['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].map(d => <div key={d}>{d}</div>)}
          </div>
          <div className="grid grid-cols-7 gap-1">
            {calendarDays.map((day, i) => {
              if (day === null) return <div key={i} />;
              const dateStr = `${mes}-${String(day).padStart(2, '0')}`;
              const hasTurno = !!turnoMap[dateStr];
              return (
                <button
                  key={i}
                  onClick={() => hasTurno ? setSelected(turnoMap[dateStr][0]) : setSelected(null)}
                  className={cn(
                    'flex h-10 items-center justify-center rounded-lg text-sm transition-colors',
                    dayColor(day),
                    !hasTurno && 'text-gray-400',
                    hasTurno && 'cursor-pointer font-medium'
                  )}
                >
                  {day}
                </button>
              );
            })}
          </div>

          <div className="flex flex-wrap gap-3 text-xs">
            <span className="flex items-center gap-1"><span className="h-3 w-3 rounded bg-green-200" /> Normal</span>
            <span className="flex items-center gap-1"><span className="h-3 w-3 rounded bg-blue-200" /> Reemplazo realizado</span>
            <span className="flex items-center gap-1"><span className="h-3 w-3 rounded bg-red-200" /> Reemplazo recibido</span>
          </div>

          {selected && (
            <div className="rounded-xl border bg-white p-4 shadow-sm">
              <h3 className="font-semibold">Detalle — {selected.fecha}</h3>
              <p className="mt-1 text-sm capitalize">Tipo: {selected.tipo}</p>
              {selected.reemplazante_nombre && <p className="text-sm">Reemplazante: {selected.reemplazante_nombre}</p>}
              {selected.monto_reemplazo != null && <p className="text-sm">Monto: {formatCLP(selected.monto_reemplazo)}</p>}
            </div>
          )}
        </>
      )}
    </div>
  );
}
