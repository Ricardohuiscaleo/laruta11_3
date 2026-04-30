'use client';

import { useEffect, useState, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, formatMonthES } from '@/lib/utils';
import {
  ChevronLeft, ChevronRight, ChevronDown, ChevronUp,
  Loader2, Send, Mail, Trash2, X,
  FileText, CreditCard,
} from 'lucide-react';
import type { SectionHeaderConfig } from '@/components/admin/AdminShell';

/* ─── Types ─── */

interface AjusteDetail {
  id: number;
  monto: number;
  concepto: string;
  categoria: string;
  categoria_slug: string;
  notas: string;
}

interface ReplacementGroup {
  personal_id: number;
  nombre: string;
  dias: number[];
  monto: number;
  pago_por: string;
}

interface WorkerPayroll {
  personal_id: number;
  nombre: string;
  rol: string;
  sueldo_base: number;
  dias_trabajados: number;
  reemplazos_hechos: number;
  total_reemplazando: number;
  total_reemplazado: number;
  reemplazos_realizados: ReplacementGroup[];
  reemplazos_recibidos: ReplacementGroup[];
  descuentos: AjusteDetail[];
  bonos: AjusteDetail[];
  total_descuentos: number;
  total_bonos: number;
  credito_r11_pendiente: number;
  total_a_pagar: number;
}

interface CentroData {
  workers: WorkerPayroll[];
  summary: {
    presupuesto: number;
    total_sueldos_base: number;
    total_descuentos: number;
    total_creditos: number;
    total_a_pagar: number;
    total_pagado: number;
    diferencia: number;
  };
  pagos: { id: number; nombre: string; monto: number; notas: string; es_externo: boolean }[];
}

interface PayrollData {
  ruta11: CentroData;
  seguridad: CentroData;
}

type NominaTab = 'ruta11' | 'seguridad';

/* ─── Helpers ─── */

function getMonthStr(offset: number) {
  const d = new Date();
  d.setMonth(d.getMonth() + offset);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

/* ─── Props ─── */

interface NominaSectionProps {
  onHeaderConfig?: (config: SectionHeaderConfig) => void;
}

/* ─── Component ─── */

export default function NominaSection({ onHeaderConfig }: NominaSectionProps) {
  const [monthOffset, setMonthOffset] = useState(0);
  const [data, setData] = useState<PayrollData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [sending, setSending] = useState<number | 'all' | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [activeTab, setActiveTab] = useState<NominaTab>('ruta11');
  const [showResumen, setShowResumen] = useState(false);

  const mes = getMonthStr(monthOffset);

  const handleTabChange = useCallback((key: string) => {
    setActiveTab(key as NominaTab);
    setExpandedId(null);
  }, []);

  /* ─── Register tabs via onHeaderConfig ─── */
  useEffect(() => {
    const centro = data?.[activeTab];
    const trailing = centro ? (
      <div className="flex items-center gap-3 text-sm">
        <span className="font-semibold text-amber-700">
          Total: {formatCLP(centro.summary.total_a_pagar)}
        </span>
        <button
          onClick={() => setShowResumen(true)}
          className="flex items-center gap-1 rounded-lg bg-amber-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-amber-700"
        >
          <FileText className="h-3.5 w-3.5" /> Resumen
        </button>
      </div>
    ) : undefined;

    onHeaderConfig?.({
      tabs: [
        { key: 'ruta11', label: 'La Ruta 11' },
        { key: 'seguridad', label: 'Cam Seguridad' },
      ],
      activeTab,
      onTabChange: handleTabChange,
      trailing,
      accent: 'amber',
    });
  }, [activeTab, handleTabChange, onHeaderConfig, data]);

  /* ─── Fetch data ─── */
  const fetchData = useCallback(() => {
    setLoading(true);
    apiFetch<{ success: boolean; data: PayrollData }>(`/admin/payroll?mes=${mes}`)
      .then(res => setData(res.data || null))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, [mes]);

  useEffect(() => { fetchData(); }, [fetchData]);

  /* ─── Actions ─── */
  const sendLiquidacion = async (personalId: number) => {
    setSending(personalId);
    try {
      await apiFetch('/admin/payroll/send-liquidacion', {
        method: 'POST',
        body: JSON.stringify({ personal_id: personalId, mes }),
      });
      alert('Liquidación enviada');
    } catch (err: any) { alert(err.message); }
    finally { setSending(null); }
  };

  const sendAll = async () => {
    setSending('all');
    try {
      await apiFetch('/admin/payroll/send-all', {
        method: 'POST',
        body: JSON.stringify({ mes }),
      });
      alert('Liquidaciones enviadas');
    } catch (err: any) { alert(err.message); }
    finally { setSending(null); }
  };

  const deleteAjuste = async (ajusteId: number) => {
    if (!confirm('¿Eliminar este ajuste?')) return;
    try {
      await apiFetch(`/admin/adjustments/${ajusteId}`, { method: 'DELETE' });
      fetchData();
    } catch (err: any) { alert(err.message); }
  };

  /* ─── Loading / Error ─── */
  if (loading) {
    return (
      <div className="flex justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-amber-600" />
      </div>
    );
  }
  if (error) {
    return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;
  }

  const centro = data?.[activeTab];
  if (!centro) {
    return (
      <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
        <p className="text-sm text-gray-500">Sin datos de nómina</p>
      </div>
    );
  }

  const { workers, summary } = centro;

  return (
    <div className="space-y-4 pt-4">
      {/* Month nav + Send all */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-1">
          <button
            onClick={() => setMonthOffset(o => o - 1)}
            className="rounded-lg p-2 hover:bg-gray-100"
            aria-label="Mes anterior"
          >
            <ChevronLeft className="h-5 w-5" />
          </button>
          <span className="font-semibold text-sm min-w-[100px] text-center">
            {formatMonthES(mes)}
          </span>
          <button
            onClick={() => setMonthOffset(o => o + 1)}
            className="rounded-lg p-2 hover:bg-gray-100"
            aria-label="Mes siguiente"
          >
            <ChevronRight className="h-5 w-5" />
          </button>
        </div>
        <button
          onClick={sendAll}
          disabled={sending === 'all'}
          className="flex items-center gap-1 rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
        >
          <Send className="h-4 w-4" />
          {sending === 'all' ? 'Enviando...' : 'Enviar Todas'}
        </button>
      </div>

      {/* Summary card */}
      <div className="rounded-xl border bg-white p-4 shadow-sm">
        <h3 className="font-semibold text-amber-700 mb-2">
          {activeTab === 'ruta11' ? 'La Ruta 11' : 'Cam Seguridad'}
        </h3>
        <div className="space-y-1 text-sm">
          <div className="flex justify-between">
            <span className="text-gray-500">Presupuesto</span>
            <span>{formatCLP(summary.presupuesto)}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-gray-500">Sueldos base</span>
            <span>{formatCLP(summary.total_sueldos_base)}</span>
          </div>
          {summary.total_descuentos !== 0 && (
            <div className="flex justify-between">
              <span className="text-gray-500">Descuentos</span>
              <span className="text-red-600">{formatCLP(summary.total_descuentos)}</span>
            </div>
          )}
          {summary.total_creditos > 0 && (
            <div className="flex justify-between">
              <span className="text-gray-500">Créditos R11</span>
              <span className="text-red-600">-{formatCLP(summary.total_creditos)}</span>
            </div>
          )}
          <div className="flex justify-between border-t pt-1 font-bold text-base">
            <span>Total a Pagar</span>
            <span className="text-amber-700">{formatCLP(summary.total_a_pagar)}</span>
          </div>
          <div className="flex justify-between text-xs text-gray-400">
            <span>Diferencia vs presupuesto</span>
            <span className={summary.diferencia >= 0 ? 'text-green-600' : 'text-red-600'}>
              {formatCLP(summary.diferencia)}
            </span>
          </div>
        </div>
      </div>

      {/* Worker cards */}
      <div className="space-y-3">
        {workers.map(w => {
          const isExpanded = expandedId === w.personal_id;
          const hasDetail = w.descuentos.length > 0 || w.bonos.length > 0
            || w.credito_r11_pendiente > 0
            || w.reemplazos_realizados.length > 0
            || w.reemplazos_recibidos.length > 0;

          return (
            <div key={w.personal_id} className="rounded-xl border bg-white shadow-sm">
              {/* Collapsed header */}
              <button
                type="button"
                className="w-full p-4 text-left"
                aria-expanded={isExpanded}
                onClick={() => setExpandedId(isExpanded ? null : w.personal_id)}
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    {hasDetail ? (
                      isExpanded
                        ? <ChevronUp className="h-4 w-4 text-gray-400 shrink-0" />
                        : <ChevronDown className="h-4 w-4 text-gray-400 shrink-0" />
                    ) : (
                      <div className="w-4" />
                    )}
                    <div>
                      <h3 className="font-semibold">{w.nombre}</h3>
                      <p className="text-xs text-gray-500 capitalize">{w.rol}</p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="text-lg font-bold text-amber-700">
                      {formatCLP(w.total_a_pagar)}
                    </p>
                    <p className="text-xs text-gray-400">a pagar</p>
                  </div>
                </div>

                {/* Quick stats row */}
                <div className="mt-2 flex flex-wrap gap-3 text-xs">
                  <div>
                    <span className="text-gray-400">Base: </span>
                    <span className="font-medium">{formatCLP(w.sueldo_base)}</span>
                  </div>
                  <div>
                    <span className="text-gray-400">Días: </span>
                    <span className="font-medium">{w.dias_trabajados}</span>
                  </div>
                  {w.total_reemplazando > 0 && (
                    <div>
                      <span className="text-gray-400">+Reemp: </span>
                      <span className="font-medium text-green-600">+{formatCLP(w.total_reemplazando)}</span>
                    </div>
                  )}
                  {w.total_reemplazado > 0 && (
                    <div>
                      <span className="text-gray-400">-Reemp: </span>
                      <span className="font-medium text-red-600">-{formatCLP(w.total_reemplazado)}</span>
                    </div>
                  )}
                  {w.total_descuentos !== 0 && (
                    <div>
                      <span className="text-gray-400">Desc: </span>
                      <span className="font-medium text-red-600">{formatCLP(w.total_descuentos)}</span>
                    </div>
                  )}
                  {w.credito_r11_pendiente > 0 && (
                    <div>
                      <span className="text-gray-400">💳 Crédito: </span>
                      <span className="font-medium text-red-600">-{formatCLP(w.credito_r11_pendiente)}</span>
                    </div>
                  )}
                </div>
              </button>

              {/* Expanded detail */}
              {isExpanded && (
                <div className="border-t px-4 pb-4 pt-3 space-y-3 text-sm">
                  {/* Sueldo base */}
                  <div className="flex justify-between">
                    <span className="text-gray-500">Sueldo base</span>
                    <span className="font-medium">{formatCLP(w.sueldo_base)}</span>
                  </div>

                  {/* Reemplazos realizados */}
                  {w.reemplazos_realizados.length > 0 && (
                    <div>
                      <p className="text-xs font-medium text-gray-500 mb-1">Reemplazos realizados (+)</p>
                      {w.reemplazos_realizados.map((r, i) => (
                        <div key={`real-${i}`} className="flex justify-between text-green-600 text-xs py-0.5">
                          <span>→ {r.nombre} · días {r.dias.join(', ')}</span>
                          <span>+{formatCLP(r.monto)}</span>
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Reemplazos recibidos */}
                  {w.reemplazos_recibidos.length > 0 && (
                    <div>
                      <p className="text-xs font-medium text-gray-500 mb-1">Reemplazos recibidos (-)</p>
                      {w.reemplazos_recibidos.map((r, i) => (
                        <div key={`rec-${i}`} className="flex justify-between text-red-600 text-xs py-0.5">
                          <span>← {r.nombre} · días {r.dias.join(', ')}</span>
                          <span>-{formatCLP(r.monto)}</span>
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Bonos (positive adjustments) */}
                  {w.bonos.length > 0 && (
                    <div>
                      <p className="text-xs font-medium text-gray-500 mb-1">Bonos / Extras (+)</p>
                      {w.bonos.map(a => (
                        <div key={a.id} className="flex items-center justify-between rounded-lg bg-green-50 px-2 py-1.5 text-xs">
                          <div>
                            <span className="font-medium">{a.concepto}</span>
                            {a.categoria && <span className="ml-1 text-gray-400">({a.categoria})</span>}
                            {a.notas && <span className="ml-1 text-gray-400">— {a.notas}</span>}
                          </div>
                          <div className="flex items-center gap-1.5">
                            <span className="font-semibold text-green-600">+{formatCLP(a.monto)}</span>
                            <button
                              onClick={(e) => { e.stopPropagation(); deleteAjuste(a.id); }}
                              className="rounded p-0.5 hover:bg-green-100"
                              aria-label={`Eliminar ajuste ${a.concepto}`}
                            >
                              <Trash2 className="h-3 w-3 text-gray-400" />
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Descuentos (negative adjustments) */}
                  {w.descuentos.length > 0 && (
                    <div>
                      <p className="text-xs font-medium text-gray-500 mb-1">Descuentos (-)</p>
                      {w.descuentos.map(a => (
                        <div key={a.id} className="flex items-center justify-between rounded-lg bg-red-50 px-2 py-1.5 text-xs">
                          <div>
                            <span className="font-medium">{a.concepto}</span>
                            {a.categoria && <span className="ml-1 text-gray-400">({a.categoria})</span>}
                            {a.notas && <span className="ml-1 text-gray-400">— {a.notas}</span>}
                          </div>
                          <div className="flex items-center gap-1.5">
                            <span className="font-semibold text-red-600">{formatCLP(a.monto)}</span>
                            <button
                              onClick={(e) => { e.stopPropagation(); deleteAjuste(a.id); }}
                              className="rounded p-0.5 hover:bg-red-100"
                              aria-label={`Eliminar ajuste ${a.concepto}`}
                            >
                              <Trash2 className="h-3 w-3 text-gray-400" />
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Crédito R11 */}
                  {w.credito_r11_pendiente > 0 && (
                    <div className="flex items-center justify-between rounded-lg bg-orange-50 px-2 py-1.5 text-xs">
                      <div className="flex items-center gap-1">
                        <CreditCard className="h-3.5 w-3.5 text-orange-500" />
                        <span className="font-medium">Crédito R11 pendiente</span>
                      </div>
                      <span className="font-semibold text-red-600">-{formatCLP(w.credito_r11_pendiente)}</span>
                    </div>
                  )}

                  {/* Total breakdown */}
                  <div className="border-t pt-2 space-y-1">
                    <div className="flex justify-between text-xs text-gray-500">
                      <span>Sueldo base</span>
                      <span>{formatCLP(w.sueldo_base)}</span>
                    </div>
                    {(w.total_reemplazando > 0 || w.total_reemplazado > 0) && (
                      <div className="flex justify-between text-xs text-gray-500">
                        <span>Reemplazos neto</span>
                        <span className={w.total_reemplazando - w.total_reemplazado >= 0 ? 'text-green-600' : 'text-red-600'}>
                          {formatCLP(w.total_reemplazando - w.total_reemplazado)}
                        </span>
                      </div>
                    )}
                    {w.total_bonos > 0 && (
                      <div className="flex justify-between text-xs text-gray-500">
                        <span>Bonos</span>
                        <span className="text-green-600">+{formatCLP(w.total_bonos)}</span>
                      </div>
                    )}
                    {w.total_descuentos !== 0 && (
                      <div className="flex justify-between text-xs text-gray-500">
                        <span>Descuentos</span>
                        <span className="text-red-600">{formatCLP(w.total_descuentos)}</span>
                      </div>
                    )}
                    {w.credito_r11_pendiente > 0 && (
                      <div className="flex justify-between text-xs text-gray-500">
                        <span>Crédito R11</span>
                        <span className="text-red-600">-{formatCLP(w.credito_r11_pendiente)}</span>
                      </div>
                    )}
                    <div className="flex justify-between font-bold text-base border-t pt-1">
                      <span>Total a Pagar</span>
                      <span className="text-amber-700">{formatCLP(w.total_a_pagar)}</span>
                    </div>
                  </div>
                </div>
              )}

              {/* Action buttons */}
              <div className="px-4 pb-3 flex gap-2">
                <button
                  onClick={() => sendLiquidacion(w.personal_id)}
                  disabled={sending === w.personal_id}
                  className="flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium hover:bg-gray-50 disabled:opacity-50"
                >
                  <Mail className="h-3 w-3" />
                  {sending === w.personal_id ? 'Enviando...' : 'Email'}
                </button>
              </div>
            </div>
          );
        })}
      </div>

      {workers.length === 0 && (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <p className="text-sm text-gray-500">
            Sin trabajadores en {activeTab === 'ruta11' ? 'La Ruta 11' : 'Cam Seguridad'} este mes
          </p>
        </div>
      )}

      {/* ─── Resumen de Pagos Modal ─── */}
      {showResumen && data && (
        <ResumenPagosModal
          data={data}
          mes={mes}
          onClose={() => setShowResumen(false)}
        />
      )}
    </div>
  );
}

/* ─── Resumen de Pagos Modal ─── */

function ResumenPagosModal({
  data,
  mes,
  onClose,
}: {
  data: PayrollData;
  mes: string;
  onClose: () => void;
}) {
  const r11 = data.ruta11;
  const seg = data.seguridad;

  const grandTotal = r11.summary.total_a_pagar + seg.summary.total_a_pagar;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label="Resumen de pagos"
    >
      <div
        className="w-full max-w-lg rounded-2xl bg-white shadow-xl max-h-[90vh] overflow-y-auto"
        onClick={e => e.stopPropagation()}
      >
        <div className="flex items-center justify-between border-b px-5 py-4">
          <h2 className="text-lg font-bold text-gray-900">
            Resumen de Pagos — {formatMonthES(mes)}
          </h2>
          <button onClick={onClose} className="rounded-lg p-1 hover:bg-gray-100" aria-label="Cerrar">
            <X className="h-5 w-5 text-gray-400" />
          </button>
        </div>

        <div className="p-5 space-y-5">
          {/* La Ruta 11 */}
          <div>
            <h3 className="font-semibold text-amber-700 mb-2">La Ruta 11</h3>
            <div className="space-y-1 text-sm">
              {r11.workers
                .filter(w => !w.rol?.includes('dueño'))
                .map(w => (
                  <div key={w.personal_id} className="flex justify-between">
                    <span className="text-gray-600">{w.nombre}</span>
                    <span className="font-medium tabular-nums">{formatCLP(w.total_a_pagar)}</span>
                  </div>
                ))}
              <div className="flex justify-between border-t pt-1 font-bold">
                <span>Subtotal Ruta 11</span>
                <span className="text-amber-700">{formatCLP(r11.summary.total_a_pagar)}</span>
              </div>
            </div>
          </div>

          {/* Cam Seguridad */}
          {seg.workers.length > 0 && (
            <div>
              <h3 className="font-semibold text-amber-700 mb-2">Cam Seguridad</h3>
              <div className="space-y-1 text-sm">
                {seg.workers
                  .filter(w => !w.rol?.includes('dueño'))
                  .map(w => (
                    <div key={w.personal_id} className="flex justify-between">
                      <span className="text-gray-600">{w.nombre}</span>
                      <span className="font-medium tabular-nums">{formatCLP(w.total_a_pagar)}</span>
                    </div>
                  ))}
                <div className="flex justify-between border-t pt-1 font-bold">
                  <span>Subtotal Seguridad</span>
                  <span className="text-amber-700">{formatCLP(seg.summary.total_a_pagar)}</span>
                </div>
              </div>
            </div>
          )}

          {/* Grand total */}
          <div className="rounded-xl bg-amber-50 p-4">
            <div className="flex justify-between text-lg font-bold">
              <span>TOTAL NÓMINA</span>
              <span className="text-amber-700">{formatCLP(grandTotal)}</span>
            </div>
            <div className="mt-1 flex justify-between text-xs text-gray-500">
              <span>Ruta 11: {formatCLP(r11.summary.total_a_pagar)}</span>
              <span>Seguridad: {formatCLP(seg.summary.total_a_pagar)}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
