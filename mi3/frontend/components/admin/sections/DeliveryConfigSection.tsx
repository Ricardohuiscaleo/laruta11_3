'use client';

import { useEffect, useState, useCallback, useMemo } from 'react';
import { apiFetch, ApiError } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, Save, RefreshCw, AlertCircle, CheckCircle2, Calculator } from 'lucide-react';

/* ─── Types ─── */

interface DeliveryConfigItem {
  config_key: string;
  config_value: string;
  description: string;
  updated_by: string | null;
  updated_at: string | null;
}

interface ConfigMeta {
  label: string;
  description: string;
  type: 'integer' | 'float';
  suffix?: string;
  min?: number;
  max?: number;
}

/* ─── Parameter metadata ─── */

const CONFIG_META: Record<string, ConfigMeta> = {
  tarifa_base: { label: 'Tarifa Base', description: 'Monto base cobrado por delivery (pesos)', type: 'integer', suffix: '$', min: 0 },
  card_surcharge: { label: 'Recargo Tarjeta', description: 'Recargo adicional por pago con tarjeta en delivery (pesos)', type: 'integer', suffix: '$', min: 0 },
  distance_threshold_km: { label: 'Umbral Distancia', description: 'Distancia en km sin recargo adicional', type: 'integer', suffix: 'km', min: 0 },
  surcharge_per_bracket: { label: 'Recargo por Tramo', description: 'Monto cobrado por cada tramo adicional de distancia (pesos)', type: 'integer', suffix: '$', min: 0 },
  bracket_size_km: { label: 'Tamaño Tramo', description: 'Tamaño en km de cada tramo de distancia', type: 'integer', suffix: 'km', min: 1 },
  rl6_discount_factor: { label: 'Factor Descuento RL6', description: 'Factor de descuento RL6 (0.2857 = 28.57%)', type: 'float', min: 0, max: 1 },
};

const PARAM_ORDER = ['tarifa_base', 'card_surcharge', 'distance_threshold_km', 'surcharge_per_bracket', 'bracket_size_km', 'rl6_discount_factor'];

/* ─── Validation ─── */

function validateField(key: string, value: string): string | null {
  const meta = CONFIG_META[key];
  if (!meta) return null;
  if (!value.trim()) return 'Este campo es requerido';
  if (meta.type === 'integer') {
    if (!/^-?\d+$/.test(value.trim())) return 'Debe ser un número entero';
    const num = parseInt(value, 10);
    if (meta.min !== undefined && num < meta.min) return `Mínimo: ${meta.min}`;
    if (meta.max !== undefined && num > meta.max) return `Máximo: ${meta.max}`;
  } else if (meta.type === 'float') {
    if (!/^-?\d+(\.\d+)?$/.test(value.trim())) return 'Debe ser un número';
    const num = parseFloat(value);
    if (meta.min !== undefined && num < meta.min) return `Mínimo: ${meta.min}`;
    if (meta.max !== undefined && num > meta.max) return `Máximo: ${meta.max}`;
  }
  return null;
}

/* ─── Delivery fee calculation preview ─── */

function calcDeliveryPreview(values: Record<string, string>) {
  const tarifaBase = parseInt(values.tarifa_base || '0', 10) || 0;
  const cardSurcharge = parseInt(values.card_surcharge || '0', 10) || 0;
  const threshold = parseInt(values.distance_threshold_km || '0', 10) || 0;
  const surchargePerBracket = parseInt(values.surcharge_per_bracket || '0', 10) || 0;
  const bracketSize = parseInt(values.bracket_size_km || '1', 10) || 1;
  const exampleDist = 8;
  const extraKm = Math.max(0, exampleDist - threshold);
  const brackets = Math.ceil(extraKm / bracketSize);
  const distSurcharge = brackets * surchargePerBracket;
  const total = tarifaBase + distSurcharge + cardSurcharge;
  return { exampleDist, tarifaBase, threshold, bracketSize, surchargePerBracket, brackets, distSurcharge, cardSurcharge, total };
}

function formatDateTime(dateStr: string | null): string {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('es-CL', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/* ─── Component ─── */

export default function DeliveryConfigSection() {
  const [items, setItems] = useState<DeliveryConfigItem[]>([]);
  const [editValues, setEditValues] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');
  const [saveError, setSaveError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const fetchConfig = useCallback(async () => {
    setLoading(true);
    setFetchError('');
    try {
      const res = await apiFetch<{ success: boolean; items: DeliveryConfigItem[] }>('/admin/delivery-config');
      const data = res.items || [];
      setItems(data);
      const vals: Record<string, string> = {};
      for (const item of data) vals[item.config_key] = item.config_value;
      setEditValues(vals);
      setErrors({});
      setFieldErrors({});
    } catch (e: any) {
      setFetchError(e.message || 'Error al cargar configuración');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchConfig(); }, [fetchConfig]);

  useEffect(() => {
    if (!successMsg) return;
    const t = setTimeout(() => setSuccessMsg(''), 4000);
    return () => clearTimeout(t);
  }, [successMsg]);

  const handleChange = useCallback((key: string, value: string) => {
    setEditValues(prev => ({ ...prev, [key]: value }));
    const err = validateField(key, value);
    setErrors(prev => {
      const next = { ...prev };
      if (err) next[key] = err; else delete next[key];
      return next;
    });
    setFieldErrors(prev => {
      if (!prev[key]) return prev;
      const next = { ...prev };
      delete next[key];
      return next;
    });
  }, []);

  const hasChanges = useMemo(() => items.some(item => editValues[item.config_key] !== item.config_value), [items, editValues]);
  const hasValidationErrors = Object.keys(errors).length > 0;

  const handleSave = useCallback(async () => {
    const newErrors: Record<string, string> = {};
    for (const key of PARAM_ORDER) {
      const err = validateField(key, editValues[key] || '');
      if (err) newErrors[key] = err;
    }
    if (Object.keys(newErrors).length > 0) { setErrors(newErrors); return; }

    setSaving(true);
    setSaveError('');
    setFieldErrors({});
    try {
      const changedItems = items
        .filter(item => editValues[item.config_key] !== item.config_value)
        .map(item => ({ config_key: item.config_key, config_value: editValues[item.config_key] }));
      if (changedItems.length === 0) return;

      const res = await apiFetch<{ success: boolean; items: DeliveryConfigItem[] }>('/admin/delivery-config', {
        method: 'PUT',
        body: JSON.stringify({ items: changedItems }),
      });
      const data = res.items || [];
      setItems(data);
      const vals: Record<string, string> = {};
      for (const item of data) vals[item.config_key] = item.config_value;
      setEditValues(vals);
      setSuccessMsg('Configuración guardada');
    } catch (e: any) {
      if (e instanceof ApiError && e.status === 422) {
        setSaveError(e.message || 'Error de validación');
      } else {
        setSaveError(e.message || 'Error al guardar');
      }
    } finally {
      setSaving(false);
    }
  }, [editValues, items]);

  const preview = useMemo(() => calcDeliveryPreview(editValues), [editValues]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20" role="status" aria-label="Cargando configuración">
        <Loader2 className="h-8 w-8 animate-spin text-red-500" />
      </div>
    );
  }

  if (fetchError) {
    return (
      <div className="rounded-xl border bg-white p-8 text-center shadow-sm space-y-4">
        <AlertCircle className="h-10 w-10 text-red-400 mx-auto" />
        <p className="text-sm text-red-600">{fetchError}</p>
        <button
          onClick={fetchConfig}
          className="inline-flex items-center gap-2 rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-600"
        >
          <RefreshCw className="h-4 w-4" /> Reintentar
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="hidden md:block text-2xl font-bold text-gray-900">Config Delivery</h1>
      </div>

      {successMsg && (
        <div className="fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg" role="status">
          <CheckCircle2 className="h-4 w-4" />
          {successMsg}
        </div>
      )}

      {saveError && (
        <div className="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700 flex items-center gap-2" role="alert">
          <AlertCircle className="h-4 w-4 shrink-0" />
          {saveError}
        </div>
      )}

      <div className="rounded-xl border bg-white shadow-sm divide-y">
        {PARAM_ORDER.map(key => {
          const meta = CONFIG_META[key];
          const item = items.find(i => i.config_key === key);
          const value = editValues[key] ?? '';
          const error = errors[key] || fieldErrors[key];
          const changed = item && value !== item.config_value;

          return (
            <div key={key} className="p-4 space-y-2">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div className="flex-1 min-w-0">
                  <label htmlFor={`config-${key}`} className="text-sm font-semibold text-gray-900">
                    {meta.label}
                    {meta.suffix && <span className="ml-1 text-xs text-gray-400">({meta.suffix})</span>}
                  </label>
                  <p className="text-xs text-gray-500 mt-0.5">{meta.description}</p>
                </div>
                <div className="sm:w-40">
                  <input
                    id={`config-${key}`}
                    type="text"
                    inputMode={meta.type === 'float' ? 'decimal' : 'numeric'}
                    value={value}
                    onChange={e => handleChange(key, e.target.value)}
                    className={cn(
                      'block w-full rounded-lg border px-3 py-2 text-sm text-right font-mono',
                      error
                        ? 'border-red-300 bg-red-50 focus:border-red-500 focus:ring-red-500'
                        : changed
                          ? 'border-amber-300 bg-amber-50 focus:border-amber-500 focus:ring-amber-500'
                          : 'border-gray-300 focus:border-red-500 focus:ring-red-500',
                    )}
                    aria-invalid={!!error}
                    aria-describedby={error ? `error-${key}` : undefined}
                  />
                  {error && (
                    <p id={`error-${key}`} className="mt-1 text-xs text-red-600" role="alert">{error}</p>
                  )}
                </div>
              </div>
              {item && (item.updated_by || item.updated_at) && (
                <p className="text-xs text-gray-400">
                  Última modificación: {formatDateTime(item.updated_at)}
                  {item.updated_by && <span> por {item.updated_by}</span>}
                </p>
              )}
            </div>
          );
        })}
      </div>

      <div className="rounded-xl border bg-blue-50 p-4 shadow-sm space-y-2">
        <div className="flex items-center gap-2 text-sm font-semibold text-blue-800">
          <Calculator className="h-4 w-4" />
          Vista previa de cálculo
        </div>
        <p className="text-sm text-blue-700">
          Para {preview.exampleDist}km con tarjeta:{' '}
          <span className="font-mono">
            {formatCLP(preview.tarifaBase)} + ceil(({preview.exampleDist} − {preview.threshold}) / {preview.bracketSize}) × {formatCLP(preview.surchargePerBracket)} + {formatCLP(preview.cardSurcharge)}
          </span>
        </p>
        <p className="text-lg font-bold text-blue-900">
          = {formatCLP(preview.total)}
        </p>
        <p className="text-xs text-blue-600">
          Desglose: base {formatCLP(preview.tarifaBase)} + {preview.brackets} tramo(s) × {formatCLP(preview.surchargePerBracket)} = {formatCLP(preview.distSurcharge)} distancia + {formatCLP(preview.cardSurcharge)} tarjeta
        </p>
      </div>

      <div className="flex justify-end">
        <button
          onClick={handleSave}
          disabled={saving || !hasChanges || hasValidationErrors}
          className={cn(
            'inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors',
            saving || !hasChanges || hasValidationErrors
              ? 'bg-gray-300 cursor-not-allowed'
              : 'bg-red-500 hover:bg-red-600',
          )}
          aria-label="Guardar configuración de delivery"
        >
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
          {saving ? 'Guardando...' : 'Guardar'}
        </button>
      </div>
    </div>
  );
}
