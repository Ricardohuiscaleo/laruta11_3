'use client';

import { useState } from 'react';
import { X, Trash2, Upload, ZoomIn } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP, formatearFecha } from '@/lib/compras-utils';
import type { Compra } from '@/types/compras';

const METODO_LABELS: Record<string, string> = {
  cash: 'Efectivo', transfer: 'Transferencia', credit: 'Crédito', debit: 'Débito',
};

interface DetalleCompraProps {
  compra: Compra;
  onClose: () => void;
  onDeleted: () => void;
}

export default function DetalleCompra({ compra, onClose, onDeleted }: DetalleCompraProps) {
  const [deleting, setDeleting] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [previewImg, setPreviewImg] = useState<string | null>(null);

  const handleDelete = async () => {
    if (!confirm('¿Eliminar esta compra? Se revertirá el stock.')) return;
    setDeleting(true);
    try {
      await comprasApi.post(`/compras/${compra.id}`, { _method: 'DELETE' });
      onDeleted();
    } catch { alert('Error al eliminar'); }
    setDeleting(false);
  };

  const handleUploadImage = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploading(true);
    try {
      const fd = new FormData();
      fd.append('image', file);
      await comprasApi.upload(`/compras/${compra.id}/imagen`, fd);
      // Ideally refresh compra data here
    } catch { alert('Error al subir imagen'); }
    setUploading(false);
  };

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
      <div className="max-h-[90vh] w-full max-w-lg overflow-auto rounded-xl bg-white shadow-xl" onClick={e => e.stopPropagation()}>
        <div className="flex items-center justify-between border-b px-4 py-3">
          <h3 className="text-base font-semibold text-gray-900">Detalle de Compra</h3>
          <button onClick={onClose} className="rounded-full p-1 hover:bg-gray-100"><X className="h-5 w-5" /></button>
        </div>

        <div className="space-y-4 p-4">
          {/* Info */}
          <div className="grid grid-cols-2 gap-2 text-sm">
            <div><span className="text-gray-500">Proveedor:</span> <span className="font-medium">{compra.proveedor}</span></div>
            <div><span className="text-gray-500">Fecha:</span> {formatearFecha(compra.fecha_compra)}</div>
            <div><span className="text-gray-500">Tipo:</span> {compra.tipo_compra}</div>
            <div><span className="text-gray-500">Pago:</span> {METODO_LABELS[compra.metodo_pago] || compra.metodo_pago}</div>
            <div className="col-span-2"><span className="text-gray-500">Monto:</span> <span className="font-bold">{formatearPesosCLP(compra.monto_total)}</span></div>
            {compra.notas && <div className="col-span-2"><span className="text-gray-500">Notas:</span> {compra.notas}</div>}
          </div>

          {/* Items */}
          {compra.detalles && compra.detalles.length > 0 && (
            <div>
              <h4 className="mb-2 text-sm font-semibold text-gray-700">Ítems</h4>
              <div className="divide-y rounded-lg border text-sm">
                {compra.detalles.map(d => (
                  <div key={d.id} className="flex items-center justify-between px-3 py-2">
                    <div>
                      <span className="font-medium">{d.nombre}</span>
                      <span className="ml-2 text-xs text-gray-500">{d.cantidad} {d.unidad}</span>
                    </div>
                    <span>{formatearPesosCLP(d.subtotal)}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Images */}
          {compra.imagen_respaldo && compra.imagen_respaldo.length > 0 && (
            <div>
              <h4 className="mb-2 text-sm font-semibold text-gray-700">Imágenes</h4>
              <div className="flex flex-wrap gap-2">
                {compra.imagen_respaldo.map((url, i) => (
                  <button key={i} onClick={() => setPreviewImg(url)}
                    className="group relative h-20 w-20 overflow-hidden rounded-lg border">
                    <img src={url} alt="" className="h-full w-full object-cover" />
                    <div className="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 group-hover:opacity-100">
                      <ZoomIn className="h-4 w-4 text-white" />
                    </div>
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="flex gap-2 border-t pt-3">
            <label className="flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-2 text-sm hover:bg-gray-50">
              <Upload className="h-4 w-4" /> {uploading ? 'Subiendo...' : 'Subir imagen'}
              <input type="file" accept="image/*" className="hidden" onChange={handleUploadImage} />
            </label>
            <button onClick={handleDelete} disabled={deleting}
              className="ml-auto flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600 hover:bg-red-100">
              <Trash2 className="h-4 w-4" /> {deleting ? 'Eliminando...' : 'Eliminar'}
            </button>
          </div>
        </div>
      </div>

      {/* Full image preview */}
      {previewImg && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" onClick={() => setPreviewImg(null)}>
          <div className="relative max-h-[90vh] max-w-[90vw]">
            <button onClick={() => setPreviewImg(null)} className="absolute -right-2 -top-2 rounded-full bg-white p-1 shadow">
              <X className="h-5 w-5" />
            </button>
            <img src={previewImg} alt="" className="max-h-[85vh] rounded-lg object-contain" />
          </div>
        </div>
      )}
    </div>
  );
}
