import React, { useState, useMemo, useEffect, useRef } from 'react';
import {
  PlusCircle, X, Star, MinusCircle, ZoomIn,
  Award, ChefHat, GlassWater, CupSoda, Droplets,
  Eye, Heart, MessageSquare, Calendar, Bike, Caravan, ChevronDown, ChevronUp, Package,
  Truck, TruckIcon, Navigation, MapPin, Clock, CheckCircle2, XCircle, CreditCard, Banknote, Smartphone, Percent, Tag, Pizza, Settings
} from 'lucide-react';
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
import ShareProductModal from './modals/ShareProductModal.jsx';
// Animated Icons
import { BellIcon } from './icons/BellIcon.jsx';
import { ShoppingCartIcon } from './icons/ShoppingCartIcon.jsx';
import { SearchIcon } from './icons/SearchIcon.jsx';
import { UserIcon } from './icons/UserIcon.jsx';
import { ShareIcon } from './icons/ShareIcon.jsx';

import ComboModal from './modals/ComboModal.jsx';
import PaymentPendingModal from './modals/PaymentPendingModal.jsx';
import SwipeToggle from './SwipeToggle.jsx';
import useDoubleTap from '../hooks/useDoubleTap.js';
import { vibrate, playNotificationSound, createConfetti, initAudio, playComandaSound, playAddSound, playRemoveSound, playSuccessSound, playCajaSound } from '../utils/effects.js';
import { validateCheckoutForm, getFormDisabledState } from '../utils/validation.js';
import AddressAutocomplete from './AddressAutocomplete.jsx';

// Lazy-loaded inline panels
const MermaPanel = React.lazy(() => import('./MermaPanel.jsx'));
const ArqueoPanel = React.lazy(() => import('./ArqueoPanel.jsx'));

// ============================================
// CAJA3: UBICACIÓN DESACTIVADA
// Esta app NO solicita ubicación del usuario
// ============================================

// Datos del menú - se cargarán dinámicamente desde MySQL
// Datos del menú - se cargarán dinámicamente desde MySQL
var menuData = {
  la_ruta_11: { tomahawks: [] },
  churrascos: { carne: [], pollo: [], vegetariano: [] },
  hamburguesas: { clasicas: [], especiales: [] },
  completos: { tradicionales: [], 'al vapor': [], papas: [] },
  papas_y_snacks: { papas: [], empanadas: [], jugos: [], bebidas: [], salsas: [] },
  Combos: { hamburguesas: [], sandwiches: [], completos: [] }
};

var categoryIcons = {
  hamburguesas: <PlusCircle style={{ width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)' }} />,
  hamburguesas_100g: <PlusCircle style={{ width: 'clamp(13.2px, 3.36vw, 16.8px)', height: 'clamp(13.2px, 3.36vw, 16.8px)' }} />,
  churrascos: <PlusCircle style={{ width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)' }} />,
  completos: <PlusCircle style={{ width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)' }} />,
  papas: <PlusCircle style={{ width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)' }} />,
  pizzas: <Pizza style={{ width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)' }} />,
  bebidas: <CupSoda style={{ width: 'clamp(19.2px, 4.8vw, 24px)', height: 'clamp(19.2px, 4.8vw, 24px)' }} />,
  combos: (
    <div style={{ display: 'flex', alignItems: 'center', gap: '2px' }}>
      <PlusCircle style={{ width: 'clamp(12px, 3vw, 16.8px)', height: 'clamp(12px, 3vw, 16.8px)' }} />
      <CupSoda style={{ width: 'clamp(12px, 3vw, 16.8px)', height: 'clamp(12px, 3vw, 16.8px)' }} />
    </div>
  ),
  Combos: (
    <div style={{ display: 'flex', alignItems: 'center', gap: '2px' }}>
      <PlusCircle style={{ width: 'clamp(12px, 3vw, 16.8px)', height: 'clamp(12px, 3vw, 16.8px)' }} />
      <CupSoda style={{ width: 'clamp(12px, 3vw, 16.8px)', height: 'clamp(12px, 3vw, 16.8px)' }} />
    </div>
  )
};

var categoryColors = {
  hamburguesas: '#FFD700',      // Amarillo Canario
  hamburguesas_100g: '#FFBF00', // Ámbar
  churrascos: '#FF7F50',        // Coral Vivo
  completos: '#FF00FF',         // Magenta
  la_ruta_11: '#E0115F',        // Rojo Cereza
  papas: '#9ACD32',             // Verde Manzana
  papas_y_snacks: '#9ACD32',    // Verde Manzana
  pizzas: '#FF69B4',            // Fucsia
  bebidas: '#40E0D0',           // Azul Turquesa
  Combos: '#FF8C00',            // Mandarina
  personalizar: '#BA55D3',      // Morado Orquídea
  extras: '#00CED1'             // Azul Cian
};

var CATEGORY_ID_MAP = {
  1: 'la_ruta_11',
  2: 'churrascos',
  3: 'hamburguesas',
  4: 'completos',
  5: 'papas_y_snacks',
  6: 'personalizar',
  7: 'extras',
  8: 'Combos',
  12: 'papas',
};

var SUBCATEGORY_ID_MAP = {
  9: 'papas',
  10: 'jugos',
  11: 'bebidas',
  12: 'salsas',
  26: 'empanadas',
  27: 'café',
  28: 'té',
  29: 'personalizar',
  30: 'extras',
  57: 'papas',
  61: 'aguas',
  62: 'latas_350ml',
  63: 'energeticas_473ml',
  64: 'energeticas_250ml',
  65: 'bebidas_1_5l',
};





function ImageFullscreenModal({ product, total, onClose }) {
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
      <img src={product.image} alt={product.name} className="max-w-full max-h-full object-contain" loading="lazy" decoding="async" />
      <div className="absolute bottom-0 left-0 right-0 p-4 bg-black/40 backdrop-blur-sm text-white text-center">
        <h3 className="text-xl font-bold">{product.name}</h3>
        <p className="text-lg text-orange-400 font-semibold">${total.toLocaleString('es-CL')}</p>
      </div>
    </div>
  );
};



function CartModal({ isOpen, onClose, cart, onAddToCart, onRemoveFromCart, cartTotal, onCheckout, onCustomizeProduct, showCheckoutSection, setShowCheckoutSection, customerInfo, setCustomerInfo, user, nearbyTrucks, cartSubtotal, onPaymentMethodSelect }) {
  if (!isOpen) return null;

  const currentDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0
    ? parseInt(nearbyTrucks[0].tarifa_delivery || 0)
    : 0;
  const finalTotal = cartSubtotal + currentDeliveryFee;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-end animate-fade-in" onClick={onClose}>
      <div className="bg-white w-full max-w-2xl max-h-[85vh] rounded-t-2xl flex flex-col animate-slide-up" onClick={(e) => e.stopPropagation()}>
        <div className="border-b flex justify-between items-center" style={{ padding: 'clamp(12px, 3vw, 16px)' }}>
          <h2 className="font-bold text-gray-800" style={{ fontSize: 'clamp(16px, 4vw, 20px)' }}>Tu Pedido</h2>
          <button onClick={onClose} className="p-1 text-gray-500 hover:text-gray-800"><X size={24} /></button>
        </div>
        {cart.length === 0 ? (
          <div className="flex-grow flex flex-col justify-center items-center text-gray-500">
            <ShoppingCartIcon size={48} className="mb-4" />
            <p>Tu carrito está vacío.</p>
          </div>
        ) : (
          <div className="flex-grow overflow-y-auto space-y-3" style={{ padding: 'clamp(12px, 3vw, 16px)' }}>
            {cart.map((item, itemIndex) => {
              const displayPrice = item.price;
              const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
              return (
                <div key={item.cartItemId} className="border rounded-lg p-3 bg-gray-50">
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-3">
                      {item.image ? (
                        <img src={item.image} alt={item.name} className="w-16 h-16 object-cover rounded-md" loading="lazy" decoding="async" />
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
                          <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                          <path d="m15 5 4 4" />
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
            <div className="grid grid-cols-3 gap-3">
              <button
                onClick={() => setCustomerInfo({ ...customerInfo, deliveryType: 'delivery' })}
                className={`p-3 border-2 rounded-lg text-center transition-colors ${customerInfo.deliveryType === 'delivery' ? 'border-orange-500 bg-orange-50' : 'border-gray-300'}`}
              >
                <div className="text-2xl mb-1">🚚</div>
                <div className="text-sm font-semibold">Delivery</div>
              </button>
              <button
                onClick={() => setCustomerInfo({ ...customerInfo, deliveryType: 'pickup' })}
                className={`p-3 border-2 rounded-lg text-center transition-colors ${customerInfo.deliveryType === 'pickup' ? 'border-orange-500 bg-orange-50' : 'border-gray-300'}`}
              >
                <div className="text-2xl mb-1">🏪</div>
                <div className="text-sm font-semibold">Retiro</div>
              </button>
              {/* Venta TV ocultado - ya no se usa */}
            </div>

            {/* Customer Info */}
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">Nombre completo *</label>
              <input
                type="text"
                placeholder="Tu nombre completo"
                value={customerInfo.name || user?.nombre || ''}
                onChange={(e) => setCustomerInfo({ ...customerInfo, name: e.target.value })}
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
                onChange={(e) => setCustomerInfo({ ...customerInfo, phone: e.target.value })}
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
                    <span className="text-xs font-medium text-gray-700">Descuento Delivery (28%)</span>
                  </label>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">Dirección de entrega *</label>
                  {customerInfo.deliveryDiscount ? (
                    <select
                      value={customerInfo.address}
                      onChange={(e) => setCustomerInfo({ ...customerInfo, address: e.target.value })}
                      className="w-full px-3 py-2 border rounded-lg text-sm"
                      required
                    >
                      <option value="">Seleccionar dirección con descuento</option>
                      <option value="Ctel. Oscar Quina 1333">Ctel. Oscar Quina 1333</option>
                      <option value="Ctel. Domeyco 1540">Ctel. Domeyco 1540</option>
                      <option value="Ctel. Av. Santa María 3000">Ctel. Av. Santa María 3000</option>
                    </select>
                  ) : (
                    <AddressAutocomplete
                      value={customerInfo.address}
                      onChange={(address) => { setCustomerInfo({ ...customerInfo, address }); setDynamicDeliveryFee(null); setDeliveryFeeLabel(null); }}
                      placeholder="Ingresa tu dirección..."
                      onDeliveryFee={(data) => { if (data.delivery_fee != null) { setDynamicDeliveryFee(data.delivery_fee); setDeliveryFeeLabel(data.label); setDeliveryDistanceInfo({ km: data.distance_km, min: data.duration_min }); } }}
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
                      onChange={(e) => setCustomerInfo({ ...customerInfo, pickupDiscount: e.target.checked })}
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
                  onChange={(e) => setCustomerInfo({ ...customerInfo, birthdayDiscount: e.target.checked })}
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
                onChange={(e) => setCustomerInfo({ ...customerInfo, pickupTime: e.target.value })}
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
                onChange={(e) => setCustomerInfo({ ...customerInfo, notes: e.target.value })}
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

        <div className="bg-white border-t sticky bottom-0" style={{ padding: 'clamp(12px, 3vw, 16px)' }}>
          {customerInfo.deliveryType === 'delivery' && currentDeliveryFee > 0 && (
            <div className="mb-2 space-y-1.5">
              <div className="flex justify-between items-center text-sm">
                <span className="text-gray-500">Subtotal</span>
                <span className="font-medium text-gray-800">${cartSubtotal.toLocaleString('es-CL')}</span>
              </div>
              <div className="flex justify-between items-center text-sm bg-gray-50 -mx-1 px-2 py-1.5 rounded-lg">
                <span className="text-gray-500 flex items-center gap-1.5">
                  <Truck size={14} className="text-orange-500" />
                  Delivery
                </span>
                <span className="font-semibold text-gray-800">${currentDeliveryFee.toLocaleString('es-CL')}</span>
              </div>
            </div>
          )}
          <div className="flex justify-between items-center mb-3 pt-2 border-t-2 border-orange-200">
            <span className="font-bold text-gray-700" style={{ fontSize: 'clamp(14px, 3.5vw, 18px)' }}>Total</span>
            <span className="font-black text-orange-500" style={{ fontSize: 'clamp(18px, 5vw, 24px)' }}>${finalTotal.toLocaleString('es-CL')}</span>
          </div>

          {!showCheckoutSection ? (
            <button
              disabled={cart.length === 0}
              onClick={() => setShowCheckoutSection(true)}
              className="w-full bg-orange-500 text-white rounded-full font-bold flex items-center justify-center gap-2 hover:bg-orange-600 transition-transform active:scale-95 shadow-lg disabled:bg-gray-300 disabled:cursor-not-allowed"
              style={{ padding: 'clamp(12px, 3vw, 16px)', fontSize: 'clamp(14px, 3.5vw, 18px)' }}
            >
              <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                <path fillRule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clipRule="evenodd" />
              </svg>
              Finalizar Pedido
            </button>
          ) : (
            <button
              onClick={() => setShowCheckoutSection(false)}
              className="w-full bg-gray-500 text-white rounded-full font-bold flex items-center justify-center gap-2 hover:bg-gray-600 transition-colors"
              style={{ padding: 'clamp(10px, 2.5vw, 12px)', fontSize: 'clamp(13px, 3.2vw, 16px)' }}
            >
              Ocultar Checkout
            </button>
          )}
        </div>
      </div>
    </div>
  );
};



function LoginModal({ isOpen, onClose }) {
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
      <div className="bg-white w-full max-w-sm mx-2 sm:m-4 rounded-2xl flex flex-col animate-slide-up text-center" style={{ padding: 'clamp(20px, 5vw, 32px)' }} onClick={(e) => e.stopPropagation()}>
        <button onClick={onClose} className="absolute top-3 right-3 p-1 text-gray-400 hover:text-gray-600"><X size={24} /></button>
        <h2 className="font-bold text-gray-800 mb-2" style={{ fontSize: 'clamp(18px, 5vw, 24px)' }}>Acceso / Registro</h2>
        <p className="text-gray-500 mb-6" style={{ fontSize: 'clamp(13px, 3.5vw, 16px)' }}>Ingresa a tu cuenta para guardar tus pedidos y reseñas.</p>
        <button
          onClick={handleGoogleLogin}
          className="w-full bg-white border border-gray-300 text-gray-700 font-semibold rounded-full flex items-center justify-center gap-2 sm:gap-3 hover:bg-gray-50 transition-colors shadow-sm"
          style={{ padding: 'clamp(10px, 2.5vw, 12px)', fontSize: 'clamp(13px, 3.5vw, 16px)' }}
        >
          <GoogleLogo />
          Continuar con Google
        </button>
      </div>
    </div>
  );
};

function FoodTrucksModal({ isOpen, onClose, trucks, userLocation, deliveryZone }) {
  if (!isOpen) return null;

  const openDirections = (truck) => {
    const url = `https://www.google.com/maps/dir/${userLocation?.latitude},${userLocation?.longitude}/${truck.latitud},${truck.longitud}`;
    window.open(url, '_blank');
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={onClose}>
      <div className="bg-white w-full h-full flex flex-col animate-slide-up" onClick={(e) => e.stopPropagation()}>
        <div className="bg-gradient-to-r from-orange-500 to-orange-600 text-white flex justify-between items-center" style={{ padding: 'clamp(12px, 3vw, 16px)' }}>
          <h2 className="font-bold flex items-center gap-2" style={{ fontSize: 'clamp(16px, 4vw, 20px)' }}>
            <Truck size={22} />
            Food Trucks Cercanos
            {deliveryZone && (
              <span className={`ml-2 text-xs px-2 py-1 rounded-full ${deliveryZone.in_delivery_zone
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
                            {truck.horario_inicio.slice(0, 5)} - {truck.horario_fin.slice(0, 5)}
                          </span>
                        </div>
                        <span className={`px-2.5 py-1.5 rounded-lg text-xs font-medium flex items-center gap-1 ${isOpen ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
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

function NotificationsModal({ isOpen, onClose, onOrdersUpdate, activeOrdersCount }) {
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




function MenuItem({ product, onSelect, onAddToCart, onRemoveFromCart, quantity, type, isLiked, handleLike, setReviewsModalProduct, onShare, isCashier, searchQuery }) {
  const [showImageModal, setShowImageModal] = useState(false);
  const [isActive, setIsActive] = useState(product.active !== 0);
  const [isTogglingStatus, setIsTogglingStatus] = useState(false);

  const highlightName = (name) => {
    const q = (searchQuery || '').trim();
    if (q.length < 2) return name;
    const idx = name.toLowerCase().indexOf(q.toLowerCase());
    if (idx === -1) return name;
    return <>{name.slice(0, idx)}<mark className="bg-yellow-300 rounded-sm px-0.5">{name.slice(idx, idx + q.length)}</mark>{name.slice(idx + q.length)}</>;
  };

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
      if (data.success) setIsActive(newStatus === 1);
    } catch (e) {}
    setIsTogglingStatus(false);
  };

  return (
    <>
      <div className={`flex items-center gap-2 bg-white rounded-lg px-2 py-1.5 transition-all ${!isActive && isCashier ? 'opacity-40' : ''}`}
        style={{ boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>

        {/* Image thumbnail - click to zoom */}
        <div className="w-11 h-11 flex-shrink-0 rounded-lg overflow-hidden cursor-pointer"
          onClick={() => product.image && setShowImageModal(true)}>
          {product.image ? (
            <img src={product.image} alt={product.name}
              className={`w-full h-full object-cover ${!isActive ? 'grayscale' : ''}`} />
          ) : (
            <div className="w-full h-full bg-gray-100 flex items-center justify-center">
              <ChefHat className="text-gray-300" size={16} />
            </div>
          )}
        </div>

        {/* Name + price + controls in compact layout */}
        <div className="flex-1 min-w-0">
          <h3 className={`font-bold text-sm leading-tight truncate ${!isActive ? 'text-gray-400' : 'text-gray-900'}`}>
            {highlightName(product.name)}
          </h3>
          <div className="flex items-center gap-1.5 mt-1">
            {!!product.is_featured && !!product.sale_price ? (
              <>
                <span className="text-red-600 font-black text-[11px]">${product.sale_price.toLocaleString('es-CL')}</span>
                <span className="text-gray-400 text-[9px] line-through">${product.price.toLocaleString('es-CL')}</span>
              </>
            ) : (
              <span className="bg-yellow-400 text-black font-black text-[12px] px-1.5 py-0.5 rounded">${product.price ? product.price.toLocaleString('es-CL') : '0'}</span>
            )}
            {product.category_name === 'Combos' && (
              <span className="flex items-center gap-0.5">
                <GiHamburger size={8} className="text-orange-500" />
                <CupSoda size={8} className="text-orange-500" />
              </span>
            )}
            {isCashier && (
              <button onClick={(e) => { e.stopPropagation(); toggleProductStatus(); }}
                className={`px-1.5 py-0.5 rounded-full text-[7px] font-black uppercase ${isActive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700 ring-1 ring-red-400'}`}
                disabled={isTogglingStatus}>
                {isTogglingStatus ? '..' : (isActive ? 'ON' : 'OFF')}
              </button>
            )}
            {/* Agregar/cantidad inline */}
            <div className="ml-auto flex-shrink-0">
              {quantity === 0 ? (
                <button onClick={(e) => { e.stopPropagation(); onAddToCart(product); }}
                  className="px-3 h-6 bg-green-500 hover:bg-green-600 text-white font-bold text-[10px] rounded-md active:scale-95 transition-all">
                  Agregar
                </button>
              ) : (
                <div className="flex items-center h-6 bg-gray-50 rounded-md border border-gray-200">
                  <button onClick={(e) => { e.stopPropagation(); onRemoveFromCart(product.id); }}
                    className="w-6 h-full text-red-500 hover:bg-red-50 flex items-center justify-center rounded-l-md text-xs font-bold">
                    −
                  </button>
                  <span className="w-5 text-center font-black text-[10px]">{quantity}</span>
                  <button onClick={(e) => { e.stopPropagation(); onAddToCart(product); }}
                    className="w-6 h-full bg-yellow-500 text-black hover:bg-yellow-600 flex items-center justify-center rounded-r-md font-black text-xs">
                    +
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Fullscreen product modal */}
      {showImageModal && (
        <div className="fixed inset-0 bg-black z-[70] flex flex-col animate-fade-in">
          <button className="absolute top-3 right-3 z-10 bg-white/20 text-white rounded-full w-9 h-9 flex items-center justify-center backdrop-blur-sm"
            onClick={() => setShowImageModal(false)}>
            <X size={20} />
          </button>
          {product.image && (
            <div className="flex-1 flex items-center justify-center overflow-hidden">
              <img src={product.image} alt={product.name} className="w-full h-full object-contain" />
            </div>
          )}
          <div className="bg-white rounded-t-2xl p-4 pb-6">
            <h3 className="text-lg font-black text-gray-900 mb-1">{product.name}</h3>
            {product.description && (
              <p className="text-sm text-gray-600 leading-relaxed mb-3">{product.description}</p>
            )}
            <div className="flex items-center justify-between">
              <span className="bg-yellow-400 text-black px-4 py-2 rounded-xl font-black text-lg">
                ${product.price?.toLocaleString('es-CL')}
              </span>
              <div className="flex items-center gap-2">
                {quantity > 0 && (
                  <button onClick={(e) => { e.stopPropagation(); onRemoveFromCart(product.id); }}
                    className="w-10 h-10 text-red-500 bg-red-50 rounded-lg flex items-center justify-center">
                    <MinusCircle size={22} />
                  </button>
                )}
                {quantity > 0 && (
                  <span className="font-black text-lg w-8 text-center">{quantity}</span>
                )}
                <button onClick={(e) => { e.stopPropagation(); onAddToCart(product); }}
                  className={`px-5 h-10 font-bold rounded-xl active:scale-95 transition-all ${quantity > 0 ? 'bg-yellow-500 text-black' : 'bg-green-600 text-white'}`}>
                  {quantity > 0 ? '+1' : 'Agregar'}
                </button>
              </div>
            </div>
            {quantity > 0 && (
              <div className="mt-2 bg-blue-50 rounded-lg p-2 text-center">
                <span className="text-xs font-bold text-blue-700">{quantity} en carrito — ${(product.price * quantity).toLocaleString('es-CL')}</span>
              </div>
            )}
          </div>
        </div>
      )}
    </>
  );
};


// Sub-component for Header Actions
const HeaderRightActions = ({
  cajaUser,
  setIsProfileOpen,
  setShowQRModal,
  setIsNotificationsOpen,
  activeOrdersCount,
  activeChecklistsCount,
  setShowCheckout,
  cartItemCount,
  playCajaSound,
  cartIconRef,
  bellIconRef,
  handleOpenConfig
}) => (
  <div className="flex items-center gap-4 sm:gap-6">
    {/* Perfil Cajera */}
    {cajaUser && (
      <button
        onClick={() => { vibrate(30); setIsProfileOpen(true); }}
        className="flex items-center gap-2 text-gray-600 hover:text-orange-500 p-1 rounded-lg hover:bg-gray-100 transition-all"
        title="Perfil Cajera"
      >
        <UserIcon size={24} className="text-orange-500" isAnimated={false} />
        <span className="font-medium text-[clamp(12px,3vw,14px)]">{cajaUser.fullName || cajaUser.user}</span>
      </button>
    )}

    {/* Compartir */}
    <button
      onClick={() => { vibrate(30); setShowQRModal(true); }}
      className="text-gray-600 hover:text-orange-500 transition-colors"
      title="Compartir App"
    >
      <ShareIcon size={24} isAnimated={false} />
    </button>

    {/* Notificaciones */}
    <button
      onClick={() => { vibrate(30); setIsNotificationsOpen(true); }}
      className="text-gray-600 hover:text-orange-500 relative"
      title="Notificaciones"
    >
      <BellIcon ref={bellIconRef} size={24} />
      {activeOrdersCount > 0 && (
        <span className="absolute -top-1 -right-1 bg-red-500 text-white rounded-full flex items-center justify-center text-[clamp(7px,2vw,9px)] w-[clamp(12px,3vw,14px)] h-[clamp(12px,3vw,14px)]">
          {activeOrdersCount}
        </span>
      )}
      {activeChecklistsCount > 0 && (
        <span className="absolute -bottom-1 -right-1 bg-blue-500 text-white rounded-full flex items-center justify-center text-[clamp(7px,2vw,9px)] w-[clamp(12px,3vw,14px)] h-[clamp(12px,3vw,14px)]">
          {activeChecklistsCount}
        </span>
      )}
    </button>

    {/* Configuración */}
    {cajaUser && (
      <button
        onClick={handleOpenConfig}
        className="text-gray-600 hover:text-orange-500 transition-colors"
        title="Configuración"
      >
        <Settings size={24} />
      </button>
    )}

    {/* Carrito */}
    <button onClick={() => { vibrate(30); playCajaSound(); setShowCheckout(true); }} className="text-gray-600 hover:text-orange-500">
      <ShoppingCartIcon ref={cartIconRef} size={24} badge={cartItemCount} tvBadge={tvPendingCount} />
    </button>
  </div>
);

// Helper to use clamp-like logic in JS if needed or just pass the string
const clamp = (min, vw, max) => `clamp(${min}px, ${vw}vw, ${max}px)`;

export default function App() {
  const cartIconRef = useRef(null);
  const bellIconRef = useRef(null);
  const [menuCategories, setMenuCategories] = useState([]);
  const [menuCategoriesExpanded, setMenuCategoriesExpanded] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  // Generar mainCategories dinámicamente desde menuCategories
  const mainCategories = useMemo(() => {
    if (menuCategories.length === 0) return [];
    return menuCategories
      .filter(cat => cat.is_active === 1)
      .sort((a, b) => a.sort_order - b.sort_order)
      .map(cat => cat.category_key);
  }, [menuCategories]);

  // Generar categoryDisplayNames dinámicamente desde menuCategories
  const categoryDisplayNames = useMemo(() => {
    const names = {};
    menuCategories.forEach(cat => {
      names[cat.category_key] = cat.display_name;
    });
    return names;
  }, [menuCategories]);

  // Generar categoryFilters dinámicamente desde menuCategories
  const categoryFilters = useMemo(() => {
    const filters = {};
    menuCategories.forEach(cat => {
      if (cat.filter_config) {
        filters[cat.category_key] = cat.filter_config;
      }
    });
    return filters;
  }, [menuCategories]);

  const [activeCategory, setActiveCategory] = useState('hamburguesas');
  const [showDispatchPopup] = useState(() => {
    const d = new Date(), day = d.getDate(), m = d.getMonth() + 1, y = d.getFullYear();
    return y === 2026 && m === 2 && (day === 20 || day === 21) && !sessionStorage.getItem('dispatch_popup_seen');
  });
  const [dispatchPopupOpen, setDispatchPopupOpen] = useState(showDispatchPopup);
  const [selectedProduct, setSelectedProduct] = useState(null);

  // Intersection Observer para detectar sección activa al hacer scroll
  useEffect(() => {
    const observerOptions = {
      root: null,
      rootMargin: '-10% 0px -80% 0px', // Detectar cuando está cerca de la parte superior
      threshold: 0
    };

    const handleIntersect = (entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const sectionId = entry.target.id;
          const catKey = sectionId.replace('section-', '');
          setActiveCategory(catKey);
        }
      });
    };

    const observer = new IntersectionObserver(handleIntersect, observerOptions);
    mainCategories.forEach(cat => {
      const element = document.getElementById(`section-${cat}`);
      if (element) observer.observe(element);
    });

    return () => observer.disconnect();
  }, [mainCategories, searchQuery]);

  const [zoomedProduct, setZoomedProduct] = useState(null);
  const [cart, setCart] = useState([]);
  const [isCartOpen, setIsCartOpen] = useState(false);
  const [tvPendingCount, setTvPendingCount] = useState(0);
  const [dynamicDeliveryFee, setDynamicDeliveryFee] = useState(null);
  const [deliveryFeeLabel, setDeliveryFeeLabel] = useState(null);
  const [deliveryDistanceInfo, setDeliveryDistanceInfo] = useState(null);

  const [tvOrderId, setTvOrderId] = useState(() => {
    const saved = localStorage.getItem('tv_order_id');
    return saved ? parseInt(saved) : null;
  });

  const setTvOrderIdPersist = (id) => {
    if (id) localStorage.setItem('tv_order_id', id);
    else localStorage.removeItem('tv_order_id');
    setTvOrderId(id);
  };
  const [isLoginOpen, setIsLoginOpen] = useState(false);
  const [activePanel, setActivePanel] = useState(null);
  const savedScrollRef = useRef(0);
  const savedCategoryRef = useRef(null);

  const openPanel = (panel) => {
    savedScrollRef.current = window.scrollY;
    savedCategoryRef.current = activeCategory;
    setActivePanel(panel);
    window.scrollTo(0, 0);
  };
  const closePanel = () => {
    setActivePanel(null);
    requestAnimationFrame(() => {
      window.scrollTo(0, savedScrollRef.current);
      if (savedCategoryRef.current) {
        setActiveCategory(savedCategoryRef.current);
      }
    });
  };

  const [showCheckout, setShowCheckout] = useState(false);
  const [showPayment, setShowPayment] = useState(false);
  const [currentOrder, setCurrentOrder] = useState(null);
  const [customerInfo, setCustomerInfo] = useState({ name: '', phone: '', email: '', address: '', deliveryType: 'pickup', pickupTime: '', customerNotes: '', deliveryDiscount: false, pickupDiscount: false, birthdayDiscount: false, discount30: false });

  // Auto-calcular tarifa si ya hay dirección al cargar (NO aplica para RL6)
  useEffect(() => {
    if (customerInfo.address && customerInfo.deliveryType === 'delivery' && dynamicDeliveryFee === null && !customerInfo.deliveryDiscount) {
      fetch('/api/location/get_delivery_fee.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ address: customerInfo.address })
      })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            setDynamicDeliveryFee(data.delivery_fee);
            setDeliveryFeeLabel(data.label);
            setDeliveryDistanceInfo({ km: data.distance_km, min: data.duration_min });
          }
        })
        .catch(() => {});
    }
  }, [customerInfo.address, customerInfo.deliveryType]);

  // Polling pedidos TV
  useEffect(() => {
    const fetchTvCount = () => {
      fetch('/api/tv/get_pending_count.php')
        .then(r => r.json())
        .then(d => setTvPendingCount(d.count || 0))
        .catch(() => {});
    };
    fetchTvCount();
    const interval = setInterval(fetchTvCount, 5000);
    return () => clearInterval(interval);
  }, []);

  const loadTvOrder = async () => {
    try {
      const res = await fetch('/api/tv/get_next_order.php');
      const data = await res.json();
      if (!data.success) { alert('No hay pedidos TV pendientes'); return; }
      const newItems = data.items.map(item => ({
        ...item,
        cartItemId: `tv_${item.id}_${Date.now()}_${Math.random()}`,
        quantity: 1
      }));
      setCart(newItems);
      setTvOrderIdPersist(data.tv_order_id);
      setCustomerInfo(prev => ({ ...prev, deliveryType: 'tv' }));
      setIsCartOpen(true);
      setTvPendingCount(prev => Math.max(0, prev - 1));
    } catch (e) {
      alert('Error al cargar pedido TV');
    }
  };
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
  const [filteredProducts, setFilteredProducts] = useState([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [suggestions, setSuggestions] = useState([]);
  const [reviewsModalProduct, setReviewsModalProduct] = useState(null);
  const [shareModalProduct, setShareModalProduct] = useState(null);
  const [comboModalProduct, setComboModalProduct] = useState(null);
  const [showCheckoutSection, setShowCheckoutSection] = useState(false);
  const [pendingPaymentModal, setPendingPaymentModal] = useState(null);
  const [showCashModal, setShowCashModal] = useState(false);
  const [showPedidosYAModal, setShowPedidosYAModal] = useState(false);
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState(null);
  const [checkoutErrors, setCheckoutErrors] = useState([]);

  // Trigger bell animation when notifications change
  useEffect(() => {
    if ((activeOrdersCount > 0 || activeChecklistsCount > 0) && bellIconRef.current) {
      bellIconRef.current.startAnimation();
    }
  }, [activeOrdersCount, activeChecklistsCount]);
  const [cashAmount, setCashAmount] = useState('');
  const [cashStep, setCashStep] = useState('input');
  const [isProcessing, setIsProcessing] = useState(false);
  const [isProcessingOrder, setIsProcessingOrder] = useState(false);
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

  const [infoExpanded, setInfoExpanded] = useState(true);
  const [statusExpanded, setStatusExpanded] = useState(false);
  const [schedulesExpanded, setSchedulesExpanded] = useState(false);
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
    const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.7143) : baseDeliveryFee;
    const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
    const discount30Amount = customerInfo.discount30 ? Math.round(cartSubtotal * 0.3) : 0;
    const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
    const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
    const finalTotal = cartSubtotal + deliveryFee - pickupDiscountAmount - discount30Amount - birthdayDiscountAmount - pizzaDiscountAmount;
    setCashAmount(finalTotal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
  };

  const setQuickAmount = (amount) => {
    setCashAmount(amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
  };

  const handleContinueCash = () => {
    const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
    const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.7143) : baseDeliveryFee;
    const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
    const discount30Amount = customerInfo.discount30 ? Math.round(cartSubtotal * 0.3) : 0;
    const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
    const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
    const finalTotal = cartSubtotal + deliveryFee - pickupDiscountAmount - discount30Amount - birthdayDiscountAmount - pizzaDiscountAmount;

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
      playSuccessSound();
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
      const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.7143) : baseDeliveryFee;
      const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
      const discount30Amount = customerInfo.discount30 ? Math.round(cartSubtotal * 0.3) : 0;
      const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
      const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
      const finalTotal = cartSubtotal + deliveryFee - pickupDiscountAmount - discount30Amount - birthdayDiscountAmount - pizzaDiscountAmount;
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
        delivery_discount: customerInfo.deliveryDiscount ? baseDeliveryFee - deliveryFee : 0,
        discount_amount: pickupDiscountAmount + discount30Amount + birthdayDiscountAmount + pizzaDiscountAmount,
        discount_10: pickupDiscountAmount,
        discount_30: discount30Amount,
        discount_birthday: birthdayDiscountAmount,
        discount_pizza: pizzaDiscountAmount,
        customer_notes: finalNotes,
        delivery_type: customerInfo.deliveryType,
        delivery_address: customerInfo.address || null,
        payment_method: 'cash',
        tv_order_id: tvOrderId || null,
        delivery_distance_km: deliveryDistanceInfo?.km || null,
        delivery_duration_min: deliveryDistanceInfo?.min || null
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
        playSuccessSound();
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
    playSuccessSound();
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
    }).catch(() => { });

    // Limpiar carrito
    setCart([]);
    setShowPayment(false);
    setCurrentOrder(null);
    setCustomerInfo({ name: '', phone: '', email: '', address: '' });

    // Marcar tv_order como pagado si aplica
    if (tvOrderId) {
      fetch('/api/tv/update_order_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: tvOrderId, status: 'pagado'})
      }).catch(() => {});
      setTvOrderIdPersist(null);
    }

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
    setShowSuggestions(false);
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
        const {latitude, longitude} = position.coords;

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
            }).catch(() => { });
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
        calculateRealDeliveryTime(lat, lng, closestTruck.latitud, closestTruck.longitud).catch(() => { });
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
      // Silenciar log en caja3 - los cajeros no cargan pedidos de usuario
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
      // Silenciar log en caja3 - los cajeros no cargan notificaciones de usuario
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

    const loadMenuCategories = async () => {
      try {
        const response = await fetch('/api/get_menu_structure.php');
        const data = await response.json();
        if (data.success) {
          setMenuCategories(data.categories);
        }
      } catch (error) {
        console.error('Error cargando categorías del menú:', error);
      }
    };

    loadMenuFromDatabase();
    loadDeliveryFee();
    loadMenuCategories();
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
    if (product.type === 'combo' || product.category_name === 'combos' || product.category_name === 'Combos') {
      setComboModalProduct(product);
      return;
    }

    vibrate(50);
    playAddSound();

    // Trigger cart icon animation
    if (cartIconRef.current) {
      cartIconRef.current.startAnimation();
    }

    if (window.Analytics) {
      window.Analytics.trackAddToCart(product.id, product.name);
    }

    setCart(prevCart => [...prevCart, {
      ...product,
      price: product.sale_price || product.price,
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
      playRemoveSound();
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
        playRemoveSound();
      }
    }
  };

  const handleCustomizeProduct = (item, itemIndex) => {
    // Usar category_id del producto directamente — no depender de category_name string
    const productCategory = CATEGORY_ID_MAP[item.category_id] || item.category_key || activeCategory;
    setSelectedProduct({
      ...item,
      isEditing: true,
      cartIndex: itemIndex,
      _overrideCategory: productCategory
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

  // Subtotal con precios PedidosYA (para pedidosya_cash)
  const cartSubtotalPYA = useMemo(() => {
    return cart.reduce((total, item) => {
      let itemPrice = item.pedidosya_price ? parseFloat(item.pedidosya_price) : item.price;

      if (item.customizations && item.customizations.length > 0) {
        const customizationsPrice = item.customizations.reduce((sum, c) => {
          let price = (c.pedidosya_price ? parseFloat(c.pedidosya_price) : c.price) * c.quantity;
          if (c.extraPrice && c.quantity > 1) {
            price = (c.pedidosya_price ? parseFloat(c.pedidosya_price) : c.price) + (c.quantity - 1) * (c.extraPrice || c.price);
          }
          return sum + price;
        }, 0);
        itemPrice += customizationsPrice;
      }

      return total + itemPrice;
    }, 0);
  }, [cart]);

  const CARD_DELIVERY_SURCHARGE = 500;

  const baseDeliveryFee = useMemo(() => {
    if (customerInfo.deliveryType === 'delivery') {
      if (dynamicDeliveryFee != null) return dynamicDeliveryFee;
      if (nearbyTrucks.length > 0) return parseInt(nearbyTrucks[0].tarifa_delivery || 0);
    }
    return 0;
  }, [customerInfo.deliveryType, nearbyTrucks, dynamicDeliveryFee]);

  const deliveryFee = useMemo(() => {
    return customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.7143) : baseDeliveryFee;
  }, [baseDeliveryFee, customerInfo.deliveryDiscount]);

  const deliveryDiscountAmount = useMemo(() => {
    return customerInfo.deliveryDiscount ? baseDeliveryFee - deliveryFee : 0;
  }, [baseDeliveryFee, deliveryFee, customerInfo.deliveryDiscount]);

  const cardDeliverySurcharge = useMemo(() => {
    return customerInfo.deliveryType === 'delivery' && selectedPaymentMethod === 'card' ? CARD_DELIVERY_SURCHARGE : 0;
  }, [customerInfo.deliveryType, selectedPaymentMethod]);

  const cartTotal = useMemo(() => {
    const surcharge = customerInfo.deliveryType === 'delivery' && selectedPaymentMethod === 'card' ? CARD_DELIVERY_SURCHARGE : 0;
    return cartSubtotal + deliveryFee + surcharge;
  }, [cartSubtotal, deliveryFee, customerInfo.deliveryType, selectedPaymentMethod]);

  const cartItemCount = useMemo(() => cart.length, [cart]);
  const getProductQuantity = (productId) => cart.filter(item => item.id === productId).length;

  // Construir comboItems usando SUBCATEGORY_ID_MAP — sin strings hardcodeados
  const getBySubcategoryId = (subId) => {
    const subKey = SUBCATEGORY_ID_MAP[subId];
    if (!subKey) return [];
    for (const catData of Object.values(menuWithImages)) {
      if (catData && typeof catData === 'object' && catData[subKey]) return catData[subKey];
    }
    return [];
  };

  const comboItems = {
    papas_y_snacks: getBySubcategoryId(9),
    jugos: getBySubcategoryId(10),
    bebidas: [
      ...getBySubcategoryId(11),
      ...getBySubcategoryId(61),
      ...getBySubcategoryId(62),
      ...getBySubcategoryId(63),
      ...getBySubcategoryId(64),
      ...getBySubcategoryId(65),
    ],
    salsas: getBySubcategoryId(12),
    empanadas: getBySubcategoryId(26),
    cafe: getBySubcategoryId(27),
    te: getBySubcategoryId(28),
    personalizar: getBySubcategoryId(29).filter(p => p.active === 1),
    extras: getBySubcategoryId(30),
  };

  const generateWhatsAppMessage = (orderId) => {
    const currentDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
    let message = `> 🍔 *NUEVO PEDIDO - LA RUTA 11*\n\n`;
    message += `*📋 Datos del pedido:*\n`;
    message += `- *Pedido:* ${orderId}\n`;
    message += `- *Cliente:* ${customerInfo.name}\n`;
    message += `- *Teléfono:* ${customerInfo.phone || 'No especificado'}\n`;
    message += `- *Tipo:* ${customerInfo.deliveryType === 'delivery' ? '🚴 Delivery' : '🏪 Retiro'}\n`;

    if (customerInfo.deliveryType === 'delivery' && customerInfo.address) {
      message += `- *Dirección:* ${customerInfo.address}\n`;
    }
    if (customerInfo.deliveryType === 'pickup' && customerInfo.pickupTime) {
      message += `- *Hora de retiro:* ${customerInfo.pickupTime}\n`;
    }

    message += `\n*📦 Productos:*\n`;
    cart.forEach((item, index) => {
      const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
      message += `${index + 1}. ${item.name} x${item.quantity} - $${(item.price * item.quantity).toLocaleString('es-CL')}\n`;

      if (isCombo && (item.fixed_items || item.selections)) {
        if (item.fixed_items) {
          item.fixed_items.forEach(fixedItem => {
            message += `   - ${item.quantity}x ${fixedItem.product_name || fixedItem.name}\n`;
          });
        }
        if (item.selections) {
          Object.entries(item.selections).forEach(([group, selection]) => {
            if (Array.isArray(selection)) {
              selection.forEach(sel => {
                message += `   - ${item.quantity}x ${sel.name}\n`;
              });
            } else if (selection) {
              message += `   - ${item.quantity}x ${selection.name}\n`;
            }
          });
        }
      }

      if (item.customizations && item.customizations.length > 0) {
        item.customizations.forEach(custom => {
          message += `   - ${custom.quantity}x ${custom.name} (+$${(custom.price * custom.quantity).toLocaleString('es-CL')})\n`;
        });
      }
    });

    message += `\n*💰 Totales:*\n`;
    message += `- *Subtotal:* $${cartSubtotal.toLocaleString('es-CL')}\n`;
    if (currentDeliveryFee > 0) {
      message += `- *Delivery:* $${currentDeliveryFee.toLocaleString('es-CL')}\n`;
    }
    message += `\n> *💰 TOTAL: $${cartTotal.toLocaleString('es-CL')}*\n\n`;
    message += `_Pedido realizado desde la app web._`;

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
      navigator.serviceWorker.register('/sw.js').catch(() => { });
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

  // Sistema de tracking de uso - DESACTIVADO
  // No se usa track_usage.php en caja3

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
        return response.json();
      })
      .then(data => {
        if (!data) return;

        // 1. Manejar Sesión de CAJERA (Sync con LocalStorage para PWA)
        if (data.cashier) {
          setCajaUser(data.cashier);
          // Si localStorage está vacío (común en PWA instalada), lo restauramos
          if (!localStorage.getItem('caja_session')) {
            console.log('Restoring caja_session from server...');
            localStorage.setItem('caja_session', JSON.stringify(data.cashier));
          }
        }

        // 2. Manejar Sesión de CLIENTE
        if (data.authenticated && data.user) {
          setUser(data.user);
          setTimeout(() => {
            loadNotifications();
            loadUserOrders();
          }, 100);
        }
      })
      .catch((err) => { console.error('Error sync session:', err); });

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

  if (activePanel) {
    return (
      <React.Suspense fallback={<div className="flex items-center justify-center min-h-screen"><div className="animate-spin rounded-full h-10 w-10 border-4 border-gray-300 border-t-orange-500" /></div>}>
        {activePanel === 'merma' && <MermaPanel onClose={closePanel} />}
        {activePanel === 'arqueo' && <ArqueoPanel onClose={closePanel} />}
      </React.Suspense>
    );
  }

  return (
    <div className="bg-white font-sans min-h-screen w-full pb-24" style={{ backgroundColor: '#ffffff', background: '#ffffff' }}>

      {dispatchPopupOpen && (
        <div className="fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
            <div className="bg-gradient-to-r from-blue-600 to-blue-700 px-5 py-4 text-white">
              <div className="text-2xl mb-1">📷 Nueva Funcionalidad</div>
              <div className="font-bold text-lg">Foto de Despacho</div>
            </div>
            <div className="p-5 space-y-3 text-sm text-gray-700">
              <p>A partir de hoy, antes de entregar un pedido debes:</p>
              <ol className="space-y-2 list-none">
                <li className="flex gap-2"><span className="font-bold text-blue-600">1.</span> Abrir las <strong>Comandas Activas</strong></li>
                <li className="flex gap-2"><span className="font-bold text-blue-600">2.</span> Tocar el botón <strong>📷</strong> en el pedido listo</li>
                <li className="flex gap-2"><span className="font-bold text-blue-600">3.</span> <strong>Verificar cada producto</strong> del checklist</li>
                <li className="flex gap-2"><span className="font-bold text-blue-600">4.</span> <strong>Tomar una foto</strong> del pedido armado</li>
                <li className="flex gap-2"><span className="font-bold text-blue-600">5.</span> Confirmar despacho ✅</li>
              </ol>
              <p className="text-xs text-gray-500 bg-gray-50 rounded-lg p-3">🛡️ Esto nos protege ante reclamos y asegura que el pedido salga completo.</p>
            </div>
            <div className="px-5 pb-5">
              <button
                onClick={() => { sessionStorage.setItem('dispatch_popup_seen', '1'); setDispatchPopupOpen(false); }}
                className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl text-sm"
              >
                Entendido, vamos 🚀
              </button>
            </div>
          </div>
        </div>
      )}

      <header className="px-2 py-1 sm:px-4 sm:py-2 fixed top-0 left-0 right-0 bg-white z-40 shadow-sm" style={{ paddingTop: 'calc(env(safe-area-inset-top, 0px) + 6px)' }}>
        <div className="w-full">
          {/* Single row for all icons with equal spacing and full width */}
          <div className="flex items-center justify-between w-full gap-1 xs:gap-2 sm:gap-4">
            {/* Logo */}
            <div className="p-2 shrink-0">
              <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png" alt="La Ruta 11" style={{ width: '28px', height: '28px' }} width="28" height="28" />
            </div>

            {/* Checklist */}
            {cajaUser && (
              <button
                onClick={() => { vibrate(30); window.location.href = '/checklist'; }}
                className="text-gray-600 hover:text-orange-500 transition-colors p-2 rounded-full hover:bg-gray-100 shrink-0"
                title="Checklist"
              >
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M9 11l3 3L22 4"></path>
                  <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>
              </button>
            )}

            {/* Toggle Productos Inactivos */}
            {cajaUser && (
              <button
                onClick={() => { vibrate(30); setShowInactiveProducts(!showInactiveProducts); }}
                className={`p-2 rounded-full transition-all shrink-0 ${showInactiveProducts
                  ? 'bg-red-500 text-white shadow-md'
                  : 'text-gray-600 hover:bg-gray-100'
                  }`}
                title={showInactiveProducts ? 'Ocultar inactivos' : 'Mostrar inactivos'}
              >
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
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
                className="flex items-center gap-1 text-gray-600 hover:text-orange-500 p-2 rounded-full hover:bg-gray-100 transition-all shrink-0"
                title="Perfil"
              >
                <UserIcon size={24} className="text-orange-500" isAnimated={false} />
                <span className="font-medium text-[10px] hidden lg:inline">{cajaUser.fullName || cajaUser.user}</span>
              </button>
            )}

            {/* Compartir */}
            <button
              onClick={() => { vibrate(30); setShowQRModal(true); }}
              className="text-gray-600 hover:text-orange-500 p-2 rounded-full hover:bg-gray-100 transition-colors shrink-0"
              title="Compartir"
            >
              <ShareIcon size={24} isAnimated={false} />
            </button>

            {/* Notificaciones */}
            <button
              onClick={() => { vibrate(30); setIsNotificationsOpen(true); }}
              className="text-gray-600 hover:text-orange-500 p-2 rounded-full hover:bg-gray-100 relative shrink-0"
              title="Notificaciones"
            >
              <BellIcon ref={bellIconRef} size={24} />
              {activeOrdersCount > 0 && (
                <span className="absolute top-1 right-1 bg-red-500 text-white rounded-full flex items-center justify-center text-[8px] min-w-[15px] h-[15px] px-1 border-2 border-white shadow-sm">
                  {activeOrdersCount}
                </span>
              )}
              {activeChecklistsCount > 0 && (
                <span className="absolute bottom-1 right-1 bg-blue-500 text-white rounded-full flex items-center justify-center text-[8px] min-w-[15px] h-[15px] px-1 border-2 border-white shadow-sm">
                  {activeChecklistsCount}
                </span>
              )}
            </button>

            {/* Configuración */}
            {cajaUser && (
              <button
                onClick={async () => {
                  vibrate(30);
                  setShowStatusModal(true);
                  try {
                    const res = await fetch('/api/get_truck_status.php?truckId=4');
                    const data = await res.json();
                    if (data.success) setTruckStatus(data.truck);

                    const schedRes = await fetch('/api/get_truck_schedules.php?truckId=4');
                    const schedData = await schedRes.json();
                    if (schedData.success) {
                      setSchedules(schedData.schedules);
                      setCurrentDayOfWeek(schedData.currentDayOfWeek);
                    }

                    const catRes = await fetch('/api/get_menu_structure.php');
                    const catData = await catRes.json();
                    if (catData.success) setMenuCategories(catData.categories);
                  } catch (error) {
                    console.error('Error opening config:', error);
                  }
                }}
                className="text-gray-600 hover:text-orange-500 p-2 rounded-full hover:bg-gray-100 transition-colors shrink-0"
                title="Configuración"
              >
                <Settings size={22} />
              </button>
            )}

            {/* Carrito */}
            <button
              onClick={() => { vibrate(30); playCajaSound(); setShowCheckout(true); }}
              className="text-gray-600 hover:text-orange-500 p-2 rounded-full hover:bg-gray-100"
              title="Carrito"
            >
              <ShoppingCartIcon ref={cartIconRef} size={24} badge={cartItemCount} tvBadge={tvPendingCount} />
            </button>
          </div>
        </div>
      </header>

      {/* CART SUMMARY FRANJA - style like comandas: shows grouped product names + total */}
      {cartItemCount > 0 && (() => {
        const productSummary = cart.reduce((acc, item) => {
          const key = item.name;
          acc[key] = (acc[key] || 0) + (item.quantity || 1);
          return acc;
        }, {});
        const summaryText = Object.entries(productSummary)
          .map(([name, qty]) => `${qty} ${name}`)
          .join(', ');
        return (
          <div
            className="fixed left-0 right-0 z-30 bg-orange-50 border-b border-orange-200 px-3 py-1 cursor-pointer rounded-b-xl"
            style={{ top: 'calc(env(safe-area-inset-top, 0px) + 50px)' }}
            onClick={() => { vibrate(30); playCajaSound(); setShowCheckout(true); }}
          >
            <div className="flex items-center justify-between text-xs py-0.5">
              <span className="font-bold text-gray-800 flex-1 mr-2 leading-tight">🛒 {summaryText}</span>
            </div>
          </div>
        );
      })()}

      <main className="pb-24 px-0.5 sm:px-4 lg:px-8 xl:px-12 2xl:px-16 max-w-screen-2xl mx-auto" style={{ paddingTop: 'calc(env(safe-area-inset-top, 0px) + 70px)' }}>
        <div className="flex flex-col gap-1">
          {(() => {
            // Build a flat array of all products tagged with their category color and key
            const allItems = [];
            mainCategories
              .filter(cat => cat !== 'personalizar' && cat !== 'extras')
              .forEach(catKey => {
                let categoryData = menuWithImages[catKey];
                // Virtual categories (bebidas, hamburguesas_100g, pizzas) pull data from other keys
                const virtualCategories = ['bebidas', 'hamburguesas_100g', 'pizzas'];
                if (!categoryData && !virtualCategories.includes(catKey)) return;

                let displayData = {};

                if (catKey === 'hamburguesas_100g') {
                  Object.entries(menuWithImages.hamburguesas || {}).forEach(([subCat, products]) => {
                    const filtered = products.filter(p => p.subcategory_id === 5);
                    if (filtered.length > 0) displayData[subCat] = filtered;
                  });
                } else if (catKey === 'hamburguesas') {
                  Object.entries(menuWithImages.hamburguesas || {}).forEach(([subCat, products]) => {
                    const filtered = products.filter(p => p.subcategory_id !== 5);
                    if (filtered.length > 0) displayData[subCat] = filtered;
                  });
                } else if (catKey === 'papas') {
                  displayData = { papas: menuWithImages.papas?.papas?.filter(p => p.category_id === 12) || [] };
                } else if (catKey === 'pizzas') {
                  displayData = { pizzas: [] };
                  Object.values(menuWithImages).forEach(category => {
                    if (Array.isArray(category)) {
                      displayData.pizzas.push(...category.filter(p => p.category_id === 5 && p.subcategory_id === 60));
                    } else {
                      Object.values(category).forEach(subcat => {
                        if (Array.isArray(subcat)) {
                          displayData.pizzas.push(...subcat.filter(p => p.category_id === 5 && p.subcategory_id === 60));
                        }
                      });
                    }
                  });
                } else if (catKey === 'bebidas') {
                  const bebidasSubcats = { 11: 'bebidas', 10: 'jugos', 28: 'té', 27: 'café', 61: 'aguas', 62: 'latas_350ml', 63: 'energeticas_473ml', 64: 'energeticas_250ml', 65: 'bebidas_1_5l' };
                  const bebidasIds = [11, 10, 28, 27, 61, 62, 63, 64, 65];
                  Object.values(menuWithImages).forEach(category => {
                    if (Array.isArray(category)) {
                      category.filter(p => p.category_id === 5 && bebidasIds.includes(p.subcategory_id)).forEach(p => {
                        const subName = bebidasSubcats[p.subcategory_id];
                        if (!displayData[subName]) displayData[subName] = [];
                        displayData[subName].push(p);
                      });
                    } else {
                      Object.values(category).forEach(subcat => {
                        if (Array.isArray(subcat)) {
                          subcat.filter(p => p.category_id === 5 && bebidasIds.includes(p.subcategory_id)).forEach(p => {
                            const subName = bebidasSubcats[p.subcategory_id];
                            if (!displayData[subName]) displayData[subName] = [];
                            displayData[subName].push(p);
                          });
                        }
                      });
                    }
                  });
                } else {
                  if (Array.isArray(categoryData)) {
                    displayData = { [catKey]: categoryData };
                  } else {
                    displayData = categoryData;
                  }
                }

                let orderedEntries = Object.entries(displayData);
                if (catKey === 'completos') {
                  orderedEntries = [
                    ['tradicionales', displayData.tradicionales || []],
                    ['especiales', displayData.especiales || []],
                    ['al vapor', displayData['al vapor'] || []]
                  ];
                }

                const catColor = categoryColors[catKey] || '#94a3b8';
                const catProducts = orderedEntries
                  .filter(([, prods]) => prods && prods.length > 0)
                  .flatMap(([, prods]) => {
                    const filtered = (!showInactiveProducts && cajaUser)
                      ? prods.filter(p => p.active !== 0)
                      : prods;
                    return filtered;
                  });

                if (catProducts.length === 0) return;

                // Tag each product with its category color and key, mark the first one
                catProducts.forEach((product, idx) => {
                  allItems.push({
                    product,
                    catKey,
                    catColor,
                    isFirstInCategory: idx === 0
                  });
                });
              });

            // Render the single continuous list
            const q = searchQuery.trim().toLowerCase();
            const filteredItems = q.length >= 2
              ? allItems.filter(({ product }) => product.name.toLowerCase().includes(q) || (product.description || '').toLowerCase().includes(q))
              : allItems;

            return filteredItems.map(({ product, catKey, catColor, isFirstInCategory }) => (
              <div
                key={product.id}
                id={isFirstInCategory ? `section-${catKey}` : undefined}
                className="scroll-mt-24"
              >
                <MenuItem
                  product={product}
                  type={product.subcategory_name || catKey}
                  onSelect={null}
                  onAddToCart={handleAddToCart}
                  onRemoveFromCart={handleRemoveFromCart}
                  quantity={getProductQuantity(product.id)}
                  isLiked={likedProducts.has(product.id)}
                  handleLike={handleLike}
                  setReviewsModalProduct={setReviewsModalProduct}
                  onShare={setShareModalProduct}
                  isCashier={!!cajaUser}
                  searchQuery={searchQuery}
                />
              </div>
            ));
          })()}
        </div>
      </main>

      {/* Barra de búsqueda con botones */}
      {/* Sugerencias de búsqueda (fuera del nav para posicionamiento correcto) */}
      {showSuggestions && (
        <div
          className="fixed left-0 right-0 bg-white/95 backdrop-blur-md border-t border-gray-200 shadow-[0_-10px_40px_rgba(0,0,0,0.15)] max-h-[55vh] overflow-y-auto z-[45]"
          style={{ bottom: 'calc(env(safe-area-inset-bottom, 0px) + 110px)' }}
        >
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
                    <img src={product.image} alt={product.name} className="w-10 h-10 object-cover rounded" loading="lazy" decoding="async" />
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
                        vibrate(30);
                        playRemoveSound();
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
                      vibrate(30);
                      playAddSound();
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

      <nav className="fixed bottom-0 left-0 right-0 z-40 lg:left-1/2 lg:transform lg:-translate-x-1/2 lg:max-w-4xl" style={{ paddingBottom: 'env(safe-area-inset-bottom, 0px)', backgroundColor: '#1a1a1a' }}>
        {/* Fila superior: Mermar | Búsqueda | Caja */}
        <div className="flex items-center gap-2 px-3 pt-2 pb-1">
          <button
            onClick={() => openPanel('merma')}
            className="flex flex-col items-center justify-center gap-0.5 flex-shrink-0 active:scale-95 transition-all"
            style={{ width: '48px' }}
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#ef4444">
              <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z" />
            </svg>
            <span className="text-[10px] font-bold text-red-400">Mermar</span>
          </button>
          <div className="flex-1 relative">
            <SearchIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={14} />
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
              className="w-full pl-8 pr-7 py-1.5 bg-white/10 border border-gray-600 rounded-full text-sm text-white placeholder-gray-400 focus:outline-none focus:border-gray-400 transition-all"
            />
            {searchQuery && (
              <button
                onClick={() => handleSearch('')}
                className="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-200"
              >
                <X size={14} />
              </button>
            )}
          </div>
          <button
            onClick={() => openPanel('arqueo')}
            className="flex flex-col items-center justify-center gap-0.5 flex-shrink-0 active:scale-95 transition-all"
            style={{ width: '48px' }}
          >
            <span className="text-green-400 font-black text-lg">$</span>
            <span className="text-[10px] font-bold text-green-400">Caja</span>
          </button>
        </div>
        {/* Fila inferior: Categorías deslizables */}
        <div
          className="flex items-center overflow-x-auto px-3 pb-2 pt-1 gap-0"
          style={{
            scrollbarWidth: 'none',
            msOverflowStyle: 'none',
            WebkitOverflowScrolling: 'touch'
          }}
        >
          {mainCategories.map((cat, index) => {
            const isActive = activeCategory === cat;
            const catColor = categoryColors[cat] || '#f97316';
            return (
              <React.Fragment key={cat}>
                <button
                  onClick={() => {
                    const element = document.getElementById(`section-${cat}`);
                    if (element) {
                      element.scrollIntoView({ behavior: 'smooth' });
                    }
                    setActiveCategory(cat);
                  }}
                  className={`flex-shrink-0 px-3 py-1 text-[11px] font-black uppercase tracking-widest transition-all whitespace-nowrap active:scale-95 ${isActive ? '' : 'text-gray-500 hover:text-gray-300'
                    }`}
                  style={{ color: isActive ? catColor : undefined }}
                >
                  {categoryDisplayNames[cat] || cat}
                </button>
                {index < mainCategories.length - 1 && (
                  <span className="text-gray-600 font-light select-none">|</span>
                )}
              </React.Fragment>
            );
          })}
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
                    onChange={(e) => setCajaUser({ ...cajaUser, fullName: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="Tu nombre completo"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                  <input
                    type="tel"
                    value={cajaUser.phone || ''}
                    onChange={(e) => setCajaUser({ ...cajaUser, phone: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="+56 9 1234 5678"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                  <input
                    type="email"
                    value={cajaUser.email || ''}
                    onChange={(e) => setCajaUser({ ...cajaUser, email: e.target.value })}
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
            <div className="bg-gradient-to-r from-red-500 to-orange-500 text-white flex justify-between items-center px-6 py-4 shadow-lg sticky top-0 z-50" style={{ paddingTop: 'max(1rem, env(safe-area-inset-top))' }}>
              <h2 className="text-xl font-bold">Finalizar Pedido</h2>
              <button onClick={() => setShowCheckout(false)} className="p-1 hover:bg-white/20 rounded-full transition-colors">
                <X size={24} />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-6">

              {/* Tipo de entrega */}
              <div className="mb-4">
                <h3 className="text-sm font-semibold text-gray-800 mb-2">Tipo de Entrega</h3>
                <div className="grid grid-cols-3 gap-2">
                  <button
                    type="button"
                    onClick={() => setCustomerInfo({ ...customerInfo, deliveryType: 'delivery' })}
                    className={`p-2 border-2 rounded-lg transition-colors flex items-center gap-2 ${customerInfo.deliveryType === 'delivery'
                      ? 'border-orange-500 bg-orange-50 text-orange-700'
                      : 'border-gray-300 hover:border-gray-400'
                      }`}
                  >
                    <Bike size={20} className="text-red-500 flex-shrink-0" />
                    <span className="font-semibold text-sm">Delivery</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => setCustomerInfo({ ...customerInfo, deliveryType: 'pickup' })}
                    className={`p-2 border-2 rounded-lg transition-colors flex items-center gap-2 ${customerInfo.deliveryType === 'pickup'
                      ? 'border-orange-500 bg-orange-50 text-orange-700'
                      : 'border-gray-300 hover:border-gray-400'
                      }`}
                  >
                    <Caravan size={20} className="text-red-500 flex-shrink-0" />
                    <span className="font-semibold text-sm">Retiro</span>
                  </button>
                  {/* Venta TV ocultado - ya no se usa */}
                </div>
              </div>

              <div className="space-y-4 mb-6">
                <h3 className="text-lg font-semibold text-gray-800 mb-3">Datos del Cliente</h3>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Nombre completo *</label>
                  <input
                    type="text"
                    value={customerInfo.name || ''}
                    onChange={(e) => setCustomerInfo({ ...customerInfo, name: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="Nombre del cliente"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                  <input
                    type="tel"
                    value={customerInfo.phone || ''}
                    onChange={(e) => setCustomerInfo({ ...customerInfo, phone: e.target.value })}
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
                          const confirmed = window.confirm('🚚 Descuento Delivery (28%)\n\nSe aplicará un 28% de descuento en el costo de delivery. Solo válido para direcciones específicas.\n\n¿Aplicar descuento?');
                          if (confirmed) {
                            setCustomerInfo({ ...customerInfo, deliveryDiscount: true, address: '' });
                          }
                        } else {
                          setCustomerInfo({ ...customerInfo, deliveryDiscount: false });
                        }
                      }}
                      className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium transition-all ${customerInfo.deliveryDiscount
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
                      <span>-28% Delivery</span>
                    </button>
                  )}
                  {customerInfo.deliveryType === 'pickup' && (
                    <button
                      onClick={() => {
                        if (!customerInfo.pickupDiscount) {
                          const confirmed = window.confirm('🏪 Descuento R11 (10%)\n\nSe aplicará un 10% de descuento en el total de tu compra por retiro en local.\n\n¿Aplicar descuento?');
                          if (confirmed) {
                            setCustomerInfo({ ...customerInfo, pickupDiscount: true });
                          }
                        } else {
                          setCustomerInfo({ ...customerInfo, pickupDiscount: false });
                        }
                      }}
                      className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium transition-all ${customerInfo.pickupDiscount
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
                          setCustomerInfo({ ...customerInfo, birthdayDiscount: true });
                        }
                      } else {
                        setCustomerInfo({ ...customerInfo, birthdayDiscount: false });
                      }
                    }}
                    className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium transition-all cursor-pointer ${customerInfo.birthdayDiscount
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
                  <button
                    onClick={() => {
                      if (!customerInfo.discount30) {
                        const confirmed = window.confirm('⭐ Descuento 30%\n\nSe aplicará un 30% de descuento en productos seleccionados.\n\n¿Aplicar descuento?');
                        if (confirmed) {
                          setCustomerInfo({ ...customerInfo, discount30: true });
                        }
                      } else {
                        setCustomerInfo({ ...customerInfo, discount30: false });
                      }
                    }}
                    className={`flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium transition-all cursor-pointer ${customerInfo.discount30
                      ? 'bg-yellow-400 text-black'
                      : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                      }`}
                  >
                    <Percent size={14} />
                    <input
                      type="checkbox"
                      checked={customerInfo.discount30}
                      readOnly
                      className="w-3 h-3 pointer-events-none"
                    />
                    <span>-30%</span>
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
                          onChange={(e) => setCustomerInfo({ ...customerInfo, address: e.target.value })}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                          required
                        >
                          <option value="">Seleccionar dirección con descuento</option>
                          <option value="Ctel. Oscar Quina 1333">Ctel. Oscar Quina 1333</option>
                          <option value="Ctel. Domeyco 1540">Ctel. Domeyco 1540</option>
                          <option value="Ctel. Av. Santa María 3000">Ctel. Av. Santa María 3000</option>
                        </select>
                      ) : (
                        <AddressAutocomplete
                          value={customerInfo.address || ''}
                          onChange={(address) => { setCustomerInfo({ ...customerInfo, address }); setDynamicDeliveryFee(null); setDeliveryFeeLabel(null); }}
                          placeholder="Ingresa tu dirección..."
                          onDeliveryFee={(data) => { if (data.delivery_fee != null) { setDynamicDeliveryFee(data.delivery_fee); setDeliveryFeeLabel(data.label); setDeliveryDistanceInfo({ km: data.distance_km, min: data.duration_min }); } }}
                        />
                      )}
                      {deliveryFeeLabel ? (
                        <p className="text-xs text-green-700 font-semibold mt-1">{deliveryFeeLabel}</p>
                      ) : nearbyTrucks.length > 0 && !customerInfo.deliveryDiscount && (
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
                    onChange={(e) => setCustomerInfo({ ...customerInfo, customerNotes: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 resize-y"
                    placeholder="Ej: sin cebolla, sin tomate, extra salsa..."
                    rows="1"
                    maxLength="400"
                  />
                </div>
              </div>

              <div className="border-t pt-4 mb-6">
                <h3 className="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                  <ShoppingCartIcon size={20} className="text-orange-500" />
                  Tu Pedido
                </h3>

                {/* Tarjeta orden TV */}
                {tvOrderId && customerInfo.deliveryType === 'tv' && (
                  <div style={{display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: 'linear-gradient(135deg, #1d4ed8, #3b82f6)', borderRadius: '10px', padding: '12px 16px', marginBottom: '12px'}}>
                    <div style={{color: 'white', fontWeight: 800, fontSize: '18px'}}>📺 Orden: #{tvOrderId}</div>
                    <button
                      onClick={async () => {
                        if (!confirm('¿Anular esta orden TV?')) return;
                        await fetch('/api/tv/update_order_status.php', {
                          method: 'POST',
                          headers: {'Content-Type': 'application/json'},
                          body: JSON.stringify({id: tvOrderId, status: 'cancelado'})
                        });
                        setTvOrderIdPersist(null);
                        setCart([]);
                        setCustomerInfo(prev => ({...prev, deliveryType: 'pickup'}));
                        setTvPendingCount(prev => Math.max(0, prev - 1));
                      }}
                      style={{background: '#ef4444', color: 'white', border: 'none', borderRadius: '8px', padding: '8px 14px', fontWeight: 700, fontSize: '13px', cursor: 'pointer'}}
                    >
                      Anular orden
                    </button>
                  </div>
                )}
                <div className="space-y-3 mb-4">
                  {cart.map((item, index) => {
                    const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
                    const isPYACash = selectedPaymentMethod === 'pedidosya_cash';
                    const basePrice = isPYACash && item.pedidosya_price ? parseFloat(item.pedidosya_price) : item.price;
                    let itemTotal = basePrice * item.quantity;

                    // Sumar personalizaciones regulares
                    if (item.customizations && item.customizations.length > 0) {
                      itemTotal += item.customizations.reduce((sum, c) => {
                        const cPrice = isPYACash && c.pedidosya_price ? parseFloat(c.pedidosya_price) : c.price;
                        return sum + (cPrice * c.quantity);
                      }, 0);
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
                          <div className="text-right">
                            {isPYACash && item.pedidosya_price ? (
                              <>
                                <p className="font-semibold text-orange-600 text-sm">${itemTotal.toLocaleString('es-CL')}</p>
                                <p className="text-xs text-gray-400 line-through">${(item.price * item.quantity).toLocaleString('es-CL')}</p>
                              </>
                            ) : (
                              <p className="font-semibold text-gray-800 text-sm">${itemTotal.toLocaleString('es-CL')}</p>
                            )}
                          </div>
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
                    <span className="text-sm text-gray-600">Subtotal:</span>
                    <span className="text-sm font-semibold text-gray-900">${(selectedPaymentMethod === 'pedidosya_cash' ? cartSubtotalPYA : cartSubtotal).toLocaleString('es-CL')}</span>
                  </div>
                  {selectedPaymentMethod === 'pedidosya_cash' && cartSubtotalPYA !== cartSubtotal && (
                    <div className="flex justify-between items-center bg-orange-50 -mx-2 px-3 py-1.5 rounded-lg">
                      <span className="text-orange-700 text-xs font-medium">🛵 Precio PedidosYA Efectivo aplicado</span>
                      <span className="text-orange-700 text-xs line-through">${cartSubtotal.toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  {customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 && (
                    <div className="bg-gray-50 -mx-2 px-3 py-2.5 rounded-lg space-y-1.5">
                      <div className="flex justify-between items-center">
                        <span className="text-sm text-gray-600 flex items-center gap-1.5">
                          <Bike size={15} className="text-orange-500" /> Delivery:
                        </span>
                        <span className={customerInfo.deliveryDiscount ? "text-sm line-through text-gray-400" : "text-sm font-semibold"}>
                          ${baseDeliveryFee.toLocaleString('es-CL')}
                        </span>
                      </div>
                      {customerInfo.deliveryDiscount && (
                        <div className="flex justify-between items-center ml-6 text-green-600">
                          <span className="text-xs">↳ Desc. Delivery (28%):</span>
                          <span className="text-xs font-semibold">-${deliveryDiscountAmount.toLocaleString('es-CL')}</span>
                        </div>
                      )}
                      {selectedPaymentMethod === 'card' && (
                        <div className="flex justify-between items-center ml-6 text-red-600">
                          <span className="text-xs">↳ 💳 Recargo tarjeta:</span>
                          <span className="text-xs font-semibold">+$500</span>
                        </div>
                      )}
                      {deliveryDistanceInfo && (
                        <p className="text-[10px] text-gray-400 ml-6">
                          📍 {deliveryDistanceInfo.km} km · ~{deliveryDistanceInfo.min} min
                        </p>
                      )}
                      {(customerInfo.deliveryDiscount || selectedPaymentMethod === 'card') && (
                        <div className="flex justify-between items-center pt-1.5 border-t border-gray-200">
                          <span className="text-xs font-bold text-gray-700 uppercase tracking-wide">Total Delivery:</span>
                          <span className="text-sm font-bold text-gray-900">${(deliveryFee + (selectedPaymentMethod === 'card' ? 500 : 0)).toLocaleString('es-CL')}</span>
                        </div>
                      )}
                    </div>
                  )}
                  {customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount && (
                    <div className="flex justify-between items-center bg-green-50 -mx-2 px-3 py-1.5 rounded-lg">
                      <span className="text-green-700 text-xs font-medium">🎉 Desc. R11 (10%):</span>
                      <span className="text-green-700 text-sm font-semibold">-${Math.round(cartSubtotal * 0.1).toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  {customerInfo.discount30 && (
                    <div className="flex justify-between items-center bg-yellow-50 -mx-2 px-3 py-1.5 rounded-lg">
                      <span className="text-yellow-700 text-xs font-medium">⭐ Desc. 30%:</span>
                      <span className="text-yellow-700 text-sm font-semibold">-${Math.round(cartSubtotal * 0.3).toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  {customerInfo.birthdayDiscount && cart.some(item => item.id === 9) && (
                    <div className="flex justify-between items-center bg-pink-50 -mx-2 px-3 py-1.5 rounded-lg">
                      <span className="text-pink-700 text-xs font-medium">🎂 Desc. Cumpleaños:</span>
                      <span className="text-pink-700 text-sm font-semibold">-${cart.find(item => item.id === 9).price.toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  {discountCode === 'PIZZA11' && cart.some(item => item.id === 231) && (
                    <div className="flex justify-between items-center bg-orange-50 -mx-2 px-3 py-1.5 rounded-lg">
                      <span className="text-orange-700 text-xs font-medium">🍕 Desc. Pizza11 (20%):</span>
                      <span className="text-orange-700 text-sm font-semibold">-${Math.round(cart.find(item => item.id === 231).price * 0.2).toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  <div className="flex justify-between items-center border-t-2 border-orange-200 pt-3 mt-1">
                    <span className="text-lg font-black text-gray-800">Total:</span>
                    <span className="text-xl font-black text-orange-500">${(() => {
                      const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
                      const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.7143) : baseDeliveryFee;
                      const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
                      const discount30Amount = customerInfo.discount30 ? Math.round(cartSubtotal * 0.3) : 0;
                      const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
                      const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
                      const surcharge = customerInfo.deliveryType === "delivery" && selectedPaymentMethod === "card" ? 500 : 0;
                      const effectiveSubtotal = selectedPaymentMethod === 'pedidosya_cash' ? cartSubtotalPYA : cartSubtotal;
                      return (effectiveSubtotal + deliveryFee + surcharge - pickupDiscountAmount - discount30Amount - birthdayDiscountAmount - pizzaDiscountAmount).toLocaleString('es-CL');
                    })()}</span>
                  </div>
                </div>
              </div>

              <div>
                <h4 className="text-sm font-bold bg-yellow-400 text-black px-3 py-2 rounded-lg mb-3">Finaliza Eligiendo Método de Pago</h4>
                {checkoutErrors.length > 0 && (
                  <div className="bg-red-50 border border-red-300 rounded-lg px-3 py-2 mb-3 animate-shake">
                    <p className="text-red-700 text-xs font-semibold">⚠️ Completa los datos para continuar:</p>
                    {checkoutErrors.map((err, i) => (
                      <p key={i} className="text-red-600 text-xs ml-3">• {err}</p>
                    ))}
                  </div>
                )}
                <div className="grid grid-cols-4 gap-2 mb-3">
                  <button
                    onClick={() => {
                      const errors = [];
                      if (!customerInfo.name) errors.push('Nombre del cliente');
                      if (customerInfo.deliveryType === 'delivery' && !customerInfo.address) errors.push('Dirección de entrega');
                      if (errors.length > 0) { setCheckoutErrors(errors); setTimeout(() => setCheckoutErrors([]), 3000); return; }
                      setCheckoutErrors([]);
                      setSelectedPaymentMethod('cash');
                      setShowCashModal(true);
                      setCashAmount('');
                      setCashStep('input');
                    }}
                    
                    className={`disabled:bg-gray-300 disabled:text-gray-500 border-2 disabled:cursor-not-allowed font-medium py-2 px-1 rounded-lg transition-all text-xs flex flex-col items-center justify-center gap-1 ${selectedPaymentMethod === 'cash'
                      ? 'bg-green-500 hover:bg-green-600 text-white border-green-500'
                      : 'bg-white hover:bg-gray-50 text-gray-700 border-gray-300'
                      }`}
                  >
                    <Banknote size={16} />
                    <span>Efectivo</span>
                  </button>
                  <button
                    onClick={() => {
                      const errors = [];
                      if (!customerInfo.name) errors.push('Nombre del cliente');
                      if (customerInfo.deliveryType === 'delivery' && !customerInfo.address) errors.push('Dirección de entrega');
                      if (errors.length > 0) { setCheckoutErrors(errors); setTimeout(() => setCheckoutErrors([]), 3000); return; }
                      setCheckoutErrors([]);
                      setSelectedPaymentMethod('card');
                    }}
                    className={`border-2 font-medium py-2 px-1 rounded-lg transition-all text-xs flex flex-col items-center justify-center gap-1 ${selectedPaymentMethod === 'card'
                      ? 'bg-purple-500 hover:bg-purple-600 text-white border-purple-500'
                      : 'bg-white hover:bg-gray-50 text-gray-700 border-gray-300'
                      }`}
                  >
                    <CreditCard size={16} />
                    <span>Tarjeta</span>
                  </button>
                  <button
                    onClick={() => {
                      const errors = [];
                      if (!customerInfo.name) errors.push('Nombre del cliente');
                      if (customerInfo.deliveryType === 'delivery' && !customerInfo.address) errors.push('Dirección de entrega');
                      if (errors.length > 0) { setCheckoutErrors(errors); setTimeout(() => setCheckoutErrors([]), 3000); return; }
                      setCheckoutErrors([]);
                      setSelectedPaymentMethod('transfer');
                    }}
                    className={`border-2 font-medium py-2 px-1 rounded-lg transition-all text-xs flex flex-col items-center justify-center gap-1 ${selectedPaymentMethod === 'transfer'
                      ? 'bg-blue-500 hover:bg-blue-600 text-white border-blue-500'
                      : 'bg-white hover:bg-gray-50 text-gray-700 border-gray-300'
                      }`}
                  >
                    <Smartphone size={16} />
                    <span>Transfer.</span>
                  </button>
                  <button
                    onClick={() => {
                      const errors = [];
                      if (!customerInfo.name) errors.push('Nombre del cliente');
                      if (customerInfo.deliveryType === 'delivery' && !customerInfo.address) errors.push('Dirección de entrega');
                      if (errors.length > 0) { setCheckoutErrors(errors); setTimeout(() => setCheckoutErrors([]), 3000); return; }
                      setCheckoutErrors([]);
                      setShowPedidosYAModal(true);
                    }}
                    className={`border-2 font-medium py-2 px-1 rounded-lg transition-all text-xs flex flex-col items-center justify-center gap-1 ${selectedPaymentMethod === 'pedidosya' || selectedPaymentMethod === 'pedidosya_cash'
                      ? 'bg-orange-500 hover:bg-orange-600 text-white border-orange-500'
                      : 'bg-white hover:bg-gray-50 text-gray-700 border-gray-300'
                      }`}
                  >
                    <Bike size={16} />
                    <span>PedidosYA</span>
                  </button>
                </div>

                {selectedPaymentMethod && selectedPaymentMethod !== 'cash' && (
                  <button
                    onClick={async () => {
                      if (isProcessingOrder) return;
                      setIsProcessingOrder(true);
                      try {
                        const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
                        const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.7143) : baseDeliveryFee;
                        const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
                        const discount30Amount = customerInfo.discount30 ? Math.round(cartSubtotal * 0.3) : 0;
                        const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
                        const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
                        const cardSurcharge = selectedPaymentMethod === 'card' && customerInfo.deliveryType === 'delivery' ? 500 : 0;
                        const effectiveSubtotal = selectedPaymentMethod === 'pedidosya_cash' ? cartSubtotalPYA : cartSubtotal;
                        const finalTotal = effectiveSubtotal + deliveryFee + cardSurcharge - pickupDiscountAmount - discount30Amount - birthdayDiscountAmount - pizzaDiscountAmount;
                        const redirectMap = { card: '/card-pending', transfer: '/transfer-pending', pedidosya: '/pedidosya-pending', pedidosya_cash: '/pedidosya-pending' };
                        const orderData = {
                          amount: finalTotal,
                          customer_name: customerInfo.name,
                          customer_phone: customerInfo.phone,
                          customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
                          user_id: user?.id || null,
                          cart_items: cart,
                          delivery_fee: deliveryFee + cardSurcharge,
                          delivery_discount: customerInfo.deliveryDiscount ? baseDeliveryFee - deliveryFee : 0,
                          discount_amount: pickupDiscountAmount + discount30Amount + birthdayDiscountAmount + pizzaDiscountAmount,
                          discount_10: pickupDiscountAmount,
                          discount_30: discount30Amount,
                          discount_birthday: birthdayDiscountAmount,
                          discount_pizza: pizzaDiscountAmount,
                          customer_notes: customerInfo.customerNotes || (cardSurcharge ? '+$500 recargo tarjeta delivery' : null),
                          delivery_type: customerInfo.deliveryType,
                          delivery_address: customerInfo.address || null,
                          payment_method: selectedPaymentMethod,
                          tv_order_id: tvOrderId || null,
                          delivery_distance_km: deliveryDistanceInfo?.km || null,
                          delivery_duration_min: deliveryDistanceInfo?.min || null
                        };
                        const response = await fetch('/api/create_order.php', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify(orderData)
                        });
                        const text = await response.text();
                        let result;
                        try { result = JSON.parse(text); } catch (e) {
                          console.error('API response not JSON:', text.substring(0, 200));
                          alert('❌ Error del servidor. Intenta de nuevo.');
                          setIsProcessingOrder(false);
                          setSelectedPaymentMethod(null);
                          return;
                        }
                        if (result.success) {
                          localStorage.removeItem('ruta11_cart');
                          localStorage.removeItem('ruta11_cart_total');
                          const redirectUrl = `${redirectMap[selectedPaymentMethod]}?order=${encodeURIComponent(result.order_id)}`;
                          window.location.assign(redirectUrl);
                        } else {
                          alert('❌ Error al crear orden: ' + (result.error || 'Error desconocido'));
                          setIsProcessingOrder(false);
                          setSelectedPaymentMethod(null);
                        }
                      } catch (error) {
                        alert('❌ Error al procesar el pago: ' + error.message);
                        setIsProcessingOrder(false);
                        setSelectedPaymentMethod(null);
                      }
                    }}
                    disabled={isProcessingOrder}
                    className="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 disabled:opacity-70 text-white font-bold py-3 px-4 rounded-lg transition-all shadow-lg flex items-center justify-center gap-2 mt-2"
                  >
                    {isProcessingOrder ? (
                      <><div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white" /><span>Procesando...</span></>
                    ) : (
                      <>✅ Confirmar Pedido</>
                    )}
                  </button>
                )}
                <p className="text-xs text-green-700 text-center mt-2">🔒 Todos los métodos son seguros</p>
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

      {/* PedidosYA Method Modal - Online/Efectivo */}
      {showPedidosYAModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" role="dialog" aria-modal="true">
          <div className="bg-white rounded-lg shadow-xl max-w-sm w-full p-6">
            <h3 className="text-xl font-bold text-gray-800 mb-4 text-center">🛵 PedidosYA - Método de Pago</h3>
            <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
              <p className="text-sm text-gray-600 mb-1">Total caja:</p>
              <p className="text-xl font-bold text-gray-700">${(cartTotal || 0).toLocaleString('es-CL')}</p>
              {cartSubtotalPYA !== cartSubtotal && (
                <>
                  <p className="text-sm text-gray-600 mb-1 mt-2">Total PedidosYA (efectivo):</p>
                  <p className="text-3xl font-bold text-orange-600">${(cartSubtotalPYA || 0).toLocaleString('es-CL')}</p>
                </>
              )}
            </div>
            <div className="grid grid-cols-2 gap-3 mb-4">
              <button
                onClick={() => { setShowPedidosYAModal(false); setSelectedPaymentMethod('pedidosya'); }}
                className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-4 rounded-lg transition-colors text-base"
              >
                🌐 Online
              </button>
              <button
                onClick={() => { setShowPedidosYAModal(false); setSelectedPaymentMethod('pedidosya_cash'); }}
                className="bg-green-600 hover:bg-green-700 text-white font-bold py-4 px-4 rounded-lg transition-colors text-base"
              >
                💵 Efectivo
              </button>
            </div>
            <button
              onClick={() => setShowPedidosYAModal(false)}
              className="w-full bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-4 rounded-lg transition-colors"
            >
              Cancelar
            </button>
          </div>
        </div>
      )}

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
                    const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.7143) : baseDeliveryFee;
                    const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
                    const discount30Amount = customerInfo.discount30 ? Math.round(cartSubtotal * 0.3) : 0;
                    const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
                    const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
                    const surcharge3752 = customerInfo.deliveryType === "delivery" && selectedPaymentMethod === "card" ? 500 : 0; return (cartSubtotal + deliveryFee + surcharge3752 - pickupDiscountAmount - discount30Amount - birthdayDiscountAmount - pizzaDiscountAmount).toLocaleString('es-CL');
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
                      const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.7143) : baseDeliveryFee;
                      const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount ? Math.round(cartSubtotal * 0.1) : 0;
                      const discount30Amount = customerInfo.discount30 ? Math.round(cartSubtotal * 0.3) : 0;
                      const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9) ? cart.find(item => item.id === 9).price : 0;
                      const pizzaDiscountAmount = discountCode === 'PIZZA11' && cart.some(item => item.id === 231) ? Math.round(cart.find(item => item.id === 231).price * 0.2) : 0;
                      const surcharge3830 = customerInfo.deliveryType === "delivery" && selectedPaymentMethod === "card" ? 500 : 0; return (cartSubtotal + deliveryFee + surcharge3830 - pickupDiscountAmount - discount30Amount - birthdayDiscountAmount - pizzaDiscountAmount).toLocaleString('es-CL');
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
                          const deliveryFee = customerInfo.deliveryDiscount ? Math.round(baseDeliveryFee * 0.7143) : baseDeliveryFee;
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
          <div className="bg-gradient-to-r from-orange-500 to-red-500 text-white p-6 shadow-lg" style={{ paddingTop: 'max(1.5rem, env(safe-area-inset-top))' }}>
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
              <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <button
                  onClick={() => setInfoExpanded(!infoExpanded)}
                  className="w-full p-6 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <Truck size={24} className="text-orange-600" />
                    <div className="text-left">
                      <h4 className="text-xl font-bold text-gray-800">Información del Local</h4>
                      <p className="text-sm text-gray-600">Datos y configuración del food truck</p>
                    </div>
                  </div>
                  <ChevronDown size={24} className={`text-gray-400 transition-transform ${infoExpanded ? 'rotate-180' : ''
                    }`} />
                </button>

                {infoExpanded && (
                  <div className="p-6 pt-0">
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
                          if (!editMode) setTempTruckData({ ...truckStatus });
                        }}
                        className="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors flex items-center gap-2"
                      >
                        {editMode ? <X size={18} /> : <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" /><path d="m15 5 4 4" /></svg>}
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
                          <span>Horario: {truckStatus.horario_inicio.slice(0, 5)} - {truckStatus.horario_fin.slice(0, 5)}</span>
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
                              onChange={(e) => setTempTruckData({ ...tempTruckData, direccion: e.target.value })}
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
                              value={tempTruckData?.horario_inicio?.slice(0, 5) || ''}
                              onChange={(e) => setTempTruckData({ ...tempTruckData, horario_inicio: e.target.value + ':00' })}
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                            />
                          </div>
                          <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Hora Fin</label>
                            <input
                              type="time"
                              value={tempTruckData?.horario_fin?.slice(0, 5) || ''}
                              onChange={(e) => setTempTruckData({ ...tempTruckData, horario_fin: e.target.value + ':00' })}
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                            />
                          </div>
                        </div>

                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Tarifa Delivery ($)</label>
                          <input
                            type="number"
                            value={tempTruckData?.tarifa_delivery || ''}
                            onChange={(e) => setTempTruckData({ ...tempTruckData, tarifa_delivery: e.target.value })}
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
                )}
              </div>

              {/* Switch de Estado con Swipe */}
              <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <button
                  onClick={() => setStatusExpanded(!statusExpanded)}
                  className="w-full p-6 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <Settings size={24} className="text-green-600" />
                    <div className="text-left">
                      <h4 className="text-xl font-bold text-gray-800">Estado Actual</h4>
                      <p className="text-sm text-gray-600">Desliza para cambiar el estado del local</p>
                    </div>
                  </div>
                  <ChevronDown size={24} className={`text-gray-400 transition-transform ${statusExpanded ? 'rotate-180' : ''
                    }`} />
                </button>

                {statusExpanded && (
                  <div className="p-6 pt-0">
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
                            setTruckStatus({ ...truckStatus, activo: newStatus ? 1 : 0 });
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
                )}
              </div>

              {/* Horarios por día */}
              <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <button
                  onClick={() => setSchedulesExpanded(!schedulesExpanded)}
                  className="w-full p-6 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <Clock size={24} className="text-purple-600" />
                    <div className="text-left">
                      <h4 className="text-xl font-bold text-gray-800">Horarios por Día</h4>
                      <p className="text-sm text-gray-600">Configura horarios específicos para cada día</p>
                    </div>
                  </div>
                  <ChevronDown size={24} className={`text-gray-400 transition-transform ${schedulesExpanded ? 'rotate-180' : ''
                    }`} />
                </button>

                {schedulesExpanded && (
                  <div className="p-6 pt-0">
                    <div className="flex items-center justify-end mb-4">
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
                          <div key={schedule.day_of_week} className={`p-3 rounded-lg border-2 transition-all ${isToday ? 'border-orange-500 bg-orange-50' : 'border-gray-200 bg-gray-50'
                            }`}>
                            <div className="flex items-center justify-between">
                              <div className="flex items-center gap-3">
                                <span className={`font-semibold ${isToday ? 'text-orange-600' : 'text-gray-800'
                                  }`}>
                                  {dayName}
                                  {isToday && <span className="ml-2 text-xs bg-orange-500 text-white px-2 py-0.5 rounded-full">HOY</span>}
                                </span>
                              </div>

                              {!editingSchedules ? (
                                <div className="flex items-center gap-2 text-sm text-gray-700">
                                  <Clock size={14} />
                                  <span>{schedule.horario_inicio.slice(0, 5)} - {schedule.horario_fin.slice(0, 5)}</span>
                                </div>
                              ) : (
                                <div className="flex items-center gap-2">
                                  <input
                                    type="time"
                                    value={schedule.horario_inicio.slice(0, 5)}
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
                                    value={schedule.horario_fin.slice(0, 5)}
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
                )}
              </div>

              {/* Gestión de Categorías del Menú */}
              <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <button
                  onClick={() => setCategoriesExpanded(!categoriesExpanded)}
                  className="w-full p-6 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <Package size={24} className="text-orange-600" />
                    <div className="text-left">
                      <h4 className="text-xl font-bold text-gray-800">Gestión de Categorías del Menú</h4>
                      <p className="text-sm text-gray-600">Controla las categorías visibles en el menú</p>
                    </div>
                  </div>
                  <ChevronDown size={24} className={`text-gray-400 transition-transform ${categoriesExpanded ? 'rotate-180' : ''
                    }`} />
                </button>

                {categoriesExpanded && (
                  <div className="p-6 pt-0 space-y-3">
                    {menuCategories.map(cat => (
                      <div key={cat.id} className="bg-gray-50 rounded-lg p-4 border-2 border-gray-200">
                        <div className="flex items-center justify-between mb-2">
                          <div className="flex items-center gap-3">
                            <span className="text-2xl">{cat.icon}</span>
                            <span className="font-semibold text-gray-800">{cat.display_name}</span>
                          </div>
                          <button
                            onClick={async () => {
                              try {
                                const res = await fetch('/api/update_menu_categories.php', {
                                  method: 'POST',
                                  headers: { 'Content-Type': 'application/json' },
                                  body: JSON.stringify({
                                    category_id: cat.id,
                                    is_active: cat.is_active ? 0 : 1
                                  })
                                });
                                const data = await res.json();
                                if (data.success) {
                                  const catRes = await fetch('/api/get_menu_structure.php');
                                  const catData = await catRes.json();
                                  if (catData.success) setMenuCategories(catData.categories);
                                  vibrate(30);
                                }
                              } catch (error) {
                                console.error('Error:', error);
                              }
                            }}
                            className={`px-4 py-2 rounded-lg font-medium transition-colors ${cat.is_active
                              ? 'bg-green-100 text-green-700 hover:bg-green-200'
                              : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                              }`}
                          >
                            {cat.is_active ? 'Visible' : 'Oculta'}
                          </button>
                        </div>
                        {cat.subcategories && cat.subcategories.length > 0 && (
                          <div className="ml-8 mt-2 space-y-1">
                            {cat.subcategories.map(sub => (
                              <div key={sub.id} className="flex items-center justify-between text-sm py-1">
                                <span className="text-gray-600">{sub.display_name}</span>
                                <span className={`px-2 py-1 rounded text-xs ${sub.is_active ? 'bg-green-50 text-green-600' : 'bg-gray-50 text-gray-400'
                                  }`}>
                                  {sub.is_active ? 'Activa' : 'Inactiva'}
                                </span>
                              </div>
                            ))}
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
        /* ===== ANIMATIONS ===== */
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
        
        html, body { background: #f5f5f5 !important; }
      `}</style>
    </div>
  );
}
