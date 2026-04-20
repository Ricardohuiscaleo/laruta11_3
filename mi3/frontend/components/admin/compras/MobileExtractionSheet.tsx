'use client';

import { useState, useEffect, useCallback } from 'react';
import { Check, Eye, Brain, ShieldCheck, Scale, Camera } from 'lucide-react';
import ExtractionPipeline from './ExtractionPipeline';
import ReconciliationQuestions from './ReconciliationQuestions';
import type { ReconciliationQuestion } from './ExtractionPipeline';
import type { ExtractionResult } from '@/types/compras';

interface Props {
  open: boolean;
  tempKey: string | null;
  tempUrl: string | null;
  uploading: boolean;
  uploadProgress: string | null;
  reconciliationQuestions: ReconciliationQuestion[];
  reconciliationLoading: boolean;
  onResult: (data: ExtractionResult, sugerencias?: ExtractionResult['sugerencias']) => void;
  onError: () => void;
  onReconciliationNeeded: (questions: ReconciliationQuestion[]) => void;
  onReconciliationSubmit: (responses: Record<string, string | number>) => void;
  onClose: () => void;
}

const STEP_MAP: Record<string, number> = { vision: 1, analisis: 2, validacion: 3, reconciliacion: 4 };
const STEPS = [
  { id: 'upload', label: 'Subida', icon: Camera },
  { id: 'vision', label: 'Visión', icon: Eye },
  { id: 'analisis', label: 'Análisis', icon: Brain },
  { id: 'validacion', label: 'Check', icon: ShieldCheck },
  { id: 'reconciliacion', label: 'Ajuste', icon: Scale },
];

function formatDetail(fase: string, data: Record<string, unknown> | null): string {
  if (!data) return '';
  const ms = Number(data.elapsed_ms || 0);
  const sec = (ms / 1000).toFixed(1);
  const tokens = Number(data.tokens || 0);
  const tokStr = tokens > 0 ? ` · ${tokens.toLocaleString('es-CL')} tok` : '';

  switch (fase) {
    case 'vision': {
      const tipo = String(data.tipo_imagen || '?');
      const conf = Math.round(Number(data.confianza || 0) * 100);
      return `📄 ${tipo} (${conf}%) · ${sec}s${tokStr}`;
    }
    case 'analisis':
      return `📋 Estructurando · ${sec}s${tokStr}`;
    case 'validacion': {
      const inc = Number(data.inconsistencias_count || 0);
      return inc > 0 ? `⚠️ ${inc} inconsistencias · ${sec}s${tokStr}` : `✅ OK · ${sec}s${tokStr}`;
    }
    case 'reconciliacion': {
      const corr = Number(data.correcciones_auto || 0);
      const preg = Number(data.preguntas || 0);
      const parts = [];
      if (corr > 0) parts.push(`${corr} correcciones`);
      if (preg > 0) parts.push(`${preg} preguntas`);
      return `🔧 ${parts.join(' · ') || 'Sin cambios'} · ${sec}s${tokStr}`;
    }
    default:
      return '';
  }
}

export default function MobileExtractionSheet({
  open, tempKey, uploading,
  reconciliationQuestions, reconciliationLoading,
  onResult, onError, onReconciliationNeeded, onReconciliationSubmit, onClose,
}: Props) {
  const [currentStep, setCurrentStep] = useState(0);
  const [stepDetails, setStepDetails] = useState<Record<number, string>>({});

  useEffect(() => {
    if (uploading) { setCurrentStep(0); setStepDetails({}); }
    else if (tempKey) setCurrentStep(1);
  }, [uploading, tempKey]);

  // Auto-close when done
  useEffect(() => {
    if (!uploading && !tempKey && open && currentStep > 0 && reconciliationQuestions.length === 0) {
      const timer = setTimeout(() => { setCurrentStep(0); setStepDetails({}); onClose(); }, 1200);
      return () => clearTimeout(timer);
    }
  }, [uploading, tempKey, open, currentStep, reconciliationQuestions.length, onClose]);

  const handlePhaseChange = useCallback((fase: string, status: string, data: Record<string, unknown> | null) => {
    const idx = STEP_MAP[fase];
    if (idx === undefined) return;
    if (status === 'running') setCurrentStep(idx);
    if (status === 'done') {
      setCurrentStep(idx + 1);
      setStepDetails(prev => ({ ...prev, [idx]: formatDetail(fase, data) }));
    }
  }, []);

  const handleResult = useCallback((data: ExtractionResult, sug?: ExtractionResult['sugerencias']) => {
    setCurrentStep(5);
    onResult(data, sug);
  }, [onResult]);

  if (!open) return null;

  const isDone = !uploading && !tempKey && currentStep >= 5;

  return (
    <div className="fixed inset-0 z-50 flex flex-col items-center justify-start" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-md" />

      <div className="relative mt-16 mx-3 w-[calc(100%-1.5rem)] max-w-sm rounded-2xl border border-white/20 bg-white/10 backdrop-blur-xl p-4 shadow-2xl">
        {/* Steps */}
        <div className="space-y-2">
          {STEPS.map((step, i) => {
            const Icon = step.icon;
            const isActive = i === currentStep;
            const isCompleted = i < currentStep;
            const isPending = i > currentStep;
            const detail = stepDetails[i] || (i === 0 && !uploading && currentStep > 0 ? '✅ Imagen subida' : '');

            return (
              <div key={step.id} className={[
                'flex items-center gap-3 rounded-xl px-3 py-2 transition-all duration-500',
                isActive ? 'bg-white/10' : '',
                isCompleted ? 'opacity-90' : '',
                isPending ? 'opacity-30' : '',
              ].join(' ')}>
                <div className={[
                  'flex items-center justify-center h-8 w-8 rounded-full shrink-0 transition-all duration-500',
                  isCompleted ? 'bg-green-500' : '',
                  isActive ? 'bg-red-500 shadow-lg shadow-red-500/40' : '',
                  isPending ? 'bg-white/10' : '',
                ].join(' ')}>
                  {isCompleted ? (
                    <Check className="h-4 w-4 text-white" />
                  ) : isActive ? (
                    <Icon className="h-4 w-4 text-white animate-pulse" />
                  ) : (
                    <Icon className="h-3.5 w-3.5 text-white/30" />
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <span className={[
                    'text-xs font-medium block',
                    isCompleted ? 'text-green-300' : isActive ? 'text-white' : 'text-white/30',
                  ].join(' ')}>
                    {step.label}
                  </span>
                  {detail && (
                    <span className="text-[10px] text-white/50 block truncate">{detail}</span>
                  )}
                </div>
              </div>
            );
          })}
        </div>

        {/* Progress bar */}
        <div className="mt-3 h-0.5 rounded-full bg-white/10 overflow-hidden">
          <div
            className="h-full rounded-full bg-gradient-to-r from-red-500 to-green-500 transition-all duration-700"
            style={{ width: `${Math.min(100, (currentStep / 5) * 100)}%` }}
          />
        </div>

        {isDone && (
          <div className="flex items-center justify-center gap-2 mt-3">
            <Check className="h-4 w-4 text-green-400" />
            <span className="text-sm font-medium text-green-300">Listo</span>
          </div>
        )}
      </div>

      {/* Hidden pipeline */}
      {tempKey && !uploading && (
        <div className="sr-only">
          <ExtractionPipeline
            tempKey={tempKey}
            onResult={handleResult}
            onError={onError}
            onReconciliationNeeded={onReconciliationNeeded}
            onPhaseChange={handlePhaseChange}
          />
        </div>
      )}

      {reconciliationQuestions.length > 0 && (
        <div className="relative mt-4 mx-3 w-[calc(100%-1.5rem)] max-w-sm rounded-2xl border border-white/20 bg-white/10 backdrop-blur-xl p-4 shadow-2xl">
          <ReconciliationQuestions questions={reconciliationQuestions} onSubmit={onReconciliationSubmit} loading={reconciliationLoading} />
        </div>
      )}
    </div>
  );
}
