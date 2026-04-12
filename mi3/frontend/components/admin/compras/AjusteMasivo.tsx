'use client';

import { useState } from 'react';
import { Eye, Check, AlertCircle } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';

interface PreviewItem {
  nombre: string;
  stock_actual: number;
  nuevo_stock: number;
  diferencia: number;
}

interface PreviewError {
  line: number;
  text: string;
  error: string;
}

interface PreviewResponse {
  valid: PreviewItem[];
  errors: PreviewError[];
}

export default function AjusteMasivo() {
  const [markdown, setMarkdown] = useState('');
  const [preview, setPreview] = useState<PreviewResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [applying, setApplying] = useState(false);
  const [applied, setApplied] = useState(false);

  const handlePreview = async () => {
    if (!markdown.trim()) return;
    setLoading(true);
    setApplied(false);
    try {
      const res = await comprasApi.post<PreviewResponse>('/stock/preview-ajuste', { texto: markdown });
      setPreview(res);
    } catch { alert('Error al previsualizar'); }
    setLoading(false);
  };

  const handleApply = async () => {
    if (!preview || preview.valid.length === 0) return;
    setApplying(true);
    try {
      await comprasApi.post('/stock/ajuste-masivo', { texto: markdown });
      setApplied(true);
      setPreview(null);
      setMarkdown('');
    } catch { alert('Error al aplicar ajuste'); }
    setApplying(false);
  };

  return (
    <div className="space-y-3">
      <div className="rounded-xl border bg-white p-4 shadow-sm">
        <h3 className="mb-2 text-sm font-semibold text-gray-700">Ajuste Masivo de Stock</h3>
        <p className="mb-3 text-xs text-gray-500">
          Formato: <code className="rounded bg-gray-100 px-1">- Nombre: cantidad unidad</code>
        </p>
        <textarea
          value={markdown}
          onChange={e => setMarkdown(e.target.value)}
          rows={8}
          placeholder={`# Ajuste Stock\n\n- Tomate: 5 kg\n- Lechuga: 3 unidad\n- Coca-Cola 350ml: 48 unidad`}
          className="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm focus:border-mi3-500 focus:outline-none focus:ring-1 focus:ring-mi3-500"
        />
        <button onClick={handlePreview} disabled={loading || !markdown.trim()}
          className="mt-2 flex items-center gap-1.5 rounded-lg bg-mi3-500 px-4 py-2 text-sm font-medium text-white hover:bg-mi3-600 disabled:opacity-50">
          <Eye className="h-4 w-4" /> {loading ? 'Procesando...' : 'Previsualizar'}
        </button>
      </div>

      {applied && (
        <div className="flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700">
          <Check className="h-4 w-4" /> Ajuste aplicado exitosamente
        </div>
      )}

      {preview && (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          {preview.errors.length > 0 && (
            <div className="mb-3 space-y-1">
              {preview.errors.map((err, i) => (
                <div key={i} className="flex items-start gap-2 rounded-lg bg-red-50 p-2 text-xs text-red-700">
                  <AlertCircle className="mt-0.5 h-3.5 w-3.5 flex-shrink-0" />
                  <span>Línea {err.line}: &quot;{err.text}&quot; — {err.error}</span>
                </div>
              ))}
            </div>
          )}

          {preview.valid.length > 0 && (
            <>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-left text-xs text-gray-500">
                      <th className="pb-2 pr-3">Ítem</th>
                      <th className="pb-2 pr-3 text-right">Stock actual</th>
                      <th className="pb-2 pr-3 text-right">Nuevo stock</th>
                      <th className="pb-2 text-right">Diferencia</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {preview.valid.map((item, i) => (
                      <tr key={i}>
                        <td className="py-2 pr-3 font-medium">{item.nombre}</td>
                        <td className="py-2 pr-3 text-right">{item.stock_actual}</td>
                        <td className="py-2 pr-3 text-right">{item.nuevo_stock}</td>
                        <td className={`py-2 text-right font-medium ${item.diferencia >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                          {item.diferencia >= 0 ? '+' : ''}{item.diferencia}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <button onClick={handleApply} disabled={applying}
                className="mt-3 flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                <Check className="h-4 w-4" /> {applying ? 'Aplicando...' : 'Aplicar ajuste'}
              </button>
            </>
          )}
        </div>
      )}
    </div>
  );
}
