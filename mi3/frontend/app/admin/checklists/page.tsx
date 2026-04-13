'use client';

import { useEffect, useState, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { cn, formatCLP, formatMonthES } from '@/lib/utils';
import {
  Loader2,
  ClipboardCheck,
  Calendar,
  ChevronLeft,
  ChevronRight,
  X,
  Eye,
  Camera,
  Clock,
  Lightbulb,
  Users,
  AlertCircle,
  Brain,
} from 'lucide-react';
import TabTestIA from '@/components/admin/TabTestIA';
import type {
  Checklist,
  ChecklistItem,
  ChecklistDetail,
  AttendanceSummary,
  ImprovementIdea,
  ApiResponse,
} from '@/types';

/* ─── Helpers ─── */

function todayStr() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function getMonthStr(offset: number) {
  const d = new Date();
  d.setMonth(d.getMonth() + offset);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function formatDateShort(dateStr: string) {
  const d = new Date(dateStr + 'T12:00:00');
  return d.toLocaleDateString('es-CL', { day: 'numeric', month: 'short' });
}

function formatTime(ts: string | null) {
  if (!ts) return '—';
  const d = new Date(ts);
  return d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
}

/* ─── Status Badge ─── */

const STATUS_STYLES: Record<string, string> = {
  pending: 'bg-gray-100 text-gray-700',
  active: 'bg-amber-100 text-amber-700',
  completed: 'bg-green-100 text-green-700',
  missed: 'bg-red-100 text-red-700',
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'Pendiente',
  active: 'Activo',
  completed: 'Completado',
  missed: 'Perdido',
};

function StatusBadge({ status }: { status: string }) {
  return (
    <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', STATUS_STYLES[status] || 'bg-gray-100 text-gray-700')}>
      {STATUS_LABELS[status] || status}
    </span>
  );
}

/* ─── AI Score Badge ─── */

function AIScoreBadge({ score }: { score: number | null }) {
  if (score === null) return <span className="text-xs text-gray-400 italic">Pendiente</span>;
  const color = score <= 50 ? 'bg-red-100 text-red-700' : score <= 75 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700';
  return (
    <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold', color)}>
      {score}/100
    </span>
  );
}

/* ─── Progress Bar (mini) ─── */

function MiniProgress({ completed, total }: { completed: number; total: number }) {
  const pct = total > 0 ? Math.round((completed / total) * 100) : 0;
  return (
    <div className="flex items-center gap-2">
      <div className="h-1.5 w-16 rounded-full bg-gray-200">
        <div className="h-1.5 rounded-full bg-amber-500 transition-all" style={{ width: `${pct}%` }} />
      </div>
      <span className="text-xs text-gray-500">{pct}%</span>
    </div>
  );
}

/* ─── Tab Button ─── */

function TabButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      onClick={onClick}
      className={cn(
        'px-4 py-2 text-sm font-medium rounded-lg transition-colors whitespace-nowrap',
        active ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
      )}
    >
      {children}
    </button>
  );
}

/* ─── Checklist Detail Modal ─── */

function ChecklistDetailModal({ checklistId, onClose }: { checklistId: number; onClose: () => void }) {
  const [detail, setDetail] = useState<ChecklistDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    apiFetch<ApiResponse<ChecklistDetail>>(`/admin/checklists/${checklistId}`)
      .then(res => setDetail(res.data || null))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, [checklistId]);

  const checklist = detail?.checklist;
  const items = detail?.items || checklist?.items || [];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="w-full max-w-lg max-h-[85vh] overflow-y-auto rounded-xl bg-white shadow-xl" onClick={e => e.stopPropagation()}>
        {/* Header */}
        <div className="sticky top-0 z-10 flex items-center justify-between border-b bg-white px-5 py-4 rounded-t-xl">
          <div>
            <h2 className="text-lg font-bold text-gray-900">Detalle Checklist</h2>
            {checklist && (
              <p className="text-xs text-gray-500">
                {checklist.type === 'apertura' ? '🌅 Apertura' : '🌙 Cierre'} · {checklist.rol} · {formatDateShort(checklist.scheduled_date)}
                {checklist.user_name && ` · ${checklist.user_name}`}
              </p>
            )}
          </div>
          <button onClick={onClose} className="rounded-lg p-1 hover:bg-gray-100">
            <X className="h-5 w-5 text-gray-400" />
          </button>
        </div>

        {/* Body */}
        <div className="p-5">
          {loading && (
            <div className="flex justify-center py-10">
              <Loader2 className="h-6 w-6 animate-spin text-amber-600" />
            </div>
          )}

          {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600">{error}</div>}

          {!loading && !error && checklist && (
            <div className="space-y-3">
              {/* Summary */}
              <div className="flex items-center gap-3 text-sm">
                <StatusBadge status={checklist.status} />
                <MiniProgress completed={checklist.completed_items} total={checklist.total_items} />
                {checklist.completed_at && (
                  <span className="text-xs text-gray-400 flex items-center gap-1">
                    <Clock className="h-3 w-3" /> {formatTime(checklist.completed_at)}
                  </span>
                )}
              </div>

              {/* Items */}
              <div className="space-y-2">
                {items.map(item => (
                  <ChecklistItemDetail key={item.id} item={item} />
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

/* ─── Checklist Item Detail (inside modal) ─── */

function ChecklistItemDetail({ item }: { item: ChecklistItem }) {
  const isPendingAI = item.requires_photo && item.photo_url && !item.ai_analyzed_at;

  return (
    <div className={cn(
      'rounded-lg border p-3',
      item.is_completed ? 'border-green-200 bg-green-50/50' : 'border-gray-200 bg-white'
    )}>
      <div className="flex items-start justify-between gap-2">
        <div className="flex-1 min-w-0">
          <p className={cn('text-sm font-medium', item.is_completed ? 'text-green-700' : 'text-gray-900')}>
            {item.description}
          </p>
          {item.completed_at && (
            <p className="mt-0.5 text-xs text-gray-400 flex items-center gap-1">
              <Clock className="h-3 w-3" /> Completado: {formatTime(item.completed_at)}
            </p>
          )}
        </div>
        {item.is_completed ? (
          <span className="text-green-500 text-xs">✓</span>
        ) : (
          <span className="text-gray-300 text-xs">○</span>
        )}
      </div>

      {/* Photo */}
      {item.photo_url && (
        <div className="mt-2">
          <img
            src={item.photo_url}
            alt="Foto checklist"
            className="h-40 w-full rounded-lg object-cover"
          />
        </div>
      )}

      {/* AI Analysis */}
      {item.requires_photo && item.photo_url && (
        <div className="mt-2 rounded-lg bg-gray-50 p-2">
          {isPendingAI ? (
            <div className="flex items-center gap-2 text-xs text-amber-600">
              <AlertCircle className="h-3.5 w-3.5" />
              <span className="font-medium">Análisis IA pendiente</span>
            </div>
          ) : item.ai_score !== null ? (
            <div className="space-y-1">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-gray-600">Puntaje IA</span>
                <AIScoreBadge score={item.ai_score} />
              </div>
              {item.ai_observations && (
                <p className="text-xs text-gray-500 leading-relaxed">{item.ai_observations}</p>
              )}
            </div>
          ) : null}
        </div>
      )}
    </div>
  );
}

/* ─── Tab: Checklists del Día ─── */

function TabChecklists() {
  const [date, setDate] = useState(todayStr());
  const [checklists, setChecklists] = useState<Checklist[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const fetchChecklists = useCallback(() => {
    setLoading(true);
    setError('');
    apiFetch<ApiResponse<Checklist[]>>(`/admin/checklists?fecha=${date}`)
      .then(res => setChecklists(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, [date]);

  useEffect(() => { fetchChecklists(); }, [fetchChecklists]);

  const changeDate = (delta: number) => {
    const d = new Date(date + 'T12:00:00');
    d.setDate(d.getDate() + delta);
    setDate(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`);
  };

  if (loading) return <div className="flex justify-center py-10"><Loader2 className="h-6 w-6 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600">{error}</div>;

  return (
    <div className="space-y-3">
      {/* Date filter */}
      <div className="flex items-center justify-between">
        <button onClick={() => changeDate(-1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronLeft className="h-5 w-5" /></button>
        <div className="flex items-center gap-2">
          <Calendar className="h-4 w-4 text-gray-400" />
          <input
            type="date"
            value={date}
            onChange={e => setDate(e.target.value)}
            className="rounded-lg border px-3 py-1.5 text-sm"
          />
        </div>
        <button onClick={() => changeDate(1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronRight className="h-5 w-5" /></button>
      </div>

      {checklists.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <ClipboardCheck className="mx-auto h-10 w-10 text-gray-300" />
          <p className="mt-2 text-sm text-gray-500">Sin checklists para esta fecha</p>
        </div>
      ) : (
        <div className="space-y-2">
          {checklists.map(cl => (
            <div
              key={cl.id}
              onClick={() => setSelectedId(cl.id)}
              className="flex items-center justify-between rounded-xl border bg-white p-3 shadow-sm cursor-pointer hover:border-amber-300 transition-colors"
            >
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-semibold text-gray-900">
                    {cl.type === 'apertura' ? '🌅' : '🌙'} {cl.type.charAt(0).toUpperCase() + cl.type.slice(1)}
                  </span>
                  <StatusBadge status={cl.status} />
                </div>
                <p className="mt-0.5 text-xs text-gray-500">
                  {cl.user_name || '—'} · <span className="capitalize">{cl.rol || '—'}</span>
                  {cl.checklist_mode === 'virtual' && (
                    <span className="ml-1 text-blue-600">(Virtual)</span>
                  )}
                </p>
              </div>
              <div className="flex items-center gap-3">
                <MiniProgress completed={cl.completed_items} total={cl.total_items} />
                <Eye className="h-4 w-4 text-gray-400" />
              </div>
            </div>
          ))}
        </div>
      )}

      {selectedId && (
        <ChecklistDetailModal checklistId={selectedId} onClose={() => setSelectedId(null)} />
      )}
    </div>
  );
}

/* ─── Tab: Asistencia ─── */

function TabAsistencia() {
  const [monthOffset, setMonthOffset] = useState(0);
  const [data, setData] = useState<AttendanceSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const mes = getMonthStr(monthOffset);

  useEffect(() => {
    setLoading(true);
    setError('');
    apiFetch<ApiResponse<AttendanceSummary[]>>(`/admin/checklists/attendance?mes=${mes}`)
      .then(res => setData(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, [mes]);

  if (loading) return <div className="flex justify-center py-10"><Loader2 className="h-6 w-6 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600">{error}</div>;

  return (
    <div className="space-y-3">
      {/* Month selector */}
      <div className="flex items-center justify-between">
        <button onClick={() => setMonthOffset(o => o - 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronLeft className="h-5 w-5" /></button>
        <span className="font-semibold">{formatMonthES(mes)}</span>
        <button onClick={() => setMonthOffset(o => o + 1)} className="rounded-lg p-2 hover:bg-gray-100"><ChevronRight className="h-5 w-5" /></button>
      </div>

      {data.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <Users className="mx-auto h-10 w-10 text-gray-300" />
          <p className="mt-2 text-sm text-gray-500">Sin datos de asistencia para este mes</p>
        </div>
      ) : (
        <div className="space-y-2">
          {data.map(row => (
            <div key={row.personal_id} className="rounded-xl border bg-white p-4 shadow-sm">
              <h3 className="font-semibold text-gray-900">{row.nombre}</h3>
              <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
                <div className="rounded-lg bg-green-50 p-2 text-center">
                  <p className="text-lg font-bold text-green-700">{row.dias_trabajados}</p>
                  <p className="text-xs text-green-600">Días trabajados</p>
                </div>
                <div className="rounded-lg bg-red-50 p-2 text-center">
                  <p className="text-lg font-bold text-red-700">{row.inasistencias}</p>
                  <p className="text-xs text-red-600">Inasistencias</p>
                </div>
                <div className="rounded-lg bg-blue-50 p-2 text-center">
                  <p className="text-lg font-bold text-blue-700">{row.virtuales}</p>
                  <p className="text-xs text-blue-600">Virtuales</p>
                </div>
                <div className="rounded-lg bg-gray-50 p-2 text-center">
                  <p className={cn('text-lg font-bold', row.monto_descuentos > 0 ? 'text-red-600' : 'text-gray-700')}>
                    {row.monto_descuentos > 0 ? `-${formatCLP(row.monto_descuentos)}` : formatCLP(0)}
                  </p>
                  <p className="text-xs text-gray-500">Descuentos</p>
                </div>
              </div>
              <p className="mt-2 text-xs text-gray-400 text-right">
                Total turnos: {row.total_turnos}
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/* ─── Tab: Ideas de Mejora ─── */

function TabIdeas() {
  const [ideas, setIdeas] = useState<ImprovementIdea[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    setLoading(true);
    setError('');
    apiFetch<ApiResponse<ImprovementIdea[]>>('/admin/checklists/ideas')
      .then(res => setIdeas(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex justify-center py-10"><Loader2 className="h-6 w-6 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600">{error}</div>;

  return (
    <div className="space-y-2">
      {ideas.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <Lightbulb className="mx-auto h-10 w-10 text-gray-300" />
          <p className="mt-2 text-sm text-gray-500">Sin ideas de mejora aún</p>
        </div>
      ) : (
        ideas.map(idea => (
          <div key={idea.id} className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="flex items-start justify-between gap-2">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <Lightbulb className="h-4 w-4 text-amber-500 flex-shrink-0" />
                  <span className="text-sm font-semibold text-gray-900">{idea.nombre}</span>
                </div>
                <p className="mt-1 text-sm text-gray-700 leading-relaxed">{idea.improvement_idea}</p>
              </div>
              <span className="text-xs text-gray-400 whitespace-nowrap">{formatDateShort(idea.completed_at)}</span>
            </div>
          </div>
        ))
      )}
    </div>
  );
}

/* ─── Main Page ─── */

type Tab = 'checklists' | 'asistencia' | 'ideas' | 'test-ia';

export default function AdminChecklistsPage() {
  const [tab, setTab] = useState<Tab>('checklists');

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">Checklists</h1>

      {/* Tabs */}
      <div className="flex gap-2 overflow-x-auto pb-1">
        <TabButton active={tab === 'checklists'} onClick={() => setTab('checklists')}>
          <span className="flex items-center gap-1.5"><ClipboardCheck className="h-4 w-4" /> Del día</span>
        </TabButton>
        <TabButton active={tab === 'asistencia'} onClick={() => setTab('asistencia')}>
          <span className="flex items-center gap-1.5"><Users className="h-4 w-4" /> Asistencia</span>
        </TabButton>
        <TabButton active={tab === 'ideas'} onClick={() => setTab('ideas')}>
          <span className="flex items-center gap-1.5"><Lightbulb className="h-4 w-4" /> Ideas</span>
        </TabButton>
        <TabButton active={tab === 'test-ia'} onClick={() => setTab('test-ia')}>
          <span className="flex items-center gap-1.5"><Brain className="h-4 w-4" /> Test IA</span>
        </TabButton>
      </div>

      {/* Tab Content */}
      {tab === 'checklists' && <TabChecklists />}
      {tab === 'asistencia' && <TabAsistencia />}
      {tab === 'ideas' && <TabIdeas />}
      {tab === 'test-ia' && <TabTestIA />}
    </div>
  );
}
