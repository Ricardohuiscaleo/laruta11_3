'use client';

import { X, Loader2, Check } from 'lucide-react';
import ExtractionPipeline from './ExtractionPipeline';
import ReconciliationQuestions from './ReconciliationQuestions';
import type { ReconciliationQuestion } from './ExtractionPipeline';
import type { ExtractionResult } from '@/types/compras';

interface MobileExtractionSheetProps {
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

export default function MobileExtractionSheet({
  open, tempKey, tempUrl, uploading, uploadProgress,
  reconciliationQuestions, reconciliationLoading,
  onResult, onError, onReconciliationNeeded, onReconciliationSubmit, onClose,
}: MobileExtractionSheetProps) {
  if (!open) return null;

  const isDone = !uploading && !tempKey && reconciliationQuestions.length === 0;

  return (
    <div className="md:hidden fixed inset-0 z-50 flex flex-col bg-white" role="dialog" aria-modal="true" aria-label="Extracción IA">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b bg-amber-50 shrink-0">
        <div className="flex items-center gap-2">
          {uploading && <Loader2 className="h-4 w-4 animate-spin text-amber-600" />}
          {tempKey && !uploading && <Loader2 className="h-4 w-4 animate-spin text-blue-600" />}
          {isDone && <Check className="h-4 w-4 text-green-600" />}
          <span className="text-sm font-semibold text-gray-800">
            {uploading ? 'Subiendo imagen...' : tempKey ? 'Analizando con IA...' : 'Extracción completa'}
          </span>
        </div>
        <button onClick={onClose} className="rounded-full p-1.5 hover:bg-amber-100" aria-label="Cerrar">
          <X className="h-5 w-5 text-gray-500" />
        </button>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto p-4 space-y-4">
        {tempUrl && (
          <div className="flex items-center gap-3 rounded-lg bg-gray-50 p-3">
            <img src={tempUrl} alt="" className="h-20 w-20 rounded-lg object-cover border" />
            <div>
              <p className="text-sm font-medium text-gray-700">{uploading ? 'Subiendo...' : 'Imagen subida'}</p>
              {uploadProgress && <p className="text-xs text-gray-500">{uploadProgress}</p>}
            </div>
          </div>
        )}

        {uploading && (
          <div className="flex flex-col items-center gap-3 py-8">
            <Loader2 className="h-10 w-10 animate-spin text-amber-500" />
            <p className="text-sm text-gray-600">{uploadProgress || 'Subiendo foto...'}</p>
          </div>
        )}

        {tempKey && !uploading && (
          <ExtractionPipeline
            tempKey={tempKey}
            onResult={onResult}
            onError={onError}
            onReconciliationNeeded={onReconciliationNeeded}
          />
        )}

        {reconciliationQuestions.length > 0 && (
          <ReconciliationQuestions
            questions={reconciliationQuestions}
            onSubmit={onReconciliationSubmit}
            loading={reconciliationLoading}
          />
        )}

        {isDone && (
          <div className="flex flex-col items-center gap-3 py-8">
            <div className="h-16 w-16 rounded-full bg-green-100 flex items-center justify-center">
              <Check className="h-8 w-8 text-green-600" />
            </div>
            <p className="text-sm font-medium text-green-700">Datos extraídos correctamente</p>
            <p className="text-xs text-gray-500">Revisa y ajusta los datos abajo</p>
            <button onClick={onClose} className="mt-2 rounded-lg bg-green-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-green-700">
              Ver resultado
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
