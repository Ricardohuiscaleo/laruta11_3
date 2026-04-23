'use client';

import { Loader2, X } from 'lucide-react';
import type { EmailEstado } from '@/types/admin';

interface EmailPreviewModalProps {
  open: boolean;
  onClose: () => void;
  onConfirm: () => void;
  html: string;
  emailTipo: EmailEstado;
  recipientEmail: string;
  sending: boolean;
}

const TIPO_CONFIG: Record<EmailEstado, { label: string; bg: string; text: string }> = {
  sin_deuda: { label: 'Sin Deuda', bg: 'bg-green-100', text: 'text-green-700' },
  recordatorio: { label: 'Recordatorio', bg: 'bg-orange-100', text: 'text-orange-700' },
  urgente: { label: 'Urgente', bg: 'bg-red-100', text: 'text-red-700' },
  moroso: { label: 'Moroso', bg: 'bg-red-200', text: 'text-red-800' },
};

export default function EmailPreviewModal({
  open,
  onClose,
  onConfirm,
  html,
  emailTipo,
  recipientEmail,
  sending,
}: EmailPreviewModalProps) {
  if (!open) return null;

  const tipo = TIPO_CONFIG[emailTipo] ?? TIPO_CONFIG.recordatorio;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />

      {/* Modal */}
      <div className="relative z-10 mx-4 flex max-h-[90vh] w-full max-w-2xl flex-col rounded-xl bg-white shadow-2xl">
        {/* Header */}
        <div className="flex items-center justify-between border-b px-5 py-4">
          <div className="flex items-center gap-3">
            <h3 className="text-base font-semibold text-gray-900">Preview Email</h3>
            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${tipo.bg} ${tipo.text}`}>
              {tipo.label}
            </span>
          </div>
          <button onClick={onClose} className="rounded-lg p-1 hover:bg-gray-100" aria-label="Cerrar">
            <X className="h-5 w-5 text-gray-400" />
          </button>
        </div>

        {/* Recipient */}
        <div className="border-b bg-gray-50 px-5 py-2.5">
          <p className="text-sm text-gray-600">
            <span className="font-medium">Para:</span> {recipientEmail}
          </p>
        </div>

        {/* Email preview iframe */}
        <div className="flex-1 overflow-auto p-4">
          <iframe
            srcDoc={html}
            title="Email preview"
            className="h-[400px] w-full rounded-lg border"
            sandbox="allow-same-origin"
          />
        </div>

        {/* Footer buttons */}
        <div className="flex items-center justify-end gap-3 border-t px-5 py-4">
          <button
            onClick={onClose}
            disabled={sending}
            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
          >
            Cancelar
          </button>
          <button
            onClick={onConfirm}
            disabled={sending}
            className="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
          >
            {sending && <Loader2 className="h-4 w-4 animate-spin" />}
            {sending ? 'Enviando...' : 'Enviar Email'}
          </button>
        </div>
      </div>
    </div>
  );
}
