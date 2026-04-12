'use client';

import { useState, useCallback, useRef } from 'react';
import { Upload, X, Loader2, Check, AlertTriangle, Edit3, ChevronDown, ChevronUp, Trash2 } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP } from '@/lib/compras-utils';
import type { ExtractionResult } from '@/types/compras';

interface UploadedImage {
  file: File;
  tempKey: string;
  tempUrl: string;
  status: 'uploading' | 'extracting' | 'extracted' | 'error';
  extraction?: ExtractionResult & { tipo_imagen?: string; notas_ia?: string };
  error?: string;
}

interface CompraGroup {
  proveedor: string;
  proveedorEditable: boolean;
  images: UploadedImage[];
  items: Array<{
    nombre: string;
    cantidad: number;
    unidad: string;
    precio_unitario: number;
    subtotal: number;
    ingrediente_id: number | null;
    editable: boolean;
  }>;
  monto_total: number;
  metodo_pago: string;
  expanded: boolean;
}

export default function SubidaMasiva() {
  const [groups, setGroups] = useState<CompraGroup[]>([]);
  const [uploading, setUploading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState<number[]>([]);
  const inputRef = useRef<HTMLInputElement>(null);

  const processFiles = useCallback(async (files: FileList | File[]) => {
    setUploading(true);
    const newGroups: CompraGroup[] = [];

    for (const file of Array.from(files)) {
      if (!file.type.startsWith('image/')) continue;

      // Upload temp
      let tempKey = '';
      let tempUrl = '';
      try {
        const fd = new FormData();
        fd.append('image', file);
        const res = await comprasApi.upload<{ tempKey: string; tempUrl: string }>('/compras/upload-temp', fd);
        tempKey = res.tempKey;
        tempUrl = res.tempUrl;
      } catch {
        continue;
      }

      const img: UploadedImage = { file, tempKey, tempUrl, status: 'extracting' };

      // Extract with IA
      try {
        const res = await comprasApi.post<{
          success: boolean;
          data?: ExtractionResult;
          error?: string;
        }>('/compras/extract', { temp_key: tempKey });

        if (res.success && res.data) {
          img.status = 'extracted';
          img.extraction = res.data as any;
        } else {
          img.status = 'error';
          img.error = res.error || 'No se pudo extraer';
        }
      } catch {
        img.status = 'error';
        img.error = 'Error de conexión';
      }

      // Group by proveedor
      const proveedor = img.extraction?.proveedor || 'Proveedor desconocido';
      const existingGroup = newGroups.find(g => 
        g.proveedor.toLowerCase() === proveedor.toLowerCase()
      );

      const items = (img.extraction?.items || []).map(item => ({
        nombre: item.nombre || '?',
        cantidad: item.cantidad || 0,
        unidad: item.unidad || 'unidad',
        precio_unitario: item.precio_unitario || 0,
        subtotal: item.subtotal || (item.cantidad || 0) * (item.precio_unitario || 0),
        ingrediente_id: null,
        editable: true,
      }));

      if (existingGroup) {
        existingGroup.images.push(img);
        existingGroup.items.push(...items);
        existingGroup.monto_total = existingGroup.items.reduce((s, i) => s + i.subtotal, 0);
      } else {
        newGroups.push({
          proveedor,
          proveedorEditable: !img.extraction?.proveedor,
          images: [img],
          items,
          monto_total: items.reduce((s, i) => s + i.subtotal, 0),
          metodo_pago: 'cash',
          expanded: true,
        });
      }
    }

    setGroups(prev => [...prev, ...newGroups]);
    setUploading(false);
  }, []);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    if (e.dataTransfer.files.length) processFiles(e.dataTransfer.files);
  }, [processFiles]);

  const updateGroup = (idx: number, updates: Partial<CompraGroup>) => {
    setGroups(prev => prev.map((g, i) => i === idx ? { ...g, ...updates } : g));
  };

  const updateItem = (groupIdx: number, itemIdx: number, field: string, value: any) => {
    setGroups(prev => prev.map((g, gi) => {
      if (gi !== groupIdx) return g;
      const items = [...g.items];
      items[itemIdx] = { ...items[itemIdx], [field]: value };
      if (field === 'cantidad' || field === 'precio_unitario') {
        items[itemIdx].subtotal = (Number(items[itemIdx].cantidad) || 0) * (Number(items[itemIdx].precio_unitario) || 0);
      }
      return { ...g, items, monto_total: items.reduce((s, i) => s + i.subtotal, 0) };
    }));
  };

  const removeItem = (groupIdx: number, itemIdx: number) => {
    setGroups(prev => prev.map((g, gi) => {
      if (gi !== groupIdx) return g;
      const items = g.items.filter((_, i) => i !== itemIdx);
      return { ...g, items, monto_total: items.reduce((s, i) => s + i.subtotal, 0) };
    }));
  };

  const removeGroup = (idx: number) => {
    setGroups(prev => prev.filter((_, i) => i !== idx));
  };

  const submitGroup = async (idx: number) => {
    const group = groups[idx];
    if (group.items.length === 0) return;

    setSubmitting(true);
    try {
      await comprasApi.post('/compras', {
        proveedor: group.proveedor,
        fecha_compra: new Date().toISOString().split('T')[0],
        tipo_compra: 'ingredientes',
        metodo_pago: group.metodo_pago,
        monto_total: group.monto_total,
        notas: `Subida masiva (${group.images.length} fotos)`,
        items: group.items.map(i => ({
          nombre_item: i.nombre,
          cantidad: i.cantidad,
          unidad: i.unidad,
          precio_unitario: i.precio_unitario,
          subtotal: i.subtotal,
          item_type: 'ingredient',
          ingrediente_id: i.ingrediente_id,
        })),
        temp_keys: group.images.map(i => i.tempKey),
        usuario: 'Admin',
      });
      setSubmitted(prev => [...prev, idx]);
    } catch {
      alert('Error al registrar compra');
    }
    setSubmitting(false);
  };

  const submitAll = async () => {
    for (let i = 0; i < groups.length; i++) {
      if (!submitted.includes(i) && groups[i].items.length > 0) {
        await submitGroup(i);
      }
    }
  };

  return (
    <div className="space-y-4">
      {/* Drop zone */}
      <div
        onDragOver={e => e.preventDefault()}
        onDrop={handleDrop}
        onClick={() => inputRef.current?.click()}
        className="flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 p-8 text-center hover:border-mi3-400 hover:bg-mi3-50/30 transition-colors"
      >
        <Upload className="h-8 w-8 text-gray-400" />
        <div>
          <p className="text-sm font-medium text-gray-700">Arrastra varias boletas/facturas aquí</p>
          <p className="text-xs text-gray-500 mt-1">La IA separará por proveedor automáticamente</p>
        </div>
        {uploading && <Loader2 className="h-5 w-5 animate-spin text-mi3-500" />}
        <input ref={inputRef} type="file" accept="image/*" multiple className="hidden"
          onChange={e => e.target.files && processFiles(e.target.files)} />
      </div>

      {/* Groups */}
      {groups.length > 0 && (
        <div className="space-y-3">
          {groups.map((group, gi) => {
            const isSubmitted = submitted.includes(gi);
            return (
              <div key={gi} className={`rounded-xl border shadow-sm ${isSubmitted ? 'bg-green-50 border-green-200' : 'bg-white'}`}>
                {/* Group header */}
                <div className="flex items-center gap-3 px-4 py-3 border-b">
                  <button onClick={() => updateGroup(gi, { expanded: !group.expanded })}
                    className="text-gray-400 hover:text-gray-600">
                    {group.expanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                  </button>

                  {isSubmitted ? (
                    <Check className="h-5 w-5 text-green-600" />
                  ) : group.proveedorEditable ? (
                    <div className="flex items-center gap-1">
                      <AlertTriangle className="h-4 w-4 text-amber-500" />
                      <input type="text" value={group.proveedor}
                        onChange={e => updateGroup(gi, { proveedor: e.target.value })}
                        className="rounded border border-amber-300 bg-amber-50 px-2 py-1 text-sm font-medium" />
                    </div>
                  ) : (
                    <span className="text-sm font-semibold text-gray-900">{group.proveedor}</span>
                  )}

                  <span className="ml-auto text-sm font-bold text-gray-700">
                    {formatearPesosCLP(group.monto_total)}
                  </span>
                  <span className="text-xs text-gray-400">{group.items.length} items · {group.images.length} fotos</span>

                  {!isSubmitted && (
                    <button onClick={() => removeGroup(gi)} className="text-red-400 hover:text-red-600">
                      <Trash2 className="h-4 w-4" />
                    </button>
                  )}
                </div>

                {/* Group body */}
                {group.expanded && !isSubmitted && (
                  <div className="p-4 space-y-3">
                    {/* Thumbnails */}
                    <div className="flex gap-2 overflow-x-auto">
                      {group.images.map((img, ii) => (
                        <div key={ii} className="relative h-16 w-16 flex-shrink-0 rounded-lg border overflow-hidden">
                          <img src={img.tempUrl} alt="" className="h-full w-full object-cover" />
                          {img.status === 'extracting' && (
                            <div className="absolute inset-0 flex items-center justify-center bg-black/40">
                              <Loader2 className="h-4 w-4 animate-spin text-white" />
                            </div>
                          )}
                          {img.status === 'error' && (
                            <div className="absolute inset-0 flex items-center justify-center bg-red-500/40">
                              <X className="h-4 w-4 text-white" />
                            </div>
                          )}
                        </div>
                      ))}
                    </div>

                    {/* Método pago */}
                    <select value={group.metodo_pago} onChange={e => updateGroup(gi, { metodo_pago: e.target.value })}
                      className="rounded-lg border px-3 py-1.5 text-sm">
                      <option value="cash">Efectivo</option>
                      <option value="transfer">Transferencia</option>
                      <option value="credit">Crédito</option>
                      <option value="debit">Débito</option>
                    </select>

                    {/* Items - editable */}
                    <div className="space-y-1">
                      {group.items.map((item, ii) => (
                        <div key={ii} className="flex flex-wrap items-center gap-2 rounded-lg border bg-gray-50 px-3 py-2 text-sm">
                          <input type="text" value={item.nombre}
                            onChange={e => updateItem(gi, ii, 'nombre', e.target.value)}
                            className="min-w-[120px] flex-1 rounded border px-2 py-1 text-sm font-medium" />
                          <input type="number" value={item.cantidad} step="any"
                            onChange={e => updateItem(gi, ii, 'cantidad', parseFloat(e.target.value) || 0)}
                            className="w-16 rounded border px-2 py-1 text-center text-sm" />
                          <input type="text" value={item.unidad}
                            onChange={e => updateItem(gi, ii, 'unidad', e.target.value)}
                            className="w-16 rounded border px-2 py-1 text-center text-xs" />
                          <input type="number" value={item.precio_unitario} step="any"
                            onChange={e => updateItem(gi, ii, 'precio_unitario', parseFloat(e.target.value) || 0)}
                            className="w-20 rounded border px-2 py-1 text-right text-sm" />
                          <span className="text-xs font-medium text-gray-600 w-20 text-right">
                            {formatearPesosCLP(item.subtotal)}
                          </span>
                          <button onClick={() => removeItem(gi, ii)} className="text-red-400 hover:text-red-600">
                            <X className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      ))}
                    </div>

                    {/* IA notes */}
                    {group.images.some(i => (i.extraction as any)?.notas_ia) && (
                      <p className="text-xs text-gray-500 bg-gray-50 rounded px-2 py-1">
                        💡 {group.images.find(i => (i.extraction as any)?.notas_ia)?.extraction?.notas_ia as any}
                      </p>
                    )}

                    {/* Submit single group */}
                    <button onClick={() => submitGroup(gi)} disabled={submitting || group.items.length === 0}
                      className="w-full rounded-lg bg-mi3-500 py-2 text-sm font-medium text-white hover:bg-mi3-600 disabled:opacity-50">
                      {submitting ? 'Registrando...' : `Registrar compra ${group.proveedor}`}
                    </button>
                  </div>
                )}

                {isSubmitted && (
                  <div className="px-4 py-3 text-sm text-green-700 flex items-center gap-2">
                    <Check className="h-4 w-4" /> Compra registrada
                  </div>
                )}
              </div>
            );
          })}

          {/* Submit all */}
          {groups.length > 1 && groups.some((_, i) => !submitted.includes(i)) && (
            <button onClick={submitAll} disabled={submitting}
              className="w-full rounded-xl bg-green-600 py-3 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50">
              {submitting ? 'Registrando...' : `Registrar todas (${groups.filter((_, i) => !submitted.includes(i)).length} compras)`}
            </button>
          )}
        </div>
      )}
    </div>
  );
}
