'use client';

import { useState, useEffect, useCallback } from 'react';
import { comprasApi } from '@/lib/compras-api';
import { Terminal, RefreshCw, ChevronDown, ChevronUp, AlertTriangle, Check, X, Clock, Eye, Search, Brain, Bot, Image as ImageIcon } from 'lucide-react';

interface ExtractionLog {
  id: number;
  compra_id: number | null;
  image_url: string;
  raw_response: Record<string, unknown>;
  extracted_data: Record<string, unknown>;
  confidence_scores: Record<string, number>;
  overall_confidence: number;
  processing_time_ms: number;
  model_id: string;
  status: 'success' | 'failed' | 'partial';
  error_message: string | null;
  created_at: string;
}

interface LogsResponse {
  success: boolean;
  data: ExtractionLog[];
  total: number;
  current_page: number;
  total_pages: number;
}

export default function ConsolaPage() {
  const [logs, setLogs] = useState<ExtractionLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<string>('all');
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [detailTab, setDetailTab] = useState<'phases' | 'extracted' | 'raw' | 'confidence'>('phases');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);

  const fetchLogs = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ per_page: '20', page: String(page) });
      if (filter !== 'all') params.set('status', filter);
      const res = await comprasApi.get<LogsResponse>(`/compras/extraction-logs?${params}`);
      if (res.success) {
        setLogs(res.data);
        setTotalPages(res.total_pages);
        setTotal(res.total);
      }
    } catch {
      // silent
    }
    setLoading(false);
  }, [page, filter]);

  useEffect(() => { fetchLogs(); }, [fetchLogs]);

  const stats = {
    total: logs.length,
    success: logs.filter(l => l.status === 'success').length,
    failed: logs.filter(l => l.status === 'failed').length,
    avgTime: logs.length > 0 ? Math.round(logs.reduce((s, l) => s + l.processing_time_ms, 0) / logs.length) : 0,
    avgConfidence: logs.filter(l => l.status === 'success').length > 0
      ? Math.round(logs.filter(l => l.status === 'success').reduce((s, l) => s + l.overall_confidence, 0) / logs.filter(l => l.status === 'success').length * 100)
      : 0,
  };

  return (
    <div className="space-y-4 p-3 md:p-4">
      {/* Stats bar */}
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 md:grid-cols-5">
        <StatCard label="Total" value={total} />
        <StatCard label="Exitosas" value={stats.success} color="green" />
        <StatCard label="Fallidas" value={stats.failed} color="red" />
        <StatCard label="Tiempo prom." value={`${(stats.avgTime / 1000).toFixed(1)}s`} />
        <StatCard label="Confianza prom." value={`${stats.avgConfidence}%`} color={stats.avgConfidence >= 70 ? 'green' : 'amber'} />
      </div>

      {/* Toolbar */}
      <div className="flex flex-wrap items-center gap-2">
        <select
          value={filter}
          onChange={e => { setFilter(e.target.value); setPage(1); }}
          className="rounded-lg border px-3 py-1.5 text-sm"
          aria-label="Filtrar por estado"
        >
          <option value="all">Todos</option>
          <option value="success">Exitosas</option>
          <option value="failed">Fallidas</option>
          <option value="partial">Parciales</option>
        </select>
        <button
          onClick={fetchLogs}
          disabled={loading}
          className="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50"
          aria-label="Refrescar logs"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} /> Refrescar
        </button>
        <span className="ml-auto text-xs text-gray-400">{total} registros</span>
      </div>

      {/* Logs list */}
      <div className="space-y-2" role="list" aria-label="Logs de extracción">
        {logs.map(log => (
          <LogRow
            key={log.id}
            log={log}
            expanded={expandedId === log.id}
            detailTab={detailTab}
            onToggle={() => setExpandedId(expandedId === log.id ? null : log.id)}
            onTabChange={setDetailTab}
          />
        ))}
        {logs.length === 0 && !loading && (
          <p className="py-8 text-center text-sm text-gray-400">No hay logs de extracción</p>
        )}
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-center gap-2">
          <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page <= 1}
            className="rounded border px-3 py-1 text-sm disabled:opacity-30">Anterior</button>
          <span className="text-sm text-gray-500">{page} / {totalPages}</span>
          <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page >= totalPages}
            className="rounded border px-3 py-1 text-sm disabled:opacity-30">Siguiente</button>
        </div>
      )}
    </div>
  );
}

function StatCard({ label, value, color }: { label: string; value: string | number; color?: string }) {
  const colorClass = color === 'green' ? 'text-green-700' : color === 'red' ? 'text-red-600' : color === 'amber' ? 'text-amber-600' : 'text-gray-900';
  return (
    <div className="rounded-lg border bg-white px-3 py-2">
      <p className="text-xs text-gray-500">{label}</p>
      <p className={`text-lg font-bold ${colorClass}`}>{value}</p>
    </div>
  );
}

function LogRow({ log, expanded, detailTab, onToggle, onTabChange }: {
  log: ExtractionLog;
  expanded: boolean;
  detailTab: string;
  onToggle: () => void;
  onTabChange: (tab: 'phases' | 'extracted' | 'raw' | 'confidence') => void;
}) {
  const statusIcon = log.status === 'success'
    ? <Check className="h-4 w-4 text-green-600" />
    : log.status === 'failed'
    ? <X className="h-4 w-4 text-red-500" />
    : <AlertTriangle className="h-4 w-4 text-amber-500" />;

  const tipoImagen = (log.extracted_data?.tipo_imagen as string) || '—';
  const proveedor = (log.extracted_data?.proveedor as string) || '—';
  const itemsCount = Array.isArray(log.extracted_data?.items) ? (log.extracted_data.items as unknown[]).length : 0;
  const isPipeline = (log.model_id || '').includes('pipeline');
  const phases = (log.raw_response?.pipeline_phases as Record<string, unknown>) || null;
  const date = new Date(log.created_at);
  const timeStr = date.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
  const dateStr = date.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit' });

  return (
    <div className={`rounded-xl border bg-white transition-shadow ${expanded ? 'shadow-md' : ''}`} role="listitem">
      {/* Summary row */}
      <button
        onClick={onToggle}
        className="flex w-full items-center gap-2 px-3 py-2.5 text-left md:gap-3"
        aria-expanded={expanded}
        aria-label={`Log #${log.id}`}
      >
        {statusIcon}
        <span className="text-xs text-gray-400 w-8 flex-shrink-0">#{log.id}</span>
        <span className="text-xs text-gray-400 flex-shrink-0">{dateStr} {timeStr}</span>
        <span className="flex-1 truncate text-sm font-medium text-gray-700">
          {proveedor !== '—' ? proveedor : tipoImagen}
        </span>
        {isPipeline && (
          <span className="hidden sm:inline-block rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-700">pipeline</span>
        )}
        <span className="text-xs text-gray-500">{itemsCount} items</span>
        <span className={`text-xs font-medium ${log.overall_confidence >= 0.7 ? 'text-green-600' : log.overall_confidence > 0 ? 'text-amber-600' : 'text-gray-400'}`}>
          {log.overall_confidence > 0 ? `${Math.round(log.overall_confidence * 100)}%` : '—'}
        </span>
        <span className="flex items-center gap-1 text-xs text-gray-400">
          <Clock className="h-3 w-3" /> {(log.processing_time_ms / 1000).toFixed(1)}s
        </span>
        {expanded ? <ChevronUp className="h-4 w-4 text-gray-400" /> : <ChevronDown className="h-4 w-4 text-gray-400" />}
      </button>

      {/* Expanded detail */}
      {expanded && (
        <div className="border-t px-3 pb-3 pt-2 space-y-3">
          {/* Error message */}
          {log.error_message && (
            <div className="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
              {log.error_message}
            </div>
          )}

          {/* Image preview */}
          {log.image_url && (
            <div className="flex items-center gap-2">
              <ImageIcon className="h-4 w-4 text-gray-400" />
              <span className="text-xs text-gray-500 truncate max-w-[300px]">{log.image_url}</span>
            </div>
          )}

          {/* Detail tabs */}
          <div className="flex gap-1 overflow-x-auto">
            {(['phases', 'extracted', 'confidence', 'raw'] as const).map(tab => (
              <button
                key={tab}
                onClick={() => onTabChange(tab)}
                className={`whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-medium transition-colors ${
                  detailTab === tab ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                }`}
              >
                {tab === 'phases' ? '🔄 Fases' : tab === 'extracted' ? '📋 Datos' : tab === 'confidence' ? '📊 Confianza' : '🔧 Raw'}
              </button>
            ))}
          </div>

          {/* Tab content */}
          <div className="rounded-lg bg-gray-50 p-3">
            {detailTab === 'phases' && <PhasesTab phases={phases} modelId={log.model_id} />}
            {detailTab === 'extracted' && <ExtractedTab data={log.extracted_data} />}
            {detailTab === 'confidence' && <ConfidenceTab scores={log.confidence_scores} overall={log.overall_confidence} />}
            {detailTab === 'raw' && <RawTab data={log.raw_response} />}
          </div>
        </div>
      )}
    </div>
  );
}

function PhasesTab({ phases, modelId }: { phases: Record<string, unknown> | null; modelId: string }) {
  if (!phases) {
    return <p className="text-xs text-gray-500">Modelo: {modelId} (sin datos de fases — extracción legacy)</p>;
  }

  const phaseConfig = [
    { key: 'percepcion', label: 'Percepción', icon: <Search className="h-3.5 w-3.5" />, desc: 'Rekognition DetectLabels + DetectText' },
    { key: 'clasificacion', label: 'Clasificación', icon: <Brain className="h-3.5 w-3.5" />, desc: 'Nova Micro — tipo de imagen + contexto BD' },
    { key: 'analisis', label: 'Análisis', icon: <Bot className="h-3.5 w-3.5" />, desc: 'Nova Pro — prompt específico por tipo' },
  ];

  return (
    <div className="space-y-2">
      <p className="text-xs text-gray-500 mb-2">Modelo: {modelId}</p>
      {phaseConfig.map(({ key, label, icon, desc }) => {
        const phase = phases[key] as Record<string, unknown> | undefined;
        if (!phase) return null;
        const elapsed = phase.elapsed_ms as number || 0;
        return (
          <div key={key} className="flex items-start gap-2 rounded-lg bg-white px-3 py-2 border">
            <span className="mt-0.5 text-gray-400">{icon}</span>
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2">
                <span className="text-sm font-medium text-gray-700">{label}</span>
                <span className="text-xs text-gray-400">{(elapsed / 1000).toFixed(2)}s</span>
              </div>
              <p className="text-xs text-gray-500">{desc}</p>
              {key === 'percepcion' && (
                <p className="text-xs text-gray-600 mt-1">
                  {Number(phase.labels_count) || 0} labels · {Number(phase.texts_count) || 0} textos
                </p>
              )}
              {key === 'clasificacion' && (
                <p className="text-xs text-gray-600 mt-1">
                  Tipo: <span className="font-medium">{String(phase.tipo || '—')}</span> · Confianza: {Math.round(Number(phase.confianza || 0) * 100)}%
                </p>
              )}
              {key === 'analisis' && (
                <p className="text-xs text-gray-600 mt-1">
                  {phase.success ? '✅ Exitoso' : '❌ Falló'}
                </p>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}

function ExtractedTab({ data }: { data: Record<string, unknown> }) {
  if (!data || Object.keys(data).length === 0) {
    return <p className="text-xs text-gray-500">Sin datos extraídos</p>;
  }

  const items = Array.isArray(data.items) ? data.items as Record<string, unknown>[] : [];

  return (
    <div className="space-y-2 text-xs">
      <div className="grid grid-cols-2 gap-2">
        <Field label="Tipo" value={data.tipo_imagen as string} />
        <Field label="Proveedor" value={data.proveedor as string} />
        <Field label="RUT" value={data.rut_proveedor as string} />
        <Field label="Fecha" value={data.fecha as string} />
        <Field label="Método pago" value={data.metodo_pago as string} />
        <Field label="Tipo compra" value={data.tipo_compra as string} />
        <Field label="Monto neto" value={data.monto_neto != null ? `$${Number(data.monto_neto).toLocaleString('es-CL')}` : null} />
        <Field label="IVA" value={data.iva != null ? `$${Number(data.iva).toLocaleString('es-CL')}` : null} />
        <Field label="Monto total" value={data.monto_total != null ? `$${Number(data.monto_total).toLocaleString('es-CL')}` : null} />
        {data.peso_bascula != null && <Field label="Peso báscula" value={`${String(data.peso_bascula)} ${data.unidad_bascula ? String(data.unidad_bascula) : 'kg'}`} />}
      </div>
      {data.notas_ia && (
        <div className="rounded bg-blue-50 px-2 py-1 text-xs text-blue-700">💡 {String(data.notas_ia)}</div>
      )}
      {items.length > 0 && (
        <div>
          <p className="font-medium text-gray-700 mb-1">Items ({items.length}):</p>
          <div className="space-y-1">
            {items.map((item, i) => (
              <div key={i} className="flex items-center gap-2 rounded bg-white border px-2 py-1">
                <span className="flex-1 truncate font-medium">{String(item.nombre || '?')}</span>
                <span className="text-gray-500">{String(item.cantidad)} {String(item.unidad)}</span>
                <span className="text-gray-500">×${Number(item.precio_unitario || 0).toLocaleString('es-CL')}</span>
                <span className="font-medium text-green-700">${Number(item.subtotal || 0).toLocaleString('es-CL')}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function ConfidenceTab({ scores, overall }: { scores: Record<string, number>; overall: number }) {
  const fields = [
    { key: 'tipo_imagen', label: 'Tipo imagen' },
    { key: 'proveedor', label: 'Proveedor' },
    { key: 'rut', label: 'RUT' },
    { key: 'items', label: 'Items' },
    { key: 'monto_neto', label: 'Monto neto' },
    { key: 'iva', label: 'IVA' },
    { key: 'monto_total', label: 'Monto total' },
    { key: 'peso_bascula', label: 'Peso báscula' },
  ];

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2 mb-2">
        <span className="text-sm font-medium text-gray-700">Confianza global:</span>
        <span className={`text-lg font-bold ${overall >= 0.7 ? 'text-green-600' : overall > 0.4 ? 'text-amber-600' : 'text-red-600'}`}>
          {Math.round(overall * 100)}%
        </span>
      </div>
      <div className="space-y-1">
        {fields.map(({ key, label }) => {
          const score = scores[key] ?? 0;
          if (score === 0) return null;
          const pct = Math.round(score * 100);
          const color = pct >= 70 ? 'bg-green-500' : pct >= 40 ? 'bg-amber-500' : 'bg-red-500';
          return (
            <div key={key} className="flex items-center gap-2">
              <span className="w-24 text-xs text-gray-600">{label}</span>
              <div className="flex-1 h-2 rounded-full bg-gray-200 overflow-hidden">
                <div className={`h-full rounded-full ${color}`} style={{ width: `${pct}%` }} />
              </div>
              <span className="w-10 text-right text-xs font-medium text-gray-700">{pct}%</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function RawTab({ data }: { data: Record<string, unknown> }) {
  return (
    <pre className="max-h-96 overflow-auto rounded-lg bg-gray-900 p-3 text-xs text-green-400 font-mono whitespace-pre-wrap break-all">
      {JSON.stringify(data, null, 2)}
    </pre>
  );
}

function Field({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <span className="text-gray-500">{label}: </span>
      <span className="font-medium text-gray-800">{value || '—'}</span>
    </div>
  );
}
