import React, { useState, useMemo, useEffect, useRef } from 'react';
import {
  PlusCircle, X, MinusCircle, ZoomIn, ChevronDown, ChevronUp, ChefHat
} from 'lucide-react';

const ComboItem = ({ item, isExtra = false, onAddToCart, onRemoveFromCart, getProductQuantity }) => {
  const quantity = getProductQuantity(item.id);
  const maxReached = isExtra && item.maxQuantity && quantity >= item.maxQuantity;

  const displayPrice = item.price;
  const priceText = item.price === 0 ? 'Gratis' : `$${item.price.toLocaleString('es-CL')}`;
  const priceColor = 'text-orange-500';

  return (
    <div className="flex justify-between items-center bg-gray-50 p-2 rounded-lg">
      <div className="flex items-center gap-3">
        {item.image ? (
          <img
            src={item.image}
            alt={item.name}
            className="w-12 h-12 object-cover rounded-md flex-shrink-0"
          />
        ) : (
          <div className="w-12 h-12 bg-gray-200 rounded-md flex-shrink-0 animate-pulse"></div>
        )}
        <div>
          <p className="font-semibold text-sm text-gray-800">{item.name}</p>
          <p className={`text-xs font-medium ${priceColor}`}>
            {priceText}
            {item.extraPrice && ` / +$${item.extraPrice.toLocaleString('es-CL')}`}
            {isExtra && item.maxQuantity && ` (máx. ${item.maxQuantity})`}
          </p>
          {item.description && <p className="text-xs text-gray-500 italic">{item.description}</p>}
        </div>
      </div>
      <div className="flex items-center gap-2">
        {quantity > 0 && (
          <button
            onClick={() => onRemoveFromCart(item.id)}
            className="text-red-500 hover:text-red-700 p-1 bg-red-50 rounded-lg transition-colors"
          >
            <MinusCircle size={22} />
          </button>
        )}
        {quantity > 0 && <span className="font-bold w-6 text-center text-lg">{quantity}</span>}
        <button
          onClick={() => onAddToCart(item)}
          disabled={maxReached}
          className={`px-2 rounded-lg transition-all active:scale-95 flex items-center justify-center font-bold text-xl ${maxReached ? 'text-gray-300 bg-gray-100 cursor-not-allowed' : 'text-green-500 hover:text-green-700 bg-green-50'}`}
          style={{ height: '32px', minWidth: '32px' }}
        >
          +
        </button>
      </div>
    </div>
  );
};

const ComboSection = ({ title, items, isExtra = false, titleColor = 'text-gray-700', sectionKey, expandedComboSections, setExpandedComboSections, onAddToCart, onRemoveFromCart, getProductQuantity, useTempCart = false }) => {
  const isExpanded = expandedComboSections.has(sectionKey);
  const toggleSection = () => {
    setExpandedComboSections(prev => {
      const newSet = new Set(prev);
      if (newSet.has(sectionKey)) {
        newSet.delete(sectionKey);
      } else {
        newSet.add(sectionKey);
      }
      return newSet;
    });
  };

  return (
    <div className="mb-4 border border-gray-200 rounded-lg overflow-hidden">
      <button
        onClick={toggleSection}
        className="w-full px-4 py-3 flex items-center justify-between bg-gray-50 hover:bg-gray-100 transition-colors"
      >
        <div className="flex items-center gap-2">
          <h4 className={`font-bold ${titleColor}`}>{title}</h4>
          {items.length > 0 && (
            <span className="bg-orange-500 text-white text-xs px-2 py-1 rounded-full">
              {items.length}
            </span>
          )}
        </div>
        {isExpanded ? (
          <ChevronUp className="text-gray-600" size={18} />
        ) : (
          <ChevronDown className="text-gray-600" size={18} />
        )}
      </button>

      <div className={`transition-all duration-300 ease-in-out overflow-hidden ${isExpanded ? 'max-h-80 opacity-100' : 'max-h-0 opacity-0'
        }`}>
        <div className="p-4 space-y-2 max-h-80 overflow-y-auto">
          {items.map(item => (
            <ComboItem
              key={item.id}
              item={item}
              isExtra={isExtra}
              onAddToCart={onAddToCart}
              onRemoveFromCart={onRemoveFromCart}
              getProductQuantity={getProductQuantity}
            />
          ))}
        </div>
      </div>
    </div>
  );
};

const ProductDetailModal = ({
  product,
  onClose,
  onAddToCart,
  onRemoveFromCart,
  getProductQuantity,
  activeCategory,
  comboItems,
  cart,
  onZoom,
  setReviewsModalProduct,
  user,
  onUpdateCartItem
}) => {
  if (!product) return null;

  const scrollRef = useRef(null);
  const [isHeaderSticky, setIsHeaderSticky] = useState(false);
  const [newReviewRating, setNewReviewRating] = useState(0);
  const [newReviewComment, setNewReviewComment] = useState('');
  const [newReviewName, setNewReviewName] = useState('');
  const [showReviewForm, setShowReviewForm] = useState(false);
  const [expandedComboSections, setExpandedComboSections] = useState(new Set(['personalizar']));

  const [tempCustomizations, setTempCustomizations] = useState(() => {
    if (product.isEditing && product.customizations) {
      const initial = {};
      product.customizations.forEach(c => {
        initial[c.id] = c.quantity;
      });
      return initial;
    }
    return {};
  });

  const handleTempAdd = (item) => {
    setTempCustomizations(prev => ({
      ...prev,
      [item.id]: (prev[item.id] || 0) + 1
    }));
  };

  const handleTempRemove = (itemId) => {
    setTempCustomizations(prev => {
      const newQty = (prev[itemId] || 0) - 1;
      if (newQty <= 0) {
        const { [itemId]: _, ...rest } = prev;
        return rest;
      }
      return { ...prev, [itemId]: newQty };
    });
  };

  const getTempQuantity = (itemId) => tempCustomizations[itemId] || 0;

  // Inicializar nombre cuando se abre el formulario
  useEffect(() => {
    if (showReviewForm) {
      setNewReviewName(user?.nombre || '');
    }
  }, [showReviewForm, user]);

  const effectiveCategory = product._overrideCategory || activeCategory;
  const showComboSection = ['churrascos', 'hamburguesas', 'completos', 'la_ruta_11', 'papas_y_snacks', 'papas', 'Combos'].includes(effectiveCategory);

  const handleScroll = () => {
    if (scrollRef.current) {
      setIsHeaderSticky(scrollRef.current.scrollTop > 60);
    }
  };

  const comboSubtotal = useMemo(() => {
    const allComboItems = [
      ...comboItems.papas_y_snacks,
      ...comboItems.jugos,
      ...comboItems.bebidas,
      ...comboItems.salsas,
      ...comboItems.personalizar,
      ...comboItems.extras,
      ...comboItems.empanadas,
      ...comboItems.cafe,
      ...comboItems.te
    ];

    return Object.entries(tempCustomizations).reduce((total, [itemId, qty]) => {
      const item = allComboItems.find(i => i.id === parseInt(itemId));
      if (!item) return total;

      let itemPrice = item.price;
      if (item.extraPrice && qty > 1) {
        itemPrice = item.price + (qty - 1) * item.extraPrice;
      } else {
        itemPrice = item.price * qty;
      }
      return total + itemPrice;
    }, 0);
  }, [tempCustomizations, comboItems]);

  const displayTotal = useMemo(() => product.price + comboSubtotal, [product.price, comboSubtotal]);

  const filterActive = (items) => (items || []).filter(item => item.active === 1 || item.is_active === 1 || item.active === true);

  return (
    <div className="fixed inset-0 bg-white z-[100] flex flex-col animate-slide-up">
      <div className="bg-white w-full h-full flex flex-col">
        <div className="bg-white border-b p-4 flex justify-between items-center flex-shrink-0" style={{ paddingTop: 'calc(env(safe-area-inset-top, 0px) + 16px)' }}>
          <h2 className="text-lg font-bold text-gray-800">Personalizando {product.name} ${displayTotal.toLocaleString('es-CL')}</h2>
          <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600">
            <X size={24} />
          </button>
        </div>

        <div className="flex-grow overflow-y-auto" ref={scrollRef}>
          <div className="p-6">
            {/* Descripción del Producto */}
            <div className="mb-6 bg-gray-50 p-4 rounded-xl border border-gray-100">
              <h3 className="font-bold text-gray-800 mb-2 flex items-center gap-2">
                <ChefHat size={18} className="text-orange-500" />
                Descripción
              </h3>
              <p className="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap">
                {product.description || 'Sin descripción disponible.'}
              </p>
            </div>

            {showComboSection && (
              <div className="border-t pt-4">
                <h3 className="text-lg font-bold text-gray-800 mb-4">Combina tu Pedido</h3>
                {/* Sección Personalizar solo para hamburguesas, churrascos, completos, tomahawks y subcategoría papas */}
                {(['hamburguesas', 'churrascos', 'completos', 'la_ruta_11', 'papas', 'Combos'].includes(effectiveCategory) ||
                  (effectiveCategory === 'papas_y_snacks' && product.subcategory_name === 'Papas')) && (
                    <ComboSection
                      title={`Personaliza tu ${effectiveCategory === 'hamburguesas' ? 'Hamburguesa' : effectiveCategory === 'churrascos' ? 'Sandwich' : effectiveCategory === 'completos' ? 'Completo' : effectiveCategory === 'la_ruta_11' ? 'Tomahawk' : effectiveCategory === 'papas' ? 'Papas' : effectiveCategory === 'Combos' ? 'Combo' : 'Papas'}`}
                      items={filterActive(comboItems.personalizar)}
                      isExtra={true}
                      titleColor="text-orange-600"
                      sectionKey="personalizar"
                      expandedComboSections={expandedComboSections}
                      setExpandedComboSections={setExpandedComboSections}
                      onAddToCart={handleTempAdd}
                      onRemoveFromCart={handleTempRemove}
                      getProductQuantity={getTempQuantity}
                      useTempCart={true}
                    />
                  )}
                <ComboSection title="Jugos" items={filterActive(comboItems.jugos)} sectionKey="jugos" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getTempQuantity} useTempCart={true} />
                <ComboSection title="Bebidas" items={filterActive(comboItems.bebidas)} sectionKey="bebidas" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getTempQuantity} useTempCart={true} />
                <ComboSection title="Café" items={filterActive(comboItems.cafe)} sectionKey="cafe" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getTempQuantity} useTempCart={true} />
                <ComboSection title="Té" items={filterActive(comboItems.te)} sectionKey="te" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getTempQuantity} useTempCart={true} />
                <ComboSection title="Salsas" items={filterActive(comboItems.salsas)} sectionKey="salsas" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getTempQuantity} useTempCart={true} />
                <ComboSection title="Extras" items={filterActive(comboItems.extras)} isExtra={true} titleColor="text-red-600" sectionKey="extras" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getTempQuantity} useTempCart={true} />
                {comboSubtotal > 0 &&
                  <div className="mt-4 pt-4 border-t flex justify-between items-center">
                    <h4 className="font-bold text-gray-800">Subtotal Acompañamientos</h4>
                    <p className="font-bold text-lg text-orange-500">${comboSubtotal.toLocaleString('es-CL')}</p>
                  </div>
                }
              </div>
            )}
          </div>
        </div>

        <div className="p-4 bg-white border-t sticky bottom-0 flex-shrink-0">
          <button
            onClick={() => {
              const allComboItems = [
                ...comboItems.papas_y_snacks,
                ...comboItems.jugos,
                ...comboItems.bebidas,
                ...comboItems.salsas,
                ...comboItems.personalizar,
                ...comboItems.extras,
                ...comboItems.empanadas,
                ...comboItems.cafe,
                ...comboItems.te
              ];

              const customizationsArray = Object.entries(tempCustomizations)
                .map(([itemId, qty]) => {
                  const item = allComboItems.find(i => i.id === parseInt(itemId));
                  if (!item) return null;
                  return { ...item, quantity: qty };
                })
                .filter(Boolean);

              const isEditing = product.isEditing;
              const cartIndex = product.cartIndex;

              if (isEditing && onUpdateCartItem) {
                onUpdateCartItem(cartIndex, product, customizationsArray);
              } else {
                onAddToCart(product);
              }
              onClose();
            }}
            className={`w-full py-4 rounded-xl text-lg font-bold flex items-center justify-center transition-transform active:scale-95 shadow-lg ${product.isEditing
              ? 'bg-blue-600 hover:bg-blue-700 text-white'
              : 'bg-green-600 hover:bg-green-700 text-white'
              }`}
          >
            {product.isEditing ? 'Actualizar Producto' : 'Agregar al Carro'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default ProductDetailModal;