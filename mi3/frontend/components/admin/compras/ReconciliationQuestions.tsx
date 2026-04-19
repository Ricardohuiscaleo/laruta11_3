'use client';

import { useState } from 'react';
import { Scale, Check } from 'lucide-react';
import type { ReconciliationQuestion } from './ExtractionPipeline';

interface ReconciliationQuestionsProps {
  questions: ReconciliationQuestion[];
  onSubmit: (responses: Record<string, string | number>) => void;
  loading?: boolean;
}

export default function ReconciliationQuestions({ questions, onSubmit, loading = false }: ReconciliationQuestionsProps) {
  const [selected, setSelected] = useState<Record<string, string | number>>({});

  const allAnswered = questions.every(q => selected[q.campo] !== undefined);

  const handleSelect = (campo: string, valor: string | number) => {
    setSelected(prev => ({ ...prev, [campo]: valor }));
  };

  const handleSubmit = () => {
    if (allAnswered) {
      onSubmit(selected);
    }
  };

  return (
    <div className="rounded-xl border border-blue-200 bg-blue-50/50 p-4 space-y-3" role="region" aria-label="Preguntas de reconciliación">
      <div className="flex items-center gap-2">
        <Scale className="h-4 w-4 text-blue-600" />
        <p className="text-sm font-medium text-blue-800">La IA necesita tu ayuda</p>
      </div>

      <div className="space-y-3">
        {questions.map((q) => (
          <div key={q.campo} className="rounded-lg border border-blue-100 bg-white p-3 space-y-2">
            <p className="text-sm text-gray-700">{q.descripcion}</p>
            <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={q.descripcion}>
              {q.opciones.map((opt) => {
                const isSelected = selected[q.campo] === opt.valor;
                return (
                  <button
                    key={String(opt.valor)}
                    type="button"
                    role="radio"
                    aria-checked={isSelected}
                    onClick={() => handleSelect(q.campo, opt.valor)}
                    disabled={loading}
                    className={`rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors ${
                      isSelected
                        ? 'border-blue-500 bg-blue-100 text-blue-800'
                        : 'border-gray-200 bg-gray-50 text-gray-700 hover:border-blue-300 hover:bg-blue-50'
                    } disabled:opacity-50`}
                  >
                    {isSelected && <Check className="inline h-3 w-3 mr-1" />}
                    {opt.etiqueta}
                  </button>
                );
              })}
            </div>
          </div>
        ))}
      </div>

      <button
        type="button"
        onClick={handleSubmit}
        disabled={!allAnswered || loading}
        className="w-full rounded-lg bg-blue-600 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
      >
        {loading ? 'Aplicando...' : 'Confirmar respuestas'}
      </button>
    </div>
  );
}
