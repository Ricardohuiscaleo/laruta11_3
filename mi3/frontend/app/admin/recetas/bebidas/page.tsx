'use client';

import { useEffect, useState, useCallback, useRef, useMemo } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, Plus, AlertTriangle, X, ChevronDown, ChevronRight, Image as ImageIcon } from 'lucide-react';
import type { ApiResponse } from '@/types';

/* ─── Types ─── */

interface Beverage {
  id: number;
  name: string;
  description: string | null;
  price: number;
  cost_price: number;
  recipe_cost: number;
  stock_quantity: number;
  min_stock_level: number;
  is_low_stock: boolean;
  category_id: number | null;
  category_name: string | null;
  subcategory_id: number | null;
  subcategory_name: string | null;
  sku: string | null;
  image_url: string | null;
  is_active: boolean;
}

interface Subcategory {
  id: number;
  name: string;
  category_id: number;
}

interface FormData {
  name: string;
  description: string;
  price: string;
  cost_price: string;
  stock_quantity: string;
  min_stock_level: string;
  subcategory_id: string;
  sku: string;
}

const emptyForm: FormData = {
  name: '', description: '', price: '', cost_price: '',
  stock_quantity: '', min_stock_level: '', subcategory_id: '', sku: '',
};

/* ─── Helpers ─── */

function truncate(text: string | null, max: number): string {
  if (!text) return '';
  return text.length > max ? text.slice(0, max) + '…' : text;
}

/* ─── ImageDropZone (inline reusable) ─── */

function ImageDropZone({ image, onImageChange }: { image: File | null; onImageChange: (file: File | null) => void }) {
  const [dragOver, setDragOver] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const previewUrl = useMemo(() => (image ? URL.createObjectURL(image) : null), [image]);

  useEffect(() => {
    return () => { if (previewUrl) URL.revokeObjectURL(previewUrl); };
  }, [previewUrl]);

  const handleFile = (file: File) => {
    if (!file.type.match(/^image\/(jpeg|png|webp)$/)) return;
    if (file.size > 5 * 1024 * 1024) return;
    onImageChange(file);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
  };

  return (
    <div className="space-y-1">
      <span className="block text-xs font-medium text-gray-600">Imagen</span>
      <div
        onDragOver={e => { e.preventDefault(); setDragOver(true); }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleDrop}
        onClick={() => inputRef.current?.click()}
        className={cn(
          'relative flex h-[120px] w-[120px] cursor-pointer items-center justify-center rounded-lg border-2 border-dashed bg-gray-50 transition-colors',
          dragOver ? 'border-red-400 bg-red-50' : 'border-gray-300 hover:border-gray-400',
          'sm:h-[120px] sm:w-[120px]',
          'max-sm:h-[100px] max-sm:w-full'
        )}
        role="button"
        tabIndex={0}
        onKeyDown={e => e.key === 'Enter' && inputRef.current?.click()}
        aria-label="Subir imagen del producto"
      >
        {previewUrl ? (
          <>
            <img src={previewUrl} alt="Preview" className="h-full w-full rounded-lg object-cover" />
            <button
              onClick={e => { e.stopPropagation(); onImageChange(null); }}
              className="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white shadow-sm hover:bg-red-600"
              aria-label="Quitar imagen"
            >
              <X className="h-3 w-3" />
            </button>
          </>
        ) : (
          <div className="flex flex-col items-center gap-1 text-gray-400">
            <ImageIcon className="h-6 w-6" />
            <span className="text-[10px]">Arrastra o click</span>
          </div>
        )}
        <input
          ref={inputRef}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          onChange={e => { const f = e.target.files?.[0]; if (f) handleFile(f); e.target.value = ''; }}
          className="hidden"
          aria-label="Seleccionar imagen"
        />
      </div>
      <p className="text-[10px] text-gray-400">JPG, PNG, WebP · Max 5MB</p>
    </div>
  );
}

/* ─── Main Component ─── */

export default function BebidasTab() {
  const [beverages, setBeverages] = useState<Beverage[]>([]);
  const [subcategories, setSubcategories] = useState<Subcategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showAddForm, setShowAddForm] = useState(false);
  const [form, setForm] = useState<FormData>(emptyForm);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [collapsed, setCollapsed] = useState<Set<string>>(new Set());
  const [bevImage, setBevImage] = useState<File | null>(null);

  const fetchBeverages = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<Beverage[]>>('/admin/bebidas');
      setBeverages(res.data || []);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Error al cargar bebidas');
    } finally {
      setLoading(false);
    }
  }, []);

  const fetchSubcategories = useCallback(async () => {
    try {
      const res = await apiFetch<ApiResponse<Subcategory[]>>('/admin/bebidas/subcategorias');
      setSubcategories(res.data || []);
    } catch {
      // Non-critical, form still works without subcategories
    }
  }, []);

  useEffect(() => { fetchBeverages(); fetchSubcategories(); }, [fetchBeverages, fetchSubcategories]);

  /* ─── Group by subcategory ─── */
  const grouped = beverages.reduce<Record<string, Beverage[]>>((acc, b) => {
    const key = b.subcategory_name || 'Sin subcategoría';
    if (!acc[key]) acc[key] = [];
    acc[key].push(b);
    return acc;
  }, {});
  const groupKeys = Object.keys(grouped);

  const toggleGroup = (key: string) => {
    setCollapsed(prev => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key); else next.add(key);
      return next;
    });
  };

  /* ─── Form handlers ─── */
  const updateField = (field: keyof FormData, value: string) => {
    setForm(prev => ({ ...prev, [field]: value }));
    setFormErrors(prev => { const n = { ...prev }; delete n[field]; return n; });
  };

  const handleSubmit = async () => {
    const errors: Record<string, string> = {};
    if (!form.name.trim()) errors.name = 'Nombre es requerido';
    if (!form.price || Number(form.price) <= 0) errors.price = 'Precio debe ser mayor a 0';
    if (Object.keys(errors).length > 0) { setFormErrors(errors); return; }

    setSaving(true);
    setError('');
    try {
      const body: Record<string, unknown> = {
        name: form.name.trim(),
        price: Number(form.price),
      };
      if (form.description.trim()) body.description = form.description.trim();
      if (form.cost_price) body.cost_price = Number(form.cost_price);
      if (form.stock_quantity) body.stock_quantity = Number(form.stock_quantity);
      if (form.min_stock_level) body.min_stock_level = Number(form.min_stock_level);
      if (form.subcategory_id) body.subcategory_id = Number(form.subcategory_id);
      if (form.sku.trim()) body.sku = form.sku.trim();

      const res = await apiFetch<ApiResponse<{ id: number }>>('/admin/bebidas', {
        method: 'POST',
        body: JSON.stringify(body),
      });

      const newId = res.data?.id;

      // Upload image if provided
      if (bevImage && newId) {
        try {
          const formData = new FormData();
          formData.append('image', bevImage);
          await apiFetch(`/admin/recetas/${newId}/imagen`, {
            method: 'POST',
            body: formData,
          });
        } catch { /* image upload is non-critical */ }
      }

      setForm(emptyForm);
      setBevImage(null);
      setShowAddForm(false);
      await fetchBeverages();
    } catch (e: unknown) {
      if (e instanceof Error) {
        try {
          const parsed = JSON.parse(e.message);
          if (parsed.errors) {
            const fieldErrors: Record<string, string> = {};
            for (const [k, v] of Object.entries(parsed.errors)) {
              fieldErrors[k] = Array.isArray(v) ? v[0] : String(v);
            }
            setFormErrors(fieldErrors);
          } else {
            setError(parsed.error || e.message);
          }
        } catch {
          setError(e.message);
        }
      }
    } finally {
      setSaving(false);
    }
  };

  /* ─── Loading state ─── */
  if (loading) {
    return (
      <div className="flex justify-center py-16" role="status" aria-label="Cargando bebidas">
        <Loader2 className="h-6 w-6 animate-spin text-red-500" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-sm text-gray-500">
          {beverages.length} productos en {groupKeys.length} subcategorías
        </p>
        <button
          onClick={() => setShowAddForm(!showAddForm)}
          className={cn(
            'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors min-h-[44px]',
            showAddForm
              ? 'border border-gray-200 text-gray-600 hover:bg-gray-50'
              : 'bg-red-500 text-white hover:bg-red-600'
          )}
          aria-label={showAddForm ? 'Cancelar' : 'Agregar bebida'}
        >
          {showAddForm ? <X className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
          {showAddForm ? 'Cancelar' : 'Agregar Bebida'}
        </button>
      </div>

      {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">{error}</div>}

      {/* ─── Add Beverage Form ─── */}
      {showAddForm && (
        <div className="rounded-xl border bg-white p-4 shadow-sm space-y-3" role="form" aria-label="Agregar bebida">
          <h3 className="text-sm font-medium text-gray-700">Nueva Bebida</h3>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label htmlFor="bev-name" className="block text-xs font-medium text-gray-600 mb-1">Nombre *</label>
              <input id="bev-name" type="text" value={form.name} onChange={e => updateField('name', e.target.value)}
                className={cn('w-full rounded-lg border px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300', formErrors.name ? 'border-red-300' : 'border-gray-200')}
                placeholder="Ej: Coca-Cola Lata 350ml" />
              {formErrors.name && <p className="mt-1 text-xs text-red-500">{formErrors.name}</p>}
            </div>
            <div>
              <label htmlFor="bev-price" className="block text-xs font-medium text-gray-600 mb-1">Precio *</label>
              <input id="bev-price" type="number" value={form.price} onChange={e => updateField('price', e.target.value)}
                className={cn('w-full rounded-lg border px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300', formErrors.price ? 'border-red-300' : 'border-gray-200')}
                placeholder="1200" min="1" />
              {formErrors.price && <p className="mt-1 text-xs text-red-500">{formErrors.price}</p>}
            </div>
            <div className="sm:col-span-2">
              <label htmlFor="bev-desc" className="block text-xs font-medium text-gray-600 mb-1">Descripción</label>
              <input id="bev-desc" type="text" value={form.description} onChange={e => updateField('description', e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300"
                placeholder="Descripción opcional" />
            </div>
            <div>
              <label htmlFor="bev-cost" className="block text-xs font-medium text-gray-600 mb-1">Costo</label>
              <input id="bev-cost" type="number" value={form.cost_price} onChange={e => updateField('cost_price', e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300"
                placeholder="0" min="0" />
            </div>
            <div>
              <label htmlFor="bev-stock" className="block text-xs font-medium text-gray-600 mb-1">Stock</label>
              <input id="bev-stock" type="number" value={form.stock_quantity} onChange={e => updateField('stock_quantity', e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300"
                placeholder="0" min="0" />
            </div>
            <div>
              <label htmlFor="bev-min" className="block text-xs font-medium text-gray-600 mb-1">Stock mínimo</label>
              <input id="bev-min" type="number" value={form.min_stock_level} onChange={e => updateField('min_stock_level', e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300"
                placeholder="5" min="0" />
            </div>
            <div>
              <label htmlFor="bev-sub" className="block text-xs font-medium text-gray-600 mb-1">Subcategoría</label>
              <select id="bev-sub" value={form.subcategory_id} onChange={e => updateField('subcategory_id', e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300 bg-white">
                <option value="">Sin subcategoría</option>
                {subcategories.map(sc => (
                  <option key={sc.id} value={sc.id}>{sc.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label htmlFor="bev-sku" className="block text-xs font-medium text-gray-600 mb-1">SKU</label>
              <input id="bev-sku" type="text" value={form.sku} onChange={e => updateField('sku', e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300"
                placeholder="Código opcional" />
            </div>
          </div>
          <div className="flex justify-end pt-2">
            <button onClick={handleSubmit} disabled={saving}
              className={cn('inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors min-h-[44px]',
                saving ? 'bg-gray-400 cursor-not-allowed' : 'bg-red-500 hover:bg-red-600')}
              aria-label="Guardar bebida">
              {saving && <Loader2 className="h-4 w-4 animate-spin" />}
              Guardar
            </button>
          </div>
        </div>
      )}

      {/* ─── Empty state ─── */}
      {beverages.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
          <AlertTriangle className="mx-auto h-8 w-8 text-gray-300" />
          <p className="mt-2 text-sm text-gray-500">No hay bebidas registradas.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {groupKeys.map(groupName => {
            const items = grouped[groupName];
            const isCollapsed = collapsed.has(groupName);
            const lowCount = items.filter(b => b.is_low_stock).length;

            return (
              <section key={groupName} className="rounded-xl border bg-white shadow-sm overflow-hidden" aria-label={`Subcategoría ${groupName}`}>
                {/* Group header */}
                <button
                  onClick={() => toggleGroup(groupName)}
                  className="flex w-full items-center justify-between border-b bg-gray-50 px-4 py-3 text-left hover:bg-gray-100 transition-colors min-h-[44px]"
                  aria-expanded={!isCollapsed}
                  aria-controls={`group-${groupName}`}
                >
                  <div className="flex items-center gap-2">
                    {isCollapsed
                      ? <ChevronRight className="h-4 w-4 text-gray-400" />
                      : <ChevronDown className="h-4 w-4 text-gray-400" />}
                    <span className="text-sm font-semibold text-gray-900">{groupName}</span>
                    <span className="rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-600">{items.length}</span>
                    {lowCount > 0 && (
                      <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">
                        <AlertTriangle className="h-3 w-3" /> {lowCount} bajo stock
                      </span>
                    )}
                  </div>
                </button>

                {/* Group content */}
                {!isCollapsed && (
                  <div id={`group-${groupName}`}>
                    {/* Mobile: cards */}
                    <div className="divide-y sm:hidden">
                      {items.map(b => (
                        <div key={b.id} className={cn('p-4 space-y-1', b.is_low_stock && 'bg-amber-50/50')}>
                          <div className="flex items-start justify-between">
                            <div className="min-w-0 flex-1">
                              <p className="text-sm font-medium text-gray-900 truncate">{b.name}</p>
                              {b.description && <p className="text-xs text-gray-500 truncate">{truncate(b.description, 60)}</p>}
                            </div>
                            <span className="ml-2 text-sm font-semibold tabular-nums text-gray-900 flex-shrink-0">{formatCLP(b.price)}</span>
                          </div>
                          <div className="flex flex-wrap gap-2 text-xs">
                            <span className={cn('rounded-full px-2 py-0.5', b.is_low_stock ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600')}>
                              Stock: {b.stock_quantity}
                            </span>
                            {b.sku && <span className="rounded-full bg-blue-50 px-2 py-0.5 text-blue-600">{b.sku}</span>}
                          </div>
                        </div>
                      ))}
                    </div>

                    {/* Desktop: table */}
                    <div className="hidden sm:block">
                      <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50/50 text-left text-xs font-medium text-gray-500">
                          <tr>
                            <th className="px-4 py-2">Nombre</th>
                            <th className="px-4 py-2">Descripción</th>
                            <th className="px-4 py-2 text-right">Precio</th>
                            <th className="px-4 py-2 text-right">Stock</th>
                            <th className="px-4 py-2">SKU</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y">
                          {items.map(b => (
                            <tr key={b.id} className={cn('transition-colors', b.is_low_stock && 'bg-amber-50/50')}>
                              <td className="px-4 py-2.5 font-medium text-gray-900">{b.name}</td>
                              <td className="px-4 py-2.5 text-gray-500 max-w-[200px] truncate">{truncate(b.description, 50)}</td>
                              <td className="px-4 py-2.5 text-right tabular-nums">{formatCLP(b.price)}</td>
                              <td className="px-4 py-2.5 text-right">
                                <span className={cn('inline-flex items-center gap-1 tabular-nums', b.is_low_stock && 'text-amber-700 font-medium')}>
                                  {b.is_low_stock && <AlertTriangle className="h-3 w-3" />}
                                  {b.stock_quantity}
                                </span>
                              </td>
                              <td className="px-4 py-2.5 text-gray-500 text-xs">{b.sku || '—'}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}
              </section>
            );
          })}
        </div>
      )}
    </div>
  );
}
