'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, formatMonthES, cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';
import type { Liquidacion, LiquidacionSeccion, ApiResponse } from '@/types';

function getMonthStr(offset: number) {
  const d = new Date();
  d.setMonth(d.getMonth() + offset);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function LiquidacionPage() {
  const [monthOffset, setMonthOffset] = useState(0);
  const [data, setData] = useState<Liquidacion | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const mes = getMonthStr(monthOffset);

  useEffect(() => {
    setLoading(true);
    setError('');
    apiFetch<ApiResponse<Liquidacion>>(`/worker/payroll?mes=${mes}`)
      .then(res => setData(res.data || null))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, [mes]);

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">Mi Liquidación</h1>

      <div className="flex items-center justify-between">
        <button onClick={() => setMonthOffset(o => o - 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronLeft className="h-5 w-5" /></button>
        <span className="font-semibold">{formatMonthES(mes)}</span>
        <button onClick={() => setMonthOffset(o => o + 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronRight className="h-5 w-5" /></button>
      </div>

      {data && data.secciones.map((sec: LiquidacionSeccion) => (
        <div key={sec.centro_costo} className="rounded-xl border bg-white p-5 shadow-sm">
          <h2 className="font-semibold capitalize text-amber-700">{sec.centro_costo === 'ruta11' ? 'La Ruta 11' : 'Seguridad'}</h2>

          <div className="mt-3 space-y-2 text-sm">
            <div className="flex justify-between"><span>Sueldo base</span><span className="font-medium">{formatCLP(sec.sueldo_base)}</span></div>
            <div className="flex justify-between"><span>Días trabajados</span><span className="font-medium">{sec.dias_trabajados}</span></div>

            {Object.keys(sec.reemplazos_realizados).length > 0 && (
              <div className="border-t pt-2">
                <p className="text-xs font-medium text-green-600">Reemplazos realizados (+)</p>
                {Object.entries(sec.reemplazos_realizados).map(([id, r]) => (
                  <div key={id} className="flex justify-between text-xs">
                    <span>{r.nombre} ({r.dias.length} días)</span>
                    <span className="text-green-600">+{formatCLP(r.monto)}</span>
                  </div>
                ))}
              </div>
            )}

            {Object.keys(sec.reemplazos_recibidos).length > 0 && (
              <div className="border-t pt-2">
                <p className="text-xs font-medium text-red-600">Reemplazos recibidos (−)</p>
                {Object.entries(sec.reemplazos_recibidos).map(([id, r]) => (
                  <div key={id} className="flex justify-between text-xs">
                    <span>{r.nombre} ({r.dias.length} días)</span>
                    <span className="text-red-600">−{formatCLP(r.monto)}</span>
                  </div>
                ))}
              </div>
            )}

            {sec.ajustes.length > 0 && (
              <div className="border-t pt-2">
                <p className="text-xs font-medium text-gray-600">Ajustes</p>
                {sec.ajustes.map(a => (
                  <div key={a.id} className="flex items-center justify-between text-xs">
                    <span className="flex items-center gap-1">
                      {a.concepto}
                      {a.categoria === 'descuento_credito_r11' && (
                        <span className="rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-medium text-purple-700">R11</span>
                      )}
                    </span>
                    <span className={a.monto < 0 ? 'text-red-600' : 'text-green-600'}>
                      {a.monto < 0 ? '−' : '+'}{formatCLP(Math.abs(a.monto))}
                    </span>
                  </div>
                ))}
              </div>
            )}

            <div className="flex justify-between border-t pt-2 font-bold">
              <span>Total {sec.centro_costo === 'ruta11' ? 'Ruta 11' : 'Seguridad'}</span>
              <span>{formatCLP(sec.total)}</span>
            </div>
          </div>
        </div>
      ))}

      {data && (
        <div className="rounded-xl border-2 border-amber-300 bg-amber-50 p-5 text-center">
          <p className="text-sm text-gray-600">Gran Total</p>
          <p className="text-2xl font-bold text-amber-700">{formatCLP(data.gran_total)}</p>
        </div>
      )}
    </div>
  );
}
