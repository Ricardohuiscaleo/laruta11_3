'use client';

import { useEffect, useState, useCallback, useRef, useMemo } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, Plus, AlertTriangle, X, ChevronDown, ChevronRight, Image as ImageIcon, Search, ToggleLeft, ToggleRight } from 'lucide-react';
import BulkActionBar from '@/components/admin/BulkActionBar';
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
  const [search, setSearch] = useState('');
  const [filterSubcategory, setFilterSubcategory] = useState('');
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());

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

  /* ─── Selection helpers ─── */

  const toggleSelect = useCallback((id: number) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }, []);

  /* ─── Single toggle ON/OFF ─── */

  const handleSingleToggle = useCallback(async (productId: number) => {
    try {
      await apiFetch('/admin/productos/toggle', {
        method: 'PATCH',
        body: JSON.stringify({ product_ids: [productId] }),
      });
      await fetchBeverages();
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Error al cambiar estado');
    }
  }, [fetchBeverages]);

  /* ─── Bulk action handlers ─── */

  const handleBulkToggle = useCallback(async () => {
    if (selectedIds.size === 0) return;
    try {
      await apiFetch('/admin/productos/toggle', {
        method: 'PATCH',
        body: JSON.stringify({ product_ids: Array.from(selectedIds) }),
      });
      setSelectedIds(new Set());
      await fetchBeverages();
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Error al cambiar estado');
    }
  }, [selectedIds, fetchBeverages]);

  const handleBulkPrice = useCallback(async (amount: number) => {
    if (selectedIds.size === 0) return;
    try {
      await apiFetch('/admin/productos/bulk-price', {
        method: 'PATCH',
        body: JSON.stringify({ product_ids: Array.from(selectedIds), adjustment: amount }),
      });
      setSelectedIds(new Set());
      await fetchBeverages();
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Error al ajustar precios');
    }
  }, [selectedIds, fetchBeverages]);

  const handleBulkDeactivate = useCallback(async () => {
    if (selectedIds.size === 0) return;
    try {
      await apiFetch('/admin/productos/bulk-deactivate', {
        method: 'PATCH',
        body: JSON.stringify({ product_ids: Array.from(selectedIds) }),
      });
      setSelectedIds(new Set());
      await fetchBeverages();
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Error al desactivar productos');
    }
  }, [selectedIds, fetchBeverages]);

  /* ─── Group by subcategory ─── */
  const filtered = useMemo(() => {
    let result = beverages;
    if (filterSubcategory) {
      result = result.filter(b => (b.subcategory_name || 'Sin subcategoría') === filterSubcategory);
    }
    const q = search.toLowerCase();
    return q ? result.filter(b => b.name.toLowerCase().includes(q)) : result;
  }, [beverages, search, filterSubcategory]);

  const grouped = filtered.reduce<Record<string, Beverage[]>>((acc, b) => {
    const key = b.subcategory_name || 'Sin subcategoría';
    if (!acc[key]) acc[key] = [];
    acc[key].push(b);
    return acc;
  }, {});
  const groupKeys = Object.keys(grouped);

  const allVisibleIds = useMemo(
    () => filtered.map(b => b.id),
    [filtered]
  );

  const allSelected = allVisibleIds.length > 0 && allVisibleIds.every(id => selectedIds.has(id));

  const toggleSelectAll = useCallback(() => {
    if (allSelected) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(allVisibleIds));
    }
  }, [allSelected, allVisibleIds]);

  const subcategoryNames = useMemo(() => {
    const names = new Set(beverages.map(b => b.subcategory_name || 'Sin subcategoría'));
    return Array.from(names).sort((a, b) => a.localeCompare(b, 'es'));
  }, [beverages]);

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
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Buscar bebida..."
            className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[44px]"
            aria-label="Buscar bebida"
          />
        </div>
        <select
          value={filterSubcategory}
          onChange={e => setFilterSubcategory(e.target.value)}
          className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm min-h-[44px] focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 flex-shrink-0"
          aria-label="Filtrar por subcategoría"
        >
          <option value="">Todas las subcategorías</option>
          {subcategoryNames.map(name => (
            <option key={name} value={name}>{name}</option>
          ))}
        </select>
      </div>

      <div className="text-xs text-gray-500">
        {filtered.length} bebida{filtered.length !== 1 ? 's' : ''} en {groupKeys.length} subcategoría{groupKeys.length !== 1 ? 's' : ''}
      </div>

      {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">{error}</div>}

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
                      {items.map(b => {
                        const isActive = b.is_active !== false && (b.is_active as unknown) !== 0;
                        const isSelected = selectedIds.has(b.id);
                        return (
                        <div key={b.id} className={cn('p-4 space-y-1', b.is_low_stock && 'bg-amber-50/50', !isActive && 'opacity-50')}>
                          <div className="flex items-start gap-3">
                            <input
                              type="checkbox"
                              checked={isSelected}
                              onChange={() => toggleSelect(b.id)}
                              className="mt-1 h-4 w-4 rounded border-gray-300 text-red-500 focus:ring-red-400 cursor-pointer"
                              aria-label={`Seleccionar ${b.name}`}
                            />
                            <div className="min-w-0 flex-1">
                              <p className="text-sm font-medium text-gray-900 truncate">{b.name}</p>
                              {b.description && <p className="text-xs text-gray-500 truncate">{truncate(b.description, 60)}</p>}
                            </div>
                            <button
                              type="button"
                              onClick={() => handleSingleToggle(b.id)}
                              className={cn(
                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold transition-colors min-h-[28px] flex-shrink-0',
                                isActive
                                  ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                  : 'bg-red-100 text-red-600 hover:bg-red-200'
                              )}
                              aria-label={isActive ? `Desactivar ${b.name}` : `Activar ${b.name}`}
                            >
                              {isActive
                                ? <><ToggleRight className="h-3 w-3" /> ON</>
                                : <><ToggleLeft className="h-3 w-3" /> OFF</>
                              }
                            </button>
                            <span className="ml-2 text-sm font-semibold tabular-nums text-gray-900 flex-shrink-0">{formatCLP(b.price)}</span>
                          </div>
                          <div className="flex flex-wrap gap-2 text-xs pl-7">
                            <span className={cn('rounded-full px-2 py-0.5', b.is_low_stock ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600')}>
                              Stock: {b.stock_quantity}
                            </span>
                            {b.sku && <span className="rounded-full bg-blue-50 px-2 py-0.5 text-blue-600">{b.sku}</span>}
                          </div>
                        </div>
                        );
                      })}
                    </div>

                    {/* Desktop: table */}
                    <div className="hidden sm:block">
                      <table className="w-full text-sm">
                        <thead className="border-b bg-gray-50/50 text-left text-xs font-medium text-gray-500">
                          <tr>
                            <th className="px-2 py-2 w-10">
                              <input
                                type="checkbox"
                                checked={allSelected}
                                onChange={toggleSelectAll}
                                className="h-4 w-4 rounded border-gray-300 text-red-500 focus:ring-red-400 cursor-pointer"
                                aria-label="Seleccionar todas las bebidas"
                              />
                            </th>
                            <th className="px-4 py-2">Nombre</th>
                            <th className="px-2 py-2 w-16 text-center">Estado</th>
                            <th className="px-4 py-2">Descripción</th>
                            <th className="px-4 py-2 text-right">Precio</th>
                            <th className="px-4 py-2 text-right">Stock</th>
                            <th className="px-4 py-2">SKU</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y">
                          {items.map(b => {
                            const isActive = b.is_active !== false && (b.is_active as unknown) !== 0;
                            const isSelected = selectedIds.has(b.id);
                            return (
                            <tr key={b.id} className={cn('transition-colors', b.is_low_stock && 'bg-amber-50/50', !isActive && 'opacity-50')}>
                              <td className="px-2 py-2.5 w-10">
                                <input
                                  type="checkbox"
                                  checked={isSelected}
                                  onChange={() => toggleSelect(b.id)}
                                  className="h-4 w-4 rounded border-gray-300 text-red-500 focus:ring-red-400 cursor-pointer"
                                  aria-label={`Seleccionar ${b.name}`}
                                />
                              </td>
                              <td className="px-4 py-2.5 font-medium text-gray-900">{b.name}</td>
                              <td className="px-2 py-2.5 text-center">
                                <button
                                  type="button"
                                  onClick={() => handleSingleToggle(b.id)}
                                  className={cn(
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold transition-colors min-h-[28px]',
                                    isActive
                                      ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                      : 'bg-red-100 text-red-600 hover:bg-red-200'
                                  )}
                                  aria-label={isActive ? `Desactivar ${b.name}` : `Activar ${b.name}`}
                                >
                                  {isActive
                                    ? <><ToggleRight className="h-3 w-3" /> ON</>
                                    : <><ToggleLeft className="h-3 w-3" /> OFF</>
                                  }
                                </button>
                              </td>
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
                            );
                          })}
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

      {/* Bulk Action Bar */}
      <BulkActionBar
        selectedCount={selectedIds.size}
        onClear={() => setSelectedIds(new Set())}
        onToggle={handleBulkToggle}
        onPriceAdjust={handleBulkPrice}
        onDeactivate={handleBulkDeactivate}
      />
    </div>
  );
}
