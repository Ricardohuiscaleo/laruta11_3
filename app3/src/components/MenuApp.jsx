import React, { useState, useMemo, useEffect, useRef, useCallback } from 'react';
import { 
    PlusCircle, X, Star, ShoppingCart, MinusCircle, Minus, User, ZoomIn,
    Award, ChefHat, GlassWater, CupSoda, Droplets,
    Eye, Heart, MessageSquare, Calendar, Search, Bike, Caravan, ChevronDown, ChevronUp,
    Truck, TruckIcon, Navigation, MapPin, Clock, CheckCircle2, XCircle, Pizza, Pencil, Smartphone, Share2
} from 'lucide-react';
import { GiHamburger, GiHotDog, GiFrenchFries, GiMeat, GiSandwich, GiSteak } from 'react-icons/gi';
import OnboardingModal from './OnboardingModal.jsx';
import LoadingScreen from './LoadingScreen.jsx';
import TUUPaymentIntegration from './TUUPaymentIntegration.jsx';
import ReviewsModal from './ReviewsModal.jsx';
import OrderNotifications from './OrderNotifications.jsx';
import MiniComandasCliente from './MiniComandasCliente.jsx';
import ProductDetailModal from './modals/ProductDetailModal.jsx';
import ProfileModalModern from './modals/ProfileModalModern.jsx';
import AuthModal from './modals/AuthModal.jsx';
import SecurityModal from './modals/SecurityModal.jsx';
import SaveChangesModal from './modals/SaveChangesModal.jsx';
import ProductQuickViewModal from './modals/ProductQuickViewModal.jsx';
import FloatingHeart from './ui/FloatingHeart.jsx';
import StarRating from './ui/StarRating.jsx';
import GoogleLogo from './ui/GoogleLogo.jsx';
import HotdogIcon from './ui/HotdogIcon.jsx';
import NotificationIcon from './ui/NotificationIcon.jsx';
import DynamicStatusMessage from './ui/DynamicStatusMessage.jsx';
import ShareProductModal from './modals/ShareProductModal.jsx';
import ComboModal from './modals/ComboModal.jsx';
import SwipeableModal from './SwipeableModal.jsx';
import useDoubleTap from '../hooks/useDoubleTap.js';
import { vibrate, playNotificationSound, createConfetti } from '../utils/effects.js';
import { validateCheckoutForm, getFormDisabledState } from '../utils/validation.js';



// Datos del menú - se cargarán dinámicamente desde MySQL
let menuData = {
  la_ruta_11: { tomahawks: [] },
  churrascos: { carne: [], pollo: [], vegetariano: [] },
  hamburguesas: { clasicas: [], especiales: [] },
  completos: { tradicionales: [], 'al vapor': [], papas: [] },
  papas_y_snacks: { papas: [], empanadas: [], jugos: [], bebidas: [], salsas: [] },
  Combos: { hamburguesas: [], sandwiches: [], completos: [] }
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
  hamburguesas: '#dc2626',
  hamburguesas_100g: '#dc2626',
  churrascos: '#dc2626',
  completos: '#dc2626',
  papas: '#dc2626',
  pizzas: '#dc2626',
  bebidas: '#dc2626',
  Combos: '#dc2626'
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
        <div className="fixed inset-0 bg-transparent z-[60] flex items-center justify-center animate-fade-in" onClick={onClose}>
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



const CartModal = ({ isOpen, onClose, cart, onAddToCart, onRemoveFromCart, cartTotal, onCheckout, onCustomizeProduct, nearbyTrucks = [] }) => {
    const [shake, setShake] = useState(false);
    const [lastDeleted, setLastDeleted] = useState(null);
    const [showUndo, setShowUndo] = useState(false);
    const [removingItems, setRemovingItems] = useState(new Set());
    const [currentSuggestion, setCurrentSuggestion] = useState(0);
    
    const suggestions = ['queso', 'carne', 'cebolla', 'tomate', 'palta', 'tocino'];
    
    const cartItemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    const handleCheckout = () => {
        if (cart.length === 0) {
            setShake(true);
            setTimeout(() => setShake(false), 500);
            return;
        }
        localStorage.setItem('ruta11_cart', JSON.stringify(cart));
        localStorage.setItem('ruta11_cart_total', cartTotal.toString());
        window.location.href = '/checkout';
    };
    
    const handleDeleteClick = (cartItemId) => {
        const item = cart.find(i => i.cartItemId === cartItemId);
        setLastDeleted({ item, cartItemId });
        setRemovingItems(prev => new Set([...prev, cartItemId]));
        setTimeout(() => {
            onRemoveFromCart(cartItemId);
            setRemovingItems(prev => {
                const newSet = new Set(prev);
                newSet.delete(cartItemId);
                return newSet;
            });
            setShowUndo(true);
            setTimeout(() => setShowUndo(false), 5000);
        }, 300);
    };
    
    const handleUndo = () => {
        if (lastDeleted) {
            onAddToCart(lastDeleted.item);
            setShowUndo(false);
            setLastDeleted(null);
        }
    };
    
    useEffect(() => {
        if (showUndo) {
            const timer = setTimeout(() => setShowUndo(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [showUndo]);
    
    useEffect(() => {
        const interval = setInterval(() => {
            setCurrentSuggestion(prev => (prev + 1) % suggestions.length);
        }, 2000);
        return () => clearInterval(interval);
    }, []);
    
    return (
        <>
        <SwipeableModal 
            isOpen={isOpen} 
            onClose={onClose}
            title={`Tu Pedido (${cartItemCount}) • TOTAL $${cartTotal.toLocaleString('es-CL')}`}
            className={shake ? 'animate-shake' : ''}
        >
                
                {cart.length === 0 ? (
                    <div className="flex-grow flex flex-col justify-center items-center text-gray-500">
                        <ShoppingCart size={48} className="mb-4" />
                        <p>Tu carrito está vacío.</p>
                    </div>
                ) : (
                    <div className="flex-grow overflow-y-auto space-y-3 bg-white px-4 py-4">
                        {cart.map((item, itemIndex) => {
                            const hasCustomizations = item.customizations && item.customizations.length > 0;
                            const customizationsTotal = hasCustomizations ? item.customizations.reduce((sum, c) => {
                                let price = c.price * c.quantity;
                                if (c.extraPrice && c.quantity > 1) {
                                    price = c.price + (c.quantity - 1) * c.extraPrice;
                                }
                                return sum + price;
                            }, 0) : 0;
                            const displayPrice = item.price + customizationsTotal;
                            const itemSubtotal = displayPrice * item.quantity;
                            const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
                            const nonPersonalizableCategories = ['Bebidas', 'Jugos', 'Té', 'Café', 'Salsas'];
                            const shouldShowPersonalizeButton = !nonPersonalizableCategories.includes(item.subcategory_name);
                            const isRemoving = removingItems.has(item.cartItemId);
                            
                            return (
                                <div key={item.cartItemId} className={`rounded-md bg-white border border-gray-200 transition-all duration-300 overflow-hidden ${
                                    isRemoving ? 'opacity-0 scale-95 -translate-x-full' : 'opacity-100 scale-100 translate-x-0'
                                }`}>
                                    <div className="bg-white p-3">
                                        <div className="flex items-start gap-3">
                                            {item.image ? (
                                                <img src={item.image} alt={item.name} className="w-16 h-16 object-cover rounded-lg flex-shrink-0" />
                                            ) : (
                                                <div className="w-16 h-16 bg-gray-200 rounded-md animate-pulse flex-shrink-0"></div>
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <p className="font-semibold text-gray-800 text-sm mb-1">{item.name}</p>
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="text-xs text-gray-600">
                                                        ${displayPrice.toLocaleString('es-CL')} × {item.quantity}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2 mb-2">
                                                    {shouldShowPersonalizeButton && (
                                                        <>
                                                            <button
                                                                onClick={() => {
                                                                    onClose();
                                                                    onCustomizeProduct(item, itemIndex);
                                                                }}
                                                                className="flex items-center gap-1 px-2 py-1 bg-yellow-400 hover:bg-yellow-500 rounded-lg transition-colors flex-shrink-0"
                                                                title={`Personalizar ${item.name}`}
                                                            >
                                                                <Pencil size={10} className="text-black" />
                                                                <span className="text-xs text-black font-bold">Personalizar</span>
                                                            </button>
                                                            <span className="text-xs text-gray-500 italic">
                                                                agrega: <span className="animate-fade-text" key={currentSuggestion}>{suggestions[currentSuggestion]}</span>
                                                            </span>
                                                        </>
                                                    )}
                                                </div>
                                                
                                                {(isCombo && item.selections) || hasCustomizations ? (
                                                    <div className="mt-2 pt-2 border-t border-gray-100">
                                                        {/* Items del combo */}
                                                        {(isCombo && (item.fixed_items || item.selections)) && (
                                                            <>
                                                                <p className="text-[10px] font-semibold text-gray-500 mb-1">ESTE COMBO INCLUYE:</p>
                                                                <div className="space-y-0.5 mb-2">
                                                                    {item.fixed_items && item.fixed_items.map((fixedItem, idx) => (
                                                                        <p key={idx} className="text-[11px] text-gray-600">• {item.quantity}x {fixedItem.product_name || fixedItem.name}</p>
                                                                    ))}
                                                                    {item.selections && Object.entries(item.selections).map(([group, selection]) => {
                                                                        if (Array.isArray(selection)) {
                                                                            return selection.map((sel, idx) => (
                                                                                <p key={`${group}-${idx}`} className="text-[11px] text-gray-600">• {item.quantity}x {sel.name}</p>
                                                                            ));
                                                                        } else {
                                                                            return (
                                                                                <p key={group} className="text-[11px] text-gray-600">• {item.quantity}x {selection.name}</p>
                                                                            );
                                                                        }
                                                                    })}
                                                                </div>
                                                            </>
                                                        )}
                                                        {/* Personalizaciones con precio adicional */}
                                                        {hasCustomizations && (
                                                            <>
                                                                <p className="text-[10px] font-semibold text-orange-600 mb-1">{isCombo ? 'ADEMÁS ESTÁ PERSONALIZADO CON:' : 'PERSONALIZADO CON:'}</p>
                                                                <div className="space-y-0.5">
                                                                    {item.customizations.map((custom, idx) => (
                                                                        <p key={idx} className="text-[11px] text-orange-600 font-medium">• {custom.quantity}x {custom.name} (+${(custom.price * custom.quantity).toLocaleString('es-CL')})</p>
                                                                    ))}
                                                                </div>
                                                            </>
                                                        )}
                                                    </div>
                                                ) : null}
                                            </div>
                                            {/* Botón eliminar directo */}
                                            <button
                                                onClick={() => handleDeleteClick(item.cartItemId)}
                                                className="w-10 h-10 flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-md transition-colors flex-shrink-0"
                                                title="Eliminar producto"
                                            >
                                                <X size={20} />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            )
                        })}
                    </div>
                )}
                
                {/* Footer Sticky */}
                <div className="bg-white border-t sticky bottom-0 px-4 py-4">
                    {nearbyTrucks.length > 0 && !nearbyTrucks[0].activo && (
                        <div className="bg-red-100 border border-red-300 rounded-lg p-3 text-center mb-3">
                            <p className="text-red-800 font-bold text-sm">⚠️ Cerrado por implementación de mejoras</p>
                            <p className="text-red-600 text-xs mt-1">
                                Contáctanos: 
                                <a href="https://wa.me/56936227422" target="_blank" rel="noopener noreferrer" className="underline font-semibold">WhatsApp</a>
                                {' '}·{' '}
                                <a href="https://www.instagram.com/la_ruta_11/" target="_blank" rel="noopener noreferrer" className="underline font-semibold">Instagram</a>
                            </p>
                        </div>
                    )}
                    <button 
                        onClick={handleCheckout}
                        disabled={nearbyTrucks.length > 0 && !nearbyTrucks[0].activo}
                        className={`w-full rounded-md font-black flex items-center justify-center gap-2 transition-all active:scale-[0.98] shadow-lg relative overflow-hidden py-4 text-lg ${
                            nearbyTrucks.length > 0 && !nearbyTrucks[0].activo
                                ? 'bg-gray-400 cursor-not-allowed text-white'
                                : 'bg-gradient-to-r from-green-500 to-green-600 text-white hover:from-green-600 hover:to-green-700'
                        }`}
                    >
                        {!(nearbyTrucks.length > 0 && !nearbyTrucks[0].activo) && (
                            <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent animate-shimmer"></div>
                        )}
                        <svg className="w-6 h-6 relative z-10" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                            <path fillRule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clipRule="evenodd"/>
                        </svg>
                        <span className="relative z-10">Ir a Pagar</span>
                    </button>
                </div>
            </SwipeableModal>
            
            {/* Toast de Undo */}
            {showUndo && (
                <div className="fixed bottom-24 left-1/2 transform -translate-x-1/2 z-[60] animate-slide-up">
                    <div className="bg-gray-900 text-white px-4 py-3 rounded-lg shadow-2xl flex items-center gap-3">
                        <span className="text-sm">Producto eliminado</span>
                        <button
                            onClick={handleUndo}
                            className="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-md text-sm font-semibold transition-colors"
                        >
                            Deshacer
                        </button>
                        <button
                            onClick={() => setShowUndo(false)}
                            className="text-gray-400 hover:text-white transition-colors"
                        >
                            <X size={16} />
                        </button>
                    </div>
                </div>
            )}
        </>
    );
};





const FoodTrucksModal = ({ isOpen, onClose, trucks, userLocation, deliveryZone, statusData }) => {
    if (!isOpen) return null;
    
    const openDirections = (truck) => {
        const url = `https://www.google.com/maps/dir/${userLocation?.latitude},${userLocation?.longitude}/${truck.latitud},${truck.longitud}`;
        window.open(url, '_blank');
    };
    
    return (
        <div className="fixed inset-0 bg-transparent z-50 flex justify-center items-center animate-fade-in" onClick={onClose}>
            <div className="bg-white w-full h-full flex flex-col animate-slide-up" onClick={(e) => e.stopPropagation()}>
                <div className="bg-gradient-to-r from-orange-500 to-orange-600 text-white flex justify-between items-center" style={{padding: 'clamp(12px, 3vw, 16px)'}}>
                    <h2 className="font-bold flex items-center gap-2" style={{fontSize: 'clamp(16px, 4vw, 20px)'}}>
                        <img src="/icon.ico" alt="La Ruta 11" className="w-5 h-5" />
                        Food Trucks Cercanos
                    </h2>
                    <button onClick={onClose} className="p-1 hover:bg-white/20 rounded-full transition-colors">
                        <X size={20} />
                    </button>
                </div>
                
                <div className="flex-grow overflow-y-auto">
                    {trucks.length > 0 && userLocation ? (
                        <div className="flex flex-col h-full">
                            {/* Mapa */}
                            <div className="flex-grow bg-gray-200 relative overflow-hidden">
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
                            
                            {/* Footer con info del truck */}
                            <div className="bg-white border-t shadow-lg p-4 pb-8">
                                {(() => {
                                    const truck = trucks[0];
                                    const now = new Date();
                                    const chileTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/Santiago' }));
                                    const hours = chileTime.getHours().toString().padStart(2, '0');
                                    const minutes = chileTime.getMinutes().toString().padStart(2, '0');
                                    const seconds = chileTime.getSeconds().toString().padStart(2, '0');
                                    const currentTime = `${hours}:${minutes}:${seconds}`;
                                    const dayOfWeek = chileTime.getDay();
                                    
                                    const scheduleText = statusData?.today_schedule ? 
                                        `${statusData.today_schedule.horario_inicio.slice(0,5)} - ${statusData.today_schedule.horario_fin.slice(0,5)}` :
                                        'Horario no disponible';
                                    
                                    return (
                                        <>
                                            <div className="flex justify-between items-start mb-3">
                                                <div className="flex items-start gap-2 flex-1">
                                                    <div className="bg-orange-100 p-2 rounded-lg">
                                                        <img src="/icon.ico" alt="" className="w-5 h-5" />
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <h3 className="font-bold text-gray-800 text-sm">{truck.nombre}</h3>
                                                        <p className="text-xs text-gray-500 mt-0.5">{truck.descripcion}</p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-1 text-orange-600 font-semibold text-sm bg-orange-50 px-2 py-1 rounded-lg flex-shrink-0">
                                                    <Navigation size={12} />
                                                    {truck.distance ? `${truck.distance.toFixed(1)} km` : '...'}
                                                </div>
                                            </div>
                                            
                                            <div className="flex items-center gap-1.5 text-xs text-gray-600 mb-3">
                                                <MapPin size={12} className="text-gray-400 flex-shrink-0" />
                                                <p className="line-clamp-1">{truck.direccion}</p>
                                            </div>
                                            
                                            <div className="flex flex-wrap items-center gap-2 mb-3">
                                                <div className="flex items-center gap-1 text-xs bg-gray-50 px-2 py-1.5 rounded-lg">
                                                    <Clock size={12} className="text-gray-500" />
                                                    <span className="text-gray-700 font-medium">
                                                        {scheduleText}
                                                    </span>
                                                </div>
                                                
                                                <span className={`px-2.5 py-1.5 rounded-lg text-xs font-medium flex items-center gap-1 ${
                                                    statusData && statusData.is_open ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                                }`}>
                                                    {statusData && statusData.is_open ? (
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
                                        </>
                                    );
                                })()}
                            </div>
                        </div>
                    ) : (
                        <div className="text-center py-12 p-4">
                            <div className="bg-gray-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                                <img src="/icon.ico" alt="" className="w-10 h-10 opacity-40" />
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

const NotificationsModal = ({ isOpen, onClose, notifications, onMarkAllRead }) => {
    const getStatusIcon = (status) => {
        switch (status) {
            case 'sent_to_kitchen': return '👨🍳';
            case 'preparing': return '🔥';
            case 'ready': return '✅';
            case 'delivered': return '🎉';
            default: return '📱';
        }
    };
    
    return (
        <>
            <div 
                className={`fixed inset-0 bg-transparent transition-opacity duration-300 z-50 ${
                    isOpen ? 'opacity-50' : 'opacity-0 pointer-events-none'
                }`}
                onClick={onClose}
            />
            <div 
                className={`fixed top-0 right-0 h-full w-full bg-white z-50 transform transition-transform duration-300 ease-out ${
                    isOpen ? 'translate-x-0' : 'translate-x-full'
                }`}
            >
                <div className="flex flex-col h-full">
                    <div className="border-b flex justify-between items-center p-4 bg-gradient-to-r from-orange-500 to-orange-600">
                        <h2 className="font-bold text-white flex items-center gap-2 text-lg">
                            <NotificationIcon size={20} />
                            Notificaciones
                        </h2>
                        <button onClick={onClose} className="p-1 text-white hover:text-orange-100"><X size={24} /></button>
                    </div>
                    
                    <div className="flex-1 overflow-y-auto p-4">
                    {notifications.length > 0 ? (
                        <div className="space-y-3">
                            {notifications.map((notif, index) => (
                                <div key={notif.id || index} className={`border-l-4 rounded-r-lg ${
                                    notif.is_read ? 'border-gray-300 bg-gray-50' : 'border-orange-500 bg-orange-50'
                                }`} style={{padding: 'clamp(8px, 2vw, 12px)'}}>
                                    <div className="flex items-start gap-2">
                                        <div className="text-lg">
                                            {getStatusIcon(notif.status)}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex justify-between items-start mb-1">
                                                <h4 className="font-semibold text-gray-800 truncate" style={{fontSize: 'clamp(11px, 2.5vw, 13px)'}}>
                                                    {notif.order_number}
                                                </h4>
                                                <span className="text-gray-500 ml-2 flex-shrink-0" style={{fontSize: 'clamp(9px, 2vw, 11px)'}}>
                                                    {new Date(notif.created_at).toLocaleTimeString('es-CL', {
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                        timeZone: 'America/Santiago'
                                                    })}
                                                </span>
                                            </div>
                                            <p className="text-gray-600 leading-tight" style={{fontSize: 'clamp(10px, 2.2vw, 12px)'}}>
                                                {notif.message}
                                            </p>
                                            {(notif.product_details || notif.product_name) && (
                                                <p className="text-gray-500 mt-1 leading-tight" style={{fontSize: 'clamp(9px, 2vw, 11px)'}}>
                                                    📦 {notif.product_details || notif.product_name}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <NotificationIcon size={44} className="text-gray-400 mx-auto mb-4" />
                            <p className="text-gray-600">No tienes notificaciones</p>
                            <p className="text-sm text-gray-500 mt-1">Te notificaremos sobre el estado de tus pedidos</p>
                        </div>
                    )}
                    </div>
                    
                    {notifications.length > 0 && (
                        <div className="p-4 border-t">
                            <button 
                                onClick={onMarkAllRead}
                                className="w-full text-sm text-orange-500 hover:text-orange-600 font-semibold"
                            >
                                Marcar todas como leídas
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
};




const MenuItem = ({ product, onSelect, onAddToCart, onRemoveFromCart, quantity, type, isLiked, handleLike, setReviewsModalProduct, onShare, setQuickViewProduct }) => {
  const [showFloatingHeart, setShowFloatingHeart] = useState(false);
  const [heartPosition, setHeartPosition] = useState({ x: 0, y: 0 });
  const heartButtonRef = useRef(null);
  
  useEffect(() => {
    if (window.Analytics) {
      window.Analytics.trackProductView(product.id, product.name);
    }
  }, [product.id, product.name]);
  
  const handleDoubleTap = useDoubleTap((e) => {
    e.stopPropagation();
    if (!isLiked) {
      const rect = e.currentTarget.getBoundingClientRect();
      setHeartPosition({
        x: rect.left + rect.width * 0.2,
        y: rect.bottom - 60
      });
      
      handleLike(product.id);
      setShowFloatingHeart(true);
      vibrate([50, 50, 50]);
    }
  });

  const handleAddToCart = (product) => {
    onAddToCart(product);
  };

  return (
    <div className="bg-white rounded-xl shadow-lg hover:shadow-xl overflow-hidden animate-fade-in transition-shadow duration-300 border border-gray-100">
      <div className="flex p-2">
        {/* Imagen - 45% izquierda con aspecto 4:5 */}
        <div 
          className="w-[45%] aspect-[4/5] cursor-pointer relative" 
          onClick={() => setQuickViewProduct(product)}
          onTouchStart={handleDoubleTap}
        >
          {product.image ? (
            <img 
              src={product.image} 
              alt={product.name} 
              className="w-full h-full object-cover rounded-lg"
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

        {/* Contenido - 55% derecha */}
        <div className="w-[55%] flex flex-col">
          <div className="p-3 flex-1">
            {/* Título */}
            <h3 className="text-sm font-bold text-gray-800 mb-2">{product.name}</h3>
            
            {/* Descripción completa */}
            <p className="text-xs text-gray-600 mb-3 flex-1">{product.description}</p>
            
            {/* Stats y precio */}
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3 text-xs">
                <button 
                  ref={heartButtonRef}
                  onClick={(e) => {
                    e.stopPropagation();
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
                  className="flex items-center gap-1 cursor-pointer transition-colors"
                >
                  <Heart size={12} className={`${isLiked ? 'text-red-500 fill-red-500' : 'text-red-500'}`} />
                  <span className="font-semibold text-gray-600">{product.likes}</span>
                </button>
                
                <button 
                  onClick={(e) => {
                    e.stopPropagation();
                    setReviewsModalProduct(product);
                  }}
                  className="flex items-center gap-1 cursor-pointer transition-colors"
                >
                  <MessageSquare size={12} className="text-gray-600" />
                  <span className="font-semibold text-gray-600">{product.reviews.count || 0}</span>
                </button>
                
                <button 
                  onClick={(e) => {
                    e.stopPropagation();
                    onShare(product);
                  }}
                  className="cursor-pointer transition-colors"
                  title="Compartir producto"
                >
                  <Share2 size={12} className="text-gray-600" />
                </button>
              </div>
              
              {/* Precio a la derecha - Estilo minimalista */}
              <span className="text-green-600 font-semibold text-xs">
                ${product.price.toLocaleString('es-CL')}
              </span>
            </div>
          </div>
          
          {/* Botón agregar - fila independiente */}
          <div className="flex items-center pl-2">
            {quantity > 0 && (
              <button
                onClick={() => onRemoveFromCart(product.id)}
                className="text-red-500 hover:text-red-600 transition-colors flex items-center justify-center rounded-lg px-1"
                style={{height: 'clamp(33.7px, 8.42vw, 43.8px)'}}
                aria-label={`Quitar ${product.name} del carro`}
              >
                <MinusCircle style={{width: 'clamp(16.85px, 4.21vw, 21.9px)', height: 'clamp(16.85px, 4.21vw, 21.9px)'}} />
              </button>
            )}
            {quantity > 0 && (
              <div className="text-gray-900 px-1 flex items-center justify-center min-w-[28px]" style={{height: 'clamp(33.7px, 8.42vw, 43.8px)'}}>
                <span className="font-bold" style={{fontSize: 'clamp(14.74px, 3.69vw, 19.16px)'}}>{quantity}</span>
              </div>
            )}
            <button 
              onClick={() => handleAddToCart(product)} 
              className={`flex-1 px-2 font-bold transition-all duration-200 flex items-center justify-center gap-1 rounded-lg ${
                quantity > 0 
                  ? 'bg-yellow-500 text-black hover:bg-yellow-600' 
                  : 'bg-green-500 hover:bg-green-600 text-white'
              }`}
              style={{height: 'clamp(33.7px, 8.42vw, 43.8px)'}}
              aria-label={`Agregar ${product.name} al carro`}
            >
              <PlusCircle style={{width: 'clamp(14.74px, 3.69vw, 19.16px)', height: 'clamp(14.74px, 3.69vw, 19.16px)'}} />
              <span className="font-bold" style={{fontSize: 'clamp(11px, 3.2vw, 19.16px)'}}>{quantity > 0 ? 'Agregar más' : 'Agregar'}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
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
  const [customerInfo, setCustomerInfo] = useState({ name: '', phone: '', email: '', address: '', deliveryType: 'pickup', pickupTime: '' });
  const [menuWithImages, setMenuWithImages] = useState(menuData);
  const [likedProducts, setLikedProducts] = useState(new Set());
  const [isLoading, setIsLoading] = useState(false);
  const [user, setUser] = useState(null);
  const [userOrders, setUserOrders] = useState([]);
  const [userStats, setUserStats] = useState(null);
  const [showAllOrders, setShowAllOrders] = useState(false);
  const [isProfileOpen, setIsProfileOpen] = useState(false);
  const [isFoodTrucksOpen, setIsFoodTrucksOpen] = useState(false);
  const [isNotificationsOpen, setIsNotificationsOpen] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
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
  const [isCategoriesVisible, setIsCategoriesVisible] = useState(true);
  const [isNavVisible, setIsNavVisible] = useState(true);
  const [lastScrollY, setLastScrollY] = useState(0);
  const [showOnboarding, setShowOnboarding] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [filteredProducts, setFilteredProducts] = useState([]);
  const [showSearchModal, setShowSearchModal] = useState(false);
  const [reviewsModalProduct, setReviewsModalProduct] = useState(null);
  const [shareModalProduct, setShareModalProduct] = useState(null);
  const [comboModalProduct, setComboModalProduct] = useState(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [myOrdersCount, setMyOrdersCount] = useState(0);
  const [isMyOrdersOpen, setIsMyOrdersOpen] = useState(false);
  const [isInfoModalOpen, setIsInfoModalOpen] = useState(false);
  const [schedules, setSchedules] = useState([]);
  const [schedulesLoading, setSchedulesLoading] = useState(true);
  const [statusData, setStatusData] = useState({
    is_open: false,
    current_time: new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Santiago' }),
    today_schedule: null
  });
  const [discountCode, setDiscountCode] = useState('');
  const [pizzaDiscount, setPizzaDiscount] = useState(0);
  const [isShareAppOpen, setIsShareAppOpen] = useState(false);
  const [showRegisterBanner, setShowRegisterBanner] = useState(!user);
  const sessionLoadedRef = useRef(false);
  const [isScrolledToEnd, setIsScrolledToEnd] = useState(false);
  const categoriesScrollRef = useRef(null);
  const [isReviewModalOpen, setIsReviewModalOpen] = useState(false);
  const [isInstagramModalOpen, setIsInstagramModalOpen] = useState(false);
  const [isWhatsAppModalOpen, setIsWhatsAppModalOpen] = useState(false);
  const [quickViewProduct, setQuickViewProduct] = useState(null);
  const [menuCategories, setMenuCategories] = useState([]);
  
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

  // Calcular descuento de pizza
  useEffect(() => {
    if (discountCode === 'PIZZA11') {
      // Buscar pizzas en el carrito
      const pizzaItems = cart.filter(item => 
        item.category_name === 'Pizzas & Snacks' && 
        item.subcategory_name === 'pizzas'
      );
      
      if (pizzaItems.length > 0) {
        const pizzaTotal = pizzaItems.reduce((sum, item) => sum + item.price, 0);
        const discount = Math.min(pizzaTotal * 0.15, 3000); // 15% máximo $3000
        setPizzaDiscount(discount);
      } else {
        setPizzaDiscount(0);
      }
    } else {
      setPizzaDiscount(0);
    }
  }, [discountCode, cart]);

  const handleCheckout = () => {
    if (!user) {
      setIsLoginOpen(true);
      return;
    }
    // Verificar si foodtruck está activo
    const isClosed = nearbyTrucks.length > 0 && nearbyTrucks[0].activo === false;
    if (isClosed) {
      return; // No hacer nada si está cerrado
    }
    // Guardar carrito y redirigir a checkout
    localStorage.setItem('ruta11_cart', JSON.stringify(cart));
    localStorage.setItem('ruta11_cart_total', cartTotal.toString());
    window.location.href = '/checkout';
  };

  const handleCreateOrder = async () => {
    if (isProcessing) return;
    
    setIsProcessing(true);
    try {
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
    } finally {
      setIsProcessing(false);
    }
  };

  const handlePaymentSuccess = (paymentData) => {
    if (isProcessing) return;
    
    setIsProcessing(true);
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
    setIsProcessing(false);
    
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
    console.log('🔍 [DEBUG] === CERRANDO SESIÓN ===');
    try {
      localStorage.removeItem('ruta11_user');
      console.log('✅ [DEBUG] Sesión eliminada de localStorage');
      // Verificar que se eliminó
      const verificacion = localStorage.getItem('ruta11_user');
      console.log('🔍 [DEBUG] Verificación eliminación:', verificacion ? 'AÚN EXISTE' : 'CONFIRMADO');
    } catch (error) {
      console.warn('⚠️ [DEBUG] No se pudo eliminar sesión:', error);
    }
    console.log('🔍 [DEBUG] Redirigiendo a logout.php...');
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
    if (!query.trim()) {
      setFilteredProducts([]);
      return;
    }
    
    const allProducts = [];
    const seenIds = new Set();
    
    Object.entries(menuWithImages).forEach(([categoryKey, category]) => {
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
      if (product.category === 'personalizar' || product.category === 'extras') return false;
      
      return product.name.toLowerCase().includes(query.toLowerCase()) ||
        product.description.toLowerCase().includes(query.toLowerCase()) ||
        categoryDisplayNames[product.category]?.toLowerCase().includes(query.toLowerCase()) ||
        product.subcategory?.toLowerCase().includes(query.toLowerCase());
    });
    
    setFilteredProducts(filtered);
  };

  const requestLocation = () => {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      alert('Tu navegador no soporta geolocalización');
      return;
    }

    setLocationPermission('requesting');
    
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        const { latitude, longitude } = position.coords;
        
        // Obtener dirección usando Google Geocoding API
        try {
          const formData = new FormData();
          formData.append('lat', latitude);
          formData.append('lng', longitude);
          
          const response = await fetch('/api/location/geocode.php', {
            method: 'POST',
            body: formData
          });
          
          const data = await response.json();
          
          let addressInfo;
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
            addressInfo = {
              formatted_address: `${latitude}, ${longitude}`,
              street: 'Calle no disponible',
              city: 'Ciudad no disponible',
              region: 'Región no disponible',
              country: 'País no disponible'
            };
          }
          
          const locationData = {
            latitude,
            longitude,
            address: addressInfo.formatted_address,
            addressInfo,
            accuracy: position.coords.accuracy
          };
          
          setUserLocation(locationData);
          setLocationPermission('granted');
          
          // Verificar zona de delivery
          checkDeliveryZone(latitude, longitude);
          
          // Obtener productos cercanos
          getNearbyProducts(latitude, longitude);
          
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
            });
          }
        } catch (error) {
          console.error('Error obteniendo dirección:', error);
          const fallbackData = {
            latitude, 
            longitude, 
            address: `${latitude}, ${longitude}`,
            addressInfo: {
              formatted_address: `${latitude}, ${longitude}`,
              street: 'Calle no disponible',
              city: 'Ciudad no disponible',
              region: 'Región no disponible',
              country: 'País no disponible'
            }
          };
          setUserLocation(fallbackData);
          setLocationPermission('granted');
        }
      },
      (error) => {
        console.error('Error obteniendo ubicación:', error);
        setLocationPermission('denied');
        alert('No se pudo obtener tu ubicación. Verifica los permisos del navegador.');
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 300000 // 5 minutos
      }
    );
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
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      
      const data = await response.json();
      if (data.error) {
        console.error('API Error:', data.error);
        setDeliveryZone({ in_delivery_zone: false, zones: [] });
      } else {
        setDeliveryZone(data);
      }
      
      // Calcular tiempo real si está en zona
      if (data.in_delivery_zone && data.zones.length > 0) {
        const zone = data.zones[0];
        
        // Obtener coordenadas del food truck más cercano
        if (nearbyTrucks.length > 0) {
          const closestTruck = nearbyTrucks[0];
          calculateRealDeliveryTime(lat, lng, closestTruck.latitud, closestTruck.longitud);
        }
      }
    } catch (error) {
      console.error('Error verificando zona de delivery:', error);
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
      
      const data = await response.json();
      setNearbyProducts(data);
    } catch (error) {
      console.error('Error obteniendo productos cercanos:', error);
    }
  };

  const getNearbyTrucks = async (lat, lng) => {
    try {
      const formData = new FormData();
      formData.append('lat', lat);
      formData.append('lng', lng);
      formData.append('radius', 10); // 10km radio
      
      const response = await fetch('/api/get_nearby_trucks.php', {
        method: 'POST',
        body: formData
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      
      const data = await response.json();
      if (data.success && data.trucks) {
        setNearbyTrucks(data.trucks);
      } else {
        console.error('API Error:', data.error || 'No trucks found');
        setNearbyTrucks([]);
      }
    } catch (error) {
      console.error('Error obteniendo food trucks cercanos:', error);
      setNearbyTrucks([]);
    }
  };

  const loadUserOrders = useCallback(async () => {
    if (!user?.email) return;
    
    try {
      const response = await fetch(`/api/get_user_orders.php?t=${Date.now()}`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Cache-Control': 'no-cache, no-store, must-revalidate',
          'Pragma': 'no-cache'
        },
        body: JSON.stringify({ user_email: user.email })
      });
      const data = await response.json();
      
      if (data.success && data.stats) {
        setUserOrders(data.orders || []);
        setUserStats(data.stats);
        console.log('✅ Stats frescos:', Math.floor(data.stats.total_spent / 1000), 'pts');
      }
    } catch (error) {
      console.error('Error cargando pedidos:', error);
      setUserOrders([]);
    }
  }, [user?.email]);

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
      
      const data = await response.json();
      if (data.success) {
        // Actualizar el tiempo en deliveryZone
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
      console.error('Error calculando tiempo real:', error);
    }
  };

  // Cargar productos desde MySQL - PRIORIDAD MÁXIMA
  useEffect(() => {
    // 1. CARGAR MENÚ INMEDIATAMENTE (sin esperar nada más)
    const loadMenuImmediately = async () => {
      try {
        console.log('🚀 Cargando menú inmediatamente...');
        const menuResponse = await fetch('/api/get_menu_products.php?v=' + Date.now());
        const menuData = await menuResponse.json();
        
        if (menuData.success && menuData.menuData) {
          console.log('✅ Menú cargado exitosamente');
          setMenuWithImages(menuData.menuData);
        } else {
          console.error('❌ Error cargando menú:', menuData.error);
          setMenuWithImages({
            la_ruta_11: { tomahawks: [] },
            churrascos: { carne: [], pollo: [], vegetariano: [] },
            hamburguesas: { clasicas: [], especiales: [] },
            completos: { tradicionales: [], 'al vapor': [], papas: [] },
            papas_y_snacks: { papas: [], empanadas: [], jugos: [], bebidas: [], salsas: [] },
            Combos: { hamburguesas: [], sandwiches: [], completos: [] }
          });
        }
      } catch (error) {
        console.error('❌ Error crítico cargando menú:', error);
        setMenuWithImages({
          la_ruta_11: { tomahawks: [] },
          churrascos: { carne: [], pollo: [], vegetariano: [] },
          hamburguesas: { clasicas: [], especiales: [] },
          completos: { tradicionales: [], 'al vapor': [], papas: [] },
          papas_y_snacks: { papas: [], empanadas: [], jugos: [], bebidas: [], salsas: [] },
          Combos: { hamburguesas: [], sandwiches: [], completos: [] }
        });
      }
    };
    
    // 2. CARGAR STATUS EN PARALELO (sin bloquear menú)
    const loadStatusData = async () => {
      try {
        console.log('🔄 Cargando status en background...');
        const statusResponse = await fetch('/api/get_status_data.php?v=' + Date.now());
        const statusData = await statusResponse.json();
        
        if (statusData.success) {
          console.log('✅ Status cargado exitosamente');
          setSchedules(statusData.schedules || []);
          setStatusData(statusData.status || {
            is_open: false,
            current_time: new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Santiago' }),
            today_schedule: null
          });
          setNearbyTrucks(statusData.trucks || []);
        }
      } catch (error) {
        console.error('⚠️ Error cargando status (no crítico):', error);
      } finally {
        setSchedulesLoading(false);
      }
    };
    
    // 3. CARGAR CATEGORÍAS DEL MENÚ
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
    
    // EJECUTAR INMEDIATAMENTE Y EN PARALELO
    loadMenuImmediately();
    loadStatusData();
    loadMenuCategories();
  }, []);

  const productsToShow = useMemo(() => {
    const menu = menuWithImages;
    if (activeCategory === 'churrascos' || activeCategory === 'completos' || activeCategory === 'hamburguesas_100g' || activeCategory === 'papas' || activeCategory === 'pizzas' || activeCategory === 'bebidas' || activeCategory === 'Combos') {
      return []; 
    }
    // Para hamburguesas 200g, filtrar solo especiales (subcat 6), excluyendo clásicas (subcat 5)
    if (activeCategory === 'hamburguesas') {
      const allProducts = [];
      Object.values(menu.hamburguesas || {}).forEach(subcat => {
        if (Array.isArray(subcat)) {
          allProducts.push(...subcat.filter(p => p.subcategory_id !== 5));
        }
      });
      return allProducts;
    }
    return Array.isArray(menu[activeCategory]) ? menu[activeCategory] : [];
  }, [activeCategory, menuWithImages]);

  const handleAddToCart = (product) => {
    // Abrir modal de combo para combos
    if (product.type === 'combo' || product.category_name === 'Combos') {
      setComboModalProduct(product);
      return;
    }
    
    // Todos los productos se agregan directamente al carrito
    vibrate(50);
    
    if (window.Analytics) {
      window.Analytics.trackAddToCart(product.id, product.name);
    }
    
    setCart(prevCart => [...prevCart, { 
      ...product, 
      quantity: 1, 
      customizations: null, 
      cartItemId: Date.now(),
      category_id: product.category_id,
      subcategory_id: product.subcategory_id
    }]);
  };
  
  const handleRemoveFromCart = (productIdOrCartItemId) => {
    // Verificar si es cartItemId (string con prefijo) o timestamp grande
    const isCartItemId = typeof productIdOrCartItemId === 'string' || 
                         (typeof productIdOrCartItemId === 'number' && productIdOrCartItemId > 1000000000000);
    
    if (isCartItemId) {
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
      const itemsOfProduct = cart.filter(item => item.id === productIdOrCartItemId);
      
      if (itemsOfProduct.length > 0) {
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
    return Math.max(0, cartSubtotal + currentDeliveryFee - pizzaDiscount);
  }, [cartSubtotal, customerInfo.deliveryType, nearbyTrucks, pizzaDiscount]);

  const cartItemCount = useMemo(() => cart.length, [cart]);
  const getProductQuantity = (productId) => cart.filter(item => item.id === productId).length;
  
  const comboItems = useMemo(() => ({
      papas_y_snacks: menuWithImages.papas?.papas || [],
      jugos: menuWithImages.papas_y_snacks?.jugos || [],
      bebidas: menuWithImages.papas_y_snacks?.bebidas || [],
      empanadas: menuWithImages.papas_y_snacks?.empanadas || [],
      cafe: menuWithImages.papas_y_snacks?.café || [],
      te: menuWithImages.papas_y_snacks?.té || [],
      salsas: menuWithImages.papas_y_snacks?.salsas || [],
      personalizar: menuWithImages.personalizar?.personalizar || [],
      extras: (menuWithImages.extras?.extras || []).filter(item => !(item.category_id === 7 && item.subcategory_id === 30)),
      deliveryExtras: (menuWithImages.extras?.extras || []).filter(item => item.category_id === 7 && item.subcategory_id === 30)
  }), [menuWithImages]);

  useEffect(() => {
    document.body.style.overflow = selectedProduct || isCartOpen || isLoginOpen || zoomedProduct || showSearchModal || isProfileOpen ? 'hidden' : 'auto';
  }, [selectedProduct, isCartOpen, isLoginOpen, zoomedProduct, showSearchModal, isProfileOpen]);

  useEffect(() => {
    let ticking = false;
    const handleScroll = () => {
      if (!ticking) {
        requestAnimationFrame(() => {
          const currentScrollY = window.scrollY;
          if (currentScrollY > lastScrollY && currentScrollY > 100) {
            setIsCategoriesVisible(false);
            setIsNavVisible(false);
          } else {
            setIsCategoriesVisible(true);
            setIsNavVisible(true);
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
    
    // Mostrar loader por 1.5 segundos y luego activar ubicación
    setIsLoading(true);
    const timer = setTimeout(() => {
      setIsLoading(false);
    }, 1500);
    
    // Auto-activar ubicación en paralelo
    const locationTimer = setTimeout(() => {
      if (typeof navigator !== 'undefined' && navigator.geolocation && locationPermission === 'prompt') {
        requestLocation();
      }
    }, 2000);
    
    return () => {
      clearTimeout(timer);
      clearTimeout(locationTimer);
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

  // Detectar producto compartido en URL o prop inicial
  useEffect(() => {
    let sharedProductId = null;
    
    // Detectar desde window.SHARED_PRODUCT_ID (páginas /p/[id])
    if (typeof window !== 'undefined' && window.SHARED_PRODUCT_ID) {
      sharedProductId = window.SHARED_PRODUCT_ID;
    } else {
      // Detectar desde URL params (?product=218)
      const urlParams = new URLSearchParams(window.location.search);
      sharedProductId = urlParams.get('product');
    }
    
    if (sharedProductId && Object.keys(menuWithImages).length > 0) {
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
        setQuickViewProduct(sharedProduct);
        // Solo limpiar URL si viene de parámetro, no de página /p/[id]
        if (!window.SHARED_PRODUCT_ID) {
          window.history.replaceState({}, document.title, window.location.pathname);
        }
      }
    }
  }, [menuWithImages]); // Se ejecuta cuando menuWithImages cambia

  useEffect(() => {
    // Prevenir ejecución duplicada en React StrictMode
    if (sessionLoadedRef.current) {
      console.log('⚠️ [DEBUG] Sesión ya cargada, saltando...');
      return;
    }
    sessionLoadedRef.current = true;
    
    // 1. CARGAR SESIÓN DESDE LOCALSTORAGE (instantáneo)
    console.log('🔍 [DEBUG] === INICIO CARGA DE SESIÓN ===');
    console.log('🔍 [DEBUG] Navegador:', navigator.userAgent);
    console.log('🔍 [DEBUG] localStorage disponible:', typeof localStorage !== 'undefined');
    
    try {
      const savedUser = localStorage.getItem('ruta11_user');
      console.log('🔍 [DEBUG] localStorage.getItem resultado:', savedUser ? 'EXISTE' : 'NULL');
      
      if (savedUser) {
        console.log('🔍 [DEBUG] Contenido localStorage (primeros 100 chars):', savedUser.substring(0, 100));
        try {
          const userData = JSON.parse(savedUser);
          console.log('✅ [DEBUG] Usuario parseado exitosamente:', userData.nombre, userData.email);
          setUser(userData);
          console.log('✅ [DEBUG] setUser() ejecutado');
          // Cargar datos relacionados inmediatamente
          loadNotifications();
          loadUserOrders();
        } catch (error) {
          console.error('❌ [DEBUG] Error parsing saved user:', error);
          localStorage.removeItem('ruta11_user');
        }
      } else {
        console.log('⚠️ [DEBUG] No hay usuario en localStorage');
      }
    } catch (error) {
      console.warn('⚠️ [DEBUG] localStorage no disponible (modo privado?):', error);
    }
    
    // 2. VERIFICAR SESIÓN CON SERVIDOR (en background, SIN BLOQUEAR UI)
    console.log('🔍 [DEBUG] Verificando sesión con servidor...');
    setTimeout(() => {
      fetch('/api/auth/check_session.php?v=' + Date.now())
        .then(response => {
          console.log('🔍 [DEBUG] Respuesta servidor status:', response.status);
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          console.log('🔍 [DEBUG] Datos del servidor:', data.authenticated ? 'AUTENTICADO' : 'NO AUTENTICADO');
          if (data.authenticated) {
            console.log('🔍 [DEBUG] Usuario del servidor:', data.user.nombre, data.user.email);
            // Actualizar con datos frescos del servidor
            setUser(data.user);
            try {
              localStorage.setItem('ruta11_user', JSON.stringify(data.user));
              console.log('✅ [DEBUG] Sesión guardada en localStorage');
              // Verificar que se guardó
              const verificacion = localStorage.getItem('ruta11_user');
              console.log('🔍 [DEBUG] Verificación guardado:', verificacion ? 'CONFIRMADO' : 'FALLÓ');
            } catch (error) {
              console.warn('⚠️ [DEBUG] No se pudo guardar en localStorage:', error);
            }
            
            // NO usar stats de check_session (pueden estar cacheados)
            // Recargar notificaciones y pedidos con datos frescos
            loadNotifications();
            loadUserOrders();
          } else {
            console.log('⚠️ [DEBUG] Sesión NO autenticada en servidor');
            // Sesión expiró en servidor, limpiar localStorage
            try {
              localStorage.removeItem('ruta11_user');
              console.log('🔍 [DEBUG] localStorage limpiado');
            } catch (error) {
              console.warn('⚠️ [DEBUG] No se pudo limpiar localStorage:', error);
            }
            setUser(null);
          }
        })
        .catch(error => {
          console.warn('❌ [DEBUG] Session check failed:', error.message);
          // Si falla la verificación pero hay usuario en localStorage, mantenerlo
        });
    }, 100); // Delay mínimo para no bloquear render inicial
    
    console.log('🔍 [DEBUG] === FIN CARGA DE SESIÓN ===');

    // Detectar parámetros de URL para login/logout
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.get('login') === 'success') {
      // Limpiar URL y recargar sesión
      window.history.replaceState({}, document.title, window.location.pathname);
      // Recargar datos de sesión sin reload completo
      fetch('/api/auth/check_session.php?v=' + Date.now())
        .then(response => response.json())
        .then(data => {
          if (data.authenticated) {
            setUser(data.user);
            try {
              localStorage.setItem('ruta11_user', JSON.stringify(data.user));
              console.log('✅ Login Google exitoso, sesión guardada');
            } catch (error) {
              console.warn('⚠️ No se pudo guardar sesión de Google:', error);
            }
            
            // NO usar stats de check_session
            loadNotifications();
            loadUserOrders();
            
            // Mostrar onboarding solo para usuarios completamente nuevos
            if (data.user.is_new_user && !localStorage.getItem('onboarding_completed')) {
              setTimeout(() => setShowOnboarding(true), 500);
            }
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
    <div className="bg-white font-sans min-h-screen w-full" style={{backgroundColor: '#ffffff', background: '#ffffff', boxShadow: 'none'}}>
      {/* Sidebar PC - Fixed left */}
      <aside className="hidden lg:flex lg:flex-col fixed left-0 top-0 bottom-0 w-64 bg-white border-r border-gray-200 z-40 overflow-y-auto">
        <div className="p-4 border-b flex-shrink-0">
          <img src="https://laruta11-images.s3.amazonaws.com/menu/logo.png" alt="La Ruta 11" className="w-10 h-10 mx-auto mb-2" />
          <div className="flex items-center justify-center gap-2">
            <h2 className="text-center font-black text-gray-800 text-sm">MENÚ</h2>
            {(() => {
              if (nearbyTrucks.length === 0 && schedulesLoading) {
                return null;
              }
              
              const isActive = nearbyTrucks.length > 0 ? nearbyTrucks[0].activo : true;
              const finalStatus = isActive && statusData.is_open;
              
              let statusText = '';
              let statusColor = '';
              let dotColor = '';
              
              if (finalStatus) {
                statusText = 'Abierto';
                statusColor = 'border-green-400 text-green-700 bg-green-50';
                dotColor = 'bg-green-500';
              } else if (statusData.status === 'opens_today' && statusData.next_open_time) {
                statusText = `Abre ${statusData.next_open_time}`;
                statusColor = 'border-yellow-400 text-yellow-700 bg-yellow-50';
                dotColor = 'bg-yellow-500';
              } else {
                statusText = 'Cerrado';
                statusColor = 'border-red-400 text-red-700 bg-red-50';
                dotColor = 'bg-red-500';
              }
              
              return (
                <span className={`px-1.5 py-0.5 rounded border text-[10px] flex items-center flex-shrink-0 ${statusColor}`}>
                  <span className={`inline-block w-1 h-1 rounded-full mr-1 ${dotColor}`}></span>
                  {statusText}
                </span>
              );
            })()}
          </div>
        </div>
        <nav className="p-3 flex-1 overflow-y-auto">
          {mainCategories.map(cat => (
            <button
              key={cat}
              onClick={() => { vibrate(30); setActiveCategory(cat); }}
              className={`w-full flex items-start gap-3 px-4 py-3 rounded-lg transition-all duration-200 mb-1.5 text-sm ${
                activeCategory === cat
                  ? 'bg-gradient-to-r from-orange-500 to-red-600 text-white shadow-md'
                  : 'text-gray-700 hover:bg-orange-50 hover:text-orange-600'
              }`}
            >
              <div className="flex items-center justify-center w-6 h-6 flex-shrink-0 mt-0.5" style={{color: activeCategory === cat ? 'white' : categoryColors[cat]}}>
                {categoryIcons[cat]}
              </div>
              <span className="font-bold leading-tight whitespace-pre-line text-left">{categoryDisplayNames[cat]}</span>
            </button>
          ))}
        </nav>
      </aside>
      {/* Modal popup de registro - PC */}
      {!user && (
        <div className="hidden lg:block fixed bottom-8 right-8 z-50 animate-fade-in">
          <div className="bg-gradient-to-r from-orange-500 to-red-600 rounded-2xl shadow-2xl p-6 max-w-sm relative overflow-hidden">
            <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent animate-shimmer" style={{animation: 'shimmer 3s infinite'}}></div>
            <div className="relative z-10">
              <div className="flex items-start justify-between mb-3">
                <div className="flex items-center gap-2">
                  <span className="text-4xl">🎉</span>
                  <h3 className="text-white font-black text-xl">Regístrate Ahora</h3>
                </div>
                <button
  onClick={(e) => e.currentTarget.closest('.fixed.bottom-8')?.remove()}
                  className="text-white/80 hover:text-white transition-colors"
                >
                  <X size={20} />
                </button>
              </div>
              <p className="text-white/90 text-sm mb-4 leading-relaxed">
                Únete y disfruta de <span className="font-bold">beneficios exclusivos</span>, puntos por cada compra y promociones especiales 🌭🍔
              </p>
              <button
                onClick={() => { vibrate(30); setIsLoginOpen(true); }}
                className="w-full bg-white text-orange-600 font-bold py-3 px-4 rounded-xl hover:bg-gray-100 transition-all shadow-lg hover:scale-105 active:scale-95"
              >
                Crear mi cuenta gratis 🚀
              </button>
            </div>
          </div>
        </div>
      )}
      
      <header className="py-1 sm:py-3 fixed top-0 left-0 right-0 bg-white z-40 border-b border-gray-100 lg:left-64">
        <div className="max-w-3xl sm:max-w-7xl mx-auto">
          {/* Vista móvil: Nueva estructura */}
          <div className="sm:hidden">
            {/* Fila 1: Con padding */}
            <div className="px-2">
            {/* Fila única: Logo | Status | Menú | Búsqueda | Notificaciones | Carrito */}
            <div className="flex items-center justify-between gap-2">
              {/* Logo */}
              <img src="https://laruta11-images.s3.amazonaws.com/menu/logo.png" alt="La Ruta 11" className="drop-shadow-sm flex-shrink-0" style={{width: 'clamp(32px, 8vw, 36px)', height: 'clamp(32px, 8vw, 36px)'}} />
              
              {/* Status */}
              {(() => {
                // Mostrar status inmediatamente si hay datos, sino mostrar "Cargando..."
                if (nearbyTrucks.length === 0 && schedulesLoading) {
                  return (
                    <span className="px-1.5 py-0.5 rounded border text-[10px] border-gray-300 text-gray-500 bg-gray-50">
                      <span className="inline-block w-1 h-1 rounded-full mr-1 bg-gray-400 animate-pulse"></span>
                      Cargando...
                    </span>
                  );
                }
                
                const isActive = nearbyTrucks.length > 0 ? nearbyTrucks[0].activo : true;
                const finalStatus = isActive && statusData.is_open;
                
                let statusText = '';
                let statusColor = '';
                let dotColor = '';
                
                if (finalStatus) {
                  statusText = 'Abierto';
                  statusColor = 'border-green-400 text-green-700 bg-green-50';
                  dotColor = 'bg-green-500';
                } else if (statusData.status === 'opens_today' && statusData.next_open_time) {
                  statusText = `Abre ${statusData.next_open_time}`;
                  statusColor = 'border-yellow-400 text-yellow-700 bg-yellow-50';
                  dotColor = 'bg-yellow-500';
                } else {
                  statusText = 'Cerrado';
                  statusColor = 'border-red-400 text-red-700 bg-red-50';
                  dotColor = 'bg-red-500';
                }
                
                return (
                  <span className={`px-1.5 py-0.5 rounded border text-[10px] flex items-center ${statusColor}`}>
                    <span className={`inline-block w-1 h-1 rounded-full mr-1 ${dotColor}`}></span>
                    {statusText}
                  </span>
                );
              })()}
              
              {/* Menú */}
              <span className="font-black text-gray-800 text-sm">MENÚ</span>
              
              {/* Share */}
              <button 
                onClick={() => { vibrate(30); setIsShareAppOpen(true); }}
                className="text-gray-600 hover:text-orange-500 transition-all p-1"
                title="Compartir app"
              >
                <Share2 size={24} />
              </button>
              
              {/* Búsqueda */}
              <button 
                onClick={() => { vibrate(30); setShowSearchModal(true); }}
                className="text-gray-600 hover:text-orange-500 transition-all p-1"
                title="Buscar"
              >
                <Search size={24} />
              </button>
              
              {/* Notificaciones */}
              <button 
                onClick={() => { vibrate(30); setIsMyOrdersOpen(true); }}
                className="text-gray-600 hover:text-orange-500 transition-all relative p-1"
                title="Notificaciones"
              >
                <NotificationIcon size={24} />
                {user && myOrdersCount > 0 && (
                  <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] rounded-full h-4 w-4 flex items-center justify-center font-bold">
                    {myOrdersCount}
                  </span>
                )}
              </button>
              
              {/* Carrito */}
              <button onClick={() => { vibrate(30); setIsCartOpen(true); }} className="text-gray-600 hover:text-orange-500 transition-all relative p-1">
                <ShoppingCart size={24}/>
                {cartItemCount > 0 && (
                  <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] rounded-full h-4 w-4 flex items-center justify-center font-bold">
                    {cartItemCount}
                  </span>
                )}
              </button>
            </div>
            </div>

            {/* Fila 2: Menú de categorías scrolleable - Sin padding */}
            <div className={`flex items-center gap-3 mt-3 transition-all duration-300 overflow-hidden ${isCategoriesVisible ? 'max-h-20' : 'max-h-0'}`}>
              {/* Menú de categorías scrolleable */}
              <div 
                ref={categoriesScrollRef}
                className="flex-1 overflow-x-auto scrollbar-visible"
                onScroll={(e) => {
                  const { scrollLeft, scrollWidth, clientWidth } = e.target;
                  setIsScrolledToEnd(scrollLeft + clientWidth >= scrollWidth - 5);
                }}
              >
                <div className="flex gap-1 pb-1">
                  {mainCategories.map(cat => (
                    <button
                      key={cat}
                      onClick={() => { vibrate(30); setActiveCategory(cat); }}
                      className={`flex flex-col items-center justify-center px-3 py-2 transition-all duration-200 text-xs font-bold min-h-[60px] ${
                        activeCategory === cat
                          ? 'bg-gradient-to-r from-orange-500 to-red-600 text-white'
                          : 'text-gray-700 hover:text-orange-500 hover:bg-orange-50'
                      }`}
                    >
                      <div 
                        className="flex items-center justify-center h-5 mb-1"
                        style={{color: activeCategory === cat ? 'white' : categoryColors[cat]}}
                      >
                        {categoryIcons[cat]}
                      </div>
                      <span className="text-[9px] leading-[1.2] text-center whitespace-pre-line max-w-[70px]">
                        {categoryDisplayNames[cat]}
                      </span>
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* Vista PC: Header optimizado - Solo una fila */}
          <div className="hidden sm:block">
            <div className="px-4 py-2">
              {/* Fila única: Logo | Status | Búsqueda | Notificaciones | Carrito */}
              <div className="flex items-center justify-between gap-3 lg:gap-4">
                {/* Logo - Solo en sm, en lg está en sidebar */}
                <img src="https://laruta11-images.s3.amazonaws.com/menu/logo.png" alt="La Ruta 11" className="w-10 h-10 drop-shadow-lg flex-shrink-0 lg:hidden" />
                
                {/* Menú adicional PC - Izquierda */}
                <div className="hidden lg:flex items-center gap-3 mr-4 pr-4 border-r border-gray-200">
                  {/* Mi Perfil */}
                  <button onClick={() => { vibrate(30); if (user) { setIsProfileOpen(true); } else { setIsLoginOpen(true); } }} className="text-gray-600 hover:text-orange-500 transition-all p-2 flex-shrink-0" title="Mi Perfil">
                    {user?.foto_perfil ? (<img src={user.foto_perfil} alt={user.nombre} className="w-5 h-5 rounded-full object-cover border border-gray-400" />) : (<User size={20} />)}
                  </button>
                  
                  {/* Información */}
                  <button onClick={() => { vibrate(30); setIsInfoModalOpen(true); }} className="text-gray-600 hover:text-orange-500 transition-all p-2 flex-shrink-0" title="Información">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                  </button>
                  
                  {/* Instagram */}
                  <button onClick={() => { vibrate(30); setIsInstagramModalOpen(true); }} className="text-gray-600 hover:text-orange-500 transition-all p-2 flex-shrink-0" title="Instagram">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                  </button>
                  
                  {/* WhatsApp */}
                  <button onClick={() => { vibrate(30); setIsWhatsAppModalOpen(true); }} className="text-gray-600 hover:text-orange-500 transition-all p-2 flex-shrink-0" title="WhatsApp">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/></svg>
                  </button>
                </div>
                
                {/* Spacer */}
                <div className="flex-1"></div>
                
                {/* Búsqueda */}
                <button 
                  onClick={() => { vibrate(30); setShowSearchModal(true); }}
                  className="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-lg text-gray-600 hover:text-orange-500 transition-all hover:bg-gray-50 flex-shrink-0"
                  title="Buscar"
                >
                  <Search size={20} />
                  <span className="text-sm font-semibold">Buscar</span>
                </button>
                
                {/* Share */}
                <button 
                  onClick={() => { vibrate(30); setIsShareAppOpen(true); }}
                  className="text-gray-600 hover:text-orange-500 transition-all p-1 flex-shrink-0"
                  title="Compartir app"
                >
                  <Share2 size={20} />
                </button>
                
                {/* Búsqueda móvil */}
                <button 
                  onClick={() => { vibrate(30); setShowSearchModal(true); }}
                  className="lg:hidden text-gray-600 hover:text-orange-500 transition-all p-1 flex-shrink-0"
                  title="Buscar"
                >
                  <Search size={20} />
                </button>
                
                {/* Notificaciones */}
                <button 
                  onClick={() => { vibrate(30); setIsMyOrdersOpen(true); }}
                  className="text-gray-600 hover:text-orange-500 transition-all relative p-1 flex-shrink-0"
                  title="Notificaciones"
                >
                  <NotificationIcon size={20} />
                  {user && myOrdersCount > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] rounded-full h-4 w-4 flex items-center justify-center font-bold">
                      {myOrdersCount}
                    </span>
                  )}
                </button>
                
                {/* Carrito */}
                <button onClick={() => { vibrate(30); setIsCartOpen(true); }} className="text-gray-600 hover:text-orange-500 transition-all relative p-1 flex-shrink-0">
                  <ShoppingCart size={20} />
                  {cartItemCount > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] rounded-full h-4 w-4 flex items-center justify-center font-bold">
                      {cartItemCount}
                    </span>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      </header>
      
      {/* Banner de deslizar - Solo móvil */}
      {isCategoriesVisible && (
        <div className={`sm:hidden fixed top-[115px] left-0 right-0 z-30 px-2 py-2 transition-all duration-300 ${isCategoriesVisible ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-full'}`}>
          <div className="max-w-3xl sm:max-w-7xl mx-auto flex justify-end">
            <div className="bg-yellow-300 text-black px-3 py-1.5 rounded-full text-[9px] font-bold flex items-center gap-1.5 whitespace-nowrap shadow-sm">
              <span>Desliza para ver más</span>
              <svg 
                width="12" 
                height="12" 
                viewBox="0 0 24 24" 
                fill="none" 
                stroke="currentColor" 
                strokeWidth="2.5" 
                className={`transition-transform duration-300 ${isScrolledToEnd ? 'rotate-180' : ''} ${!isScrolledToEnd ? 'animate-bounce-horizontal' : ''}`}
              >
                <path d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </div>
        </div>
      )}
      
      {/* Cart Sidebar - Solo PC */}
      <aside className="hidden lg:flex lg:flex-col fixed right-0 top-0 bottom-0 w-96 bg-white border-l border-gray-200 z-30 overflow-y-auto">
        <div className="p-4 border-b bg-gradient-to-r from-orange-500 to-red-600 flex-shrink-0">
          <h2 className="text-lg font-bold text-white flex items-center gap-2">
            <ShoppingCart size={20} />
            Tu Pedido ({cartItemCount})
          </h2>
          <p className="text-white/90 text-sm mt-1">Total: ${cartTotal.toLocaleString('es-CL')}</p>
        </div>
        
        {cart.length === 0 ? (
          <div className="flex-grow flex flex-col justify-center items-center text-gray-400 p-8">
            <ShoppingCart size={64} className="mb-4 opacity-30" />
            <p className="text-center text-gray-600 font-medium">Agrega productos al carrito</p>
            <p className="text-center text-sm text-gray-500 mt-2">Explora nuestro menú y selecciona tus favoritos</p>
          </div>
        ) : (
          <>
            <div className="flex-grow overflow-y-auto p-4 space-y-3">
              {cart.map((item) => {
                const hasCustomizations = item.customizations && item.customizations.length > 0;
                const customizationsTotal = hasCustomizations ? item.customizations.reduce((sum, c) => {
                  let price = c.price * c.quantity;
                  if (c.extraPrice && c.quantity > 1) {
                    price = c.price + (c.quantity - 1) * c.extraPrice;
                  }
                  return sum + price;
                }, 0) : 0;
                const displayPrice = item.price + customizationsTotal;
                const itemSubtotal = displayPrice * item.quantity;
                const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
                const nonPersonalizableCategories = ['Bebidas', 'Jugos', 'Té', 'Café', 'Salsas'];
                const shouldShowPersonalizeButton = !nonPersonalizableCategories.includes(item.subcategory_name);
                
                return (
                  <div key={item.cartItemId} className="bg-gray-50 rounded-lg p-3 border border-gray-200">
                    <div className="flex items-start gap-3">
                      {item.image && (
                        <img src={item.image} alt={item.name} className="w-16 h-16 object-cover rounded-lg flex-shrink-0" />
                      )}
                      <div className="flex-1 min-w-0">
                        <p className="font-semibold text-gray-800 text-sm mb-1">{item.name}</p>
                        <p className="text-xs text-gray-600 mb-2">${displayPrice.toLocaleString('es-CL')} × {item.quantity}</p>
                        
                        {shouldShowPersonalizeButton && (
                          <button
                            onClick={() => setSelectedProduct({...item, cartIndex: cart.findIndex(i => i.cartItemId === item.cartItemId), isEditing: true})}
                            className="flex items-center gap-1 px-2 py-1 bg-yellow-400 hover:bg-yellow-500 rounded text-xs font-bold mb-2"
                          >
                            <Pencil size={10} />
                            Personalizar
                          </button>
                        )}
                        
                        {(isCombo && item.selections) || hasCustomizations ? (
                          <div className="mt-2 pt-2 border-t border-gray-200">
                            {(isCombo && (item.fixed_items || item.selections)) && (
                              <>
                                <p className="text-[10px] font-semibold text-gray-500 mb-1">INCLUYE:</p>
                                <div className="space-y-0.5 mb-2">
                                  {item.fixed_items && item.fixed_items.map((fixedItem, idx) => (
                                    <p key={idx} className="text-[11px] text-gray-600">• {item.quantity}x {fixedItem.product_name || fixedItem.name}</p>
                                  ))}
                                  {item.selections && Object.entries(item.selections).map(([group, selection]) => {
                                    if (Array.isArray(selection)) {
                                      return selection.map((sel, idx) => (
                                        <p key={`${group}-${idx}`} className="text-[11px] text-gray-600">• {item.quantity}x {sel.name}</p>
                                      ));
                                    } else {
                                      return (
                                        <p key={group} className="text-[11px] text-gray-600">• {item.quantity}x {selection.name}</p>
                                      );
                                    }
                                  })}
                                </div>
                              </>
                            )}
                            {hasCustomizations && (
                              <>
                                <p className="text-[10px] font-semibold text-orange-600 mb-1">{isCombo ? 'PERSONALIZADO CON:' : 'PERSONALIZADO CON:'}</p>
                                <div className="space-y-0.5">
                                  {item.customizations.map((custom, idx) => (
                                    <p key={idx} className="text-[11px] text-orange-600 font-medium">• {custom.quantity}x {custom.name} (+${(custom.price * custom.quantity).toLocaleString('es-CL')})</p>
                                  ))}
                                </div>
                              </>
                            )}
                          </div>
                        ) : null}
                      </div>
                      <button
                        onClick={() => handleRemoveFromCart(item.cartItemId)}
                        className="text-gray-400 hover:text-red-500 flex-shrink-0"
                      >
                        <X size={18} />
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
            
            <div className="border-t p-4 bg-white flex-shrink-0">
              <div className="space-y-2 mb-4">
                <div className="flex justify-between items-center text-sm">
                  <span className="text-gray-600">Subtotal:</span>
                  <span className="font-semibold">${cartSubtotal.toLocaleString('es-CL')}</span>
                </div>
                {customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 && (
                  <div className="flex justify-between items-center text-sm">
                    <span className="text-gray-600">Delivery:</span>
                    <span className="font-semibold">${parseInt(nearbyTrucks[0].tarifa_delivery || 0).toLocaleString('es-CL')}</span>
                  </div>
                )}
                {pizzaDiscount > 0 && (
                  <div className="flex justify-between items-center text-sm bg-yellow-50 -mx-2 px-2 py-1 rounded">
                    <span className="text-gray-600">Descuento:</span>
                    <span className="font-semibold text-green-600">-${pizzaDiscount.toLocaleString('es-CL')}</span>
                  </div>
                )}
                <div className="flex justify-between items-center text-lg font-bold border-t pt-2">
                  <span>Total:</span>
                  <span className="text-green-600">${cartTotal.toLocaleString('es-CL')}</span>
                </div>
              </div>
              
              {nearbyTrucks.length > 0 && !nearbyTrucks[0].activo && (
                <div className="bg-red-100 border border-red-300 rounded-lg p-2 text-center mb-3">
                  <p className="text-red-800 font-bold text-xs">⚠️ Cerrado por mejoras</p>
                </div>
              )}
              <button
                onClick={handleCheckout}
                disabled={nearbyTrucks.length > 0 && !nearbyTrucks[0].activo}
                className={`w-full rounded-lg font-bold py-3 text-base transition-all flex items-center justify-center gap-2 ${
                  nearbyTrucks.length > 0 && !nearbyTrucks[0].activo
                    ? 'bg-gray-400 cursor-not-allowed text-white'
                    : 'bg-gradient-to-r from-green-500 to-green-600 text-white hover:from-green-600 hover:to-green-700'
                }`}
              >
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                  <path fillRule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clipRule="evenodd"/>
                </svg>
                Ir a Pagar
              </button>
            </div>
          </>
        )}
      </aside>

      <main className={`px-0.5 sm:px-4 lg:px-8 xl:px-12 2xl:px-16 max-w-3xl sm:max-w-3xl lg:max-w-5xl mx-auto pb-20 lg:ml-64 lg:mr-96 ${
        !user ? 'pt-[180px] sm:pt-[160px]' : 'pt-32 sm:pt-28'
      }`}>
        {(activeCategory === 'churrascos' || activeCategory === 'hamburguesas' || activeCategory === 'hamburguesas_100g' || activeCategory === 'completos' || activeCategory === 'papas' || activeCategory === 'pizzas' || activeCategory === 'bebidas' || activeCategory === 'Combos') ? (
            <div className="space-y-8">
                {(() => {
                  let categoryData = menuWithImages[activeCategory];
                  
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
                  
                  if (!categoryData || Object.keys(categoryData).length === 0) return null;
                  let orderedEntries = Object.entries(categoryData);
                  
                  // Orden específico para completos
                  if (activeCategory === 'completos') {
                    orderedEntries = [
                      ['tradicionales', categoryData.tradicionales || []],
                      ['especiales', categoryData.especiales || []]
                    ];
                  }
                  
                  // Orden específico para bebidas
                  if (activeCategory === 'bebidas') {
                    orderedEntries = [
                      ['bebidas', categoryData.bebidas || []],
                      ['jugos', categoryData.jugos || []],
                      ['té', categoryData.té || []],
                      ['café', categoryData.café || []]
                    ];
                  }
                  
                  return orderedEntries
                    .filter(([subCategory, products]) => products && products.length > 0)
                    .map(([subCategory, products]) => (
                    <section key={subCategory} id={subCategory}>
                        <h2 className="text-2xl sm:text-3xl font-black text-gray-800 capitalize border-b-2 border-orange-500 pb-2 px-2 mb-2">{subCategory === 'papas' ? 'Papas Fritas ❤️' : subCategory}</h2>
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4 lg:gap-6 mt-4">
                            {products.map(product => (
                                <MenuItem
                                    key={product.id}
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
                                    setQuickViewProduct={setQuickViewProduct}
                                />
                            ))}
                        </div>
                    </section>
                  ));
                })()}
            </div>
        ) : (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4 lg:gap-6">
                {productsToShow.map(product => (
                    <MenuItem
                        key={product.id}
                        product={product}
                        onSelect={null}
                        onAddToCart={handleAddToCart}
                        onRemoveFromCart={handleRemoveFromCart}
                        quantity={getProductQuantity(product.id)}
                        isLiked={likedProducts.has(product.id)}
                        handleLike={handleLike}
                        setReviewsModalProduct={setReviewsModalProduct}
                        onShare={setShareModalProduct}
                        setQuickViewProduct={setQuickViewProduct}
                    />
                ))}
            </div>
        )}
      </main>

      {/* Modal de Búsqueda */}
      {showSearchModal && (
        <div className="fixed inset-0 bg-transparent z-50 flex items-start sm:items-center justify-center sm:pt-0 pt-20 animate-fade-in overflow-y-auto" onClick={() => { setShowSearchModal(false); setSearchQuery(''); setFilteredProducts([]); }}>
          <div className="bg-white w-full sm:w-auto sm:min-w-[500px] max-w-md mx-4 rounded-2xl shadow-2xl animate-slide-up" onClick={(e) => e.stopPropagation()}>
            <div className="p-4 border-b flex items-center gap-2 sm:gap-3">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={18} />
                <input
                  type="text"
                  placeholder="Buscar productos..."
                  value={searchQuery}
                  onChange={(e) => handleSearch(e.target.value)}
                  autoFocus
                  className="w-full pl-10 pr-3 py-2 sm:py-3 bg-gray-50 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                />
              </div>
              <button
                onClick={() => { setShowSearchModal(false); setSearchQuery(''); setFilteredProducts([]); }}
                className="bg-black text-white px-3 sm:px-4 py-2 sm:py-3 rounded-xl text-sm font-medium hover:bg-gray-800 transition-colors flex-shrink-0"
              >
                Cerrar
              </button>
            </div>
            <div className="max-h-96 overflow-y-auto">
              {searchQuery && filteredProducts.length > 0 ? (
                filteredProducts.map(product => {
                  const quantity = getProductQuantity(product.id);
                  return (
                    <div
                      key={product.id}
                      className="w-full px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-b-0 flex items-center gap-3 transition-colors"
                    >
                      {product.image ? (
                        <img src={product.image} alt={product.name} className="w-12 h-12 object-cover rounded-lg" />
                      ) : (
                        <div className="w-12 h-12 bg-gray-200 rounded-lg"></div>
                      )}
                      <div className="flex-1">
                        <p className="text-sm font-semibold text-gray-800">{product.name}</p>
                        <p className="text-xs text-gray-500">{categoryDisplayNames[product.category]}</p>
                        <p className="text-xs font-bold bg-yellow-400 text-black inline-block px-2 py-0.5 rounded mt-1">${product.price.toLocaleString('es-CL')}</p>
                      </div>
                      <div className="flex items-center gap-2 bg-white/90 backdrop-blur-sm rounded-full p-1 shadow-md">
                        {quantity > 0 && (
                          <button
                            onClick={() => handleRemoveFromCart(product.id)}
                            className="text-red-600 rounded-full hover:bg-red-100 transition-colors p-1"
                          >
                            <MinusCircle size={18} />
                          </button>
                        )}
                        {quantity > 0 && <span className="font-bold text-xs text-gray-800 w-4 text-center">{quantity}</span>}
                        <button 
                          onClick={() => handleAddToCart(product)}
                          className="text-green-600 rounded-full hover:bg-green-100 transition-colors p-1"
                        >
                          <PlusCircle size={18} />
                        </button>
                      </div>
                    </div>
                  );
                })
              ) : searchQuery ? (
                <div className="p-8 text-center text-gray-500">
                  <Search size={48} className="mx-auto mb-2 opacity-30" />
                  <p>No se encontraron productos</p>
                </div>
              ) : (
                <div className="p-8 text-center text-gray-400">
                  <Search size={48} className="mx-auto mb-2 opacity-30" />
                  <p>Escribe para buscar productos</p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}



      <AuthModal 
        isOpen={isLoginOpen} 
        onClose={() => setIsLoginOpen(false)}
        onLoginSuccess={(userData) => {
          setUser(userData);
          setIsLoginOpen(false);
          loadNotifications();
          loadUserOrders();
        }}
      />
      <FoodTrucksModal 
        isOpen={isFoodTrucksOpen} 
        onClose={() => setIsFoodTrucksOpen(false)}
        trucks={nearbyTrucks}
        userLocation={userLocation}
        deliveryZone={deliveryZone}
        statusData={statusData}
      />
      <NotificationsModal 
        isOpen={isNotificationsOpen} 
        onClose={() => setIsNotificationsOpen(false)}
        notifications={notifications}
        onMarkAllRead={async () => {
          try {
            const response = await fetch('/api/mark_notifications_read.php', { method: 'POST' });
            const data = await response.json();
            if (data.success) {
              setNotifications(prev => prev.map(n => ({ ...n, is_read: true })));
              setUnreadCount(0);
            }
          } catch (error) {
            console.error('Error marking notifications as read:', error);
          }
        }}
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
      <ProfileModalModern 
        isOpen={isProfileOpen} 
        onClose={() => {
          setIsProfileOpen(false);
          // Refrescar stats al cerrar modal
          if (user) {
            loadUserOrders();
          }
        }} 
        user={user}
        setUser={setUser}
        userLocation={userLocation}
        locationPermission={locationPermission}
        requestLocation={requestLocation}
        setUserLocation={setUserLocation}
        setLocationPermission={setLocationPermission}
        hasProfileChanges={hasProfileChanges}
        setHasProfileChanges={setHasProfileChanges}
        setIsSaveChangesModalOpen={setIsSaveChangesModalOpen}
        setIsLogoutModalOpen={setIsLogoutModalOpen}
        setIsDeleteAccountModalOpen={setIsDeleteAccountModalOpen}
        userOrders={userOrders}
        userStats={userStats}
        showAllOrders={showAllOrders}
        setShowAllOrders={setShowAllOrders}
        loadUserOrders={loadUserOrders}
      />
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
        onUpdateCartItem={(cartIndex, updatedProduct, newCustomizations) => {
          setCart(prevCart => {
            const newCart = [...prevCart];
            newCart[cartIndex] = {
              ...updatedProduct,
              customizations: newCustomizations.length > 0 ? newCustomizations : null,
              cartItemId: prevCart[cartIndex].cartItemId
            };
            return newCart;
          });
        }}
      />
       <ImageFullscreenModal 
        product={zoomedProduct?.product} 
        total={zoomedProduct?.total}
        onClose={() => setZoomedProduct(null)} 
      />
      <CartModal
        isOpen={isCartOpen}
        onClose={() => setIsCartOpen(false)}
        cart={cart}
        onAddToCart={handleAddToCart}
        onRemoveFromCart={handleRemoveFromCart}
        cartTotal={cartTotal}
        onCheckout={handleCheckout}
        onCustomizeProduct={(item, itemIndex) => {
          setSelectedProduct({...item, cartIndex: itemIndex, isEditing: true});
        }}
        nearbyTrucks={nearbyTrucks}
      />
      
      {/* Checkout Modal */}
      {showCheckout && (
        <div className="fixed inset-0 bg-transparent z-50 flex justify-center items-center animate-fade-in">
          <div className="bg-white w-full max-w-md mx-4 rounded-2xl flex flex-col animate-slide-up max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <h2 className="text-xl font-bold text-gray-800">Finalizar Pedido</h2>
                <button onClick={() => setShowCheckout(false)} className="p-1 text-gray-400 hover:text-gray-600">
                  <X size={24} />
                </button>
              </div>
              
              {/* Tipo de entrega */}
              <div className="mb-6">
                <h3 className="text-lg font-semibold text-gray-800 mb-3">Tipo de Entrega</h3>
                <div className="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    onClick={() => setCustomerInfo({...customerInfo, deliveryType: 'delivery'})}
                    className={`p-3 border-2 rounded-lg text-center transition-colors ${
                      customerInfo.deliveryType === 'delivery' 
                        ? 'border-orange-500 bg-orange-50 text-orange-700' 
                        : 'border-gray-300 hover:border-gray-400'
                    }`}
                  >
                    <div className="flex justify-center mb-2">
                      <Bike size={32} className="text-red-500" />
                    </div>
                    <div className="font-semibold">Delivery</div>
                    <div className="text-xs text-gray-500">Entrega a domicilio</div>
                  </button>
                  <button
                    type="button"
                    onClick={() => setCustomerInfo({...customerInfo, deliveryType: 'pickup'})}
                    className={`p-3 border-2 rounded-lg text-center transition-colors ${
                      customerInfo.deliveryType === 'pickup' 
                        ? 'border-orange-500 bg-orange-50 text-orange-700' 
                        : 'border-gray-300 hover:border-gray-400'
                    }`}
                  >
                    <div className="flex justify-center mb-2">
                      <Caravan size={32} className="text-red-500" />
                    </div>
                    <div className="font-semibold">Retiro</div>
                    <div className="text-xs text-gray-500">Retiro en local</div>
                  </button>
                </div>
              </div>
              
              {/* Código de descuento */}
              <div className="mb-6">
                <label className="block text-sm font-medium text-orange-600 mb-1">Código de descuento</label>
                <input
                  type="text"
                  value={discountCode}
                  onChange={(e) => setDiscountCode(e.target.value.toUpperCase().slice(0, 7))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 uppercase"
                  placeholder="Ej: PIZZA11"
                  maxLength={7}
                />
                {pizzaDiscount > 0 && (
                  <p className="text-xs text-green-600 mt-1 flex items-center gap-1">
                    <CheckCircle2 size={12} /> Descuento aplicado: -${pizzaDiscount.toLocaleString('es-CL')}
                  </p>
                )}
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
                  <label className="block text-sm font-medium text-gray-700 mb-1">Teléfono *</label>
                  <input
                    type="tel"
                    value={customerInfo.phone || user?.telefono || ''}
                    onChange={(e) => setCustomerInfo({...customerInfo, phone: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="+56 9 1234 5678"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                  <input
                    type="email"
                    value={customerInfo.email || user?.email || ''}
                    onChange={(e) => setCustomerInfo({...customerInfo, email: e.target.value})}
                    className={`w-full px-3 py-2 border rounded-md focus:outline-none ${
                      user ? 'border-gray-200 bg-gray-50 text-gray-600 cursor-not-allowed' : 'border-gray-300 focus:ring-2 focus:ring-orange-500'
                    }`}
                    placeholder="tu@email.com"
                    readOnly={!!user}
                  />
                </div>
                
                {/* Dirección para delivery */}
                {customerInfo.deliveryType === 'delivery' && (
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Dirección de entrega *</label>
                    <input
                      type="text"
                      id="deliveryAddress"
                      value={customerInfo.address || ''}
                      onChange={(e) => setCustomerInfo({...customerInfo, address: e.target.value})}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                      placeholder="Buscar dirección..."
                      required
                    />
                    {nearbyTrucks.length > 0 && (
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
                )}
                
                {/* Horario de retiro */}
                {customerInfo.deliveryType === 'pickup' && (
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Horario de retiro *</label>
                    <select
                      value={customerInfo.pickupTime || ''}
                      onChange={(e) => setCustomerInfo({...customerInfo, pickupTime: e.target.value})}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                      required
                    >
                      <option value="">Seleccionar horario</option>
                      {(() => {
                        // Hora de Chile (UTC-3)
                        const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'America/Santiago' }));
                        const currentHour = now.getHours();
                        const currentMinute = now.getMinutes();
                        const dayOfWeek = now.getDay();
                        
                        // Horarios cada media hora
                        let allSlots = [];
                        if (dayOfWeek >= 1 && dayOfWeek <= 4) { // Lunes a Jueves hasta 00:30
                          allSlots = ['18:00','18:30','19:00','19:30','20:00','20:30','21:00','21:30','22:00','22:30','23:00','23:30','00:00','00:30'];
                        } else if (dayOfWeek === 5 || dayOfWeek === 6) { // Viernes y Sábado hasta 02:30
                          allSlots = ['18:00','18:30','19:00','19:30','20:00','20:30','21:00','21:30','22:00','22:30','23:00','23:30','00:00','00:30','01:00','01:30','02:00','02:30'];
                        } else { // Domingo hasta 00:00
                          allSlots = ['18:00','18:30','19:00','19:30','20:00','20:30','21:00','21:30','22:00','22:30','23:00','23:30','00:00'];
                        }
                        
                        // Filtrar slots disponibles (hora actual + 30 min)
                        return allSlots.filter(slot => {
                          const [slotHour, slotMin] = slot.split(':').map(Number);
                          const slotTime = slotHour * 60 + slotMin;
                          const currentTime = currentHour * 60 + currentMinute + 30;
                          
                          // Si es después de medianoche (00:00-02:30) y estamos después de las 19:00
                          if (slotHour < 3 && currentHour >= 19) return true;
                          // Si el slot es mayor al tiempo actual + 30 min
                          return slotTime >= currentTime;
                        }).map(slot => <option key={slot} value={slot}>{slot}</option>);
                      })()
                      }
                    </select>
                    <p className="text-xs text-gray-500 mt-1">Tiempo de preparación: 15-30 minutos</p>
                  </div>
                )}
              </div>
              
              <div className="border-t pt-4 mb-6">
                <h3 className="text-xl font-black text-gray-900 mb-3">Tu Pedido</h3>
                <div className="space-y-2 mb-4">
                  {cart.map(item => {
                    const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
                    let itemTotal = item.price * item.quantity;
                    if (item.customizations && item.customizations.length > 0) {
                      const customizationsTotal = item.customizations.reduce((sum, custom) => {
                        return sum + (custom.price * custom.quantity);
                      }, 0);
                      itemTotal += customizationsTotal;
                    }
                    return (
                      <div key={item.cartItemId || item.id} className="py-1">
                        <div className="flex justify-between">
                          <span className="text-sm font-medium">{item.name} x{item.quantity}</span>
                          <span className="text-sm font-medium">${itemTotal.toLocaleString('es-CL')}</span>
                        </div>
                        {isCombo && (item.fixed_items || item.selections) && (
                          <div className="ml-2 mt-1 text-xs text-gray-600">
                            <span className="font-medium">Incluye:</span>
                            <div className="mt-1 space-y-0.5">
                              {item.fixed_items && item.fixed_items.map((fixedItem, idx) => (
                                <div key={idx}>• {item.quantity}x {fixedItem.product_name || fixedItem.name}</div>
                              ))}
                              {item.selections && Object.entries(item.selections).map(([group, selection], idx) => {
                                if (Array.isArray(selection)) {
                                  return selection.map((sel, selIdx) => (
                                    <div key={`${group}-${selIdx}`} className="text-blue-600">• {item.quantity}x {sel.name}</div>
                                  ));
                                } else {
                                  return (
                                    <div key={group} className="text-blue-600">• {item.quantity}x {selection.name}</div>
                                  );
                                }
                              })}
                            </div>
                          </div>
                        )}
                        {!isCombo && item.customizations && item.customizations.length > 0 && (
                          <div className="ml-2 mt-1 text-xs text-gray-600">
                            <span className="font-medium">Incluye:</span>
                            <div className="mt-1 space-y-0.5">
                              {item.customizations.map((custom, idx) => (
                                <div key={idx} className="text-blue-600">• {custom.quantity}x {custom.name} (+${(custom.price * custom.quantity).toLocaleString('es-CL')})</div>
                              ))}
                            </div>
                          </div>
                        )}
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
                    <div className="flex justify-between items-center">
                      <span className="text-gray-600">Delivery:</span>
                      <span className="font-semibold">${parseInt(nearbyTrucks[0].tarifa_delivery || 0).toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  {pizzaDiscount > 0 && (
                    <div className="flex justify-between items-center bg-yellow-100 -mx-2 px-2 py-1 rounded">
                      <span className="text-gray-600">Descuento (PIZZA11):</span>
                      <span className="font-semibold text-green-600">-${pizzaDiscount.toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  <div className="flex justify-between items-center text-lg font-bold border-t pt-2">
                    <span>Total:</span>
                    <span className="text-green-600">${cartTotal.toLocaleString('es-CL')}</span>
                  </div>
                </div>
              </div>
              
              <div className="space-y-3">
                <button
                  onClick={handleCreateOrder}
                  disabled={getFormDisabledState(customerInfo, user) || isProcessing}
                  className="w-full bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2"
                >
                  <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                    <path fillRule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clipRule="evenodd"/>
                  </svg>
                  {isProcessing ? 'Procesando...' : 'Continuar al Pago'}
                </button>
                
                <button
                  onClick={() => {
                    if (isProcessing) return;
                    handlePaymentSuccess();
                  }}
                  disabled={isProcessing}
                  className="w-full bg-gray-500 hover:bg-gray-600 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-2 px-4 rounded-lg transition-colors"
                >
                  {isProcessing ? 'Procesando...' : 'Pagar en el Local'}
                </button>
                
                <button
                  onClick={() => {
                    if (isProcessing) return;
                    
                    const validation = validateCheckoutForm(customerInfo, user);
                    if (!validation.isValid) {
                      alert(validation.message);
                      return;
                    }
                    
                    setIsProcessing(true);
                    
                    const orderNumber = 'R11-' + Date.now();
                    const customerName = customerInfo.name || user?.nombre || 'Cliente';
                    const customerPhone = customerInfo.phone || user?.telefono || '';
                    const deliveryType = customerInfo.deliveryType || 'pickup';
                    const currentDeliveryFee = deliveryType === 'delivery' && nearbyTrucks.length > 0 ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) : 0;
                    
                    let message = `**NUEVO PEDIDO - LA RUTA 11**\n\n`;
                    message += `**Pedido:** ${orderNumber}\n`;
                    message += `**Cliente:** ${customerName}\n`;
                    if (customerPhone) message += `**Telefono:** ${customerPhone}\n`;
                    
                    if (deliveryType === 'delivery') {
                      message += `**Tipo:** Delivery\n`;
                      if (customerInfo.address) message += `**Direccion:** ${customerInfo.address}\n`;
                    } else {
                      message += `**Tipo:** Retiro en Food Truck\n`;
                      if (customerInfo.pickupTime) message += `**Hora de retiro:** ${customerInfo.pickupTime}\n`;
                    }
                    
                    message += `\n**PRODUCTOS:**\n`;
                    
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
                            } else {
                              message += `   • ${item.quantity}x ${selection.name}\n`;
                            }
                          });
                        }
                      }
                    });
                    
                    if (currentDeliveryFee > 0) {
                      message += `\n**Subtotal productos:** $${cartSubtotal.toLocaleString('es-CL')}\n`;
                      message += `**Delivery:** $${currentDeliveryFee.toLocaleString('es-CL')}\n`;
                    }
                    message += `\n**TOTAL: $${cartTotal.toLocaleString('es-CL')}**\n\n`;
                    message += `Pedido realizado desde la app web.`;
                    
                    const whatsappUrl = `https://wa.me/56936227422?text=${encodeURIComponent(message)}`;
                    window.open(whatsappUrl, '_blank');
                    
                    // Limpiar carrito después de enviar
                    setTimeout(() => {
                      setCart([]);
                      setShowCheckout(false);
                      setIsProcessing(false);
                      alert('Pedido enviado por WhatsApp. Te contactaremos pronto!');
                    }, 1000);
                  }}
                  disabled={getFormDisabledState(customerInfo, user) || isProcessing}
                  className="w-full bg-green-500 hover:bg-green-600 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-2 px-4 rounded-lg transition-colors flex items-center justify-center gap-2"
                >
                  <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                  </svg>
                  {isProcessing ? 'Procesando...' : 'Terminar Pedido (WhatsApp)'}
                </button>
              </div>
              
              <p className="text-xs text-gray-500 text-center mt-3">
                🔒 Pago 100% seguro SSL con TUU Webpay
              </p>
            </div>
          </div>
        </div>
      )}
      
      {/* Payment Modal with TUU Integration */}
      {showPayment && currentOrder && (
        <div className="fixed inset-0 bg-transparent z-50 flex justify-center items-center animate-fade-in">
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
      <ProductQuickViewModal 
        product={quickViewProduct}
        isOpen={!!quickViewProduct}
        onClose={() => setQuickViewProduct(null)}
        onAddToCart={handleAddToCart}
        onRemoveFromCart={handleRemoveFromCart}
        quantity={quickViewProduct ? getProductQuantity(quickViewProduct.id) : 0}
        isLiked={quickViewProduct ? likedProducts.has(quickViewProduct.id) : false}
        onLike={handleLike}
        onOpenReviews={setReviewsModalProduct}
        onShare={setShareModalProduct}
      />
      {user && <OrderNotifications userId={user.id} onNotificationsUpdate={(notifs, unread) => {
        setNotifications(notifs);
        setUnreadCount(unread);
      }} />}
      <MiniComandasCliente 
        customerName={user?.nombre}
        userId={user?.id}
        onOrdersUpdate={(count) => setMyOrdersCount(count)}
        isOpen={isMyOrdersOpen}
        onClose={() => setIsMyOrdersOpen(false)}
        onOpenLogin={() => setIsLoginOpen(true)}
      />
      
      {/* Review Modal */}
      {isReviewModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-70 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={() => setIsReviewModalOpen(false)}>
          <div className="bg-white w-full max-w-md mx-4 rounded-2xl shadow-2xl animate-slide-up" onClick={(e) => e.stopPropagation()}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-xl font-bold text-gray-800">Recomiéndanos ⭐</h2>
                <button onClick={() => setIsReviewModalOpen(false)} className="p-1 text-gray-400 hover:text-gray-600">
                  <X size={24} />
                </button>
              </div>
              <p className="text-gray-600 text-sm mb-6">Tu opinión nos ayuda a mejorar. Déjanos una reseña en Google y cuéntanos tu experiencia 🙏</p>
              <button
                onClick={() => { vibrate(30); window.open('https://g.page/r/CWcB3w3uXjdaEAE/review', '_blank'); setIsReviewModalOpen(false); }}
                className="w-full bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2"
              >
                <Star size={20} />
                Dejar Reseña en Google
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Instagram Modal */}
      {isInstagramModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-70 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={() => setIsInstagramModalOpen(false)}>
          <div className="bg-white w-full max-w-md mx-4 rounded-2xl shadow-2xl animate-slide-up" onClick={(e) => e.stopPropagation()}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-xl font-bold text-gray-800">Síguenos en Instagram 📸</h2>
                <button onClick={() => setIsInstagramModalOpen(false)} className="p-1 text-gray-400 hover:text-gray-600">
                  <X size={24} />
                </button>
              </div>
              <p className="text-gray-600 text-sm mb-6">Entérate de nuestras promociones, nuevos productos y eventos especiales siguiéndonos en Instagram 🎉</p>
              <button
                onClick={() => { vibrate(30); window.open('https://www.instagram.com/la_ruta_11/', '_blank'); setIsInstagramModalOpen(false); }}
                className="w-full bg-gradient-to-r from-pink-500 to-purple-600 hover:from-pink-600 hover:to-purple-700 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2"
              >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                </svg>
                Ir a Instagram
              </button>
            </div>
          </div>
        </div>
      )}

      {/* WhatsApp Modal */}
      {isWhatsAppModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-70 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={() => setIsWhatsAppModalOpen(false)}>
          <div className="bg-white w-full max-w-md mx-4 rounded-2xl shadow-2xl animate-slide-up" onClick={(e) => e.stopPropagation()}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-xl font-bold text-gray-800">Contáctanos por WhatsApp 💬</h2>
                <button onClick={() => setIsWhatsAppModalOpen(false)} className="p-1 text-gray-400 hover:text-gray-600">
                  <X size={24} />
                </button>
              </div>
              <p className="text-gray-600 text-sm mb-6">¿Tienes alguna pregunta o consulta? Escríbenos directamente por WhatsApp y te responderemos lo antes posible 📱</p>
              <button
                onClick={() => { vibrate(30); window.open('https://wa.me/56936227422', '_blank'); setIsWhatsAppModalOpen(false); }}
                className="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2"
              >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                </svg>
                Abrir WhatsApp
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Share App Modal */}
      {isShareAppOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-70 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={() => setIsShareAppOpen(false)}>
          <div className="bg-white w-full max-w-md mx-4 rounded-2xl shadow-2xl animate-slide-up" onClick={(e) => e.stopPropagation()}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-xl font-bold text-gray-800">Comparte La Ruta 11</h2>
                <button onClick={() => setIsShareAppOpen(false)} className="p-1 text-gray-400 hover:text-gray-600">
                  <X size={24} />
                </button>
              </div>
              
              <p className="text-gray-600 text-sm mb-6">Invita a tus amigos a usar nuestra app y disfruta juntos 🎉 de las mejores Hamburguesas, Sandwiches, Completos y Papas Rústicas de Arica ❤️</p>
              
              <button
                onClick={() => {
                  vibrate(30);
                  const message = encodeURIComponent('¡Hola! Te invito a La Ruta 11, la mejor app para pedir completos, hamburguesas y churrascos de manera simple y rápida abriendo este link: https://app.laruta11.cl');
                  const whatsappUrl = `https://wa.me/?text=${message}`;
                  window.open(whatsappUrl, '_blank');
                }}
                className="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2 mb-3"
              >
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                </svg>
                Compartir por WhatsApp
              </button>
              
              <button
                onClick={() => {
                  vibrate(30);
                  navigator.clipboard.writeText('https://app.laruta11.cl');
                  alert('Link copiado al portapapeles');
                }}
                className="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-4 rounded-lg transition-colors"
              >
                Copiar Link
              </button>
            </div>
          </div>
        </div>
      )}
      
      {/* Information Modal */}
      {(
        <>
          <div 
            className={`fixed inset-0 bg-transparent transition-opacity duration-300 z-50 ${
              isInfoModalOpen ? 'opacity-50' : 'opacity-0 pointer-events-none'
            }`}
            onClick={() => setIsInfoModalOpen(false)}
          />
          {/* Mobile: Lateral slide */}
          <div 
            className={`sm:hidden fixed top-0 right-0 h-full w-full bg-white z-50 transform transition-transform duration-300 ease-out ${
              isInfoModalOpen ? 'translate-x-0' : 'translate-x-full'
            }`}
          >
            <div className="flex flex-col h-full">
              {/* Header */}
              <div className="border-b flex justify-between items-center p-4 bg-gradient-to-r from-orange-500 to-orange-600">
                <h2 className="font-bold text-white flex items-center gap-2 text-lg">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                  </svg>
                  La Ruta 11
                </h2>
                <button onClick={() => setIsInfoModalOpen(false)} className="p-1 text-white hover:text-orange-100"><X size={24} /></button>
              </div>
              
              {/* Content */}
              <div className="flex-1 overflow-y-auto p-6 space-y-6">
                {/* Descripción */}
                <div>
                  <h3 className="font-bold text-gray-800 mb-2 flex items-center gap-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" className="text-orange-500">
                      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                    Sobre Nosotros
                  </h3>
                  <p className="text-gray-600 text-sm leading-relaxed">
                    Somos La Ruta 11, food trucks especializados en completos, hamburguesas y churrascos artesanales. 
                    Nos dedicamos a ofrecer la mejor experiencia gastronómica con ingredientes frescos y sabores únicos.
                  </p>
                </div>
                
                {/* Horarios */}
                <div>
                  <h3 className="font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" className="text-orange-500">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
                    </svg>
                    Horarios de Atención
                  </h3>
                  <div className="space-y-2 text-sm">
                    {schedules.length > 0 ? schedules.map((schedule, index) => (
                      <div key={index} className={`flex justify-between items-center py-1 px-2 rounded ${schedule.is_today ? 'bg-orange-100 border border-orange-300' : ''}`}>
                        <span className={`${schedule.is_today ? 'text-orange-800 font-bold' : 'text-gray-600'}`}>
                          {schedule.day}{schedule.is_today ? ' (hoy)' : ''}
                        </span>
                        <span className={`font-semibold ${schedule.is_today ? 'text-orange-800' : ''}`}>{schedule.start} - {schedule.end}</span>
                      </div>
                    )) : (
                      <>
                        <div className="flex justify-between">
                          <span className="text-gray-600">Lunes - Jueves:</span>
                          <span className="font-semibold">18:00 - 00:30</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-gray-600">Viernes - Sábado:</span>
                          <span className="font-semibold">18:00 - 03:00</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-gray-600">Domingo:</span>
                          <span className="font-semibold">18:00 - 00:00</span>
                        </div>
                      </>
                    )}
                  </div>
                </div>
                
                {/* Redes Sociales */}
                <div>
                  <h3 className="font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" className="text-orange-500">
                      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                    Síguenos
                  </h3>
                  <div className="flex gap-4">
                    <a 
                      href="https://www.instagram.com/la_ruta_11/" 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className="flex items-center gap-2 bg-gradient-to-r from-pink-500 to-purple-600 text-white px-4 py-2 rounded-lg hover:shadow-lg transition-all"
                    >
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                      </svg>
                      Instagram
                    </a>
                    <a 
                      href="https://wa.me/56936227422" 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className="flex items-center gap-2 bg-green-500 text-white px-4 py-2 rounded-lg hover:shadow-lg transition-all"
                    >
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                      </svg>
                      WhatsApp
                    </a>
                  </div>
                </div>
                
                {/* Ubicación */}
                <div>
                  <h3 className="font-bold text-gray-800 mb-3 flex items-center gap-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" className="text-orange-500">
                      <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    Ubicación
                  </h3>
                  <div className="mb-3 rounded-lg overflow-hidden border border-gray-200">
                    <div className="aspect-[4/3]">
                      <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15137.082940291351!2d-70.30726709437847!3d-18.471391954398037!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x915aa9be349b5ac7%3A0x5a375eee0ddf0167!2sLa%20Ruta%2011!5e0!3m2!1ses!2scl!4v1766369155665!5m2!1ses!2scl"
                        width="100%"
                        height="100%"
                        style={{border: 0}}
                        allowFullScreen=""
                        loading="lazy"
                        referrerPolicy="no-referrer-when-downgrade"
                        title="Ubicación La Ruta 11"
                      ></iframe>
                    </div>
                  </div>
                  <a 
                    href="https://maps.app.goo.gl/8RM68ErBdwgl3pkUE"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors flex items-center justify-center gap-2 mb-2"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    Abrir en Google Maps
                  </a>
                  <a 
                    href="https://g.page/r/CWcB3w3uXjdaEAE/review"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="w-full bg-yellow-500 text-black px-4 py-2 rounded-lg hover:bg-yellow-600 transition-colors flex items-center justify-center gap-2 mb-2"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                    Dejar Reseña en Google
                  </a>
                  <button 
                    onClick={() => {
                      setIsInfoModalOpen(false);
                      setIsFoodTrucksOpen(true);
                    }}
                    className="w-full bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors flex items-center justify-center gap-2"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    Ver Food Trucks Cercanos
                  </button>
                </div>
              </div>
            </div>
          </div>
          
          {/* PC: Centered modal */}
          <div 
            className={`hidden sm:flex fixed inset-0 items-center justify-center z-50 p-4 transition-all duration-300 ${
              isInfoModalOpen ? 'opacity-100 scale-100' : 'opacity-0 scale-95 pointer-events-none'
            }`}
          >
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
              {/* Header */}
              <div className="border-b flex justify-between items-center p-6 bg-gradient-to-r from-orange-500 to-orange-600">
                <h2 className="font-bold text-white flex items-center gap-3 text-2xl">
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                  </svg>
                  La Ruta 11
                </h2>
                <button onClick={() => setIsInfoModalOpen(false)} className="p-2 text-white hover:text-orange-100 hover:bg-white/10 rounded-full transition-all">
                  <X size={28} />
                </button>
              </div>
              
              {/* Content - Grid layout for PC */}
              <div className="overflow-y-auto max-h-[calc(90vh-100px)]">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 p-8">
                  {/* Left Column */}
                  <div className="space-y-8">
                    {/* Descripción */}
                    <div>
                      <h3 className="font-bold text-gray-800 mb-4 flex items-center gap-3 text-xl">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" className="text-orange-500">
                          <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        Sobre Nosotros
                      </h3>
                      <p className="text-gray-600 leading-relaxed text-base">
                        Somos La Ruta 11, food trucks especializados en completos, hamburguesas y churrascos artesanales. 
                        Nos dedicamos a ofrecer la mejor experiencia gastronómica con ingredientes frescos y sabores únicos.
                      </p>
                    </div>
                    
                    {/* Redes Sociales */}
                    <div>
                      <h3 className="font-bold text-gray-800 mb-4 flex items-center gap-3 text-xl">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" className="text-orange-500">
                          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                        Síguenos
                      </h3>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <a 
                          href="https://www.instagram.com/la_ruta_11/" 
                          target="_blank" 
                          rel="noopener noreferrer"
                          className="flex items-center gap-3 bg-gradient-to-r from-pink-500 to-purple-600 text-white px-6 py-4 rounded-xl hover:shadow-lg transition-all hover:scale-105"
                        >
                          <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                          </svg>
                          <span className="font-semibold">Instagram</span>
                        </a>
                        <a 
                          href="https://wa.me/56936227422" 
                          target="_blank" 
                          rel="noopener noreferrer"
                          className="flex items-center gap-3 bg-green-500 text-white px-6 py-4 rounded-xl hover:shadow-lg transition-all hover:scale-105"
                        >
                          <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                          </svg>
                          <span className="font-semibold">WhatsApp</span>
                        </a>
                      </div>
                    </div>
                    
                    {/* Horarios */}
                    <div>
                      <h3 className="font-bold text-gray-800 mb-4 flex items-center gap-3 text-xl">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" className="text-orange-500">
                          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
                        </svg>
                        Horarios de Atención
                      </h3>
                      <div className="bg-gray-50 rounded-xl p-6 space-y-3">
                        {schedules.length > 0 ? schedules.map((schedule, index) => (
                          <div key={index} className={`flex justify-between items-center py-2 px-3 rounded-lg border-b border-gray-200 last:border-b-0 ${schedule.is_today ? 'bg-orange-100 border-orange-300' : ''}`}>
                            <span className={`font-medium ${schedule.is_today ? 'text-orange-800 font-bold' : 'text-gray-700'}`}>
                              {schedule.day}{schedule.is_today ? ' (hoy)' : ''}
                            </span>
                            <span className={`font-bold ${schedule.is_today ? 'text-orange-800' : 'text-gray-900'}`}>{schedule.start} - {schedule.end}</span>
                          </div>
                        )) : (
                          <>
                            <div className="flex justify-between items-center py-2 border-b border-gray-200 last:border-b-0">
                              <span className="text-gray-700 font-medium">Lunes - Jueves:</span>
                              <span className="font-bold text-gray-900">18:00 - 00:30</span>
                            </div>
                            <div className="flex justify-between items-center py-2 border-b border-gray-200 last:border-b-0">
                              <span className="text-gray-700 font-medium">Viernes - Sábado:</span>
                              <span className="font-bold text-gray-900">18:00 - 03:00</span>
                            </div>
                            <div className="flex justify-between items-center py-2">
                              <span className="text-gray-700 font-medium">Domingo:</span>
                              <span className="font-bold text-gray-900">18:00 - 00:00</span>
                            </div>
                          </>
                        )}
                      </div>
                    </div>
                  </div>
                  
                  {/* Right Column - Ubicación */}
                  <div>
                    <h3 className="font-bold text-gray-800 mb-4 flex items-center gap-3 text-xl">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" className="text-orange-500">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                      </svg>
                      Ubicación
                    </h3>
                    
                    {/* Mapa */}
                    <div className="mb-6 rounded-xl overflow-hidden border border-gray-200 shadow-lg">
                      <div className="aspect-[16/10]">
                        <iframe
                          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15137.082940291351!2d-70.30726709437847!3d-18.471391954398037!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x915aa9be349b5ac7%3A0x5a375eee0ddf0167!2sLa%20Ruta%2011!5e0!3m2!1ses!2scl!4v1766369155665!5m2!1ses!2scl"
                          width="100%"
                          height="100%"
                          style={{border: 0}}
                          allowFullScreen=""
                          loading="lazy"
                          referrerPolicy="no-referrer-when-downgrade"
                          title="Ubicación La Ruta 11"
                        ></iframe>
                      </div>
                    </div>
                    
                    {/* Botones de acción */}
                    <div className="space-y-3">
                      <a 
                        href="https://maps.app.goo.gl/8RM68ErBdwgl3pkUE"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="w-full bg-blue-500 text-white px-6 py-3 rounded-xl hover:bg-blue-600 transition-all flex items-center justify-center gap-3 font-semibold hover:scale-105"
                      >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        Abrir en Google Maps
                      </a>
                      <a 
                        href="https://g.page/r/CWcB3w3uXjdaEAE/review"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="w-full bg-yellow-500 text-black px-6 py-3 rounded-xl hover:bg-yellow-600 transition-all flex items-center justify-center gap-3 font-semibold hover:scale-105"
                      >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        Dejar Reseña en Google
                      </a>
                      <button 
                        onClick={() => {
                          setIsInfoModalOpen(false);
                          setIsFoodTrucksOpen(true);
                        }}
                        className="w-full bg-orange-500 text-white px-6 py-3 rounded-xl hover:bg-orange-600 transition-all flex items-center justify-center gap-3 font-semibold hover:scale-105"
                      >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        Ver Food Trucks Cercanos
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </>
      )}

      {/* Menú inferior fijo - Solo móvil */}
      <nav className={`sm:hidden fixed bottom-0 left-0 right-0 bg-white/80 backdrop-blur-md z-40 shadow-sm transition-transform duration-300 rounded-t-3xl ${isNavVisible ? 'translate-y-0' : 'translate-y-full'}`}>
        <div className="max-w-3xl mx-auto px-2 py-2">
          <div className="flex items-center justify-around relative">
            {!user && showRegisterBanner && (
              <div className={`absolute left-2 bottom-full mb-1 bg-black text-white text-xs px-2 py-1 rounded-full whitespace-nowrap flex items-center gap-1 transition-transform duration-300 ${isNavVisible ? 'translate-y-0' : '-translate-y-2 opacity-0'}`}>
                👇😎Registrate
                <button onClick={() => setShowRegisterBanner(false)} className="ml-1 hover:text-gray-300">
                  <X size={12} />
                </button>
              </div>
            )}
            {/* Mi Perfil */}
            <button onClick={() => { vibrate(30); if (user) { setIsProfileOpen(true); } else { setIsLoginOpen(true); } }} className="flex flex-col items-center gap-1 py-2 px-2 text-gray-700 hover:bg-gray-200 rounded-lg transition-all active:scale-95">
              {user?.foto_perfil ? (<img src={user.foto_perfil} alt={user.nombre} className="w-5 h-5 rounded-full object-cover border border-gray-400" />) : (<User size={20} />)}
              <span className="text-[9px] font-bold">Mi Perfil</span>
            </button>
            
            {/* Nosotros */}
            <button onClick={() => { vibrate(30); setIsInfoModalOpen(true); }} className="flex flex-col items-center gap-1 py-2 px-2 text-gray-700 hover:bg-gray-200 rounded-lg transition-all active:scale-95">
              <Caravan size={20} />
              <span className="text-[9px] font-bold">Nosotros</span>
            </button>
            
            {/* Recomendar */}
            <button onClick={() => { vibrate(30); setIsReviewModalOpen(true); }} className="flex flex-col items-center gap-1 py-2 px-2 rounded-lg transition-all active:scale-95 bg-gradient-to-br from-yellow-400 via-yellow-300 to-yellow-500 hover:from-yellow-500 hover:via-yellow-400 hover:to-yellow-600 shadow-md hover:shadow-lg relative overflow-hidden">
              <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/40 to-transparent animate-shimmer"></div>
              <Star size={20} className="text-yellow-900 relative z-10" fill="currentColor" />
              <span className="text-[9px] font-bold text-yellow-900 relative z-10">Recomendar</span>
            </button>
            
            {/* Instagram */}
            <button onClick={() => { vibrate(30); setIsInstagramModalOpen(true); }} className="flex flex-col items-center gap-1 py-2 px-2 text-gray-700 hover:bg-gray-200 rounded-lg transition-all active:scale-95">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
              <span className="text-[9px] font-bold">Instagram</span>
            </button>
            
            {/* WhatsApp */}
            <button onClick={() => { vibrate(30); setIsWhatsAppModalOpen(true); }} className="flex flex-col items-center gap-1 py-2 px-2 text-gray-700 hover:bg-gray-200 rounded-lg transition-all active:scale-95">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/></svg>
              <span className="text-[9px] font-bold">WhatsApp</span>
            </button>
          </div>
        </div>
      </nav>

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
        @keyframes gradient {
          0% { background-position: 0% 50%; }
          50% { background-position: 100% 50%; }
          100% { background-position: 0% 50%; }
        }
        @keyframes shimmer {
          0% { transform: translateX(-100%); }
          100% { transform: translateX(100%); }
        }
        @keyframes shake {
          0%, 100% { transform: translateX(0); }
          10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
          20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        @keyframes bounce-fade {
          0% { transform: scale(0); opacity: 0; }
          50% { transform: scale(1.2); opacity: 1; }
          100% { transform: scale(1); opacity: 0; }
        }
        @keyframes bounce-horizontal {
          0%, 100% { transform: translateX(0); }
          50% { transform: translateX(4px); }
        }
        @keyframes fade-text {
          0%, 100% { opacity: 0; }
          50% { opacity: 1; }
        }

        .animate-fade-in { animation: fade-in 0.2s ease-out forwards; }
        .animate-bounce-fade { animation: bounce-fade 1s ease-out forwards; }
        .animate-bounce-horizontal { animation: bounce-horizontal 1s ease-in-out infinite; }
        .animate-slide-up { animation: slide-up 0.3s ease-out forwards; }
        .animate-shimmer-sweep { animation: shimmer-sweep 2s ease-in-out infinite; }
        .animate-heart-float { animation: heartFloat 2s ease-out forwards; }
        .animate-gradient { 
          background-size: 200% 200%;
          animation: gradient 3s ease infinite;
        }
        .animate-shimmer { animation: shimmer 3s ease-in-out infinite; }
        .animate-shake { animation: shake 0.5s ease-in-out; }
        .animate-fade-text { animation: fade-text 2s ease-in-out infinite; }
        .dragging { transition: none !important; cursor: grabbing !important; }
        .cart-modal { transition: transform 0.3s ease-out; }
        .cart-modal.dragging { transition: none !important; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #f97316; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #ea580c; }
        
        .scrollbar-visible::-webkit-scrollbar { height: 4px; }
        .scrollbar-visible::-webkit-scrollbar-track { background: #fed7aa; border-radius: 2px; }
        .scrollbar-visible::-webkit-scrollbar-thumb { background: #f97316; border-radius: 2px; }
        .scrollbar-visible::-webkit-scrollbar-thumb:hover { background: #ea580c; }
        
        html, body { background: #ffffff !important; }
      `}</style>
    </div>
  );
}
