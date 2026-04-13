'use client';

import { useState, useCallback, useRef, useEffect } from 'react';
import { Upload, X, Loader2, Check, AlertTriangle, Trash2, ChevronDown, ChevronUp, FileText, Search, Sparkles, Package } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP } from '@/lib/compras-utils';
import type { ExtractionResult, Kpi, ItemSugerencia } from '@/types/compras';

interface UploadedImage {
  tempKey: string;
  tempUrl: string;
  status: 'uploading' | 'extracting' | 'extracted' | 'error';
  extraction?: ExtractionResult;
  sugerencias?: { proveedor: any; items: ItemSugerencia[] };
  error?: string;
}

interface CompraItem {
  ingrediente_id: number | null;
  product_id: number | null;
  item_type: 'ingredient' | 'product';
  nombre: string;
  cantidad: number;
  unidad: string;
  precio_unitario: number;
  subtotal: number;
  empaque_detalle?: string | null;
  match_score?: number;
  match_name?: string;
}

interface CompraGroup {
  proveedor: string;
  fecha_compra: string;
  metodo_pago: string;
  tipo_compra: string;
  notas: string;
  images: UploadedImage[];
  items: CompraItem[];
  expanded: boolean;
}

const METODOS_PAGO = [
  { value: 'cash', label: 'Efectivo' },
  { value: 'transfer', label: 'Transferencia' },
  { value: 'credit', label: 'Crédito' },
  { value: 'debit', label: 'Débito' },
];

// --- Item search mini-component (inline) ---
function InlineItemSearch({ onSelect }: { onSelect: (item: { id: number; name: string; unit: string; type: 'ingredient' | 'product'; cost_per_unit: number | null }) => void }) {
  const [q, setQ] = useState('');
  const [results, setResults] = useState<any[]>([]);
  const [open, setOpen] = useState(false);
  const timer = useRef<NodeJS.Timeout | null>(null);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const h = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false); };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, []);

  const search = async (val: string) => {
    if (val.length < 2) { setResults([]); setOpen(false); return; }
    try {
      const data = await comprasApi.get<any[]>(`/compras/items?q=${encodeURIComponent(val)}`);
      setResults(data);
      setOpen(true);
    } catch { setResults([]); }
  };

  return (
    <div ref={ref} className="relative">
      <div className="relative">
        <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
        <input type="text" value={q} placeholder="Buscar ingrediente..."
          onChange={e => { setQ(e.target.value); if (timer.current) clearTimeout(timer.current); timer.current = setTimeout(() => search(e.target.value), 300); }}
          className="w-full rounded-lg border border-gray-300 py-1.5 pl-8 pr-3 text-sm" />
      </div>
      {open && results.length > 0 && (
        <div className="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-lg border bg-white shadow-lg">
          {results.map((r: any) => (
            <button key={`${r.type}-${r.id}`} onClick={() => { onSelect(r); setQ(''); setOpen(false); }}
              className="flex w-full items-center justify-between px-3 py-1.5 text-left text-sm hover:bg-gray-50">
              <span className="font-medium">{r.name}</span>
              <span className="text-xs text-gray-400">{r.current_stock} {r.unit}</span>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

// --- Main page component ---
export default function RegistroPage() {
  const [groups, setGroups] = useState<CompraGroup[]>([]);
  const [uploading, setUploading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState<number[]>([]);
  const [saldo, setSaldo] = useState<number | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    comprasApi.get<{ success: boolean; data: Kpi }>('/kpis')
      .then(r => setSaldo(r.data?.saldo_disponible ?? null)).catch(() => {});
  }, []);

  // Upload + extract + group
  const processFiles = useCallback(async (files: FileList | File[]) => {
    setUploading(true);
    const newGroups: CompraGroup[] = [...groups.filter((_, i) => !submitted.includes(i))];

    for (const file of Array.from(files)) {
      if (!file.type.startsWith('image/')) continue;

      let tempKey = '', tempUrl = '';
      try {
        const fd = new FormData();
        fd.append('image', file);
        const res = await comprasApi.upload<{ tempKey: string; tempUrl: string }>('/compras/upload-temp', fd);
        tempKey = res.tempKey;
        tempUrl = res.tempUrl;
      } catch { continue; }

      const img: UploadedImage = { tempKey, tempUrl, status: 'extracting' };

      // Extract with IA + get sugerencias
      try {
        const res = await comprasApi.post<{
          success: boolean;
          data?: ExtractionResult;
          confianza?: any;
          sugerencias?: { proveedor: any; items: ItemSugerencia[] };
          error?: string;
        }>('/compras/extract', { temp_key: tempKey });

        if (res.success && res.data) {
          img.status = 'extracted';
          img.extraction = { ...res.data, confianza: res.confianza ?? res.data.confianza, sugerencias: res.sugerencias };
          img.sugerencias = res.sugerencias;
        } else {
          img.status = 'error';
          img.error = res.error || 'No se pudo extraer';
        }
      } catch {
        img.status = 'error';
        img.error = 'Error de conexión';
      }

      // Build items with DB match
      const extractedItems = img.extraction?.items || [];
      const sugItems = img.sugerencias?.items || [];
      const items: CompraItem[] = extractedItems.map((item, idx) => {
        const sug = sugItems[idx];
        const matched = sug?.pre_selected && sug?.match;
        if (matched && sug.match) {
          const m = sug.match;
          const isIng = sug.match_type === 'ingredient';
          return {
            ingrediente_id: isIng ? m.id : null,
            product_id: !isIng ? m.id : null,
            item_type: (sug.match_type || 'ingredient') as 'ingredient' | 'product',
            nombre: m.name,
            cantidad: item.cantidad || 0,
            unidad: m.unit || item.unidad || 'unidad',
            precio_unitario: item.precio_unitario || 0,
            subtotal: item.subtotal || (item.cantidad || 0) * (item.precio_unitario || 0),
            empaque_detalle: item.empaque_detalle || null,
            match_score: sug.score,
            match_name: m.name,
          };
        }
        return {
          ingrediente_id: null, product_id: null, item_type: 'ingredient' as const,
          nombre: item.nombre || '', cantidad: item.cantidad || 0,
          unidad: item.unidad || 'unidad', precio_unitario: item.precio_unitario || 0,
          subtotal: item.subtotal || (item.cantidad || 0) * (item.precio_unitario || 0),
          empaque_detalle: item.empaque_detalle || null,
          match_score: sug?.score, match_name: sug?.match?.name,
        };
      });

      // Determine proveedor
      const provSug = img.sugerencias?.proveedor;
      const proveedor = provSug?.nombre_original || img.extraction?.proveedor || 'Proveedor desconocido';
      const metodoPago = img.extraction?.metodo_pago || 'cash';
      const tipoCompra = img.extraction?.tipo_compra || 'ingredientes';

      // Group by proveedor
      const existing = newGroups.find(g => g.proveedor.toLowerCase() === proveedor.toLowerCase());
      if (existing) {
        existing.images.push(img);
        existing.items.push(...items);
      } else {
        newGroups.push({
          proveedor, fecha_compra: img.extraction?.fecha || new Date().toISOString().split('T')[0],
          metodo_pago: metodoPago, tipo_compra: tipoCompra, notas: '',
          images: [img], items, expanded: true,
        });
      }
    }

    setGroups(newGroups);
    setSubmitted([]);
    setUploading(false);
  }, [groups, submitted]);

  // Add manual group (no photo)
  const addManualGroup = () => {
    setGroups(prev => [...prev, {
      proveedor: '', fecha_compra: new Date().toISOString().split('T')[0],
      metodo_pago: 'cash', tipo_compra: 'ingredientes', notas: '',
      images: [], items: [], expanded: true,
    }]);
  };

  // Add item from search to a group
  const addItemToGroup = (gi: number, item: { id: number; name: string; unit: string; type: 'ingredient' | 'product'; cost_per_unit: number | null }) => {
    setGroups(prev => prev.map((g, i) => {
      if (i !== gi) return g;
      const newItem: CompraItem = {
        ingrediente_id: item.type === 'ingredient' ? item.id : null,
        product_id: item.type === 'product' ? item.id : null,
        item_type: item.type, nombre: item.name, cantidad: 1,
        unidad: item.unit, precio_unitario: item.cost_per_unit ?? 0,
        subtotal: item.cost_per_unit ?? 0, match_score: 100, match_name: item.name,
      };
      return { ...g, items: [...g.items, newItem] };
    }));
  };

  const updateGroup = (idx: number, updates: Partial<CompraGroup>) => {
    setGroups(prev => prev.map((g, i) => i === idx ? { ...g, ...updates } : g));
  };

  const updateItem = (gi: number, ii: number, field: string, value: any) => {
    setGroups(prev => prev.map((g, gIdx) => {
      if (gIdx !== gi) return g;
      const items = [...g.items];
      items[ii] = { ...items[ii], [field]: value };
      if (field === 'cantidad' || field === 'precio_unitario') {
        items[ii].subtotal = (Number(items[ii].cantidad) || 0) * (Number(items[ii].precio_unitario) || 0);
      }
      return { ...g, items };
    }));
  };

  const removeItem = (gi: number, ii: number) => {
    setGroups(prev => prev.map((g, i) => i !== gi ? g : { ...g, items: g.items.filter((_, j) => j !== ii) }));
  };

  const removeGroup = (idx: number) => {
    setGroups(prev => prev.filter((_, i) => i !== idx));
  };

  const groupTotal = (g: CompraGroup) => g.items.reduce((s, i) => s + (i.subtotal || 0), 0);

  const submitGroup = async (idx: number) => {
    const group = groups[idx];
    if (!group.proveedor || group.items.length === 0) return;
    setSubmitting(true);
    try {
      await comprasApi.post('/compras', {
        proveedor: group.proveedor,
        fecha_compra: group.fecha_compra,
        tipo_compra: group.tipo_compra,
        metodo_pago: group.metodo_pago,
        monto_total: groupTotal(group),
        notas: group.notas || (group.images.length > 0 ? `${group.images.length} foto(s)` : ''),
        items: group.items.map(i => ({
          nombre_item: i.nombre, cantidad: i.cantidad, unidad: i.unidad,
          precio_unitario: i.precio_unitario, subtotal: i.subtotal,
          item_type: i.item_type, ingrediente_id: i.ingrediente_id, product_id: i.product_id,
        })),
        temp_keys: group.images.map(i => i.tempKey),
        usuario: 'Admin',
      });
      setSubmitted(prev => [...prev, idx]);
      comprasApi.get<{ success: boolean; data: Kpi }>('/kpis').then(r => setSaldo(r.data?.saldo_disponible ?? null)).catch(() => {});
    } catch { alert('Error al registrar compra'); }
    setSubmitting(false);
  };

  const submitAll = async () => {
    for (let i = 0; i < groups.length; i++) {
      if (!submitted.includes(i) && groups[i].items.length > 0 && groups[i].proveedor) await submitGroup(i);
    }
  };

  const pendingGroups = groups.filter((_, i) => !submitted.includes(i));
  const grandTotal = pendingGroups.reduce((s, g) => s + groupTotal(g), 0);

  return (
    <div className="space-y-4">
      {/* Saldo */}
      {saldo !== null && (
        <div className="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2 text-sm">
          <span className="text-gray-500">Saldo disponible</span>
          <span className={`font-bold ${saldo < 0 ? 'text-red-600' : 'text-green-700'}`}>{formatearPesosCLP(saldo)}</span>
        </div>
      )}

      {/* Drop zone — always visible */}
      <div
        onDragOver={e => e.preventDefault()}
        onDrop={e => { e.preventDefault(); if (e.dataTransfer.files.length) processFiles(e.dataTransfer.files); }}
        onClick={() => inputRef.current?.click()}
        className="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 p-6 text-center hover:border-mi3-400 hover:bg-mi3-50/30 transition-colors"
      >
        {uploading ? (
          <><Loader2 className="h-6 w-6 animate-spin text-mi3-500" /><p className="text-sm text-mi3-600">Procesando fotos...</p></>
        ) : (
          <><Upload className="h-6 w-6 text-gray-400" />
          <p className="text-sm text-gray-600">Sube 1 o más fotos de boletas/facturas</p>
          <p className="text-xs text-gray-400">La IA extrae datos y agrupa por proveedor</p></>
        )}
        <input ref={inputRef} type="file" accept="image/*" multiple className="hidden"
          onChange={e => { if (e.target.files) processFiles(e.target.files); e.target.value = ''; }} />
      </div>

      {/* Manual entry button */}
      {groups.length === 0 && (
        <button onClick={addManualGroup}
          className="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 py-2.5 text-sm text-gray-600 hover:bg-gray-50">
          <FileText className="h-4 w-4" /> Ingresar manualmente sin foto
        </button>
      )}

      {/* Groups */}
      {groups.map((group, gi) => {
        const isSubmitted = submitted.includes(gi);
        const total = groupTotal(group);
        return (
          <div key={gi} className={`rounded-xl border shadow-sm ${isSubmitted ? 'bg-green-50 border-green-200' : 'bg-white'}`}>
            {/* Header */}
            <div className="flex items-center gap-2 px-4 py-3 border-b cursor-pointer" onClick={() => updateGroup(gi, { expanded: !group.expanded })}>
              {group.expanded ? <ChevronUp className="h-4 w-4 text-gray-400" /> : <ChevronDown className="h-4 w-4 text-gray-400" />}
              {isSubmitted && <Check className="h-4 w-4 text-green-600" />}
              <span className="text-sm font-semibold text-gray-900 flex-1">{group.proveedor || 'Sin proveedor'}</span>
              <span className="text-sm font-bold">{formatearPesosCLP(total)}</span>
              <span className="text-xs text-gray-400">{group.items.length} items</span>
              {!isSubmitted && (
                <button onClick={e => { e.stopPropagation(); removeGroup(gi); }} className="text-red-400 hover:text-red-600">
                  <Trash2 className="h-4 w-4" />
                </button>
              )}
            </div>

            {/* Body */}
            {group.expanded && !isSubmitted && (
              <div className="p-4 space-y-3">
                {/* Thumbnails */}
                {group.images.length > 0 && (
                  <div className="flex gap-2 overflow-x-auto">
                    {group.images.map((img, ii) => (
                      <div key={ii} className="relative h-14 w-14 flex-shrink-0 rounded-lg border overflow-hidden">
                        <img src={img.tempUrl} alt="" className="h-full w-full object-cover" />
                        {img.status === 'extracting' && <div className="absolute inset-0 flex items-center justify-center bg-black/40"><Loader2 className="h-3 w-3 animate-spin text-white" /></div>}
                        {img.status === 'error' && <div className="absolute inset-0 flex items-center justify-center bg-red-500/40"><X className="h-3 w-3 text-white" /></div>}
                      </div>
                    ))}
                  </div>
                )}

                {/* Fields row */}
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                  <div>
                    <label className="text-xs text-gray-500">Proveedor</label>
                    <input type="text" value={group.proveedor} onChange={e => updateGroup(gi, { proveedor: e.target.value })}
                      className="w-full rounded border px-2 py-1.5 text-sm" placeholder="Proveedor" />
                  </div>
                  <div>
                    <label className="text-xs text-gray-500">Fecha</label>
                    <input type="date" value={group.fecha_compra} onChange={e => updateGroup(gi, { fecha_compra: e.target.value })}
                      className="w-full rounded border px-2 py-1.5 text-sm" />
                  </div>
                  <div>
                    <label className="text-xs text-gray-500">Pago</label>
                    <select value={group.metodo_pago} onChange={e => updateGroup(gi, { metodo_pago: e.target.value })}
                      className="w-full rounded border px-2 py-1.5 text-sm">
                      {METODOS_PAGO.map(m => <option key={m.value} value={m.value}>{m.label}</option>)}
                    </select>
                  </div>
                  <div>
                    <label className="text-xs text-gray-500">Notas</label>
                    <input type="text" value={group.notas} onChange={e => updateGroup(gi, { notas: e.target.value })}
                      className="w-full rounded border px-2 py-1.5 text-sm" placeholder="Opcional" />
                  </div>
                </div>

                {/* Search to add items */}
                <InlineItemSearch onSelect={item => addItemToGroup(gi, item)} />

                {/* Items */}
                {group.items.length > 0 && (
                  <div className="space-y-1">
                    {group.items.map((item, ii) => (
                      <div key={ii} className="rounded-lg border bg-gray-50 px-3 py-2 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                          <input type="text" value={item.nombre} onChange={e => updateItem(gi, ii, 'nombre', e.target.value)}
                            className="min-w-[100px] flex-1 rounded border px-2 py-1 text-sm font-medium" />
                          <input type="number" value={item.cantidad} step="any" onChange={e => updateItem(gi, ii, 'cantidad', parseFloat(e.target.value) || 0)}
                            className="w-16 rounded border px-2 py-1 text-center" />
                          <span className="text-xs text-gray-500">{item.unidad}</span>
                          <input type="number" value={item.precio_unitario} step="any" onChange={e => updateItem(gi, ii, 'precio_unitario', parseFloat(e.target.value) || 0)}
                            className="w-20 rounded border px-2 py-1 text-right" />
                          <span className="w-20 text-right text-xs font-medium">{formatearPesosCLP(item.subtotal)}</span>
                          <button onClick={() => removeItem(gi, ii)} className="text-red-400 hover:text-red-600"><X className="h-3.5 w-3.5" /></button>
                        </div>
                        {/* Match + empaque info */}
                        <div className="flex flex-wrap gap-2 mt-1">
                          {item.empaque_detalle && <span className="text-xs text-blue-600">📦 {item.empaque_detalle}</span>}
                          {item.ingrediente_id && <span className="text-xs text-green-600">✅ {item.match_name} ({Math.round(item.match_score || 0)}%)</span>}
                          {!item.ingrediente_id && !item.product_id && item.match_name && (
                            <span className="text-xs text-amber-600">⚠️ {item.match_name} ({Math.round(item.match_score || 0)}%)</span>
                          )}
                          {!item.ingrediente_id && !item.product_id && !item.match_name && (
                            <span className="text-xs text-red-500">❌ Sin match — busca arriba para vincular</span>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {/* Submit group */}
                <button onClick={() => submitGroup(gi)} disabled={submitting || !group.proveedor || group.items.length === 0}
                  className="w-full rounded-lg bg-mi3-500 py-2.5 text-sm font-medium text-white hover:bg-mi3-600 disabled:opacity-50">
                  {submitting ? 'Registrando...' : `Registrar — ${formatearPesosCLP(total)}`}
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

      {/* Submit all + add more */}
      {groups.length > 0 && (
        <div className="space-y-2">
          <button onClick={addManualGroup}
            className="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 py-2 text-sm text-gray-500 hover:bg-gray-50">
            + Agregar compra manual
          </button>
          {pendingGroups.length > 1 && (
            <button onClick={submitAll} disabled={submitting}
              className="w-full rounded-xl bg-green-600 py-3 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50">
              {submitting ? 'Registrando...' : `Registrar todas (${pendingGroups.length}) — ${formatearPesosCLP(grandTotal)}`}
            </button>
          )}
          {saldo !== null && grandTotal > saldo && (
            <div className="flex items-center gap-2 rounded-lg bg-amber-50 p-2 text-sm text-amber-700">
              <AlertTriangle className="h-4 w-4 flex-shrink-0" /> El total supera el saldo disponible
            </div>
          )}
        </div>
      )}
    </div>
  );
}
