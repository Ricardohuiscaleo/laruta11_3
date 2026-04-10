import React, { useState, useEffect } from 'react';
import { X, Check } from 'lucide-react';

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
      console.log('DEBUG-SISTEMA-NUEVO-V3: Cargando combo', combo.name, 'con ID:', combo.id);

      // Map product names to real combo IDs (Fallback hardcoded)
      const comboMapping = {
        'Combo Doble Mixta': 1,
        'Combo Completo': 2,
        'Combo Gorda': 3,
        'Combo Dupla': 4,
        'Combo Salchipapa': 234,
        'Combo Salchipapas': 234
      };

      let realComboId = comboMapping[combo.name] || combo.id;
      
      const baseUrl = window.location.port === '4321' ? 'http://localhost:3000' : '';

      const fetchCombo = async (id) => {
        const response = await fetch(`${baseUrl}/api/get_combos.php?combo_id=${id}&v=${Date.now()}`);
        return await response.json();
      };

      let data = await fetchCombo(realComboId);

      // Si no se encuentra por ID, intentar buscar por nombre en todos los combos
      if (!data.success || !data.combos || data.combos.length === 0) {
        console.log('Combo ID not found, searching by name...');
        const allResponse = await fetch(`${baseUrl}/api/get_combos.php?v=${Date.now()}`);
        const allData = await allResponse.json();
        
        if (allData.success && allData.combos) {
          // Buscar coincidencia exacta o por palabra clave
          const match = allData.combos.find(c => 
            c.name.toLowerCase() === combo.name.toLowerCase() ||
            (combo.name.toLowerCase().includes('salchipapa') && c.name.toLowerCase().includes('salchipapa'))
          );
          
          if (match) {
            console.log('Match found by name:', match.name, 'ID:', match.id);
            // Cargar los detalles de ese combo específico (para obtener items fijos y selecciones)
            data = await fetchCombo(match.id);
          }
        }
      }

      console.log('Final combo data received:', data);

      if (data.success && data.combos && data.combos.length > 0) {
        const comboDetails = data.combos[0];
        setComboData(comboDetails);
      } else {
        console.warn('No combo configuration found for:', combo.name);
        onClose();
        onAddToCart({ ...combo, quantity: 1, isSimpleProduct: true });
        return;
      }
    } catch (error) {
      console.error('Error loading combo data:', error);
    }
    setLoading(false);
  };

  const handleSelectionChange = (groupName, productId, maxSelections, action = 'toggle') => {
    setSelections(prev => {
      const currentSelections = prev[groupName] || [];

      if (maxSelections === 1) {
        // Single selection
        const isSelected = currentSelections === productId;
        return {
          ...prev,
          [groupName]: isSelected ? null : productId
        };
      } else {
        // Multiple selection with quantities
        const currentArray = Array.isArray(currentSelections) ? currentSelections : [];
        const totalCount = currentArray.length;

        if (action === 'add') {
          // Add one more of this product
          if (totalCount < maxSelections) {
            return {
              ...prev,
              [groupName]: [...currentArray, productId]
            };
          }
        } else if (action === 'remove') {
          // Remove one of this product
          const index = currentArray.indexOf(productId);
          if (index > -1) {
            const newArray = [...currentArray];
            newArray.splice(index, 1);
            return {
              ...prev,
              [groupName]: newArray
            };
          }
        }
      }
      return prev;
    });
  };

  const calculateTotalPrice = () => {
    if (!comboData) return parseInt(combo.price || 0);

    let totalPrice = parseInt(combo.price || 0);

    // Add additional prices from selections
    Object.entries(selections).forEach(([groupName, selection]) => {
      const options = comboData.selection_groups?.[groupName];
      if (Array.isArray(selection)) {
        // Multiple selections
        selection.forEach(productId => {
          const option = options?.find(o => o.product_id === productId);
          if (option && option.additional_price) {
            totalPrice += parseInt(option.additional_price);
          }
        });
      } else if (selection) {
        // Single selection
        const option = options?.find(o => o.product_id === selection);
        if (option && option.additional_price) {
          totalPrice += parseInt(option.additional_price);
        }
      }
    });

    return totalPrice;
  };

  const handleAddToCart = () => {
    if (!comboData) return;

    // Validate all required selections are made
    const invalidGroups = [];
    Object.entries(comboData.selection_groups || {}).forEach(([groupName, options]) => {
      const maxSelections = options[0]?.max_selections || 1;
      const totalSelected = getTotalSelected(groupName);
      if (totalSelected !== maxSelections) {
        invalidGroups.push(`${groupName} (${totalSelected}/${maxSelections})`);
      }
    });

    if (invalidGroups.length > 0) {
      alert(`Por favor completa las selecciones:\n${invalidGroups.join('\n')}`);
      return;
    }

    // Build selections object with product details
    const detailedSelections = {};
    Object.entries(selections).forEach(([groupName, selection]) => {
      const options = comboData.selection_groups?.[groupName];
      if (Array.isArray(selection)) {
        // Multiple selections
        detailedSelections[groupName] = selection.map(productId => {
          const option = options?.find(o => o.product_id === productId);
          return option ? {
            id: option.product_id,
            name: option.product_name,
            price: option.additional_price || 0
          } : null;
        }).filter(Boolean);
      } else if (selection) {
        // Single selection
        const option = options?.find(o => o.product_id === selection);
        if (option) {
          detailedSelections[groupName] = {
            id: option.product_id,
            name: option.product_name,
            price: option.additional_price || 0
          };
        }
      }
    });

    const finalPrice = calculateTotalPrice();

    const comboWithSelections = {
      ...combo,
      price: finalPrice,
      selections: detailedSelections,
      fixed_items: comboData.fixed_items || [],
      displayName: getComboDisplayName(),
      quantity: 1
    };

    onAddToCart(comboWithSelections);
    onClose();
  };

  const getComboDisplayName = () => {
    if (!comboData) return combo.name;

    let name = combo.name;
    Object.entries(selections).forEach(([groupName, productId]) => {
      const options = comboData.selection_groups?.[groupName];
      const option = options?.find(o => o.product_id === productId);
      if (option) {
        name += ` + ${option.product_name}`;
      }
    });
    return name;
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={onClose}>
      <div className="bg-white w-[95vw] max-w-6xl mx-4 rounded-2xl flex flex-col animate-slide-up max-h-[90vh]" onClick={(e) => e.stopPropagation()}>
        <div className="border-b flex justify-between items-center p-3 sm:p-4">
          <h2 className="font-bold text-gray-800 text-lg sm:text-xl">Personalizar Combo</h2>
          <button onClick={onClose} className="p-1 text-gray-500 hover:text-gray-800">
            <X size={24} />
          </button>
        </div>

        <div className="flex-grow overflow-y-auto p-3 sm:p-4">
          {loading ? (
            <div className="text-center py-8">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500 mx-auto"></div>
              <p className="text-gray-600 mt-4">Cargando combo...</p>
            </div>
          ) : comboData ? (
            <div className="space-y-4 sm:space-y-6">              {/* Combo Header super resumido */}
              <div className="text-center mb-2">
                <h3 className="text-xl sm:text-2xl font-bold text-gray-800">{combo.name}</h3>
                <p className="text-lg sm:text-xl font-bold text-orange-500 mt-1">${parseInt(combo.price).toLocaleString('es-CL')}</p>
              </div>

              {/* Selection Groups (Omitimos Mostrar los Fixed Items para mantener UI Limpia) */}
              {comboData.selection_groups && Object.entries(comboData.selection_groups).map(([groupName, options], groupIndex) => {
                const maxSelections = options.length > 0 ? (options[0].max_selections || 1) : 1;
                const totalSelected = getTotalSelected(groupName);
                return (
                  <div key={groupIndex}>
                    <h4 className="text-base sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-3 text-center bg-gray-100 py-2 rounded-lg">
                      Elige tu {groupName} ({totalSelected}/{maxSelections})
                    </h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
                      {options.map((option, optionIndex) => {
                        const currentCount = maxSelections === 1
                          ? (selections[groupName] === option.product_id ? 1 : 0)
                          : (Array.isArray(selections[groupName]) ? selections[groupName].filter(id => id === option.product_id).length : 0);
                        const totalSelectedInGroup = getTotalSelected(groupName);

                        return (
                          <div
                            key={optionIndex}
                            className={`flex items-center gap-2 sm:gap-3 p-2 sm:p-3 rounded-lg border-2 transition-all ${currentCount > 0
                              ? 'border-orange-500 bg-orange-50'
                              : 'border-gray-200'
                              }`}
                          >
                            {option.image_url && (
                              <img
                                src={option.image_url}
                                alt={option.product_name}
                                className="w-10 h-10 sm:w-12 sm:h-12 object-cover rounded flex-shrink-0"
                                onError={(e) => {
                                  console.log('Error loading option image:', e.target.src);
                                  e.target.style.display = 'none';
                                }}
                              />
                            )}
                            <div className="flex-1 min-w-0 text-left">
                              <p className="font-medium text-gray-800 text-sm sm:text-base truncate">{option.product_name}</p>
                              {option.additional_price > 0 ? (
                                <p className="text-xs sm:text-sm text-orange-600">+${parseInt(option.additional_price).toLocaleString('es-CL')}</p>
                              ) : (
                                <p className="text-xs sm:text-sm text-green-600 font-medium">Incluido</p>
                              )}
                            </div>
                            {maxSelections > 1 ? (
                                <div className="flex items-center gap-1 sm:gap-2 flex-shrink-0">
                                  <button
                                    onClick={() => handleSelectionChange(groupName, option.product_id, maxSelections, 'remove')}
                                    disabled={currentCount === 0}
                                    className="combo-nav-btn w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center transition-colors font-black text-lg sm:text-xl"
                                  >
                                    -
                                  </button>
                                  <span className="w-6 sm:w-8 text-center font-bold text-gray-800 text-base sm:text-lg">{currentCount}</span>
                                  <button
                                    onClick={() => handleSelectionChange(groupName, option.product_id, maxSelections, 'add')}
                                    disabled={totalSelectedInGroup >= maxSelections}
                                    className="combo-nav-btn w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-green-500 hover:bg-green-600 disabled:bg-gray-200 text-white disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center transition-all active:scale-90 shadow-sm font-bold text-lg sm:text-xl"
                                  >
                                    +
                                  </button>
                                </div>
                              ) : (
                                <button
                                  onClick={() => handleSelectionChange(groupName, option.product_id, maxSelections)}
                                  className={`combo-nav-btn w-9 h-9 sm:w-10 sm:h-10 rounded-lg border-2 flex items-center justify-center transition-all flex-shrink-0 ${currentCount > 0 ? 'bg-green-500 border-green-500 text-white' : 'border-gray-200 hover:bg-gray-50'
                                    }`}
                                >
                                  {currentCount > 0 ? (
                                    <Check size={20} />
                                  ) : (
                                    <div className="w-4 h-4 sm:w-5 sm:h-5 rounded-md border-2 border-gray-300"></div>
                                  )}
                                </button>
                              )}
                            </div>
                          );
                        })}
                      </div>
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
  
          <div className="border-t p-3 sm:p-4 flex gap-3 sm:gap-4">
            <button
              onClick={onClose}
              className="combo-nav-btn flex-1 font-bold py-3 sm:py-3.5 px-3 sm:px-4 rounded-xl transition-all flex items-center justify-center shadow-md bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm sm:text-base"
            >
              Volver
            </button>
            <button
              onClick={handleAddToCart}
              disabled={loading || !comboData}
              className={`combo-nav-btn flex-1 font-bold py-3 sm:py-3.5 px-3 sm:px-4 rounded-xl transition-all flex items-center justify-center shadow-lg active:scale-95 text-sm sm:text-base ${loading || !comboData ? 'bg-gray-300 text-gray-500' : 'bg-green-500 hover:bg-green-600 text-white'
                }`}
            >
              Agregar - ${calculateTotalPrice().toLocaleString('es-CL')}
            </button>
        </div>
      </div>
    </div>
  );
};

export default ComboModal;
