'use client';

import { useState, useEffect } from 'react';
import { comprasApi } from '@/lib/compras-api';
import { cn } from '@/lib/utils';
import { FileText, ChevronDown, ChevronUp, Code, Tag, History, Loader2 } from 'lucide-react';
import { PromptEditor } from './PromptEditor';
import { PromptHistory } from './PromptHistory';

export interface AiPrompt {
  id: number;
  slug: string;
  pipeline: string;
  label: string;
  description: string | null;
  prompt_text: string;
  variables: string[] | null;
  prompt_version: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface AiPromptVersion {
  id: number;
  ai_prompt_id: number;
  prompt_text: string;
  prompt_version: number;
  created_at: string;
}

interface PromptsResponse {
  success: boolean;
  data: AiPrompt[];
}

const PIPELINE_LABELS: Record<string, string> = {
  legacy: 'Legacy (Single-call)',
  'multi-agent-rules': 'Multi-Agent Rules',
  'multi-agent-phases': 'Multi-Agent Phases',
};

export default function PromptsManager() {
  const [prompts, setPrompts] = useState<AiPrompt[]>([]);
  const [loading, setLoading] = useState(true);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [historyId, setHistoryId] = useState<number | null>(null);
  const [historyVersions, setHistoryVersions] = useState<AiPromptVersion[]>([]);

  useEffect(() => {
    fetchPrompts();
  }, []);

  async function fetchPrompts() {
    setLoading(true);
    try {
      const res = await comprasApi.get<PromptsResponse>('/compras/ai-prompts');
      if (res.success) setPrompts(res.data);
    } catch {
      // silent
    }
    setLoading(false);
  }

  const grouped = prompts.reduce<Record<string, AiPrompt[]>>((acc, p) => {
    (acc[p.pipeline] ||= []).push(p);
    return acc;
  }, {});

  function handlePromptUpdated(updated: AiPrompt) {
    setPrompts(prev => prev.map(p => p.id === updated.id ? updated : p));
    setEditingId(null);
  }

  async function openHistory(prompt: AiPrompt) {
    if (historyId === prompt.id) {
      setHistoryId(null);
      return;
    }
    try {
      const res = await comprasApi.get<{ success: boolean; data: { prompt: AiPrompt; versions: AiPromptVersion[] } }>(
        `/compras/ai-prompts/${prompt.id}`
      );
      if (res.success) {
        setHistoryVersions(res.data.versions);
        setHistoryId(prompt.id);
      }
    } catch {
      // silent
    }
  }

  function handleReverted(updated: AiPrompt) {
    setPrompts(prev => prev.map(p => p.id === updated.id ? updated : p));
    setHistoryId(null);
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
      </div>
    );
  }

  return (
    <div className="space-y-6 p-3 md:p-4">
      <div className="flex items-center gap-2">
        <FileText className="h-5 w-5 text-gray-600" />
        <h2 className="text-lg font-semibold text-gray-800">Prompts IA</h2>
        <span className="text-sm text-gray-400">({prompts.length} prompts)</span>
      </div>

      {Object.entries(PIPELINE_LABELS).map(([pipeline, label]) => {
        const items = grouped[pipeline];
        if (!items?.length) return null;
        return (
          <section key={pipeline} className="space-y-2">
            <h3 className="text-sm font-semibold text-gray-600 uppercase tracking-wide">{label}</h3>
            <div className="space-y-2">
              {items.map(prompt => (
                <PromptCard
                  key={prompt.id}
                  prompt={prompt}
                  expanded={expandedId === prompt.id}
                  editing={editingId === prompt.id}
                  showHistory={historyId === prompt.id}
                  historyVersions={historyVersions}
                  onToggle={() => setExpandedId(expandedId === prompt.id ? null : prompt.id)}
                  onEdit={() => setEditingId(editingId === prompt.id ? null : prompt.id)}
                  onHistory={() => openHistory(prompt)}
                  onPromptUpdated={handlePromptUpdated}
                  onReverted={handleReverted}
                />
              ))}
            </div>
          </section>
        );
      })}
    </div>
  );
}

function PromptCard({
  prompt, expanded, editing, showHistory, historyVersions,
  onToggle, onEdit, onHistory, onPromptUpdated, onReverted,
}: {
  prompt: AiPrompt;
  expanded: boolean;
  editing: boolean;
  showHistory: boolean;
  historyVersions: AiPromptVersion[];
  onToggle: () => void;
  onEdit: () => void;
  onHistory: () => void;
  onPromptUpdated: (p: AiPrompt) => void;
  onReverted: (p: AiPrompt) => void;
}) {
  return (
    <div className={cn('rounded-xl border bg-white transition-shadow', expanded && 'shadow-md')}>
      <button
        onClick={onToggle}
        className="flex w-full items-center gap-2 px-3 py-2.5 text-left"
        aria-expanded={expanded}
        aria-label={prompt.label}
      >
        <FileText className="h-4 w-4 text-gray-400 flex-shrink-0" />
        <span className="flex-1 truncate text-sm font-medium text-gray-700">{prompt.label}</span>
        <span className="hidden sm:inline rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
          <Tag className="inline h-3 w-3 mr-0.5" />{prompt.slug}
        </span>
        <span className="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-600">v{prompt.prompt_version}</span>
        {expanded ? <ChevronUp className="h-4 w-4 text-gray-400" /> : <ChevronDown className="h-4 w-4 text-gray-400" />}
      </button>

      {expanded && (
        <div className="border-t px-3 pb-3 pt-2 space-y-3">
          {prompt.variables && prompt.variables.length > 0 && (
            <div className="flex flex-wrap items-center gap-1.5">
              <Code className="h-3.5 w-3.5 text-gray-400" />
              {prompt.variables.map(v => (
                <span key={v} className="rounded-full bg-purple-50 px-2 py-0.5 text-xs text-purple-700 font-mono">
                  {`{${v}}`}
                </span>
              ))}
            </div>
          )}

          <div className="flex gap-2">
            <button onClick={onEdit} className="flex items-center gap-1 rounded-lg border px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-50">
              <Code className="h-3 w-3" /> {editing ? 'Cerrar editor' : 'Editar'}
            </button>
            <button onClick={onHistory} className="flex items-center gap-1 rounded-lg border px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-50">
              <History className="h-3 w-3" /> Historial
            </button>
          </div>

          {editing && (
            <PromptEditor prompt={prompt} onSaved={onPromptUpdated} onCancel={onEdit} />
          )}

          {showHistory && (
            <PromptHistory prompt={prompt} versions={historyVersions} onReverted={onReverted} />
          )}
        </div>
      )}
    </div>
  );
}
