import React, { useState, useEffect } from 'react';
import { ArrowLeft, ShoppingCart, User, MapPin, CreditCard, Bike, Caravan } from 'lucide-react';
import { vibrate, playSuccessSound } from '../utils/effects.js';
import TUUPaymentIntegration from './TUUPaymentIntegration.jsx';
import TUUPaymentFrame from './TUUPaymentFrame.jsx';

const CheckoutApp = () => {
  const [cart, setCart] = useState([]);
  const [cartTotal, setCartTotal] = useState(0);
  const [customerInfo, setCustomerInfo] = useState({
    name: '',
    phone: '',
    email: '',
    address: '',
    deliveryType: 'pickup',
    pickupTime: '',
    customerNotes: '',
    deliveryDiscount: false,
    pickupDiscount: false,
    birthdayDiscount: false
  });
  const [user, setUser] = useState(null);
  const [step, setStep] = useState(1);
  const [paymentUrl, setPaymentUrl] = useState(null);

  const [nearbyTrucks, setNearbyTrucks] = useState([]);
  const [cartSubtotal, setCartSubtotal] = useState(0);
  const [isProcessing, setIsProcessing] = useState(false);
  const [showCashModal, setShowCashModal] = useState(false);
  const [cashAmount, setCashAmount] = useState('');
  const [cashStep, setCashStep] = useState('input');

  useEffect(() => {
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
        let itemPrice = item.price * item.quantity;
        if (item.customizations && item.customizations.length > 0) {
          itemPrice += item.customizations.reduce((sum, c) => sum + (c.price * c.quantity), 0);
        }
        return total + itemPrice;
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
        }
      })
      .catch(error => console.error('Error checking session:', error));

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

  const baseDeliveryFee = customerInfo.deliveryType === 'delivery' && nearbyTrucks.length > 0
    ? parseInt(nearbyTrucks[0].tarifa_delivery || 0)
    : 0;

  const deliveryFee = customerInfo.deliveryDiscount
    ? Math.round(baseDeliveryFee * 0.6)
    : baseDeliveryFee;

  const pickupDiscountAmount = customerInfo.deliveryType === 'pickup' && customerInfo.pickupDiscount
    ? Math.round(cartSubtotal * 0.1)
    : 0;

  const birthdayDiscountAmount = customerInfo.birthdayDiscount && cart.some(item => item.id === 9)
    ? cart.find(item => item.id === 9).price
    : 0;

  const finalTotal = cartSubtotal + deliveryFee - pickupDiscountAmount - birthdayDiscountAmount;

  useEffect(() => {
    setCartTotal(finalTotal);
  }, [finalTotal, customerInfo.deliveryType, deliveryFee, cartSubtotal, nearbyTrucks]);

  const handleTUUPayment = async () => {
    try {
      // PASO 1: Crear el pago y obtener order_id
      const paymentData = {
        amount: cartTotal,
        customer_name: customerInfo.name,
        customer_phone: customerInfo.phone,
        customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
        user_id: user?.id || null,
        cart_items: cart,
        delivery_fee: deliveryFee,
        customer_notes: customerInfo.customerNotes || null,
        delivery_type: customerInfo.deliveryType,
        delivery_address: customerInfo.address || null,
        pickup_time: customerInfo.pickupTime || null
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

        // PASO 3: Redirigir a Webpay
        window.location.href = result.payment_url;
      } else {
        alert('Error al crear el pago: ' + (result.error || 'Error desconocido'));
      }
    } catch (error) {
      console.error('Error TUU:', error);
      alert('Error al procesar el pago: ' + error.message);
    }
  };

  const handleCardPayment = async () => {
    if (isProcessing) return;

    if (!customerInfo.name) {
      alert('Por favor completa tu nombre');
      return;
    }

    if (customerInfo.deliveryType === 'delivery' && !customerInfo.address) {
      alert('Por favor ingresa la dirección de entrega');
      return;
    }

    setIsProcessing(true);
    try {
      const orderData = {
        amount: cartTotal,
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
        payment_method: 'card'
      };

      const response = await fetch('/api/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
      });

      const result = await response.json();
      if (result.success) {
        localStorage.removeItem('ruta11_cart');
        localStorage.removeItem('ruta11_cart_total');
        window.location.href = '/card-pending?order=' + result.order_id;
      } else {
        setIsProcessing(false);
        alert('Error al crear el pedido: ' + (result.error || 'Error desconocido'));
      }
    } catch (error) {
      setIsProcessing(false);
      console.error('Error card:', error);
      alert('Error al procesar el pedido: ' + error.message);
    }
  };

  const handleCashPayment = () => {
    if (!customerInfo.name) {
      alert('Por favor completa tu nombre');
      return;
    }
    setShowCashModal(true);
    setCashAmount('');
    setCashStep('input');
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
    setCashAmount(cartTotal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
  };

  const setQuickAmount = (amount) => {
    setCashAmount(amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
  };

  const handleContinueCash = () => {
    const numericAmount = parseInt(cashAmount.replace(/\./g, ''));

    if (!numericAmount || numericAmount === 0) {
      alert('⚠️ Debe ingresar un monto o seleccionar "Monto Exacto"');
      return;
    }

    if (numericAmount < cartTotal) {
      const faltante = cartTotal - numericAmount;
      alert(`⚠️ Monto insuficiente. Faltan $${faltante.toLocaleString('es-CL')}`);
      return;
    }

    if (numericAmount === cartTotal) {
      processCashOrder();
    } else {
      setCashStep('confirm');
    }
  };

  const processCashOrder = async () => {
    setIsProcessing(true);
    try {
      const orderData = {
        amount: cartTotal,
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
        payment_method: 'cash'
      };

      const response = await fetch('/api/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
      });

      const result = await response.json();
      if (result.success) {
        vibrate(50);
        playSuccessSound();
        localStorage.removeItem('ruta11_cart');
        localStorage.removeItem('ruta11_cart_total');
        window.location.href = '/cash-pending?order=' + result.order_id;
      } else {
        setIsProcessing(false);
        alert('Error al crear el pedido: ' + (result.error || 'Error desconocido'));
      }
    } catch (error) {
      setIsProcessing(false);
      console.error('Error cash:', error);
      alert('Error al procesar el pedido: ' + error.message);
    }
  };

  const closeCashModal = () => {
    setShowCashModal(false);
    setCashAmount('');
    setCashStep('input');
  };

  const handlePedidosYAPayment = async () => {
    if (isProcessing) return;

    if (!customerInfo.name) {
      alert('Por favor completa tu nombre');
      return;
    }

    setIsProcessing(true);
    try {
      const orderData = {
        amount: cartTotal,
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
        payment_method: 'pedidosya'
      };

      const response = await fetch('/api/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
      });

      const result = await response.json();
      if (result.success) {
        localStorage.removeItem('ruta11_cart');
        localStorage.removeItem('ruta11_cart_total');
        window.location.href = '/pedidosya-pending?order=' + result.order_id;
      } else {
        setIsProcessing(false);
        alert('Error al crear el pedido: ' + (result.error || 'Error desconocido'));
      }
    } catch (error) {
      setIsProcessing(false);
      console.error('Error pedidosya:', error);
      alert('Error al procesar el pedido: ' + error.message);
    }
  };

  const handleTransferPayment = async () => {
    if (isProcessing) return;

    if (!customerInfo.name) {
      alert('Por favor completa tu nombre');
      return;
    }

    if (customerInfo.deliveryType === 'delivery' && !customerInfo.address) {
      alert('Por favor ingresa la dirección de entrega');
      return;
    }

    setIsProcessing(true);
    try {
      const orderData = {
        amount: cartTotal,
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
        payment_method: 'transfer'
      };

      const response = await fetch('/api/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
      });

      const result = await response.json();
      if (result.success) {
        // Construir mensaje de WhatsApp usando formato 2026
        let whatsappMessage = `> 🏦 *PEDIDO PENDIENTE - LA RUTA 11*\n\n`;
        whatsappMessage += `*📋 Datos del pedido:*\n`;
        whatsappMessage += `- *Pedido:* ${result.order_id}\n`;
        whatsappMessage += `- *Cliente:* ${customerInfo.name}\n`;
        whatsappMessage += `- *Estado:* Pendiente de transferencia\n`;
        whatsappMessage += `- *Método:* Transferencia bancaria\n\n`;

        // Agregar productos
        if (cart.length > 0) {
          whatsappMessage += `*📦 Productos:*\n`;
          cart.forEach((item, index) => {
            const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
            let itemTotal = item.price * item.quantity;

            if (item.customizations && item.customizations.length > 0) {
              itemTotal += item.customizations.reduce((sum, c) => sum + (c.price * c.quantity), 0);
            }

            whatsappMessage += `${index + 1}. ${item.name} x${item.quantity} - $${itemTotal.toLocaleString('es-CL')}\n`;

            // Mostrar personalizaciones
            if (item.customizations && item.customizations.length > 0) {
              item.customizations.forEach(custom => {
                whatsappMessage += `   - ${custom.quantity}x ${custom.name} (+$${(custom.price * custom.quantity).toLocaleString('es-CL')})\n`;
              });
            }

            // Mostrar productos incluidos en el combo
            if (isCombo && (item.fixed_items || item.selections)) {
              // Productos fijos del combo
              if (item.fixed_items) {
                item.fixed_items.forEach(fixedItem => {
                  whatsappMessage += `   - ${item.quantity}x ${fixedItem.product_name || fixedItem.name}\n`;
                });
              }

              // Selecciones del combo
              if (item.selections) {
                Object.values(item.selections).forEach(selection => {
                  if (Array.isArray(selection)) {
                    selection.forEach(sel => {
                      whatsappMessage += `   - ${item.quantity}x ${sel.name}\n`;
                    });
                  } else {
                    whatsappMessage += `   - ${item.quantity}x ${selection.name}\n`;
                  }
                });
              }
            }
          });
          whatsappMessage += `\n`;
        }

        // Agregar información de entrega
        whatsappMessage += `*🚚 Entrega:*\n`;
        whatsappMessage += `- *Tipo:* ${customerInfo.deliveryType === 'delivery' ? '🚴 Delivery' : '🏪 Retiro en local'}\n`;

        if (customerInfo.deliveryType === 'delivery' && customerInfo.address) {
          whatsappMessage += `- *Dirección:* ${customerInfo.address}\n`;
        }

        if (customerInfo.deliveryType === 'pickup' && customerInfo.pickupTime) {
          whatsappMessage += `- *Horario retiro:* ${customerInfo.pickupTime}\n`;
        }

        if (deliveryFee > 0) {
          whatsappMessage += `- *Costo delivery:* $${deliveryFee.toLocaleString('es-CL')}\n`;
        }
        whatsappMessage += `\n`;

        // Agregar notas del cliente
        if (customerInfo.customerNotes && customerInfo.customerNotes.trim()) {
          whatsappMessage += `*📝 Notas del cliente:*\n> ${customerInfo.customerNotes.trim()}\n\n`;
        }

        whatsappMessage += `> *💰 TOTAL: $${cartTotal.toLocaleString('es-CL')}*\n\n`;
        whatsappMessage += `_Pago con Transferencia - Pedido desde la app web._\n`;
        whatsappMessage += `_Por favor confirmar recepción del comprobante._`;

        const whatsappUrl = `https://wa.me/56936227422?text=${encodeURIComponent(whatsappMessage)}`;

        // Limpiar carrito y redirigir
        localStorage.removeItem('ruta11_cart');
        localStorage.removeItem('ruta11_cart_total');

        window.open(whatsappUrl, '_blank');
        window.location.href = '/transfer-pending?order=' + result.order_id;
      } else {
        setIsProcessing(false);
        alert('Error al crear el pedido: ' + (result.error || 'Error desconocido'));
      }
    } catch (error) {
      setIsProcessing(false);
      console.error('Error transfer:', error);
      alert('Error al procesar el pedido: ' + error.message);
    }
  };

  const goBack = () => {
    window.location.href = '/';
  };

  const proceedToPayment = () => {
    if (!customerInfo.name) {
      alert('Por favor completa tu nombre');
      return;
    }
    handleTUUPayment();
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white shadow-sm border-b" style={{ paddingTop: 'env(safe-area-inset-top, 0px)' }}>
        <div className="max-w-4xl mx-auto px-4 py-4">
          <div className="flex items-center gap-4">
            <button
              onClick={goBack}
              className="p-2 hover:bg-gray-100 rounded-full transition-colors"
            >
              <ArrowLeft size={24} className="text-gray-600" />
            </button>
            <div className="flex items-center gap-3">
              <img
                src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png"
                alt="La Ruta 11"
                className="w-8 h-8"
              />
              <div>
                <h1 className="text-xl font-bold text-gray-800">
                  Finalizar Pedido
                </h1>
                <p className="text-sm text-gray-600">La Ruta 11</p>
              </div>
            </div>
          </div>
        </div>
      </header>

      <div className="max-w-4xl mx-auto px-4 py-6">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2">
            <div className="bg-white rounded-lg shadow-sm p-6">
              <div className="mb-6">
                <h3 className="text-lg font-semibold text-gray-800 mb-3">Tipo de Entrega</h3>
                <div className="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    onClick={() => setCustomerInfo({ ...customerInfo, deliveryType: 'delivery' })}
                    className={`p-3 border-2 rounded-lg text-center transition-colors ${customerInfo.deliveryType === 'delivery'
                      ? 'border-orange-500 bg-orange-50 text-orange-700'
                      : 'border-gray-300 hover:border-gray-400'
                      }`}
                  >
                    <div className="flex justify-center mb-2">
                      <Bike size={32} className="text-red-500" />
                    </div>
                    <div className="font-semibold">Delivery</div>
                    <div className="text-xs text-gray-500">Entrega a domicilio</div>
                    {nearbyTrucks.length > 0 && nearbyTrucks[0].tarifa_delivery && (
                      <div className="text-xs text-blue-600 mt-1">
                        +${parseInt(nearbyTrucks[0].tarifa_delivery).toLocaleString('es-CL')}
                      </div>
                    )}
                  </button>
                  <button
                    type="button"
                    onClick={() => setCustomerInfo({ ...customerInfo, deliveryType: 'pickup' })}
                    className={`p-3 border-2 rounded-lg text-center transition-colors ${customerInfo.deliveryType === 'pickup'
                      ? 'border-orange-500 bg-orange-50 text-orange-700'
                      : 'border-gray-300 hover:border-gray-400'
                      }`}
                  >
                    <div className="flex justify-center mb-2">
                      <Caravan size={32} className="text-red-500" />
                    </div>
                    <div className="font-semibold">Compra Local</div>
                    <div className="text-xs text-gray-500">En Foodtruck</div>
                  </button>
                </div>
              </div>
              <div className="flex items-center gap-3 mb-4">
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
                    onChange={(e) => setCustomerInfo({ ...customerInfo, name: e.target.value })}
                    className={`w-full px-3 py-2 border rounded-md focus:outline-none ${user ? 'border-gray-200 bg-gray-50 text-gray-600 cursor-not-allowed' : 'border-gray-300 focus:ring-2 focus:ring-orange-500'
                      }`}
                    placeholder="Tu nombre completo"
                    readOnly={!!user}
                    required
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Teléfono
                  </label>
                  <input
                    type="tel"
                    value={customerInfo.phone}
                    onChange={(e) => setCustomerInfo({ ...customerInfo, phone: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                    placeholder="+56 9 1234 5678"
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
                        <span className="text-sm font-medium text-gray-700">Descuento Delivery (40%)</span>
                      </label>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Dirección de entrega *
                      </label>
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
                        <input
                          type="text"
                          value={customerInfo.address}
                          onChange={(e) => setCustomerInfo({ ...customerInfo, address: e.target.value })}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
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
                          onChange={(e) => setCustomerInfo({ ...customerInfo, pickupDiscount: e.target.checked })}
                          className="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500"
                        />
                        <span className="text-sm font-medium text-gray-700">Descuento R11 (10% en total)</span>
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
                    <span className="text-sm font-medium text-gray-700">🎂 Descuento Cumpleaños (Hamburguesa gratis)</span>
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

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Notas adicionales (opcional)
                  </label>
                  <textarea
                    value={customerInfo.customerNotes}
                    onChange={(e) => setCustomerInfo({ ...customerInfo, customerNotes: e.target.value })}
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
            <div className="bg-white rounded-lg shadow-sm p-6 sticky top-6">
              <div className="flex items-center gap-3 mb-4">
                <ShoppingCart className="text-orange-500" size={20} />
                <h3 className="text-lg font-semibold text-gray-800">Tu Pedido</h3>
              </div>

              <div className="space-y-3 mb-4">
                {cart.map((item, index) => {
                  const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
                  let itemTotal = item.price * item.quantity;

                  if (item.customizations && item.customizations.length > 0) {
                    itemTotal += item.customizations.reduce((sum, c) => sum + (c.price * c.quantity), 0);
                  }

                  return (
                    <div key={index} className="border-b border-gray-100 pb-3 last:border-b-0">
                      <div className="flex justify-between items-start">
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
                                    <span key={`${group}-${selIdx}`} className="text-blue-600">{item.quantity}x {sel.name}{selIdx < selection.length - 1 ? ', ' : (idx < Object.keys(item.selections).length - 1 ? ', ' : '')}</span>
                                  ));
                                } else {
                                  return (
                                    <span key={group} className="text-blue-600">{item.quantity}x {selection.name}{idx < Object.keys(item.selections).length - 1 ? ', ' : ''}</span>
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
                    </div>
                  );
                })}
              </div>

              <div className="border-t pt-4">
                <div className="space-y-2">
                  <div className="flex justify-between items-center">
                    <span className="text-gray-600">Subtotal:</span>
                    <span className="font-semibold">${cartSubtotal.toLocaleString('es-CL')}</span>
                  </div>
                  {baseDeliveryFee > 0 && (
                    <>
                      <div className="flex justify-between items-center">
                        <span className="text-gray-600 flex items-center gap-1">
                          <Bike size={16} className="text-red-500" /> Delivery:
                        </span>
                        <span className={customerInfo.deliveryDiscount ? "font-semibold line-through text-gray-400" : "font-semibold"}>
                          ${baseDeliveryFee.toLocaleString('es-CL')}
                        </span>
                      </div>
                      {customerInfo.deliveryDiscount && (
                        <div className="flex justify-between items-center">
                          <span className="text-green-600 text-sm">Descuento Delivery (40%):</span>
                          <span className="font-semibold text-green-600">${deliveryFee.toLocaleString('es-CL')}</span>
                        </div>
                      )}
                    </>
                  )}
                  {pickupDiscountAmount > 0 && (
                    <div className="flex justify-between items-center">
                      <span className="text-green-600 text-sm">Descuento R11 (10%):</span>
                      <span className="font-semibold text-green-600">-${pickupDiscountAmount.toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  {birthdayDiscountAmount > 0 && (
                    <div className="flex justify-between items-center">
                      <span className="text-green-600 text-sm">🎂 Descuento Cumpleaños:</span>
                      <span className="font-semibold text-green-600">-${birthdayDiscountAmount.toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  <div className="flex justify-between items-center text-lg font-bold border-t pt-2">
                    <span>Total:</span>
                    <span className="text-orange-500">${cartTotal.toLocaleString('es-CL')}</span>
                  </div>
                </div>
              </div>

              <div className="mt-6">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">Método de Pago</h4>
                <div className="grid grid-cols-4 gap-2">
                  <button
                    onClick={handleCashPayment}
                    disabled={isProcessing}
                    className="bg-white hover:bg-gray-50 disabled:bg-gray-100 border-2 border-gray-300 hover:border-green-500 disabled:cursor-not-allowed text-gray-700 font-medium py-2 px-1 rounded-lg transition-all text-xs"
                  >
                    Efectivo
                  </button>

                  <button
                    onClick={handleCardPayment}
                    disabled={isProcessing}
                    className="bg-white hover:bg-gray-50 disabled:bg-gray-100 border-2 border-gray-300 hover:border-purple-500 disabled:cursor-not-allowed text-gray-700 font-medium py-2 px-1 rounded-lg transition-all text-xs"
                  >
                    Tarjeta
                  </button>

                  <button
                    onClick={handleTransferPayment}
                    disabled={isProcessing}
                    className="bg-white hover:bg-gray-50 disabled:bg-gray-100 border-2 border-gray-300 hover:border-blue-500 disabled:cursor-not-allowed text-gray-700 font-medium py-2 px-1 rounded-lg transition-all text-xs"
                  >
                    Transfer.
                  </button>

                  <button
                    onClick={handlePedidosYAPayment}
                    disabled={isProcessing}
                    className="bg-white hover:bg-gray-50 disabled:bg-gray-100 border-2 border-gray-300 hover:border-orange-500 disabled:cursor-not-allowed text-gray-700 font-medium py-2 px-1 rounded-lg transition-all text-xs"
                  >
                    PedidosYA
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {showCashModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            {cashStep === 'input' ? (
              <>
                <h3 className="text-xl font-bold text-gray-800 mb-4">💵 Pago en Efectivo</h3>

                <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                  <p className="text-sm text-gray-600 mb-1">Total a pagar:</p>
                  <p className="text-3xl font-bold text-orange-600">${cartTotal.toLocaleString('es-CL')}</p>
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
                    <span className="text-lg font-semibold">${cartTotal.toLocaleString('es-CL')}</span>
                  </div>
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-sm text-gray-600">Paga con:</span>
                    <span className="text-lg font-semibold">${parseInt(cashAmount.replace(/\./g, '')).toLocaleString('es-CL')}</span>
                  </div>
                  <div className="border-t border-green-300 pt-2 mt-2">
                    <div className="flex justify-between items-center">
                      <span className="text-base font-semibold text-gray-700">Vuelto a entregar:</span>
                      <span className="text-2xl font-bold text-green-600">
                        ${(parseInt(cashAmount.replace(/\./g, '')) - cartTotal).toLocaleString('es-CL')}
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
    </div>
  );
};

export default CheckoutApp;