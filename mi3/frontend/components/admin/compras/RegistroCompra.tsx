'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import { Trash2, AlertTriangle, Check, Camera, FileText } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { calcularIVA, formatearPesosCLP } from '@/lib/compras-utils';
import type { CompraFormData, CompraFormItem, Kpi, ExtractionResult } from '@/types/compras';
import ItemSearch from './ItemSearch';
import ImageUploader from './ImageUploader';

const TIPOS_COMPRA = [
  { value: 'ingredientes', label: 'Ingredientes' },
  { value: 'bebidas', label: 'Bebidas' },
  { value: 'insumos', label: 'Insumos' },
  { value: 'otros', label: 'Otros' },
];

const METODOS_PAGO = [
  { value: 'cash', label: 'Efectivo' },
  { value: 'transfer', label: 'Transferencia' },
  { value: 'credit', label: 'Crédito' },
  { value: 'debit', label: 'Débito' },
];

interface TempImage { tempKey: string; tempUrl: string; file?: File; }

export default function RegistroCompra() {
  const [step, setStep] = useState<'foto' | 'formulario'>('foto');
  const [form, setForm] = useState<CompraFormData>({
    proveedor: '',
    fecha_compra: new Date().toISOString().split('T')[0],
    tipo_compra: 'ingredientes',
    metodo_pago: 'cash',
    notas: '',
    items: [],
    temp_image_keys: [],
  });
  const [images, setImages] = useState<TempImage[]>([]);
  const [saldo, setSaldo] = useState<number | null>(null);
  const [proveedores, setProveedores] = useState<string[]>([]);
  const [filteredProv, setFilteredProv] = useState<string[]>([]);
  const [showProvDropdown, setShowProvDropdown] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);
  const provRef = useRef<HTMLDivElement>(null);
  const provTimerRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    comprasApi.get<{ success: boolean; data: Kpi }>('/kpis')
      .then(r => setSaldo(r.data?.saldo_disponible ?? null))
      .catch(() => {});
  }, []);

  const searchProveedores = useCallback(async (q: string) => {
    if (q.length < 2) { setFilteredProv([]); return; }
    try {
      const data = await comprasApi.get<string[]>(`/compras/proveedores?q=${encodeURIComponent(q)}`);
      setFilteredProv(data);
      setShowProvDropdown(true);
    } catch { setFilteredProv([]); }
  }, []);

  const handleProvChange = (val: string) => {
    setForm(f => ({ ...f, proveedor: val }));
    if (provTimerRef.current) clearTimeout(provTimerRef.current);
    provTimerRef.current = setTimeout(() => searchProveedores(val), 300);
  };

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (provRef.current && !provRef.current.contains(e.target as Node)) setShowProvDropdown(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  // When IA extracts data, pre-fill the form with DB-matched items
  const handleExtractionResult = (data: ExtractionResult) => {
    const sugerencias = data.sugerencias;
    const itemsSugeridos = sugerencias?.items || [];

    const newItems: CompraFormItem[] = (data.items || []).map((item, idx) => {
      // Try to find a DB match from sugerencias
      const sug = itemsSugeridos[idx];
      const matched = sug?.pre_selected && sug?.match ? sug : null;

      if (matched && matched.match) {
        const m = matched.match;
        const isIngredient = matched.match_type === 'ingredient';
        return {
          ingrediente_id: isIngredient ? m.id : null,
          product_id: !isIngredient ? m.id : null,
          item_type: (matched.match_type || 'ingredient') as 'ingredient' | 'product',
          nombre: m.name,
          cantidad: item.cantidad || 0,
          unidad: m.unit || item.unidad || 'unidad',
          precio_unitario: item.precio_unitario || 0,
          subtotal: item.subtotal || (item.cantidad || 0) * (item.precio_unitario || 0),
          incluye_iva: false,
        };
      }

      // No match found — add as unlinked item (user can search manually)
      return {
        ingrediente_id: null,
        product_id: null,
        item_type: 'ingredient' as const,
        nombre: item.nombre || '',
        cantidad: item.cantidad || 0,
        unidad: item.unidad || 'unidad',
        precio_unitario: item.precio_unitario || 0,
        subtotal: item.subtotal || (item.cantidad || 0) * (item.precio_unitario || 0),
        incluye_iva: false,
      };
    });

    // Use matched proveedor name if available
    const proveedorFinal = sugerencias?.proveedor?.nombre_original || data.proveedor || '';

    setForm(f => ({
      ...f,
      proveedor: proveedorFinal || f.proveedor,
      fecha_compra: data.fecha || f.fecha_compra,
      metodo_pago: data.metodo_pago || f.metodo_pago,
      tipo_compra: data.tipo_compra || f.tipo_compra,
      items: [...f.items, ...newItems],
    }));
    setStep('formulario');
  };

  const addItem = (item: { id: number; name: string; current_stock: number; unit: string; cost_per_unit: number | null; type: 'ingredient' | 'product' }) => {
    const newItem: CompraFormItem = {
      ingrediente_id: item.type === 'ingredient' ? item.id : null,
      product_id: item.type === 'product' ? item.id : null,
      item_type: item.type,
      nombre: item.name,
      cantidad: 1,
      unidad: item.unit,
      precio_unitario: item.cost_per_unit ?? 0,
      subtotal: item.cost_per_unit ?? 0,
      incluye_iva: false,
    };
    setForm(f => ({ ...f, items: [...f.items, newItem] }));
  };

  const updateItem = (idx: number, field: keyof CompraFormItem, value: unknown) => {
    setForm(f => {
      const items = [...f.items];
      const item = { ...items[idx], [field]: value };
      if (field === 'incluye_iva' || field === 'precio_unitario' || field === 'cantidad') {
        const precio = Number(item.precio_unitario) || 0;
        const cantidad = Number(item.cantidad) || 1;
        if (item.incluye_iva) {
          const neto = calcularIVA(precio * cantidad, cantidad);
          item.subtotal = neto * cantidad;
        } else {
          item.subtotal = precio * cantidad;
        }
      }
      items[idx] = item;
      return { ...f, items };
    });
  };

  const removeItem = (idx: number) => {
    setForm(f => ({ ...f, items: f.items.filter((_, i) => i !== idx) }));
  };

  const total = form.items.reduce((sum, it) => sum + (it.subtotal || 0), 0);
  const overBudget = saldo !== null && total > saldo;

  const handleSubmit = async () => {
    if (!form.proveedor || form.items.length === 0) return;
    setSubmitting(true);
    try {
      await comprasApi.post('/compras', {
        ...form,
        monto_total: total,
        temp_keys: images.map(i => i.tempKey),
        usuario: 'Admin',
        items: form.items.map(it => ({
          ...it,
          nombre_item: it.nombre,
        })),
      });
      setSuccess(true);
      setForm({ proveedor: '', fecha_compra: new Date().toISOString().split('T')[0], tipo_compra: 'ingredientes', metodo_pago: 'cash', notas: '', items: [], temp_image_keys: [] });
      setImages([]);
      setStep('foto');
      setTimeout(() => setSuccess(false), 3000);
      comprasApi.get<{ success: boolean; data: Kpi }>('/kpis').then(r => setSaldo(r.data?.saldo_disponible ?? null)).catch(() => {});
    } catch {
      alert('Error al registrar compra');
    }
    setSubmitting(false);
  };

  return (
    <div className="space-y-4">
      {success && (
        <div className="flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700">
          <Check className="h-4 w-4" /> Compra registrada exitosamente
        </div>
      )}

      {/* Step 1: Foto primero */}
      {step === 'foto' && (
        <div className="space-y-4">
          <div className="rounded-xl border bg-white p-4 shadow-sm">
            <h3 className="mb-3 text-sm font-semibold text-gray-700 flex items-center gap-2">
              <Camera className="h-4 w-4" /> Sube la foto de la boleta o producto
            </h3>
            <p className="text-xs text-gray-500 mb-3">La IA extraerá los datos automáticamente</p>
            <ImageUploader images={images} onChange={setImages} onExtractionResult={handleExtractionResult} />
          </div>

          <button onClick={() => setStep('formulario')}
            className="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 py-2.5 text-sm text-gray-600 hover:bg-gray-50">
            <FileText className="h-4 w-4" /> Ingresar manualmente sin foto
          </button>
        </div>
      )}

      {/* Step 2: Formulario (pre-llenado por IA o manual) */}
      {step === 'formulario' && (
        <>
          {/* Header fields */}
          <div className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="grid gap-3 sm:grid-cols-2">
              <div ref={provRef} className="relative sm:col-span-2">
                <label className="mb-1 block text-xs font-medium text-gray-600">Proveedor</label>
                <input type="text" value={form.proveedor}
                  onChange={e => handleProvChange(e.target.value)}
                  placeholder="Nombre del proveedor"
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-mi3-500 focus:outline-none focus:ring-1 focus:ring-mi3-500" />
                {showProvDropdown && filteredProv.length > 0 && (
                  <div className="absolute z-10 mt-1 max-h-40 w-full overflow-auto rounded-lg border bg-white shadow-lg">
                    {filteredProv.map(p => (
                      <button key={p} onClick={() => { setForm(f => ({ ...f, proveedor: p })); setShowProvDropdown(false); }}
                        className="w-full px-3 py-2 text-left text-sm hover:bg-gray-50">{p}</button>
                    ))}
                  </div>
                )}
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-gray-600">Fecha</label>
                <input type="date" value={form.fecha_compra}
                  onChange={e => setForm(f => ({ ...f, fecha_compra: e.target.value }))}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-gray-600">Tipo</label>
                <select value={form.tipo_compra} onChange={e => setForm(f => ({ ...f, tipo_compra: e.target.value }))}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                  {TIPOS_COMPRA.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                </select>
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-gray-600">Pago</label>
                <select value={form.metodo_pago} onChange={e => setForm(f => ({ ...f, metodo_pago: e.target.value }))}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                  {METODOS_PAGO.map(m => <option key={m.value} value={m.value}>{m.label}</option>)}
                </select>
              </div>
              <div className="sm:col-span-2">
                <label className="mb-1 block text-xs font-medium text-gray-600">Notas</label>
                <textarea value={form.notas} onChange={e => setForm(f => ({ ...f, notas: e.target.value }))}
                  rows={2} placeholder="Notas opcionales..."
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
              </div>
            </div>
          </div>

          {/* Items */}
          <div className="rounded-xl border bg-white p-4 shadow-sm">
            <h3 className="mb-3 text-sm font-semibold text-gray-700">Ítems</h3>
            <ItemSearch onSelect={addItem} onCreateNew={() => {}} />

            {form.items.length > 0 && (
              <div className="mt-3 space-y-2">
                {form.items.map((item, idx) => (
                  <div key={idx} className="flex flex-wrap items-center gap-2 rounded-lg border bg-gray-50 p-2 text-sm">
                    <input type="text" value={item.nombre}
                      onChange={e => updateItem(idx, 'nombre', e.target.value)}
                      className="min-w-[100px] flex-1 rounded border px-2 py-1 text-sm font-medium" />
                    <input type="number" value={item.cantidad} min={0.01} step="any"
                      onChange={e => updateItem(idx, 'cantidad', parseFloat(e.target.value) || 0)}
                      className="w-16 rounded border px-2 py-1 text-center" />
                    <span className="text-xs text-gray-500">{item.unidad}</span>
                    <input type="number" value={item.precio_unitario} min={0} step="any"
                      onChange={e => updateItem(idx, 'precio_unitario', parseFloat(e.target.value) || 0)}
                      className="w-24 rounded border px-2 py-1 text-right" placeholder="Precio" />
                    <label className="flex items-center gap-1 text-xs text-gray-500">
                      <input type="checkbox" checked={item.incluye_iva}
                        onChange={e => updateItem(idx, 'incluye_iva', e.target.checked)}
                        className="rounded" /> IVA
                    </label>
                    <span className="ml-auto text-sm font-medium">{formatearPesosCLP(item.subtotal)}</span>
                    <button onClick={() => removeItem(idx)} className="text-red-400 hover:text-red-600">
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Images - always show thumbnails + allow adding more */}
          <div className="rounded-xl border bg-white p-4 shadow-sm">
            <h3 className="mb-3 text-sm font-semibold text-gray-700">
              Imágenes de respaldo {images.length > 0 && `(${images.length})`}
            </h3>
            {images.length > 0 && (
              <div className="flex flex-wrap gap-2 mb-3">
                {images.map((img, i) => (
                  <div key={img.tempKey} className="relative h-16 w-16 rounded-lg border overflow-hidden">
                    <img src={img.tempUrl} alt="" className="h-full w-full object-cover" />
                  </div>
                ))}
              </div>
            )}
            <ImageUploader images={images} onChange={setImages} />
          </div>

          {/* Total + Submit */}
          <div className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-lg font-bold text-gray-900">Total: {formatearPesosCLP(total)}</p>
                {saldo !== null && (
                  <p className="text-xs text-gray-500">Saldo disponible: {formatearPesosCLP(saldo)}</p>
                )}
              </div>
              <div className="flex gap-2">
                <button onClick={() => setStep('foto')}
                  className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50">
                  ← Volver
                </button>
                <button onClick={handleSubmit} disabled={submitting || !form.proveedor || form.items.length === 0}
                  className="rounded-lg bg-mi3-500 px-6 py-2.5 text-sm font-medium text-white hover:bg-mi3-600 disabled:opacity-50">
                  {submitting ? 'Registrando...' : 'Registrar'}
                </button>
              </div>
            </div>
            {overBudget && (
              <div className="mt-2 flex items-center gap-2 rounded-lg bg-amber-50 p-2 text-sm text-amber-700">
                <AlertTriangle className="h-4 w-4 flex-shrink-0" />
                El monto total supera el saldo disponible
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
}
