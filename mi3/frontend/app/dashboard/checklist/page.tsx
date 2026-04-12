'use client';

import { useEffect, useState, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { cn } from '@/lib/utils';
import {
  Loader2,
  ClipboardCheck,
  Camera,
  CheckCircle2,
  AlertCircle,
  RefreshCw,
  X,
  Lightbulb,
  Send,
} from 'lucide-react';
import type { Checklist, ChecklistItem, ChecklistVirtual, ApiResponse } from '@/types';

/* ─── Progress Bar ─── */
function ProgressBar({ completed, total }: { completed: number; total: number }) {
  const pct = total > 0 ? Math.round((completed / total) * 100) : 0;
  const remaining = total - completed;
  return (
    <div className="mt-3">
      <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
        <span>{remaining} ítem{remaining !== 1 ? 's' : ''} restante{remaining !== 1 ? 's' : ''}</span>
        <span className="font-semibold text-amber-700">{pct}%</span>
      </div>
      <div className="h-2.5 w-full rounded-full bg-gray-200">
        <div
          className="h-2.5 rounded-full bg-amber-500 transition-all duration-300"
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  );
}

/* ─── Photo Upload Component ─── */
function PhotoUpload({
  item,
  checklistId,
  checklistType,
  onPhotoUploaded,
}: {
  item: ChecklistItem;
  checklistId: number;
  checklistType: 'apertura' | 'cierre';
  onPhotoUploaded: (itemId: number, photoUrl: string, aiScore?: number | null, aiObs?: string | null) => void;
}) {
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState('');
  const [preview, setPreview] = useState<string | null>(item.photo_url);

  const compressImage = (file: File, maxWidth = 1200, quality = 0.8): Promise<Blob> => {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement('canvas');
        let { width, height } = img;
        if (width > maxWidth) {
          height = Math.round((height * maxWidth) / width);
          width = maxWidth;
        }
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        if (!ctx) return reject(new Error('Canvas not supported'));
        ctx.drawImage(img, 0, 0, width, height);
        canvas.toBlob(
          (blob) => (blob ? resolve(blob) : reject(new Error('Compression failed'))),
          'image/jpeg',
          quality,
        );
      };
      img.onerror = () => reject(new Error('Error loading image'));
      img.src = URL.createObjectURL(file);
    });
  };

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Preview
    const reader = new FileReader();
    reader.onload = () => setPreview(reader.result as string);
    reader.readAsDataURL(file);

    // Compress + Upload
    setUploading(true);
    setError('');
    try {
      const compressed = await compressImage(file);
      const formData = new FormData();
      formData.append('photo', compressed, 'checklist.jpg');
      // Derive photo context from item description
      const photoType = item.description.toLowerCase().includes('exterior') ? 'exterior' : 'interior';
      formData.append('contexto', `${photoType}_${checklistType}`);

      const res = await apiFetch<ApiResponse<{ url: string; ai_score: number | null; ai_observations: string | null; ai_status: string }>>(
        `/worker/checklists/${checklistId}/items/${item.id}/photo`,
        {
          method: 'POST',
          body: formData,
        }
      );
      onPhotoUploaded(item.id, res.data?.url || '', res.data?.ai_score ?? null, res.data?.ai_observations ?? null);
    } catch (err: any) {
      setError(err.message || 'Error al subir foto');
      setPreview(item.photo_url);
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className="mt-2">
      {preview ? (
        <div className="relative">
          <img
            src={preview}
            alt="Foto del ítem"
            className="h-32 w-full rounded-lg object-cover"
          />
          {!item.is_completed && (
            <label className="absolute bottom-2 right-2 cursor-pointer rounded-lg bg-white/90 px-2 py-1 text-xs font-medium text-gray-700 shadow hover:bg-white">
              Cambiar
              <input
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={handleFileChange}
              />
            </label>
          )}
        </div>
      ) : (
        <label className="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed border-gray-300 p-4 text-gray-400 hover:border-amber-400 hover:text-amber-500 transition-colors">
          {uploading ? (
            <Loader2 className="h-6 w-6 animate-spin" />
          ) : (
            <Camera className="h-6 w-6" />
          )}
          <span className="text-xs font-medium">
            {uploading ? 'Subiendo...' : 'Tomar o subir foto'}
          </span>
          <input
            type="file"
            accept="image/*"
            capture="environment"
            className="hidden"
            onChange={handleFileChange}
            disabled={uploading}
          />
        </label>
      )}
      {error && (
        <div className="mt-2 flex items-center gap-2 rounded-lg bg-red-50 p-2 text-xs text-red-600">
          <AlertCircle className="h-3.5 w-3.5 flex-shrink-0" />
          <span className="flex-1">{error}</span>
          <button
            onClick={() => setError('')}
            className="flex items-center gap-1 text-red-700 font-medium hover:underline"
          >
            <RefreshCw className="h-3 w-3" /> Reintentar
          </button>
        </div>
      )}
    </div>
  );
}

/* ─── Checklist Item Row ─── */
function ChecklistItemRow({
  item,
  checklistId,
  checklistType,
  onItemCompleted,
  onPhotoUploaded,
}: {
  item: ChecklistItem;
  checklistId: number;
  checklistType: 'apertura' | 'cierre';
  onItemCompleted: (itemId: number) => void;
  onPhotoUploaded: (itemId: number, photoUrl: string, aiScore?: number | null, aiObs?: string | null) => void;
}) {
  const [completing, setCompleting] = useState(false);
  const needsPhoto = item.requires_photo && !item.photo_url;
  const canToggle = !needsPhoto || item.is_completed; // Can unmark even if photo needed

  const handleToggle = async () => {
    if (completing) return;
    if (!item.is_completed && needsPhoto) return; // Can't mark without photo
    setCompleting(true);
    try {
      await apiFetch(`/worker/checklists/${checklistId}/items/${item.id}/complete`, {
        method: 'POST',
      });
      onItemCompleted(item.id);
    } catch {
      // Silently fail — user can retry
    } finally {
      setCompleting(false);
    }
  };

  return (
    <div className={cn(
      'rounded-lg border p-3 transition-colors',
      item.is_completed ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-white'
    )}>
      <div className="flex items-start gap-3">
        <button
          onClick={handleToggle}
          disabled={completing || (!item.is_completed && needsPhoto)}
          title={needsPhoto && !item.is_completed ? 'Sube una foto primero' : item.is_completed ? 'Desmarcar' : 'Marcar'}
          className={cn(
            'mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border-2 transition-colors',
            item.is_completed
              ? 'border-green-500 bg-green-500 text-white hover:bg-green-400'
              : !needsPhoto
                ? 'border-amber-400 hover:border-amber-500 hover:bg-amber-50'
                : 'border-gray-300 cursor-not-allowed opacity-50'
          )}
        >
          {completing ? (
            <Loader2 className="h-3.5 w-3.5 animate-spin" />
          ) : item.is_completed ? (
            <CheckCircle2 className="h-4 w-4" />
          ) : null}
        </button>
        <div className="flex-1 min-w-0">
          <p className={cn(
            'text-sm font-medium',
            item.is_completed ? 'text-green-700 line-through' : 'text-gray-900'
          )}>
            {item.description}
          </p>
          {item.requires_photo && needsPhoto && !item.is_completed && (
            <p className="mt-1 text-xs text-amber-600 flex items-center gap-1">
              <Camera className="h-3 w-3" /> Foto requerida para completar
            </p>
          )}
        </div>
      </div>
      {item.requires_photo && (
        <PhotoUpload
          item={item}
          checklistId={checklistId}
          checklistType={checklistType}
          onPhotoUploaded={onPhotoUploaded}
        />
      )}
      {/* AI Analysis Feedback */}
      {(item as any).ai_score != null && (
        <div className={cn(
          'mt-2 rounded-lg p-2.5 text-xs',
          (item as any).ai_score >= 80 ? 'bg-green-50 text-green-700' :
          (item as any).ai_score >= 50 ? 'bg-amber-50 text-amber-700' :
          'bg-red-50 text-red-700'
        )}>
          <div className="flex items-center justify-between mb-1">
            <span className="font-semibold">Análisis IA</span>
            <span className={cn(
              'rounded-full px-2 py-0.5 text-xs font-bold',
              (item as any).ai_score >= 80 ? 'bg-green-200 text-green-800' :
              (item as any).ai_score >= 50 ? 'bg-amber-200 text-amber-800' :
              'bg-red-200 text-red-800'
            )}>
              {(item as any).ai_score}/100
            </span>
          </div>
          <p>{(item as any).ai_observations}</p>
        </div>
      )}
    </div>
  );
}

/* ─── Checklist Card ─── */
function ChecklistCard({
  checklist,
  onUpdate,
}: {
  checklist: Checklist;
  onUpdate: () => void;
}) {
  const typeLabel = checklist.type === 'apertura' ? '🌅 Apertura' : '🌙 Cierre';
  const isCompleted = checklist.status === 'completed';

  const handleItemCompleted = (itemId: number) => {
    // Optimistic UI: update local state immediately
    checklist.items = checklist.items.map(i => 
      i.id === itemId ? { ...i, is_completed: !i.is_completed, completed_at: i.is_completed ? null : new Date().toISOString() } : i
    );
    checklist.completed_items = checklist.items.filter(i => i.is_completed).length;
    checklist.completion_percentage = Math.round((checklist.completed_items / checklist.total_items) * 100);
    if (checklist.completed_items === checklist.total_items) checklist.status = 'completed';
    else checklist.status = 'active';
    onUpdate();
  };

  const handlePhotoUploaded = (itemId: number, photoUrl: string, aiScore?: number | null, aiObs?: string | null) => {
    checklist.items = checklist.items.map(i =>
      i.id === itemId ? { ...i, photo_url: photoUrl, is_completed: true, completed_at: new Date().toISOString(), ai_score: aiScore, ai_observations: aiObs } : i
    );
    checklist.completed_items = checklist.items.filter(i => i.is_completed).length;
    checklist.completion_percentage = Math.round((checklist.completed_items / checklist.total_items) * 100);
    if (checklist.completed_items === checklist.total_items) checklist.status = 'completed';
    else checklist.status = 'active';
    onUpdate();
  };

  return (
    <div className={cn(
      'rounded-xl border shadow-sm overflow-hidden',
      isCompleted ? 'border-green-200' : 'border-gray-200'
    )}>
      {/* Header */}
      <div className={cn(
        'px-4 py-3 flex items-center justify-between',
        isCompleted ? 'bg-green-50' : 'bg-gray-50'
      )}>
        <div>
          <h3 className="font-semibold text-gray-900">{typeLabel}</h3>
          <p className="text-xs text-gray-500 capitalize">
            {checklist.rol} · {checklist.scheduled_time?.slice(0, 5) || ''}
          </p>
        </div>
        {isCompleted ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
            <CheckCircle2 className="h-3.5 w-3.5" /> Completado
          </span>
        ) : (
          <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
            Pendiente
          </span>
        )}
      </div>

      {/* Progress */}
      <div className="px-4">
        <ProgressBar completed={checklist.completed_items} total={checklist.total_items} />
      </div>

      {/* Items */}
      <div className="p-4 space-y-2">
        {checklist.items.map(item => (
          <ChecklistItemRow
            key={item.id}
            item={item}
            checklistId={checklist.id}
            checklistType={checklist.type as 'apertura' | 'cierre'}
            onItemCompleted={handleItemCompleted}
            onPhotoUploaded={handlePhotoUploaded}
          />
        ))}
      </div>
    </div>
  );
}

/* ─── Virtual Checklist Section ─── */
function VirtualChecklistSection({ onCompleted }: { onCompleted: () => void }) {
  const [virtual, setVirtual] = useState<ChecklistVirtual | null>(null);
  const [loading, setLoading] = useState(true);
  const [idea, setIdea] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [completed, setCompleted] = useState(false);

  useEffect(() => {
    apiFetch<ApiResponse<ChecklistVirtual>>('/worker/checklists/virtual')
      .then(res => {
        const data = res.data || null;
        setVirtual(data);
        if (data?.completed_at) setCompleted(true);
      })
      .catch(() => {
        // No virtual checklist available — that's fine
      })
      .finally(() => setLoading(false));
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (idea.trim().length < 20) {
      setError('La idea debe tener al menos 20 caracteres');
      return;
    }
    if (!virtual) return;

    setSubmitting(true);
    setError('');
    try {
      await apiFetch(`/worker/checklists/virtual/${virtual.id}/complete`, {
        method: 'POST',
        body: JSON.stringify({ improvement_idea: idea.trim() }),
      });
      setCompleted(true);
      onCompleted();
    } catch (err: any) {
      setError(err.message || 'Error al completar checklist virtual');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) return null;
  if (!virtual) return null;

  const confirmationText = virtual.confirmation_text ||
    'Al marcar este checklist confirmo que no asistiré a foodtruck porque mi compañero/a no asistirá este día. No se me descontará. No obstante estaré a disposición de otras tareas.';

  return (
    <div className="rounded-xl border border-blue-200 bg-blue-50/50 shadow-sm overflow-hidden">
      <div className="px-4 py-3 bg-blue-50 flex items-center gap-2">
        <Lightbulb className="h-5 w-5 text-blue-600" />
        <h3 className="font-semibold text-blue-900">Checklist Virtual</h3>
        {completed && (
          <span className="ml-auto inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
            <CheckCircle2 className="h-3.5 w-3.5" /> Completado
          </span>
        )}
      </div>

      <div className="p-4 space-y-4">
        {/* Confirmation text */}
        <div className="rounded-lg bg-white border border-blue-100 p-3">
          <p className="text-sm text-gray-700 leading-relaxed">{confirmationText}</p>
        </div>

        {completed ? (
          <div className="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700">
            ✅ Checklist virtual completado. Tu asistencia fue registrada.
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Para completar este checklist, indica ideas de cómo mejorar nuestros servicios actuales
                <span className="text-gray-400 font-normal"> (preparación nueva, procedimiento nuevo, oportunidad de mejora)</span>
              </label>
              <textarea
                value={idea}
                onChange={e => {
                  setIdea(e.target.value);
                  if (error) setError('');
                }}
                placeholder="Escribe tu idea de mejora aquí (mínimo 20 caracteres)..."
                rows={3}
                className={cn(
                  'block w-full rounded-lg border px-3 py-2.5 text-sm focus:ring-1 transition-colors',
                  error
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500'
                )}
              />
              <div className="mt-1 flex items-center justify-between text-xs">
                <span className={cn(
                  idea.trim().length < 20 ? 'text-gray-400' : 'text-green-600'
                )}>
                  {idea.trim().length}/20 caracteres mínimo
                </span>
                {error && <span className="text-red-500">{error}</span>}
              </div>
            </div>

            <button
              type="submit"
              disabled={submitting || idea.trim().length < 20}
              className="w-full flex items-center justify-center gap-2 rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {submitting ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Send className="h-4 w-4" />
              )}
              {submitting ? 'Enviando...' : 'Completar Checklist Virtual'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}

/* ─── Main Page ─── */
export default function ChecklistPage() {
  const [checklists, setChecklists] = useState<Checklist[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const fetchChecklists = useCallback(() => {
    setLoading(true);
    setError('');
    apiFetch<ApiResponse<Checklist[]>>('/worker/checklists')
      .then(res => setChecklists(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    fetchChecklists();
  }, [fetchChecklists]);

  const allCompleted = checklists.length > 0 && checklists.every(c => c.status === 'completed');

  if (loading) {
    return (
      <div className="flex justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-amber-600" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-gray-900">Checklist</h1>
        <div className="rounded-lg bg-red-50 p-4 text-sm text-red-600">{error}</div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">Checklist del Día</h1>

      {checklists.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <ClipboardCheck className="mx-auto h-12 w-12 text-gray-300" />
          <p className="mt-3 text-gray-500">No tienes checklists pendientes</p>
          <p className="mt-1 text-xs text-gray-400">
            Los checklists se crean automáticamente cuando tienes un turno asignado
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          {checklists.map(checklist => (
            <ChecklistCard
              key={checklist.id}
              checklist={checklist}
              onUpdate={() => setChecklists([...checklists])}
            />
          ))}

          {allCompleted && (
            <div className="rounded-lg bg-green-50 border border-green-200 p-4 text-center">
              <CheckCircle2 className="mx-auto h-8 w-8 text-green-500" />
              <p className="mt-2 text-sm font-medium text-green-700">
                ¡Todos los checklists completados! 🎉
              </p>
            </div>
          )}
        </div>
      )}

      {/* Virtual Checklist Section */}
      <VirtualChecklistSection onCompleted={fetchChecklists} />
    </div>
  );
}
