'use client';

import { useState, useEffect, useCallback } from 'react';
import { Check, Eye, Brain, ShieldCheck, Scale, Camera, Loader2 } from 'lucide-react';
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

interface StepDetail {
  summary: string;
  badges: { label: string; color: 'green' | 'amber' | 'blue' | 'gray' | 'red' }[];
  sub?: string;
}

const STEPS = [
  { id: 'upload',        label: 'Subida',   icon: Camera    },
  { id: 'vision',        label: 'Visión',   icon: Eye       },
  { id: 'analisis',      label: 'Análisis', icon: Brain     },
  { id: 'validacion',    label: 'Check',    icon: ShieldCheck },
  { id: 'reconciliacion',label: 'Ajuste',   icon: Scale     },
];

const BADGE_CLASSES: Record<string, string> = {
  green: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
  amber: 'bg-amber-50  text-amber-700  border border-amber-200',
  blue:  'bg-blue-50   text-blue-700   border border-blue-200',
  gray:  'bg-gray-50   text-gray-500   border border-gray-200',
  red:   'bg-red-50    text-red-600    border border-red-200',
};

function formatDetail(fase: string, data: Record<string, unknown> | null): StepDetail {
  if (!data) return { summary: '', badges: [] };

  const ms    = Number(data.elapsed_ms || data.elapsedMs || 0);
  const sec   = ms > 0 ? `${(ms / 1000).toFixed(1)}s` : '';
  const tokens = Number(data.tokens || 0);
  const tokBadge = tokens > 0
    ? [{ label: `${tokens.toLocaleString('es-CL')} tok`, color: 'gray' as const }]
    : [];
  const timeBadge = sec ? [{ label: sec, color: 'gray' as const }] : [];

  switch (fase) {
    case 'vision': {
      const tipo   = String(data.tipo_imagen || '?');
      const conf   = Math.round(Number(data.confianza || 0) * 100);
      const tipoIcons: Record<string, string> = {
        boleta: '🧾', factura: '📄', producto: '📦',
        bascula: '⚖️', transferencia: '💸', desconocido: '❓',
      };
      return {
        summary: `${tipoIcons[tipo] || '📄'} ${tipo}`,
        badges: [
          { label: `${conf}% confianza`, color: conf >= 70 ? 'green' : 'amber' },
          ...timeBadge,
          ...tokBadge,
        ],
      };
    }

    case 'analisis': {
      const proveedor   = data.proveedor as string | undefined;
      const items       = Number(data.items_count || 0);
      const monto       = Number(data.monto_total || 0);
      const confidence  = Math.round(Number(data.overall_confidence || 0) * 100);
      const confColor: 'green' | 'amber' = confidence >= 70 ? 'green' : 'amber';
      const badges = [
        ...(confidence > 0 ? [{ label: `${confidence}%`, color: confColor }] : []),
        ...(items > 0 ? [{ label: `${items} ítems`, color: 'blue' as const }] : []),
        ...timeBadge,
        ...tokBadge,
      ];
      return {
        summary: proveedor ? `📋 ${proveedor}` : '📋 Estructurando',
        badges,
        sub: monto > 0 ? `$${monto.toLocaleString('es-CL')} total` : undefined,
      };
    }

    case 'validacion': {
      const inc   = Number(data.inconsistencias_count || (data.inconsistencias as unknown[])?.length || 0);
      const isOk  = inc === 0;
      return {
        summary: isOk ? '✅ Sin inconsistencias' : `⚠️ ${inc} inconsistencia${inc > 1 ? 's' : ''}`,
        badges: [
          { label: isOk ? 'OK' : `${inc} problema${inc > 1 ? 's' : ''}`, color: isOk ? 'green' : 'amber' },
          ...timeBadge,
          ...tokBadge,
        ],
      };
    }

    case 'reconciliacion': {
      const corr = Number(data.correcciones_auto || (data.correcciones_aplicadas as unknown[])?.length || 0);
      const preg = Number(data.preguntas || (data.preguntas as unknown[])?.length || 0);
      const parts: string[] = [];
      if (corr > 0) parts.push(`${corr} correc.`);
      if (preg > 0) parts.push(`${preg} preg.`);
      return {
        summary: corr > 0 ? `🔧 ${corr} corrección${corr > 1 ? 'es' : ''} auto` : '✓ Datos consistentes',
        badges: [
          ...(corr > 0 ? [{ label: `${corr} fix${corr > 1 ? 's' : ''}`, color: 'green' as const }] : []),
          ...(preg > 0 ? [{ label: `${preg} pregunta${preg > 1 ? 's' : ''}`, color: 'blue' as const }] : []),
          ...timeBadge,
          ...tokBadge,
        ],
      };
    }

    default:
      return { summary: '', badges: [] };
  }
}

export default function MobileExtractionSheet({
  open, tempKey, uploading,
  reconciliationQuestions, reconciliationLoading,
  onResult, onError, onReconciliationNeeded, onReconciliationSubmit, onClose,
}: Props) {
  const [currentStep, setCurrentStep] = useState(0);
  const [stepDetails, setStepDetails] = useState<Record<number, StepDetail>>({});

  useEffect(() => {
    if (uploading) { setCurrentStep(0); setStepDetails({}); }
    else if (tempKey) setCurrentStep(1);
  }, [uploading, tempKey]);

  // Auto-close when done
  useEffect(() => {
    if (!uploading && !tempKey && open && currentStep > 0 && reconciliationQuestions.length === 0) {
      const timer = setTimeout(() => { setCurrentStep(0); setStepDetails({}); onClose(); }, 1500);
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

  const isDone    = !uploading && !tempKey && currentStep >= 5;
  const progress  = Math.min(100, (currentStep / 5) * 100);

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center"
      role="dialog"
      aria-modal="true"
    >
      {/* Backdrop — almost transparent, minimal blur */}
      <div className="absolute inset-0 bg-black/[0.07] backdrop-blur-[2px]" />

      {/* Card — solid white, centered */}
      <div className="relative w-full max-w-sm mx-4 sm:mx-auto rounded-2xl bg-white shadow-2xl overflow-hidden">

        {/* Header */}
        <div className="px-5 pt-5 pb-3 border-b border-gray-100">
          <p className="text-[10px] font-black uppercase tracking-[0.18em] text-gray-400">
            Extracción IA
          </p>
          <div className="mt-2 h-1 rounded-full bg-gray-100 overflow-hidden">
            <div
              className="h-full rounded-full bg-gradient-to-r from-blue-500 to-emerald-500 transition-all duration-700"
              style={{ width: `${progress}%` }}
            />
          </div>
        </div>

        {/* Steps */}
        <div className="px-5 py-3 space-y-1">
          {STEPS.map((step, i) => {
            const Icon        = step.icon;
            const isActive    = i === currentStep;
            const isCompleted = i < currentStep;
            const isPending   = i > currentStep;

            // Upload step special detail
            const detail: StepDetail = i === 0
              ? (!uploading && currentStep > 0
                  ? { summary: '✅ Imagen subida', badges: [] }
                  : uploading
                    ? { summary: 'Subiendo imagen...', badges: [] }
                    : { summary: '', badges: [] })
              : (stepDetails[i] || { summary: '', badges: [] });

            return (
              <div
                key={step.id}
                className={[
                  'flex items-start gap-3 rounded-xl px-3 py-2.5 transition-all duration-500',
                  isActive    ? 'bg-blue-50/70 ring-1 ring-blue-100' : '',
                  isCompleted ? '' : '',
                  isPending   ? 'opacity-35' : '',
                ].join(' ')}
              >
                {/* Status icon */}
                <div className={[
                  'flex items-center justify-center h-8 w-8 rounded-full shrink-0 mt-0.5 transition-all duration-500',
                  isCompleted ? 'bg-emerald-500 shadow-sm shadow-emerald-200' : '',
                  isActive    ? 'bg-gray-900 shadow-md shadow-gray-200' : '',
                  isPending   ? 'bg-gray-100' : '',
                ].join(' ')}>
                  {isCompleted ? (
                    <Check className="h-4 w-4 text-white" strokeWidth={2.5} />
                  ) : isActive ? (
                    <Loader2 className="h-4 w-4 text-white animate-spin" />
                  ) : (
                    <Icon className="h-3.5 w-3.5 text-gray-300" />
                  )}
                </div>

                {/* Content */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-baseline gap-2">
                    <span className={[
                      'text-sm font-semibold leading-tight',
                      isCompleted ? 'text-gray-700'  : '',
                      isActive    ? 'text-gray-900'  : '',
                      isPending   ? 'text-gray-300'  : '',
                    ].join(' ')}>
                      {step.label}
                    </span>
                    {isActive && (
                      <span className="text-[10px] font-medium text-blue-500 animate-pulse">procesando…</span>
                    )}
                  </div>

                  {/* Dynamic detail */}
                  {(isCompleted || (i === 0 && isActive)) && detail.summary && (
                    <p className="text-xs text-gray-500 mt-0.5 leading-snug">{detail.summary}</p>
                  )}

                  {/* Sub-text (e.g. total amount) */}
                  {isCompleted && detail.sub && (
                    <p className="text-xs font-semibold text-emerald-600 mt-0.5">{detail.sub}</p>
                  )}

                  {/* Badges row */}
                  {isCompleted && detail.badges.length > 0 && (
                    <div className="flex flex-wrap gap-1 mt-1.5">
                      {detail.badges.map((b, bi) => (
                        <span
                          key={bi}
                          className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ${BADGE_CLASSES[b.color]}`}
                        >
                          {b.label}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>

        {/* Done footer */}
        {isDone && (
          <div className="mx-5 mb-5 flex items-center justify-center gap-2 rounded-xl bg-emerald-50 border border-emerald-100 py-3 animate-in fade-in zoom-in-95 duration-500">
            <Check className="h-4 w-4 text-emerald-600" strokeWidth={2.5} />
            <span className="text-sm font-bold text-emerald-700">Listo</span>
          </div>
        )}

        {/* Separator if no done yet */}
        {!isDone && <div className="h-4" />}
      </div>

      {/* Reconciliation card */}
      {reconciliationQuestions.length > 0 && (
        <div className="relative mt-4 w-full max-w-sm mx-4 sm:mx-auto rounded-2xl bg-white shadow-xl overflow-hidden">
          <div className="p-5">
            <ReconciliationQuestions
              questions={reconciliationQuestions}
              onSubmit={onReconciliationSubmit}
              loading={reconciliationLoading}
            />
          </div>
        </div>
      )}

      {/* Hidden pipeline engine */}
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
    </div>
  );
}
