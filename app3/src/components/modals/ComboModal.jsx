import React, { useState, useEffect, useMemo } from 'react';
import { X, Check, Plus } from 'lucide-react';

const ComboModal = ({ combo, isOpen, onClose, onAddToCart, quantity = 1 }) => {
  const [comboData, setComboData] = useState(null);
  const [selections, setSelections] = useState({});
  const [loading, setLoading] = useState(false);

  const getTotalSelected = (groupName) => {
    const currentSelections = selections[groupName];
    if (!currentSelections) return 0;
    if (typeof currentSelections === 'number') return 1;
    if (typeof currentSelections === 'object' && !Array.isArray(currentSelections)) {
      return Object.values(currentSelections).reduce((sum, qty) => sum + (qty || 0), 0);
    }
    if (Array.isArray(currentSelections)) return currentSelections.length;
    return 0;
  };

  useEffect(() => {
    if (isOpen && combo) {
      setSelections({});
      loadComboData();
    }
  }, [isOpen, combo]);

  const loadComboData = async () => {
    setLoading(true);
    try {
      const response = await fetch(`/api/get_combos.php?product_id=${combo.id}&v=${Date.now()}`);
      const data = await response.json();

      if (data.success && data.combo) {
        setComboData(data.combo);
      } else if (data.success && data.combos && data.combos.length > 0) {
        setComboData(data.combos[0]);
      } else {
        onClose();
        onAddToCart({
          ...combo,
          quantity: 1,
          isSimpleProduct: true
        });
        return;
      }
    } catch (error) {
      // Silent fail — modal will show error state
    }
    setLoading(false);
  };

  const handleSelectionChange = (groupName, productId, maxSelections, action = 'toggle') => {
    setSelections(prev => {
      const current = prev[groupName];

      if (maxSelections === 1) {
        if (action === 'toggle') {
          return { ...prev, [groupName]: current === productId ? null : productId };
        }
        return prev;
      }

      // Multi-select: store counts per productId
      const counts = (typeof current === 'object' && !Array.isArray(current) && current !== null)
        ? { ...current }
        : {};

      if (!counts[productId]) counts[productId] = 0;
      const total = Object.values(counts).reduce((s, c) => s + c, 0);

      if (action === 'add') {
        if (total < maxSelections) {
          counts[productId] = (counts[productId] || 0) + 1;
        }
      } else if (action === 'remove') {
        if (counts[productId] > 0) {
          counts[productId] -= 1;
          if (counts[productId] <= 0) delete counts[productId];
        }
      }

      return { ...prev, [groupName]: counts };
    });
  };

  // Calculate total price_adjustment from selections
  const selectionsPriceAdjustment = useMemo(() => {
    if (!comboData?.selection_groups) return 0;
    let total = 0;
    Object.entries(selections).forEach(([groupName, selection]) => {
      const group = comboData.selection_groups[groupName];
      if (!group) return;
      const options = group.options || [];
      if (typeof selection === 'object' && !Array.isArray(selection)) {
        Object.entries(selection).forEach(([pid, qty]) => {
          const opt = options.find(o => o.product_id === Number(pid));
          if (opt) total += (opt.price_adjustment || 0) * (qty || 0);
        });
      } else if (Array.isArray(selection)) {
        selection.forEach(productId => {
          const opt = options.find(o => o.product_id === productId);
          if (opt) total += (opt.price_adjustment || 0);
        });
      } else if (selection) {
        const opt = options.find(o => o.product_id === selection);
        if (opt) total += (opt.price_adjustment || 0);
      }
    });
    return total;
  }, [selections, comboData]);

  const comboTotalPrice = useMemo(() => {
    return (combo?.sale_price || combo?.price || 0) + selectionsPriceAdjustment;
  }, [combo?.sale_price, combo?.price, selectionsPriceAdjustment]);

  const handleAddToCart = () => {
    if (!comboData) return;
    
    const invalidGroups = [];
    Object.entries(comboData.selection_groups || {}).forEach(([groupName, group]) => {
      const maxSelections = group.max_selections || 1;
      const totalSelected = getTotalSelected(groupName);
      if (totalSelected !== maxSelections) {
        invalidGroups.push(`${groupName} (${totalSelected}/${maxSelections})`);
      }
    });
    
    if (invalidGroups.length > 0) {
      alert(`Por favor completa las selecciones:\n${invalidGroups.join('\n')}`);
      return;
    }
    
    const detailedSelections = {};
    Object.entries(selections).forEach(([groupName, selection]) => {
      const group = comboData.selection_groups?.[groupName];
      const options = group?.options || [];
      if (typeof selection === 'object' && !Array.isArray(selection)) {
        const items = [];
        Object.entries(selection).forEach(([pid, qty]) => {
          const option = options.find(o => o.product_id === Number(pid));
          if (option && qty > 0) {
            for (let i = 0; i < qty; i++) {
              items.push({ id: option.product_id, name: option.product_name, price: option.price_adjustment || 0 });
            }
          }
        });
        detailedSelections[groupName] = items;
      } else if (Array.isArray(selection)) {
        detailedSelections[groupName] = selection.map(productId => {
          const option = options.find(o => o.product_id === productId);
          return option ? { id: option.product_id, name: option.product_name, price: option.price_adjustment || 0 } : null;
        }).filter(Boolean);
      } else if (selection) {
        const option = options.find(o => o.product_id === selection);
        if (option) {
          detailedSelections[groupName] = [{
            id: option.product_id,
            name: option.product_name,
            price: option.price_adjustment || 0
          }];
        }
      }
    });
    
    const comboWithSelections = {
      ...combo,
      price: comboTotalPrice,
      basePrice: combo.sale_price || combo.price,
      selections: detailedSelections,
      fixed_items: comboData.fixed_items || [],
      quantity: 1,
      component_customizations: buildComponentCustomizations(detailedSelections)
    };
    
    onAddToCart(comboWithSelections);
    onClose();
  };

  const buildComponentCustomizations = (detailedSelections) => {
    const components = [];
    if (comboData.fixed_items) {
      let counter = 0;
      comboData.fixed_items.forEach((fixedItem, fi) => {
        for (let i = 0; i < fixedItem.quantity; i++) {
          counter++;
          components.push({
            type: 'fixed',
            fixed_index: fi,
            component_index: i,
            product_name: `${fixedItem.product_name}`,
            label: `${fixedItem.product_name} ${counter}`,
            customizations: []
          });
        }
      });
    }
    if (detailedSelections) {
      Object.entries(detailedSelections).forEach(([groupName, items]) => {
      items.forEach((sel, i) => {
        components.push({
          type: 'selection',
          group: groupName,
          product_id: sel.id,
          product_name: sel.name,
          label: `${sel.name}`,
          customizations: []
        });
      });
    });
    }
    return components;
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={onClose}>
      <div className="bg-white w-full max-w-2xl mx-4 rounded-2xl flex flex-col animate-slide-up max-h-[90vh]" onClick={(e) => e.stopPropagation()}>
        <div className="border-b flex justify-between items-center p-4">
          <h2 className="font-bold text-gray-800 text-xl">Personalizar Combo</h2>
          <button onClick={onClose} className="p-1 text-gray-500 hover:text-gray-800" aria-label="Cerrar">
            <X size={24} />
          </button>
        </div>

        <div className="flex-grow overflow-y-auto p-4">
          {loading ? (
            <div className="text-center py-8">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500 mx-auto"></div>
              <p className="text-gray-600 mt-4">Cargando combo...</p>
            </div>
          ) : comboData ? (
            <div>
              {/* Two-column: left = foto, right = nombre + precio + incluye */}
              <div className="grid grid-cols-[40%_60%] gap-2 mb-4">
                {/* Left: solo la foto */}
                <div className="flex items-start pt-1">
                  {(combo.image_url || comboData.image_url) && (
                    <img
                      src={combo.image_url || comboData.image_url}
                      alt={combo.name}
                      className="w-full aspect-[4/5] object-cover rounded-xl"
                      onError={(e) => { e.target.style.display = 'none'; }}
                    />
                  )}
                </div>

                {/* Right: nombre + precio + Incluye */}
                <div>
                  <h3 className="font-black text-[14px] text-gray-800 leading-tight">{combo.name}</h3>
                  <div className="mt-0.5">
                    {combo.sale_price ? (
                      <>
                        <span className="text-lg font-black text-red-600">${combo.sale_price.toLocaleString('es-CL')}</span>
                        <span className="text-[11px] text-gray-400 line-through ml-1.5">${combo.price.toLocaleString('es-CL')}</span>
                      </>
                    ) : (
                      <span className="text-lg font-black text-orange-500">${comboTotalPrice.toLocaleString('es-CL')}</span>
                    )}
                    {selectionsPriceAdjustment > 0 && (
                      <span className="text-[10px] text-gray-400 ml-1">+${selectionsPriceAdjustment.toLocaleString('es-CL')}</span>
                    )}
                  </div>

                  <h4 className="text-[10px] font-bold text-gray-400 uppercase tracking-wider mt-2.5 mb-1.5">Incluye:</h4>
                  <ul className="space-y-1">
                    {comboData.fixed_items && comboData.fixed_items.map((item, index) => (
                      <li key={index} className="flex items-center gap-1.5 text-[12px] leading-tight">
                        <span className="w-1 h-1 rounded-full bg-orange-400 flex-shrink-0 mt-0.5" />
                        <span className="text-gray-700">{item.product_name}</span>
                        {item.quantity > 1 && <span className="text-gray-400 text-[10px]">x{item.quantity}</span>}
                      </li>
                    ))}
                    {comboData.selection_groups && Object.entries(comboData.selection_groups).map(([groupName, group]) => (
                      <li key={groupName} className="flex items-center gap-1.5 text-[12px] leading-tight">
                        <span className="w-1 h-1 rounded-full bg-gray-300 flex-shrink-0 mt-0.5" />
                        <span className="text-gray-500"> {groupName} a elección ({group.max_selections || 1})</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>

              {/* Selection Groups */}
              {comboData.selection_groups && Object.entries(comboData.selection_groups).map(([groupName, group], groupIndex) => {
                const options = group.options || [];
                const baseMaxSelections = group.max_selections || 1;
                const maxSelections = baseMaxSelections * quantity;
                const totalSelected = getTotalSelected(groupName);
                return (
                  <div key={groupIndex} className="border-t border-gray-100 pt-3">
                    <h4 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
                      Elige {groupName} ({totalSelected}/{maxSelections})
                    </h4>
                    {options.length === 0 ? (
                      <p className="text-sm text-gray-400">Sin opciones disponibles</p>
                    ) : (
                      <div className="space-y-1">
                        {options.map((option, optionIndex) => {
                          const currentCount = (() => {
                            const sel = selections[groupName];
                            if (!sel) return 0;
                            if (maxSelections === 1) return sel === option.product_id ? 1 : 0;
                            if (typeof sel === 'object' && !Array.isArray(sel)) {
                              return sel[option.product_id] || 0;
                            }
                            if (Array.isArray(sel)) return sel.filter(id => id === option.product_id).length;
                            return 0;
                          })();
                          const totalSelected = getTotalSelected(groupName);
                          const isSelected = currentCount > 0;

                          return (
                            <div key={optionIndex}
                              className={`flex items-center gap-2 px-3 py-2 rounded-lg transition-colors ${
                                isSelected ? 'bg-orange-50 ring-1 ring-orange-300' : 'hover:bg-gray-50'
                              }`}>
                              {option.image_url ? (
                                <img src={option.image_url} alt={option.product_name} className="w-7 h-7 object-cover rounded flex-shrink-0" onError={(e) => { e.target.style.display = 'none'; }} />
                              ) : (
                                <div className="w-7 h-7 rounded bg-gray-100 flex-shrink-0" />
                              )}
                              <span className="flex-1 text-[13px] text-gray-700 truncate">{option.product_name}</span>
                              {option.price_adjustment > 0 ? (
                                <span className="text-[11px] font-medium text-orange-600">+${parseInt(option.price_adjustment).toLocaleString('es-CL')}</span>
                              ) : (
                                <span className="text-[11px] text-green-500">Incluido</span>
                              )}
                              {maxSelections > 1 ? (
                                <div className="flex items-center gap-1 flex-shrink-0">
                                  <button onClick={(e) => { e.stopPropagation(); handleSelectionChange(groupName, option.product_id, maxSelections, 'remove'); }}
                                    disabled={currentCount === 0}
                                    className="w-5 h-5 rounded-full bg-gray-200 hover:bg-gray-300 disabled:opacity-20 flex items-center justify-center text-gray-700 font-bold text-xs">−</button>
                                  <span className="w-4 text-center font-bold text-xs text-gray-700">{currentCount}</span>
                                  <button onClick={(e) => { e.stopPropagation(); handleSelectionChange(groupName, option.product_id, maxSelections, 'add'); }}
                                    disabled={totalSelected >= maxSelections}
                                    className="w-5 h-5 rounded-full bg-orange-500 hover:bg-orange-600 disabled:opacity-20 flex items-center justify-center text-white font-bold text-xs">+</button>
                                </div>
                              ) : (
                                <div onClick={() => handleSelectionChange(groupName, option.product_id, 1)}
                                  className={`w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 cursor-pointer ${isSelected ? 'border-orange-500 bg-orange-500' : 'border-gray-300'}`}>
                                  {isSelected && <Check size={12} className="text-white" />}
                                </div>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          ) : (
            <div className="text-center py-8">
              <p className="text-gray-600">Error cargando datos del combo</p>
            </div>
          )}
        </div>

        <div className="border-t p-4">
          <button
            onClick={handleAddToCart}
            disabled={loading || !comboData}
            className="w-full bg-orange-500 hover:bg-orange-600 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2"
          >
            <Plus size={20} />
            Agregar al Carrito - ${comboTotalPrice.toLocaleString('es-CL')}
          </button>
        </div>
      </div>
    </div>
  );
};

export default ComboModal;
