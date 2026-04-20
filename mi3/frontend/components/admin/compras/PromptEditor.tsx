'use client';

import { useState, useRef, useEffect } from 'react';
import { comprasApi } from '@/lib/compras-api';
import { Save, X, Loader2, Code } from 'lucide-react';
import type { AiPrompt } from './PromptsManager';

interface PromptEditorProps {
  prompt: AiPrompt;
  onSaved: (updated: AiPrompt) => void;
  onCancel: () => void;
}

export function PromptEditor({ prompt, onSaved, onCancel }: PromptEditorProps) {
  const [text, setText] = useState(prompt.prompt_text);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    autoResize();
  }, [text]);

  function autoResize() {
    const ta = textareaRef.current;
    if (ta) {
      ta.style.height = 'auto';
      ta.style.height = `${Math.min(ta.scrollHeight, 500)}px`;
    }
  }

  async function handleSave() {
    if (text === prompt.prompt_text) return;
    setSaving(true);
    setError(null);
    const original = prompt.prompt_text;
    try {
      const res = await comprasApi.patch<{ success: boolean; data: AiPrompt }>(
        `/compras/ai-prompts/${prompt.id}`,
        { prompt_text: text }
      );
      if (res.success) {
        onSaved(res.data);
      }
    } catch {
      setText(original);
      setError('Error al guardar. Intenta de nuevo.');
    }
    setSaving(false);
  }

  function handleCancel() {
    setText(prompt.prompt_text);
    onCancel();
  }

  const hasChanges = text !== prompt.prompt_text;

  return (
    <div className="space-y-2">
      {prompt.variables && prompt.variables.length > 0 && (
        <div className="flex flex-wrap items-center gap-1.5">
          <Code className="h-3.5 w-3.5 text-gray-400" />
          <span className="text-xs text-gray-500">Variables:</span>
          {prompt.variables.map(v => (
            <span key={v} className="rounded-full bg-purple-50 px-2 py-0.5 text-xs text-purple-700 font-mono">
              {`{${v}}`}
            </span>
          ))}
        </div>
      )}

      <textarea
        ref={textareaRef}
        value={text}
        onChange={e => setText(e.target.value)}
        className="w-full rounded-lg border bg-gray-50 p-3 font-mono text-xs leading-relaxed text-gray-800 focus:border-blue-300 focus:outline-none focus:ring-1 focus:ring-blue-300 resize-none"
        rows={6}
        aria-label={`Editar prompt: ${prompt.label}`}
      />

      {error && <p className="text-xs text-red-600">{error}</p>}

      <div className="flex items-center gap-2">
        <button
          onClick={handleSave}
          disabled={saving || !hasChanges}
          className="flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {saving ? <Loader2 className="h-3 w-3 animate-spin" /> : <Save className="h-3 w-3" />}
          Guardar
        </button>
        <button
          onClick={handleCancel}
          className="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50"
        >
          <X className="h-3 w-3" /> Cancelar
        </button>
      </div>
    </div>
  );
}
