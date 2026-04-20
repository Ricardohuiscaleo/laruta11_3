'use client';

import { useState, useEffect } from 'react';
import { X, Loader2, Check, Eye, Brain, ShieldCheck, Scale, Camera } from 'lucide-react';
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

const STEPS = [
  { id: 'upload', label: 'Subida', icon: Camera },
  { id: 'vision', label: 'Visión', icon: Eye },
  { id: 'analisis', label: 'Análisis', icon: Brain },
  { id: 'validacion', label: 'Check', icon: ShieldCheck },
  { id: 'reconciliacion', label: 'Ajuste', icon: Scale },
];

export default function MobileExtractionSheet({
  open, tempKey, tempUrl, uploading, uploadProgress,
  reconciliationQuestions, reconciliationLoading,
  onResult, onError, onReconciliationNeeded, onReconciliationSubmit, onClose,
}: Props) {
  const [currentStep, setCurrentStep] = useState(0);
  const [done, setDone] = useState(false);

  useEffect(() => {
    if (uploading) { setCurrentStep(0); setDone(false); }
    else if (tempKey) { setCurrentStep(1); setDone(false); }
  }, [uploading, tempKey]);

  useEffect(() => {
    if (!uploading && !tempKey && open && currentStep > 0) {
      setDone(true);
      setCurrentStep(5);
    }
  }, [uploading, tempKey, open, currentStep]);

  if (!open) return null;

  const handleResult = (data: ExtractionResult, sug?: ExtractionResult['sugerencias']) => {
    setDone(true);
    setCurrentStep(5);
    onResult(data, sug);
  };

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900" role="dialog" aria-modal="true">
      {/* Header */}
      <div className="flex items-center justify-between px-4 pt-3 pb-2 shrink-0">
        <span className="text-xs font-medium text-slate-400 uppercase tracking-wider">Extracción IA</span>
        <button onClick={onClose} className="rounded-full p-2 hover:bg-white/10 transition-colors" aria-label="Cerrar">
          <X className="h-5 w-5 text-slate-400" />
        </button>
      </div>

      {/* Image preview */}
      {tempUrl && (
        <div className="mx-4 mb-4 shrink-0">
          <div className="relative overflow-hidden rounded-2xl border border-white/10">
            <img src={tempUrl} alt="" className="w-full h-36 object-cover" />
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
            {!done && (
              <div className="absolute bottom-3 left-3 flex items-center gap-2">
                <div className="h-2 w-2 rounded-full bg-green-400 animate-pulse" />
                <span className="text-xs font-medium text-white/90">Procesando...</span>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Step indicators */}
      <div className="mx-4 mb-5 shrink-0">
        <div className="flex items-center justify-between">
          {STEPS.map((step, i) => {
            const Icon = step.icon;
            const isActive = i === currentStep;
            const isCompleted = i < currentStep;
            const isPending = i > currentStep;
            return (
              <div key={step.id} className="flex flex-col items-center gap-1.5 flex-1">
                <div className={[
                  'relative flex items-center justify-center h-10 w-10 rounded-full transition-all duration-500',
                  isCompleted ? 'bg-green-500' : '',
                  isActive ? 'bg-red-500 scale-110 shadow-lg shadow-red-500/30' : '',
                  isPending ? 'bg-white/10' : '',
                ].join(' ')}>
                  {isCompleted ? (
                    <Check className="h-5 w-5 text-white" />
                  ) : isActive ? (
                    <Icon className="h-5 w-5 text-white animate-pulse" />
                  ) : (
                    <Icon className="h-4 w-4 text-white/30" />
                  )}
                </div>
                <span className={[
                  'text-[10px] font-medium transition-colors duration-300',
                  isCompleted ? 'text-green-400' : isActive ? 'text-white' : 'text-white/30',
                ].join(' ')}>
                  {step.label}
                </span>
              </div>
            );
          })}
        </div>
        {/* Progress bar */}
        <div className="mt-3 h-1 rounded-full bg-white/10 overflow-hidden">
          <div
            className="h-full rounded-full bg-gradient-to-r from-red-500 to-green-500 transition-all duration-700 ease-out"
            style={{ width: `${Math.min(100, (currentStep / 5) * 100)}%` }}
          />
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto px-4 pb-8">
        {uploading && (
          <div className="flex flex-col items-center gap-4 pt-8">
            <Loader2 className="h-12 w-12 animate-spin text-red-400" />
            <p className="text-sm text-white/70">{uploadProgress || 'Subiendo imagen...'}</p>
          </div>
        )}

        {tempKey && !uploading && (
          <div className="rounded-2xl bg-white/5 border border-white/10 p-4">
            <ExtractionPipeline
              tempKey={tempKey}
              onResult={handleResult}
              onError={onError}
              onReconciliationNeeded={onReconciliationNeeded}
            />
          </div>
        )}

        {reconciliationQuestions.length > 0 && (
          <div className="mt-4 rounded-2xl bg-white/5 border border-white/10 p-4">
            <ReconciliationQuestions
              questions={reconciliationQuestions}
              onSubmit={onReconciliationSubmit}
              loading={reconciliationLoading}
            />
          </div>
        )}

        {done && reconciliationQuestions.length === 0 && (
          <div className="flex flex-col items-center gap-5 pt-6">
            <div className="h-20 w-20 rounded-full bg-green-500/20 flex items-center justify-center">
              <Check className="h-10 w-10 text-green-400" />
            </div>
            <div className="text-center">
              <p className="text-lg font-semibold text-white">Listo</p>
              <p className="text-sm text-white/50 mt-1">Datos extraídos correctamente</p>
            </div>
            <button
              onClick={() => { setDone(false); setCurrentStep(0); onClose(); }}
              className="mt-2 w-full max-w-[280px] rounded-xl bg-green-500 py-3.5 text-sm font-semibold text-white active:bg-green-600 transition-colors"
            >
              Ver resultado
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
