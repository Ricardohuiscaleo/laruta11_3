'use client';

import { useState, useEffect } from 'react';
import { RefreshCw, Share2, Loader2, AlertTriangle, CheckCircle, Clock, Users, DollarSign, TrendingDown } from 'lucide-react';

const API = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';
const fmt = (n: number) => '$' + Math.round(n).toLocaleString('es-CL');

interface Deudor {
  nombre: string;
  rut: string;
  grado_militar: string;
  unidad_trabajo: string;
  limite_credito: number;
  credito_usado: number;
  disponible: number;
  es_moroso: boolean;
  dias_mora: number;
  pagado_este_mes: number;
}

interface Summary {
  total_usuarios: number;
  total_credito_otorgado: number;
  total_deuda_actual: number;
  total_morosos: number;
  total_deuda_morosos: number;
  pagos_del_mes_count: number;
  pagos_del_mes_monto: number;
  tasa_cobro: number;
}

interface ApiResponse {
  success: boolean;
  generated_at: string;
  periodo: { inicio: string; fin: string };
  summary: Summary;
  deudores: Deudor[];
}

export default function RL6ResumenPage({ params }: { params: { token: string } }) {
  const [data, setData] = useState<ApiResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);

  const fetchData = async (showLoader = true) => {
    if (showLoader) setLoading(true);
    else setRefreshing(true);
    setError('');
    try {
      const res = await fetch(`${API}/api/v1/rl6/resumen/${params.token}`, {
        headers: { Accept: 'application/json' },
      });
      const d = await res.json();
      if (d.success) setData(d);
      else setError('Resumen no disponible');
    } catch {
      setError('Error de conexión');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => { fetchData(); }, [params.token]);

  const handleShare = async () => {
    if (!data) return;
    const s = data.summary;
    const lines = [
      '📊 *RESUMEN CRÉDITO RL6 — La Ruta 11*',
      `📅 ${new Date(data.generated_at).toLocaleDateString('es-CL')}`,
      '━━━━━━━━━━━━━━━━━━━━',
      `💰 Crédito otorgado: *${fmt(s.total_credito_otorgado)}*`,
      `📉 Deuda actual: *${fmt(s.total_deuda_actual)}*`,
      `✅ Pagado este mes: *${fmt(s.pagos_del_mes_monto)}* (${s.pagos_del_mes_count} usuarios)`,
      `⚠️ Morosos: *${s.total_morosos}* — ${fmt(s.total_deuda_morosos)}`,
      `📊 Tasa de cobro: *${s.tasa_cobro}%*`,
      '━━━━━━━━━━━━━━━━━━━━',
      `👥 Total usuarios: ${s.total_usuarios}`,
      '',
      `🔗 ${window.location.href}`,
    ].join('\n');

    try {
      if (navigator.share) {
        await navigator.share({ text: lines });
      } else {
        await navigator.clipboard.writeText(lines);
        setCopied(true);
        setTimeout(() => setCopied(false), 2500);
      }
    } catch {
      await navigator.clipboard.writeText(lines);
      setCopied(true);
      setTimeout(() => setCopied(false), 2500);
    }
  };

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex min-h-screen items-center justify-center text-red-500 p-4 text-center">
        <div>
          <AlertTriangle className="h-8 w-8 mx-auto mb-2" />
          <p>{error}</p>
        </div>
      </div>
    );
  }

  if (!data) return null;

  const s = data.summary;
  const periodoInicio = new Date(data.periodo.inicio).toLocaleDateString('es-CL', { day: 'numeric', month: 'long' });
  const periodoFin = new Date(data.periodo.fin).toLocaleDateString('es-CL', { day: 'numeric', month: 'long', year: 'numeric' });

  return (
    <div style={{ maxWidth: '100%', padding: '0 4px', paddingBottom: '24px' }} className="space-y-2">

      {/* Header */}
      <div className="text-center py-3 relative">
        <div className="flex items-center justify-center gap-2">
          <h1 className="text-xl font-bold text-gray-900">📊 Crédito RL6</h1>
          <button
            onClick={() => fetchData(false)}
            disabled={refreshing}
            className="inline-flex items-center justify-center h-8 w-8 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
            title="Actualizar"
          >
            <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
          </button>
          <button
            onClick={handleShare}
            className="inline-flex items-center justify-center h-8 w-8 rounded-full text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors"
            title="Compartir"
          >
            <Share2 className="h-4 w-4" />
          </button>
        </div>
        <p className="text-xs text-gray-400 mt-1">
          La Ruta 11 — {periodoInicio} al {periodoFin}
        </p>
        <p className="text-[10px] text-gray-300">
          Actualizado: {new Date(data.generated_at).toLocaleString('es-CL')}
        </p>
        {copied && (
          <div className="absolute left-1/2 -translate-x-1/2 mt-1 px-3 py-1 rounded-full bg-gray-800 text-white text-[11px] whitespace-nowrap shadow-lg animate-fade-in">
            ✅ Mensaje copiado
          </div>
        )}
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-2 gap-2">
        <div className="rounded-xl border bg-white shadow-sm p-3">
          <div className="flex items-center gap-2 mb-1">
            <DollarSign className="h-4 w-4 text-blue-500" />
            <p className="text-[10px] text-gray-500 uppercase tracking-wider font-medium">Otorgado</p>
          </div>
          <p className="text-lg font-bold text-blue-700">{fmt(s.total_credito_otorgado)}</p>
          <p className="text-[10px] text-gray-400">{s.total_usuarios} usuarios</p>
        </div>

        <div className="rounded-xl border bg-white shadow-sm p-3">
          <div className="flex items-center gap-2 mb-1">
            <TrendingDown className="h-4 w-4 text-orange-500" />
            <p className="text-[10px] text-gray-500 uppercase tracking-wider font-medium">Deuda actual</p>
          </div>
          <p className="text-lg font-bold text-orange-700">{fmt(s.total_deuda_actual)}</p>
          <p className="text-[10px] text-gray-400">Pendiente</p>
        </div>

        <div className="rounded-xl border bg-white shadow-sm p-3">
          <div className="flex items-center gap-2 mb-1">
            <CheckCircle className="h-4 w-4 text-green-500" />
            <p className="text-[10px] text-gray-500 uppercase tracking-wider font-medium">Pagado este mes</p>
          </div>
          <p className="text-lg font-bold text-green-700">{fmt(s.pagos_del_mes_monto)}</p>
          <p className="text-[10px] text-gray-400">{s.pagos_del_mes_count} usuarios</p>
        </div>

        <div className="rounded-xl border bg-white shadow-sm p-3">
          <div className="flex items-center gap-2 mb-1">
            <AlertTriangle className="h-4 w-4 text-red-500" />
            <p className="text-[10px] text-gray-500 uppercase tracking-wider font-medium">Morosos</p>
          </div>
          <p className="text-lg font-bold text-red-700">{s.total_morosos} / {s.total_usuarios}</p>
          <p className="text-[10px] text-gray-400">{fmt(s.total_deuda_morosos)} en mora</p>
        </div>
      </div>

      {/* Tasa de cobro */}
      <div className="rounded-xl border bg-white shadow-sm p-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Clock className="h-4 w-4 text-purple-500" />
            <span className="text-sm font-medium text-gray-700">Tasa de cobro</span>
          </div>
          <span className={`text-lg font-bold ${s.tasa_cobro >= 50 ? 'text-green-600' : 'text-orange-600'}`}>
            {s.tasa_cobro}%
          </span>
        </div>
        <div className="mt-2 h-2 rounded-full bg-gray-100 overflow-hidden">
          <div
            className={`h-full rounded-full transition-all ${s.tasa_cobro >= 50 ? 'bg-green-500' : 'bg-orange-400'}`}
            style={{ width: `${Math.min(s.tasa_cobro, 100)}%` }}
          />
        </div>
      </div>

      {/* Debtors table */}
      <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
        <div className="px-3 py-2 border-b bg-gray-50 flex items-center justify-between">
          <h3 className="text-sm font-semibold text-gray-700">
            Deudores ({data.deudores.length})
          </h3>
          <span className="text-xs text-gray-400">Total: {fmt(s.total_deuda_actual)}</span>
        </div>

        {data.deudores.length === 0 ? (
          <div className="p-6 text-center text-gray-400">
            <CheckCircle className="h-8 w-8 mx-auto mb-2 text-green-400" />
            <p className="text-sm">¡Todos al día! Sin deudas pendientes.</p>
          </div>
        ) : (
          <div className="divide-y">
            {data.deudores.map((d, i) => (
              <div key={i} className={`px-3 py-2.5 ${d.es_moroso ? 'bg-red-50' : d.pagado_este_mes > 0 ? 'bg-green-50' : ''}`}>
                <div className="flex items-start justify-between">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-1.5">
                      <span className="text-sm font-semibold text-gray-900 truncate">{d.nombre}</span>
                      {d.es_moroso && <span className="inline-flex items-center rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-700">MORA</span>}
                      {d.pagado_este_mes > 0 && <span className="inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700">PAGÓ</span>}
                    </div>
                    <p className="text-xs text-gray-500 mt-0.5">
                      {d.grado_militar} · {d.unidad_trabajo}
                    </p>
                  </div>
                  <div className="text-right flex-shrink-0 ml-2">
                    <p className="text-sm font-bold text-orange-700">{fmt(d.credito_usado)}</p>
                    <p className="text-[10px] text-gray-400">de {fmt(d.limite_credito)}</p>
                  </div>
                </div>

                <div className="mt-1.5 flex items-center gap-3 text-[10px] text-gray-400">
                  {d.dias_mora > 0 && <span>⏱ {d.dias_mora} días mora</span>}
                  {d.pagado_este_mes > 0 && <span className="text-green-600">✅ Pagó {fmt(d.pagado_este_mes)}</span>}
                  <span>📦 Disp. {fmt(d.disponible)}</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Refresh button */}
      <div className="text-center pt-2">
        <button
          onClick={() => fetchData(false)}
          disabled={refreshing}
          className="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white px-5 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors disabled:opacity-50"
        >
          <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
          {refreshing ? 'Actualizando...' : 'Actualizar resumen'}
        </button>
      </div>
    </div>
  );
}
