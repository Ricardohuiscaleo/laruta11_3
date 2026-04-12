'use client';

import { useState, useEffect } from 'react';
import { Plus, Trash2, Copy, Check } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP } from '@/lib/compras-utils';
import type { Kpi } from '@/types/compras';
import ItemSearch from './ItemSearch';

interface ProyeccionItem {
  nombre: string;
  cantidad: number;
  unidad: string;
  precio: number;
}

export default function ProyeccionCompras() {
  const [items, setItems] = useState<ProyeccionItem[]>([]);
  const [saldo, setSaldo] = useState<number | null>(null);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    comprasApi.get<{ success: boolean; data: Kpi }>('/kpis').then(r => setSaldo(r.data?.saldo_disponible ?? null)).catch(() => {});
  }, []);

  const addItem = (item: { nombre: string; unidad: string; ultimo_precio: number | null }) => {
    setItems(prev => [...prev, {
      nombre: item.nombre,
      cantidad: 1,
      unidad: item.unidad,
      precio: item.ultimo_precio ?? 0,
    }]);
  };

  const updateItem = (idx: number, field: keyof ProyeccionItem, value: string | number) => {
    setItems(prev => {
      const next = [...prev];
      next[idx] = { ...next[idx], [field]: value };
      return next;
    });
  };

  const removeItem = (idx: number) => setItems(prev => prev.filter((_, i) => i !== idx));

  const totalProyectado = items.reduce((sum, it) => sum + (Number(it.cantidad) || 0) * (Number(it.precio) || 0), 0);

  const generateWhatsApp = () => {
    const lines = ['*Proyección de Compras*', ''];
    items.forEach(it => {
      const sub = (Number(it.cantidad) || 0) * (Number(it.precio) || 0);
      lines.push(`• ${it.nombre}: ${it.cantidad} ${it.unidad} × ${formatearPesosCLP(Number(it.precio))} = ${formatearPesosCLP(sub)}`);
    });
    lines.push('', `*Total proyectado:* ${formatearPesosCLP(totalProyectado)}`);
    if (saldo !== null) {
      lines.push(`*Saldo disponible:* ${formatearPesosCLP(saldo)}`);
      const diff = saldo - totalProyectado;
      lines.push(`*${diff >= 0 ? 'Sobrante' : 'Faltante'}:* ${formatearPesosCLP(Math.abs(diff))}`);
    }
    return lines.join('\n');
  };

  const handleCopy = async () => {
    await navigator.clipboard.writeText(generateWhatsApp());
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div className="space-y-3">
      <div className="rounded-xl border bg-white p-4 shadow-sm">
        <h3 className="mb-3 text-sm font-semibold text-gray-700">Agregar ítems a la proyección</h3>
        <ItemSearch onSelect={addItem as any} />
      </div>

      {items.length > 0 && (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <div className="space-y-2">
            {items.map((item, idx) => (
              <div key={idx} className="flex flex-wrap items-center gap-2 rounded-lg border bg-gray-50 p-2 text-sm">
                <span className="min-w-[100px] font-medium text-gray-800">{item.nombre}</span>
                <input type="number" value={item.cantidad} min={0.01} step="any"
                  onChange={e => updateItem(idx, 'cantidad', parseFloat(e.target.value) || 0)}
                  className="w-16 rounded border px-2 py-1 text-center" />
                <span className="text-xs text-gray-500">{item.unidad}</span>
                <input type="number" value={item.precio} min={0} step="any"
                  onChange={e => updateItem(idx, 'precio', parseFloat(e.target.value) || 0)}
                  className="w-24 rounded border px-2 py-1 text-right" placeholder="Precio" />
                <span className="ml-auto text-sm font-medium">
                  {formatearPesosCLP((Number(item.cantidad) || 0) * (Number(item.precio) || 0))}
                </span>
                <button onClick={() => removeItem(idx)} className="text-red-400 hover:text-red-600">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Summary */}
      <div className="rounded-xl border bg-white p-4 shadow-sm">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-lg font-bold text-gray-900">Total: {formatearPesosCLP(totalProyectado)}</p>
            {saldo !== null && (
              <p className={`text-sm ${totalProyectado > saldo ? 'text-red-600' : 'text-green-600'}`}>
                Saldo disponible: {formatearPesosCLP(saldo)}
                {totalProyectado > saldo
                  ? ` (faltan ${formatearPesosCLP(totalProyectado - saldo)})`
                  : ` (sobran ${formatearPesosCLP(saldo - totalProyectado)})`}
              </p>
            )}
          </div>
          {items.length > 0 && (
            <button onClick={handleCopy}
              className="flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
              {copied ? <><Check className="h-4 w-4" /> Copiado</> : <><Copy className="h-4 w-4" /> WhatsApp</>}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
