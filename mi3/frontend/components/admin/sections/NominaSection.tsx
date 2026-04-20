'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, formatMonthES } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Loader2, Send, DollarSign, Mail } from 'lucide-react';

interface WorkerPayroll {
  personal_id: number;
  nombre: string;
  rol: string;
  sueldo_base: number;
  dias_trabajados: number;
  reemplazos: number;
  ajustes_total: number;
  gran_total: number;
}

interface PayrollSummary {
  centro_costo: string;
  presupuesto: number;
  total_sueldos: number;
  total_pagado: number;
  diferencia: number;
}

interface PayrollData {
  resumen: WorkerPayroll[];
  centros: PayrollSummary[];
}

function getMonthStr(offset: number) {
  const d = new Date();
  d.setMonth(d.getMonth() + offset);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function NominaSection() {
  const [monthOffset, setMonthOffset] = useState(0);
  const [data, setData] = useState<PayrollData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [sending, setSending] = useState<number | 'all' | null>(null);

  const mes = getMonthStr(monthOffset);

  useEffect(() => {
    setLoading(true);
    apiFetch<{ success: boolean; data: PayrollData }>(`/admin/payroll?mes=${mes}`)
      .then(res => setData(res.data || null))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, [mes]);

  const registerPayment = async (personalId: number) => {
    try {
      await apiFetch('/admin/payroll/payments', { method: 'POST', body: JSON.stringify({ personal_id: personalId, mes }) });
      alert('Pago registrado');
    } catch (err: any) { alert(err.message); }
  };

  const sendLiquidacion = async (personalId: number) => {
    setSending(personalId);
    try {
      await apiFetch('/admin/payroll/send-liquidacion', { method: 'POST', body: JSON.stringify({ personal_id: personalId, mes }) });
      alert('Liquidación enviada');
    } catch (err: any) { alert(err.message); }
    finally { setSending(null); }
  };

  const sendAll = async () => {
    setSending('all');
    try {
      await apiFetch('/admin/payroll/send-all', { method: 'POST', body: JSON.stringify({ mes }) });
      alert('Liquidaciones enviadas');
    } catch (err: any) { alert(err.message); }
    finally { setSending(null); }
  };

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="hidden md:block text-2xl font-bold text-gray-900">Nómina</h1>
        <button onClick={sendAll} disabled={sending === 'all'} className="flex items-center gap-1 rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50">
          <Send className="h-4 w-4" /> {sending === 'all' ? 'Enviando...' : 'Enviar Todas'}
        </button>
      </div>

      <div className="flex items-center justify-between">
        <button onClick={() => setMonthOffset(o => o - 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronLeft className="h-5 w-5" /></button>
        <span className="font-semibold">{formatMonthES(mes)}</span>
        <button onClick={() => setMonthOffset(o => o + 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronRight className="h-5 w-5" /></button>
      </div>

      {/* Summary by cost center */}
      {data?.centros && data.centros.length > 0 && (
        <div className="grid gap-3 sm:grid-cols-2">
          {data.centros.map(c => (
            <div key={c.centro_costo} className="rounded-xl border bg-white p-4 shadow-sm">
              <h3 className="font-semibold capitalize text-amber-700">{c.centro_costo === 'ruta11' ? 'La Ruta 11' : c.centro_costo}</h3>
              <div className="mt-2 space-y-1 text-sm">
                <div className="flex justify-between"><span className="text-gray-500">Presupuesto</span><span>{formatCLP(c.presupuesto)}</span></div>
                <div className="flex justify-between"><span className="text-gray-500">Total sueldos</span><span>{formatCLP(c.total_sueldos)}</span></div>
                <div className="flex justify-between"><span className="text-gray-500">Total pagado</span><span>{formatCLP(c.total_pagado)}</span></div>
                <div className="flex justify-between font-medium"><span>Diferencia</span><span className={c.diferencia >= 0 ? 'text-green-600' : 'text-red-600'}>{formatCLP(c.diferencia)}</span></div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Worker cards */}
      <div className="space-y-3">
        {data?.resumen?.map(w => (
          <div key={w.personal_id} className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="font-semibold">{w.nombre}</h3>
                <p className="text-xs text-gray-500 capitalize">{w.rol}</p>
              </div>
              <p className="text-lg font-bold text-amber-700">{formatCLP(w.gran_total)}</p>
            </div>
            <div className="mt-2 grid grid-cols-4 gap-2 text-center text-xs">
              <div><p className="text-gray-400">Base</p><p className="font-medium">{formatCLP(w.sueldo_base)}</p></div>
              <div><p className="text-gray-400">Días</p><p className="font-medium">{w.dias_trabajados}</p></div>
              <div><p className="text-gray-400">Reemp.</p><p className="font-medium">{w.reemplazos}</p></div>
              <div><p className="text-gray-400">Ajustes</p><p className="font-medium">{formatCLP(w.ajustes_total)}</p></div>
            </div>
            <div className="mt-3 flex gap-2">
              <button onClick={() => registerPayment(w.personal_id)} className="flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium hover:bg-gray-50">
                <DollarSign className="h-3 w-3" /> Pago
              </button>
              <button onClick={() => sendLiquidacion(w.personal_id)} disabled={sending === w.personal_id}
                className="flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium hover:bg-gray-50 disabled:opacity-50">
                <Mail className="h-3 w-3" /> {sending === w.personal_id ? 'Enviando...' : 'Email'}
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
