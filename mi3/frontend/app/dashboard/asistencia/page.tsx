'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatMonthES } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Loader2, ClipboardCheck } from 'lucide-react';
import type { ApiResponse } from '@/types';

interface AttendanceData {
  mes: string;
  resumen: Record<string, {
    dias_normales: number;
    reemplazos_realizados: number;
    dias_trabajados: number;
    dias_reemplazado: number;
  } | null>;
  dias_mes: number;
}

function getMonthStr(offset: number) {
  const d = new Date();
  d.setMonth(d.getMonth() + offset);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function AsistenciaPage() {
  const [monthOffset, setMonthOffset] = useState(0);
  const [data, setData] = useState<AttendanceData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const mes = getMonthStr(monthOffset);

  useEffect(() => {
    setLoading(true);
    setError('');
    apiFetch<ApiResponse<AttendanceData>>(`/worker/attendance?mes=${mes}`)
      .then(res => setData(res.data || null))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, [mes]);

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  const centros = data ? Object.entries(data.resumen).filter(([, v]) => v !== null) as [string, NonNullable<AttendanceData['resumen'][string]>][] : [];

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">Asistencia</h1>

      <div className="flex items-center justify-between">
        <button onClick={() => setMonthOffset(o => o - 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronLeft className="h-5 w-5" /></button>
        <span className="font-semibold">{formatMonthES(mes)}</span>
        <button onClick={() => setMonthOffset(o => o + 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronRight className="h-5 w-5" /></button>
      </div>

      {centros.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <ClipboardCheck className="mx-auto h-12 w-12 text-gray-300" />
          <p className="mt-3 text-sm text-gray-500">Sin datos de asistencia para este mes</p>
        </div>
      ) : (
        centros.map(([centro, info]) => (
          <div key={centro} className="rounded-xl border bg-white p-5 shadow-sm">
            <h2 className="font-semibold capitalize text-amber-700">{centro === 'ruta11' ? 'La Ruta 11' : 'Seguridad'}</h2>
            <div className="mt-3 grid grid-cols-2 gap-3">
              <div className="rounded-lg bg-gray-50 p-3 text-center">
                <p className="text-xs text-gray-500">Días normales</p>
                <p className="text-lg font-bold">{info.dias_normales}</p>
              </div>
              <div className="rounded-lg bg-blue-50 p-3 text-center">
                <p className="text-xs text-gray-500">Reemplazos realizados</p>
                <p className="text-lg font-bold text-blue-600">{info.reemplazos_realizados}</p>
              </div>
              <div className="rounded-lg bg-green-50 p-3 text-center">
                <p className="text-xs text-gray-500">Total trabajados</p>
                <p className="text-lg font-bold text-green-600">{info.dias_trabajados}</p>
              </div>
              <div className="rounded-lg bg-red-50 p-3 text-center">
                <p className="text-xs text-gray-500">Días reemplazado</p>
                <p className="text-lg font-bold text-red-600">{info.dias_reemplazado}</p>
              </div>
            </div>
          </div>
        ))
      )}
    </div>
  );
}
