'use client';

import { useState } from 'react';
import { comprasApi } from '@/lib/compras-api';
import { RotateCcw, Loader2, History } from 'lucide-react';
import type { AiPrompt, AiPromptVersion } from './PromptsManager';

interface PromptHistoryProps {
  prompt: AiPrompt;
  versions: AiPromptVersion[];
  onReverted: (updated: AiPrompt) => void;
}

export function PromptHistory({ prompt, versions, onReverted }: PromptHistoryProps) {
  const [revertingId, setRevertingId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function handleRevert(versionId: number) {
    setRevertingId(versionId);
    setError(null);
    try {
      const res = await comprasApi.post<{ success: boolean; data: AiPrompt }>(
        `/compras/ai-prompts/${prompt.id}/revert/${versionId}`,
        {}
      );
      if (res.success) {
        onReverted(res.data);
      }
    } catch {
      setError('Error al revertir. Intenta de nuevo.');
    }
    setRevertingId(null);
  }

  if (!versions.length) {
    return (
      <div className="flex items-center gap-2 py-3 text-xs text-gray-400">
        <History className="h-3.5 w-3.5" />
        Sin historial de versiones
      </div>
    );
  }

  return (
    <div className="space-y-2">
      <p className="text-xs font-medium text-gray-600">Historial de versiones</p>
      {error && <p className="text-xs text-red-600">{error}</p>}
      <div className="space-y-1 max-h-60 overflow-y-auto">
        {versions.map(v => {
          const date = new Date(v.created_at);
          const dateStr = date.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: '2-digit' });
          const timeStr = date.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
          return (
            <div key={v.id} className="flex items-center gap-2 rounded-lg border bg-gray-50 px-3 py-2">
              <span className="rounded bg-blue-50 px-1.5 py-0.5 text-xs font-medium text-blue-600">
                v{v.prompt_version}
              </span>
              <span className="flex-1 text-xs text-gray-500">{dateStr} {timeStr}</span>
              <button
                onClick={() => handleRevert(v.id)}
                disabled={revertingId === v.id}
                className="flex items-center gap-1 rounded border px-2 py-0.5 text-xs text-gray-600 hover:bg-white disabled:opacity-50"
                aria-label={`Revertir a versión ${v.prompt_version}`}
              >
                {revertingId === v.id
                  ? <Loader2 className="h-3 w-3 animate-spin" />
                  : <RotateCcw className="h-3 w-3" />}
                Revertir
              </button>
            </div>
          );
        })}
      </div>
    </div>
  );
}
