'use client';

import { useEffect, useState, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import {
  Loader2, Plus, AlertTriangle, X, Package,
} from 'lucide-react';
import type { ApiResponse } from '@/types';

/* ─── Types ─── */

interface LinkedProduct {
  id: number;
  name: string;
  price: number;
}

interface Beverage {
  id: number;
  name: string;
  category: string;
  unit: string;
  cost_per_unit: number;
  current_stock: number;
  min_stock_level: number;
  supplier: string | null;
  is_low_stock: boolean;
  linked_products: LinkedProduct[];
}

const UNIT_OPTIONS = ['unidad', 'L', 'ml'] as const;

/* ─── Main Component ─── */

export default function BebidasTab() {
  const [beverages, setBeverages] = useState<Beverage[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [showAddForm, setShowAddForm] = useState(false);
  const [showProductForm, setShowProductForm] = useState(false);

  const fetchBeverages = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiFetch<ApiResponse<Beverage[]>>('/admin/bebidas');
      setBeverages(res.data || []);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchBeverages(); }, [fetchBeverages]);

  if (loading) {
    return (
      <div className="flex justify-center py-16" role="status" aria-label="Cargando bebidas">
        <Loader2 className="h-6 w-6 animate-spin text-red-500" />
      </div>
    );
  }

  if (error) {
    return <div className="rounded-lg bg-red-50 p-4 text-red-600" role="alert">{error}</div>;
  }

  return (
    <div className="space-y-4">
      {/* Actions */}
      <div className="flex flex-wrap gap-2">
        <button
          onClick={() => { setShowAddForm(!showAddForm); setShowProductForm(false); }}
          className={cn(
            'inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors min-h-[44px]',
            showAddForm ? 'bg-gray-200 text-gray-700' : 'bg-red-500 text-white hover:bg-red-600'
          )}
        >
          {showAddForm ? <X className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
          Agregar Bebida
        </button>
        <button
          onClick={() => { setShowProductForm(!showProductForm); setShowAddForm(false); }}
          className={cn(
            'inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors min-h-[44px]',
            showProductForm ? 'bg-gray-200 text-gray-700' : 'bg-blue-500 text-white hover:bg-blue-600'
          )}
        >
          {showProductForm ? <X className="h-4 w-4" /> : <Package className="h-4 w-4" />}
          Agregar Producto Bebida
        </button>
      </div>

      {/* Add Beverage Form */}
      {showAddForm && (
        <AddBeverageForm
          onSuccess={() => { setShowAddForm(false); fetchBeverages(); }}
          onCancel={() => setShowAddForm(false)}
        />
      )}

      {/* Add Beverage Product Form */}
      {showProductForm && (
        <AddBeverageProductForm
          beverages={beverages}
          onSuccess={() => { setShowProductForm(false); fetchBeverages(); }}
          onCancel={() => setShowProductForm(false)}
        />
      )}

      {/* Summary */}
      <div className="text-xs text-gray-500">
        {beverages.length} bebida{beverages.length !== 1 ? 's' : ''}
        {beverages.filter(b => b.is_low_stock).length > 0 && (
          <span className="ml-2 text-amber-600">
            · {beverages.filter(b => b.is_low_stock).length} con stock bajo
          </span>
        )}
      </div>

      {/* Desktop Table */}
      <div className="hidden sm:block overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
            <tr>
              <th className="px-4 py-3">Nombre</th>
              <th className="px-4 py-3 text-right">Stock</th>
              <th className="px-4 py-3 text-right">Costo/U</th>
              <th className="px-4 py-3">Proveedor</th>
              <th className="px-4 py-3">Producto</th>
              <th className="px-4 py-3">Estado</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {beverages.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-gray-400">
                  No hay bebidas registradas
                </td>
              </tr>
            ) : (
              beverages.map(b => (
                <tr
                  key={b.id}
                  className={cn(
                    'transition-colors',
                    b.is_low_stock && 'bg-amber-50/50'
                  )}
                >
                  <td className="px-4 py-3 font-medium text-gray-900">{b.name}</td>
                  <td className="px-4 py-3 text-right tabular-nums">
                    {b.current_stock} {b.unit}
                  </td>
                  <td className="px-4 py-3 text-right tabular-nums">{formatCLP(b.cost_per_unit)}</td>
                  <td className="px-4 py-3 text-gray-500">{b.supplier || '—'}</td>
                  <td className="px-4 py-3">
                    {b.linked_products.length > 0 ? (
                      b.linked_products.map(p => (
                        <span key={p.id} className="text-sm text-gray-700">
                          {p.name} ({formatCLP(p.price)})
                        </span>
                      ))
                    ) : (
                      <span className="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                        Sin producto
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    {b.is_low_stock ? (
                      <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                        <AlertTriangle className="h-3 w-3" /> Stock bajo
                      </span>
                    ) : (
                      <span className="inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                        OK
                      </span>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Mobile Cards */}
      <div className="sm:hidden space-y-3">
        {beverages.length === 0 ? (
          <div className="rounded-xl border bg-white p-6 text-center text-gray-400">
            No hay bebidas registradas
          </div>
        ) : (
          beverages.map(b => (
            <div
              key={b.id}
              className={cn(
                'rounded-xl border bg-white p-4 shadow-sm space-y-2',
                b.is_low_stock && 'border-amber-200 bg-amber-50/30'
              )}
            >
              <div className="flex items-center justify-between">
                <span className="font-medium text-gray-900">{b.name}</span>
                {b.is_low_stock ? (
                  <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                    <AlertTriangle className="h-3 w-3" /> Bajo
                  </span>
                ) : (
                  <span className="inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                    OK
                  </span>
                )}
              </div>
              <div className="grid grid-cols-2 gap-2 text-xs text-gray-500">
                <div>Stock: <span className="font-medium text-gray-700">{b.current_stock} {b.unit}</span></div>
                <div>Costo: <span className="font-medium text-gray-700">{formatCLP(b.cost_per_unit)}</span></div>
                <div>Proveedor: <span className="font-medium text-gray-700">{b.supplier || '—'}</span></div>
                <div>
                  {b.linked_products.length > 0 ? (
                    b.linked_products.map(p => (
                      <span key={p.id} className="font-medium text-gray-700">{p.name}</span>
                    ))
                  ) : (
                    <span className="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                      Sin producto
                    </span>
                  )}
                </div>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}


/* ─── Add Beverage Form ─── */

function AddBeverageForm({ onSuccess, onCancel }: { onSuccess: () => void; onCancel: () => void }) {
  const [name, setName] = useState('');
  const [unit, setUnit] = useState<string>('unidad');
  const [costPerUnit, setCostPerUnit] = useState('');
  const [supplier, setSupplier] = useState('');
  const [minStock, setMinStock] = useState('');
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      await apiFetch('/admin/bebidas', {
        method: 'POST',
        body: JSON.stringify({
          name: name.trim(),
          unit,
          cost_per_unit: Number(costPerUnit),
          supplier: supplier.trim() || null,
          min_stock_level: minStock ? Number(minStock) : null,
        }),
      });
      onSuccess();
    } catch (e: any) {
      if (e.status === 422) {
        try {
          const body = JSON.parse(e.message);
          setErrors(body.errors || {});
        } catch {
          setErrors({ name: [e.message] });
        }
      } else {
        setErrors({ name: [e.message || 'Error al crear bebida'] });
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="rounded-xl border bg-white p-4 shadow-sm space-y-3">
      <h3 className="text-sm font-medium text-gray-700">Nueva Bebida</h3>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <label htmlFor="bev-name" className="block text-xs font-medium text-gray-500 mb-1">Nombre *</label>
          <input
            id="bev-name"
            type="text"
            value={name}
            onChange={e => setName(e.target.value)}
            required
            className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[40px]"
          />
          {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name[0]}</p>}
        </div>
        <div>
          <label htmlFor="bev-unit" className="block text-xs font-medium text-gray-500 mb-1">Unidad *</label>
          <select
            id="bev-unit"
            value={unit}
            onChange={e => setUnit(e.target.value)}
            className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[40px]"
          >
            {UNIT_OPTIONS.map(u => <option key={u} value={u}>{u}</option>)}
          </select>
          {errors.unit && <p className="mt-1 text-xs text-red-500">{errors.unit[0]}</p>}
        </div>
        <div>
          <label htmlFor="bev-cost" className="block text-xs font-medium text-gray-500 mb-1">Costo/Unidad *</label>
          <input
            id="bev-cost"
            type="number"
            value={costPerUnit}
            onChange={e => setCostPerUnit(e.target.value)}
            required
            min={1}
            step="any"
            className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[40px]"
          />
          {errors.cost_per_unit && <p className="mt-1 text-xs text-red-500">{errors.cost_per_unit[0]}</p>}
        </div>
        <div>
          <label htmlFor="bev-supplier" className="block text-xs font-medium text-gray-500 mb-1">Proveedor</label>
          <input
            id="bev-supplier"
            type="text"
            value={supplier}
            onChange={e => setSupplier(e.target.value)}
            className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[40px]"
          />
        </div>
        <div>
          <label htmlFor="bev-min-stock" className="block text-xs font-medium text-gray-500 mb-1">Stock mínimo</label>
          <input
            id="bev-min-stock"
            type="number"
            value={minStock}
            onChange={e => setMinStock(e.target.value)}
            min={0}
            step="any"
            className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[40px]"
          />
        </div>
      </div>
      <div className="flex gap-2 pt-1">
        <button
          type="submit"
          disabled={saving || !name.trim() || !costPerUnit}
          className="inline-flex items-center gap-1.5 rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-600 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed min-h-[44px]"
        >
          {saving && <Loader2 className="h-4 w-4 animate-spin" />}
          Crear Bebida
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors min-h-[44px]"
        >
          Cancelar
        </button>
      </div>
    </form>
  );
}


/* ─── Add Beverage Product Form ─── */

function AddBeverageProductForm({
  beverages,
  onSuccess,
  onCancel,
}: {
  beverages: Beverage[];
  onSuccess: () => void;
  onCancel: () => void;
}) {
  const [name, setName] = useState('');
  const [price, setPrice] = useState('');
  const [description, setDescription] = useState('');
  const [ingredientId, setIngredientId] = useState('');
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      await apiFetch('/admin/bebidas/producto', {
        method: 'POST',
        body: JSON.stringify({
          name: name.trim(),
          price: Number(price),
          description: description.trim() || null,
          ingredient_id: Number(ingredientId),
        }),
      });
      onSuccess();
    } catch (e: any) {
      if (e.status === 422) {
        try {
          const body = JSON.parse(e.message);
          setErrors(body.errors || {});
        } catch {
          setErrors({ name: [e.message] });
        }
      } else {
        setErrors({ name: [e.message || 'Error al crear producto'] });
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="rounded-xl border bg-white p-4 shadow-sm space-y-3">
      <h3 className="text-sm font-medium text-gray-700">Nuevo Producto Bebida</h3>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <label htmlFor="bp-name" className="block text-xs font-medium text-gray-500 mb-1">Nombre *</label>
          <input
            id="bp-name"
            type="text"
            value={name}
            onChange={e => setName(e.target.value)}
            required
            className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[40px]"
          />
          {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name[0]}</p>}
        </div>
        <div>
          <label htmlFor="bp-price" className="block text-xs font-medium text-gray-500 mb-1">Precio *</label>
          <input
            id="bp-price"
            type="number"
            value={price}
            onChange={e => setPrice(e.target.value)}
            required
            min={1}
            step="any"
            className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[40px]"
          />
          {errors.price && <p className="mt-1 text-xs text-red-500">{errors.price[0]}</p>}
        </div>
        <div>
          <label htmlFor="bp-ingredient" className="block text-xs font-medium text-gray-500 mb-1">Bebida (ingrediente) *</label>
          <select
            id="bp-ingredient"
            value={ingredientId}
            onChange={e => setIngredientId(e.target.value)}
            required
            className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[40px]"
          >
            <option value="">Seleccionar...</option>
            {beverages.map(b => (
              <option key={b.id} value={b.id}>{b.name}</option>
            ))}
          </select>
          {errors.ingredient_id && <p className="mt-1 text-xs text-red-500">{errors.ingredient_id[0]}</p>}
        </div>
        <div className="sm:col-span-2 lg:col-span-3">
          <label htmlFor="bp-desc" className="block text-xs font-medium text-gray-500 mb-1">Descripción</label>
          <input
            id="bp-desc"
            type="text"
            value={description}
            onChange={e => setDescription(e.target.value)}
            className="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[40px]"
          />
        </div>
      </div>
      <div className="flex gap-2 pt-1">
        <button
          type="submit"
          disabled={saving || !name.trim() || !price || !ingredientId}
          className="inline-flex items-center gap-1.5 rounded-lg bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed min-h-[44px]"
        >
          {saving && <Loader2 className="h-4 w-4 animate-spin" />}
          Crear Producto
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors min-h-[44px]"
        >
          Cancelar
        </button>
      </div>
    </form>
  );
}
