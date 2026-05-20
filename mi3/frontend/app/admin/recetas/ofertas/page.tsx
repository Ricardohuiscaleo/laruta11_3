'use client';

import { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { cn, formatCLP } from '@/lib/utils';
import {
  Loader2,
  Search,
  Tag,
  ChevronDown,
  ChevronRight,
  Percent,
  RotateCcw,
  CheckCircle2,
  AlertCircle,
  Sparkles,
  X,
} from 'lucide-react';
import type { ApiResponse } from '@/types';

interface ProductOffer {
  id: number;
  name: string;
  price: number;
  sale_price: number | null;
  is_featured: boolean;
  discount_percent: number;
}

interface CategoryGroup {
  category_id: number;
  category_name: string;
  products: ProductOffer[];
}

interface ApplyPayload {
  product_ids: number[];
  discount_percent: number;
  round_to: number;
}

export default function OfertasPage() {
  const [groups, setGroups] = useState<CategoryGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [expandedCategories, setExpandedCategories] = useState<Set<number>>(new Set());
  const [discountInputs, setDiscountInputs] = useState<Record<number, number>>({});
  const [applying, setApplying] = useState<Set<number>>(new Set());
  const [successMsg, setSuccessMsg] = useState('');
  const [categoryDiscounts, setCategoryDiscounts] = useState<Record<number, number>>({});
  const [applyingCategory, setApplyingCategory] = useState<Set<number>>(new Set());

  const fetchOffers = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params = search ? `?search=${encodeURIComponent(search)}` : '';
      const res = await apiFetch<ApiResponse<CategoryGroup[]>>(`/admin/offers${params}`);
      setGroups(res.data || []);
      setExpandedCategories(new Set((res.data || []).map(g => g.category_id)));
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Error al cargar productos');
    } finally {
      setLoading(false);
    }
  }, [search]);

  useEffect(() => {
    fetchOffers();
  }, [fetchOffers]);

  const toggleCategory = (id: number) => {
    setExpandedCategories(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const handleToggleProduct = async (product: ProductOffer) => {
    const productId = product.id;
    setApplying(prev => new Set(prev).add(productId));

    try {
      if (product.is_featured) {
        await apiFetch<ApiResponse<unknown>>('/admin/offers/remove', {
          method: 'POST',
          body: JSON.stringify({ product_ids: [productId] }),
        });
        setSuccessMsg(`Oferta removida de "${product.name}"`);
      } else {
        const discount = discountInputs[productId] || 10;
        const payload: ApplyPayload = {
          product_ids: [productId],
          discount_percent: discount,
          round_to: 10,
        };
        await apiFetch<ApiResponse<unknown>>('/admin/offers/apply', {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        setSuccessMsg(`Oferta aplicada: ${discount}% OFF en "${product.name}"`);
      }
      await fetchOffers();
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Error al cambiar oferta');
    } finally {
      setApplying(prev => {
        const next = new Set(prev);
        next.delete(productId);
        return next;
      });
    }
  };

  const handleCategoryToggle = async (group: CategoryGroup) => {
    const catId = group.category_id;
    setApplyingCategory(prev => new Set(prev).add(catId));

    try {
      const allFeatured = group.products.every(p => p.is_featured);

      if (allFeatured) {
        await apiFetch<ApiResponse<unknown>>('/admin/offers/remove-category', {
          method: 'POST',
          body: JSON.stringify({ category_id: catId }),
        });
        setSuccessMsg(`Ofertas removidas de "${group.category_name}"`);
      } else {
        const discount = categoryDiscounts[catId] || 10;
        await apiFetch<ApiResponse<unknown>>('/admin/offers/apply-category', {
          method: 'POST',
          body: JSON.stringify({
            category_id: catId,
            discount_percent: discount,
            round_to: 10,
          }),
        });
        setSuccessMsg(`Ofertas aplicadas: ${discount}% OFF en toda la categoría "${group.category_name}"`);
      }
      await fetchOffers();
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Error al cambiar ofertas de categoría');
    } finally {
      setApplyingCategory(prev => {
        const next = new Set(prev);
        next.delete(catId);
        return next;
      });
    }
  };

  const clearMessages = () => {
    setError('');
    setSuccessMsg('');
  };

  const featuredCount = groups.reduce((sum, g) =>
    sum + g.products.filter(p => p.is_featured).length, 0
  );

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h2 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
            <Tag className="w-5 h-5 text-red-500" />
            Gestión de Ofertas
          </h2>
          <p className="text-sm text-gray-500 mt-0.5">
            {loading ? 'Cargando...' : `${featuredCount} producto${featuredCount !== 1 ? 's' : ''} en oferta`}
          </p>
        </div>
        <button
          onClick={fetchOffers}
          className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors"
        >
          <RotateCcw className="w-4 h-4" />
          Recargar
        </button>
      </div>

      {/* Alerts */}
      {error && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">
          <AlertCircle className="w-4 h-4 shrink-0" />
          <span className="flex-1">{error}</span>
          <button onClick={clearMessages} className="p-0.5 hover:bg-red-100 rounded">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}
      {successMsg && (
        <div className="flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-600" role="status">
          <CheckCircle2 className="w-4 h-4 shrink-0" />
          <span className="flex-1">{successMsg}</span>
          <button onClick={clearMessages} className="p-0.5 hover:bg-green-100 rounded">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* Search */}
      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
        <input
          type="text"
          placeholder="Buscar producto..."
          value={search}
          onChange={e => setSearch(e.target.value)}
          className="w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-4 text-sm
                     focus:border-red-300 focus:outline-none focus:ring-2 focus:ring-red-100 transition-colors"
        />
      </div>

      {/* Content */}
      {loading ? (
        <div className="flex items-center justify-center py-16" role="status" aria-label="Cargando">
          <Loader2 className="h-6 w-6 animate-spin text-red-500" />
        </div>
      ) : groups.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-gray-400">
          <Tag className="w-12 h-12 mb-3" />
          <p className="text-sm">No se encontraron productos</p>
        </div>
      ) : (
        <div className="space-y-3">
          {groups.map(group => {
            const allFeatured = group.products.length > 0 && group.products.every(p => p.is_featured);
            const isExpanded = expandedCategories.has(group.category_id);

            return (
              <div key={group.category_id} className="rounded-xl border bg-white shadow-sm overflow-hidden">
                {/* Category header */}
                <button
                  onClick={() => toggleCategory(group.category_id)}
                  className="flex items-center gap-2 w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors"
                >
                  {isExpanded ? <ChevronDown className="w-4 h-4 text-gray-400" /> : <ChevronRight className="w-4 h-4 text-gray-400" />}
                  <span className="font-medium text-gray-900">{group.category_name}</span>
                  <span className="text-xs text-gray-400 ml-1">
                    ({group.products.filter(p => p.is_featured).length}/{group.products.length} en oferta)
                  </span>
                  <div className="ml-auto flex items-center gap-2">
                    <button
                      onClick={e => {
                        e.stopPropagation();
                        handleCategoryToggle(group);
                      }}
                      disabled={applyingCategory.has(group.category_id)}
                      className={cn(
                        'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                        allFeatured
                          ? 'bg-red-100 text-red-700 hover:bg-red-200'
                          : 'bg-green-100 text-green-700 hover:bg-green-200'
                      )}
                    >
                      {applyingCategory.has(group.category_id) ? (
                        <Loader2 className="w-3 h-3 animate-spin" />
                      ) : allFeatured ? (
                        'Desactivar todas'
                      ) : (
                        'Ofertar todas'
                      )}
                    </button>
                    <div className="flex items-center gap-1">
                      <Percent className="w-3 h-3 text-gray-400" />
                      <input
                        type="number"
                        min={1}
                        max={99}
                        value={categoryDiscounts[group.category_id] ?? 10}
                        onClick={e => e.stopPropagation()}
                        onChange={e => {
                          e.stopPropagation();
                          setCategoryDiscounts(prev => ({ ...prev, [group.category_id]: Number(e.target.value) }));
                        }}
                        className="w-14 rounded border border-gray-200 px-1.5 py-1 text-xs text-center
                                   focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-100"
                      />
                    </div>
                  </div>
                </button>

                {/* Products */}
                {isExpanded && (
                  <div className="border-t border-gray-100 divide-y divide-gray-50">
                    {group.products.map(product => {
                      const isOnOffer = product.is_featured && product.sale_price !== null;
                      const discount = discountInputs[product.id] ?? (product.discount_percent || 10);
                      const previewPrice = isOnOffer
                        ? product.sale_price!
                        : Math.floor(product.price * (1 - discount / 100) / 10) * 10;

                      return (
                        <div key={product.id} className="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50/50 transition-colors">
                          {/* Toggle */}
                          <button
                            onClick={() => handleToggleProduct(product)}
                            disabled={applying.has(product.id)}
                            className={cn(
                              'relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200',
                              'focus:outline-none focus:ring-2 focus:ring-red-300 focus:ring-offset-1',
                              isOnOffer ? 'bg-red-500' : 'bg-gray-200'
                            )}
                            role="switch"
                            aria-checked={isOnOffer}
                          >
                            <span
                              className={cn(
                                'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition duration-200',
                                isOnOffer ? 'translate-x-5' : 'translate-x-0'
                              )}
                            />
                          </button>

                          {/* Product info */}
                          <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-gray-900 truncate">{product.name}</p>
                            <div className="flex items-center gap-2 text-xs text-gray-500">
                              <span>{formatCLP(product.price)}</span>
                              {isOnOffer ? (
                                <>
                                  <span className="text-gray-300">→</span>
                                  <span className="font-semibold text-red-600">{formatCLP(product.sale_price!)}</span>
                                  <span className="rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-700">
                                    -{product.discount_percent}%
                                  </span>
                                </>
                              ) : (
                                <>
                                  <span className="text-gray-300">→</span>
                                  <span className="text-gray-400">{formatCLP(previewPrice)}</span>
                                </>
                              )}
                            </div>
                          </div>

                          {/* Discount input */}
                          <div className="flex items-center gap-1">
                            <Percent className="w-3 h-3 text-gray-400" />
                            <input
                              type="number"
                              min={1}
                              max={99}
                              value={discount}
                              onChange={e => {
                                const val = Number(e.target.value);
                                setDiscountInputs(prev => ({ ...prev, [product.id]: val }));
                              }}
                              className="w-14 rounded border border-gray-200 px-1.5 py-1 text-xs text-center
                                         focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-100
                                         disabled:bg-gray-50 disabled:text-gray-400"
                              disabled={applying.has(product.id)}
                            />
                          </div>

                          {/* Quick toggle */}
                          <button
                            onClick={() => handleToggleProduct(product)}
                            disabled={applying.has(product.id)}
                            className={cn(
                              'inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors',
                              isOnOffer
                                ? 'bg-red-50 text-red-600 hover:bg-red-100'
                                : 'bg-green-50 text-green-600 hover:bg-green-100',
                              applying.has(product.id) && 'opacity-50'
                            )}
                          >
                            {applying.has(product.id) ? (
                              <Loader2 className="w-3 h-3 animate-spin" />
                            ) : isOnOffer ? (
                              'Quitar'
                            ) : (
                              <Sparkles className="w-3 h-3" />
                            )}
                          </button>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
