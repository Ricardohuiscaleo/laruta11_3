import React, { useState, useMemo, useEffect, useRef } from 'react';
import { 
    PlusCircle, X, MinusCircle, ZoomIn, ChevronDown, ChevronUp, Pizza
} from 'lucide-react';
import { GiHamburger, GiHotDog, GiFrenchFries, GiSandwich } from 'react-icons/gi';
import { CupSoda } from 'lucide-react';

const ComboItem = ({ item, isExtra = false, onAddToCart, onRemoveFromCart, getProductQuantity, preventClose, useTempCart, getTempQuantity }) => {
    const quantity = useTempCart ? getTempQuantity(item.id) : getProductQuantity(item.id);
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
                   <button onClick={(e) => { e.stopPropagation(); onRemoveFromCart(item.id); }} className="text-red-500 hover:text-red-700"><MinusCircle size={22} /></button>
              )}
              {quantity > 0 && <span className="font-bold w-5 text-center">{quantity}</span>}
              <button 
                  onClick={(e) => { e.stopPropagation(); onAddToCart(item); }} 
                  disabled={maxReached}
                  className={`${maxReached ? 'text-gray-400 cursor-not-allowed' : 'text-green-500 hover:text-green-700'}`}
              >
                  <PlusCircle size={22} />
              </button>
          </div>
      </div>
    );
};

const ComboSection = ({ title, items, isExtra = false, titleColor = 'text-gray-700', bgColor = 'bg-gray-50', sectionKey, expandedComboSections, setExpandedComboSections, onAddToCart, onRemoveFromCart, getProductQuantity, preventClose, useTempCart, getTempQuantity }) => {
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
  
  const hoverBgColor = bgColor === 'bg-yellow-400' ? 'hover:bg-yellow-500' : 'hover:bg-gray-100';
  
  return (
    <div className="mb-4 border border-gray-200 rounded-lg overflow-hidden">
      <button
        onClick={toggleSection}
        className={`w-full px-4 py-3 flex items-center justify-between ${bgColor} ${hoverBgColor} transition-colors`}
      >
        <div className="flex items-center gap-2 text-left">
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
      
      <div className={`transition-all duration-300 ease-in-out overflow-hidden ${
        isExpanded ? 'max-h-80 opacity-100' : 'max-h-0 opacity-0'
      }`}>
        <div className="p-4 space-y-2 max-h-80 overflow-y-auto scrollbar-hide" style={{scrollbarWidth: 'none', msOverflowStyle: 'none'}}>
          {items.map(item => (
            <ComboItem 
              key={item.id} 
              item={item} 
              isExtra={isExtra} 
              onAddToCart={onAddToCart}
              onRemoveFromCart={onRemoveFromCart}
              getProductQuantity={getProductQuantity}
              preventClose={preventClose}
              useTempCart={useTempCart}
              getTempQuantity={getTempQuantity}
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
  
  const isEditing = product.isEditing;
  const cartIndex = product.cartIndex;
  
  // Definir íconos por categoría
  const categoryIcons = {
    hamburguesas: <GiHamburger size={20} />,
    hamburguesas_100g: <GiHamburger size={16} />,
    churrascos: <GiSandwich size={20} />,
    completos: <GiHotDog size={20} />,
    papas: <GiFrenchFries size={20} />,
    pizzas: <Pizza size={20} />,
    bebidas: <CupSoda size={20} />,
    Combos: (
      <div style={{display: 'flex', alignItems: 'center', gap: '2px'}}>
        <GiHamburger size={16} />
        <CupSoda size={16} />
      </div>
    )
  };
  
  const currentIcon = categoryIcons[activeCategory] || <PlusCircle size={20} />;
  
  const scrollRef = useRef(null);
  const [isHeaderSticky, setIsHeaderSticky] = useState(false);
  const [newReviewRating, setNewReviewRating] = useState(0);
  const [newReviewComment, setNewReviewComment] = useState('');
  const [newReviewName, setNewReviewName] = useState('');
  const [showReviewForm, setShowReviewForm] = useState(false);
  const [expandedComboSections, setExpandedComboSections] = useState(new Set());
  const [showExitWarning, setShowExitWarning] = useState(false);
  const [tempCustomizations, setTempCustomizations] = useState(() => {
    if (isEditing && product.customizations) {
      const initial = {};
      product.customizations.forEach(c => {
        initial[c.id] = c.quantity;
      });
      return initial;
    }
    return {};
  });
  
  const handleClose = () => {
    const hasCustomizations = Object.keys(tempCustomizations).length > 0;
    if (hasCustomizations) {
      setShowExitWarning(true);
    } else {
      onClose();
    }
  };
  
  const preventClose = () => {};
  
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
  
  // Siempre mostrar sección de personalización
  const showComboSection = true;
  
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
  
  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in">
      <div className="bg-white w-full h-full sm:w-auto sm:h-auto sm:max-w-2xl sm:max-h-[90vh] sm:rounded-2xl flex flex-col animate-slide-up sm:shadow-2xl">
        <div className="relative flex-shrink-0 bg-white border-b sm:rounded-t-2xl">
          <div className="flex items-center justify-between p-4">
            <h3 className="font-bold text-lg text-gray-800">Combina tu Pedido</h3>
            <button onClick={handleClose} className="bg-gray-100 rounded-full p-2 text-gray-800 hover:bg-gray-200 transition-all">
              <X size={24} />
            </button>
          </div>
        </div>
        
        <div className="flex-grow relative overflow-y-auto scrollbar-hide" ref={scrollRef} onScroll={handleScroll} style={{scrollbarWidth: 'none', msOverflowStyle: 'none'}}>
          <div className="px-3 pt-4 pb-6">
            <ComboSection 
              title={`Personaliza "${product.name}"`} 
              items={comboItems.personalizar} 
              isExtra={true} 
              titleColor="text-black text-left"
              bgColor="bg-yellow-400"
              sectionKey="personalizar"
              expandedComboSections={expandedComboSections}
              setExpandedComboSections={setExpandedComboSections}
              onAddToCart={handleTempAdd}
              onRemoveFromCart={handleTempRemove}
              getProductQuantity={getProductQuantity}
              preventClose={preventClose}
              useTempCart={true}
              getTempQuantity={getTempQuantity}
            />

            <ComboSection title="Jugos" items={comboItems.jugos} sectionKey="jugos" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getProductQuantity} preventClose={preventClose} useTempCart={true} getTempQuantity={getTempQuantity} />
            <ComboSection title="💡 ¿Agregar una bebida?" items={comboItems.bebidas} sectionKey="bebidas" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getProductQuantity} preventClose={preventClose} useTempCart={true} getTempQuantity={getTempQuantity} />
            <ComboSection title="Café" items={comboItems.cafe} sectionKey="cafe" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getProductQuantity} preventClose={preventClose} useTempCart={true} getTempQuantity={getTempQuantity} />
            <ComboSection title="Té" items={comboItems.te} sectionKey="te" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getProductQuantity} preventClose={preventClose} useTempCart={true} getTempQuantity={getTempQuantity} />
            <ComboSection title="Salsas" items={comboItems.salsas} sectionKey="salsas" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getProductQuantity} preventClose={preventClose} useTempCart={true} getTempQuantity={getTempQuantity} />
            {comboItems.extras && comboItems.extras.length > 0 && (
              <ComboSection title="Extras" items={comboItems.extras} isExtra={true} titleColor="text-red-600" sectionKey="extras" expandedComboSections={expandedComboSections} setExpandedComboSections={setExpandedComboSections} onAddToCart={handleTempAdd} onRemoveFromCart={handleTempRemove} getProductQuantity={getProductQuantity} preventClose={preventClose} useTempCart={true} getTempQuantity={getTempQuantity} />
            )}
          </div>
        </div>
        
        <div className="p-4 pb-6 bg-white border-t sticky bottom-0 flex-shrink-0">
          {comboSubtotal > 0 && (
            <div className="mb-3 flex justify-between items-center">
              <h4 className="font-bold text-gray-800">Subtotal Acompañamientos</h4>
              <p className="font-bold text-lg text-orange-500">${comboSubtotal.toLocaleString('es-CL')}</p>
            </div>
          )}
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
              
              if (isEditing && onUpdateCartItem) {
                onUpdateCartItem(cartIndex, product, customizationsArray);
              } else {
                // Agregar producto principal con personalizaciones adjuntas
                const productWithCustomizations = {
                  ...product,
                  customizations: customizationsArray.length > 0 ? customizationsArray : null,
                  quantity: 1,
                  cartItemId: Date.now()
                };
                onAddToCart(productWithCustomizations);
              }
              onClose();
            }} 
            className={`w-full ${isEditing ? 'bg-orange-500 hover:bg-orange-600' : 'bg-orange-500 hover:bg-orange-600'} text-white py-4 rounded-md text-lg font-bold flex items-center justify-center gap-2 transition-transform active:scale-95 shadow-lg`}
          >
            {isEditing ? (
              <span>Agregar a este producto</span>
            ) : (
              <>
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/>
                </svg>
                Agregar al Carro
              </>
            )}
          </button>
        </div>
      </div>
      
      {/* Modal de advertencia de salida */}
      {showExitWarning && (
        <div className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" onClick={() => setShowExitWarning(false)}>
          <div className="bg-white rounded-xl p-6 max-w-sm w-full animate-slide-up" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-gray-800 mb-2">¿Salir sin agregar?</h3>
            <p className="text-gray-600 text-sm mb-6">
              Has seleccionado personalizaciones que no se han agregado al producto. ¿Deseas salir sin guardar los cambios?
            </p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowExitWarning(false)}
                className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded-lg font-semibold transition-colors"
              >
                Cancelar
              </button>
              <button
                onClick={() => {
                  setShowExitWarning(false);
                  onClose();
                }}
                className="flex-1 bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-lg font-semibold transition-colors"
              >
                Salir sin guardar
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProductDetailModal;