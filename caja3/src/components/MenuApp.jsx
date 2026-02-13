import React, { useState, useMemo, useEffect, useRef } from 'react';
import { 
    PlusCircle, X, Star, ShoppingCart, MinusCircle, User, ZoomIn,
    Award, ChefHat, GlassWater, CupSoda, Droplets,
    Eye, Heart, MessageSquare, Calendar, Search, Bike, Caravan, ChevronDown, ChevronUp, Package,
    Truck, TruckIcon, Navigation, MapPin, Clock, CheckCircle2, XCircle, CreditCard, Banknote, Smartphone, Percent, Tag, Pizza, Share2, Settings
} from 'lucide-react';
import { GiHamburger, GiHotDog, GiFrenchFries, GiMeat, GiSandwich, GiSteak } from 'react-icons/gi';
import OnboardingModal from './OnboardingModal.jsx';
import LoadingScreen from './LoadingScreen.jsx';
import TUUPaymentIntegration from './TUUPaymentIntegration.jsx';
import ReviewsModal from './ReviewsModal.jsx';
import OrderNotifications from './OrderNotifications.jsx';
import MiniComandas from './MiniComandas.jsx';
import OrdersListener from './OrdersListener.jsx';
import ChecklistsListener from './ChecklistsListener.jsx';
import ProductDetailModal from './modals/ProductDetailModal.jsx';
import ProfileModal from './modals/ProfileModal.jsx';
import SecurityModal from './modals/SecurityModal.jsx';
import SaveChangesModal from './modals/SaveChangesModal.jsx';
import FloatingHeart from './ui/FloatingHeart.jsx';
import StarRating from './ui/StarRating.jsx';
import GoogleLogo from './ui/GoogleLogo.jsx';
import HotdogIcon from './ui/HotdogIcon.jsx';
import NotificationIcon from './ui/NotificationIcon.jsx';
import ShareProductModal from './modals/ShareProductModal.jsx';
import ComboModal from './modals/ComboModal.jsx';
import PaymentPendingModal from './modals/PaymentPendingModal.jsx';
import SwipeToggle from './SwipeToggle.jsx';
import useDoubleTap from '../hooks/useDoubleTap.js';
import { vibrate, playNotificationSound, createConfetti, initAudio, playComandaSound } from '../utils/effects.js';
import { validateCheckoutForm, getFormDisabledState } from '../utils/validation.js';



// ============================================
// CAJA3: UBICACIÓN DESACTIVADA
// Esta app NO solicita ubicación del usuario
// ============================================

// Datos del menú - se cargarán dinámicamente desde MySQL
let menuData = {
  la_ruta_11: { tomahawks: [] },
  churrascos: { carne: [], pollo: [], vegetariano: [] },
  hamburguesas: { clasicas: [], especiales: [] },
  completos: { tradicionales: [], 'al vapor': [], papas: [] },
  papas_y_snacks: { papas: [], empanadas: [], jugos: [], bebidas: [], salsas: [] },
  Combos: { hamburguesas: [], sandwiches: [], completos: [] }
};

const categoryDisplayNames = {
  hamburguesas: "Hamburguesas\n(200g)",
  hamburguesas_100g: "Hamburguesas\n(100g)",
  churrascos: "Sandwiches", 
  completos: "Completos",
  papas: "Papas",
  pizzas: "Pizzas",
  bebidas: "Bebidas",
  Combos: "Combos"
};

const mainCategories = ['hamburguesas', 'hamburguesas_100g', 'churrascos', 'completos', 'papas', 'pizzas', 'bebidas', 'Combos'];

const categoryFilters = {
  hamburguesas_100g: { category_id: 3, subcategory_id: 5 },
  hamburguesas: { category_id: 3, subcategory_id: 6 },
  papas: { category_id: 12, subcategory_ids: [9, 57] },
  pizzas: { category_id: 5, subcategory_id: 60 },
  bebidas: { category_id: 5, subcategory_ids: [11, 10, 28, 27] }
};



const categoryIcons = {
  hamburguesas: <GiHamburger style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  hamburguesas_100g: <GiHamburger style={{width: 'clamp(13.2px, 3.36vw, 16.8px)', height: 'clamp(13.2px, 3.36vw, 16.8px)'}} />,
  churrascos: <GiSandwich style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  completos: <GiHotDog style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  papas: <GiFrenchFries style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  pizzas: <Pizza style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  bebidas: <CupSoda style={{width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)'}} />,
  Combos: (
    <div style={{display: 'flex', alignItems: 'center', gap: '2px'}}>
      <GiHamburger style={{width: 'clamp(12px, 3vw, 16.8px)', height: 'clamp(12px, 3vw, 16.8px)'}} />
      <CupSoda style={{width: 'clamp(12px, 3vw, 16.8px)', height: 'clamp(12px, 3vw, 16.8px)'}} />
    </div>
  )
};

const categoryColors = {
  hamburguesas: '#D2691E', // Marrón dorado para hamburguesas
  churrascos: '#FF6347', // Tomate rojo para sandwiches
  completos: '#FF4500', // Naranja rojizo para completos
  papas_y_snacks: '#FFD700', // Dorado para papas fritas
  Combos: '#FF6B35' // Naranja para combos
};





const ImageFullscreenModal = ({ product, total, onClose }) => {
    if (!product) return null;
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [onClose]);
    return (
        <div className="fixed inset-0 bg-black/80 z-[60] flex items-center justify-center animate-fade-in" onClick={onClose}>
            <button onClick={onClose} className="absolute top-4 right-4 bg-black/50 rounded-full p-2 text-white hover:bg-black/70 transition-all z-20">
                <X size={24} />
            </button>
            <img src={product.image} alt={product.name} className="max-w-full max-h-full object-contain" />
            <div className="absolute bottom-0 left-0 right-0 p-4 bg-black/40 backdrop-blur-sm text-white text-center">
                <h3 className="text-xl font-bold">{product.name}</h3>
                <p className="text-lg text-orange-400 font-semibold">${total.toLocaleString('es-CL')}</p>
            </div>
        </div>
    );
};



const CartModal = ({ isOpen, onClose, cart, onAddToCart, onRemoveFromCart, cartTotal, onCheckout, onCustomizeProduct, showCheckoutSection, setShowCheckoutSection, customerInfo, setCustomerInfo, user, nearbyTrucks, cartSubtotal, onPaymentMethodSelect }) => {
    if (!isOpen) return null;
    
    const currentDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 
      ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) 
      : 0;
    const finalTotal = cartSubtotal + currentDeliveryFee;
    
    return (
        <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-end animate-fade-in" onClick={onClose}>
            <div className="bg-white w-full max-w-2xl max-h-[85vh] rounded-t-2xl flex flex-col animate-slide-up" onClick={(e) => e.stopPropagation()}>
                <div className="border-b flex justify-between items-center" style={{padding: 'clamp(12px, 3vw, 16px)'}}>
                    <h2 className="font-bold text-gray-800" style={{fontSize: 'clamp(16px, 4vw, 20px)'}}>Tu Pedido</h2>
                    <button onClick={onClose} className="p-1 text-gray-500 hover:text-gray-800"><X size={24} /></button>
                </div>
                {cart.length === 0 ? (
                    <div className="flex-grow flex flex-col justify-center items-center text-gray-500">
                        <ShoppingCart size={48} className="mb-4" />
                        <p>Tu carrito está vacío.</p>
                    </div>
                ) : (
                    <div className="flex-grow overflow-y-auto space-y-3" style={{padding: 'clamp(12px, 3vw, 16px)'}}>
                        {cart.map((item, itemIndex) => {
                            const displayPrice = item.price;
                            const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
                            return (
                                <div key={item.cartItemId} className="border rounded-lg p-3 bg-gray-50">
                                    <div className="flex items-center justify-between mb-2">
                                        <div className="flex items-center gap-3">
                                            {item.image ? (
                                                <img src={item.image} alt={item.name} className="w-16 h-16 object-cover rounded-md" />
                                            ) : (
                                                <div className="w-16 h-16 bg-gray-200 rounded-md animate-pulse"></div>
                                            )}
                                            <div>
                                                <p className="font-semibold">{item.name}</p>
                                                <p className="text-orange-500 font-medium">${displayPrice.toLocaleString('es-CL')}</p>
                                            </div>
                                        </div>
                                        <button onClick={() => onRemoveFromCart(item.cartItemId)} className="bg-red-500 rounded-full p-1.5 text-white hover:bg-red-600"><X size={20} /></button>
                                    </div>
                                    {(() => {
                                      // Ocultar botón personalizar para bebidas, jugos, té, café, salsas
                                      const nonPersonalizableCategories = ['Bebidas', 'Jugos', 'Té', 'Café', 'Salsas'];
                                      const shouldShowPersonalizeButton = !nonPersonalizableCategories.includes(item.subcategory_name);
                                      
                                      if (!shouldShowPersonalizeButton) return null;
                                      
                                      return (
                                        <button
                                          onClick={() => {
                                            onClose();
                                            onCustomizeProduct(item, itemIndex);
                                          }}
                                          className="mt-2 w-full bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium py-2 px-3 rounded-lg transition-colors flex items-center justify-center gap-1.5"
                                        >
                                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                                            <path d="m15 5 4 4"/>
                                          </svg>
                                          Personalizar
                                        </button>
                                      );
                                    })()}
                                    {item.customizations && item.customizations.length > 0 && (
                                      <div className="mt-2 pt-2 border-t border-gray-200">
                                        <p className="text-xs font-medium text-gray-700 mb-1">Incluye:</p>
                                        <div className="space-y-1">
                                          {item.customizations.map((custom, idx) => (
                                            <p key={idx} className="text-xs text-blue-600 font-medium">
                                              • {custom.quantity}x {custom.name} (+${(custom.price * custom.quantity).toLocaleString('es-CL')})
                                            </p>
                                          ))}
                                        </div>
                                      </div>
                                    )}
                                    {isCombo && item.selections && (
                                        <div className="mt-2 pt-2 border-t border-gray-200">
                                            <p className="text-xs font-medium text-gray-700 mb-1">Incluye:</p>
                                            <div className="space-y-1">
                                                {item.fixed_items && item.fixed_items.map((fixedItem, idx) => (
                                                    <p key={idx} className="text-xs text-gray-600">• {fixedItem.quantity || 1}x {fixedItem.product_name || fixedItem.name}</p>
                                                ))}
                                                {Object.entries(item.selections || {}).map(([group, selection]) => {
                                                    if (Array.isArray(selection)) {
                                                        return selection.map((sel, idx) => (
                                                            <p key={`${group}-${idx}`} className="text-xs text-blue-600 font-medium">• 1x {sel.name}</p>
                                                        ));
                                                    } else {
                                                        return (
                                                            <p key={group} className="text-xs text-blue-600 font-medium">• 1x {selection.name}</p>
                                                        );
                                                    }
                                                })}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
                
                {/* Checkout Section */}
                {showCheckoutSection && cart.length > 0 && (
                    <div className="border-t bg-gray-50 p-4 space-y-4">
                        <h3 className="font-bold text-gray-800 text-lg">Datos de Entrega</h3>
                        
                        {/* Delivery Type */}
                        <div className="grid grid-cols-2 gap-3">
                            <button
                                onClick={() => setCustomerInfo({...customerInfo, deliveryType: 'delivery'})}
                                className={`p-3 border-2 rounded-lg text-center transition-colors ${customerInfo.deliveryType === 'delivery' ? 'border-orange-500 bg-orange-50' : 'border-gray-300'}`}
                            >
                                <div className="text-2xl mb-1">🚚</div>
                                <div className="text-sm font-semibold">Delivery</div>
                            </button>
                            <button
                                onClick={() => setCustomerInfo({...customerInfo, deliveryType: 'pickup'})}
                                className={`p-3 border-2 rounded-lg text-center transition-colors ${customerInfo.deliveryType === 'pickup' ? 'border-orange-500 bg-orange-50' : 'border-gray-300'}`}
                            >
                                <div className="text-2xl mb-1">🏪</div>
                                <div className="text-sm font-semibold">Retiro</div>
                            </button>
                        </div>
                        
                        {/* Customer Info */}
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Nombre completo *</label>
                            <input
                                type="text"
                                placeholder="Tu nombre completo"
                                value={customerInfo.name || user?.nombre || ''}
                                onChange={(e) => setCustomerInfo({...customerInfo, name: e.target.value})}
                                className={`w-full px-3 py-2 border rounded-lg text-sm ${user ? 'bg-gray-50 text-gray-600 cursor-not-allowed' : ''}`}
                                readOnly={!!user}
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Teléfono</label>
                            <input
                                type="tel"
                                placeholder="+56 9 1234 5678"
                                value={customerInfo.phone || ''}
                                onChange={(e) => setCustomerInfo({...customerInfo, phone: e.target.value})}
                                className="w-full px-3 py-2 border rounded-lg text-sm"
                            />
                        </div>
                        
                        {customerInfo.deliveryType === 'delivery' && (
                            <>
                                <div>
                                    <label className="flex items-center gap-2 cursor-pointer mb-2">
                                        <input
                                            type="checkbox"
                                            checked={customerInfo.deliveryDiscount}
                                            onChange={(e) => {
                                                const isChecked = e.target.checked;
                                                setCustomerInfo({
                                                    ...customerInfo,
                                                    deliveryDiscount: isChecked,
                                                    address: isChecked ? '' : customerInfo.address
                                                });
                                            }}
                                            className="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500"
                                        />
                                        <span className="text-xs font-medium text-gray-700">Descuento Delivery (40%)</span>
                                    </label>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Dirección de entrega *</label>
                                    {customerInfo.deliveryDiscount ? (
                                        <select
                                            value={customerInfo.address}
                                            onChange={(e) => setCustomerInfo({...customerInfo, address: e.target.value})}
                                            className="w-full px-3 py-2 border rounded-lg text-sm"
                                            required
                                        >
                                            <option value="">Seleccionar dirección con descuento</option>
                                            <option value="Ctel. Oscar Quina 1333">Ctel. Oscar Quina 1333</option>
                                            <option value="Ctel. Domeyco 1540">Ctel. Domeyco 1540</option>
                                            <option value="Ctel. Av. Santa María 3000">Ctel. Av. Santa María 3000</option>
                                        </select>
                                    ) : (
                                        <input
                                            type="text"
                                            value={customerInfo.address}
                                            onChange={(e) => setCustomerInfo({...customerInfo, address: e.target.value})}
                                            className="w-full px-3 py-2 border rounded-lg text-sm"
                                            placeholder="Ingresa tu dirección..."
                                            required
                                        />
                                    )}
                                </div>
                            </>
                        )}
                        
                        {customerInfo.deliveryType === 'pickup' && (
                            <>
                                <div>
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={customerInfo.pickupDiscount}
                                            onChange={(e) => setCustomerInfo({...customerInfo, pickupDiscount: e.target.checked})}
                                            className="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500"
                                        />
                                        <span className="text-xs font-medium text-gray-700">Descuento R11 (10% en total)</span>
                                    </label>
                                    {customerInfo.pickupDiscount && (
                                        <p className="text-xs text-green-600 bg-green-50 p-2 rounded mt-2">
                                            ✓ Descuento del 10% aplicado en el total de tu compra
                                        </p>
                                    )}
                                </div>
                            </>
                        )}
                        
                        <div>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={customerInfo.birthdayDiscount}
                                    onChange={(e) => setCustomerInfo({...customerInfo, birthdayDiscount: e.target.checked})}
                                    className="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500"
                                    disabled={!cart.some(item => item.id === 9)}
                                />
                                <span className="text-xs font-medium text-gray-700">🎂 Descuento Cumpleaños (Hamburguesa gratis)</span>
                            </label>
                            {customerInfo.birthdayDiscount && (
                                <p className="text-xs text-green-600 bg-green-50 p-2 rounded mt-2">
                                    ✓ Hamburguesa Clásica gratis por cumpleaños
                                </p>
                            )}
                            {!cart.some(item => item.id === 9) && (
                                <p className="text-xs text-gray-500 mt-1">
                                    Agrega una Hamburguesa Clásica para aplicar descuento
                                </p>
                            )}
                        </div>
                        
                        {customerInfo.deliveryType === 'pickup' && (
                            <select
                                value={customerInfo.pickupTime || ''}
                                onChange={(e) => setCustomerInfo({...customerInfo, pickupTime: e.target.value})}
                                className="w-full px-3 py-2 border rounded-lg text-sm"
                            >
                                <option value=""></option>
                                <option value="15:00">15:00 - 15:30</option>
                                <option value="15:30">15:30 - 16:00</option>
                                <option value="16:00">16:00 - 16:30</option>
                                <option value="16:30">16:30 - 17:00</option>
                                <option value="17:00">17:00 - 17:30</option>
                                <option value="17:30">17:30 - 18:00</option>
                                <option value="18:00">18:00 - 18:30</option>
                                <option value="18:30">18:30 - 19:00</option>
                                <option value="19:00">19:00 - 19:30</option>
                                <option value="19:30">19:30 - 20:00</option>
                                <option value="20:00">20:00 - 20:30</option>
                                <option value="20:30">20:30 - 21:00</option>
                                <option value="21:00">21:00 - 21:30</option>
                            </select>
                        )}
                        
                        {/* Notas adicionales */}
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Notas adicionales (opcional)</label>
                            <textarea
                                placeholder="Ej: sin cebolla, sin tomate, extra salsa..."
                                value={customerInfo.notes || ''}
                                onChange={(e) => setCustomerInfo({...customerInfo, notes: e.target.value})}
                                className="w-full px-3 py-2 border rounded-lg text-sm resize-none"
                                rows="3"
                                maxLength="400"
                            />
                            <p className="text-xs text-gray-500 mt-1">Máximo 400 caracteres</p>
                        </div>
                        
                        {/* Payment Methods */}
                        <div>
                            <h4 className="font-semibold text-gray-800 mb-2 text-sm">Método de Pago</h4>
                            <div className="space-y-2">
                                <button
                                    onClick={() => onPaymentMethodSelect('cash')}
                                    disabled={!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)}
                                    className="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-2 px-3 rounded-lg text-sm transition-colors"
                                >
                                    💵 Pago en Efectivo
                                </button>
                                <button
                                    onClick={() => onPaymentMethodSelect('card')}
                                    disabled={!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)}
                                    className="w-full bg-purple-500 hover:bg-purple-600 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-2 px-3 rounded-lg text-sm transition-colors flex items-center justify-center gap-2"
                                >
                                    <CreditCard size={18} />
                                    Pago con Tarjeta (POS)
                                </button>
                                <button
                                    onClick={() => onPaymentMethodSelect('transfer')}
                                    disabled={!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)}
                                    className="w-full bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-2 px-3 rounded-lg text-sm transition-colors"
                                >
                                    🏦 Pago con Transferencia
                                </button>
                                <button
                                    onClick={() => onPaymentMethodSelect('pedidosya')}
                                    disabled={!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)}
                                    className="w-full bg-orange-500 hover:bg-orange-600 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-2 px-3 rounded-lg text-sm transition-colors"
                                >
                                    🛵 Pago en PedidosYA
                                </button>
                            </div>
                        </div>
                        
                        <div className="p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <p className="text-xs text-gray-600 text-center font-medium">
                                ⚠️ App Caja - Todos estos pagos se registran.
                            </p>
                        </div>
                    </div>
                )}
                
                <div className="bg-white border-t sticky bottom-0" style={{padding: 'clamp(12px, 3vw, 16px)'}}>
                    {customerInfo.deliveryType === 'delivery' && currentDeliveryFee > 0 && (
                        <div className="mb-2 space-y-1">
                            <div className="flex justify-between items-center text-sm">
                                <span className="text-gray-600">Subtotal</span>
                                <span className="font-medium">${cartSubtotal.toLocaleString('es-CL')}</span>
                            </div>
                            <div className="flex justify-between items-center text-sm">
                                <span className="text-gray-600 flex items-center gap-1">
                                    <Truck size={14} />
                                    Delivery
                                </span>
                                <span className="font-medium text-blue-600">${currentDeliveryFee.toLocaleString('es-CL')}</span>
                            </div>
                        </div>
                    )}
                    <div className="flex justify-between items-center mb-3 pt-2 border-t">
                        <span className="font-medium text-gray-600" style={{fontSize: 'clamp(14px, 3.5vw, 18px)'}}>Total</span>
                        <span className="font-bold text-gray-800" style={{fontSize: 'clamp(18px, 5vw, 24px)'}}>${finalTotal.toLocaleString('es-CL')}</span>
                    </div>
                    
                    {!showCheckoutSection ? (
                        <button 
                            disabled={cart.length === 0} 
                            onClick={() => setShowCheckoutSection(true)}
                            className="w-full bg-orange-500 text-white rounded-full font-bold flex items-center justify-center gap-2 hover:bg-orange-600 transition-transform active:scale-95 shadow-lg disabled:bg-gray-300 disabled:cursor-not-allowed" 
                            style={{padding: 'clamp(12px, 3vw, 16px)', fontSize: 'clamp(14px, 3.5vw, 18px)'}}
                        >
                            <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                                <path fillRule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clipRule="evenodd"/>
                            </svg>
                            Finalizar Pedido
                        </button>
                    ) : (
                        <button 
                            onClick={() => setShowCheckoutSection(false)}
                            className="w-full bg-gray-500 text-white rounded-full font-bold flex items-center justify-center gap-2 hover:bg-gray-600 transition-colors" 
                            style={{padding: 'clamp(10px, 2.5vw, 12px)', fontSize: 'clamp(13px, 3.2vw, 16px)'}}
                        >
                            Ocultar Checkout
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
};



const LoginModal = ({ isOpen, onClose }) => {
    if (!isOpen) return null;
    const handleGoogleLogin = () => {
        const authUrl = 'https://accounts.google.com/o/oauth2/auth?' + new URLSearchParams({
            client_id: '531902921465-1l4fa0esvcbhdlq4btejp7d1thdtj4a7.apps.googleusercontent.com',
            redirect_uri: 'https://app.laruta11.cl/api/auth/google/app_callback.php',
            scope: 'email profile',
            response_type: 'code'
        });
        window.location.href = authUrl;
    };
    return (
        <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={onClose}>
            <div className="bg-white w-full max-w-sm mx-2 sm:m-4 rounded-2xl flex flex-col animate-slide-up text-center" style={{padding: 'clamp(20px, 5vw, 32px)'}} onClick={(e) => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-3 right-3 p-1 text-gray-400 hover:text-gray-600"><X size={24} /></button>
                <h2 className="font-bold text-gray-800 mb-2" style={{fontSize: 'clamp(18px, 5vw, 24px)'}}>Acceso / Registro</h2>
                <p className="text-gray-500 mb-6" style={{fontSize: 'clamp(13px, 3.5vw, 16px)'}}>Ingresa a tu cuenta para guardar tus pedidos y reseñas.</p>
                <button 
                    onClick={handleGoogleLogin}
                    className="w-full bg-white border border-gray-300 text-gray-700 font-semibold rounded-full flex items-center justify-center gap-2 sm:gap-3 hover:bg-gray-50 transition-colors shadow-sm"
                    style={{padding: 'clamp(10px, 2.5vw, 12px)', fontSize: 'clamp(13px, 3.5vw, 16px)'}}
                >
                    <GoogleLogo />
                    Continuar con Google
                </button>
            </div>
        </div>
    );
};

const FoodTrucksModal = ({ isOpen, onClose, trucks, userLocation, deliveryZone }) => {
    if (!isOpen) return null;
    
    const openDirections = (truck) => {
        const url = `https://www.google.com/maps/dir/${userLocation?.latitude},${userLocation?.longitude}/${truck.latitud},${truck.longitud}`;
        window.open(url, '_blank');
    };
    
    return (
        <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={onClose}>
            <div className="bg-white w-full h-full flex flex-col animate-slide-up" onClick={(e) => e.stopPropagation()}>
                <div className="bg-gradient-to-r from-orange-500 to-orange-600 text-white flex justify-between items-center" style={{padding: 'clamp(12px, 3vw, 16px)'}}>
                    <h2 className="font-bold flex items-center gap-2" style={{fontSize: 'clamp(16px, 4vw, 20px)'}}>
                        <Truck size={22} />
                        Food Trucks Cercanos
                        {deliveryZone && (
                            <span className={`ml-2 text-xs px-2 py-1 rounded-full ${
                                deliveryZone.in_delivery_zone 
                                    ? 'bg-white/20 text-white' 
                                    : 'bg-red-500 text-white'
                            }`}>
                                {deliveryZone.in_delivery_zone 
                                    ? (
                                        <span className="flex items-center gap-1">
                                            <TruckIcon size={12} />
                                            {deliveryZone.zones[0]?.tiempo_estimado}min
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-1">
                                            <XCircle size={12} />
                                            Sin delivery
                                        </span>
                                    )
                                }
                            </span>
                        )}
                    </h2>
                    <button onClick={onClose} className="p-1 hover:bg-white/20 rounded-full transition-colors"><X size={20} /></button>
                </div>
                
                <div className="flex-grow overflow-y-auto">
                    {trucks.length > 0 && userLocation ? (
                        <div className="flex flex-col h-full">
                            {/* Mapa */}
                            <div className="h-64 bg-gray-200 relative rounded-t-lg overflow-hidden">
                                <iframe
                                    width="100%"
                                    height="100%"
                                    frameBorder="0"
                                    src={`https://www.google.com/maps/embed/v1/directions?key=AIzaSyAcK15oZ84Puu5Nc4wDQT_Wyht0xqkbO-A&origin=${userLocation.latitude},${userLocation.longitude}&destination=${trucks[0]?.latitud},${trucks[0]?.longitud}&mode=driving&zoom=14`}
                                    allowFullScreen
                                    loading="lazy"
                                    referrerPolicy="no-referrer-when-downgrade"
                                    title="Mapa de Food Trucks"
                                ></iframe>
                            </div>
                            
                            {/* Lista de trucks */}
                            <div className="p-4 space-y-3">
                                {trucks.map(truck => {
                                    // Verificar si está abierto según horario actual (Chile)
                                    const now = new Date();
                                    const chileTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/Santiago' }));
                                    const hours = chileTime.getHours().toString().padStart(2, '0');
                                    const minutes = chileTime.getMinutes().toString().padStart(2, '0');
                                    const seconds = chileTime.getSeconds().toString().padStart(2, '0');
                                    const currentTime = `${hours}:${minutes}:${seconds}`;
                                    
                                    // Manejar horarios que cruzan medianoche (ej: 18:00 - 00:30)
                                    let isOpen;
                                    if (truck.horario_inicio > truck.horario_fin) {
                                        // Cruza medianoche: abierto si hora >= inicio O hora <= fin
                                        isOpen = truck.activo && (currentTime >= truck.horario_inicio || currentTime <= truck.horario_fin);
                                    } else {
                                        // Normal: abierto si hora >= inicio Y hora <= fin
                                        isOpen = truck.activo && currentTime >= truck.horario_inicio && currentTime <= truck.horario_fin;
                                    }
                                    
                                    return (
                                        <div key={truck.id} className="border border-gray-200 rounded-xl p-4 hover:shadow-md transition-shadow bg-white">
                                            <div className="flex justify-between items-start mb-3">
                                                <div className="flex items-start gap-2">
                                                    <div className="bg-orange-100 p-2 rounded-lg">
                                                        <Truck size={18} className="text-orange-600" />
                                                    </div>
                                                    <div>
                                                        <h3 className="font-bold text-gray-800 text-sm">{truck.nombre}</h3>
                                                        <p className="text-xs text-gray-500 mt-0.5">{truck.descripcion}</p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-1 text-orange-600 font-semibold text-sm bg-orange-50 px-2 py-1 rounded-lg">
                                                    <Navigation size={12} />
                                                    {truck.distance ? `${truck.distance.toFixed(1)} km` : '...'}
                                                </div>
                                            </div>
                                            
                                            <div className="flex items-center gap-1.5 text-xs text-gray-600 mb-3">
                                                <MapPin size={12} className="text-gray-400" />
                                                <p className="line-clamp-1">{truck.direccion}</p>
                                            </div>
                                            
                                            <div className="flex flex-wrap items-center gap-2 mb-3">
                                                <div className="flex items-center gap-1 text-xs bg-gray-50 px-2 py-1.5 rounded-lg">
                                                    <Clock size={12} className="text-gray-500" />
                                                    <span className="text-gray-700 font-medium">
                                                        {truck.horario_inicio.slice(0,5)} - {truck.horario_fin.slice(0,5)}
                                                    </span>
                                                </div>
                                                <span className={`px-2.5 py-1.5 rounded-lg text-xs font-medium flex items-center gap-1 ${
                                                    isOpen ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                                }`}>
                                                    {isOpen ? (
                                                        <><CheckCircle2 size={12} /> Abierto</>
                                                    ) : (
                                                        <><XCircle size={12} /> Cerrado</>
                                                    )}
                                                </span>
                                                {truck.tarifa_delivery && (
                                                    <div className="flex items-center gap-1 text-xs bg-blue-50 px-2 py-1.5 rounded-lg">
                                                        <TruckIcon size={12} className="text-blue-600" />
                                                        <span className="text-blue-700 font-medium">
                                                            ${parseInt(truck.tarifa_delivery).toLocaleString('es-CL')}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                            
                                            <button 
                                                onClick={() => openDirections(truck)}
                                                className="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:from-blue-600 hover:to-blue-700 transition-all flex items-center justify-center gap-2 shadow-sm"
                                            >
                                                <Navigation size={16} />
                                                Cómo llegar
                                            </button>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    ) : (
                        <div className="text-center py-12 p-4">
                            <div className="bg-gray-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                                <Truck size={40} className="text-gray-400" />
                            </div>
                            <p className="text-gray-700 font-medium text-lg">No hay food trucks cerca</p>
                            <p className="text-sm text-gray-500 mt-2 flex items-center justify-center gap-1">
                                {!userLocation ? (
                                    <><MapPin size={14} /> Activa tu ubicación para encontrar trucks cercanos</>
                                ) : (
                                    <><Navigation size={14} /> No hay trucks en un radio de 10km</>
                                )}
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

const NotificationsModal = ({ isOpen, onClose, onOrdersUpdate, activeOrdersCount }) => {
    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
        return () => {
            document.body.style.overflow = '';
        };
    }, [isOpen]);

    if (!isOpen) return null;
    
    return (
        <MiniComandas onOrdersUpdate={onOrdersUpdate} onClose={onClose} activeOrdersCount={activeOrdersCount} />
    );
};




const MenuItem = ({ product, onSelect, onAddToCart, onRemoveFromCart, quantity, type, isLiked, handleLike, setReviewsModalProduct, onShare, isCashier }) => {
  const [showFloatingHeart, setShowFloatingHeart] = useState(false);
  const [heartPosition, setHeartPosition] = useState({ x: 0, y: 0 });
  const [showImageModal, setShowImageModal] = useState(false);
  const [isActive, setIsActive] = useState(product.active !== 0);
  const [isTogglingStatus, setIsTogglingStatus] = useState(false);
  const heartButtonRef = useRef(null);
  
  // Track product view when component mounts
  useEffect(() => {
    if (window.Analytics) {
      window.Analytics.trackProductView(product.id, product.name);
    }
  }, [product.id, product.name]);
  
  // Doble tap para me gusta
  const handleDoubleTap = useDoubleTap((e) => {
    e.stopPropagation();
    if (!isLiked) {
      // Obtener posición del corazón en la tarjeta
      const rect = e.currentTarget.getBoundingClientRect();
      setHeartPosition({
        x: rect.left + rect.width * 0.2, // Posición del corazón en la tarjeta
        y: rect.bottom - 60 // Cerca del corazón de me gusta
      });
      
      handleLike(product.id);
      setShowFloatingHeart(true);
      vibrate([50, 50, 50]); // Vibración triple
      

    }
  });
    const typeColors = {
        // Churrascos/Sandwiches
        'Carne': {
            bg: 'bg-red-500',
            text: 'text-white',
            border: 'border-red-500'
        },
        'Pollo': {
            bg: 'bg-yellow-500',
            text: 'text-white',
            border: 'border-yellow-500'
        },
        'Vegetariano': {
            bg: 'bg-green-500',
            text: 'text-white',
            border: 'border-green-500'
        },
        // Hamburguesas
        'Clásicas': {
            bg: 'bg-orange-500',
            text: 'text-white',
            border: 'border-orange-500'
        },
        'Especiales': {
            bg: 'bg-purple-500',
            text: 'text-white',
            border: 'border-purple-500'
        },
        // Completos
        'Tradicionales': {
            bg: 'bg-blue-500',
            text: 'text-white',
            border: 'border-blue-500'
        },
        'Al Vapor': {
            bg: 'bg-cyan-500',
            text: 'text-white',
            border: 'border-cyan-500'
        },
        // Tomahawks
        'Tomahawks': {
            bg: 'bg-red-700',
            text: 'text-white',
            border: 'border-red-700'
        },
        // Snacks
        'Papas': {
            bg: 'bg-yellow-600',
            text: 'text-white',
            border: 'border-yellow-600'
        },
        'Jugos': {
            bg: 'bg-green-400',
            text: 'text-white',
            border: 'border-green-400'
        },
        'Bebidas': {
            bg: 'bg-blue-400',
            text: 'text-white',
            border: 'border-blue-400'
        },
        'Salsas': {
            bg: 'bg-red-400',
            text: 'text-white',
            border: 'border-red-400'
        },
        'Empanadas': {
            bg: 'bg-amber-500',
            text: 'text-white',
            border: 'border-amber-500'
        }
    };
    const colorClasses = type ? typeColors[type] : null;
  const toggleProductStatus = async () => {
    setIsTogglingStatus(true);
    try {
      const newStatus = isActive ? 0 : 1;
      const res = await fetch('/api/toggle_product_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ productId: product.id, active: newStatus })
      });
      const data = await res.json();
      if (data.success) {
        setIsActive(newStatus === 1);
      }
    } catch (error) {
      console.error('Error:', error);
    }
    setIsTogglingStatus(false);
  };

  return (
    <>
    <div className={`bg-white rounded-xl overflow-hidden animate-fade-in transition-all duration-300 flex min-h-[160px] relative ${!isActive && isCashier ? 'border-2 border-red-500' : ''}`} style={{boxShadow: '4px 4px 8px rgba(0, 0, 0, 0.1), -2px -2px 6px rgba(255, 255, 255, 0.7)'}}>
        {/* Capa gris para productos inactivos */}
        {!isActive && isCashier && (
            <div className="absolute inset-0 bg-gray-500 bg-opacity-60 z-10 rounded-xl"></div>
        )}
        
        <div 
            className="w-32 flex-shrink-0 relative aspect-[4/5] cursor-pointer" 
            onTouchStart={handleDoubleTap}
            onClick={() => product.image && setShowImageModal(true)}
        >
            {product.image ? (
                <img 
                    src={product.image} 
                    alt={product.name} 
                    className="w-full h-full object-cover"
                />
            ) : (
                <div className="w-full h-full bg-gray-200 animate-pulse"></div>
            )}
            <FloatingHeart 
                show={showFloatingHeart} 
                startPosition={heartPosition}
                onAnimationEnd={() => setShowFloatingHeart(false)}
            />
        </div>
        
        <div className="flex-1 p-2 flex flex-col justify-between min-h-[160px]">
            {isCashier && (
                <button
                    onClick={(e) => {
                        e.stopPropagation();
                        toggleProductStatus();
                    }}
                    disabled={isTogglingStatus}
                    className={`absolute top-1 left-1 z-20 px-2 py-1 rounded-md text-[10px] font-bold transition-all shadow-lg ${isActive ? 'bg-green-500 text-white' : 'bg-red-500 text-white'} ${isTogglingStatus ? 'opacity-50' : 'hover:scale-105'}`}
                >
                    {isActive ? 'ACTIVO' : 'INACTIVO'}
                </button>
            )}
            <div className="flex-1">
                <div className="flex items-start justify-between gap-2 mb-1">
                    <h3 
                        className="font-bold text-sm text-gray-800 cursor-pointer line-clamp-2 flex-1"
                        onClick={() => onSelect(product)}
                        title={product.name}
                    >
                        {product.name}
                    </h3>
                    {product.category_name === 'Combos' && (
                        <div className="bg-orange-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full flex items-center gap-0.5 flex-shrink-0">
                            <GiHamburger size={10} />
                            <CupSoda size={10} />
                        </div>
                    )}
                </div>
                <p className="text-xs text-gray-600 mb-2 line-clamp-3">
                    {product.description}
                </p>
            </div>
            
            <div className="space-y-2 flex-shrink-0">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2 text-xs text-gray-500">
                        <div className="flex items-center gap-0.5"><Eye size={12} /><span>{product.views}</span></div>
                        <button 
                            ref={heartButtonRef}
                            onClick={(e) => {
                                if (!isLiked) {
                                    const rect = e.currentTarget.getBoundingClientRect();
                                    setHeartPosition({
                                        x: rect.left + rect.width / 2,
                                        y: rect.top + rect.height / 2
                                    });
                                    setShowFloatingHeart(true);
                                    vibrate(50);
                                }
                                handleLike(product.id);
                            }} 
                            className={`flex items-center gap-0.5 ${isLiked ? 'text-red-500' : ''}`}
                        >
                            <Heart size={12} className={isLiked ? 'fill-current' : ''} /><span>{product.likes}</span>
                        </button>
                        <button 
                            onClick={(e) => {
                                e.stopPropagation();
                                setReviewsModalProduct(product);
                            }}
                            className="flex items-center gap-0.5"
                        >
                            <MessageSquare size={12} /><span>{product.reviews.count || 0}</span>
                        </button>
                        <button 
                            onClick={(e) => {
                                e.stopPropagation();
                                onShare(product);
                            }}
                            className="flex items-center"
                        >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>
                            </svg>
                        </button>
                    </div>
                    <p className="text-sm font-bold bg-yellow-400 text-black px-2 py-1 rounded-md flex-shrink-0">${product.price.toLocaleString('es-CL')}</p>
                </div>
                
                <div className="flex items-center gap-2">
                    {quantity > 0 && (
                        <button
                            onClick={() => onRemoveFromCart(product.id)}
                            className="text-red-600 rounded-full hover:bg-red-100 transition-colors p-1 flex-shrink-0"
                        >
                            <MinusCircle size={20} />
                        </button>
                    )}
                    {quantity > 0 && <span className="font-bold text-sm text-gray-800 w-6 text-center flex-shrink-0">{quantity}</span>}
                    <button 
                        onClick={() => onAddToCart(product)} 
                        className={`flex-1 text-white rounded-lg py-2 font-bold transition-all duration-300 flex items-center justify-center gap-1 min-w-0 ${
                            quantity > 0 ? 'bg-blue-500 hover:bg-blue-600' : 'bg-green-500 hover:bg-green-600'
                        }`}
                    >
                        <PlusCircle size={18} className="flex-shrink-0" />
                        <span className="truncate">Agregar</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    {/* Modal de imagen completa */}
    {showImageModal && (
      <div 
        className="fixed inset-0 bg-black/90 z-[70] flex items-center justify-center animate-fade-in" 
        onClick={() => setShowImageModal(false)}
      >
        <button 
          onClick={() => setShowImageModal(false)} 
          className="absolute top-4 right-4 bg-black/50 rounded-full p-2 text-white hover:bg-black/70 transition-all z-20"
        >
          <X size={24} />
        </button>
        <img 
          src={product.image} 
          alt={product.name} 
          className="max-w-full max-h-full object-contain" 
        />
        <div className="absolute bottom-0 left-0 right-0 p-6 bg-black/60 backdrop-blur-md text-white">
          <h3 className="text-2xl font-bold mb-2">{product.name}</h3>
          <p className="text-sm text-gray-200 mb-3 line-clamp-2">{product.description}</p>
          <p className="text-xl font-bold text-yellow-400">${product.price.toLocaleString('es-CL')}</p>
        </div>
      </div>
    )}
    </>
  );
};

export default function App() {
  const [activeCategory, setActiveCategory] = useState('hamburguesas');
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [zoomedProduct, setZoomedProduct] = useState(null);
  const [cart, setCart] = useState([]);
  const [isCartOpen, setIsCartOpen] = useState(false);
  const [isLoginOpen, setIsLoginOpen] = useState(false);
  const [showCheckout, setShowCheckout] = useState(false);
  const [showPayment, setShowPayment] = useState(false);
  const [currentOrder, setCurrentOrder] = useState(null);
  const [customerInfo, setCustomerInfo] = useState({ name: '', phone: '', email: '', address: '', deliveryType: 'pickup', pickupTime: '', customerNotes: '', deliveryDiscount: false, pickupDiscount: false, birthdayDiscount: false });
  const [menuWithImages, setMenuWithImages] = useState(menuData);
  const [likedProducts, setLikedProducts] = useState(new Set());
  const [isLoading, setIsLoading] = useState(false);
  const [user, setUser] = useState(null);
  const [cajaUser, setCajaUser] = useState(null);
  const [userOrders, setUserOrders] = useState([]);
  const [userStats, setUserStats] = useState(null);
  const [showAllOrders, setShowAllOrders] = useState(false);
  const [isProfileOpen, setIsProfileOpen] = useState(false);
  const [isFoodTrucksOpen, setIsFoodTrucksOpen] = useState(false);
  const [isNotificationsOpen, setIsNotificationsOpen] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [activeOrdersCount, setActiveOrdersCount] = useState(0);
  const [activeChecklistsCount, setActiveChecklistsCount] = useState(0);
  const [userLocation, setUserLocation] = useState(null);
  const [locationPermission, setLocationPermission] = useState('prompt');
  const [deliveryZone, setDeliveryZone] = useState(null);
  const [nearbyProducts, setNearbyProducts] = useState(null);
  const [nearbyTrucks, setNearbyTrucks] = useState([]);
  const [isLogoutModalOpen, setIsLogoutModalOpen] = useState(false);
  const [isDeleteAccountModalOpen, setIsDeleteAccountModalOpen] = useState(false);
  const [hasProfileChanges, setHasProfileChanges] = useState(false);
  const [isSaveChangesModalOpen, setIsSaveChangesModalOpen] = useState(false);
  const [sessionId] = useState(() => Date.now().toString());
  const [sessionStartTime] = useState(Date.now());
  const [currentSessionTime, setCurrentSessionTime] = useState(0);
  const [isNavVisible, setIsNavVisible] = useState(true);
  const [isHeaderVisible, setIsHeaderVisible] = useState(true);
  const [lastScrollY, setLastScrollY] = useState(0);
  const [showOnboarding, setShowOnboarding] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [filteredProducts, setFilteredProducts] = useState([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [suggestions, setSuggestions] = useState([]);
  const [reviewsModalProduct, setReviewsModalProduct] = useState(null);
  const [shareModalProduct, setShareModalProduct] = useState(null);
  const [comboModalProduct, setComboModalProduct] = useState(null);
  const [showCheckoutSection, setShowCheckoutSection] = useState(false);
  const [pendingPaymentModal, setPendingPaymentModal] = useState(null);
  const [showCashModal, setShowCashModal] = useState(false);
  const [cashAmount, setCashAmount] = useState('');
  const [cashStep, setCashStep] = useState('input');
  const [isProcessing, setIsProcessing] = useState(false);
  const [highlightedProductId, setHighlightedProductId] = useState(null);
  const [discountCode, setDiscountCode] = useState('');
  const [audioEnabled, setAudioEnabled] = useState(true);
  const [showQRModal, setShowQRModal] = useState(false);
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [truckStatus, setTruckStatus] = useState(null);
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);
  const [editMode, setEditMode] = useState(false);
  const [tempTruckData, setTempTruckData] = useState(null);
  const [schedules, setSchedules] = useState([]);
  const [categories, setCategories] = useState([]);
  const [categoriesExpanded, setCategoriesExpanded] = useState(false);
  const [currentDayOfWeek, setCurrentDayOfWeek] = useState(null);
  const [editingSchedules, setEditingSchedules] = useState(false);
  const [showInactiveProducts, setShowInactiveProducts] = useState(false);



  const handleCheckout = () => {
    if (!user) {
      setIsLoginOpen(true);
      return;
    }
    setShowCheckout(true);
  };

  const formatCurrency = (value) => {
    const numbers = value.replace(/\D/g, '');
    return numbers.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  };

  const handleCashInput = (e) => {
    const formatted = formatCurrency(e.target.value);
    setCashAmount(formatted);
  };

  const setExactAmount = () => {
    const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
    const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.6) : baseDeliveryFee;
    const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
    const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
    const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
    const finalTotal = cartSubtotal + deliveryFee - pickupDiscountAmount - birthdayDiscountAmount - pizzaDiscountAmount;
    setCashAmount(finalTotal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
  };

  const setQuickAmount = (amount) => {
    setCashAmount(amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
  };

  const handleContinueCash = () => {
    const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
    const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.6) : baseDeliveryFee;
    const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
    const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
    const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
    const finalTotal = cartSubtotal + deliveryFee - pickupDiscountAmount - birthdayDiscountAmount - pizzaDiscountAmount;
    
    const numericAmount = parseInt(cashAmount.replace(/\./g, ''));
    
    if (!numericAmount || numericAmount === 0) {
      alert('⚠️ Debe ingresar un monto o seleccionar "Monto Exacto"');
      return;
    }
    
    if (numericAmount < finalTotal) {
      const faltante = finalTotal - numericAmount;
      alert(`⚠️ Monto insuficiente. Faltan $${faltante.toLocaleString('es-CL')}`);
      return;
    }
    
    if (numericAmount === finalTotal) {
      processCashOrder();
    } else {
      setCashStep('confirm');
    }
  };

  const processCashOrder = async () => {
    setIsProcessing(true);
    try {
      console.log('💵 Procesando pago en EFECTIVO...');
      const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
      const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.6) : baseDeliveryFee;
      const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
      const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
      const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
      const finalTotal = cartSubtotal + deliveryFee - pickupDiscountAmount - birthdayDiscountAmount - pizzaDiscountAmount;
      const numericAmount = parseInt(cashAmount.replace(/\./g, ''));
      const vuelto = numericAmount - finalTotal;
      
      // Agregar mensaje estructurado a las notas
      const paymentNote = `💵 EFECTIVO | Paga con: $${numericAmount.toLocaleString('es-CL')} | Vuelto: $${vuelto.toLocaleString('es-CL')}`;
      const finalNotes = customerInfo.customerNotes 
        ? `${customerInfo.customerNotes}\n\n${paymentNote}` 
        : paymentNote;
      
      const orderData = {
        amount: finalTotal,
        customer_name: customerInfo.name,
        customer_phone: customerInfo.phone,
        customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
        user_id: user?.id || null,
        cart_items: cart,
        delivery_fee: deliveryFee,
        customer_notes: finalNotes,
        delivery_type: customerInfo.deliveryType,
        delivery_address: customerInfo.address || null,
        payment_method: 'cash'
      };
      
      console.log('📤 Enviando orden:', orderData);
      const response = await fetch('/api/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
      });
      
      const result = await response.json();
      console.log('📥 Respuesta:', result);
      if (result.success) {
        localStorage.removeItem('ruta11_cart');
        localStorage.removeItem('ruta11_cart_total');
        console.log('✅ Redirigiendo a /cash-pending?order=' + result.order_id);
        window.location.href = '/cash-pending?order=' + result.order_id;
      } else {
        setIsProcessing(false);
        alert('❌ Error al crear el pedido: ' + (result.error || 'Error desconocido'));
      }
    } catch (error) {
      setIsProcessing(false);
      console.error('❌ Error procesando pago en efectivo:', error);
      alert('❌ Error al procesar el pedido: ' + error.message);
    }
  };

  const closeCashModal = () => {
    setShowCashModal(false);
    setCashAmount('');
    setCashStep('input');
  };

  const handleCreateOrder = async () => {
    const orderNumber = 'R11-' + Date.now();
    const orderData = {
      order_number: orderNumber,
      customer: customerInfo.name ? customerInfo : { name: user?.nombre || '', phone: '', email: user?.email || '' },
      items: cart,
      total: cartTotal,
      created_at: new Date().toISOString()
    };

    setCurrentOrder(orderData);
    setShowCheckout(false);
    setShowPayment(true);
  };

  const handlePaymentSuccess = (paymentData) => {
    createConfetti();
    playNotificationSound();
    vibrate([200, 100, 200]);
    
    // Agregar notificación de pago exitoso
    const newNotification = {
      titulo: '✅ Pago Exitoso',
      mensaje: `Tu pedido #${currentOrder?.order_number} fue pagado correctamente. ¡Gracias por tu compra!`,
      leida: false
    };
    setNotifications(prev => [newNotification, ...prev]);
    
    // Notificar al admin
    fetch('/api/notify_admin_payment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_number: currentOrder?.order_number,
        amount: cartTotal,
        customer_name: customerInfo.name || user?.nombre || 'Cliente'
      })
    }).catch(() => {});
    
    // Limpiar carrito
    setCart([]);
    setShowPayment(false);
    setCurrentOrder(null);
    setCustomerInfo({ name: '', phone: '', email: '', address: '' });
    
    if (paymentData && paymentData.payment_url) {
      alert(`¡Pedido #${currentOrder?.order_number} enviado a TUU para pago online!`);
    } else {
      alert(`¡Pedido #${currentOrder?.order_number} creado exitosamente! Puedes pagar en el local.`);
    }
  };

  const handlePaymentError = () => {
    setShowPayment(false);
  };


  const handleLike = async (productId) => {
    if (likedProducts.has(productId)) return;
    
    try {
      const response = await fetch('/api/toggle_like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId })
      });
      
      if (response.ok) {
        const data = await response.json();
        setMenuWithImages(prevMenu => {
          const updatedMenu = { ...prevMenu };
          Object.keys(updatedMenu).forEach(category => {
            if (Array.isArray(updatedMenu[category])) {
              updatedMenu[category] = updatedMenu[category].map(product => 
                product.id === productId ? { ...product, likes: data.likes } : product
              );
            } else {
              Object.keys(updatedMenu[category]).forEach(subcat => {
                updatedMenu[category][subcat] = updatedMenu[category][subcat].map(product => 
                  product.id === productId ? { ...product, likes: data.likes } : product
                );
              });
            }
          });
          return updatedMenu;
        });
        setLikedProducts(prev => new Set([...prev, productId]));
      }
    } catch (error) {
      console.error('Error al dar like:', error);
    }
  };

  const handleSaveChanges = () => {
    console.log('Guardando cambios del perfil');
    setHasProfileChanges(false);
    setIsSaveChangesModalOpen(false);
    setIsProfileOpen(false);
  };

  const handleDiscardChanges = () => {
    setHasProfileChanges(false);
    setIsSaveChangesModalOpen(false);
    setIsProfileOpen(false);
  };

  const handleLogout = () => {
    window.location.href = '/api/auth/logout.php';
  };

  const handleDeleteAccount = () => {
    fetch('/api/auth/delete_account.php', { method: 'POST' })
      .then(() => {
        window.location.href = '/api/auth/logout.php';
      })
      .catch(error => console.error('Error eliminando cuenta:', error));
  };

  const handleSearch = (query) => {
    setSearchQuery(query);
    if (!query.trim() || query.length < 2) {
      setFilteredProducts([]);
      setSuggestions([]);
      setShowSuggestions(false);
      return;
    }
    
    const allProducts = [];
    const seenIds = new Set();
    
    Object.entries(menuWithImages).forEach(([categoryKey, category]) => {
      // Excluir categorías personalizar y extras de la vista principal
      if (categoryKey === 'personalizar' || categoryKey === 'extras') return;
      
      if (Array.isArray(category)) {
        category.forEach(p => {
          if (!seenIds.has(p.id)) {
            allProducts.push({...p, category: categoryKey});
            seenIds.add(p.id);
          }
        });
      } else {
        Object.entries(category).forEach(([subKey, products]) => {
          products.forEach(p => {
            if (!seenIds.has(p.id)) {
              allProducts.push({...p, category: categoryKey, subcategory: subKey});
              seenIds.add(p.id);
            }
          });
        });
      }
    });
    
    const filtered = allProducts.filter(product => {
      // Excluir productos de categorías personalizar y extras de búsquedas
      if (product.category === 'personalizar' || product.category === 'extras') return false;
      
      // Solo mostrar productos activos (active !== 0)
      if (product.active === 0) return false;
      
      // Buscar solo por nombre y descripción del producto
      return product.name.toLowerCase().includes(query.toLowerCase()) ||
        product.description.toLowerCase().includes(query.toLowerCase());
    });
    
    setFilteredProducts(filtered);
    setSuggestions(filtered.slice(0, 8));
    setShowSuggestions(query.length >= 2 && filtered.length > 0);
  };
  
  const selectSuggestion = (product) => {
    setShowSuggestions(false);
    setSearchQuery('');
    setFilteredProducts([]);
    
    // Change to product's category
    if (product.category) {
      setActiveCategory(product.category);
    }
    
    // Wait for category change and then scroll to product
    setTimeout(() => {
      const productElement = document.getElementById(`product-${product.id}`);
      if (productElement) {
        productElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setHighlightedProductId(product.id);
        setTimeout(() => setHighlightedProductId(null), 2000);
      }
    }, 100);
  };

  // DISABLED FOR CAJA: No se solicita ubicación en caja3
  const requestLocation = () => {
    console.log('⚠️ Ubicación desactivada en caja3');
    return;
    /* CÓDIGO DESACTIVADO
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      alert('Tu navegador no soporta geolocalización');
      return;
    }

    setLocationPermission('requesting');
    
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        const { latitude, longitude } = position.coords;
        
        // Obtener dirección usando Google Geocoding API
        let addressInfo;
        try {
          const formData = new FormData();
          formData.append('lat', latitude);
          formData.append('lng', longitude);
          
          const response = await fetch('/api/location/geocode.php', {
            method: 'POST',
            body: formData
          });
          
          const text = await response.text();
          
          // Verificar si la respuesta es JSON válido
          if (!text || text.trim().startsWith('<')) {
            throw new Error('API returned HTML instead of JSON');
          }
          
          const data = JSON.parse(text);
          
          if (data.success) {
            addressInfo = {
              formatted_address: data.formatted_address,
              street: data.readable.street,
              city: data.readable.city,
              region: data.readable.region,
              country: data.readable.country,
              components: data.components
            };
          } else {
            throw new Error(data.error || 'Geocoding failed');
          }
        } catch (error) {
          // Silenciar error de geocoding
          addressInfo = {
            formatted_address: `${latitude}, ${longitude}`,
            street: 'Calle no disponible',
            city: 'Ciudad no disponible',
            region: 'Región no disponible',
            country: 'País no disponible'
          };
        }
        
        try {
          
          const locationData = {
            latitude,
            longitude,
            address: addressInfo.formatted_address,
            addressInfo,
            accuracy: position.coords.accuracy
          };
          
          setUserLocation(locationData);
          setLocationPermission('granted');
          
          // DISABLED FOR CAJA: Verificar zona de delivery
          // checkDeliveryZone(latitude, longitude);
          
          // DISABLED FOR CAJA: Obtener productos cercanos
          // getNearbyProducts(latitude, longitude);
          
          // Obtener food trucks cercanos
          getNearbyTrucks(latitude, longitude);
          
          // Guardar en servidor si usuario está logueado
          if (user) {
            const saveFormData = new FormData();
            saveFormData.append('latitud', latitude);
            saveFormData.append('longitud', longitude);
            saveFormData.append('direccion', addressInfo.formatted_address);
            saveFormData.append('precision', position.coords.accuracy);
            
            fetch('/api/location/save_location.php', {
              method: 'POST',
              body: saveFormData
            }).catch(() => {});
          }
        } catch (error) {
          // Silenciar error al guardar ubicación
        }
      },
      (error) => {
        setLocationPermission('denied');
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 300000 // 5 minutos
      }
    );
    */
  };

  const checkDeliveryZone = async (lat, lng) => {
    try {
      const formData = new FormData();
      formData.append('lat', lat);
      formData.append('lng', lng);
      
      const response = await fetch('/api/location/check_delivery_zone.php', {
        method: 'POST',
        body: formData
      });
      
      if (!response.ok) return;
      
      const data = await response.json();
      if (data.error) {
        setDeliveryZone({ in_delivery_zone: false, zones: [] });
      } else {
        setDeliveryZone(data);
      }
      
      if (data.in_delivery_zone && data.zones.length > 0 && nearbyTrucks.length > 0) {
        const closestTruck = nearbyTrucks[0];
        calculateRealDeliveryTime(lat, lng, closestTruck.latitud, closestTruck.longitud).catch(() => {});
      }
    } catch (error) {
      // Silenciar errores de API no disponible
    }
  };

  const getNearbyProducts = async (lat, lng) => {
    try {
      const formData = new FormData();
      formData.append('lat', lat);
      formData.append('lng', lng);
      
      const response = await fetch('/api/location/get_nearby_products.php', {
        method: 'POST',
        body: formData
      });
      
      if (!response.ok) return;
      const data = await response.json();
      setNearbyProducts(data);
    } catch (error) {
      // Silenciar errores de API no disponible
    }
  };

  const loadDeliveryFee = async () => {
    try {
      const response = await fetch('/api/get_truck_status.php?truckId=4');
      const data = await response.json();
      
      if (data.success && data.truck) {
        setNearbyTrucks([{
          id: data.truck.id,
          nombre: data.truck.nombre,
          tarifa_delivery: data.truck.tarifa_delivery,
          activo: data.truck.activo
        }]);
      }
    } catch (error) {
      console.error('Error loading delivery fee:', error);
    }
  };

  const loadUserOrders = async () => {
    if (!user?.email) {
      console.log('No hay usuario logueado');
      return;
    }
    
    try {
      console.log('Cargando pedidos del usuario:', user.email);
      const response = await fetch('/api/get_user_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_email: user.email })
      });
      const data = await response.json();
      console.log('Respuesta API pedidos:', data);
      
      if (data.success) {
        console.log('Pedidos encontrados:', data.orders?.length || 0);
        setUserOrders(data.orders || []);
        setUserStats(data.stats || null);
      } else {
        console.log('Error en API:', data.error);
      }
    } catch (error) {
      console.error('Error cargando pedidos del usuario:', error);
      setUserOrders([]);
    }
  };

  const loadNotifications = async () => {
    if (!user) {
      console.log('No hay usuario logueado');
      return;
    }
    
    try {
      const response = await fetch('/api/get_order_notifications.php');
      const data = await response.json();
      
      if (data.success && data.notifications) {
        setNotifications(data.notifications);
        setUnreadCount(data.unread_count || 0);
      } else {
        console.log('Error en API notificaciones:', data.error);
        setNotifications([]);
        setUnreadCount(0);
      }
    } catch (error) {
      console.error('Error cargando notificaciones:', error);
      setNotifications([]);
      setUnreadCount(0);
    }
  };

  const calculateRealDeliveryTime = async (userLat, userLng, truckLat, truckLng) => {
    try {
      const formData = new FormData();
      formData.append('user_lat', userLat);
      formData.append('user_lng', userLng);
      formData.append('truck_lat', truckLat);
      formData.append('truck_lng', truckLng);
      
      const response = await fetch('/api/location/calculate_delivery_time.php', {
        method: 'POST',
        body: formData
      });
      
      if (!response.ok) return;
      const data = await response.json();
      if (data.success) {
        setDeliveryZone(prev => ({
          ...prev,
          zones: prev.zones.map(zone => ({
            ...zone,
            tiempo_estimado: data.total_delivery_time,
            real_time_data: data
          }))
        }));
      }
    } catch (error) {
      // Silenciar errores de API no disponible
    }
  };

  // Cargar productos desde MySQL
  useEffect(() => {
    const loadMenuFromDatabase = async () => {
      try {
        const isCashier = !!localStorage.getItem('caja_session');
        const cashierParam = isCashier ? '&cashier=1' : '';
        const response = await fetch('/api/get_menu_products.php?v=' + Date.now() + cashierParam);
        const data = await response.json();
        
        if (data.success && data.menuData) {
          setMenuWithImages(data.menuData);
        } else {
          console.error('Error cargando menú:', data.error);
          setMenuWithImages({});
        }
      } catch (error) {
        console.error('Error conectando con API:', error);
        setMenuWithImages({});
      }
    };
    
    loadMenuFromDatabase();
    loadDeliveryFee();
  }, []);

  const productsToShow = useMemo(() => {
    const filter = categoryFilters[activeCategory];
    if (!filter) return [];
    
    const allProducts = [];
    Object.values(menuWithImages).forEach(category => {
      if (Array.isArray(category)) {
        allProducts.push(...category);
      } else {
        Object.values(category).forEach(subcat => {
          if (Array.isArray(subcat)) {
            allProducts.push(...subcat);
          }
        });
      }
    });
    
    let filtered = allProducts.filter(p => {
      if (filter.subcategory_id) {
        return p.category_id === filter.category_id && p.subcategory_id === filter.subcategory_id;
      }
      if (filter.subcategory_ids) {
        return p.category_id === filter.category_id && filter.subcategory_ids.includes(p.subcategory_id);
      }
      return false;
    });
    
    if (!showInactiveProducts && cajaUser) {
      filtered = filtered.filter(p => p.active !== 0);
    }
    
    return filtered;
  }, [activeCategory, menuWithImages, showInactiveProducts, cajaUser]);

  const handleAddToCart = (product) => {
    if (product.type === 'combo' || product.category_name === 'Combos') {
      setComboModalProduct(product);
      return;
    }
    
    vibrate(50);
    
    if (window.Analytics) {
      window.Analytics.trackAddToCart(product.id, product.name);
    }
    
    setCart(prevCart => [...prevCart, { 
      ...product, 
      quantity: 1, 
      customizations: null,
      cartItemId: Date.now() + Math.random(),
      category_id: product.category_id,
      subcategory_id: product.subcategory_id,
      subcategory_name: product.subcategory_name
    }]);
  };
  
  const handleRemoveFromCart = (productIdOrCartItemId) => {
    // Si es cartItemId (desde CartModal), eliminar ese item específico
    if (typeof productIdOrCartItemId === 'number' && productIdOrCartItemId > 1000000000000) {
      const product = cart.find(item => item.cartItemId === productIdOrCartItemId);
      if (window.Analytics && product) {
        window.Analytics.trackInteraction({
          action_type: 'remove_from_cart',
          element_type: 'product',
          product_id: product.id,
          product_name: product.name
        });
      }
      setCart(prevCart => prevCart.filter(item => item.cartItemId !== productIdOrCartItemId));
    } else {
      // Si es product.id (desde MenuItem), eliminar el ÚLTIMO item agregado
      const productId = productIdOrCartItemId;
      const itemsOfProduct = cart.filter(item => item.id === productId);
      
      if (itemsOfProduct.length > 0) {
        // Encontrar el último item agregado (mayor cartItemId)
        const lastItem = itemsOfProduct.reduce((latest, current) => 
          current.cartItemId > latest.cartItemId ? current : latest
        );
        
        if (window.Analytics) {
          window.Analytics.trackInteraction({
            action_type: 'remove_from_cart',
            element_type: 'product',
            product_id: lastItem.id,
            product_name: lastItem.name
          });
        }
        
        setCart(prevCart => prevCart.filter(item => item.cartItemId !== lastItem.cartItemId));
      }
    }
  };
  
  const handleCustomizeProduct = (item, itemIndex) => {
    setSelectedProduct({
      ...item,
      isEditing: true,
      cartIndex: itemIndex
    });
    setIsCartOpen(false);
  };
  
  const handleUpdateCartItem = (cartIndex, updatedProduct, newCustomizations) => {
    setCart(prevCart => {
      const newCart = [...prevCart];
      newCart[cartIndex] = {
        ...updatedProduct,
        customizations: newCustomizations.length > 0 ? newCustomizations : null,
        cartItemId: prevCart[cartIndex].cartItemId
      };
      return newCart;
    });
  };

  const cartSubtotal = useMemo(() => {
    return cart.reduce((total, item) => {
      let itemPrice = item.price;
      
      if (item.customizations && item.customizations.length > 0) {
        const customizationsPrice = item.customizations.reduce((sum, c) => {
          let price = c.price * c.quantity;
          if (c.extraPrice && c.quantity > 1) {
            price = c.price + (c.quantity - 1) * c.extraPrice;
          }
          return sum + price;
        }, 0);
        itemPrice += customizationsPrice;
      }
      
      return total + itemPrice;
    }, 0);
  }, [cart]);

  const deliveryFee = useMemo(() => {
    if (customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0) {
      return parseInt(nearbyTrucks[0].tarifa_delivery || 0);
    }
    return 0;
  }, [customerInfo.deliveryType, nearbyTrucks]);

  const cartTotal = useMemo(() => {
    const currentDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 
      ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) 
      : 0;
    return cartSubtotal + currentDeliveryFee;
  }, [cartSubtotal, customerInfo.deliveryType, nearbyTrucks]);

  const cartItemCount = useMemo(() => cart.length, [cart]);
  const getProductQuantity = (productId) => cart.filter(item => item.id === productId).length;
  
  const comboItems = {
      papas_y_snacks: menuWithImages.papas?.papas || [],
      jugos: menuWithImages.papas_y_snacks?.jugos || [],
      bebidas: menuWithImages.papas_y_snacks?.bebidas || [],
      empanadas: menuWithImages.papas_y_snacks?.empanadas || [],
      cafe: menuWithImages.papas_y_snacks?.café || [],
      te: menuWithImages.papas_y_snacks?.té || [],
      salsas: menuWithImages.papas_y_snacks?.salsas || [],
      personalizar: (menuWithImages.completos?.personalizar || []).filter(p => p.active === 1),
      extras: menuWithImages.papas_y_snacks?.extras || []
  };
  
  const generateWhatsAppMessage = (orderId) => {
    const currentDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
    let message = `*NUEVO PEDIDO - LA RUTA 11*\n\n`;
    message += `*Pedido:* ${orderId}\n`;
    message += `*Cliente:* ${customerInfo.name}\n`;
    message += `*Teléfono:* ${customerInfo.phone || 'No especificado'}\n`;
    message += `*Tipo de entrega:* ${customerInfo.deliveryType === 'delivery' ? 'Delivery' : 'Retiro'}\n`;
    
    if (customerInfo.deliveryType === 'delivery' && customerInfo.address) {
      message += `*Dirección:* ${customerInfo.address}\n`;
    }
    if (customerInfo.deliveryType === 'pickup' && customerInfo.pickupTime) {
      message += `*Hora de retiro:* ${customerInfo.pickupTime}\n`;
    }
    
    message += `\n*PRODUCTOS:*\n`;
    cart.forEach((item, index) => {
      const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
      message += `${index + 1}. ${item.name} x${item.quantity} - $${(item.price * item.quantity).toLocaleString('es-CL')}\n`;
      
      if (isCombo && (item.fixed_items || item.selections)) {
        message += `   Incluye:\n`;
        if (item.fixed_items) {
          item.fixed_items.forEach(fixedItem => {
            message += `   • ${item.quantity}x ${fixedItem.product_name || fixedItem.name}\n`;
          });
        }
        if (item.selections) {
          Object.entries(item.selections).forEach(([group, selection]) => {
            if (Array.isArray(selection)) {
              selection.forEach(sel => {
                message += `   • ${item.quantity}x ${sel.name}\n`;
              });
            } else if (selection) {
              message += `   • ${item.quantity}x ${selection.name}\n`;
            }
          });
        }
      }
      
      if (item.customizations && item.customizations.length > 0) {
        item.customizations.forEach(custom => {
          message += `   + ${custom.quantity}x ${custom.name} (+$${(custom.price * custom.quantity).toLocaleString('es-CL')})\n`;
        });
      }
    });
    
    message += `\n*Subtotal:* $${cartSubtotal.toLocaleString('es-CL')}\n`;
    if (currentDeliveryFee > 0) {
      message += `*Delivery:* $${currentDeliveryFee.toLocaleString('es-CL')}\n`;
    }
    message += `*Total:* $${cartTotal.toLocaleString('es-CL')}\n\n`;
    message += `Pedido realizado desde la app web.`;
    
    return message;
  };

  useEffect(() => {
    document.body.style.overflow = selectedProduct || isCartOpen || isLoginOpen || zoomedProduct || showCheckout ? 'hidden' : 'auto';
  }, [selectedProduct, isCartOpen, isLoginOpen, zoomedProduct, showCheckout]);

  useEffect(() => {
    let ticking = false;
    const handleScroll = () => {
      if (!ticking) {
        requestAnimationFrame(() => {
          const currentScrollY = window.scrollY;
          if (currentScrollY > lastScrollY && currentScrollY > 100) {
            setIsNavVisible(false);
            setIsHeaderVisible(false);
          } else {
            setIsNavVisible(true);
            setIsHeaderVisible(true);
          }
          setLastScrollY(currentScrollY);
          ticking = false;
        });
        ticking = true;
      }
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, [lastScrollY]);

  useEffect(() => {
    // Registrar Service Worker para PWA badge
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
    
    // Desbloquear audio con primera interacción del usuario
    const unlockAudio = () => {
      initAudio();
      // Remover listeners después del primer uso
      document.removeEventListener('click', unlockAudio);
      document.removeEventListener('touchstart', unlockAudio);
    };
    document.addEventListener('click', unlockAudio, { once: true });
    document.addEventListener('touchstart', unlockAudio, { once: true });
    
    // Mostrar loader por 1.5 segundos
    setIsLoading(true);
    const timer = setTimeout(() => {
      setIsLoading(false);
    }, 1500);
    
    return () => {
      clearTimeout(timer);
      document.removeEventListener('click', unlockAudio);
      document.removeEventListener('touchstart', unlockAudio);
    };
  }, []);

  // Sistema de tracking de uso
  useEffect(() => {
    if (!user) return;
    
    // Iniciar sesión
    const startSession = async () => {
      try {
        const formData = new FormData();
        formData.append('action', 'start_session');
        formData.append('session_id', sessionId);
        const response = await fetch('/api/track_usage.php', { method: 'POST', body: formData });
        const result = await response.json();
        console.log('Start session result:', result);
      } catch (error) {
        console.error('Error iniciando sesión:', error);
      }
    };
    
    startSession();
    
    // Actualizar tiempo cada 30 segundos
    const timeInterval = setInterval(() => {
      setCurrentSessionTime(Math.floor((Date.now() - sessionStartTime) / 1000));
      
      const formData = new FormData();
      formData.append('action', 'update_activity');
      formData.append('session_id', sessionId);
      fetch('/api/track_usage.php', { method: 'POST', body: formData }).catch(() => {});
    }, 30000);
    
    // Finalizar sesión al cerrar
    const endSession = () => {
      const formData = new FormData();
      formData.append('action', 'end_session');
      formData.append('session_id', sessionId);
      navigator.sendBeacon('/api/track_usage.php', formData);
    };
    
    window.addEventListener('beforeunload', endSession);
    
    return () => {
      clearInterval(timeInterval);
      window.removeEventListener('beforeunload', endSession);
      endSession();
    };
  }, [user, sessionId, sessionStartTime]);

  // Autocompletar campos del checkout cuando se abre el modal
  useEffect(() => {
    if (showCheckout && user) {
      setCustomerInfo({
        name: user.nombre || '',
        phone: user.telefono || '',
        email: user.email || '',
        address: user.direccion || userLocation?.address || ''
      });
    }
  }, [showCheckout, user, userLocation]);

  useEffect(() => {
    // Cargar usuario de caja/admin desde localStorage
    const cajaSession = localStorage.getItem('caja_session');
    if (cajaSession) {
      try {
        const sessionData = JSON.parse(cajaSession);
        setCajaUser(sessionData);
      } catch (error) {
        console.error('Error parsing caja session:', error);
        localStorage.removeItem('caja_session');
      }
    }
    
    // Verificar si usuario está logueado
    fetch('/api/auth/check_session.php')
      .then(response => {
        if (!response.ok) return null;
        return response.text();
      })
      .then(text => {
        if (!text || text.trim().startsWith('<')) return;
        try {
          const data = JSON.parse(text);
          if (data.authenticated) {
            setUser(data.user);
            setTimeout(() => {
              loadNotifications();
              loadUserOrders();
            }, 100);
          }
        } catch (e) {
          // Silenciar errores de parsing
        }
      })
      .catch(() => {});

    // Detectar parámetros de URL para login/logout/producto compartido
    const urlParams = new URLSearchParams(window.location.search);
    
    // Producto compartido
    const sharedProductId = urlParams.get('product');
    if (sharedProductId) {
      // Buscar y mostrar el producto compartido después de cargar el menú
      setTimeout(() => {
        const allProducts = [];
        Object.values(menuWithImages).forEach(category => {
          if (Array.isArray(category)) {
            allProducts.push(...category);
          } else {
            Object.values(category).forEach(subcategory => {
              if (Array.isArray(subcategory)) {
                allProducts.push(...subcategory);
              }
            });
          }
        });
        
        const sharedProduct = allProducts.find(p => p.id == sharedProductId);
        if (sharedProduct) {
          setSelectedProduct(sharedProduct);
          // Limpiar URL
          window.history.replaceState({}, document.title, window.location.pathname);
        }
      }, 1000);
    }
    
    if (urlParams.get('login') === 'success') {
      // Limpiar URL y recargar sesión
      window.history.replaceState({}, document.title, window.location.pathname);
      // Recargar datos de sesión sin reload completo
      fetch('/api/auth/check_session.php')
        .then(response => response.json())
        .then(data => {
          if (data.authenticated) {
            setUser(data.user);
            loadNotifications();
            loadUserOrders();
            
            // DISABLED: Onboarding no necesario en caja3
            // if (data.user.is_new_user && !localStorage.getItem('onboarding_completed')) {
            //   setTimeout(() => setShowOnboarding(true), 500);
            // }
          }
        })
        .catch(error => console.error('Error checking session after login:', error));
    }
    if (urlParams.get('logout') === 'success') {
      setUser(null);
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  }, [menuWithImages]);

  if (isLoading) {
    return <LoadingScreen onComplete={() => setIsLoading(false)} />;
  }

  return (
    <div className="bg-white font-sans min-h-screen w-full pb-24" style={{backgroundColor: '#ffffff', background: '#ffffff'}}>
      <header className="px-4 py-2 sm:p-3 fixed top-0 left-0 right-0 bg-white z-40 shadow-sm">
        <div className="flex items-center justify-between w-full">
          {/* Logo */}
          <img src="https://laruta11-images.s3.amazonaws.com/menu/logo.png" alt="La Ruta 11" style={{width: 'clamp(32px, 8vw, 40px)', height: 'clamp(32px, 8vw, 40px)'}} />
          
          {/* Checklist */}
          {cajaUser && (
            <button 
                onClick={() => { vibrate(30); window.location.href = '/checklist'; }} 
                className="text-gray-600 hover:text-orange-500 transition-colors"
                title="Checklist"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M9 11l3 3L22 4"></path>
                  <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>
            </button>
          )}
          
          {/* Toggle Productos Inactivos */}
          {cajaUser && (
            <button 
                onClick={() => { vibrate(30); setShowInactiveProducts(!showInactiveProducts); }} 
                className={`p-1.5 rounded-lg transition-all ${
                  showInactiveProducts 
                    ? 'bg-red-500 text-white' 
                    : 'bg-gray-200 text-gray-600 hover:bg-gray-300'
                }`}
                title={showInactiveProducts ? 'Ocultar inactivos' : 'Mostrar inactivos'}
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  {showInactiveProducts ? (
                    <>
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </>
                  ) : (
                    <>
                      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                      <line x1="1" y1="1" x2="23" y2="23"></line>
                    </>
                  )}
                </svg>
            </button>
          )}
          
          {/* Perfil Cajera */}
          {cajaUser && (
            <button 
                onClick={() => { vibrate(30); setIsProfileOpen(true); }} 
                className="flex items-center gap-2 text-gray-600 hover:text-orange-500 p-1 rounded-lg hover:bg-gray-100 transition-all"
                title="Perfil Cajera"
            >
                <User size={20} className="text-orange-500" />
                <span className="font-medium" style={{fontSize: 'clamp(12px, 3vw, 14px)'}}>{cajaUser.fullName || cajaUser.user}</span>
            </button>
          )}
          
          {/* Configuración */}
          {cajaUser && (
            <button 
                onClick={async () => { 
                  vibrate(30); 
                  setShowStatusModal(true);
                  const res = await fetch('/api/get_truck_status.php?truckId=4');
                  const data = await res.json();
                  if (data.success) setTruckStatus(data.truck);
                  
                  const schedRes = await fetch('/api/get_truck_schedules.php?truckId=4');
                  const schedData = await schedRes.json();
                  if (schedData.success) {
                    setSchedules(schedData.schedules);
                    setCurrentDayOfWeek(schedData.currentDayOfWeek);
                  }
                  
                  const catRes = await fetch('/api/get_product_categories.php');
                  const catData = await catRes.json();
                  if (catData.success) setCategories(catData.categories);
                  }
                }}
                className="text-gray-600 hover:text-orange-500 transition-colors"
                title="Configuración"
            >
                <Settings size={20} />
            </button>
          )}
          
          {/* Compartir */}
          <button 
              onClick={() => { vibrate(30); setShowQRModal(true); }}
              className="text-gray-600 hover:text-orange-500 transition-colors"
              title="Compartir App"
          >
              <Share2 size={20} />
          </button>
          
          {/* Notificaciones */}
          <button 
              onClick={() => { vibrate(30); setIsNotificationsOpen(true); }}
              className="text-gray-600 hover:text-orange-500 relative" 
              title="Notificaciones"
          >
              <NotificationIcon size={20} />
              {activeOrdersCount > 0 && (
                  <span className="absolute -top-1 -right-1 bg-red-500 text-white rounded-full flex items-center justify-center" style={{fontSize: 'clamp(8px, 2vw, 10px)', width: 'clamp(14px, 3.5vw, 16px)', height: 'clamp(14px, 3.5vw, 16px)'}}>
                      {activeOrdersCount}
                  </span>
              )}
              {activeChecklistsCount > 0 && (
                  <span className="absolute -bottom-1 -right-1 bg-blue-500 text-white rounded-full flex items-center justify-center" style={{fontSize: 'clamp(8px, 2vw, 10px)', width: 'clamp(14px, 3.5vw, 16px)', height: 'clamp(14px, 3.5vw, 16px)'}}>
                      {activeChecklistsCount}
                  </span>
              )}
          </button>
          
          {/* Carrito */}
          <button onClick={() => { vibrate(30); setShowCheckout(true); }} className="text-gray-600 hover:text-orange-500 relative">
              <ShoppingCart size={20}/>
              {cartItemCount > 0 && (
                  <span className="absolute -top-2 -right-2 bg-red-500 text-white font-bold rounded-full flex items-center justify-center animate-fade-in" style={{fontSize: 'clamp(9px, 2.2vw, 11px)', width: 'clamp(16px, 4vw, 20px)', height: 'clamp(16px, 4vw, 20px)'}}>
                      {cartItemCount}
                  </span>
              )}
          </button>
        </div>
      </header>
      

      
      <main className="pt-20 pb-24 px-0.5 sm:px-4 lg:px-8 xl:px-12 2xl:px-16 max-w-screen-2xl mx-auto" style={showSuggestions ? {filter: 'blur(2px)', pointerEvents: 'none'} : {}}>
        {(activeCategory === 'churrascos' || activeCategory === 'completos' || activeCategory === 'Combos') ? (
            <div className="space-y-8">
                {(() => {
                  let categoryData = menuWithImages[activeCategory];
                  if (!categoryData) return null;
                  
                  // Filtro para hamburguesas 100g (solo clásicas)
                  if (activeCategory === 'hamburguesas_100g') {
                    categoryData = {};
                    Object.entries(menuWithImages.hamburguesas || {}).forEach(([subCat, products]) => {
                      const filtered = products.filter(p => p.subcategory_id === 5);
                      if (filtered.length > 0) categoryData[subCat] = filtered;
                    });
                  }
                  
                  // Filtro para hamburguesas 200g (excluir clásicas)
                  if (activeCategory === 'hamburguesas') {
                    categoryData = {};
                    Object.entries(menuWithImages.hamburguesas || {}).forEach(([subCat, products]) => {
                      const filtered = products.filter(p => p.subcategory_id !== 5);
                      if (filtered.length > 0) categoryData[subCat] = filtered;
                    });
                  }
                  
                  // Filtro para Papas (Cat 12, Subcat 9 y 57)
                  if (activeCategory === 'papas') {
                    categoryData = { papas: [] };
                    Object.values(menuWithImages).forEach(category => {
                      if (Array.isArray(category)) {
                        categoryData.papas.push(...category.filter(p => p.category_id === 12 && [9, 57].includes(p.subcategory_id)));
                      } else {
                        Object.values(category).forEach(subcat => {
                          if (Array.isArray(subcat)) {
                            categoryData.papas.push(...subcat.filter(p => p.category_id === 12 && [9, 57].includes(p.subcategory_id)));
                          }
                        });
                      }
                    });
                  }
                  
                  // Filtro para Pizzas (Cat 5, Subcat 60)
                  if (activeCategory === 'pizzas') {
                    categoryData = { pizzas: [] };
                    Object.values(menuWithImages).forEach(category => {
                      if (Array.isArray(category)) {
                        categoryData.pizzas.push(...category.filter(p => p.category_id === 5 && p.subcategory_id === 60));
                      } else {
                        Object.values(category).forEach(subcat => {
                          if (Array.isArray(subcat)) {
                            categoryData.pizzas.push(...subcat.filter(p => p.category_id === 5 && p.subcategory_id === 60));
                          }
                        });
                      }
                    });
                  }
                  
                  // Filtro para Bebidas (Cat 5, Subcat 11, 10, 28, 27)
                  if (activeCategory === 'bebidas') {
                    categoryData = {};
                    const bebidasSubcats = { 11: 'bebidas', 10: 'jugos', 28: 'té', 27: 'café' };
                    Object.values(menuWithImages).forEach(category => {
                      if (Array.isArray(category)) {
                        category.filter(p => p.category_id === 5 && [11, 10, 28, 27].includes(p.subcategory_id)).forEach(p => {
                          const subName = bebidasSubcats[p.subcategory_id];
                          if (!categoryData[subName]) categoryData[subName] = [];
                          categoryData[subName].push(p);
                        });
                      } else {
                        Object.values(category).forEach(subcat => {
                          if (Array.isArray(subcat)) {
                            subcat.filter(p => p.category_id === 5 && [11, 10, 28, 27].includes(p.subcategory_id)).forEach(p => {
                              const subName = bebidasSubcats[p.subcategory_id];
                              if (!categoryData[subName]) categoryData[subName] = [];
                              categoryData[subName].push(p);
                            });
                          }
                        });
                      }
                    });
                  }
                  
                  let orderedEntries = Object.entries(categoryData);
                  
                  // Orden específico para completos
                  if (activeCategory === 'completos') {
                    orderedEntries = [
                      ['tradicionales', categoryData.tradicionales || []],
                      ['especiales', categoryData.especiales || []],
                      ['al vapor', categoryData['al vapor'] || []]
                    ];
                  }
                  
                  return orderedEntries
                    .filter(([subCategory, products]) => products && products.length > 0)
                    .map(([subCategory, products]) => {
                      // Filtrar productos inactivos si el toggle está desactivado
                      const filteredProducts = (!showInactiveProducts && cajaUser) 
                        ? products.filter(p => p.active !== 0) 
                        : products;
                      
                      if (filteredProducts.length === 0) return null;
                      
                      return (
                    <section key={subCategory} id={subCategory}>
                        <h2 className="text-2xl sm:text-3xl font-extrabold text-gray-800 capitalize border-b-2 border-orange-500 pb-2 px-2 mb-2">{subCategory === 'papas' ? 'Papas Fritas ❤️' : subCategory === 'sandwiches' ? 'Sándwiches' : subCategory}</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-3 gap-2 sm:gap-3 lg:gap-4 xl:gap-6 mt-4">
                            {filteredProducts.map(product => (
                                <div key={product.id} id={`product-${product.id}`} className={highlightedProductId === product.id ? 'border-4 border-orange-500 rounded-xl transition-all duration-300' : ''}>
                                  <MenuItem
                                      product={product}
                                      type={product.subcategory_name || subCategory}
                                      onSelect={null}
                                      onAddToCart={handleAddToCart}
                                      onRemoveFromCart={handleRemoveFromCart}
                                      quantity={getProductQuantity(product.id)}
                                      isLiked={likedProducts.has(product.id)}
                                      handleLike={handleLike}
                                      setReviewsModalProduct={setReviewsModalProduct}
                                      onShare={setShareModalProduct}
                                      isCashier={!!cajaUser}
                                  />
                                </div>
                            ))}
                        </div>
                    </section>
                      );
                    }).filter(Boolean);
                })()}
            </div>
        ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-3 gap-2 sm:gap-3 lg:gap-4 xl:gap-6">
                {productsToShow.map(product => (
                    <div key={product.id} id={`product-${product.id}`} className={highlightedProductId === product.id ? 'border-4 border-orange-500 rounded-xl transition-all duration-300' : ''}>
                      <MenuItem
                          product={product}
                          onSelect={null}
                          onAddToCart={handleAddToCart}
                          onRemoveFromCart={handleRemoveFromCart}
                          quantity={getProductQuantity(product.id)}
                          isLiked={likedProducts.has(product.id)}
                          handleLike={handleLike}
                          setReviewsModalProduct={setReviewsModalProduct}
                          onShare={setShareModalProduct}
                          isCashier={!!cajaUser}
                      />
                    </div>
                ))}
            </div>
        )}
      </main>

      {/* Barra de búsqueda con botones */}
      <div className="fixed bottom-20 left-4 right-4 z-30 flex items-center gap-2 lg:left-1/2 lg:transform lg:-translate-x-1/2 lg:max-w-xl">
        <button
          onClick={() => window.location.href = '/mermas'}
          className="bg-red-600 hover:bg-red-700 text-white rounded-full shadow-lg transition-all flex items-center justify-center flex-shrink-0"
          style={{ width: '40px', height: '40px' }}
          title="Mermas"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
          </svg>
        </button>
        <div className="flex-1 bg-white border border-gray-200 rounded-full shadow-lg">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={16} />
            <input
              type="text"
              placeholder="Buscar productos..."
              value={searchQuery}
              onChange={(e) => handleSearch(e.target.value)}
              onFocus={() => {
                if (searchQuery && suggestions.length > 0) {
                  setShowSuggestions(true);
                }
              }}
              className="w-full pl-9 pr-8 py-2 bg-transparent rounded-full text-sm focus:outline-none transition-all"
            />
            {searchQuery && (
              <button
                onClick={() => handleSearch('')}
                className="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
              >
                <X size={16} />
              </button>
            )}
            {/* Sugerencias */}
            {showSuggestions && (
              <div className="absolute bottom-full left-0 right-0 bg-white border border-gray-200 rounded-xl shadow-lg max-h-80 overflow-y-auto mb-2">
                {suggestions.map(product => {
                  const quantity = getProductQuantity(product.id);
                  return (
                    <div
                      key={product.id}
                      className="w-full px-3 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-b-0 flex items-center gap-2"
                    >
                      <div 
                        className="flex items-center gap-2 flex-1 min-w-0 cursor-pointer"
                        onClick={() => selectSuggestion(product)}
                      >
                        {product.image ? (
                          <img src={product.image} alt={product.name} className="w-10 h-10 object-cover rounded" />
                        ) : (
                          <div className="w-10 h-10 bg-gray-200 rounded"></div>
                        )}
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium text-gray-800 truncate">{product.name}</p>
                          <p className="text-xs text-gray-500">{categoryDisplayNames[product.category]}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2 flex-shrink-0">
                        <p className="text-sm font-bold bg-yellow-400 text-black px-2 py-1 rounded">${product.price.toLocaleString('es-CL')}</p>
                        {quantity > 0 && (
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              handleRemoveFromCart(product.id);
                            }}
                            className="bg-red-500 hover:bg-red-600 text-white rounded-full w-7 h-7 flex items-center justify-center font-bold text-lg"
                          >
                            -
                          </button>
                        )}
                        {quantity > 0 && <span className="font-bold text-sm text-gray-800 w-5 text-center">{quantity}</span>}
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            handleAddToCart(product);
                          }}
                          className="bg-green-500 hover:bg-green-600 text-white rounded-full w-7 h-7 flex items-center justify-center font-bold text-lg"
                        >
                          +
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
        <button
          onClick={() => window.location.href = '/arqueo'}
          className="bg-green-600 hover:bg-green-700 text-white rounded-full shadow-lg transition-all flex items-center justify-center flex-shrink-0"
          style={{ width: '40px', height: '40px' }}
          title="Arqueo de Caja"
        >
          <span style={{ fontSize: '18px', fontWeight: 'bold' }}>$</span>
        </button>
      </div>

      <nav className="fixed bottom-0 left-0 right-0 z-40 lg:left-1/2 lg:transform lg:-translate-x-1/2 lg:max-w-4xl lg:rounded-t-2xl">
        <div className="bg-white shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] rounded-t-xl">
          <div className="flex items-center overflow-x-auto px-2 pt-0 pb-4 gap-1" style={{scrollbarWidth: 'none', msOverflowStyle: 'none'}}>
          {mainCategories.map(cat => (
            <button
              key={cat}
              onClick={() => { vibrate(30); setActiveCategory(cat); }}
              className={`flex flex-col items-center justify-center flex-shrink-0 min-w-[70px] py-2 px-2 transition-colors duration-200 text-xs font-medium rounded-lg relative ${
                activeCategory === cat
                  ? ''
                  : 'text-gray-700 hover:text-orange-500 hover:bg-orange-50'
              }`}
            >
              {activeCategory === cat && (
                <div className="absolute -top-0 left-0 right-0 h-1 bg-orange-500"></div>
              )}
              <div 
                className="flex items-center justify-center h-6 mb-1"
                style={{color: activeCategory === cat ? categoryColors[cat] : '#374151'}}
              >
                {categoryIcons[cat]}
              </div>
              <span 
                className="text-[9px] sm:text-[10px] leading-tight text-center max-w-full break-words"
                style={activeCategory === cat ? (
                  cat === 'la_ruta_11' ? {
                    color: '#dc2626'
                  } : {
                    background: 'linear-gradient(135deg, #dc2626, #ea580c, #f97316)',
                    WebkitBackgroundClip: 'text',
                    WebkitTextFillColor: 'transparent',
                    backgroundClip: 'text'
                  }
                ) : {}}
              >
                {categoryDisplayNames[cat]}
              </span>
            </button>
          ))}
          </div>
        </div>
      </nav>

      <LoginModal isOpen={isLoginOpen} onClose={() => setIsLoginOpen(false)} />
      <FoodTrucksModal 
        isOpen={isFoodTrucksOpen} 
        onClose={() => setIsFoodTrucksOpen(false)}
        trucks={nearbyTrucks}
        userLocation={userLocation}
        deliveryZone={deliveryZone}
      />
      <NotificationsModal 
        isOpen={isNotificationsOpen} 
        onClose={() => setIsNotificationsOpen(false)}
        onOrdersUpdate={(count) => setActiveOrdersCount(count)}
        activeOrdersCount={activeOrdersCount}
      />
      <SecurityModal 
        isOpen={isLogoutModalOpen}
        onClose={() => setIsLogoutModalOpen(false)}
        type="logout"
        onConfirm={handleLogout}
      />
      <SecurityModal 
        isOpen={isDeleteAccountModalOpen}
        onClose={() => setIsDeleteAccountModalOpen(false)}
        type="delete"
        onConfirm={handleDeleteAccount}
      />
      <SaveChangesModal 
        isOpen={isSaveChangesModalOpen}
        onClose={() => setIsSaveChangesModalOpen(false)}
        onSave={handleSaveChanges}
        onDiscard={handleDiscardChanges}
      />
      {/* Modal de Perfil Caja */}
      {isProfileOpen && cajaUser && (
        <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={() => setIsProfileOpen(false)}>
          <div className="bg-white w-full max-w-md mx-4 rounded-2xl flex flex-col animate-slide-up" onClick={(e) => e.stopPropagation()}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <h2 className="text-xl font-bold text-gray-800">Datos de Usuario</h2>
                <button onClick={() => setIsProfileOpen(false)} className="p-1 text-gray-400 hover:text-gray-600">
                  <X size={24} />
                </button>
              </div>
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
                  <input
                    type="text"
                    value={cajaUser.user}
                    disabled
                    className="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-600 cursor-not-allowed"
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Nombre Completo</label>
                  <input
                    type="text"
                    value={cajaUser.fullName || ''}
                    onChange={(e) => setCajaUser({...cajaUser, fullName: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="Tu nombre completo"
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                  <input
                    type="tel"
                    value={cajaUser.phone || ''}
                    onChange={(e) => setCajaUser({...cajaUser, phone: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="+56 9 1234 5678"
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                  <input
                    type="email"
                    value={cajaUser.email || ''}
                    onChange={(e) => setCajaUser({...cajaUser, email: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="tu@email.com"
                  />
                </div>
                
                <button
                  onClick={async () => {
                    try {
                      const response = await fetch('/api/update_cashier_profile.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                          userId: cajaUser.userId,
                          fullName: cajaUser.fullName || '',
                          phone: cajaUser.phone || '',
                          email: cajaUser.email || ''
                        })
                      });
                      const result = await response.json();
                      if (result.success) {
                        localStorage.setItem('caja_session', JSON.stringify(cajaUser));
                        alert('✅ Datos actualizados exitosamente');
                        setIsProfileOpen(false);
                      } else {
                        alert('❌ Error: ' + (result.error || 'No se pudo actualizar'));
                      }
                    } catch (error) {
                      console.error('Error:', error);
                      alert('❌ Error de conexión');
                    }
                  }}
                  className="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition-colors"
                >
                  Guardar Cambios
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
      <ProductDetailModal 
        product={selectedProduct} 
        onClose={() => setSelectedProduct(null)}
        onAddToCart={handleAddToCart}
        onRemoveFromCart={handleRemoveFromCart}
        getProductQuantity={getProductQuantity}
        activeCategory={activeCategory}
        comboItems={comboItems}
        cart={cart}
        onZoom={(product, total) => setZoomedProduct({ product, total })}
        setReviewsModalProduct={setReviewsModalProduct}
        user={user}
        onUpdateCartItem={handleUpdateCartItem}
      />
       <ImageFullscreenModal 
        product={zoomedProduct?.product} 
        total={zoomedProduct?.total}
        onClose={() => setZoomedProduct(null)} 
      />
      {/* CartModal eliminado - ahora se usa directamente showCheckout */}
      
      {/* Checkout Modal */}
      {showCheckout && (
        <div className="fixed inset-0 bg-white z-40 flex flex-col overflow-hidden">
          <div className="bg-white w-full h-full flex flex-col overflow-hidden">
            {/* Header fijo con gradiente */}
            <div className="bg-gradient-to-r from-red-500 to-orange-500 text-white flex justify-between items-center px-6 py-4 shadow-lg sticky top-0 z-50">
              <h2 className="text-xl font-bold">Finalizar Pedido</h2>
              <button onClick={() => setShowCheckout(false)} className="p-1 hover:bg-white/20 rounded-full transition-colors">
                <X size={24} />
              </button>
            </div>
            
            <div className="flex-1 overflow-y-auto p-6">
              
              {/* Tipo de entrega */}
              <div className="mb-4">
                <h3 className="text-sm font-semibold text-gray-800 mb-2">Tipo de Entrega</h3>
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={() => setCustomerInfo({...customerInfo, deliveryType: 'delivery'})}
                    className={`p-2 border-2 rounded-lg transition-colors flex items-center gap-2 ${
                      customerInfo.deliveryType === 'delivery' 
                        ? 'border-orange-500 bg-orange-50 text-orange-700' 
                        : 'border-gray-300 hover:border-gray-400'
                    }`}
                  >
                    <Bike size={20} className="text-red-500 flex-shrink-0" />
                    <span className="font-semibold text-sm">Delivery</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setCustomerInfo({...customerInfo, deliveryType: 'pickup'})}
                    className={`p-2 border-2 rounded-lg transition-colors flex items-center gap-2 ${
                      customerInfo.deliveryType === 'pickup' 
                        ? 'border-orange-500 bg-orange-50 text-orange-700' 
                        : 'border-gray-300 hover:border-gray-400'
                    }`}
                  >
                    <Caravan size={20} className="text-red-500 flex-shrink-0" />
                    <span className="font-semibold text-sm">Retiro</span>
                  </button>
                </div>
              </div>
              
              <div className="space-y-4 mb-6">
                <h3 className="text-lg font-semibold text-gray-800 mb-3">Datos del Cliente</h3>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Nombre completo *</label>
                  <input
                    type="text"
                    value={customerInfo.name || user?.nombre || ''}
                    onChange={(e) => setCustomerInfo({...customerInfo, name: e.target.value})}
                    className={`w-full px-3 py-2 border rounded-md focus:outline-none ${
                      user ? 'border-gray-200 bg-gray-50 text-gray-600 cursor-not-allowed' : 'border-gray-300 focus:ring-2 focus:ring-orange-500'
                    }`}
                    placeholder="Tu nombre completo"
                    readOnly={!!user}
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                  <input
                    type="tel"
                    value={customerInfo.phone || user?.telefono || ''}
                    onChange={(e) => setCustomerInfo({...customerInfo, phone: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="+56 9 1234 5678"
                  />
                </div>
                
                {/* Descuentos */}
                <div className="flex flex-wrap gap-2 mb-4">
                  {customerInfo.deliveryType === 'delivery' && (
                    <button
                      onClick={() => {
                        if (!customerInfo.deliveryDiscount) {
                          const confirmed = window.confirm('🚚 Descuento Delivery (40%)\n\nSe aplicará un 40% de descuento en el costo de delivery. Solo válido para direcciones específicas.\n\n¿Aplicar descuento?');
                          if (confirmed) {
                            setCustomerInfo({...customerInfo, deliveryDiscount: true, address: ''});
                          }
                        } else {
                          setCustomerInfo({...customerInfo, deliveryDiscount: false});
                        }
                      }}
                      className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium transition-all ${
                        customerInfo.deliveryDiscount 
                          ? 'bg-green-500 text-white' 
                          : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                      }`}
                    >
                      <Bike size={14} />
                      <input
                        type="checkbox"
                        checked={customerInfo.deliveryDiscount}
                        readOnly
                        className="w-3 h-3 pointer-events-none"
                      />
                      <span>-40% Delivery</span>
                    </button>
                  )}
                  {customerInfo.deliveryType === 'pickup' && (
                    <button
                      onClick={() => {
                        if (!customerInfo.pickupDiscount) {
                          const confirmed = window.confirm('🏪 Descuento R11 (10%)\n\nSe aplicará un 10% de descuento en el total de tu compra por retiro en local.\n\n¿Aplicar descuento?');
                          if (confirmed) {
                            setCustomerInfo({...customerInfo, pickupDiscount: true});
                          }
                        } else {
                          setCustomerInfo({...customerInfo, pickupDiscount: false});
                        }
                      }}
                      className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium transition-all ${
                        customerInfo.pickupDiscount 
                          ? 'bg-green-500 text-white' 
                          : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                      }`}
                    >
                      <Percent size={14} />
                      <input
                        type="checkbox"
                        checked={customerInfo.pickupDiscount}
                        readOnly
                        className="w-3 h-3 pointer-events-none"
                      />
                      <span>-10% R11</span>
                    </button>
                  )}
                  <button
                    onClick={() => {
                      if (!cart.some(item => item.id === 9)) {
                        alert('⚠️ Debes agregar una Hamburguesa Clásica al carrito para aplicar este descuento.');
                        return;
                      }
                      if (!customerInfo.birthdayDiscount) {
                        const confirmed = window.confirm('🎂 Descuento Cumpleaños\n\nHamburguesa Clásica GRATIS por tu cumpleaños.\n\n¿Aplicar descuento?');
                        if (confirmed) {
                          setCustomerInfo({...customerInfo, birthdayDiscount: true});
                        }
                      } else {
                        setCustomerInfo({...customerInfo, birthdayDiscount: false});
                      }
                    }}
                    className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium transition-all cursor-pointer ${
                      customerInfo.birthdayDiscount 
                        ? 'bg-green-500 text-white' 
                        : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                    }`}
                  >
                    <Tag size={14} />
                    <input
                      type="checkbox"
                      checked={customerInfo.birthdayDiscount}
                      readOnly
                      className="w-3 h-3 pointer-events-none"
                    />
                    <span>🎂 Cumpleaños</span>
                  </button>
                  <div className="flex items-center gap-1">
                    <span className="bg-orange-500 text-white px-1.5 py-0.5 rounded text-[10px] font-medium whitespace-nowrap">Código Descuento:</span>
                    <input
                      type="text"
                      value={discountCode}
                      onChange={(e) => setDiscountCode(e.target.value.toUpperCase())}
                      maxLength="7"
                      placeholder="Código"
                      className="w-20 px-2 py-1 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-orange-500 uppercase"
                    />
                  </div>
                </div>
                
                {/* Dirección para delivery */}
                {customerInfo.deliveryType === 'delivery' && (
                  <>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Dirección de entrega *</label>
                      {customerInfo.deliveryDiscount ? (
                        <select
                          value={customerInfo.address}
                          onChange={(e) => setCustomerInfo({...customerInfo, address: e.target.value})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                          required
                        >
                          <option value="">Seleccionar dirección con descuento</option>
                          <option value="Ctel. Oscar Quina 1333">Ctel. Oscar Quina 1333</option>
                          <option value="Ctel. Domeyco 1540">Ctel. Domeyco 1540</option>
                          <option value="Ctel. Av. Santa María 3000">Ctel. Av. Santa María 3000</option>
                        </select>
                      ) : (
                        <input
                          type="text"
                          id="deliveryAddress"
                          value={customerInfo.address || ''}
                          onChange={(e) => setCustomerInfo({...customerInfo, address: e.target.value})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                          placeholder="Ingresa tu dirección..."
                          required
                        />
                      )}
                      {nearbyTrucks.length > 0 && !customerInfo.deliveryDiscount && (
                        <p className="text-xs text-blue-600 mt-1">
                          🚚 Costo de delivery: ${parseInt(nearbyTrucks[0].tarifa_delivery || 0).toLocaleString('es-CL')}
                        </p>
                      )}
                      {nearbyTrucks.length === 0 && (
                        <p className="text-xs text-red-600 mt-1">
                          ⚠️ No hay food trucks disponibles para delivery
                        </p>
                      )}
                    </div>
                  </>
                )}
                
                {/* Notas adicionales */}
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label className="text-sm font-medium text-gray-700">Notas adicionales (opcional)</label>
                    <span className="bg-blue-500 text-white text-xs px-2 py-0.5 rounded-full font-medium">
                      {400 - (customerInfo.customerNotes?.length || 0)}
                    </span>
                  </div>
                  <textarea
                    value={customerInfo.customerNotes}
                    onChange={(e) => setCustomerInfo({...customerInfo, customerNotes: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 resize-y"
                    placeholder="Ej: sin cebolla, sin tomate, extra salsa..."
                    rows="1"
                    maxLength="400"
                  />
                </div>
              </div>
              
              <div className="border-t pt-4 mb-6">
                <h3 className="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                  <ShoppingCart size={20} className="text-orange-500" />
                  Tu Pedido
                </h3>
                <div className="space-y-3 mb-4">
                  {cart.map((item, index) => {
                    const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
                    let itemTotal = item.price * item.quantity;
                    
                    // Sumar personalizaciones regulares
                    if (item.customizations && item.customizations.length > 0) {
                      itemTotal += item.customizations.reduce((sum, c) => sum + (c.price * c.quantity), 0);
                    }
                    
                    // Sumar costos adicionales de selecciones de combo
                    if (isCombo && item.selections) {
                      Object.values(item.selections).forEach(selection => {
                        if (Array.isArray(selection)) {
                          selection.forEach(sel => {
                            if (sel.price && sel.price > 0) {
                              itemTotal += sel.price * item.quantity;
                            }
                          });
                        } else if (selection && selection.price && selection.price > 0) {
                          itemTotal += selection.price * item.quantity;
                        }
                      });
                    }
                    
                    return (
                      <div key={item.cartItemId || item.id} className="border-b border-gray-100 pb-3 last:border-b-0">
                        <div className="flex justify-between items-start mb-2">
                          <div className="flex-1">
                            <p className="font-medium text-gray-800 text-sm">{item.name}</p>
                            <p className="text-xs text-gray-500">Cantidad: {item.quantity}</p>
                            
                            {item.customizations && item.customizations.length > 0 && (
                              <div className="mt-1 text-xs">
                                <span className="font-medium text-gray-700">Incluye:</span>
                                {item.customizations.map((custom, idx) => (
                                  <div key={idx} className="text-blue-600">
                                    • {custom.quantity}x {custom.name} (+${(custom.price * custom.quantity).toLocaleString('es-CL')})
                                  </div>
                                ))}
                              </div>
                            )}
                            
                            {isCombo && (item.fixed_items || item.selections) && (
                              <div className="mt-1 text-xs text-gray-600">
                                <span className="font-medium">Incluye: </span>
                                {item.fixed_items && item.fixed_items.map((fixedItem, idx) => (
                                  <span key={idx}>{item.quantity}x {fixedItem.product_name || fixedItem.name}{idx < item.fixed_items.length - 1 || Object.keys(item.selections || {}).length > 0 ? ', ' : ''}</span>
                                ))}
                                {item.selections && Object.entries(item.selections).map(([group, selection], idx) => {
                                  if (Array.isArray(selection)) {
                                    return selection.map((sel, selIdx) => (
                                      <span key={`${group}-${selIdx}`} className={sel.price > 0 ? "text-blue-600 font-medium" : "text-blue-600"}>
                                        {item.quantity}x {sel.name}{sel.price > 0 ? ` (+$${(sel.price * item.quantity).toLocaleString('es-CL')})` : ''}
                                        {selIdx < selection.length - 1 ? ', ' : (idx < Object.keys(item.selections).length - 1 ? ', ' : '')}
                                      </span>
                                    ));
                                  } else {
                                    return (
                                      <span key={group} className={selection.price > 0 ? "text-blue-600 font-medium" : "text-blue-600"}>
                                        {item.quantity}x {selection.name}{selection.price > 0 ? ` (+$${(selection.price * item.quantity).toLocaleString('es-CL')})` : ''}
                                        {idx < Object.keys(item.selections).length - 1 ? ', ' : ''}
                                      </span>
                                    );
                                  }
                                })}
                              </div>
                            )}
                          </div>
                          <p className="font-semibold text-gray-800 text-sm">
                            ${itemTotal.toLocaleString('es-CL')}
                          </p>
                        </div>
                        <div className="flex gap-2">
                          <button
                            onClick={() => handleCustomizeProduct(item, index)}
                            className="flex-1 bg-white hover:bg-gray-50 border border-gray-300 hover:border-blue-500 text-gray-700 text-xs py-1 px-2 rounded transition-all"
                          >
                            Personalizar
                          </button>
                          <button
                            onClick={() => {
                              setCart(prevCart => prevCart.filter((_, i) => i !== index));
                            }}
                            className="flex-1 bg-white hover:bg-gray-50 border border-gray-300 hover:border-red-500 text-gray-700 text-xs py-1 px-2 rounded transition-all"
                          >
                            Eliminar
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
                <div className="space-y-2 border-t pt-2">
                  <div className="flex justify-between items-center">
                    <span className="text-gray-600">Subtotal:</span>
                    <span className="font-semibold">${cartSubtotal.toLocaleString('es-CL')}</span>
                  </div>
                  {customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 && (
                    <>
                      <div className="flex justify-between items-center">
                        <span className="text-gray-600 flex items-center gap-1">
                          <Bike size={16} className="text-red-500" /> Delivery:
                        </span>
                        <span className={customerInfo.deliveryDiscount ? "font-semibold line-through text-gray-400" : "font-semibold"}>
                          ${parseInt(nearbyTrucks[0].tarifa_delivery || 0).toLocaleString('es-CL')}
                        </span>
                      </div>
                      {customerInfo.deliveryDiscount && (
                        <div className="flex justify-between items-center">
                          <span className="text-green-600 text-sm">Descuento Delivery (40%):</span>
                          <span className="font-semibold text-green-600">${Math.round(parseInt(nearbyTrucks[0].tarifa_delivery || 0) * 0.6).toLocaleString('es-CL')}</span>
                        </div>
                      )}
                    </>
                  )}
                  {customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount && (
                    <div className="flex justify-between items-center">
                      <span className="text-green-600 text-sm">Descuento R11 (10%):</span>
                      <span className="font-semibold text-green-600">-${Math.round(cartSubtotal * 0.1).toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  {customerInfo.birthdayDiscount && cart.some(item => item.id === 9) && (
                    <div className="flex justify-between items-center">
                      <span className="text-green-600 text-sm">🎂 Descuento Cumpleaños:</span>
                      <span className="font-semibold text-green-600">-${cart.find(item => item.id === 9).price.toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  {discountCode === 'PIZZA11' && cart.some(item => item.id === 231) && (
                    <div className="flex justify-between items-center bg-yellow-100 px-2 py-1 rounded">
                      <span className="text-orange-600 text-sm font-bold">🍕 Descuento Pizza11 (20%):</span>
                      <span className="font-semibold text-orange-600">-${Math.round(cart.find(item => item.id === 231).price * 0.2).toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  <div className="flex justify-between items-center text-lg font-bold border-t pt-2">
                    <span>Total:</span>
                    <span className="text-orange-500">${(() => {
                      const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
                      const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.6) : baseDeliveryFee;
                      const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
                      const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
                      const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
                      return (cartSubtotal + deliveryFee - pickupDiscountAmount - birthdayDiscountAmount - pizzaDiscountAmount).toLocaleString('es-CL');
                    })()}</span>
                  </div>
                </div>
              </div>
              
              <div>
                <h4 className="text-sm font-bold bg-yellow-400 text-black px-3 py-2 rounded-lg mb-3">Finaliza Eligiendo Método de Pago</h4>
                <div className="grid grid-cols-4 gap-2 mb-3">
                  <button
                    onClick={() => {
                      if (!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)) return;
                      setShowCashModal(true);
                      setCashAmount('');
                      setCashStep('input');
                    }}
                    disabled={!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)}
                    className="disabled:bg-white disabled:text-gray-700 border-2 disabled:cursor-not-allowed font-medium py-2 px-1 rounded-lg transition-all text-xs bg-green-500 hover:bg-green-600 text-white border-green-500 flex flex-col items-center justify-center gap-1"
                  >
                    <Banknote size={16} />
                    <span>Efectivo</span>
                  </button>
                  <button
                    onClick={async () => {
                      if (!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)) return;
                      const confirmed = window.confirm('Has seleccionado TARJETA como método de pago. ¿Continuar?');
                      if (!confirmed) return;
                      try {
                        console.log('💳 Procesando pago con TARJETA...');
                        const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
                        const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.6) : baseDeliveryFee;
                        const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
                        const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
                        const finalTotal = cartSubtotal + deliveryFee - pickupDiscountAmount - birthdayDiscountAmount;
                        
                        const orderData = {
                          amount: finalTotal,
                          customer_name: customerInfo.name,
                          customer_phone: customerInfo.phone,
                          customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
                          user_id: user?.id || null,
                          cart_items: cart,
                          delivery_fee: deliveryFee,
                          customer_notes: customerInfo.customerNotes || null,
                          delivery_type: customerInfo.deliveryType,
                          delivery_address: customerInfo.address || null,
                          payment_method: 'card'
                        };
                        console.log('📤 Enviando orden:', orderData);
                        const response = await fetch('/api/create_order.php', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify(orderData)
                        });
                        const result = await response.json();
                        console.log('📥 Respuesta:', result);
                        if (result.success) {
                          localStorage.removeItem('ruta11_cart');
                          localStorage.removeItem('ruta11_cart_total');
                          console.log('✅ Redirigiendo a /card-pending?order=' + result.order_id);
                          window.location.href = '/card-pending?order=' + result.order_id;
                        } else {
                          alert('❌ Error al crear orden: ' + (result.error || 'Error desconocido'));
                        }
                      } catch (error) {
                        console.error('❌ Error procesando pago con tarjeta:', error);
                        alert('❌ Error al procesar el pago: ' + error.message);
                      }
                    }}
                    disabled={!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)}
                    className="disabled:bg-white disabled:text-gray-700 border-2 disabled:cursor-not-allowed font-medium py-2 px-1 rounded-lg transition-all text-xs bg-purple-500 hover:bg-purple-600 text-white border-purple-500 flex flex-col items-center justify-center gap-1"
                  >
                    <CreditCard size={16} />
                    <span>Tarjeta</span>
                  </button>
                  <button
                    onClick={async () => {
                      if (!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)) return;
                      const confirmed = window.confirm('Has seleccionado TRANSFERENCIA como método de pago. ¿Continuar?');
                      if (!confirmed) return;
                      try {
                        console.log('📱 Procesando pago con TRANSFERENCIA...');
                        const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
                        const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.6) : baseDeliveryFee;
                        const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
                        const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
                        const finalTotal = cartSubtotal + deliveryFee - pickupDiscountAmount - birthdayDiscountAmount;
                        
                        const orderData = {
                          amount: finalTotal,
                          customer_name: customerInfo.name,
                          customer_phone: customerInfo.phone,
                          customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
                          user_id: user?.id || null,
                          cart_items: cart,
                          delivery_fee: deliveryFee,
                          customer_notes: customerInfo.customerNotes || null,
                          delivery_type: customerInfo.deliveryType,
                          delivery_address: customerInfo.address || null,
                          payment_method: 'transfer'
                        };
                        console.log('📤 Enviando orden:', orderData);
                        const response = await fetch('/api/create_order.php', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify(orderData)
                        });
                        const result = await response.json();
                        console.log('📥 Respuesta:', result);
                        if (result.success) {
                          localStorage.removeItem('ruta11_cart');
                          localStorage.removeItem('ruta11_cart_total');
                          console.log('✅ Redirigiendo a /transfer-pending?order=' + result.order_id);
                          window.location.href = '/transfer-pending?order=' + result.order_id;
                        } else {
                          alert('❌ Error al crear orden: ' + (result.error || 'Error desconocido'));
                        }
                      } catch (error) {
                        console.error('❌ Error procesando pago con transferencia:', error);
                        alert('❌ Error al procesar el pago: ' + error.message);
                      }
                    }}
                    disabled={!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)}
                    className="disabled:bg-white disabled:text-gray-700 border-2 disabled:cursor-not-allowed font-medium py-2 px-1 rounded-lg transition-all text-xs bg-blue-500 hover:bg-blue-600 text-white border-blue-500 flex flex-col items-center justify-center gap-1"
                  >
                    <Smartphone size={16} />
                    <span>Transfer.</span>
                  </button>
                  <button
                    onClick={async () => {
                      if (!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)) return;
                      const confirmed = window.confirm('Has seleccionado PEDIDOSYA como método de pago. ¿Continuar?');
                      if (!confirmed) return;
                      try {
                        console.log('🚴 Procesando pago con PEDIDOSYA...');
                        const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
                        const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.6) : baseDeliveryFee;
                        const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
                        const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
                        const finalTotal = cartSubtotal + deliveryFee - pickupDiscountAmount - birthdayDiscountAmount;
                        
                        const orderData = {
                          amount: finalTotal,
                          customer_name: customerInfo.name,
                          customer_phone: customerInfo.phone,
                          customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
                          user_id: user?.id || null,
                          cart_items: cart,
                          delivery_fee: deliveryFee,
                          customer_notes: customerInfo.customerNotes || null,
                          delivery_type: customerInfo.deliveryType,
                          delivery_address: customerInfo.address || null,
                          payment_method: 'pedidosya'
                        };
                        console.log('📤 Enviando orden:', orderData);
                        const response = await fetch('/api/create_order.php', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify(orderData)
                        });
                        const result = await response.json();
                        console.log('📥 Respuesta:', result);
                        if (result.success) {
                          localStorage.removeItem('ruta11_cart');
                          localStorage.removeItem('ruta11_cart_total');
                          console.log('✅ Redirigiendo a /pedidosya-pending?order=' + result.order_id);
                          window.location.href = '/pedidosya-pending?order=' + result.order_id;
                        } else {
                          alert('❌ Error al crear orden: ' + (result.error || 'Error desconocido'));
                        }
                      } catch (error) {
                        console.error('❌ Error procesando pago con PedidosYA:', error);
                        alert('❌ Error al procesar el pago: ' + error.message);
                      }
                    }}
                    disabled={!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)}
                    className="disabled:bg-white disabled:text-gray-700 border-2 disabled:cursor-not-allowed font-medium py-2 px-1 rounded-lg transition-all text-xs bg-orange-500 hover:bg-orange-600 text-white border-orange-500 flex flex-col items-center justify-center gap-1"
                  >
                    <Bike size={16} />
                    <span>PedidosYA</span>
                  </button>
                </div>
                
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* Payment Modal with TUU Integration */}
      {showPayment && currentOrder && (
        <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in">
          <div className="bg-white w-full max-w-2xl mx-4 rounded-2xl flex flex-col animate-slide-up max-h-[90vh] overflow-hidden" onClick={(e) => e.stopPropagation()}>
            <div className="p-4 border-b flex justify-between items-center">
              <h2 className="text-xl font-bold text-gray-800">Pagar Pedido #{currentOrder.order_number}</h2>
              <button onClick={() => setShowPayment(false)} className="p-1 text-gray-400 hover:text-gray-600">
                <X size={24} />
              </button>
            </div>
            
            <div className="flex-grow overflow-y-auto p-4">
              <TUUPaymentIntegration
                cartItems={cart}
                total={cartTotal}
                customerInfo={{
                  name: customerInfo.name || user?.nombre || '',
                  phone: customerInfo.phone || '',
                  email: customerInfo.email || user?.email || '',
                  table: 'Delivery'
                }}
                deliveryFee={deliveryFee}
                onPaymentSuccess={handlePaymentSuccess}
              />
            </div>
          </div>
        </div>
      )}
      <OnboardingModal 
        isOpen={showOnboarding}
        onComplete={() => setShowOnboarding(false)}
      />
      <ReviewsModal 
        product={reviewsModalProduct}
        isOpen={!!reviewsModalProduct}
        onClose={() => {
          console.log('Closing reviews modal');
          setReviewsModalProduct(null);
        }}
      />
      <ShareProductModal 
        product={shareModalProduct}
        isOpen={!!shareModalProduct}
        onClose={() => setShareModalProduct(null)}
      />
      <ComboModal 
        combo={comboModalProduct}
        isOpen={!!comboModalProduct}
        onClose={() => setComboModalProduct(null)}
        quantity={1}
        onAddToCart={(comboWithSelections) => {
          vibrate(50);
          setCart(prevCart => [...prevCart, { 
            ...comboWithSelections, 
            quantity: 1,
            cartItemId: `combo-${Date.now()}-${Math.random()}`
          }]);
          setComboModalProduct(null);
        }}
      />
      {user && <OrderNotifications userId={user.id} audioEnabled={audioEnabled} onNotificationsUpdate={(notifs, unread) => {
        setNotifications(notifs);
        setUnreadCount(unread);
      }} />}
      <OrdersListener onOrdersUpdate={(count) => setActiveOrdersCount(count)} />
      <ChecklistsListener onChecklistsUpdate={(count) => setActiveChecklistsCount(count)} />
      
      {showCashModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            {cashStep === 'input' ? (
              <>
                <h3 className="text-xl font-bold text-gray-800 mb-4">💵 Pago en Efectivo</h3>
                
                <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                  <p className="text-sm text-gray-600 mb-1">Total a pagar:</p>
                  <p className="text-3xl font-bold text-orange-600">${(() => {
                    const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
                    const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.6) : baseDeliveryFee;
                    const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
                    const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
                    const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
                    return (cartSubtotal + deliveryFee - pickupDiscountAmount - birthdayDiscountAmount - pizzaDiscountAmount).toLocaleString('es-CL');
                  })()}</p>
                </div>

                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    ¿Con cuánto paga el cliente?
                  </label>
                  <div className="relative">
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-lg font-semibold">$</span>
                    <input
                      type="text"
                      value={cashAmount}
                      onChange={handleCashInput}
                      onKeyDown={(e) => e.key === 'Enter' && handleContinueCash()}
                      className="w-full pl-8 pr-3 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-lg font-semibold"
                      placeholder="0"
                      autoFocus
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-2 mb-4">
                  <button
                    onClick={setExactAmount}
                    className="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-3 rounded-lg transition-colors text-sm"
                  >
                    Monto Exacto
                  </button>
                  <button
                    onClick={() => setQuickAmount(5000)}
                    className="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-3 rounded-lg transition-colors text-sm"
                  >
                    $5.000
                  </button>
                  <button
                    onClick={() => setQuickAmount(10000)}
                    className="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-3 rounded-lg transition-colors text-sm"
                  >
                    $10.000
                  </button>
                  <button
                    onClick={() => setQuickAmount(20000)}
                    className="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-3 rounded-lg transition-colors text-sm"
                  >
                    $20.000
                  </button>
                </div>

                <div className="flex gap-3">
                  <button
                    onClick={closeCashModal}
                    className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-4 rounded-lg transition-colors"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={handleContinueCash}
                    className="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition-colors"
                  >
                    Continuar
                  </button>
                </div>
              </>
            ) : (
              <>
                <h3 className="text-xl font-bold text-gray-800 mb-4">💰 Confirmar Vuelto</h3>
                
                <div className="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-sm text-gray-600">Total:</span>
                    <span className="text-lg font-semibold">${(() => {
                      const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
                      const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.6) : baseDeliveryFee;
                      const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
                      const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
                      const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
                      return (cartSubtotal + deliveryFee - pickupDiscountAmount - birthdayDiscountAmount - pizzaDiscountAmount).toLocaleString('es-CL');
                    })()}</span>
                  </div>
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-sm text-gray-600">Paga con:</span>
                    <span className="text-lg font-semibold">${parseInt(cashAmount.replace(/\./g, '')).toLocaleString('es-CL')}</span>
                  </div>
                  <div className="border-t border-green-300 pt-2 mt-2">
                    <div className="flex justify-between items-center">
                      <span className="text-base font-semibold text-gray-700">Vuelto a entregar:</span>
                      <span className="text-2xl font-bold text-green-600">
                        ${(() => {
                          const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
                          const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.6) : baseDeliveryFee;
                          const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
                          const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
                          const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
                          const finalTotal = cartSubtotal + deliveryFee - pickupDiscountAmount - birthdayDiscountAmount - pizzaDiscountAmount;
                          return (parseInt(cashAmount.replace(/\./g, '')) - finalTotal).toLocaleString('es-CL');
                        })()}
                      </span>
                    </div>
                  </div>
                </div>

                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                  <p className="text-sm text-yellow-800 font-medium text-center">
                    ⚠️ Confirma que entregarás el vuelto correcto
                  </p>
                </div>

                <div className="flex gap-3">
                  <button
                    onClick={() => setCashStep('input')}
                    disabled={isProcessing}
                    className="flex-1 bg-gray-300 hover:bg-gray-400 disabled:bg-gray-200 text-gray-800 font-bold py-3 px-4 rounded-lg transition-colors"
                  >
                    Volver
                  </button>
                  <button
                    onClick={processCashOrder}
                    disabled={isProcessing}
                    className="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded-lg transition-colors"
                  >
                    {isProcessing ? '⏳ Procesando...' : '✓ Confirmar Vuelto'}
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      )}

      <PaymentPendingModal
        isOpen={!!pendingPaymentModal}
        onClose={() => {
          setPendingPaymentModal(null);
          setCart([]);
          setCustomerInfo({ name: '', phone: '', email: '', address: '', deliveryType: 'pickup', pickupTime: '', notes: '' });
        }}
        paymentType={pendingPaymentModal?.paymentType}
        orderData={pendingPaymentModal?.orderData}
      />

      {/* Modal Estado del Local */}
      {showStatusModal && truckStatus && (
        <div className="fixed inset-0 bg-white z-50 flex flex-col">
          <div className="bg-gradient-to-r from-orange-500 to-red-500 text-white p-6 shadow-lg">
            <div className="flex justify-between items-center">
              <div>
                <h2 className="text-2xl font-bold">Configuración del Local</h2>
                <p className="text-sm text-white/80 mt-1">Gestiona el estado y configuración de tu food truck</p>
              </div>
              <button onClick={() => { setShowStatusModal(false); setEditMode(false); }} className="p-2 hover:bg-white/20 rounded-full transition-colors">
                <X size={24} />
              </button>
            </div>
          </div>
          
          <div className="flex-1 overflow-y-auto p-6 bg-gray-50">
            <div className="max-w-3xl mx-auto space-y-6">
              {/* Información del Local */}
              <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <div className="flex items-center justify-between mb-6">
                  <div className="flex items-center gap-4">
                    <div className="bg-orange-100 p-4 rounded-full">
                      <Truck size={32} className="text-orange-600" />
                    </div>
                    <div>
                      <h3 className="text-2xl font-bold text-gray-800">{truckStatus.nombre}</h3>
                      <p className="text-gray-600">{truckStatus.descripcion}</p>
                    </div>
                  </div>
                  <button
                    onClick={() => {
                      setEditMode(!editMode);
                      if (!editMode) setTempTruckData({...truckStatus});
                    }}
                    className="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors flex items-center gap-2"
                  >
                    {editMode ? <X size={18} /> : <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>}
                    {editMode ? 'Cancelar' : 'Editar'}
                  </button>
                </div>
                
                {!editMode ? (
                  <div className="space-y-3 text-sm">
                    <div className="flex items-center gap-2 text-gray-700">
                      <MapPin size={16} className="text-gray-400" />
                      <span>{truckStatus.direccion}</span>
                    </div>
                    <div className="flex items-center gap-2 text-gray-700">
                      <Clock size={16} className="text-gray-400" />
                      <span>Horario: {truckStatus.horario_inicio.slice(0,5)} - {truckStatus.horario_fin.slice(0,5)}</span>
                    </div>
                    <div className="flex items-center gap-2 text-gray-700">
                      <TruckIcon size={16} className="text-gray-400" />
                      <span>Tarifa delivery: ${parseInt(truckStatus.tarifa_delivery).toLocaleString('es-CL')}</span>
                    </div>
                  </div>
                ) : (
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">Dirección</label>
                      <div className="flex gap-2">
                        <input
                          type="text"
                          value={tempTruckData?.direccion || ''}
                          onChange={(e) => setTempTruckData({...tempTruckData, direccion: e.target.value})}
                          className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                        <button
                          onClick={() => {
                            if (navigator.geolocation) {
                              navigator.geolocation.getCurrentPosition(async (pos) => {
                                const { latitude, longitude } = pos.coords;
                                try {
                                  const formData = new FormData();
                                  formData.append('lat', latitude);
                                  formData.append('lng', longitude);
                                  const res = await fetch('/api/location/geocode.php', { method: 'POST', body: formData });
                                  const data = await res.json();
                                  if (data.success) {
                                    setTempTruckData({
                                      ...tempTruckData,
                                      direccion: data.formatted_address,
                                      latitud: latitude,
                                      longitud: longitude
                                    });
                                    vibrate(50);
                                  }
                                } catch (error) {
                                  console.error('Error:', error);
                                }
                              });
                            }
                          }}
                          className="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition-colors flex items-center gap-2"
                          title="Usar mi ubicación actual"
                        >
                          <Navigation size={18} />
                          GPS
                        </button>
                      </div>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Hora Inicio</label>
                        <input
                          type="time"
                          value={tempTruckData?.horario_inicio?.slice(0,5) || ''}
                          onChange={(e) => setTempTruckData({...tempTruckData, horario_inicio: e.target.value + ':00'})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Hora Fin</label>
                        <input
                          type="time"
                          value={tempTruckData?.horario_fin?.slice(0,5) || ''}
                          onChange={(e) => setTempTruckData({...tempTruckData, horario_fin: e.target.value + ':00'})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                      </div>
                    </div>
                    
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">Tarifa Delivery ($)</label>
                      <input
                        type="number"
                        value={tempTruckData?.tarifa_delivery || ''}
                        onChange={(e) => setTempTruckData({...tempTruckData, tarifa_delivery: e.target.value})}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                      />
                    </div>
                    
                    <button
                      onClick={async () => {
                        try {
                          const res = await fetch('/api/update_truck_config.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ truckId: 4, ...tempTruckData })
                          });
                          const data = await res.json();
                          if (data.success) {
                            setTruckStatus(tempTruckData);
                            setEditMode(false);
                            vibrate(50);
                            alert('✅ Configuración actualizada exitosamente');
                          }
                        } catch (error) {
                          console.error('Error:', error);
                          alert('❌ Error al actualizar');
                        }
                      }}
                      className="w-full px-4 py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-bold transition-colors"
                    >
                      Guardar Cambios
                    </button>
                  </div>
                )}
              </div>

              {/* Switch de Estado con Swipe */}
              <div className="bg-white rounded-xl shadow-lg p-4 sm:p-6 border border-gray-200">
                <div className="text-center mb-4 sm:mb-6">
                  <h4 className="text-lg sm:text-xl font-bold text-gray-800 mb-1 sm:mb-2">Estado Actual</h4>
                  <p className="text-xs sm:text-sm text-gray-600">Desliza para cambiar el estado del local</p>
                </div>
                
                <SwipeToggle
                  isActive={truckStatus.activo === 1}
                  onChange={async (newStatus) => {
                    setIsUpdatingStatus(true);
                    try {
                      const res = await fetch('/api/update_truck_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ truckId: 4, activo: newStatus ? 1 : 0 })
                      });
                      const data = await res.json();
                      if (data.success) {
                        setTruckStatus({...truckStatus, activo: newStatus ? 1 : 0});
                        vibrate(50);
                      }
                    } catch (error) {
                      console.error('Error:', error);
                    }
                    setIsUpdatingStatus(false);
                  }}
                  disabled={isUpdatingStatus}
                  label="Desliza para cambiar el estado"
                />
              </div>

              {/* Horarios por día */}
              <div className="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <div className="flex items-center justify-between mb-4">
                  <div>
                    <h4 className="text-xl font-bold text-gray-800">Horarios por Día</h4>
                    <p className="text-sm text-gray-600 mt-1">Configura horarios específicos para cada día</p>
                  </div>
                  <button
                    onClick={() => setEditingSchedules(!editingSchedules)}
                    className="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg font-medium transition-colors flex items-center gap-2"
                  >
                    {editingSchedules ? <X size={18} /> : <Clock size={18} />}
                    {editingSchedules ? 'Cancelar' : 'Editar'}
                  </button>
                </div>
                
                <div className="space-y-2">
                  {schedules.map((schedule) => {
                    const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                    const dayName = dayNames[schedule.day_of_week];
                    const isToday = schedule.day_of_week === currentDayOfWeek;
                    
                    return (
                      <div key={schedule.day_of_week} className={`p-3 rounded-lg border-2 transition-all ${
                        isToday ? 'border-orange-500 bg-orange-50' : 'border-gray-200 bg-gray-50'
                      }`}>
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-3">
                            <span className={`font-semibold ${
                              isToday ? 'text-orange-600' : 'text-gray-800'
                            }`}>
                              {dayName}
                              {isToday && <span className="ml-2 text-xs bg-orange-500 text-white px-2 py-0.5 rounded-full">HOY</span>}
                            </span>
                          </div>
                          
                          {!editingSchedules ? (
                            <div className="flex items-center gap-2 text-sm text-gray-700">
                              <Clock size={14} />
                              <span>{schedule.horario_inicio.slice(0,5)} - {schedule.horario_fin.slice(0,5)}</span>
                            </div>
                          ) : (
                            <div className="flex items-center gap-2">
                              <input
                                type="time"
                                value={schedule.horario_inicio.slice(0,5)}
                                onChange={(e) => {
                                  const newSchedules = [...schedules];
                                  newSchedules[schedule.day_of_week].horario_inicio = e.target.value + ':00';
                                  setSchedules(newSchedules);
                                }}
                                className="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
                              />
                              <span className="text-gray-500">-</span>
                              <input
                                type="time"
                                value={schedule.horario_fin.slice(0,5)}
                                onChange={(e) => {
                                  const newSchedules = [...schedules];
                                  newSchedules[schedule.day_of_week].horario_fin = e.target.value + ':00';
                                  setSchedules(newSchedules);
                                }}
                                className="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
                              />
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
                
                {editingSchedules && (
                  <button
                    onClick={async () => {
                      try {
                        for (const schedule of schedules) {
                          await fetch('/api/update_truck_schedule.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                              truckId: 4,
                              dayOfWeek: schedule.day_of_week,
                              horarioInicio: schedule.horario_inicio,
                              horarioFin: schedule.horario_fin
                            })
                          });
                        }
                        setEditingSchedules(false);
                        vibrate(50);
                        alert('✅ Horarios actualizados exitosamente');
                      } catch (error) {
                        console.error('Error:', error);
                        alert('❌ Error al actualizar horarios');
                      }
                    }}
                    className="w-full mt-4 px-4 py-3 bg-purple-500 hover:bg-purple-600 text-white rounded-lg font-bold transition-colors"
                  >
                    Guardar Horarios
                  </button>
                )}
              </div>

              {/* Control de Categorías */}
              <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <button
                  onClick={() => setCategoriesExpanded(!categoriesExpanded)}
                  className="w-full p-6 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <Package size={24} className="text-orange-600" />
                    <div className="text-left">
                      <h4 className="text-xl font-bold text-gray-800">Control de Categorías</h4>
                      <p className="text-sm text-gray-600">Mostrar/ocultar categorías del menú</p>
                    </div>
                  </div>
                  <ChevronDown size={24} className={`text-gray-400 transition-transform ${
                    categoriesExpanded ? 'rotate-180' : ''
                  }`} />
                </button>
                
                {categoriesExpanded && (
                  <div className="p-6 pt-0 space-y-3">
                    {categories.map(cat => (
                      <div key={cat.id} className="bg-gray-50 rounded-lg p-4 border-2 border-gray-200">
                        <div className="flex items-center justify-between mb-3">
                          <div className="flex items-center gap-3">
                            <label className="flex items-center gap-2 cursor-pointer">
                              <input
                                type="checkbox"
                                checked={cat.is_active}
                                onChange={async (e) => {
                                  const newCategories = categories.map(c => 
                                    c.id === cat.id ? {...c, is_active: e.target.checked} : c
                                  );
                                  setCategories(newCategories);
                                  
                                  try {
                                    await fetch('/api/update_categories.php', {
                                      method: 'POST',
                                      headers: { 'Content-Type': 'application/json' },
                                      body: JSON.stringify({ categories: newCategories })
                                    });
                                    vibrate(30);
                                  } catch (error) {
                                    console.error('Error:', error);
                                  }
                                }}
                                className="w-5 h-5 cursor-pointer"
                              />
                              <span className="font-semibold text-gray-800">{cat.name}</span>
                            </label>
                            <span className={`px-2 py-1 rounded text-xs font-bold ${
                              cat.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                            }`}>
                              {cat.is_active ? 'Visible' : 'Oculta'}
                            </span>
                          </div>
                          <span className="text-sm text-gray-500">{cat.product_count || 0} productos</span>
                        </div>
                        
                        {cat.products && cat.products.length > 0 && (
                          <div className="flex gap-2 flex-wrap pt-3 border-t border-gray-200">
                            {cat.products.slice(0, 6).map(prod => (
                              <div key={prod.id} className="flex items-center gap-2 bg-white px-2 py-1 rounded border border-gray-200">
                                {prod.image_url && (
                                  <img src={prod.image_url} className="w-6 h-6 object-cover rounded" />
                                )}
                                <span className="text-xs text-gray-600">{prod.name}</span>
                              </div>
                            ))}
                            {cat.products.length > 6 && (
                              <span className="text-xs text-gray-500 px-2 py-1">+{cat.products.length - 6} más</span>
                            )}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Información adicional */}
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p className="text-sm text-blue-800">
                  <strong>ℹ️ Nota:</strong> Cuando el local está cerrado, los clientes no podrán realizar pedidos desde la app.
                </p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal QR Compartir App */}
      {showQRModal && (
        <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={() => setShowQRModal(false)}>
          <div className="bg-white w-full max-w-sm mx-4 rounded-2xl flex flex-col animate-slide-up p-6" onClick={(e) => e.stopPropagation()}>
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold text-gray-800">Compartir App</h2>
              <button onClick={() => setShowQRModal(false)} className="p-1 text-gray-400 hover:text-gray-600">
                <X size={24} />
              </button>
            </div>
            
            <div className="text-center">
              <p className="text-gray-600 mb-4">Escanea el código QR para acceder a la app</p>
              <div className="bg-white p-4 rounded-lg inline-block">
                <img 
                  src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=https://app.laruta11.cl" 
                  alt="QR Code" 
                  className="w-48 h-48"
                />
              </div>
              <p className="text-sm text-gray-500 mt-4">app.laruta11.cl</p>
            </div>
          </div>
        </div>
      )}

      <style>{`
        @keyframes fade-in {
          from { opacity: 0; transform: scale(0.95); }
          to { opacity: 1; transform: scale(1); }
        }
        @keyframes slide-up {
          from { transform: translateY(100%); }
          to { transform: translateY(0); }
        }
        @keyframes shimmer-sweep {
          0% { transform: translateX(-100%) skewX(-15deg); }
          100% { transform: translateX(200%) skewX(-15deg); }
        }
        @keyframes heartFloat {
          0% { transform: translate(-50%, -50%) scale(0.8) rotate(0deg); opacity: 1; }
          10% { transform: translate(-50%, -50%) scale(1.2) rotate(-5deg); opacity: 1; }
          20% { transform: translate(-50%, -60%) scale(1) rotate(5deg); opacity: 1; }
          40% { transform: translate(-50%, -80%) scale(0.9) rotate(-3deg); opacity: 0.8; }
          60% { transform: translate(-50%, -120%) scale(0.7) rotate(2deg); opacity: 0.6; }
          80% { transform: translate(-50%, -160%) scale(0.5) rotate(-1deg); opacity: 0.3; }
          100% { transform: translate(-50%, -200%) scale(0.2) rotate(0deg); opacity: 0; }
        }

        .animate-fade-in { animation: fade-in 0.2s ease-out forwards; }
        .animate-slide-up { animation: slide-up 0.3s ease-out forwards; }
        .animate-shimmer-sweep { animation: shimmer-sweep 2s ease-in-out infinite; }
        .animate-heart-float { animation: heartFloat 2s ease-out forwards; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #aaa; }
        
        nav > div > div::-webkit-scrollbar { display: none; }
        
        html, body { background: #ffffff !important; }
      `}</style>
    </div>
  );
}
