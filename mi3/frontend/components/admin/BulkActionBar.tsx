'use client';

import { useState, useRef, useEffect, useCallback } from 'react';
import {
  X,
  DollarSign,
  Trash2,
  Plus,
  Minus,
  Send,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface BulkActionBarProps {
  selectedCount: number;
  onClear: () => void;
  onPriceAdjust: (amount: number) => void;
  onDelete: () => void;
}

export default function BulkActionBar({
  selectedCount,
  onClear,
  onPriceAdjust,
  onDelete,
}: BulkActionBarProps) {
  const [customAmount, setCustomAmount] = useState('');
  const [showConfirm, setShowConfirm] = useState(false);
  const [visible, setVisible] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  // Slide-up animation
  useEffect(() => {
    if (selectedCount > 0) {
      requestAnimationFrame(() => setVisible(true));
    } else {
      setVisible(false);
    }
  }, [selectedCount]);

  const handleCustomSubmit = useCallback(() => {
    const parsed = parseInt(customAmount, 10);
    if (!isNaN(parsed) && parsed !== 0) {
      onPriceAdjust(parsed);
      setCustomAmount('');
    }
  }, [customAmount, onPriceAdjust]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLInputElement>) => {
      if (e.key === 'Enter') handleCustomSubmit();
    },
    [handleCustomSubmit],
  );

  const confirmDelete = useCallback(() => {
    onDelete();
    setShowConfirm(false);
  }, [onDelete]);

  if (selectedCount <= 0) return null;

  return (
    <>
      {/* Confirmation dialog */}
      {showConfirm && (
        <div
          className="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
          role="dialog"
          aria-modal="true"
          aria-label="Confirmar eliminación"
        >
          <div className="bg-white rounded-xl shadow-2xl max-w-sm w-full p-5">
            <h3 className="text-lg font-bold text-gray-900 mb-2">
              ¿Eliminar {selectedCount} producto{selectedCount > 1 ? 's' : ''}?
            </h3>
            <p className="text-sm text-gray-500 mb-5">
              Esta acción es permanente. Los productos y sus recetas asociadas se eliminarán de la base de datos.
            </p>
            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => setShowConfirm(false)}
                className="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors min-h-[44px]"
              >
                Cancelar
              </button>
              <button
                type="button"
                onClick={confirmDelete}
                className="flex-1 px-4 py-2.5 rounded-lg bg-red-600 text-sm font-medium text-white hover:bg-red-700 transition-colors min-h-[44px]"
              >
                Eliminar
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Bulk action bar */}
      <div
        className={cn(
          'fixed bottom-0 left-0 right-0 z-50',
          'transition-transform duration-300 ease-out',
          visible ? 'translate-y-0' : 'translate-y-full',
        )}
        role="toolbar"
        aria-label={`Acciones para ${selectedCount} producto${selectedCount > 1 ? 's' : ''} seleccionado${selectedCount > 1 ? 's' : ''}`}
      >
        <div
          className={cn(
            'mx-auto max-w-4xl',
            'bg-gray-900 text-white rounded-t-xl shadow-2xl',
            'px-3 py-3 sm:px-5 sm:py-3',
            'pb-[calc(0.75rem+env(safe-area-inset-bottom))]',
          )}
        >
          {/* Mobile layout: 2 rows */}
          <div className="sm:hidden space-y-2">
            {/* Row 1: count + clear */}
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium">
                {selectedCount} seleccionado{selectedCount > 1 ? 's' : ''}
              </span>
              <button
                type="button"
                onClick={onClear}
                className="p-1.5 rounded-lg hover:bg-white/10 transition-colors min-w-[44px] min-h-[44px] flex items-center justify-center"
                aria-label="Limpiar selección"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
            {/* Row 2: actions */}
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => onPriceAdjust(100)}
                className="flex-1 flex items-center justify-center gap-1 px-2 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 text-sm font-medium transition-colors min-h-[44px]"
                aria-label="Subir precio $100"
              >
                <Plus className="w-4 h-4" />100
              </button>
              <button
                type="button"
                onClick={() => onPriceAdjust(-100)}
                className="flex-1 flex items-center justify-center gap-1 px-2 py-2.5 rounded-lg bg-orange-600 hover:bg-orange-700 text-sm font-medium transition-colors min-h-[44px]"
                aria-label="Bajar precio $100"
              >
                <Minus className="w-4 h-4" />100
              </button>
              <div className="flex items-center gap-1 flex-1">
                <div className="relative flex-1">
                  <DollarSign className="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-gray-400" />
                  <input
                    ref={inputRef}
                    type="number"
                    value={customAmount}
                    onChange={(e) => setCustomAmount(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="±"
                    className="w-full pl-6 pr-1 py-2.5 rounded-lg bg-white/10 border border-white/20 text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-white/40 min-h-[44px]"
                    aria-label="Monto personalizado"
                  />
                </div>
                <button
                  type="button"
                  onClick={handleCustomSubmit}
                  disabled={!customAmount || parseInt(customAmount, 10) === 0}
                  className="p-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors min-w-[44px] min-h-[44px] flex items-center justify-center"
                  aria-label="Aplicar monto"
                >
                  <Send className="w-4 h-4" />
                </button>
              </div>
              <button
                type="button"
                onClick={() => setShowConfirm(true)}
                className="p-2.5 rounded-lg bg-red-600 hover:bg-red-700 transition-colors min-w-[44px] min-h-[44px] flex items-center justify-center"
                aria-label="Eliminar seleccionados"
              >
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          </div>

          {/* Desktop layout: single row */}
          <div className="hidden sm:flex items-center gap-2">
            <span className="bg-white/20 text-sm font-semibold px-3 py-1.5 rounded-lg whitespace-nowrap">
              {selectedCount} seleccionado{selectedCount > 1 ? 's' : ''}
            </span>
            <button
              type="button"
              onClick={onClear}
              className="p-2 rounded-lg hover:bg-white/10 transition-colors min-w-[44px] min-h-[44px] flex items-center justify-center"
              aria-label="Limpiar selección"
            >
              <X className="w-4 h-4" />
            </button>

            <div className="w-px h-6 bg-white/20" />

            <button
              type="button"
              onClick={() => onPriceAdjust(100)}
              className="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-sm font-medium transition-colors min-h-[44px]"
              aria-label="Subir precio $100"
            >
              <Plus className="w-4 h-4" />$100
            </button>
            <button
              type="button"
              onClick={() => onPriceAdjust(-100)}
              className="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-orange-600 hover:bg-orange-700 text-sm font-medium transition-colors min-h-[44px]"
              aria-label="Bajar precio $100"
            >
              <Minus className="w-4 h-4" />$100
            </button>

            <div className="flex items-center gap-1">
              <div className="relative">
                <DollarSign className="absolute left-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                <input
                  type="number"
                  value={customAmount}
                  onChange={(e) => setCustomAmount(e.target.value)}
                  onKeyDown={handleKeyDown}
                  placeholder="±"
                  className="w-24 pl-7 pr-2 py-2 rounded-lg bg-white/10 border border-white/20 text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-white/40 min-h-[44px]"
                  aria-label="Monto personalizado"
                />
              </div>
              <button
                type="button"
                onClick={handleCustomSubmit}
                disabled={!customAmount || parseInt(customAmount, 10) === 0}
                className="p-2 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors min-w-[44px] min-h-[44px] flex items-center justify-center"
                aria-label="Aplicar monto personalizado"
              >
                <Send className="w-4 h-4" />
              </button>
            </div>

            <div className="w-px h-6 bg-white/20" />

            <button
              type="button"
              onClick={() => setShowConfirm(true)}
              className="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-sm font-medium transition-colors min-h-[44px]"
              aria-label="Eliminar seleccionados"
            >
              <Trash2 className="w-4 h-4" />
              Eliminar
            </button>
          </div>
        </div>
      </div>
    </>
  );
}
