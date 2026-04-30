import { useState, useEffect, useCallback } from 'react';
import { X, ArrowLeft, Search, Trash2, Loader2, CheckCircle, AlertTriangle, Minus, Plus } from 'lucide-react';
import {
  MERMA_REASONS, getStockColor, calculateMermaTotal,
  canSubmitMerma, fuzzyMatch, formatDateChilean, getDailyMermaTotal,
  getMermaInputType, getSmartQuestion, getSmartPlaceholder,
  convertToBaseUnit, calculateSmartCost, getConversionText,
  stockInNaturalUnits, validateSmartQuantity,
} from '../utils/mermaUtils.js';

const fmt = (n) => Math.round(n).toLocaleString('es-CL');
const STOCK_DOT = { green: 'bg-green-500', yellow: 'bg-yellow-500', red: 'bg-red-500' };

function Hl({ name, q }) {
  if (!q || q.length < 2) return name;
  const i = name.toLowerCase().indexOf(q.toLowerCase());
  if (i === -1) return name;
  return <>{name.slice(0, i)}<mark className="bg-yellow-300 rounded-sm px-0.5">{name.slice(i, i + q.length)}</mark>{name.slice(i + q.length)}</>;
}

export default function MermaPanel({ onClose }) {
  const [step, setStep] = useState(1);
  const [activeTab, setActiveTab] = useState('mermar');
  const [ingredientes, setIngredientes] = useState([]);
  const [productos, setProductos] = useState([]);
  const [loadingData, setLoadingData] = useState(true);
  const [dataError, setDataError] = useState(false);
  const [itemType, setItemType] = useState('ingredient');
  const [searchTerm, setSearchTerm] = useState('');
  const [mermaItems, setMermaItems] = useState([]);
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitSuccess, setSubmitSuccess] = useState(false);
  const [submitError, setSubmitError] = useState('');
  const [mermasHistorial, setMermasHistorial] = useState([]);
  const [historialLoading, setHistorialLoading] = useState(false);

  const loadData = useCallback(async () => {
    setLoadingData(true); setDataError(false);
    try {
      const [iR, pR] = await Promise.all([
        fetch(`/api/get_ingredientes.php?t=${Date.now()}`),
        fetch(`/api/get_productos.php?t=${Date.now()}`)
      ]);
      const iD = await iR.json(), pD = await pR.json();
      setIngredientes(Array.isArray(iD) ? iD.filter(i => i.is_active) : []);
      setProductos(Array.isArray(pD) ? pD.filter(p => p.is_active) : []);
    } catch { setDataError(true); }
    finally { setLoadingData(false); }
  }, []);
  useEffect(() => { loadData(); }, [loadData]);

  const loadHistorial = useCallback(async () => {
    setHistorialLoading(true);
    try {
      const r = await fetch(`/api/get_mermas.php?t=${Date.now()}`);
      const d = await r.json();
      setMermasHistorial(Array.isArray(d) ? d : []);
    } catch { setMermasHistorial([]); }
    finally { setHistorialLoading(false); }
  }, []);
  useEffect(() => { if (activeTab === 'historial') loadHistorial(); }, [activeTab, loadHistorial]);

  const items = itemType === 'ingredient'
    ? ingredientes
    : productos.filter(p => ![6, 7].includes(parseInt(p.category_id)));

  const filtered = searchTerm.trim().length >= 2
    ? items.map(i => ({ ...i, score: fuzzyMatch(i.name, searchTerm) }))
        .filter(i => i.score > 0).sort((a, b) => b.score - a.score).slice(0, 20)
    : [];

  const totalCost = calculateMermaTotal(mermaItems);

  const addItem = (item) => {
    const isIngredient = itemType === 'ingredient';
    const stock = parseFloat(isIngredient ? item.current_stock : item.stock_quantity) || 0;
    const cost = parseFloat(isIngredient ? item.cost_per_unit : item.cost_price) || 0;
    const unit = isIngredient ? (item.unit || 'kg') : 'unidad';
    const min = parseFloat(item.min_stock_level) || 0;
    const inputType = isIngredient ? getMermaInputType(item) : 'unidad';

    setMermaItems(prev => [...prev, {
      item_id: item.id, item_type: itemType, nombre_item: item.name,
      cantidad: '', unidad: unit, costo_unitario: cost, subtotal: 0,
      stock_actual: stock, min_stock: min, category: item.category || null,
      inputType, peso_por_unidad: parseFloat(item.peso_por_unidad) || 0,
      nombre_unidad_natural: item.nombre_unidad_natural || null,
      _raw: item,
    }]);
  };

  const updateQty = (idx, val) => {
    setMermaItems(prev => prev.map((it, i) => {
      if (i !== idx) return it;
      const q = parseFloat(val) || 0;
      let subtotal = 0;
      if (it.item_type === 'ingredient' && it._raw) {
        subtotal = calculateSmartCost(q, it._raw);
      } else {
        subtotal = q * it.costo_unitario;
      }
      return { ...it, cantidad: val, subtotal };
    }));
  };

  const stepQty = (idx, delta) => {
    setMermaItems(prev => prev.map((it, i) => {
      if (i !== idx) return it;
      const cur = parseInt(it.cantidad) || 0;
      const next = Math.max(0, cur + delta);
      const val = String(next);
      let subtotal = 0;
      if (it.item_type === 'ingredient' && it._raw) {
        subtotal = calculateSmartCost(next, it._raw);
      } else {
        subtotal = next * it.costo_unitario;
      }
      return { ...it, cantidad: val, subtotal };
    }));
  };

  const removeItem = (idx) => setMermaItems(prev => prev.filter((_, i) => i !== idx));

  const getValidItems = () => mermaItems.filter(it => {
    const q = parseFloat(it.cantidad) || 0;
    if (q <= 0) return false;
    if (it.item_type === 'ingredient' && it._raw) {
      const baseQty = convertToBaseUnit(q, it._raw);
      return baseQty <= it.stock_actual;
    }
    return q <= it.stock_actual;
  });

  const validItems = getValidItems();

  const handleSubmit = async () => {
    if (!canSubmitMerma(validItems, reason)) return;
    setSubmitting(true); setSubmitError('');
    try {
      for (const item of validItems) {
        const q = parseFloat(item.cantidad) || 0;
        const isNatural = item.inputType === 'natural';
        const baseQty = item._raw ? convertToBaseUnit(q, item._raw) : q;

        const body = {
          item_type: item.item_type,
          item_id: item.item_id,
          quantity: baseQty,
          reason,
        };
        if (isNatural && item.peso_por_unidad > 0) {
          body.cantidad_natural = Math.round(q);
        }

        const res = await fetch('/api/registrar_merma.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        });
        if (!res.ok) throw new Error(`Error: ${item.nombre_item}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.error || `Error: ${item.nombre_item}`);
      }
      setSubmitSuccess(true);
      setTimeout(() => {
        setMermaItems([]); setReason(''); setStep(1);
        setSubmitSuccess(false); setActiveTab('historial');
      }, 2000);
    } catch (e) { setSubmitError(e.message || 'Error al registrar'); }
    finally { setSubmitting(false); }
  };

  if (submitSuccess) return (
    <div className="fixed inset-0 bg-white z-50 flex flex-col items-center justify-center"
      style={{ paddingTop: 'env(safe-area-inset-top)' }}>
      <div className="animate-bounce"><CheckCircle size={80} className="text-green-500" /></div>
      <p className="mt-4 text-xl font-bold text-green-700">Merma registrada</p>
    </div>
  );

  return (
    <div className="fixed inset-0 bg-gray-50 z-50 flex flex-col"
      style={{ paddingTop: 'env(safe-area-inset-top, 0px)', paddingBottom: 'env(safe-area-inset-bottom, 0px)' }}>
      <header className="flex-shrink-0 bg-gradient-to-r from-red-500 to-orange-500 text-white px-4 py-3 flex items-center gap-3 shadow-lg"
        style={{ paddingTop: 'max(0.75rem, env(safe-area-inset-top))' }}>
        {step > 1 ? (
          <button onClick={() => setStep(step - 1)}
            className="p-2 rounded-full hover:bg-white/20 transition-colors" aria-label="Volver">
            <ArrowLeft size={22} />
          </button>
        ) : <div className="w-[38px]" />}
        <div className="flex-1">
          <h1 className="text-lg font-bold">Mermas</h1>
        </div>
        {mermaItems.length > 0 && step === 1 && (
          <button onClick={() => setStep(2)}
            className="bg-white text-red-600 px-3 py-1.5 rounded-lg text-xs font-bold active:scale-95">
            Siguiente &rarr;
          </button>
        )}
        <button onClick={onClose}
          className="p-2 rounded-full hover:bg-white/20 transition-colors" aria-label="Cerrar">
          <X size={22} />
        </button>
      </header>

      <div className="flex-shrink-0 flex bg-white border-b" role="tablist">
        {[['mermar', '\uD83D\uDDD1\uFE0F Mermar'], ['historial', '\uD83D\uDCCB Historial']].map(([key, label]) => (
          <button key={key} role="tab" aria-selected={activeTab === key}
            onClick={() => { setActiveTab(key); if (key === 'mermar') setStep(1); }}
            className={`flex-1 py-3 text-sm font-semibold transition-colors ${activeTab === key ? 'text-red-600' : 'text-gray-500'}`}
            style={{ minHeight: '44px', borderBottomWidth: activeTab === key ? '3px' : '0',
              borderBottomColor: activeTab === key ? '#dc2626' : 'transparent', borderBottomStyle: 'solid' }}>
            {label}
          </button>
        ))}
      </div>

      <div className="flex-1 overflow-y-auto">
        {activeTab === 'mermar' ? renderMermar() : renderHistorial()}
      </div>
    </div>
  );

  function renderMermar() {
    if (loadingData) return (
      <div className="flex flex-col items-center justify-center py-20">
        <Loader2 size={36} className="animate-spin text-gray-400" />
        <p className="mt-3 text-sm text-gray-500">Cargando...</p>
      </div>
    );
    if (dataError) return (
      <div className="flex flex-col items-center justify-center py-20 px-4">
        <AlertTriangle size={40} className="text-red-400" />
        <p className="mt-3 text-gray-700">Error al cargar datos</p>
        <button onClick={loadData} className="mt-4 px-6 py-3 bg-red-600 text-white rounded-lg font-semibold"
          style={{ minHeight: '44px' }}>Reintentar</button>
      </div>
    );
    if (step === 1) return renderStep1();
    if (step === 2) return renderStep2();
    return renderStep3();
  }

  function renderStep1() {
    return (
      <div className="p-3 space-y-3">
        <div className="flex gap-2">
          {[['ingredient', 'Ingredientes'], ['product', 'Productos']].map(([t, l]) => (
            <button key={t} onClick={() => { setItemType(t); setSearchTerm(''); }}
              className={`flex-1 py-2.5 rounded-lg font-semibold text-sm transition-colors ${itemType === t ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700'}`}
              style={{ minHeight: '44px' }}>{l}</button>
          ))}
        </div>

        <div className="relative">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input type="text"
            placeholder={`Buscar ${itemType === 'ingredient' ? 'ingrediente' : 'producto'}...`}
            value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:border-red-400 focus:outline-none"
            style={{ minHeight: '44px' }} />
        </div>

        {mermaItems.length > 0 && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-2 space-y-2">
            <div className="flex justify-between items-center px-1">
              <span className="text-xs font-semibold text-red-800">
                {mermaItems.length} item{mermaItems.length > 1 ? 's' : ''}
              </span>
              <span className="text-sm font-bold text-red-600">${fmt(totalCost)}</span>
            </div>
            {mermaItems.map((it, idx) => {
              const q = parseFloat(it.cantidad) || 0;
              const isNatural = it.inputType === 'natural';
              const isUnit = it.inputType === 'unidad' || it.item_type === 'product';
              const useIntStepper = isNatural || isUnit;
              const validation = it._raw ? validateSmartQuantity(q, it._raw) : { blocked: q > it.stock_actual };
              const nombre = it.nombre_unidad_natural || (isUnit ? 'un' : it.unidad);

              return (
                <div key={idx} className="bg-white rounded-lg p-2.5 border border-gray-100">
                  <div className="flex items-center justify-between mb-1.5">
                    <p className="text-xs font-semibold text-gray-900 truncate flex-1 mr-2">{it.nombre_item}</p>
                    <button onClick={() => removeItem(idx)} className="p-1 text-red-400 hover:text-red-600 rounded"
                      aria-label="Eliminar item">
                      <Trash2 size={14} />
                    </button>
                  </div>
                  <div className="flex items-center gap-2">
                    {useIntStepper ? (
                      <div className="flex items-center gap-0 border border-gray-300 rounded-lg overflow-hidden">
                        <button onClick={() => stepQty(idx, -1)}
                          className="w-9 h-9 flex items-center justify-center bg-gray-50 active:bg-gray-200 transition-colors"
                          aria-label="Disminuir cantidad">
                          <Minus size={14} />
                        </button>
                        <input type="number" inputMode="numeric" value={it.cantidad}
                          onChange={(e) => updateQty(idx, e.target.value)} placeholder="0"
                          className={`w-12 h-9 text-center text-sm font-bold border-x border-gray-300 ${validation.blocked ? 'text-red-600 bg-red-50' : 'text-gray-900'}`} />
                        <button onClick={() => stepQty(idx, 1)}
                          className="w-9 h-9 flex items-center justify-center bg-gray-50 active:bg-gray-200 transition-colors"
                          aria-label="Aumentar cantidad">
                          <Plus size={14} />
                        </button>
                      </div>
                    ) : (
                      <input type="number" step="0.01" inputMode="decimal" value={it.cantidad}
                        onChange={(e) => updateQty(idx, e.target.value)}
                        placeholder={it._raw ? getSmartPlaceholder(it._raw) : '0'}
                        className={`w-20 px-2 py-1.5 border rounded-lg text-sm text-center font-bold ${validation.blocked ? 'border-red-400 bg-red-50 text-red-600' : 'border-gray-300'}`} />
                    )}
                    <span className="text-xs text-gray-500 font-medium">{nombre}</span>
                    <div className="flex-1 text-right">
                      {q > 0 && <span className="text-xs font-bold text-red-600">${fmt(it.subtotal)}</span>}
                    </div>
                  </div>
                  {isNatural && q > 0 && it._raw && (
                    <p className="text-[10px] text-gray-400 mt-1">
                      = {convertToBaseUnit(q, it._raw).toFixed(3)} {it.unidad}
                    </p>
                  )}
                  {validation.blocked && (
                    <p className="text-[10px] text-red-500 mt-1 font-medium">
                      Supera stock ({it.stock_actual} {it.unidad})
                    </p>
                  )}
                </div>
              );
            })}
          </div>
        )}

        {filtered.length === 0 && searchTerm.trim().length >= 2 && (
          <p className="text-center text-sm text-gray-400 py-8">No se encontr&oacute; &quot;{searchTerm}&quot;</p>
        )}

        {filtered.length === 0 && searchTerm.trim().length < 2 && mermaItems.length === 0 && (
          <div className="text-center py-12 text-gray-400">
            <Search size={40} className="mx-auto mb-3 text-gray-300" />
            <p className="text-sm font-medium">Escribe para buscar</p>
            <p className="text-xs mt-1">ingredientes o productos</p>
          </div>
        )}

        <div className="space-y-1">
          {filtered.map((item) => {
            const isIng = itemType === 'ingredient';
            const stock = parseFloat(isIng ? item.current_stock : item.stock_quantity) || 0;
            const min = parseFloat(item.min_stock_level) || 0;
            const color = isIng ? getStockColor(stock, min) : 'green';
            const unit = isIng ? (item.unit || 'kg') : 'un';
            const alreadyAdded = mermaItems.some(m => m.item_id === item.id && m.item_type === itemType);
            const naturalCount = isIng ? stockInNaturalUnits(item) : null;

            return (
              <button key={item.id} onClick={() => !alreadyAdded && addItem(item)}
                disabled={alreadyAdded}
                className={`w-full text-left flex items-center gap-2 px-3 py-2.5 rounded-lg border transition-colors ${alreadyAdded ? 'bg-gray-50 border-gray-200 opacity-50' : 'bg-white border-gray-200 hover:border-red-300 active:bg-red-50'}`}
                style={{ minHeight: '48px' }}>
                <span className={`w-2.5 h-2.5 rounded-full flex-shrink-0 ${STOCK_DOT[color]}`} />
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-semibold text-gray-900 truncate">
                    <Hl name={item.name} q={searchTerm} />
                  </p>
                  {isIng && naturalCount !== null && (
                    <p className="text-[10px] text-gray-400 mt-0.5">
                      {stock} {unit} &asymp; {naturalCount} {item.nombre_unidad_natural || 'un'}
                    </p>
                  )}
                </div>
                {isIng && naturalCount === null && (
                  <span className="text-xs text-gray-400 flex-shrink-0">{stock} {unit}</span>
                )}
                {alreadyAdded && <span className="text-xs text-green-600 font-bold">&check;</span>}
              </button>
            );
          })}
        </div>
      </div>
    );
  }

  function renderStep2() {
    return (
      <div className="p-4 space-y-4">
        <h2 className="text-base font-bold text-gray-800">Selecciona el motivo</h2>
        <div className="grid grid-cols-3 gap-2">
          {MERMA_REASONS.map((r) => (
            <button key={r.value} onClick={() => setReason(r.value)}
              className={`flex flex-col items-center justify-center p-3 rounded-xl border-2 transition-all ${
                reason === r.value ? 'border-red-500 bg-red-50 text-red-700 scale-[1.02]' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'
              }`} style={{ minHeight: '72px', aspectRatio: '1' }}>
              <span className="text-2xl leading-none">{r.emoji}</span>
              <span className="text-[10px] mt-1.5 leading-tight text-center font-medium">{r.label}</span>
            </button>
          ))}
        </div>
        {reason && (
          <button onClick={() => setStep(3)}
            className="w-full py-3 bg-red-600 text-white rounded-lg font-bold text-sm active:scale-[0.98] transition-all"
            style={{ minHeight: '44px' }}>
            Ver Resumen &rarr;
          </button>
        )}
      </div>
    );
  }

  function renderStep3() {
    const reasonObj = MERMA_REASONS.find(r => r.value === reason);
    return (
      <div className="p-4 space-y-4">
        <h2 className="text-base font-bold text-gray-800">Resumen de Merma</h2>
        <div className="flex items-center gap-2 bg-gray-50 rounded-lg p-3 border border-gray-200">
          <span className="text-xl">{reasonObj?.emoji}</span>
          <span className="text-sm font-semibold text-gray-800">{reason}</span>
        </div>
        <div className="space-y-2">
          {validItems.map((it, idx) => {
            const q = parseFloat(it.cantidad) || 0;
            const convText = it._raw ? getConversionText(q, it._raw) : `${q} ${it.unidad}`;
            return (
              <div key={idx} className="flex items-center justify-between bg-white rounded-lg p-3 border border-gray-200">
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-semibold text-gray-900 truncate">{it.nombre_item}</p>
                  <p className="text-xs text-gray-500">{convText}</p>
                </div>
                <span className="text-sm font-bold text-red-600">${fmt(it.subtotal)}</span>
              </div>
            );
          })}
        </div>
        <div className="bg-red-50 border-2 border-red-300 rounded-lg p-4 flex justify-between items-center">
          <span className="font-bold text-gray-900">TOTAL</span>
          <span className="font-bold text-red-600" style={{ fontSize: '22px' }}>
            ${fmt(calculateMermaTotal(validItems))}
          </span>
        </div>
        {validItems.length === 0 && (
          <div className="bg-yellow-50 border border-yellow-300 rounded-lg p-3 flex items-center gap-2">
            <AlertTriangle size={16} className="text-yellow-600" />
            <p className="text-sm text-yellow-700">No hay items v&aacute;lidos. Vuelve y agrega cantidades.</p>
          </div>
        )}
        {submitError && (
          <div className="bg-red-50 border border-red-300 rounded-lg p-3 flex items-center gap-2">
            <AlertTriangle size={16} className="text-red-500" />
            <p className="text-sm text-red-700">{submitError}</p>
          </div>
        )}
        <button onClick={handleSubmit}
          disabled={validItems.length === 0 || submitting}
          className={`w-full py-4 rounded-lg font-bold text-base transition-all ${
            validItems.length > 0 && !submitting ? 'bg-red-600 text-white active:scale-[0.98]' : 'bg-gray-200 text-gray-400 cursor-not-allowed'
          }`} style={{ minHeight: '48px' }}>
          {submitting
            ? <span className="flex items-center justify-center gap-2"><Loader2 size={18} className="animate-spin" /> Registrando...</span>
            : '\uD83D\uDDD1\uFE0F Confirmar Merma'}
        </button>
      </div>
    );
  }

  function renderHistorial() {
    if (historialLoading) return (
      <div className="flex items-center justify-center py-20">
        <Loader2 size={36} className="animate-spin text-gray-400" />
      </div>
    );
    const today = new Date().toISOString().split('T')[0];
    const dailyTotal = getDailyMermaTotal(mermasHistorial, today);
    return (
      <div className="p-4 space-y-3">
        <div className="bg-red-50 border border-red-200 rounded-lg p-3">
          <p className="text-xs text-red-700 font-medium">Mermas de hoy</p>
          <p className="text-xl font-bold text-red-600">${fmt(dailyTotal)}</p>
        </div>
        {mermasHistorial.length === 0 ? (
          <p className="text-center text-sm text-gray-400 py-12">No hay mermas registradas</p>
        ) : mermasHistorial.map((m) => (
          <div key={m.id} className="bg-white border border-gray-200 rounded-lg p-3">
            <div className="flex justify-between items-start">
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-gray-900 truncate">{m.item_name}</p>
                <p className="text-xs text-gray-500">{m.quantity} {m.unit} &middot; {m.reason}</p>
              </div>
              <div className="text-right flex-shrink-0 ml-2">
                <p className="text-sm font-bold text-red-600">${fmt(m.cost)}</p>
                <p className="text-xs text-gray-400">{formatDateChilean(m.created_at)}</p>
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  }
}
