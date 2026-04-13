'use client';

import { useEffect, useState, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { cn } from '@/lib/utils';
import {
  Loader2,
  Camera,
  Upload,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Brain,
  FileText,
  Zap,
  ChevronDown,
  ChevronUp,
  Star,
  RefreshCw,
} from 'lucide-react';
import type { ApiResponse } from '@/types';

/* ─── Types ─── */

interface AITrainingPhoto {
  id: number;
  checklist_item_id: number | null;
  photo_url: string;
  contexto: string;
  ai_score: number | null;
  ai_observations: string | null;
  admin_feedback: 'correct' | 'incorrect' | null;
  admin_notes: string | null;
  admin_score: number | null;
  created_at: string;
}

interface AIPromptData {
  contexto: string;
  active: { id: number; prompt_base: string; prompt_version: number } | null;
  versions: Array<{ id: number; prompt_base: string; prompt_version: number; is_active: boolean }>;
  precision: number;
  needs_review: boolean;
  corrections_count: number;
  candidate: { id: number; prompt_base: string; prompt_version: number } | null;
}

interface AITaskSummary {
  activos: number;
  mejorados: number;
  escalados: number;
}

/* ─── Context Selector ─── */

const CONTEXTS = [
  'interior_apertura', 'exterior_apertura', 'interior_cierre', 'exterior_cierre',
  'plancha_apertura', 'plancha_cierre', 'lavaplatos_apertura', 'lavaplatos_cierre',
  'meson_apertura', 'meson_cierre', 'lavaplatos_meson_apertura', 'lavaplatos_meson_cierre',
];

function ContextSelector({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  return (
    <select
      value={value}
      onChange={e => onChange(e.target.value)}
      className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
    >
      <option value="">Todos los contextos</option>
      {CONTEXTS.map(c => (
        <option key={c} value={c}>{c.replace(/_/g, ' ')}</option>
      ))}
    </select>
  );
}

/* ─── Photo List Section ─── */

function PhotoListSection() {
  const [photos, setPhotos] = useState<AITrainingPhoto[]>([]);
  const [loading, setLoading] = useState(true);
  const [contexto, setContexto] = useState('');
  const [feedbackId, setFeedbackId] = useState<number | null>(null);
  const [feedbackNotes, setFeedbackNotes] = useState('');
  const [feedbackScore, setFeedbackScore] = useState(50);
  const [submittingFeedback, setSubmittingFeedback] = useState(false);

  const fetchPhotos = useCallback(() => {
    setLoading(true);
    const params = contexto ? `?contexto=${contexto}` : '';
    apiFetch<{ success: boolean; data: AITrainingPhoto[] }>(`/admin/checklists/ai-photos${params}`)
      .then(res => setPhotos(res.data || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [contexto]);

  useEffect(() => { fetchPhotos(); }, [fetchPhotos]);

  const handleFeedback = async (photoId: number, feedback: 'correct' | 'incorrect') => {
    if (feedback === 'correct') {
      try {
        await apiFetch('/admin/checklists/ai-feedback', {
          method: 'POST',
          body: JSON.stringify({ training_id: photoId, feedback: 'correct' }),
        });
        setPhotos(prev => prev.map(p => p.id === photoId ? { ...p, admin_feedback: 'correct' } : p));
      } catch {}
    } else {
      setFeedbackId(photoId);
    }
  };

  const submitIncorrectFeedback = async () => {
    if (!feedbackId) return;
    setSubmittingFeedback(true);
    try {
      await apiFetch('/admin/checklists/ai-feedback', {
        method: 'POST',
        body: JSON.stringify({
          training_id: feedbackId,
          feedback: 'incorrect',
          admin_notes: feedbackNotes,
          admin_score: feedbackScore,
        }),
      });
      setPhotos(prev => prev.map(p => p.id === feedbackId ? { ...p, admin_feedback: 'incorrect', admin_notes: feedbackNotes, admin_score: feedbackScore } : p));
      setFeedbackId(null);
      setFeedbackNotes('');
      setFeedbackScore(50);
    } catch {}
    setSubmittingFeedback(false);
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h3 className="font-semibold text-gray-900 flex items-center gap-2">
          <Camera className="h-4 w-4" /> Fotos evaluadas
        </h3>
        <ContextSelector value={contexto} onChange={setContexto} />
      </div>

      {loading ? (
        <div className="flex justify-center py-6"><Loader2 className="h-6 w-6 animate-spin text-amber-600" /></div>
      ) : photos.length === 0 ? (
        <p className="text-sm text-gray-500 text-center py-4">Sin fotos evaluadas</p>
      ) : (
        <div className="grid grid-cols-1 gap-3">
          {photos.map(photo => (
            <div key={photo.id} className="rounded-lg border bg-white p-3 shadow-sm">
              <div className="flex gap-3">
                <img src={photo.photo_url} alt="" className="h-20 w-20 rounded-lg object-cover flex-shrink-0" />
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="text-xs bg-gray-100 rounded px-1.5 py-0.5">{photo.contexto.replace(/_/g, ' ')}</span>
                    {photo.ai_score != null && (
                      <span className={cn(
                        'text-xs font-bold rounded-full px-2 py-0.5',
                        photo.ai_score >= 80 ? 'bg-green-100 text-green-700' :
                        photo.ai_score >= 50 ? 'bg-amber-100 text-amber-700' :
                        'bg-red-100 text-red-700'
                      )}>{photo.ai_score}/100</span>
                    )}
                  </div>
                  <p className="text-xs text-gray-600 line-clamp-2">{photo.ai_observations || 'Sin evaluar'}</p>
                  <p className="text-xs text-gray-400 mt-1">{new Date(photo.created_at).toLocaleDateString('es-CL')}</p>
                </div>
              </div>

              {/* Feedback buttons */}
              {photo.admin_feedback ? (
                <div className={cn(
                  'mt-2 rounded px-2 py-1 text-xs',
                  photo.admin_feedback === 'correct' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
                )}>
                  {photo.admin_feedback === 'correct' ? '✅ Marcado correcto' : `❌ Incorrecto: ${photo.admin_notes || ''}`}
                </div>
              ) : (
                <div className="mt-2 flex gap-2">
                  <button onClick={() => handleFeedback(photo.id, 'correct')} className="flex-1 flex items-center justify-center gap-1 rounded-lg border border-green-300 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50">
                    <CheckCircle2 className="h-3.5 w-3.5" /> Correcto
                  </button>
                  <button onClick={() => handleFeedback(photo.id, 'incorrect')} className="flex-1 flex items-center justify-center gap-1 rounded-lg border border-red-300 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">
                    <XCircle className="h-3.5 w-3.5" /> Incorrecto
                  </button>
                </div>
              )}

              {/* Incorrect feedback form */}
              {feedbackId === photo.id && (
                <div className="mt-2 space-y-2 rounded-lg bg-red-50 p-3">
                  <textarea
                    value={feedbackNotes}
                    onChange={e => setFeedbackNotes(e.target.value)}
                    placeholder="¿Qué debería haber dicho la IA?"
                    rows={2}
                    className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                  />
                  <div>
                    <label className="text-xs text-gray-600">Score correcto: {feedbackScore}</label>
                    <input type="range" min="0" max="100" value={feedbackScore} onChange={e => setFeedbackScore(Number(e.target.value))} className="w-full" />
                  </div>
                  <div className="flex gap-2">
                    <button onClick={() => { setFeedbackId(null); setFeedbackNotes(''); }} className="flex-1 rounded-lg border py-1.5 text-xs">Cancelar</button>
                    <button onClick={submitIncorrectFeedback} disabled={submittingFeedback} className="flex-1 rounded-lg bg-red-600 py-1.5 text-xs text-white font-medium disabled:opacity-50">
                      {submittingFeedback ? 'Enviando...' : 'Enviar'}
                    </button>
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/* ─── Test Prompt Section ─── */

function TestPromptSection() {
  const [contexto, setContexto] = useState(CONTEXTS[0]);
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [testing, setTesting] = useState(false);
  const [result, setResult] = useState<{ score: number; observations: string; prompt_used: string } | null>(null);
  const [error, setError] = useState('');

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0];
    if (!f) return;
    setFile(f);
    setResult(null);
    const reader = new FileReader();
    reader.onload = () => setPreview(reader.result as string);
    reader.readAsDataURL(f);
  };

  const handleTest = async () => {
    if (!file) return;
    setTesting(true);
    setError('');
    setResult(null);
    try {
      const formData = new FormData();
      formData.append('photo', file);
      formData.append('contexto', contexto);
      const res = await apiFetch<{ success: boolean; data: { score: number; observations: string; prompt_used: string } }>('/admin/checklists/ai-test', {
        method: 'POST',
        body: formData,
      });
      setResult(res.data);
    } catch (err: any) {
      setError(err.message || 'Error al probar');
    }
    setTesting(false);
  };

  return (
    <div className="space-y-3 rounded-xl border bg-white p-4 shadow-sm">
      <h3 className="font-semibold text-gray-900 flex items-center gap-2">
        <Zap className="h-4 w-4 text-amber-500" /> Probar prompt
      </h3>

      <div className="flex gap-3 items-end">
        <div className="flex-1">
          <label className="block text-xs text-gray-600 mb-1">Contexto</label>
          <select value={contexto} onChange={e => setContexto(e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm">
            {CONTEXTS.map(c => <option key={c} value={c}>{c.replace(/_/g, ' ')}</option>)}
          </select>
        </div>
      </div>

      {preview ? (
        <div className="relative">
          <img src={preview} alt="Preview" className="h-40 w-full rounded-lg object-cover" />
          <button onClick={() => { setFile(null); setPreview(null); setResult(null); }} className="absolute top-2 right-2 rounded-full bg-white/90 p-1 shadow">
            <XCircle className="h-4 w-4 text-gray-500" />
          </button>
        </div>
      ) : (
        <label className="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed border-gray-300 p-6 text-gray-400 hover:border-amber-400 hover:text-amber-500 transition-colors">
          <Upload className="h-6 w-6" />
          <span className="text-xs font-medium">Subir foto para probar</span>
          <input type="file" accept="image/*" className="hidden" onChange={handleFileChange} />
        </label>
      )}

      {file && (
        <button onClick={handleTest} disabled={testing} className="w-full flex items-center justify-center gap-2 rounded-lg bg-amber-600 py-2.5 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">
          {testing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Brain className="h-4 w-4" />}
          {testing ? 'Analizando...' : 'Probar con IA'}
        </button>
      )}

      {error && <p className="text-xs text-red-600">{error}</p>}

      {result && (
        <div className="space-y-2 rounded-lg bg-gray-50 p-3">
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium">Resultado</span>
            <span className={cn(
              'rounded-full px-2.5 py-0.5 text-xs font-bold',
              result.score >= 80 ? 'bg-green-200 text-green-800' :
              result.score >= 50 ? 'bg-amber-200 text-amber-800' :
              'bg-red-200 text-red-800'
            )}>{result.score}/100</span>
          </div>
          <p className="text-sm text-gray-700">{result.observations}</p>
        </div>
      )}
    </div>
  );
}

/* ─── Prompts Panel ─── */

function PromptsPanel() {
  const [prompts, setPrompts] = useState<AIPromptData[]>([]);
  const [loading, setLoading] = useState(true);
  const [expandedCtx, setExpandedCtx] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editText, setEditText] = useState('');
  const [saving, setSaving] = useState(false);

  const fetchPrompts = useCallback(() => {
    setLoading(true);
    apiFetch<{ success: boolean; data: AIPromptData[] }>('/admin/checklists/ai-prompts')
      .then(res => setPrompts(res.data || []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { fetchPrompts(); }, [fetchPrompts]);

  const handleSave = async (id: number) => {
    setSaving(true);
    try {
      await apiFetch(`/admin/checklists/ai-prompts/${id}`, {
        method: 'PUT',
        body: JSON.stringify({ prompt_base: editText }),
      });
      setEditingId(null);
      fetchPrompts();
    } catch {}
    setSaving(false);
  };

  const handleActivate = async (id: number) => {
    try {
      await apiFetch(`/admin/checklists/ai-prompts/${id}/activate`, { method: 'POST' });
      fetchPrompts();
    } catch {}
  };

  const handleGenerateCandidate = async (id: number) => {
    try {
      await apiFetch(`/admin/checklists/ai-prompts/${id}/generate-candidate`, { method: 'POST' });
      fetchPrompts();
    } catch {}
  };

  if (loading) return <div className="flex justify-center py-6"><Loader2 className="h-6 w-6 animate-spin text-amber-600" /></div>;

  return (
    <div className="space-y-3">
      <h3 className="font-semibold text-gray-900 flex items-center gap-2">
        <FileText className="h-4 w-4" /> Prompts por contexto
      </h3>

      {prompts.map(p => (
        <div key={p.contexto} className="rounded-lg border bg-white shadow-sm overflow-hidden">
          <button
            onClick={() => setExpandedCtx(expandedCtx === p.contexto ? null : p.contexto)}
            className="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50"
          >
            <div className="flex items-center gap-2">
              <span className="text-sm font-medium">{p.contexto.replace(/_/g, ' ')}</span>
              {p.needs_review && <span className="text-xs bg-red-100 text-red-700 rounded-full px-2 py-0.5">⚠️ Revisar</span>}
            </div>
            <div className="flex items-center gap-3 text-xs text-gray-500">
              <span>v{p.active?.prompt_version || '?'}</span>
              <span className={cn(p.precision < 70 ? 'text-red-600 font-bold' : '')}>{p.precision.toFixed(0)}% precisión</span>
              <span>{p.corrections_count} correcciones</span>
              {expandedCtx === p.contexto ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
            </div>
          </button>

          {expandedCtx === p.contexto && p.active && (
            <div className="border-t px-4 py-3 space-y-3">
              {editingId === p.active.id ? (
                <div className="space-y-2">
                  <textarea value={editText} onChange={e => setEditText(e.target.value)} rows={8} className="w-full rounded-lg border px-3 py-2 text-xs font-mono" />
                  <div className="flex gap-2">
                    <button onClick={() => setEditingId(null)} className="rounded-lg border px-3 py-1.5 text-xs">Cancelar</button>
                    <button onClick={() => handleSave(p.active!.id)} disabled={saving} className="rounded-lg bg-amber-600 px-3 py-1.5 text-xs text-white font-medium disabled:opacity-50">
                      {saving ? 'Guardando...' : 'Guardar (nueva versión)'}
                    </button>
                  </div>
                </div>
              ) : (
                <div>
                  <pre className="text-xs text-gray-600 whitespace-pre-wrap bg-gray-50 rounded-lg p-3 max-h-40 overflow-y-auto">{p.active.prompt_base}</pre>
                  <div className="flex gap-2 mt-2">
                    <button onClick={() => { setEditingId(p.active!.id); setEditText(p.active!.prompt_base); }} className="rounded-lg border px-3 py-1.5 text-xs font-medium hover:bg-gray-50">Editar</button>
                    <button onClick={() => handleGenerateCandidate(p.active!.id)} className="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50 flex items-center gap-1">
                      <Brain className="h-3 w-3" /> Generar candidato
                    </button>
                  </div>
                </div>
              )}

              {p.candidate && (
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-medium text-blue-700">Candidato v{p.candidate.prompt_version}</span>
                    <button onClick={() => handleActivate(p.candidate!.id)} className="rounded-lg bg-blue-600 px-3 py-1 text-xs text-white font-medium hover:bg-blue-700">
                      Activar
                    </button>
                  </div>
                  <pre className="text-xs text-blue-800 whitespace-pre-wrap max-h-32 overflow-y-auto">{p.candidate.prompt_base}</pre>
                </div>
              )}
            </div>
          )}
        </div>
      ))}
    </div>
  );
}

/* ─── AI Tasks Summary ─── */

function AITasksSummary() {
  const [summary, setSummary] = useState<AITaskSummary | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiFetch<{ success: boolean; summary: AITaskSummary }>('/admin/checklists/ai-tasks')
      .then(res => setSummary(res.summary))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) return null;
  if (!summary) return null;

  return (
    <div className="rounded-xl border bg-white p-4 shadow-sm">
      <h3 className="font-semibold text-gray-900 flex items-center gap-2 mb-3">
        <AlertTriangle className="h-4 w-4 text-amber-500" /> Tareas IA
      </h3>
      <div className="grid grid-cols-3 gap-3">
        <div className="rounded-lg bg-amber-50 p-3 text-center">
          <p className="text-2xl font-bold text-amber-700">{summary.activos}</p>
          <p className="text-xs text-amber-600">Activos</p>
        </div>
        <div className="rounded-lg bg-green-50 p-3 text-center">
          <p className="text-2xl font-bold text-green-700">{summary.mejorados}</p>
          <p className="text-xs text-green-600">Mejorados</p>
        </div>
        <div className="rounded-lg bg-red-50 p-3 text-center">
          <p className="text-2xl font-bold text-red-700">{summary.escalados}</p>
          <p className="text-xs text-red-600">Escalados</p>
        </div>
      </div>
    </div>
  );
}

/* ─── Main TabTestIA Component ─── */

export default function TabTestIA() {
  return (
    <div className="space-y-6">
      <AITasksSummary />
      <TestPromptSection />
      <PhotoListSection />
      <PromptsPanel />
    </div>
  );
}
