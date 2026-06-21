'use client';

import { useEffect, useState, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, Plus, AlertTriangle, X, Image as ImageIcon, Search, ToggleLeft, ToggleRight } from 'lucide-react';
import ImageQuickDrop from '@/components/admin/ImageQuickDrop';
import type { ApiResponse } from '@/types';

interface ExtraItem {
  id: number;
  name: string;
  description: string | null;
  price: number;
  cost_price: number;
  sale_price: number | null;
  category_id: number | null;
  subcategory_id: number | null;
  image_url: string | null;
  is_active: boolean;
}

interface FormData {
  name: string;
  description: string;
  price: string;
  cost_price: string;
  sale_price: string;
}

const emptyForm: FormData = {
  name: '', description: '', price: '', cost_price: '', sale_price: '',
};

export default function ExtrasTab() {
  const [extras, setExtras] = useState<ExtraItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showAddForm, setShowAddForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<FormData>(emptyForm);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [search, setSearch] = useState('');
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [dragOverRow, setDragOverRow] = useState<number | null>(null);
  const [flashIds, setFlashIds] = useState<Set<number>>(new Set());

  const fetchExtras = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiFetch<ApiResponse<ExtraItem[]>>('/admin/extras');
      setExtras(res.data || []);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Error al cargar extras');
    }
    setLoading(false);
  }, []);

  useEffect(() => { fetchExtras(); }, [fetchExtras]);

  const toggleSelect = useCallback((id: number) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }, []);

  const updateExtras = useCallback((updater: (e: ExtraItem) => ExtraItem, ids?: Set<number>) => {
    setExtras(prev => prev.map(e => (!ids || ids.has(e.id)) ? updater(e) : e));
  }, []);

  const handleToggleActive = useCallback(async (item: ExtraItem) => {
    const newActive = !item.is_active;
    updateExtras(e => e.id === item.id ? { ...e, is_active: newActive } : e, new Set([item.id]));
    try {
      await apiFetch(`/admin/extras/${item.id}`, {
        method: 'PUT',
        body: JSON.stringify({ is_active: newActive }),
      });
      setFlashIds(new Set([item.id]));
      setTimeout(() => setFlashIds(new Set()), 800);
    } catch {
      updateExtras(e => e.id === item.id ? { ...e, is_active: !newActive } : e, new Set([item.id]));
    }
  }, [updateExtras]);

  const handleQuickImageUpload = useCallback(async (productId: number, file: File) => {
    const formData = new FormData();
    formData.append('image', file);
    const res = await apiFetch<ApiResponse<{ image_url: string }>>(`/admin/recetas/${productId}/imagen`, {
      method: 'POST',
      body: formData,
    });
    if (res.data?.image_url) {
      setExtras(prev => prev.map(e => e.id === productId ? { ...e, image_url: res.data!.image_url } : e));
    }
  }, []);

  const updateField = (field: keyof FormData, value: string) => {
    setForm(prev => ({ ...prev, [field]: value }));
    setFormErrors(prev => { const n = { ...prev }; delete n[field]; return n; });
  };

  const openEdit = (item: ExtraItem) => {
    setEditingId(item.id);
    setForm({
      name: item.name,
      description: item.description || '',
      price: String(item.price),
      cost_price: String(item.cost_price || ''),
      sale_price: item.sale_price ? String(item.sale_price) : '',
    });
    setShowAddForm(true);
    setFormErrors({});
  };

  const handleSubmit = async () => {
    const errors: Record<string, string> = {};
    if (!form.name.trim()) errors.name = 'Nombre requerido';
    if (!form.price || Number(form.price) <= 0) errors.price = 'Precio debe ser > 0';
    if (Object.keys(errors).length > 0) { setFormErrors(errors); return; }

    setSaving(true);
    setError('');
    try {
      const body: Record<string, unknown> = { name: form.name.trim(), price: Number(form.price) };
      if (form.description.trim()) body.description = form.description.trim();
      if (form.cost_price) body.cost_price = Number(form.cost_price);
      if (form.sale_price) body.sale_price = Number(form.sale_price);

      if (editingId) {
        await apiFetch(`/admin/extras/${editingId}`, {
          method: 'PUT',
          body: JSON.stringify(body),
        });
      } else {
        await apiFetch('/admin/extras', {
          method: 'POST',
          body: JSON.stringify(body),
        });
      }

      setForm(emptyForm);
      setShowAddForm(false);
      setEditingId(null);
      await fetchExtras();
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
    }
    setSaving(false);
  };

  const filtered = extras.filter(e =>
    !search || e.name.toLowerCase().includes(search.toLowerCase())
  );

  if (loading) {
    return <div className="flex items-center justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-red-500" /></div>;
  }

  return (
    <div className="space-y-4">
      {error && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700">
          <AlertTriangle className="h-4 w-4" />{error}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input
            type="text" value={search} onChange={e => setSearch(e.target.value)}
            placeholder="Buscar extra..." className="w-64 rounded-lg border py-2 pl-9 pr-3 text-sm"
          />
        </div>
        <button onClick={() => { setShowAddForm(true); setEditingId(null); setForm(emptyForm); setFormErrors({}); }}
          className="flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-sm text-white hover:bg-red-700">
          <Plus className="h-4 w-4" />Nuevo Extra
        </button>
      </div>

      {showAddForm && (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <h3 className="mb-3 text-sm font-semibold text-gray-700">
            {editingId ? 'Editar Extra' : 'Nuevo Extra'}
          </h3>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <label className="block text-xs font-medium text-gray-600">Nombre *</label>
              <input type="text" value={form.name} onChange={e => updateField('name', e.target.value)}
                className={cn('w-full rounded border px-2 py-1.5 text-sm', formErrors.name && 'border-red-400')} />
              {formErrors.name && <p className="mt-0.5 text-xs text-red-500">{formErrors.name}</p>}
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600">Precio *</label>
              <input type="number" value={form.price} onChange={e => updateField('price', e.target.value)}
                className={cn('w-full rounded border px-2 py-1.5 text-sm', formErrors.price && 'border-red-400')} />
              {formErrors.price && <p className="mt-0.5 text-xs text-red-500">{formErrors.price}</p>}
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600">Costo</label>
              <input type="number" value={form.cost_price} onChange={e => updateField('cost_price', e.target.value)}
                className="w-full rounded border px-2 py-1.5 text-sm" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-600">Precio Oferta</label>
              <input type="number" value={form.sale_price} onChange={e => updateField('sale_price', e.target.value)}
                className="w-full rounded border px-2 py-1.5 text-sm" />
            </div>
          </div>
          <div className="mt-3">
            <label className="block text-xs font-medium text-gray-600">Descripción</label>
            <textarea value={form.description} onChange={e => updateField('description', e.target.value)}
              className="w-full rounded border px-2 py-1.5 text-sm" rows={2} />
          </div>
          <div className="mt-3 flex gap-2">
            <button onClick={handleSubmit} disabled={saving}
              className="rounded-lg bg-red-600 px-4 py-1.5 text-sm text-white disabled:opacity-50">
              {saving ? 'Guardando...' : editingId ? 'Actualizar' : 'Crear'}
            </button>
            <button onClick={() => { setShowAddForm(false); setEditingId(null); }}
              className="rounded-lg bg-gray-200 px-4 py-1.5 text-sm">Cancelar</button>
          </div>
        </div>
      )}

      <div className="rounded-xl border bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-xs text-gray-500">
                <th className="w-8 px-3 py-3"><input type="checkbox" className="accent-red-500" /></th>
                <th className="px-3 py-3">Imagen</th>
                <th className="px-3 py-3">Nombre</th>
                <th className="px-3 py-3">Precio</th>
                <th className="px-3 py-3">Costo</th>
                <th className="px-3 py-3">Oferta</th>
                <th className="px-3 py-3 w-20">Activo</th>
                <th className="px-3 py-3 w-20"></th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {filtered.map(item => (
                <tr key={item.id}
                  onDragOver={e => { e.preventDefault(); setDragOverRow(item.id); }}
                  onDragLeave={() => setDragOverRow(null)}
                  onDrop={e => { e.preventDefault(); setDragOverRow(null); const f = e.dataTransfer.files[0]; if (f) handleQuickImageUpload(item.id, f); }}
                  className={cn(
                    'transition-colors',
                    !item.is_active && 'opacity-50',
                    dragOverRow === item.id ? 'ring-2 ring-red-400 bg-red-50' : 'hover:bg-gray-50',
                    flashIds.has(item.id) && 'bg-green-100 transition-colors duration-500'
                  )}>
                  <td className="px-3 py-2">
                    <input type="checkbox" checked={selectedIds.has(item.id)} onChange={() => toggleSelect(item.id)}
                      className="accent-red-500" />
                  </td>
                  <td className="px-3 py-2">
                    <ImageQuickDrop
                      imageUrl={item.image_url}
                      productName={item.name}
                      onUpload={async (file) => handleQuickImageUpload(item.id, file)}
                      size={36}
                    />
                  </td>
                  <td className="px-3 py-2 font-medium">{item.name}</td>
                  <td className="px-3 py-2">{formatCLP(item.price)}</td>
                  <td className="px-3 py-2">{item.cost_price > 0 ? formatCLP(item.cost_price) : '-'}</td>
                  <td className="px-3 py-2">{item.sale_price ? formatCLP(item.sale_price) : '-'}</td>
                  <td className="px-3 py-2">
                    <button onClick={() => handleToggleActive(item)}
                      className={cn(
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold transition-colors min-h-[28px]',
                        item.is_active
                          ? 'bg-green-100 text-green-700 hover:bg-green-200'
                          : 'bg-red-100 text-red-600 hover:bg-red-200'
                      )}
                      aria-label={item.is_active ? `Desactivar ${item.name}` : `Activar ${item.name}`}>
                      {item.is_active
                        ? <><ToggleRight className="h-3 w-3" /> ON</>
                        : <><ToggleLeft className="h-3 w-3" /> OFF</>
                      }
                    </button>
                  </td>
                  <td className="px-3 py-2">
                    <button onClick={() => openEdit(item)}
                      className="text-xs text-blue-600 hover:underline">Editar</button>
                  </td>
                </tr>
              ))}
              {filtered.length === 0 && (
                <tr><td colSpan={8} className="px-3 py-8 text-center text-sm text-gray-400">Sin extras</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
