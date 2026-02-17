import React, { useState, useEffect } from 'react';
import { ArrowLeft, ShoppingCart, User, MapPin, CreditCard, Bike, Caravan, Clock, Calendar, DollarSign, Smartphone, X, Star, Truck, Utensils, Gift, Mail, Lock, Eye, EyeOff, Sparkles, Phone, Wallet, Award } from 'lucide-react';
import TUUPaymentIntegration from './TUUPaymentIntegration.jsx';
import TUUPaymentFrame from './TUUPaymentFrame.jsx';
import ScheduleOrderModal from './ScheduleOrderModal.jsx';
import ClosedInfoModal from './modals/ClosedInfoModal.jsx';
import ReviewsModal from './ReviewsModal.jsx';
import AddressAutocomplete from './AddressAutocomplete.jsx';
import { isWithinBusinessHours, getBusinessStatus } from '../utils/businessHours.js';

const CheckoutApp = ({ onClose }) => {
  const [cart, setCart] = useState([]);
  const [cartTotal, setCartTotal] = useState(0);
  const [customerInfo, setCustomerInfo] = useState({
    name: '',
    phone: '',
    email: '',
    address: '',
    deliveryType: 'pickup',
    pickupTime: '',
    customerNotes: ''
  });
  const [user, setUser] = useState(null);
  const [step, setStep] = useState(1);
  const [paymentUrl, setPaymentUrl] = useState(null);
  const [pickupSlots, setPickupSlots] = useState([]);
  const [pickupMessages, setPickupMessages] = useState([]);
  const [nearbyTrucks, setNearbyTrucks] = useState([]);
  const [cartSubtotal, setCartSubtotal] = useState(0);
  const [isScheduleModalOpen, setIsScheduleModalOpen] = useState(false);
  const [scheduledTime, setScheduledTime] = useState(null);
  const [businessStatus, setBusinessStatus] = useState({ isOpen: true });
  const [discountCode, setDiscountCode] = useState('');
  const [discountAmount, setDiscountAmount] = useState(0);
  const [deliveryDiscountActive, setDeliveryDiscountActive] = useState(false);
  const [showCashModal, setShowCashModal] = useState(false);
  const [showCardModal, setShowCardModal] = useState(false);
  const [showTransferModal, setShowTransferModal] = useState(false);
  const [cashAmount, setCashAmount] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('');
  const [showBenefitsModal, setShowBenefitsModal] = useState(false);
  const [userPoints, setUserPoints] = useState(0);
  const [userStamps, setUserStamps] = useState(0);
  const [currentStampProgress, setCurrentStampProgress] = useState(0);
  const [availableRewards, setAvailableRewards] = useState([]);
  const [showLoginIncentiveModal, setShowLoginIncentiveModal] = useState(false);
  const [authMode, setAuthMode] = useState('login');
  const [showPassword, setShowPassword] = useState(false);
  const [authEmail, setAuthEmail] = useState('');
  const [authPassword, setAuthPassword] = useState('');
  const [authNombre, setAuthNombre] = useState('');
  const [authTelefono, setAuthTelefono] = useState('');
  const [authConfirmPassword, setAuthConfirmPassword] = useState('');
  const [authLoading, setAuthLoading] = useState(false);
  const [authFeedback, setAuthFeedback] = useState({ type: '', message: '' });
  const [appliedReward, setAppliedReward] = useState(null);
  const [showComboFreeModal, setShowComboFreeModal] = useState(false);
  const [comboFreeProducts, setComboFreeProducts] = useState({ papas: null, bebidas: [] });
  const [selectedBebida, setSelectedBebida] = useState(null);
  const [showTermsModal, setShowTermsModal] = useState(false);
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [walletBalance, setWalletBalance] = useState(0);
  const [cashbackAmount, setCashbackAmount] = useState(0);
  const [availableDrinks, setAvailableDrinks] = useState([]);
  const [isClosedModalOpen, setIsClosedModalOpen] = useState(false);
  const [deliveryExtras, setDeliveryExtras] = useState([]);
  const [selectedDeliveryExtras, setSelectedDeliveryExtras] = useState([]);
  const [showReviewModal, setShowReviewModal] = useState(false);
  const [reviewProduct, setReviewProduct] = useState(null);
  const [orderCompleted, setOrderCompleted] = useState(false);
  const [rl6Credit, setRl6Credit] = useState(null);
  const [loadingRL6, setLoadingRL6] = useState(false);
  const [isProcessingOrder, setIsProcessingOrder] = useState(false);
  
  // Verificar si es militar RL6 aprobado (loose equality para manejar strings y numbers)
  const isMilitarRL6 = (user?.es_militar_rl6 == 1 || user?.es_militar_rl6 === '1') && 
                       (user?.credito_aprobado == 1 || user?.credito_aprobado === '1');

  // Cargar beneficios del usuario desde tuu_orders
  useEffect(() => {
    if (user) {
      fetch('/api/get_user_tuu_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: user.id })
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const paidOrders = data.orders.filter(order => 
              order.payment_status === 'paid'
            );
            const totalSpent = paidOrders.reduce((sum, order) => sum + parseFloat(order.amount || 0), 0);
            const points = Math.floor(totalSpent / 10); // $10 = 1 punto
            const stamps = data.available_stamps || 0;
            
            setUserPoints(points);
            setUserStamps(stamps);
            
            // Calcular recompensas disponibles
            const rewards = [];
            if (stamps >= 2) rewards.push({ id: 'delivery', name: 'Delivery Gratis', stamps: 2, active: stamps >= 2 });
            if (stamps >= 4) rewards.push({ id: 'combo', name: 'Papas + Bebida Gratis', stamps: 4, active: stamps >= 4 });
            setAvailableRewards(rewards);
          }
        })
        .catch(error => console.error('Error loading benefits:', error));
      
      // Cargar saldo de wallet
      fetch(`/api/get_wallet_balance.php?user_id=${user.id}&t=${Date.now()}`)
        .then(response => response.json())
        .then(data => {
          console.log('💰 Wallet data:', data);
          if (data.success) {
            setWalletBalance(data.wallet.balance || 0);
            console.log('💰 Wallet balance set to:', data.wallet.balance);
          }
        })
        .catch(error => console.error('Error loading wallet:', error));
      
      // Cargar crédito RL6 si es militar aprobado
      if ((user.es_militar_rl6 == 1 || user.es_militar_rl6 === '1') && 
          (user.credito_aprobado == 1 || user.credito_aprobado === '1')) {
        setLoadingRL6(true);
        fetch(`/api/rl6/get_credit.php?user_id=${user.id}&t=${Date.now()}`)
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              setRl6Credit(data.credit);
            }
          })
          .catch(error => console.error('Error loading RL6 credit:', error))
          .finally(() => setLoadingRL6(false));
      }
    }
  }, [user, cartTotal]);

  // Cargar imágenes faltantes de productos en carrito
  useEffect(() => {
    const loadMissingImages = async () => {
      const cartItems = JSON.parse(localStorage.getItem('ruta11_cart') || '[]');
      const itemsNeedingImages = cartItems.filter(item => !item.image_url && item.id);
      
      if (itemsNeedingImages.length > 0) {
        try {
          const response = await fetch('/api/get_productos.php');
          const products = await response.json();
          
          if (Array.isArray(products)) {
            const updatedCart = cartItems.map(item => {
              if (!item.image_url && item.id) {
                const product = products.find(p => p.id === item.id);
                if (product?.image_url) {
                  return { ...item, image_url: product.image_url };
                }
              }
              return item;
            });
            
            localStorage.setItem('ruta11_cart', JSON.stringify(updatedCart));
            setCart(updatedCart);
          }
        } catch (error) {
          console.error('Error loading product images:', error);
        }
      }
    };
    
    loadMissingImages();
  }, []);
  
  useEffect(() => {
    // Check business hours
    const status = getBusinessStatus();
    setBusinessStatus(status);
    
    // NO mostrar modal a militares RL6 (tienen acceso 24/7)
    // Solo mostrar a usuarios regulares cuando está cerrado
    const shouldShowModal = !status.isOpen && !isMilitarRL6;
    if (shouldShowModal) {
      setTimeout(() => setIsClosedModalOpen(true), 500);
    }
    
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('cancelled') === '1') {
      alert('❌ Pago cancelado. Puedes intentar nuevamente.');
      window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    const savedCart = localStorage.getItem('ruta11_cart');
    const savedTotal = localStorage.getItem('ruta11_cart_total');
    
    if (savedCart) {
      const parsedCart = JSON.parse(savedCart);
      setCart(parsedCart);
      
      const subtotal = parsedCart.reduce((total, item) => {
        let itemTotal = item.price * item.quantity;
        // Agregar costo de customizaciones (extras)
        if (item.customizations && item.customizations.length > 0) {
          const customizationsTotal = item.customizations.reduce((sum, custom) => {
            return sum + (custom.price * custom.quantity);
          }, 0);
          itemTotal += customizationsTotal;
        }
        return total + itemTotal;
      }, 0);
      setCartSubtotal(subtotal);
      setCartTotal(parseFloat(savedTotal) || subtotal);
    } else {
      window.location.href = '/';
    }

    fetch('/api/auth/check_session.php')
      .then(response => response.json())
      .then(data => {
        if (data.authenticated) {
          setUser(data.user);
          setCustomerInfo(prev => ({
            ...prev,
            name: data.user.nombre || '',
            phone: data.user.telefono || '',
            email: data.user.email || '',
            address: data.user.direccion || ''
          }));
          // Usar stats del API si están disponibles
          if (data.stats) {
            const points = Math.floor((data.stats.total_spent || 0) / 10);
            const stamps = Math.floor(points / 1000);
            setUserPoints(points);
            setUserStamps(stamps);
          }
        }
      })
      .catch(error => console.error('Error checking session:', error));
      
    fetch('/api/get_pickup_times.php')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          setPickupSlots(data.slots || []);
          setPickupMessages(data.messages || []);
        }
      })
      .catch(error => console.error('Error loading pickup times:', error));
      
    fetch('/api/get_delivery_fee.php')
      .then(response => response.json())
      .then(data => {
        if (data.success && data.tarifa_delivery) {
          setNearbyTrucks([{
            id: 1,
            nombre: 'La Ruta 11',
            tarifa_delivery: data.tarifa_delivery,
            activo: 1
          }]);
        }
      })
      .catch(error => console.error('Error loading delivery fee:', error));
  }, []);
  
  // Cargar bebidas disponibles para upselling y extras de delivery
  useEffect(() => {
    fetch('/api/get_productos.php')
      .then(response => response.json())
      .then(data => {
        if (Array.isArray(data)) {
          // category_id 5 = Papas y Snacks, subcategory_id 11, 27, 28 = Bebidas
          const bebidas = data.filter(p => 
            p.category_id === 5 && (p.subcategory_id === 11 || p.subcategory_id === 27 || p.subcategory_id === 28) && (p.is_active === 1 || p.active === 1)
          ).sort((a, b) => a.subcategory_id - b.subcategory_id);
          setAvailableDrinks(bebidas);
          
          // category_id 7 = Extras, subcategory_id 30 = Extras de Delivery
          const extrasDelivery = data.filter(p => 
            p.category_id === 7 && p.subcategory_id === 30 && (p.is_active === 1 || p.active === 1)
          );
          setDeliveryExtras(extrasDelivery);
        }
      })
      .catch(error => console.error('Error loading drinks:', error));
  }, []);

  // Validar código de descuento con API
  useEffect(() => {
    const validateCode = async () => {
      if (!discountCode.trim()) {
        setDiscountAmount(0);
        return;
      }
      
      if (discountCode.toUpperCase() === 'RL6') {
        return;
      }
      
      try {
        const response = await fetch('/api/validate_discount_code.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ code: discountCode, cart: cart })
        });
        
        const result = await response.json();
        if (result.success && result.valid) {
          setDiscountAmount(result.discount_amount);
        } else {
          setDiscountAmount(0);
        }
      } catch (error) {
        console.error('Error validando código:', error);
        setDiscountAmount(0);
      }
    };
    
    validateCode();
  }, [discountCode, cart]);
  
  // Activar descuento delivery con código RL6
  useEffect(() => {
    if (discountCode.toUpperCase() === 'RL6' && customerInfo.deliveryType === 'delivery') {
      setDeliveryDiscountActive(true);
      if (customerInfo.address && !['Ctel. Oscar Quina 1333', 'Ctel. Domeyco 1540', 'Ctel. Av. Santa María 3000'].includes(customerInfo.address)) {
        setCustomerInfo(prev => ({...prev, address: ''}));
      }
    } else if (discountCode.toUpperCase() !== 'RL6') {
      setDeliveryDiscountActive(false);
    }
  }, [discountCode, customerInfo.deliveryType]);

  const deliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0 
    ? parseInt(nearbyTrucks[0].tarifa_delivery || 0) 
    : 0;
  
  const deliveryDiscountAmount = deliveryDiscountActive ? Math.round(deliveryFee * 0.4) : 0;
  const finalDeliveryCost = deliveryFee - deliveryDiscountAmount;
  
  // Calcular total de extras de delivery (sin descuentos ni cashback)
  const deliveryExtrasTotal = selectedDeliveryExtras.reduce((sum, extra) => sum + (extra.price * extra.quantity), 0);
  
  // Cashback solo aplica al subtotal de productos (no al delivery ni extras)
  const subtotalAfterDiscounts = cartSubtotal - discountAmount - cashbackAmount;
  const finalTotal = subtotalAfterDiscounts + finalDeliveryCost + deliveryExtrasTotal;
  
  useEffect(() => {
    setCartTotal(finalTotal);
  }, [finalTotal]);

  const handleTUUPayment = async () => {
    // Validar dirección con descuento RL6
    if (deliveryDiscountActive && customerInfo.deliveryType === 'delivery' && !customerInfo.address) {
      alert('⚠️ Por favor selecciona una dirección con descuento');
      return;
    }
    
    try {
      // PASO 0: Usar cashback si aplica
      if (cashbackAmount > 0 && user) {
        const cashbackRes = await fetch('/api/use_wallet_balance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            user_id: user.id,
            amount: cashbackAmount
          })
        });
        const cashbackResult = await cashbackRes.json();
        if (!cashbackResult.success) {
          alert('❌ Error al usar cashback: ' + cashbackResult.error);
          return;
        }
      }
      
      // PASO 1: Crear el pago y obtener order_id
      const paymentData = {
        amount: cartTotal,
        customer_name: customerInfo.name,
        customer_phone: customerInfo.phone,
        customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
        user_id: user?.id || null,
        cart_items: cart,
        delivery_fee: deliveryFee,
        delivery_extras: selectedDeliveryExtras,
        customer_notes: customerInfo.customerNotes || null,
        delivery_type: customerInfo.deliveryType,
        delivery_address: customerInfo.address || null,
        pickup_time: customerInfo.pickupTime || null,
        scheduled_time: scheduledTime ? `${scheduledTime.date} ${scheduledTime.time}` : null,
        is_scheduled: !!scheduledTime,
        cashback_used: cashbackAmount
      };

      console.log('Sending payment data:', paymentData);
      
      const response = await fetch('/api/tuu/create_payment_direct.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(paymentData)
      });

      const result = await response.json();
      if (result.success) {
        // PASO 1.5: Registrar uso de recompensa si aplica
        if (appliedReward) {
          await fetch('/api/record_reward_usage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              order_number: result.order_id,
              reward_type: appliedReward.type,
              stamps_consumed: appliedReward.stamps
            })
          });
        }
        
        // PASO 2: Guardar datos de delivery ANTES de redirigir (SEGURIDAD)
        const deliveryResponse = await fetch('/api/tuu/save_delivery_info.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            order_number: result.order_id,
            delivery_type: customerInfo.deliveryType,
            delivery_address: customerInfo.address,
            customer_notes: customerInfo.customerNotes,
            pickup_time: customerInfo.pickupTime
          })
        });
        
        const deliveryResult = await deliveryResponse.json();
        if (!deliveryResult.success) {
          console.warn('Error guardando delivery info:', deliveryResult.error);
        }
        
        
        // PASO 4: Marcar orden completada y redirigir
        setOrderCompleted(true);
        
        // PASO 5: Redirigir a Webpay
        window.location.href = result.payment_url;
      } else {
        alert('Error al crear el pago: ' + (result.error || 'Error desconocido'));
      }
    } catch (error) {
      console.error('Error TUU:', error);
      alert('Error al procesar el pago: ' + error.message);
    }
  };

  const processTransferOrder = async () => {
    setIsProcessingOrder(true);

    try {
      // Usar cashback si aplica
      if (cashbackAmount > 0 && user) {
        const cashbackRes = await fetch('/api/use_wallet_balance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            user_id: user.id,
            amount: cashbackAmount
          })
        });
        const cashbackResult = await cashbackRes.json();
        if (!cashbackResult.success) {
          alert('❌ Error al usar cashback: ' + cashbackResult.error);
          return;
        }
      }
      
      // Crear orden con estado 'unpaid' para transferencia
      const orderData = {
        amount: cartTotal,
        subtotal: cartSubtotal,
        discount_amount: discountAmount,
        delivery_discount: deliveryDiscountAmount,
        delivery_extras_total: deliveryExtrasTotal,
        delivery_extras: selectedDeliveryExtras,
        customer_name: customerInfo.name,
        customer_phone: customerInfo.phone,
        customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
        user_id: user?.id || null,
        cart_items: cart,
        delivery_fee: deliveryFee,
        customer_notes: customerInfo.customerNotes || null,
        delivery_type: customerInfo.deliveryType,
        delivery_address: customerInfo.address || null,
        pickup_time: customerInfo.pickupTime || null,
        payment_method: 'transfer',
        scheduled_time: scheduledTime ? `${scheduledTime.date} ${scheduledTime.time}` : null,
        is_scheduled: !!scheduledTime,
        cashback_used: cashbackAmount
      };

      const response = await fetch('/api/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
      });

      const result = await response.json();
      if (result.success) {
        // Marcar orden completada
        setOrderCompleted(true);
        
        // Limpiar carrito y redirigir
        localStorage.removeItem('ruta11_cart');
        localStorage.removeItem('ruta11_cart_total');
        
        window.location.href = '/transfer-pending?order=' + result.order_id;
      } else {
        setIsProcessingOrder(false);
        alert('Error al crear el pedido: ' + (result.error || 'Error desconocido'));
      }
    } catch (error) {
      setIsProcessingOrder(false);
      console.error('Error transfer:', error);
      alert('Error al procesar el pedido: ' + error.message);
    }
  };

  const goBack = () => {
    if (onClose) {
      onClose();
    } else {
      window.location.href = '/';
    }
  };

  const proceedToPayment = () => {
    if (!customerInfo.name || !customerInfo.phone) {
      alert('Por favor completa tu nombre y teléfono');
      return;
    }
    
    // Validar dirección con descuento RL6
    if (deliveryDiscountActive && customerInfo.deliveryType === 'delivery' && !customerInfo.address) {
      alert('⚠️ Por favor selecciona una dirección con descuento');
      return;
    }
    
    // Check if within business hours (SOLO para usuarios regulares)
    if (!businessStatus.isOpen && !scheduledTime && !isMilitarRL6) {
      setIsClosedModalOpen(true);
      return;
    }
    
    handleTUUPayment();
  };
  
  const handleScheduleOrder = (slot) => {
    setScheduledTime(slot);
    setIsScheduleModalOpen(false);
  };
  
  const handleSubmitOrder = () => {
    if (isProcessingOrder) return;
    
    if (!paymentMethod) {
      alert('⚠️ Por favor selecciona un método de pago');
      return;
    }
    
    if (!customerInfo.name || !customerInfo.phone) {
      alert('⚠️ Por favor completa tu nombre y teléfono');
      return;
    }
    
    if (customerInfo.deliveryType === 'delivery' && !customerInfo.address) {
      alert('⚠️ Por favor ingresa tu dirección de entrega');
      return;
    }
    
    if (deliveryDiscountActive && customerInfo.deliveryType === 'delivery' && !customerInfo.address) {
      alert('⚠️ Para usar el código RL6, debes seleccionar una de las direcciones disponibles');
      return;
    }
    
    // Check if within business hours (SOLO para usuarios regulares)
    if (!businessStatus.isOpen && !scheduledTime && !isMilitarRL6) {
      setIsClosedModalOpen(true);
      return;
    }
    
    setIsProcessingOrder(true);
    
    // Solo abrir modales, no procesar directamente
    if (paymentMethod === 'cash') {
      setShowCashModal(true);
      setIsProcessingOrder(false);
    } else if (paymentMethod === 'card') {
      setShowCardModal(true);
      setIsProcessingOrder(false);
    } else if (paymentMethod === 'transfer') {
      setShowTransferModal(true);
      setIsProcessingOrder(false);
    } else if (paymentMethod === 'online') {
      proceedToPayment();
    } else if (paymentMethod === 'rl6') {
      processRL6Payment();
    }
  };
  
  const confirmCashPayment = async () => {
    const cashValue = parseInt(cashAmount.replace(/\D/g, ''));
    if (!cashValue || cashValue < cartTotal) {
      alert('El monto debe ser mayor o igual al total del pedido');
      return;
    }
    
    setIsProcessingOrder(true);
    
    try {
      // Usar cashback si aplica
      if (cashbackAmount > 0 && user) {
        const cashbackRes = await fetch('/api/use_wallet_balance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            user_id: user.id,
            amount: cashbackAmount
          })
        });
        const cashbackResult = await cashbackRes.json();
        if (!cashbackResult.success) {
          alert('❌ Error al usar cashback: ' + cashbackResult.error);
          return;
        }
      }
      
      const change = cashValue - cartTotal;
      const cashNote = `💵 PAGO EN EFECTIVO - Cliente paga con: $${cashValue.toLocaleString('es-CL')} - Vuelto: $${change.toLocaleString('es-CL')}`;
      const finalNotes = customerInfo.customerNotes 
        ? `${cashNote}\n\n${customerInfo.customerNotes}` 
        : cashNote;
      
      const orderData = {
        amount: cartTotal,
        subtotal: cartSubtotal,
        discount_amount: discountAmount,
        delivery_discount: deliveryDiscountAmount,
        customer_name: customerInfo.name,
        customer_phone: customerInfo.phone,
        customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
        user_id: user?.id || null,
        cart_items: cart,
        delivery_fee: deliveryFee,
        delivery_extras: selectedDeliveryExtras,
        customer_notes: finalNotes,
        delivery_type: customerInfo.deliveryType,
        delivery_address: customerInfo.address || null,
        pickup_time: customerInfo.pickupTime || null,
        payment_method: 'cash',
        scheduled_time: scheduledTime ? `${scheduledTime.date} ${scheduledTime.time}` : null,
        is_scheduled: !!scheduledTime,
        cashback_used: cashbackAmount
      };

      const response = await fetch('/api/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
      });

      const result = await response.json();
      if (result.success) {
        setOrderCompleted(true);
        localStorage.removeItem('ruta11_cart');
        localStorage.removeItem('ruta11_cart_total');
        window.location.href = '/cash-pending?order=' + result.order_id;
      } else {
        setIsProcessingOrder(false);
        alert('Error al crear el pedido: ' + (result.error || 'Error desconocido'));
      }
    } catch (error) {
      setIsProcessingOrder(false);
      console.error('Error cash:', error);
      alert('Error al procesar el pedido: ' + error.message);
    }
  };
  
  const processRL6Payment = async () => {
    // Validar saldo disponible
    if (!rl6Credit || cartTotal > rl6Credit.credito_disponible) {
      alert(`❌ Crédito insuficiente. Disponible: $${parseInt(rl6Credit?.credito_disponible || 0).toLocaleString('es-CL')}`);
      return;
    }
    
    if (!confirm(`¿Confirmas usar tu crédito RL6? Se descontarán $${cartTotal.toLocaleString('es-CL')} de tu límite. Pagarás el 21 del mes.`)) {
      return;
    }
    
    setIsProcessingOrder(true);
    
    try {
      // Crear orden primero
      const orderData = {
        amount: cartTotal,
        subtotal: cartSubtotal,
        discount_amount: discountAmount,
        delivery_discount: deliveryDiscountAmount,
        customer_name: customerInfo.name,
        customer_phone: customerInfo.phone,
        customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
        user_id: user?.id || null,
        cart_items: cart,
        delivery_fee: deliveryFee,
        customer_notes: customerInfo.customerNotes || null,
        delivery_type: customerInfo.deliveryType,
        delivery_address: customerInfo.address || null,
        pickup_time: customerInfo.pickupTime || null,
        payment_method: 'rl6_credit',
        scheduled_time: scheduledTime ? `${scheduledTime.date} ${scheduledTime.time}` : null,
        is_scheduled: !!scheduledTime
      };

      const orderResponse = await fetch('/api/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
      });

      const orderResult = await orderResponse.json();
      if (!orderResult.success) {
        alert('❌ Error al crear orden: ' + orderResult.error);
        return;
      }
      
      // Usar crédito RL6
      const creditResponse = await fetch('/api/rl6/use_credit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: user.id,
          amount: cartTotal,
          order_id: orderResult.order_id
        })
      });
      
      const creditResult = await creditResponse.json();
      if (creditResult.success) {
        setOrderCompleted(true);
        localStorage.removeItem('ruta11_cart');
        localStorage.removeItem('ruta11_cart_total');
        window.location.href = '/rl6-pending?order=' + orderResult.order_id;
      } else {
        setIsProcessingOrder(false);
        alert('❌ Error al usar crédito: ' + creditResult.error);
      }
    } catch (error) {
      setIsProcessingOrder(false);
      console.error('Error RL6:', error);
      alert('❌ Error al procesar: ' + error.message);
    }
  };
  
  const processCardOrder = async () => {
    setIsProcessingOrder(true);
    
    try {
      // Usar cashback si aplica
      if (cashbackAmount > 0 && user) {
        const cashbackRes = await fetch('/api/use_wallet_balance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            user_id: user.id,
            amount: cashbackAmount
          })
        });
        const cashbackResult = await cashbackRes.json();
        if (!cashbackResult.success) {
          alert('❌ Error al usar cashback: ' + cashbackResult.error);
          return;
        }
      }
      
      const orderData = {
        amount: cartTotal,
        subtotal: cartSubtotal,
        discount_amount: discountAmount,
        delivery_discount: deliveryDiscountAmount,
        customer_name: customerInfo.name,
        customer_phone: customerInfo.phone,
        customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
        user_id: user?.id || null,
        cart_items: cart,
        delivery_fee: deliveryFee,
        customer_notes: customerInfo.customerNotes || null,
        delivery_type: customerInfo.deliveryType,
        delivery_address: customerInfo.address || null,
        pickup_time: customerInfo.pickupTime || null,
        payment_method: 'card',
        scheduled_time: scheduledTime ? `${scheduledTime.date} ${scheduledTime.time}` : null,
        is_scheduled: !!scheduledTime,
        cashback_used: cashbackAmount
      };

      const response = await fetch('/api/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
      });

      const result = await response.json();
      if (result.success) {
        setOrderCompleted(true);
        localStorage.removeItem('ruta11_cart');
        localStorage.removeItem('ruta11_cart_total');
        window.location.href = '/card-pending?order=' + result.order_id;
      } else {
        setIsProcessingOrder(false);
        alert('Error al crear el pedido: ' + (result.error || 'Error desconocido'));
      }
    } catch (error) {
      setIsProcessingOrder(false);
      console.error('Error card:', error);
      alert('Error al procesar el pedido: ' + error.message);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-gradient-to-r from-red-500 to-orange-500 shadow-lg fixed top-0 left-0 right-0 z-50">
        <div className="max-w-4xl mx-auto px-1 py-4">
          <div className="flex items-center justify-between gap-3">
            <div className="flex items-center gap-2">
              <button 
                onClick={goBack}
                className="p-2 hover:bg-white/20 rounded-full transition-colors"
              >
                <ArrowLeft size={20} className="text-white" />
              </button>
              <img 
                src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.jpg" 
                alt="La Ruta 11" 
                className="w-7 h-7 sm:w-8 sm:h-8"
              />
              <div>
                <h1 className="text-sm sm:text-lg font-bold text-white leading-tight">Checkout</h1>
                <p className="text-[10px] sm:text-xs text-white/80">La Ruta 11</p>
              </div>
            </div>
            
            {user && (
              <div className="flex items-center gap-2 bg-white/10 backdrop-blur-md px-2 sm:px-3 py-1.5 sm:py-2 rounded-xl border border-white/20">
                <img 
                  src={user.foto_perfil || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.nombre)}
                  alt={user.nombre}
                  className="w-8 h-8 sm:w-10 sm:h-10 rounded-full border-2 border-white/30"
                />
                <div>
                  <p className="text-xs text-white/90 font-bold leading-tight">{user.nombre.split(' ')[0]}</p>
                  <p className="text-[10px] sm:text-xs text-white/80 leading-tight">cashback: {walletBalance !== null ? `$${parseInt(walletBalance).toLocaleString('es-CL')}` : <span className="animate-pulse">...</span>}</p>
                  {isMilitarRL6 && rl6Credit && (
                    <p className="text-[10px] sm:text-xs text-amber-300 leading-tight font-semibold">crédito: ${parseInt(rl6Credit.credito_disponible).toLocaleString('es-CL')}</p>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      </header>

      <div className="max-w-4xl mx-auto px-1 py-6 mt-32 sm:mt-28">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2">
            <div className="bg-white rounded-lg shadow-sm p-1">
              <div className="mb-6">
                <h3 className="text-lg font-semibold text-gray-800 mb-3">Tipo de Entrega</h3>
                <div className="grid gap-3 grid-cols-2">
                  <button
                    type="button"
                    onClick={() => setCustomerInfo({...customerInfo, deliveryType: 'delivery'})}
                    className={`p-4 rounded-xl text-center transition-all transform hover:scale-105 ${
                      customerInfo.deliveryType === 'delivery' 
                        ? 'bg-gradient-to-br from-orange-500 to-red-600 shadow-lg' 
                        : 'bg-gradient-to-br from-gray-400 to-gray-500 opacity-60 hover:opacity-80'
                    }`}
                  >
                    <div className="flex justify-center mb-2">
                      <Bike size={32} className="text-white" />
                    </div>
                    <div className="font-bold text-white">Delivery</div>
                    <div className="text-xs text-white/90">Entrega a domicilio</div>
                    {nearbyTrucks.length > 0 && nearbyTrucks[0].tarifa_delivery && (
                      <div className="text-xs text-white font-semibold mt-1">
                        +${parseInt(nearbyTrucks[0].tarifa_delivery).toLocaleString('es-CL')}
                      </div>
                    )}
                  </button>
                  <button
                    type="button"
                    onClick={() => setCustomerInfo({...customerInfo, deliveryType: 'pickup'})}
                    className={`p-4 rounded-xl text-center transition-all transform hover:scale-105 ${
                      customerInfo.deliveryType === 'pickup' 
                        ? 'bg-gradient-to-br from-orange-500 to-red-600 shadow-lg' 
                        : 'bg-gradient-to-br from-gray-400 to-gray-500 opacity-60 hover:opacity-80'
                    }`}
                  >
                    <div className="flex justify-center mb-2">
                      <Caravan size={32} className="text-white" />
                    </div>
                    <div className="font-bold text-white">Retiro</div>
                    <div className="text-xs text-white/90">Retiro en local</div>
                  </button>
                </div>
              </div>
              
              {/* Extras de Delivery */}
              {customerInfo.deliveryType === 'delivery' && deliveryExtras.length > 0 && (
                <div className="mb-6">
                  <div className="flex items-center gap-2 mb-3">
                    <div className="bg-orange-500 p-2 rounded-lg">
                      <Sparkles size={20} className="text-white" />
                    </div>
                    <div>
                      <p className="font-bold text-gray-800 text-sm">🎉 ¿Agregar extras de delivery?</p>
                      <p className="text-xs text-gray-600">Sorprende con algo especial</p>
                    </div>
                  </div>
                  
                  <div className="flex gap-3 overflow-x-auto pb-2 scrollbar-hide" style={{scrollbarWidth: 'none', msOverflowStyle: 'none'}}>
                    {deliveryExtras.map(extra => {
                      const selected = selectedDeliveryExtras.find(e => e.id === extra.id);
                      const quantity = selected?.quantity || 0;
                      return (
                        <div key={extra.id} className="flex-shrink-0 w-28 bg-white rounded-lg border-2 border-orange-200 p-2">
                          <img src={extra.image_url} alt={extra.name} className="w-full h-20 object-cover rounded-md mb-2" />
                          <p className="text-xs font-semibold text-gray-800 truncate">{extra.name}</p>
                          <p className="text-[10px] text-gray-500 leading-tight mb-1 line-clamp-3">{extra.description}</p>
                          <p className="text-sm font-bold text-orange-600">${parseInt(extra.price).toLocaleString('es-CL')}</p>
                          
                          {quantity === 0 ? (
                            <button
                              type="button"
                              onClick={() => {
                                setSelectedDeliveryExtras([...selectedDeliveryExtras, {id: extra.id, name: extra.name, price: extra.price, quantity: 1}]);
                              }}
                              className="w-full mt-1 bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold py-1 rounded transition-colors"
                            >
                              + Agregar
                            </button>
                          ) : (
                            <div className="flex items-center justify-between mt-1 bg-orange-50 rounded px-1 py-1">
                              <button
                                type="button"
                                onClick={() => {
                                  const newExtras = selectedDeliveryExtras.map(e => 
                                    e.id === extra.id ? {...e, quantity: e.quantity - 1} : e
                                  ).filter(e => e.quantity > 0);
                                  setSelectedDeliveryExtras(newExtras);
                                }}
                                className="bg-red-500 text-white w-6 h-6 rounded font-bold hover:bg-red-600 transition-colors"
                              >
                                -
                              </button>
                              <span className="text-sm font-bold text-orange-600">{quantity}</span>
                              <button
                                type="button"
                                onClick={() => {
                                  const newExtras = selectedDeliveryExtras.map(e => 
                                    e.id === extra.id ? {...e, quantity: e.quantity + 1} : e
                                  );
                                  setSelectedDeliveryExtras(newExtras);
                                }}
                                className="bg-green-500 text-white w-6 h-6 rounded font-bold hover:bg-green-600 transition-colors"
                              >
                                +
                              </button>
                            </div>
                          )}
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}
              
              <div className="flex items-center gap-3 mb-6">
                <User className="text-orange-500" size={24} />
                <h2 className="text-lg font-semibold text-gray-800">Datos del Cliente</h2>
              </div>

              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Nombre completo *
                  </label>
                  <input
                    type="text"
                    value={customerInfo.name}
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
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Teléfono *
                  </label>
                  <input
                    type="tel"
                    value={customerInfo.phone}
                    onChange={(e) => setCustomerInfo({...customerInfo, phone: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="+56 9 1234 5678"
                    required
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-orange-600 mb-1">
                    Código de descuento
                  </label>
                  <div className="relative">
                    <input
                      type="text"
                      value={discountCode}
                      onChange={(e) => setDiscountCode(e.target.value.toUpperCase().slice(0, 7))}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 uppercase"
                      placeholder="Ej: PIZZA11, TENS"
                      maxLength="7"
                    />
                    {(discountAmount > 0 || deliveryDiscountActive) && (
                      <div className="absolute right-3 top-1/2 -translate-y-1/2 text-green-600">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                      </div>
                    )}
                  </div>
                </div>
                
                {/* Upselling Inteligente */}
                {(() => {
                  if (cart.length > 0 && availableDrinks.length > 0) {
                    return (
                      <div className="mt-4 bg-gradient-to-br from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-xl p-4 shadow-sm">
                        <div className="flex items-center gap-2 mb-3">
                          <div className="bg-blue-500 p-2 rounded-lg">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                              <path d="M3 2l2.01 18.23C5.13 21.23 5.97 22 7 22h10c1.03 0 1.87-.77 1.99-1.77L21 2H3zm9 17c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3z"/>
                            </svg>
                          </div>
                          <div>
                            <p className="font-bold text-gray-800 text-sm">💡 ¿Agregar una bebida?</p>
                            <p className="text-xs text-gray-600">Complementa tu pedido</p>
                          </div>
                        </div>
                        
                        <div className="flex gap-3 overflow-x-auto pb-2 scrollbar-hide" style={{scrollbarWidth: 'none', msOverflowStyle: 'none'}}>
                          {availableDrinks.map(drink => {
                            const inCart = cart.find(item => item.id === drink.id);
                            const quantity = inCart ? inCart.quantity : 0;
                            
                            return (
                            <div key={drink.id} className="flex-shrink-0 w-28 bg-white rounded-lg border-2 border-blue-200 p-2">
                              <img 
                                src={drink.image_url} 
                                alt={drink.name} 
                                className="w-full h-20 object-cover rounded-md mb-2"
                              />
                              <p className="text-xs font-semibold text-gray-800 truncate">{drink.name}</p>
                              <p className="text-sm font-bold text-blue-600">${parseInt(drink.price).toLocaleString('es-CL')}</p>
                              
                              {quantity === 0 ? (
                                <button
                                  onClick={() => {
                                    const newCart = [...cart, {
                                      id: drink.id,
                                      name: drink.name,
                                      price: parseFloat(drink.price),
                                      quantity: 1,
                                      image_url: drink.image_url,
                                      category_id: drink.category_id,
                                      subcategory_id: drink.subcategory_id
                                    }];
                                    setCart(newCart);
                                    localStorage.setItem('ruta11_cart', JSON.stringify(newCart));
                                    const newTotal = newCart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                                    setCartSubtotal(newTotal);
                                    localStorage.setItem('ruta11_cart_total', newTotal);
                                  }}
                                  className="w-full mt-1 bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold py-1 rounded transition-colors"
                                >
                                  + Agregar
                                </button>
                              ) : (
                                <div className="flex items-center justify-between mt-1 bg-blue-50 rounded px-1 py-1">
                                  <button
                                    onClick={() => {
                                      const newCart = cart.map(item => 
                                        item.id === drink.id && item.quantity > 1
                                          ? {...item, quantity: item.quantity - 1}
                                          : item
                                      ).filter(item => !(item.id === drink.id && item.quantity === 1));
                                      setCart(newCart);
                                      localStorage.setItem('ruta11_cart', JSON.stringify(newCart));
                                      const newTotal = newCart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                                      setCartSubtotal(newTotal);
                                      localStorage.setItem('ruta11_cart_total', newTotal);
                                    }}
                                    className="bg-red-500 text-white w-6 h-6 rounded font-bold hover:bg-red-600 transition-colors"
                                  >
                                    -
                                  </button>
                                  <span className="text-sm font-bold text-blue-600">{quantity}</span>
                                  <button
                                    onClick={() => {
                                      const newCart = cart.map(item => 
                                        item.id === drink.id
                                          ? {...item, quantity: item.quantity + 1}
                                          : item
                                      );
                                      setCart(newCart);
                                      localStorage.setItem('ruta11_cart', JSON.stringify(newCart));
                                      const newTotal = newCart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                                      setCartSubtotal(newTotal);
                                      localStorage.setItem('ruta11_cart_total', newTotal);
                                    }}
                                    className="bg-green-500 text-white w-6 h-6 rounded font-bold hover:bg-green-600 transition-colors"
                                  >
                                    +
                                  </button>
                                </div>
                              )}
                            </div>
                            );
                          })}
                        </div>
                      </div>
                    );
                  }
                  return null;
                })()}

                {customerInfo.deliveryType === 'delivery' && (
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Dirección de entrega *
                    </label>
                    {deliveryDiscountActive ? (
                      <select
                        value={customerInfo.address}
                        onChange={(e) => setCustomerInfo({...customerInfo, address: e.target.value})}
                        className="w-full px-3 py-2 border border-green-400 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 bg-green-50"
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
                        onChange={(address) => setCustomerInfo({...customerInfo, address})}
                        placeholder="Escribe tu dirección..."
                      />
                    )}
                    {nearbyTrucks.length > 0 && !deliveryDiscountActive && (
                      <p className="text-xs text-blue-600 mt-1">
                        🚚 Costo de delivery: ${parseInt(nearbyTrucks[0].tarifa_delivery || 0).toLocaleString('es-CL')}
                      </p>
                    )}
                  </div>
                )}
                
                {scheduledTime && (
                  <div className="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="text-sm font-medium text-green-800">Pedido Programado</p>
                        <p className="text-xs text-green-600">{scheduledTime.display}</p>
                      </div>
                      <button
                        onClick={() => setScheduledTime(null)}
                        className="text-red-500 hover:text-red-700 text-xs underline"
                      >
                        Cancelar
                      </button>
                    </div>
                  </div>
                )}
                
                {/* Mostrar "Programar Pedido" para Delivery y Retiro (NO para Cuartel) */}
                {customerInfo.deliveryType !== 'cuartel' && !scheduledTime && businessStatus.isOpen && (
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Horario de retiro *
                    </label>
                    <select
                      value={customerInfo.pickupTime}
                      onChange={(e) => setCustomerInfo({...customerInfo, pickupTime: e.target.value})}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                      required
                    >
                      <option value="">Seleccionar horario</option>
                      {pickupSlots.map(slot => (
                        <option key={slot.value} value={slot.value}>{slot.display}</option>
                      ))}
                    </select>
                    {pickupMessages.length > 0 && (
                      <div className="mt-2">
                        {pickupMessages.map((message, index) => (
                          <p key={index} className="text-xs text-orange-600 bg-orange-50 p-2 rounded">
                            ⏰ {message}
                          </p>
                        ))}
                      </div>
                    )}
                    {pickupSlots.length === 0 && pickupMessages.length === 0 && (
                      <p className="text-xs text-gray-500 mt-1">Tiempo de preparación: 15-30 minutos</p>
                    )}
                  </div>
                )}
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Notas adicionales (opcional)
                  </label>
                  <textarea
                    value={customerInfo.customerNotes}
                    onChange={(e) => setCustomerInfo({...customerInfo, customerNotes: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none"
                    placeholder="Ej: sin cebolla, sin tomate, extra salsa..."
                    rows="3"
                    maxLength="400"
                  />
                  <p className="text-xs text-gray-500 mt-1">Máximo 400 caracteres</p>
                </div>
              </div>
            </div>
          </div>

          <div className="lg:col-span-1">
            <div className="bg-white rounded-lg shadow-sm p-1 sticky top-24">
              <div className="flex items-center gap-3 mb-4">
                <ShoppingCart className="text-orange-500" size={20} />
                <h3 className="text-lg font-semibold text-gray-800">Tu Pedido</h3>
              </div>

              <div className="space-y-3 mb-4">
                {cart.map((item, index) => {
                  const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
                  let itemTotal = item.price * item.quantity;
                  if (item.customizations && item.customizations.length > 0) {
                    const customizationsTotal = item.customizations.reduce((sum, custom) => {
                      return sum + (custom.price * custom.quantity);
                    }, 0);
                    itemTotal += customizationsTotal;
                  }
                  return (
                    <div key={index} className="border-b border-gray-100 pb-3 last:border-b-0">
                      <div className="flex gap-2 items-start">
                        <img 
                          src={item.image_url || 'https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.jpg'} 
                          alt={item.name}
                          className="w-12 h-12 object-cover rounded-md border border-gray-200 flex-shrink-0"
                          onError={(e) => { e.target.src = 'https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.jpg'; }}
                        />
                        <div className="flex-1 min-w-0">
                          <p className="font-medium text-gray-800 text-sm truncate">{item.name}</p>
                          <p className="text-xs text-gray-500">Cantidad: {item.quantity}</p>
                          {isCombo && (item.fixed_items || item.selections) && (
                            <div className="mt-1 text-xs">
                              <div className="text-gray-600 mb-0.5">
                                <span className="font-semibold">Este combo incluye: </span>
                                {item.fixed_items && item.fixed_items.map((fixedItem, idx) => (
                                  <span key={idx}>{item.quantity}x {fixedItem.product_name || fixedItem.name}{idx < item.fixed_items.length - 1 || Object.keys(item.selections || {}).length > 0 ? ', ' : ''}</span>
                                ))}
                                {item.selections && Object.entries(item.selections).map(([group, selection], idx) => {
                                  if (Array.isArray(selection)) {
                                    return selection.map((sel, selIdx) => (
                                      <span key={`${group}-${selIdx}`}>{item.quantity}x {sel.name}{selIdx < selection.length - 1 ? ', ' : (idx < Object.keys(item.selections).length - 1 ? ', ' : '')}</span>
                                    ));
                                  } else {
                                    return (
                                      <span key={group}>{item.quantity}x {selection.name}{idx < Object.keys(item.selections).length - 1 ? ', ' : ''}</span>
                                    );
                                  }
                                })}
                              </div>
                              {item.customizations && item.customizations.length > 0 && (
                                <div className="text-orange-600">
                                  <span className="font-semibold">Además está personalizado con: </span>
                                  {item.customizations.map((custom, idx) => (
                                    <span key={idx}>
                                      {custom.quantity}x {custom.name} (+${(custom.price * custom.quantity).toLocaleString('es-CL')}){idx < item.customizations.length - 1 ? ', ' : ''}
                                    </span>
                                  ))}
                                </div>
                              )}
                            </div>
                          )}
                          {!isCombo && item.customizations && item.customizations.length > 0 && (
                            <div className="mt-1 text-xs text-orange-600">
                              <span className="font-semibold">Personalizado con: </span>
                              {item.customizations.map((custom, idx) => (
                                <span key={idx}>
                                  {custom.quantity}x {custom.name} (+${(custom.price * custom.quantity).toLocaleString('es-CL')}){idx < item.customizations.length - 1 ? ', ' : ''}
                                </span>
                              ))}
                            </div>
                          )}
                        </div>
                        <div className="flex flex-col items-end gap-1">
                          <button
                            onClick={() => {
                              const newCart = cart.filter((_, i) => i !== index);
                              setCart(newCart);
                              localStorage.setItem('ruta11_cart', JSON.stringify(newCart));
                              const newTotal = newCart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                              setCartSubtotal(newTotal);
                              localStorage.setItem('ruta11_cart_total', newTotal);
                            }}
                            className="text-red-500 hover:text-red-700 transition-colors p-1"
                            title="Eliminar"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                          </button>
                          <p className="font-semibold text-gray-800 text-sm whitespace-nowrap">
                            ${itemTotal.toLocaleString('es-CL')}
                          </p>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>

              <div className="border-t pt-4">
                <div className="space-y-2">
                  {discountAmount > 0 && (
                    <div className="flex justify-between items-center bg-yellow-100 -mx-2 px-2 py-1 rounded">
                      <span className="text-gray-700 font-medium text-sm">🎉 Descuento {discountCode.toUpperCase()}:</span>
                      <span className="font-semibold text-green-600">-${discountAmount.toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  <div className="flex justify-between items-center font-semibold">
                    <span className="text-gray-700">Subtotal productos:</span>
                    <span className="text-gray-900">${(cartSubtotal - discountAmount - cashbackAmount).toLocaleString('es-CL')}</span>
                  </div>
                  {deliveryFee > 0 && deliveryDiscountAmount > 0 && (
                    <>
                      <div className="flex justify-between items-center">
                        <span className="text-gray-600">Delivery:</span>
                        <span className="font-semibold line-through text-gray-400">${deliveryFee.toLocaleString('es-CL')}</span>
                      </div>
                      <div className="flex justify-between items-center">
                        <span className="text-gray-600">Descuento Delivery (40%):</span>
                        <span className="font-semibold text-green-600">${(deliveryFee - deliveryDiscountAmount).toLocaleString('es-CL')}</span>
                      </div>
                    </>
                  )}
                  {deliveryFee > 0 && deliveryDiscountAmount === 0 && (
                    <div className="flex justify-between items-center">
                      <span className="text-gray-600 flex items-center gap-1">
                        <Bike size={16} className="text-red-500" /> Delivery:
                      </span>
                      <span className="font-semibold">${deliveryFee.toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  {selectedDeliveryExtras.length > 0 && (
                    <div className="bg-orange-50 -mx-2 px-2 py-2 rounded">
                      <div className="flex items-center gap-1 mb-1">
                        <Sparkles size={14} className="text-orange-500" />
                        <span className="text-gray-700 font-semibold text-xs">Extras delivery:</span>
                      </div>
                      {selectedDeliveryExtras.map((extra, idx) => (
                        <div key={idx} className="flex justify-between items-center text-xs text-gray-600 ml-5">
                          <span>{extra.quantity}x {extra.name}</span>
                          <span className="font-semibold">${(extra.price * extra.quantity).toLocaleString('es-CL')}</span>
                        </div>
                      ))}
                      <div className="flex justify-between items-center mt-1 pt-1 border-t border-orange-200">
                        <span className="text-gray-700 font-semibold text-sm">Total extras:</span>
                        <span className="font-bold text-orange-600">${deliveryExtrasTotal.toLocaleString('es-CL')}</span>
                      </div>
                    </div>
                  )}
                  {user && walletBalance > 0 && (
                    <div className={`border-t pt-3 mt-3 -mx-4 px-4 py-4 rounded-lg ${walletBalance >= 500 ? 'bg-gradient-to-br from-green-50 to-emerald-50' : 'bg-gray-100'}`}>
                      <div className="flex justify-between items-center mb-3">
                        <div>
                          <span className={`text-sm font-bold flex items-center gap-1 ${walletBalance >= 500 ? 'text-gray-700' : 'text-gray-500'}`}>
                            💰 Usa cashback desde $500
                          </span>
                          <span className="text-xs text-gray-500">Disponible: ${parseInt(walletBalance).toLocaleString('es-CL')}</span>
                          {walletBalance >= 500 && (
                            <span className="text-xs text-blue-600 block mt-0.5">Solo aplica a productos</span>
                          )}
                        </div>
                        {walletBalance >= 500 && (
                          <button
                            onClick={() => setCashbackAmount(Math.min(walletBalance, cartSubtotal - discountAmount))}
                            className="text-xs bg-green-600 text-white px-3 py-1.5 rounded-full font-medium hover:bg-green-700 transition-colors"
                          >
                            Usar todo
                          </button>
                        )}
                      </div>
                      <input
                        type="range"
                        min="0"
                        max={walletBalance >= 500 ? Math.min(walletBalance, cartSubtotal - discountAmount) : 0}
                        step="10"
                        value={walletBalance >= 500 ? cashbackAmount : 0}
                        onChange={(e) => walletBalance >= 500 && setCashbackAmount(parseInt(e.target.value))}
                        disabled={walletBalance < 500}
                        className={`w-full h-3 rounded-lg appearance-none ${walletBalance >= 500 ? 'bg-gray-200 cursor-pointer accent-green-600' : 'bg-gray-300 cursor-not-allowed'}`}
                        style={{
                          background: walletBalance >= 500
                            ? `linear-gradient(to right, #10b981 0%, #10b981 ${(cashbackAmount / Math.min(walletBalance, cartSubtotal - discountAmount)) * 100}%, #e5e7eb ${(cashbackAmount / Math.min(walletBalance, cartSubtotal - discountAmount)) * 100}%, #e5e7eb 100%)`
                            : '#d1d5db'
                        }}
                      />
                      {walletBalance >= 500 && (
                        <div className="flex justify-between items-center mt-3">
                          <span className="text-sm font-bold text-green-700">Aplicando: ${parseInt(cashbackAmount).toLocaleString('es-CL')}</span>
                          {cashbackAmount > 0 && (
                            <button
                              onClick={() => setCashbackAmount(0)}
                              className="text-xs text-red-600 font-medium hover:underline"
                            >
                              Limpiar
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                  )}
                  {cashbackAmount > 0 && (
                    <div className="flex justify-between items-center bg-green-100 -mx-2 px-2 py-1 rounded">
                      <span className="text-gray-700 font-medium text-sm">💰 Cashback Aplicado:</span>
                      <span className="font-semibold text-green-600">-${cashbackAmount.toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  <div className="flex justify-between items-center text-lg font-bold border-t pt-2">
                    <span>Total:</span>
                    <span className="text-orange-500">${cartTotal.toLocaleString('es-CL')}</span>
                  </div>
                </div>
              </div>

              {/* LÓGICA: 
                  - Delivery/Retiro SIN programar → Solo botón Programar
                  - Delivery/Retiro CON programado → Métodos de pago
                  - Cuartel → Métodos de pago directo
              */}
              {(() => {
                // Verificar horario hábil (18:00 - 00:45)
                const now = new Date();
                const hours = now.getHours();
                const minutes = now.getMinutes();
                const currentTime = hours * 60 + minutes;
                const openTime = 18 * 60;
                const closeTime = 0 * 60 + 45;
                const isWithinHours = (currentTime >= openTime) || (currentTime <= closeTime);
                
                // Mostrar botón Programar si: no es militar RL6, no es cuartel, no hay pedido programado, y está fuera de horario
                if (!isMilitarRL6 && customerInfo.deliveryType !== 'cuartel' && !scheduledTime && !isWithinHours) {
                  return (
                    <button
                      onClick={() => setIsScheduleModalOpen(true)}
                      className="w-full mt-6 bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2"
                    >
                      <Calendar size={20} />
                      Programar Pedido
                    </button>
                  );
                }
                
                // Mostrar métodos de pago
                return (
                <div className="mt-6">
                  
                  <h3 className="text-lg font-semibold bg-yellow-400 text-black mb-3 flex items-center gap-2 px-3 py-2 rounded-lg">
                    <CreditCard size={20} className="text-black" />
                    Método de Pago
                  </h3>
                  <div className="grid grid-cols-2 gap-3 mb-4">
                    <button
                      type="button"
                      onClick={() => setPaymentMethod('cash')}
                      className={`p-4 rounded-lg border-2 transition-all flex flex-col items-center gap-2 ${
                        paymentMethod === 'cash'
                          ? 'border-green-500 bg-green-500 text-white'
                          : 'border-gray-300 hover:border-green-300'
                      }`}
                    >
                      <DollarSign size={24} className={paymentMethod === 'cash' ? 'text-white' : 'text-gray-600'} />
                      <span className={`font-bold text-sm ${paymentMethod === 'cash' ? 'text-white' : 'text-gray-700'}`}>
                        Efectivo
                      </span>
                    </button>

                    <button
                      type="button"
                      onClick={() => setPaymentMethod('card')}
                      className={`p-4 rounded-lg border-2 transition-all flex flex-col items-center gap-2 ${
                        paymentMethod === 'card'
                          ? 'border-blue-500 bg-blue-500 text-white'
                          : 'border-gray-300 hover:border-blue-300'
                      }`}
                    >
                      <CreditCard size={24} className={paymentMethod === 'card' ? 'text-white' : 'text-gray-600'} />
                      <span className={`font-bold text-sm ${paymentMethod === 'card' ? 'text-white' : 'text-gray-700'}`}>
                        Tarjeta
                      </span>
                    </button>

                    <button
                      type="button"
                      onClick={() => setPaymentMethod('transfer')}
                      className={`p-4 rounded-lg border-2 transition-all flex flex-col items-center gap-2 ${
                        paymentMethod === 'transfer'
                          ? 'border-orange-500 bg-orange-500 text-white'
                          : 'border-gray-300 hover:border-orange-300'
                      }`}
                    >
                      <Smartphone size={24} className={paymentMethod === 'transfer' ? 'text-white' : 'text-gray-600'} />
                      <span className={`font-bold text-sm ${paymentMethod === 'transfer' ? 'text-white' : 'text-gray-700'}`}>
                        Transferencia
                      </span>
                    </button>

                    <button
                      type="button"
                      onClick={() => setPaymentMethod('online')}
                      className={`p-4 rounded-lg border-2 transition-all flex flex-col items-center gap-2 ${
                        paymentMethod === 'online'
                          ? 'border-gray-500 bg-gray-500 text-white'
                          : 'border-gray-300 hover:border-gray-400'
                      }`}
                    >
                      <CreditCard size={24} className={paymentMethod === 'online' ? 'text-white' : 'text-gray-600'} />
                      <span className={`font-bold text-sm ${paymentMethod === 'online' ? 'text-white' : 'text-gray-700'}`}>
                        Pago Online
                      </span>
                    </button>
                    
                    {isMilitarRL6 && rl6Credit && (
                      <button
                        type="button"
                        onClick={() => setPaymentMethod('rl6')}
                        className={`p-4 rounded-lg border-2 transition-all flex flex-col items-center gap-2 col-span-2 ${
                          paymentMethod === 'rl6'
                            ? 'border-red-500 bg-gradient-to-r from-red-500 to-orange-500 text-white'
                            : 'border-gray-300 hover:border-red-300'
                        }`}
                      >
                        <Award size={24} className={paymentMethod === 'rl6' ? 'text-white' : 'text-gray-600'} />
                        <div className="text-center">
                          <span className={`font-bold text-sm block ${paymentMethod === 'rl6' ? 'text-white' : 'text-gray-700'}`}>
                            🎖️ Crédito RL6
                          </span>
                          <span className={`text-xs ${paymentMethod === 'rl6' ? 'text-white' : 'text-gray-500'}`}>
                            Disponible: ${parseInt(rl6Credit.credito_disponible).toLocaleString('es-CL')}
                          </span>
                        </div>
                      </button>
                    )}
                  </div>
                  
                  <button
                    onClick={handleSubmitOrder}
                    disabled={!paymentMethod || isProcessingOrder}
                    className="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-lg transition-all shadow-lg relative overflow-hidden"
                  >
                    {isProcessingOrder && (
                      <div className="absolute inset-0 bg-yellow-400 animate-fill rounded-lg z-0"></div>
                    )}
                    <span className="relative z-10">
                      {isProcessingOrder ? 'Procesando...' : 'Confirmar Pedido'}
                    </span>
                  </button>
                  
                  <div className="p-3 bg-green-50 rounded-lg mt-3">
                    <p className="text-xs text-green-700 text-center">
                      🔒 Todos los métodos son seguros
                    </p>
                  </div>
                </div>
                );
              })()}
            </div>
          </div>
        </div>
      </div>
      
      <ScheduleOrderModal 
        isOpen={isScheduleModalOpen}
        onClose={() => setIsScheduleModalOpen(false)}
        onSchedule={handleScheduleOrder}
      />
      
      <ClosedInfoModal 
        isOpen={isClosedModalOpen}
        onClose={() => setIsClosedModalOpen(false)}
        nextOpenDay={(() => {
          const now = new Date();
          const chileTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/Santiago' }));
          const hours = chileTime.getHours();
          const minutes = chileTime.getMinutes();
          const dayOfWeek = chileTime.getDay();
          const currentMinutes = hours * 60 + minutes;
          const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
          
          // Horarios según día
          let openTime, closeTime;
          if (dayOfWeek >= 1 && dayOfWeek <= 4) {
            openTime = 18 * 60;
            closeTime = 0 * 60 + 30;
          } else if (dayOfWeek === 5 || dayOfWeek === 6) {
            openTime = 18 * 60;
            closeTime = 2 * 60 + 30;
          } else {
            openTime = 18 * 60;
            closeTime = 0;
          }
          
          // Si estamos antes de las 18:00 del mismo día, abre hoy
          if (currentMinutes < openTime) {
            return 'Hoy';
          }
          
          // Si estamos después del cierre (madrugada), abre hoy mismo a las 18:00
          if (currentMinutes <= closeTime) {
            return 'Hoy';
          }
          
          // Si no, abre mañana
          return 'Mañana';
        })()}
        nextOpenTime="18:00"
        isActive={nearbyTrucks.length > 0 ? nearbyTrucks[0].activo : true}
      />
      
      
      {/* Modal de Efectivo */}
      {showCashModal && (
        <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={() => setShowCashModal(false)}>
          <div className="bg-white w-full max-w-md mx-4 rounded-2xl flex flex-col animate-slide-up" onClick={(e) => e.stopPropagation()}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-xl font-bold text-green-600">💵 Confirmar Pago en Efectivo</h2>
                <button onClick={() => setShowCashModal(false)} className="p-1 text-gray-400 hover:text-gray-600">
                  <X size={24} />
                </button>
              </div>
              
              <p className="text-gray-600 mb-4">¿Con cuánto pagarás?</p>
              
              <div className="mb-4">
                <input
                  type="text"
                  value={cashAmount}
                  onChange={(e) => {
                    const value = e.target.value.replace(/\D/g, '');
                    if (value) {
                      setCashAmount('$' + parseInt(value).toLocaleString('es-CL'));
                    } else {
                      setCashAmount('');
                    }
                  }}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-lg font-semibold"
                  placeholder="$0"
                />
                <p className="text-xs text-gray-500 mt-2">
                  Total a pagar: <span className="font-bold text-orange-600">${cartTotal.toLocaleString('es-CL')}</span>
                </p>
              </div>
              
              <div className="space-y-2">
                <button
                  onClick={confirmCashPayment}
                  disabled={!cashAmount || isProcessingOrder}
                  className="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-lg transition-all relative overflow-hidden"
                >
                  {isProcessingOrder && (
                    <div className="absolute inset-0 bg-yellow-400 animate-fill rounded-lg z-0"></div>
                  )}
                  <span className="relative z-10">
                    {isProcessingOrder ? 'Procesando...' : 'Confirmar Pedido'}
                  </span>
                </button>
                <button
                  onClick={() => setShowCashModal(false)}
                  className="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* Modal de Tarjeta */}
      {showCardModal && (
        <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={() => setShowCardModal(false)}>
          <div className="bg-white w-full max-w-md mx-4 rounded-2xl flex flex-col animate-slide-up" onClick={(e) => e.stopPropagation()}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-xl font-bold text-blue-600">💳 Pago con Tarjeta</h2>
                <button onClick={() => setShowCardModal(false)} className="p-1 text-gray-400 hover:text-gray-600">
                  <X size={24} />
                </button>
              </div>
              
              <p className="text-gray-600 mb-4">Pagarás con tarjeta de débito o crédito al recibir tu pedido. El repartidor o cajero llevará el terminal de pago.</p>
              
              <div className="bg-blue-50 p-3 rounded-lg mb-4">
                <p className="text-sm text-blue-800">✓ Aceptamos todas las tarjetas</p>
                <p className="text-sm text-blue-800">✓ Pago seguro con terminal POS</p>
                <p className="text-sm text-blue-800">✓ Débito y crédito</p>
              </div>
              
              <div className="space-y-2">
                <button
                  onClick={processCardOrder}
                  disabled={isProcessingOrder}
                  className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-lg transition-all relative overflow-hidden"
                >
                  {isProcessingOrder && (
                    <div className="absolute inset-0 bg-yellow-400 animate-fill rounded-lg z-0"></div>
                  )}
                  <span className="relative z-10">
                    {isProcessingOrder ? 'Procesando...' : 'Confirmar Pedido'}
                  </span>
                </button>
                <button
                  onClick={() => setShowCardModal(false)}
                  className="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* Modal de Transferencia */}
      {showTransferModal && (
        <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={() => setShowTransferModal(false)}>
          <div className="bg-white w-full max-w-md mx-4 rounded-2xl flex flex-col animate-slide-up" onClick={(e) => e.stopPropagation()}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-xl font-bold text-orange-600">📱 Transferencia Bancaria</h2>
                <button onClick={() => setShowTransferModal(false)} className="p-1 text-gray-400 hover:text-gray-600">
                  <X size={24} />
                </button>
              </div>
              
              <p className="text-gray-600 mb-4">Realiza una transferencia bancaria y envíanos el comprobante por WhatsApp. Tu pedido se confirmará al verificar el pago.</p>
              
              <div className="bg-orange-50 p-3 rounded-lg mb-4">
                <p className="text-sm text-orange-800">✓ Transferencia o depósito</p>
                <p className="text-sm text-orange-800">✓ Envía comprobante por WhatsApp</p>
                <p className="text-sm text-orange-800">✓ Confirmación inmediata</p>
              </div>
              
              <div className="space-y-2">
                <button
                  onClick={processTransferOrder}
                  disabled={isProcessingOrder}
                  className="w-full bg-orange-600 hover:bg-orange-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-lg transition-all relative overflow-hidden"
                >
                  {isProcessingOrder && (
                    <div className="absolute inset-0 bg-yellow-400 animate-fill rounded-lg z-0"></div>
                  )}
                  <span className="relative z-10">
                    {isProcessingOrder ? 'Procesando...' : 'Confirmar Pedido'}
                  </span>
                </button>
                <button
                  onClick={() => setShowTransferModal(false)}
                  className="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* Modal de Incentivo para Login */}
      {showLoginIncentiveModal && (
        <div className="fixed inset-0 bg-black/90 backdrop-blur-sm z-50 flex items-center justify-center p-4" onClick={() => setShowLoginIncentiveModal(false)}>
          <div className="bg-slate-900 w-full max-w-md max-h-[90vh] rounded-2xl shadow-2xl overflow-hidden animate-slide-up flex flex-col" onClick={(e) => e.stopPropagation()}>
            
            {/* Header */}
            <div className="bg-gradient-to-r from-orange-600 to-red-600 p-6 relative">
              <button onClick={() => setShowLoginIncentiveModal(false)} className="absolute top-4 right-4 text-white/80 hover:text-white">
                <X size={24} />
              </button>
              <div className="flex items-center gap-3 mb-2">
                <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.jpg" alt="La Ruta 11" className="w-12 h-12" />
                <div>
                  <h2 className="text-2xl font-black text-white">LA RUTA 11</h2>
                  <p className="text-orange-100 text-sm">Tu cuenta, tus beneficios</p>
                </div>
              </div>
            </div>

            {/* Benefits Section */}
            <div className="bg-slate-800 p-4 border-b border-slate-700">
              <h3 className="text-white font-bold text-sm mb-3 flex items-center gap-2">
                <Star size={16} className="text-yellow-400" />
                ¿Por qué crear una cuenta?
              </h3>
              <div className="space-y-2">
                <div className="flex items-start gap-2 text-xs">
                  <Wallet size={14} className="text-green-400 mt-0.5 shrink-0" />
                  <span className="text-slate-300 leading-tight">Gana 1% cashback en cada compra</span>
                </div>
                <div className="flex items-start gap-2 text-xs">
                  <Truck size={14} className="text-blue-400 mt-0.5 shrink-0" />
                  <span className="text-slate-300 leading-tight">Usa tu cashback desde $500</span>
                </div>
                <div className="flex items-start gap-2 text-xs">
                  <Star size={14} className="text-orange-400 mt-0.5 shrink-0" />
                  <span className="text-slate-300 leading-tight">Historial de pedidos y saldo</span>
                </div>
              </div>
            </div>

            {/* Tabs */}
            <div className="flex bg-slate-800 border-b border-slate-700">
              <button
                onClick={() => setAuthMode('login')}
                className={`flex-1 py-3 text-sm font-bold transition-colors ${
                  authMode === 'login' 
                    ? 'bg-slate-900 text-orange-500 border-b-2 border-orange-500' 
                    : 'text-slate-400 hover:text-white'
                }`}
              >
                Iniciar Sesión
              </button>
              <button
                onClick={() => setAuthMode('register')}
                className={`flex-1 py-3 text-sm font-bold transition-colors ${
                  authMode === 'register' 
                    ? 'bg-slate-900 text-orange-500 border-b-2 border-orange-500' 
                    : 'text-slate-400 hover:text-white'
                }`}
              >
                Registrarse
              </button>
            </div>

            {/* Form */}
            <div className="p-6 space-y-4 overflow-y-auto">
              {authMode === 'register' && (
                <div>
                  <label className="block text-slate-300 text-sm font-medium mb-2">
                    <User size={14} className="inline mr-1" />
                    Nombre Completo
                  </label>
                  <input
                    type="text"
                    value={authNombre}
                    onChange={(e) => setAuthNombre(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="Juan Pérez"
                  />
                </div>
              )}

              <div>
                <label className="block text-slate-300 text-sm font-medium mb-2">
                  <Mail size={14} className="inline mr-1" />
                  Email
                </label>
                <input
                  type="email"
                  value={authEmail}
                  onChange={(e) => setAuthEmail(e.target.value)}
                  className="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                  placeholder="tu@email.com"
                />
              </div>

              <div>
                <label className="block text-slate-300 text-sm font-medium mb-2">
                  <Lock size={14} className="inline mr-1" />
                  Contraseña
                </label>
                <div className="relative">
                  <input
                    type={showPassword ? "text" : "password"}
                    value={authPassword}
                    onChange={(e) => setAuthPassword(e.target.value)}
                    className="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2.5 pr-20 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="Mínimo 6 caracteres"
                    minLength={6}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-12 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors"
                  >
                    {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                  </button>
                  <span className={`absolute right-4 top-1/2 -translate-y-1/2 text-xs font-mono transition-colors ${
                    authPassword.length >= 6 ? 'text-green-400' : 'text-slate-500'
                  }`}>
                    {authPassword.length}/6
                  </span>
                </div>
              </div>

              {authMode === 'register' && (
                <>
                  <div>
                    <label className="block text-slate-300 text-sm font-medium mb-2">
                      <Lock size={14} className="inline mr-1" />
                      Confirmar Contraseña
                    </label>
                    <input
                      type="password"
                      value={authConfirmPassword}
                      onChange={(e) => setAuthConfirmPassword(e.target.value)}
                      className="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                      placeholder="Repite tu contraseña"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-300 text-sm font-medium mb-2">
                      <Phone size={14} className="inline mr-1" />
                      Teléfono (opcional)
                    </label>
                    <input
                      type="tel"
                      value={authTelefono}
                      onChange={(e) => setAuthTelefono(e.target.value)}
                      className="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                      placeholder="+56 9 1234 5678"
                    />
                  </div>
                </>
              )}

              {authFeedback.message && (
                <div className={`flex items-center gap-2 p-3 rounded-lg text-sm ${
                  authFeedback.type === 'success' 
                    ? 'bg-green-900/30 border border-green-700 text-green-400' 
                    : 'bg-red-900/30 border border-red-700 text-red-400'
                }`}>
                  {authFeedback.type === 'success' ? '✓' : '✕'}
                  <span>{authFeedback.message}</span>
                </div>
              )}

              <button
                onClick={async () => {
                  if (!authEmail || !authPassword) {
                    setAuthFeedback({ type: 'error', message: 'Completa email y contraseña' });
                    return;
                  }
                  
                  if (authMode === 'register') {
                    if (!authNombre) {
                      setAuthFeedback({ type: 'error', message: 'Ingresa tu nombre completo' });
                      return;
                    }
                    if (authPassword !== authConfirmPassword) {
                      setAuthFeedback({ type: 'error', message: 'Las contraseñas no coinciden' });
                      return;
                    }
                  }
                  
                  setAuthLoading(true);
                  setAuthFeedback({ type: '', message: '' });
                  
                  try {
                    const endpoint = authMode === 'register' 
                      ? '/api/auth/register_manual.php' 
                      : '/api/auth/login_manual.php';
                    
                    const formData = new FormData();
                    formData.append('email', authEmail);
                    formData.append('password', authPassword);
                    if (authMode === 'register') {
                      formData.append('nombre', authNombre);
                      if (authTelefono) formData.append('telefono', authTelefono);
                    }
                    
                    const response = await fetch(endpoint, {
                      method: 'POST',
                      body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                      setAuthFeedback({ 
                        type: 'success', 
                        message: authMode === 'register' ? '¡Registro exitoso!' : '¡Bienvenido!'
                      });
                      setTimeout(() => {
                        window.location.reload();
                      }, 1500);
                    } else {
                      setAuthFeedback({ type: 'error', message: result.error || 'Error al procesar' });
                    }
                  } catch (error) {
                    setAuthFeedback({ type: 'error', message: 'Error de conexión' });
                  } finally {
                    setAuthLoading(false);
                  }
                }}
                disabled={authLoading}
                className={`w-full bg-white text-black font-bold py-3 rounded-lg transition-all shadow-lg relative overflow-hidden ${
                  authLoading 
                    ? 'cursor-not-allowed' 
                    : 'hover:bg-gray-800 hover:text-white hover:border-2 hover:border-gray-400 hover:shadow-2xl hover:scale-105'
                }`}
              >
                {authLoading && (
                  <div className="absolute inset-0 bg-yellow-400 animate-fill rounded-lg z-0"></div>
                )}
                <span className="relative z-10">
                {authLoading ? 'Procesando...' : (authMode === 'register' ? 'Crear Cuenta' : 'Iniciar Sesión')}
                </span>
              </button>

              <div className="relative">
                <div className="absolute inset-0 flex items-center">
                  <div className="w-full border-t border-slate-700"></div>
                </div>
                <div className="relative flex justify-center text-xs">
                  <span className="bg-slate-900 px-2 text-yellow-400 font-semibold">o continúa rápido y simple 😎👇</span>
                </div>
              </div>

              <button
                onClick={() => {
                  setAuthLoading(true);
                  const authUrl = 'https://accounts.google.com/o/oauth2/auth?' + new URLSearchParams({
                    client_id: '531902921465-1l4fa0esvcbhdlq4btejp7d1thdtj4a7.apps.googleusercontent.com',
                    redirect_uri: 'https://app.laruta11.cl/api/auth/google/app_callback.php',
                    scope: 'email profile',
                    response_type: 'code'
                  });
                  window.location.href = authUrl;
                }}
                disabled={authLoading}
                className={`w-full bg-white text-black font-semibold py-3 rounded-lg transition-all flex items-center justify-center gap-2 relative overflow-hidden ${
                  authLoading 
                    ? 'cursor-not-allowed' 
                    : 'hover:bg-gray-800 hover:text-white hover:border-2 hover:border-gray-400'
                }`}
              >
                {authLoading && (
                  <div className="absolute inset-0 bg-yellow-400 animate-fill rounded-lg z-0"></div>
                )}
                <svg className="w-5 h-5 relative z-10" viewBox="0 0 24 24">
                  <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                  <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                  <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                  <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span className="relative z-10">Google</span>
              </button>
            </div>

          </div>
        </div>
      )}
      
      {/* Modal de Términos y Condiciones */}
      {showTermsModal && (
        <div className="fixed inset-0 bg-black/90 backdrop-blur-sm z-[60] flex items-center justify-center p-4" onClick={() => setShowTermsModal(false)}>
          <div className="bg-white w-full max-w-2xl max-h-[90vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col" onClick={(e) => e.stopPropagation()}>
            <div className="bg-gradient-to-r from-orange-600 to-red-600 p-6 relative">
              <button onClick={() => setShowTermsModal(false)} className="absolute top-4 right-4 text-white/80 hover:text-white">
                <X size={24} />
              </button>
              <h2 className="text-2xl font-black text-white">Términos y Condiciones</h2>
              <p className="text-orange-100 text-sm">Sistema de Beneficios La Ruta 11</p>
            </div>
            
            <div className="p-6 overflow-y-auto">
              <div className="space-y-4 text-gray-700">
                <div>
                  <h3 className="font-bold text-lg text-gray-900 mb-2">1. Sistema de Puntos y Sellos</h3>
                  <ul className="text-sm leading-relaxed list-disc list-inside space-y-1">
                    <li><strong>Acumulación:</strong> $10 gastados = 1 punto</li>
                    <li><strong>Conversión:</strong> 1.000 puntos = 1 sello</li>
                    <li><strong>Cálculo:</strong> Solo se consideran productos (sin delivery)</li>
                    <li><strong>Automático:</strong> Los puntos se acumulan con cada compra pagada</li>
                  </ul>
                </div>
                
                <div>
                  <h3 className="font-bold text-lg text-gray-900 mb-2">2. Sistema de Cashback</h3>
                  <ul className="text-sm leading-relaxed list-disc list-inside space-y-1">
                    <li><strong>🥉 Nivel 1 (6 sellos):</strong> Ganas $6.000 en saldo</li>
                    <li><strong>🥈 Nivel 2 (12 sellos):</strong> Ganas $12.000 en saldo</li>
                    <li><strong>🥇 Nivel 3 (18 sellos):</strong> Ganas $18.000 en saldo</li>
                    <li><strong>Total posible:</strong> $36.000 en cashback</li>
                    <li><strong>Uso:</strong> El saldo se puede usar en cualquier compra futura</li>
                    <li><strong>Aplicación:</strong> El cashback solo aplica al subtotal (no al delivery)</li>
                    <li><strong>Combinable:</strong> El cashback SÍ se puede usar junto con beneficios de sellos</li>
                  </ul>
                </div>
                
                <div>
                  <h3 className="font-bold text-lg text-gray-900 mb-2">3. Aplicación de Beneficios</h3>
                  <p className="text-sm leading-relaxed">
                    Los beneficios se aplican <strong>únicamente en el pedido actual</strong> realizado a través de la página de checkout. 
                    No se generan códigos, cupones ni QR. El beneficio se descuenta automáticamente al confirmar el pedido.
                  </p>
                </div>
                
                <div>
                  <h3 className="font-bold text-lg text-gray-900 mb-2">4. Consumo de Sellos</h3>
                  <p className="text-sm leading-relaxed">
                    Al aplicar un beneficio, los sellos correspondientes se <strong>consumen inmediatamente</strong> al confirmar la compra. 
                    Esta acción es <strong>irreversible</strong> y no se pueden recuperar los sellos una vez utilizados.
                  </p>
                </div>
                
                <div>
                  <h3 className="font-bold text-lg text-gray-900 mb-2">5. Uso Único por Pedido</h3>
                  <p className="text-sm leading-relaxed">
                    Solo se puede aplicar <strong>un beneficio de sellos por pedido</strong>. No es posible combinar múltiples recompensas de sellos. El cashback SÍ se puede usar junto con beneficios.
                  </p>
                </div>
                
                <div>
                  <h3 className="font-bold text-lg text-gray-900 mb-2">6. Requisitos Mínimos</h3>
                  <ul className="text-sm leading-relaxed list-disc list-inside space-y-1">
                    <li><strong>Delivery Gratis:</strong> Solo aplica para pedidos con entrega a domicilio</li>
                    <li><strong>Papas + Bebida Gratis:</strong> Compra mínima de $10.000</li>
                  </ul>
                </div>
                
                <div>
                  <h3 className="font-bold text-lg text-gray-900 mb-2">7. Productos Específicos</h3>
                  <p className="text-sm leading-relaxed">
                    El beneficio "Papas + Bebida Gratis" incluye productos específicos predefinidos. 
                    El usuario puede elegir la bebida de una lista de opciones disponibles. 
                    No se pueden sustituir por otros productos.
                  </p>
                </div>
                
                <div>
                  <h3 className="font-bold text-lg text-gray-900 mb-2">8. Cancelación de Pedido</h3>
                  <p className="text-sm leading-relaxed">
                    Si un pedido con beneficio aplicado es <strong>cancelado antes del pago</strong>, los sellos NO se consumen. 
                    Si el pedido es cancelado <strong>después del pago</strong>, los sellos ya fueron consumidos y no se reembolsan.
                  </p>
                </div>
                
                <div>
                  <h3 className="font-bold text-lg text-gray-900 mb-2">9. Validez y Modificaciones</h3>
                  <p className="text-sm leading-relaxed">
                    La Ruta 11 se reserva el derecho de modificar, suspender o cancelar el programa de beneficios en cualquier momento. 
                    Los sellos acumulados no tienen fecha de vencimiento mientras el programa esté activo.
                  </p>
                </div>
                
                <div>
                  <h3 className="font-bold text-lg text-gray-900 mb-2">10. Uso Indebido</h3>
                  <p className="text-sm leading-relaxed">
                    Cualquier intento de fraude, manipulación o uso indebido del sistema resultará en la <strong>suspensión inmediata</strong> de la cuenta 
                    y pérdida de todos los sellos acumulados.
                  </p>
                </div>
                
                <div className="bg-green-50 border border-green-200 rounded-lg p-4 mt-6">
                  <p className="text-sm text-green-800">
                    <strong>✓ Importante:</strong> Al hacer click en "Aplicar" en cualquier beneficio o usar cashback, confirmas que has leído y aceptas estos términos y condiciones.
                  </p>
                </div>
              </div>
            </div>
            
            <div className="p-6 border-t bg-gray-50">
              <button
                onClick={() => setShowTermsModal(false)}
                className="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold py-3 rounded-lg transition-colors"
              >
                Entendido
              </button>
            </div>
          </div>
        </div>
      )}
      
      {/* Modal de Selección Papas + Bebida */}
      {showComboFreeModal && (
        <div className="fixed inset-0 bg-black/90 backdrop-blur-sm z-50 flex items-center justify-center p-4" onClick={() => setShowComboFreeModal(false)}>
          <div className="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden" onClick={(e) => e.stopPropagation()}>
            <div className="bg-gradient-to-r from-green-600 to-emerald-600 p-6 relative">
              <button onClick={() => setShowComboFreeModal(false)} className="absolute top-4 right-4 text-white/80 hover:text-white">
                <X size={24} />
              </button>
              <h2 className="text-2xl font-black text-white">Papas + Bebida Gratis</h2>
              <p className="text-green-100 text-sm">Selecciona tu bebida favorita</p>
            </div>
            
            <div className="p-6">
              {/* Papas fijas */}
              {comboFreeProducts.papas && (
                <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                  <div className="flex items-center gap-3">
                    <img src={comboFreeProducts.papas.image_url} alt={comboFreeProducts.papas.name} className="w-16 h-16 object-cover rounded" />
                    <div className="flex-1">
                      <p className="font-semibold text-gray-800">{comboFreeProducts.papas.name}</p>
                      <p className="text-sm text-green-600 font-bold">✓ Incluido</p>
                    </div>
                  </div>
                </div>
              )}
              
              {/* Selección de bebida */}
              <div>
                <h3 className="font-semibold text-gray-800 mb-3">Elige tu bebida:</h3>
                <div className="space-y-2 max-h-64 overflow-y-auto">
                  {comboFreeProducts.bebidas.map(bebida => (
                    <button
                      key={bebida.id}
                      onClick={() => setSelectedBebida(bebida)}
                      className={`w-full p-3 border-2 rounded-lg transition-all flex items-center gap-3 ${
                        selectedBebida?.id === bebida.id
                          ? 'border-green-500 bg-green-50'
                          : 'border-gray-200 hover:border-green-300'
                      }`}
                    >
                      <img src={bebida.image_url} alt={bebida.name} className="w-12 h-12 object-cover rounded" />
                      <div className="flex-1 text-left">
                        <p className="font-medium text-gray-800">{bebida.name}</p>
                        <p className="text-xs text-gray-500">Gratis</p>
                      </div>
                      {selectedBebida?.id === bebida.id && (
                        <div className="text-green-600">✓</div>
                      )}
                    </button>
                  ))}
                </div>
              </div>
              
              <button
                onClick={() => {
                  if (!selectedBebida) {
                    alert('⚠️ Selecciona una bebida');
                    return;
                  }
                  // Agregar al carrito con precio 0
                  const newCart = [...cart];
                  newCart.push({
                    ...comboFreeProducts.papas,
                    quantity: 1,
                    price: 0,
                    originalPrice: comboFreeProducts.papas.price,
                    isFreeReward: true
                  });
                  newCart.push({
                    ...selectedBebida,
                    quantity: 1,
                    price: 0,
                    originalPrice: selectedBebida.price,
                    isFreeReward: true
                  });
                  setCart(newCart);
                  localStorage.setItem('ruta11_cart', JSON.stringify(newCart));
                  setAppliedReward({ type: 'combo_free', stamps: 4 });
                  setShowComboFreeModal(false);
                  alert('✅ Papas + Bebida agregados gratis!');
                }}
                disabled={!selectedBebida}
                className="w-full mt-4 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-3 rounded-lg transition-colors"
              >
                Confirmar Selección
              </button>
            </div>
          </div>
        </div>
      )}
      
      {/* Modal de Beneficios */}
      {showBenefitsModal && user && (
        <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={() => { if (!showTermsModal) setShowBenefitsModal(false); }}>
          <div className="bg-white w-full max-w-lg mx-4 rounded-2xl flex flex-col animate-slide-up max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
            <div className="p-6">
              <div className="flex justify-between items-center mb-6">
                <div className="flex items-center gap-2">
                  <Star className="text-yellow-500" size={24} />
                  <h2 className="text-xl font-bold text-gray-800">Mis Beneficios</h2>
                </div>
                <button onClick={() => setShowBenefitsModal(false)} className="p-1 text-gray-400 hover:text-gray-600">
                  <X size={24} />
                </button>
              </div>
              



              {/* Recompensas disponibles */}
              <div className="mb-6">
                <h3 className="text-lg font-semibold text-gray-800 mb-4">Saldo de Cashback</h3>
                <div className="space-y-3">
                  <div className="border-2 border-green-500 rounded-xl p-4 bg-green-50">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <Wallet className="text-green-600" size={24} />
                        <div>
                          <p className="font-semibold text-gray-800">Cashback Disponible</p>
                          <p className="text-xs text-gray-600">Ganas 1% en cada compra</p>
                        </div>
                      </div>
                      <span className="text-2xl font-bold text-green-600">${parseInt(walletData?.balance || 0).toLocaleString('es-CL')}</span>
                    </div>
                  </div>
                </div>
              </div>
              

            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default CheckoutApp;