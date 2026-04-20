'use client';

import { useState, useCallback, useEffect, useRef } from 'react';
import { Search, Brain, Bot, Check, X, Loader2, AlertTriangle, Clock, Eye, ShieldCheck, Scale } from 'lucide-react';
import { getToken } from '@/lib/auth';
import type { ExtractionResult } from '@/types/compras';

// Legacy phase IDs (Bedrock/Gemini)
type LegacyPhaseId = 'percepcion' | 'clasificacion' | 'analisis';
// Multi-agent phase IDs
type MultiAgentPhaseId = 'vision' | 'analisis' | 'validacion' | 'reconciliacion';
// Combined type
type PhaseId = LegacyPhaseId | MultiAgentPhaseId;
type EngineType = 'gemini' | 'bedrock' | 'multi-agent' | null;

interface PipelinePhase {
  id: PhaseId;
  label: string;
  icon: React.ReactNode;
  status: 'pending' | 'running' | 'done' | 'error';
  data: Record<string, unknown> | null;
  elapsedMs: number;
}

interface PipelineEvent {
  fase: string;
  status: string;
  engine?: string;
  data: Record<string, unknown> | null;
  elapsed_ms: number;
}

export interface ReconciliationQuestion {
  campo: string;
  descripcion: string;
  opciones: { valor: string | number; etiqueta: string }[];
}

interface ExtractionPipelineProps {
  tempKey: string;
  onResult: (data: ExtractionResult, sugerencias: ExtractionResult['sugerencias']) => void;
  onError: () => void;
  onReconciliationNeeded?: (questions: ReconciliationQuestion[]) => void;
  onPhaseChange?: (fase: string, status: string, data: Record<string, unknown> | null) => void;
  autoStart?: boolean;
}

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';

const BEDROCK_PHASES: PipelinePhase[] = [
  { id: 'percepcion', label: 'Identificando objetos y textos', icon: <Search className="h-4 w-4" />, status: 'pending', data: null, elapsedMs: 0 },
  { id: 'clasificacion', label: 'Clasificando imagen', icon: <Brain className="h-4 w-4" />, status: 'pending', data: null, elapsedMs: 0 },
  { id: 'analisis', label: 'Analizando con IA', icon: <Bot className="h-4 w-4" />, status: 'pending', data: null, elapsedMs: 0 },
];

const GEMINI_PHASES: PipelinePhase[] = [
  { id: 'clasificacion', label: 'Clasificando imagen (Gemini)', icon: <Brain className="h-4 w-4" />, status: 'pending', data: null, elapsedMs: 0 },
  { id: 'analisis', label: 'Analizando con IA (Gemini)', icon: <Bot className="h-4 w-4" />, status: 'pending', data: null, elapsedMs: 0 },
];

const MULTI_AGENT_PHASES: PipelinePhase[] = [
  { id: 'vision', label: 'Percibiendo imagen', icon: <Eye className="h-4 w-4" />, status: 'pending', data: null, elapsedMs: 0 },
  { id: 'analisis', label: 'Estructurando datos', icon: <Brain className="h-4 w-4" />, status: 'pending', data: null, elapsedMs: 0 },
  { id: 'validacion', label: 'Validando coherencia', icon: <ShieldCheck className="h-4 w-4" />, status: 'pending', data: null, elapsedMs: 0 },
  { id: 'reconciliacion', label: 'Reconciliando', icon: <Scale className="h-4 w-4" />, status: 'pending', data: null, elapsedMs: 0 },
];


export default function ExtractionPipeline({ tempKey, onResult, onError, onReconciliationNeeded, onPhaseChange, autoStart = true }: ExtractionPipelineProps) {
  const [phases, setPhases] = useState<PipelinePhase[]>(BEDROCK_PHASES.map(p => ({ ...p })));
  const [running, setRunning] = useState(false);
  const [slow, setSlow] = useState(false);
  const [totalMs, setTotalMs] = useState(0);
  const startedRef = useRef(false);
  const slowTimerRef = useRef<ReturnType<typeof setTimeout>>();
  const engineRef = useRef<EngineType>(null);

  const updatePhase = useCallback((id: string, updates: Partial<PipelinePhase>) => {
    setPhases(prev => prev.map(p => p.id === id ? { ...p, ...updates } : p));
  }, []);

  const initPhasesForEngine = useCallback((engine: EngineType) => {
    if (engineRef.current) return;
    engineRef.current = engine;
    if (engine === 'gemini') {
      setPhases(GEMINI_PHASES.map(p => ({ ...p })));
    } else if (engine === 'multi-agent') {
      setPhases(MULTI_AGENT_PHASES.map(p => ({ ...p })));
    }
  }, []);

  const handleEvent = useCallback((event: PipelineEvent) => {
    const { fase, status, data, elapsed_ms, engine } = event;

    // Detect engine from first event
    if (engine && !engineRef.current) {
      if (engine === 'multi-agent') {
        initPhasesForEngine('multi-agent');
      } else {
        initPhasesForEngine(engine === 'gemini' ? 'gemini' : 'bedrock');
      }
    }

    if (fase === 'resultado') {
      setTotalMs(elapsed_ms);
      if (status === 'done' && data?.success) {
        const result = data as unknown as {
          data: ExtractionResult;
          sugerencias: ExtractionResult['sugerencias'];
          confianza: ExtractionResult['confianza'];
        };
        const extraction: ExtractionResult = {
          ...result.data,
          confianza: result.confianza ?? result.data?.confianza,
          sugerencias: result.sugerencias ?? result.data?.sugerencias,
        };
        onResult(extraction, result.sugerencias);
      } else {
        onError();
      }
      return;
    }

    if (fase === 'error') {
      onError();
      return;
    }

    // Determine valid phases based on engine
    const phaseId = fase as PhaseId;
    const multiAgentPhases: MultiAgentPhaseId[] = ['vision', 'analisis', 'validacion', 'reconciliacion'];
    const geminiPhases: LegacyPhaseId[] = ['clasificacion', 'analisis'];
    const bedrockPhases: LegacyPhaseId[] = ['percepcion', 'clasificacion', 'analisis'];

    let validPhases: PhaseId[];
    if (engineRef.current === 'multi-agent') {
      validPhases = multiAgentPhases;
    } else if (engineRef.current === 'gemini') {
      validPhases = geminiPhases;
    } else {
      validPhases = bedrockPhases;
    }

    if (validPhases.includes(phaseId)) {
      updatePhase(phaseId, {
        status: status === 'done' ? 'done' : status === 'error' ? 'error' : 'running',
        data: data as Record<string, unknown> | null,
        elapsedMs: elapsed_ms,
      });

      // Notify parent of phase changes
      onPhaseChange?.(fase, status, data as Record<string, unknown> | null);

      // Handle reconciliation questions from multi-agent pipeline
      if (phaseId === 'reconciliacion' && status === 'done' && data) {
        const preguntas = data.preguntas as ReconciliationQuestion[] | undefined;
        if (preguntas && preguntas.length > 0 && onReconciliationNeeded) {
          onReconciliationNeeded(preguntas);
        }
      }
    }
  }, [onResult, onError, updatePhase, initPhasesForEngine, onReconciliationNeeded, onPhaseChange]);

  const runPipeline = useCallback(async () => {
    if (running) return;
    setRunning(true);
    setSlow(false);

    slowTimerRef.current = setTimeout(() => setSlow(true), 8000);

    try {
      const token = getToken();
      const res = await fetch(`${API_URL}/api/v1/admin/compras/extract-pipeline`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream',
          ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
        },
        credentials: 'include',
        body: JSON.stringify({ temp_key: tempKey }),
      });

      if (!res.ok || !res.body) {
        throw new Error(`HTTP ${res.status}`);
      }

      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';

        for (const line of lines) {
          if (!line.startsWith('data: ')) continue;
          const jsonStr = line.slice(6).trim();
          if (!jsonStr) continue;

          try {
            const event: PipelineEvent = JSON.parse(jsonStr);
            handleEvent(event);
          } catch {
            // skip malformed events
          }
        }
      }
    } catch {
      onError();
    } finally {
      setRunning(false);
      if (slowTimerRef.current) clearTimeout(slowTimerRef.current);
    }
  }, [tempKey, running, handleEvent, onError]);

  useEffect(() => {
    if (autoStart && !startedRef.current) {
      startedRef.current = true;
      runPipeline();
    }
  }, [autoStart, runPipeline]);

  useEffect(() => {
    return () => {
      if (slowTimerRef.current) clearTimeout(slowTimerRef.current);
    };
  }, []);

  return (
    <div className="rounded-xl border border-white/20 bg-white/60 backdrop-blur-sm p-4 space-y-3 shadow-sm" role="status" aria-live="polite" aria-label="Pipeline de extracción IA">
      <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">Extracción inteligente</p>

      <div className="space-y-2">
        {phases.map((phase) => (
          <PhaseRow key={phase.id} phase={phase} />
        ))}
      </div>

      {slow && (
        <div className="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-700">
          <Clock className="h-3.5 w-3.5 flex-shrink-0" />
          <span>Tomando más tiempo de lo normal...</span>
        </div>
      )}

      {totalMs > 0 && (
        <p className="text-xs text-gray-400 text-right">{(totalMs / 1000).toFixed(1)}s total</p>
      )}
    </div>
  );
}


function PhaseRow({ phase }: { phase: PipelinePhase }) {
  const statusIcon = {
    pending: <div className="h-4 w-4 rounded-full border-2 border-gray-200" />,
    running: <Loader2 className="h-4 w-4 animate-spin text-blue-500" />,
    done: <Check className="h-4 w-4 text-green-600" />,
    error: <X className="h-4 w-4 text-red-500" />,
  }[phase.status];

  return (
    <div className={`flex items-start gap-3 rounded-lg border px-3 py-2.5 transition-colors ${
      phase.status === 'running' ? 'bg-white border-blue-200 shadow-sm' :
      phase.status === 'done' ? 'bg-white border-green-200' :
      phase.status === 'error' ? 'bg-white border-red-200' : 'bg-white border-gray-100'
    }`}>
      <div className="mt-0.5 flex-shrink-0">{statusIcon}</div>

      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <span className="text-gray-400">{phase.icon}</span>
          <span className={`text-sm font-medium ${
            phase.status === 'done' ? 'text-gray-700' :
            phase.status === 'running' ? 'text-blue-700' :
            phase.status === 'error' ? 'text-red-600' : 'text-gray-400'
          }`}>
            {phase.label}
          </span>
          {phase.elapsedMs > 0 && phase.status === 'done' && (
            <span className="text-xs text-gray-400">{(phase.elapsedMs / 1000).toFixed(1)}s</span>
          )}
        </div>

        {phase.status === 'done' && phase.data && (
          <PhaseDetails id={phase.id} data={phase.data} />
        )}
      </div>
    </div>
  );
}

function PhaseDetails({ id, data }: { id: string; data: Record<string, unknown> }) {
  const tokens = data.tokens as number | undefined;

  if (id === 'percepcion') {
    const labels = (data.labels as string[]) || [];
    const texts = (data.texts_preview as string[]) || [];
    return (
      <div className="mt-1 space-y-1">
        {labels.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {labels.slice(0, 6).map((l, i) => (
              <span key={i} className="inline-block rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">{l}</span>
            ))}
          </div>
        )}
        {texts.length > 0 && (
          <p className="text-xs text-gray-500 truncate">
            📝 {texts.slice(0, 4).join(' · ')}
          </p>
        )}
      </div>
    );
  }

  if (id === 'clasificacion') {
    const tipo = data.tipo_imagen as string;
    const confianza = data.confianza as number;
    const proveedores = data.contexto_proveedores as number;
    const productos = data.contexto_productos as number;
    const tipoIcons: Record<string, string> = {
      boleta: '🧾', factura: '📄', producto: '📦', bascula: '⚖️', transferencia: '💸', desconocido: '❓',
    };
    return (
      <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-600">
        <span className="font-medium">{tipoIcons[tipo] || '❓'} {tipo}</span>
        {confianza > 0 && <span className="text-gray-400">({Math.round(confianza * 100)}%)</span>}
        {(proveedores > 0 || productos > 0) && (
          <span className="text-gray-400">· {proveedores} proveedores, {productos} ingredientes</span>
        )}
        {tokens != null && tokens > 0 && (
          <span className="text-gray-400">· {tokens} tokens</span>
        )}
      </div>
    );
  }

  if (id === 'analisis') {
    const proveedor = data.proveedor as string;
    const itemsCount = data.items_count as number;
    const montoTotal = data.monto_total as number;
    const confidence = data.overall_confidence as number;
    return (
      <div className="mt-1 flex flex-wrap items-center gap-2 text-xs">
        {proveedor && <span className="font-medium text-gray-700">{proveedor}</span>}
        {itemsCount > 0 && <span className="text-gray-500">{itemsCount} items</span>}
        {montoTotal > 0 && <span className="text-green-700 font-medium">${montoTotal.toLocaleString('es-CL')}</span>}
        {confidence > 0 && (
          <span className={`${confidence >= 0.7 ? 'text-green-600' : 'text-amber-600'}`}>
            {Math.round(confidence * 100)}% confianza
          </span>
        )}
        {tokens != null && tokens > 0 && (
          <span className="text-gray-400">· {tokens} tokens</span>
        )}
      </div>
    );
  }

  // Multi-agent: Vision phase details
  if (id === 'vision') {
    const tipo = data.tipo_imagen as string;
    const confianza = data.confianza as number;
    const tipoIcons: Record<string, string> = {
      boleta: '🧾', factura: '📄', producto: '📦', bascula: '⚖️', transferencia: '💸', desconocido: '❓',
    };
    return (
      <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-600">
        {tipo && <span className="font-medium">{tipoIcons[tipo] || '❓'} {tipo}</span>}
        {confianza > 0 && <span className="text-gray-400">({Math.round(confianza * 100)}%)</span>}
        {tokens != null && tokens > 0 && (
          <span className="text-gray-400">· {tokens} tokens</span>
        )}
      </div>
    );
  }

  // Multi-agent: Validation phase details
  if (id === 'validacion') {
    const inconsistencias = data.inconsistencias as Array<{ campo: string; descripcion: string; severidad: string }> | undefined;
    const count = data.inconsistencias_count as number | undefined;
    const numIssues = inconsistencias?.length ?? count ?? 0;
    return (
      <div className="mt-1 space-y-1">
        <div className="flex flex-wrap items-center gap-2 text-xs">
          {numIssues === 0 ? (
            <span className="text-green-600 font-medium">✓ Sin inconsistencias</span>
          ) : (
            <span className="text-amber-600 font-medium">⚠️ {numIssues} inconsistencia{numIssues > 1 ? 's' : ''}</span>
          )}
          {tokens != null && tokens > 0 && (
            <span className="text-gray-400">· {tokens} tokens</span>
          )}
        </div>
        {inconsistencias && inconsistencias.length > 0 && (
          <ul className="space-y-0.5">
            {inconsistencias.slice(0, 3).map((inc, i) => (
              <li key={i} className={`text-xs ${inc.severidad === 'error' ? 'text-red-600' : 'text-amber-600'}`}>
                • {inc.descripcion}
              </li>
            ))}
            {inconsistencias.length > 3 && (
              <li className="text-xs text-gray-400">...y {inconsistencias.length - 3} más</li>
            )}
          </ul>
        )}
      </div>
    );
  }

  // Multi-agent: Reconciliation phase details
  if (id === 'reconciliacion') {
    const correcciones = data.correcciones_aplicadas as string[] | undefined;
    const preguntas = data.preguntas as ReconciliationQuestion[] | undefined;
    return (
      <div className="mt-1 space-y-1">
        <div className="flex flex-wrap items-center gap-2 text-xs">
          {correcciones && correcciones.length > 0 && (
            <span className="text-green-600 font-medium">🔧 {correcciones.length} corrección{correcciones.length > 1 ? 'es' : ''} auto</span>
          )}
          {preguntas && preguntas.length > 0 && (
            <span className="text-blue-600 font-medium">❓ {preguntas.length} pregunta{preguntas.length > 1 ? 's' : ''}</span>
          )}
          {(!correcciones || correcciones.length === 0) && (!preguntas || preguntas.length === 0) && (
            <span className="text-green-600 font-medium">✓ Datos consistentes</span>
          )}
          {tokens != null && tokens > 0 && (
            <span className="text-gray-400">· {tokens} tokens</span>
          )}
        </div>
        {correcciones && correcciones.length > 0 && (
          <ul className="space-y-0.5">
            {correcciones.slice(0, 3).map((c, i) => (
              <li key={i} className="text-xs text-green-700">✓ {c}</li>
            ))}
          </ul>
        )}
      </div>
    );
  }

  return null;
}
