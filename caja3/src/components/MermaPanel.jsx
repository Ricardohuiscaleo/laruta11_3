import { useState, useEffect, useCallback } from 'react';
import { X, Trash2, Search, ArrowLeft, ClipboardList, Loader2, CheckCircle, AlertTriangle } from 'lucide-react';
import {
  MERMA_REASONS,
  getStockColor,
  countCriticalIngredients,
  calculateMermaSubtotal,
  calculateMermaTotal,
  validateMermaQuantity,
  canSubmitMerma,
  filterAndSortItems,
  getDailyMermaTotal,
  formatDateChilean,
} from '../utils/mermaUtils.js';

// ─── Helpers ──────────────────────────────────────────────────
const fmt = (n) => Math.round(n).toLocaleString('es-CL');

const CATEGORY_COLORS = {
  'Carnes': 'bg-red-100 text-red-700',
  'Vegetales': 'bg-green-100 text-green-700',
  'Salsas': 'bg-orange-100 text-orange-700',
  'Condimentos': 'bg-yellow-100 text-yellow-700',
  'Panes': 'bg-amber-100 text-amber-700',
  'Embutidos': 'bg-pink-100 text-pink-700',
  'Pre-elaborados': 'bg-purple-100 text-purple-700',
  'Lácteos': 'bg-blue-100 text-blue-700',
  'Bebidas': 'bg-cyan-100 text-cyan-700',
  'Gas': 'bg-gray-100 text-gray-700',
  'Servicios': 'bg-slate-100 text-slate-700',
  'Packaging': 'bg-stone-100 text-stone-700',
  'Limpieza': 'bg-teal-100 text-teal-700',
};

function getCategoryBadgeClass(cat) {
  return CATEGORY_COLORS[cat] || 'bg-gray-100 text-gray-700';
}

const STOCK_DOT = { green: 'bg-green-500', yellow: 'bg-yellow-500', red: 'bg-red-500' };

// ─── Main Component ───────────────────────────────────────────
export default function MermaPanel({ onClose }) {
  const [activeTab, setActiveTab] = useState('mermar');
  const [step, setStep] = useState(1);

  // Data
  const [ingredientes, setIngredientes] = useState([]);
  const [productos, setProductos] = useState([]);
  const [loadingData, setLoadingData] = useState(true);
  const [dataError, setDataError] = useState(false);

  // Search & selection
  const [itemType, setItemType] = useState('ingredient');
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedItem, setSelectedItem] = useState(null);

  // Merma accumulation
  const [mermaItems, setMermaItems] = useState([]);
  const [cantidad, setCantidad] = useState('');
  const [reason, setReason] = useState('');

  // Submit
  const [submitting, setSubmitting] = useState(false);
  const [submitSuccess, setSubmitSuccess] = useState(false);
  const [submitError, setSubmitError] = useState('');

  // History
  const [mermasHistorial, setMermasHistorial] = useState([]);
  const [historialLoading, setHistorialLoading] = useState(false);
  const [historialError, setHistorialError] = useState(false);


  // ─── Data Loading ─────────────────────────────────────────
  const loadData = useCallback(async () => {
    setLoadingData(true);
    setDataError(false);
    try {
      const [ingRes, prodRes] = await Promise.all([
        fetch(`/api/get_ingredientes.php?t=${Date.now()}`),
        fetch(`/api/get_productos.php?t=${Date.now()}`),
      ]);
      const ingData = await ingRes.json();
      const prodData = await prodRes.json();
      setIngredientes(Array.isArray(ingData) ? ingData.filter((i) => i.is_active) : []);
      setProductos(Array.isArray(prodData) ? prodData.filter((p) => p.is_active) : []);
    } catch {
      setDataError(true);
    } finally {
      setLoadingData(false);
    }
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const loadHistorial = useCallback(async () => {
    setHistorialLoading(true);
    setHistorialError(false);
    try {
      const res = await fetch(`/api/get_mermas.php?t=${Date.now()}`);
      const data = await res.json();
      setMermasHistorial(Array.isArray(data) ? data : []);
    } catch {
      setHistorialError(true);
      setMermasHistorial([]);
    } finally {
      setHistorialLoading(false);
    }
  }, []);

  useEffect(() => {
    if (activeTab === 'historial') loadHistorial();
  }, [activeTab, loadHistorial]);

  // ─── Derived Data ─────────────────────────────────────────
  const items = itemType === 'ingredient' ? ingredientes : productos;
  const searchResults = filterAndSortItems(items, searchTerm);
  const criticalCount = countCriticalIngredients(ingredientes);
  const totalCost = calculateMermaTotal(mermaItems);

  const cantidadNum = parseFloat(cantidad) || 0;
  const stockActual = selectedItem
    ? (selectedItem.type === 'ingredient' ? parseFloat(selectedItem.current_stock) : parseFloat(selectedItem.stock_quantity)) || 0
    : 0;
  const minStock = selectedItem?.min_stock_level ? parseFloat(selectedItem.min_stock_level) : 0;
  const validation = selectedItem ? validateMermaQuantity(cantidadNum, stockActual, minStock) : { blocked: false, alertCritical: false };
  const unitLabel = selectedItem ? (selectedItem.type === 'ingredient' ? selectedItem.unit : 'unidad') : '';
  const costoUnitario = selectedItem
    ? parseFloat(selectedItem.type === 'ingredient' ? selectedItem.cost_per_unit : selectedItem.cost_price) || 0
    : 0;

  // ─── Handlers ─────────────────────────────────────────────
  const handleSelectItem = (item) => {
    setSelectedItem({ ...item, type: itemType });
    setCantidad('');
    setStep(2);
  };

  const handleAddItem = () => {
    if (!selectedItem || cantidadNum <= 0 || validation.blocked) return;
    const subtotal = calculateMermaSubtotal(cantidadNum, costoUnitario);
    setMermaItems((prev) => [...prev, {
      item_id: selectedItem.id,
      item_type: selectedItem.type,
      nombre_item: selectedItem.name,
      cantidad: cantidadNum,
      unidad: unitLabel,
      stock_actual: stockActual,
      costo_unitario: costoUnitario,
      subtotal,
    }]);
    setSelectedItem(null);
    setCantidad('');
    setSearchTerm('');
    setStep(1);
  };

  const handleRemoveItem = (index) => {
    setMermaItems((prev) => prev.filter((_, i) => i !== index));
  };

  const handleBackToStep1 = () => {
    setSelectedItem(null);
    setCantidad('');
    setStep(1);
  };

  const handleSubmit = async () => {
    if (!canSubmitMerma(mermaItems, reason)) return;
    setSubmitting(true);
    setSubmitError('');
    try {
      for (const item of mermaItems) {
        const res = await fetch('/api/registrar_merma.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            item_type: item.item_type,
            item_id: item.item_id,
            quantity: item.cantidad,
            reason,
          }),
        });
        if (!res.ok) throw new Error(`Error al registrar ${item.nombre_item}`);
      }
      setSubmitSuccess(true);
      setTimeout(() => {
        setMermaItems([]);
        setReason('');
        setSelectedItem(null);
        setCantidad('');
        setSearchTerm('');
        setStep(1);
        setSubmitSuccess(false);
        setActiveTab('historial');
      }, 2000);
    } catch (err) {
      setSubmitError(err.message || 'Error al registrar mermas. Intenta de nuevo.');
    } finally {
      setSubmitting(false);
    }
  };


  // ─── Success Overlay ──────────────────────────────────────
  if (submitSuccess) {
    return (
      <div className="fixed inset-0 bg-white z-50 flex flex-col items-center justify-center">
        <div className="animate-bounce">
          <CheckCircle size={80} className="text-green-500" />
        </div>
        <p className="mt-4 text-xl font-bold text-green-700">¡Merma registrada!</p>
        <p className="text-sm text-gray-500 mt-1">Redirigiendo al historial...</p>
      </div>
    );
  }

  // ─── Render helpers (defined before return) ───────────────

  function renderMermarTab() {
    if (loadingData) {
      return (
        <div className="flex flex-col items-center justify-center py-20">
          <Loader2 size={36} className="animate-spin text-gray-400" />
          <p className="mt-3 text-sm text-gray-500">Cargando datos...</p>
        </div>
      );
    }
    if (dataError) {
      return (
        <div className="flex flex-col items-center justify-center py-20 px-4">
          <AlertTriangle size={40} className="text-red-400" />
          <p className="mt-3 text-base text-gray-700 text-center">Error al cargar datos.</p>
          <button
            onClick={loadData}
            className="mt-4 px-6 py-3 bg-red-600 text-white rounded-lg font-semibold"
            style={{ minHeight: '44px' }}
          >
            Toca para reintentar
          </button>
        </div>
      );
    }
    return (
      <div className="p-4 space-y-4">
        {/* Critical badge */}
        {criticalCount > 0 && (
          <div className="bg-red-50 border border-red-200 rounded-lg px-3 py-2 flex items-center gap-2">
            <AlertTriangle size={16} className="text-red-500 flex-shrink-0" />
            <span className="text-sm text-red-700 font-medium">
              {criticalCount} ingrediente{criticalCount > 1 ? 's' : ''} en estado crítico
            </span>
          </div>
        )}

        {/* Accumulated items summary */}
        {mermaItems.length > 0 && step === 1 && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-3">
            <div className="flex justify-between items-center mb-2">
              <span className="text-sm font-semibold text-red-800">
                {mermaItems.length} item{mermaItems.length > 1 ? 's' : ''} agregado{mermaItems.length > 1 ? 's' : ''}
              </span>
              <span className="text-lg font-bold text-red-600">${fmt(totalCost)}</span>
            </div>
            <button
              onClick={() => setStep(2)}
              className="w-full py-2 bg-red-600 text-white rounded-lg text-sm font-semibold"
              style={{ minHeight: '44px' }}
            >
              Registrar Merma →
            </button>
          </div>
        )}

        {step === 1 && renderStep1()}
        {step === 2 && renderStep2()}
      </div>
    );
  }


  function renderStep1() {
    return (
      <>
        <h2 className="text-base font-bold text-gray-800">Paso 1 — ¿Qué se perdió?</h2>

        {/* Toggle Ingredientes / Productos */}
        <div className="flex gap-2">
          <button
            onClick={() => { setItemType('ingredient'); setSearchTerm(''); }}
            className={`flex-1 py-3 rounded-lg font-semibold text-sm transition-colors ${
              itemType === 'ingredient'
                ? 'bg-red-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
            style={{ minHeight: '44px' }}
          >
            Ingredientes
          </button>
          <button
            onClick={() => { setItemType('product'); setSearchTerm(''); }}
            className={`flex-1 py-3 rounded-lg font-semibold text-sm transition-colors ${
              itemType === 'product'
                ? 'bg-red-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
            style={{ minHeight: '44px' }}
          >
            Productos
          </button>
        </div>

        {/* Search */}
        <div className="relative">
          <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            type="text"
            placeholder={`Buscar ${itemType === 'ingredient' ? 'ingrediente' : 'producto'}...`}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-lg text-sm focus:border-red-400 focus:outline-none"
            style={{ minHeight: '44px' }}
            aria-label={`Buscar ${itemType === 'ingredient' ? 'ingrediente' : 'producto'}`}
          />
        </div>

        {/* Results */}
        {searchTerm.trim() && searchResults.length === 0 && (
          <div className="text-center py-8 text-gray-500">
            <Search size={32} className="mx-auto mb-2 text-gray-300" />
            <p className="text-sm">No se encontraron items para &apos;{searchTerm}&apos;</p>
          </div>
        )}

        {searchResults.length > 0 && (
          <div className="space-y-2">
            {searchResults.map((item) => {
              const stock = itemType === 'ingredient' ? parseFloat(item.current_stock) : parseFloat(item.stock_quantity);
              const unit = itemType === 'ingredient' ? item.unit : 'unidad';
              const minLevel = item.min_stock_level ? parseFloat(item.min_stock_level) : 0;
              const color = itemType === 'ingredient' ? getStockColor(stock, minLevel) : 'green';
              const category = item.category || null;

              return (
                <button
                  key={item.id}
                  onClick={() => handleSelectItem(item)}
                  className="w-full text-left bg-white border border-gray-200 rounded-lg p-3 hover:border-red-300 hover:bg-red-50 transition-colors active:bg-red-100"
                  style={{ minHeight: '44px' }}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex-1 min-w-0">
                      <p className="font-semibold text-base text-gray-900 truncate">{item.name}</p>
                      <div className="flex items-center gap-2 mt-1 flex-wrap">
                        {category && (
                          <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${getCategoryBadgeClass(category)}`}>
                            {category}
                          </span>
                        )}
                        <div className="flex items-center gap-1">
                          <span className={`w-2 h-2 rounded-full ${STOCK_DOT[color]}`} />
                          <span className="text-xs text-gray-500">{stock} {unit}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </button>
              );
            })}
          </div>
        )}
      </>
    );
  }


  function renderStep2() {
    return (
      <>
        {/* Back button */}
        <button
          onClick={handleBackToStep1}
          className="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
          style={{ minHeight: '44px' }}
        >
          <ArrowLeft size={18} /> Volver a buscar
        </button>

        <h2 className="text-base font-bold text-gray-800">Paso 2 — ¿Cuánto? + ¿Por qué?</h2>

        {/* Selected item info */}
        {selectedItem && (
          <div className="bg-gray-50 rounded-lg p-4 space-y-2">
            <p className="font-bold text-base text-gray-900">{selectedItem.name}</p>
            <div className="grid grid-cols-2 gap-2 text-sm">
              {selectedItem.category && (
                <div>
                  <span className="text-gray-500">Categoría: </span>
                  <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${getCategoryBadgeClass(selectedItem.category)}`}>
                    {selectedItem.category}
                  </span>
                </div>
              )}
              <div>
                <span className="text-gray-500">Stock: </span>
                <span className="font-semibold">{stockActual} {unitLabel}</span>
              </div>
              <div>
                <span className="text-gray-500">Costo/unidad: </span>
                <span className="font-semibold">${fmt(costoUnitario)}</span>
              </div>
            </div>

            {/* Quantity input */}
            <div className="pt-2">
              <label htmlFor="merma-cantidad" className="block text-sm font-medium text-gray-700 mb-1">
                Cantidad a mermar ({unitLabel})
              </label>
              <input
                id="merma-cantidad"
                type="number"
                step="0.01"
                inputMode="numeric"
                placeholder="0.00"
                value={cantidad}
                onChange={(e) => setCantidad(e.target.value)}
                className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg text-base focus:border-red-400 focus:outline-none"
                style={{ minHeight: '44px' }}
              />
            </div>

            {/* Validation warnings */}
            {cantidadNum > 0 && validation.blocked && (
              <div className="flex items-start gap-2 bg-red-50 border border-red-300 rounded-lg p-3" role="alert">
                <AlertTriangle size={18} className="text-red-500 flex-shrink-0 mt-0.5" />
                <p className="text-sm text-red-700 font-medium">
                  Excede stock disponible ({stockActual} {unitLabel})
                </p>
              </div>
            )}
            {cantidadNum > 0 && !validation.blocked && validation.alertCritical && (
              <div className="flex items-start gap-2 bg-yellow-50 border border-yellow-300 rounded-lg p-3" role="alert">
                <AlertTriangle size={18} className="text-yellow-600 flex-shrink-0 mt-0.5" />
                <p className="text-sm text-yellow-700 font-medium">
                  ⚠️ Quedará en estado crítico
                </p>
              </div>
            )}

            {/* Subtotal preview */}
            {cantidadNum > 0 && !validation.blocked && (
              <div className="bg-red-50 border border-red-200 rounded-lg p-3 flex justify-between items-center">
                <span className="text-sm font-medium text-red-800">Costo merma:</span>
                <span className="text-lg font-bold text-red-600">${fmt(cantidadNum * costoUnitario)}</span>
              </div>
            )}

            {/* Add button */}
            <button
              onClick={handleAddItem}
              disabled={cantidadNum <= 0 || validation.blocked}
              className={`w-full py-3 rounded-lg font-semibold text-sm transition-colors ${
                cantidadNum > 0 && !validation.blocked
                  ? 'bg-red-600 text-white active:bg-red-700'
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              }`}
              style={{ minHeight: '44px' }}
            >
              Agregar
            </button>
          </div>
        )}

        {/* Reason selector */}
        {mermaItems.length > 0 && (
          <>
            <h3 className="text-sm font-bold text-gray-800 mt-2">¿Por qué?</h3>
            <div className="grid grid-cols-3 gap-2">
              {MERMA_REASONS.map((r) => (
                <button
                  key={r.value}
                  onClick={() => setReason(r.value)}
                  className={`flex flex-col items-center justify-center p-2 rounded-lg border-2 text-center transition-colors ${
                    reason === r.value
                      ? 'border-red-500 bg-red-50 text-red-700'
                      : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'
                  }`}
                  style={{ minHeight: '44px' }}
                >
                  <span className="text-lg leading-none">{r.emoji}</span>
                  <span className="text-xs mt-1 leading-tight">{r.label}</span>
                </button>
              ))}
            </div>
          </>
        )}

        {/* Accumulated items list */}
        {mermaItems.length > 0 && (
          <div className="space-y-2 mt-2">
            <h3 className="text-sm font-bold text-gray-800">Items a mermar</h3>
            {mermaItems.map((item, index) => (
              <div key={index} className="flex items-center justify-between bg-gray-50 rounded-lg p-3 border border-gray-200">
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-semibold text-gray-900 truncate">{item.nombre_item}</p>
                  <p className="text-xs text-gray-500">{item.cantidad} {item.unidad}</p>
                </div>
                <div className="flex items-center gap-3">
                  <span className="text-sm font-bold text-red-600">${fmt(item.subtotal)}</span>
                  <button
                    onClick={() => handleRemoveItem(index)}
                    className="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                    aria-label={`Eliminar ${item.nombre_item}`}
                  >
                    <Trash2 size={16} />
                  </button>
                </div>
              </div>
            ))}

            {/* Total */}
            <div className="bg-red-50 border-2 border-red-300 rounded-lg p-4 flex justify-between items-center">
              <span className="font-bold text-gray-900">TOTAL MERMA</span>
              <span className="font-bold text-red-600" style={{ fontSize: '20px' }}>${fmt(totalCost)}</span>
            </div>

            {/* Submit error */}
            {submitError && (
              <div className="flex items-start gap-2 bg-red-50 border border-red-300 rounded-lg p-3" role="alert">
                <AlertTriangle size={18} className="text-red-500 flex-shrink-0 mt-0.5" />
                <p className="text-sm text-red-700">{submitError}</p>
              </div>
            )}

            {/* Submit button */}
            <button
              onClick={handleSubmit}
              disabled={!canSubmitMerma(mermaItems, reason) || submitting}
              className={`w-full py-4 rounded-lg font-bold text-base transition-colors ${
                canSubmitMerma(mermaItems, reason) && !submitting
                  ? 'bg-red-600 text-white active:bg-red-700'
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              }`}
              style={{ minHeight: '44px' }}
            >
              {submitting ? (
                <span className="flex items-center justify-center gap-2">
                  <Loader2 size={18} className="animate-spin" /> Registrando...
                </span>
              ) : (
                'Registrar Merma'
              )}
            </button>
          </div>
        )}

        {/* If no selected item and no accumulated items, prompt to search */}
        {!selectedItem && mermaItems.length === 0 && (
          <div className="text-center py-8 text-gray-400">
            <p className="text-sm">Busca un item para comenzar</p>
          </div>
        )}
      </>
    );
  }


  function renderHistorialTab() {
    if (historialLoading) {
      return (
        <div className="flex flex-col items-center justify-center py-20">
          <Loader2 size={36} className="animate-spin text-gray-400" />
          <p className="mt-3 text-sm text-gray-500">Cargando historial...</p>
        </div>
      );
    }
    if (historialError) {
      return (
        <div className="flex flex-col items-center justify-center py-20 px-4">
          <AlertTriangle size={40} className="text-red-400" />
          <p className="mt-3 text-base text-gray-700 text-center">Error al cargar historial.</p>
          <button
            onClick={loadHistorial}
            className="mt-4 px-6 py-3 bg-red-600 text-white rounded-lg font-semibold"
            style={{ minHeight: '44px' }}
          >
            Reintentar
          </button>
        </div>
      );
    }

    const today = new Date().toISOString().split('T')[0];
    const dailyTotal = getDailyMermaTotal(mermasHistorial, today);

    return (
      <div className="p-4 space-y-4">
        {/* Daily summary */}
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <p className="text-sm text-red-700 font-medium">Mermas de hoy</p>
          <p className="text-2xl font-bold text-red-600 mt-1">${fmt(dailyTotal)}</p>
        </div>

        {mermasHistorial.length === 0 ? (
          <div className="text-center py-12 text-gray-400">
            <ClipboardList size={40} className="mx-auto mb-3 text-gray-300" />
            <p className="text-sm">No hay mermas registradas</p>
          </div>
        ) : (
          <div className="space-y-3">
            {mermasHistorial.map((merma) => (
              <div key={merma.id} className="bg-white border border-gray-200 rounded-lg p-4">
                <div className="flex justify-between items-start">
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold text-sm text-gray-900 truncate">{merma.item_name}</p>
                    <p className="text-xs text-gray-500 mt-0.5">{merma.quantity} {merma.unit}</p>
                  </div>
                  <div className="text-right flex-shrink-0 ml-3">
                    <p className="text-sm font-bold text-red-600">${fmt(merma.cost)}</p>
                    <p className="text-xs text-gray-400 mt-0.5">{formatDateChilean(merma.created_at)}</p>
                  </div>
                </div>
                <div className="mt-2 pt-2 border-t border-gray-100">
                  <span className="text-xs text-gray-500">Motivo: </span>
                  <span className="text-xs font-medium text-gray-700">{merma.reason}</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    );
  }

  // ─── Main Return ──────────────────────────────────────────
  return (
    <div className="fixed inset-0 bg-white z-50 flex flex-col overflow-hidden">
      {/* Header */}
      <header className="flex-shrink-0 bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
        <h1 className="text-lg font-bold text-gray-900">🗑️ Gestión de Mermas</h1>
        <button
          onClick={onClose}
          className="p-2 rounded-full hover:bg-gray-100 transition-colors"
          aria-label="Cerrar panel de mermas"
        >
          <X size={24} className="text-gray-600" />
        </button>
      </header>

      {/* Tabs */}
      <div className="flex-shrink-0 flex border-b border-gray-200 bg-white" role="tablist">
        <button
          role="tab"
          aria-selected={activeTab === 'mermar'}
          onClick={() => setActiveTab('mermar')}
          className={`flex-1 py-3 text-sm font-semibold flex items-center justify-center gap-2 transition-colors ${
            activeTab === 'mermar'
              ? 'text-red-600'
              : 'text-gray-500 hover:text-gray-700'
          }`}
          style={{
            minHeight: '44px',
            borderBottomWidth: '3px',
            borderBottomColor: activeTab === 'mermar' ? '#dc2626' : 'transparent',
            borderBottomStyle: 'solid',
          }}
        >
          <Trash2 size={18} /> Mermar
        </button>
        <button
          role="tab"
          aria-selected={activeTab === 'historial'}
          onClick={() => setActiveTab('historial')}
          className={`flex-1 py-3 text-sm font-semibold flex items-center justify-center gap-2 transition-colors ${
            activeTab === 'historial'
              ? 'text-red-600'
              : 'text-gray-500 hover:text-gray-700'
          }`}
          style={{
            minHeight: '44px',
            borderBottomWidth: '3px',
            borderBottomColor: activeTab === 'historial' ? '#dc2626' : 'transparent',
            borderBottomStyle: 'solid',
          }}
        >
          <ClipboardList size={18} /> Historial
        </button>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto">
        {activeTab === 'mermar' ? renderMermarTab() : renderHistorialTab()}
      </div>
    </div>
  );
}
