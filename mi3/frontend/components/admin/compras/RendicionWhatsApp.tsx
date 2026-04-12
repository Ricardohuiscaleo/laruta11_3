'use client';

import { useState } from 'react';
import { X, Copy, Check } from 'lucide-react';
import { formatearPesosCLP, formatearFecha } from '@/lib/compras-utils';
import type { Compra } from '@/types/compras';

interface RendicionWhatsAppProps {
  compras: Compra[];
  onClose: () => void;
}

export default function RendicionWhatsApp({ compras, onClose }: RendicionWhatsAppProps) {
  const [montoTransferencia, setMontoTransferencia] = useState('');
  const [saldoAnterior, setSaldoAnterior] = useState('');
  const [copied, setCopied] = useState(false);

  const totalCompras = compras.reduce((sum, c) => sum + c.monto_total, 0);

  const generateText = () => {
    const lines: string[] = ['*Rendición de Compras*', ''];
    compras.forEach(c => {
      lines.push(`📌 ${c.proveedor} — ${formatearPesosCLP(c.monto_total)} (${formatearFecha(c.fecha_compra)})`);
    });
    lines.push('', `*Total compras:* ${formatearPesosCLP(totalCompras)}`);
    if (montoTransferencia) {
      lines.push(`*Transferencia:* ${formatearPesosCLP(Number(montoTransferencia))}`);
    }
    if (saldoAnterior) {
      const saldoNum = Number(saldoAnterior);
      const saldoFinal = saldoNum + Number(montoTransferencia || 0) - totalCompras;
      lines.push(`*Saldo anterior:* ${formatearPesosCLP(saldoNum)}`);
      lines.push(`*Saldo actual:* ${formatearPesosCLP(saldoFinal)}`);
    }
    return lines.join('\n');
  };

  const handleCopy = async () => {
    await navigator.clipboard.writeText(generateText());
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
      <div className="w-full max-w-md rounded-xl bg-white shadow-xl" onClick={e => e.stopPropagation()}>
        <div className="flex items-center justify-between border-b px-4 py-3">
          <h3 className="text-base font-semibold text-gray-900">Rendición WhatsApp</h3>
          <button onClick={onClose} className="rounded-full p-1 hover:bg-gray-100"><X className="h-5 w-5" /></button>
        </div>

        <div className="space-y-3 p-4">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-600">Monto transferencia</label>
              <input type="number" value={montoTransferencia} onChange={e => setMontoTransferencia(e.target.value)}
                placeholder="0" className="w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
            <div>
              <label className="mb-1 block text-xs font-medium text-gray-600">Saldo anterior</label>
              <input type="number" value={saldoAnterior} onChange={e => setSaldoAnterior(e.target.value)}
                placeholder="0" className="w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
          </div>

          <div className="rounded-lg bg-gray-50 p-3">
            <pre className="whitespace-pre-wrap text-sm text-gray-700">{generateText()}</pre>
          </div>

          <button onClick={handleCopy}
            className="flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 py-2.5 text-sm font-medium text-white hover:bg-green-700">
            {copied ? <><Check className="h-4 w-4" /> Copiado</> : <><Copy className="h-4 w-4" /> Copiar al portapapeles</>}
          </button>
        </div>
      </div>
    </div>
  );
}
