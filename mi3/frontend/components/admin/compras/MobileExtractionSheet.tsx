'use client';

import { useState, useEffect } from 'react';
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

const STEPS = [
  { id: 'upload', label: 'Subida', icon: Camera },
  { id: 'vision', label: 'Visión', icon: Eye },
  { id: 'analisis', label: 'Análisis', icon: Brain },
  { id: 'validacion', label: 'Check', icon: ShieldCheck },
  { id: 'reconciliacion', label: 'Ajuste', icon: Scale },
];

export default function MobileExtractionSheet({
  open, tempKey, uploading,
  reconciliationQuestions, reconciliationLoading,
  onResult, onError, onReconciliationNeeded, onReconciliationSubmit, onClose,
}: Props) {
  const [currentStep, setCurrentStep] = useState(0);

  useEffect(() => {
    if (uploading) setCurrentStep(0);
    else if (tempKey) setCurrentStep(1);
  }, [uploading, tempKey]);

  // Auto-close when done
  useEffect(() => {
    if (!uploading && !tempKey && open && currentStep > 0 && reconciliationQuestions.length === 0) {
      const timer = setTimeout(() => { setCurrentStep(0); onClose(); }, 800);
      return () => clearTimeout(timer);
    }
  }, [uploading, tempKey, open, currentStep, reconciliationQuestions.length, onClose]);

  if (!open) return null;

  const handleResult = (data: ExtractionResult, sug?: ExtractionResult['sugerencias']) => {
    setCurrentStep(5);
    onResult(data, sug);
  };

  const isDone = !uploading && !tempKey && currentStep >= 5;

  return (
    <div className="fixed inset-0 z-50 flex flex-col items-center justify-start" role="dialog" aria-modal="true">
      {/* Backdrop blur */}
      <div className="absolute inset-0 bg-black/40 backdrop-blur-md" />

      {/* Glass card */}
      <div className="relative mt-20 mx-4 w-[calc(100%-2rem)] max-w-sm rounded-2xl border border-white/20 bg-white/10 backdrop-blur-xl p-5 shadow-2xl">
        <div className="flex items-center justify-between mb-3">
          {STEPS.map((step, i) => {
            const Icon = step.icon;
            const isActive = i === currentStep;
            const isCompleted = i < currentStep;
            return (
              <div key={step.id} className="flex flex-col items-center gap-1 flex-1">
                <div className={[
                  'flex items-center justify-center h-9 w-9 rounded-full transition-all duration-500',
                  isCompleted ? 'bg-green-500' : '',
                  isActive ? 'bg-red-500 scale-110 shadow-lg shadow-red-500/40' : '',
                  !isCompleted && !isActive ? 'bg-white/10' : '',
                ].join(' ')}>
                  {isCompleted ? <Check className="h-4 w-4 text-white" /> : isActive ? <Icon className="h-4 w-4 text-white animate-pulse" /> : <Icon className="h-3.5 w-3.5 text-white/25" />}
                </div>
                <span className={['text-[9px] font-medium', isCompleted ? 'text-green-300' : isActive ? 'text-white' : 'text-white/25'].join(' ')}>{step.label}</span>
              </div>
            );
          })}
        </div>
        <div className="h-0.5 rounded-full bg-white/10 overflow-hidden">
          <div className="h-full rounded-full bg-gradient-to-r from-red-500 to-green-500 transition-all duration-700" style={{ width: `${Math.min(100, (currentStep / 5) * 100)}%` }} />
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
          <ExtractionPipeline tempKey={tempKey} onResult={handleResult} onError={onError} onReconciliationNeeded={onReconciliationNeeded} />
        </div>
      )}

      {reconciliationQuestions.length > 0 && (
        <div className="relative mt-4 mx-4 w-[calc(100%-2rem)] max-w-sm rounded-2xl border border-white/20 bg-white/10 backdrop-blur-xl p-4 shadow-2xl">
          <ReconciliationQuestions questions={reconciliationQuestions} onSubmit={onReconciliationSubmit} loading={reconciliationLoading} />
        </div>
      )}
    </div>
  );
}
