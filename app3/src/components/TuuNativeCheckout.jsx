import React, { useState, useEffect } from 'react';
import { Smartphone, CreditCard, Receipt } from 'lucide-react';

export default function TuuNativeCheckout({ cart, onPaymentSuccess }) {
  const [loading, setLoading] = useState(false);
  const [isTuuDevice, setIsTuuDevice] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState(1); // 1=débito, 2=cualquiera, 3=crédito

  useEffect(() => {
    // Detectar si estamos en un dispositivo TUU
    const checkTuuDevice = () => {
      const userAgent = navigator.userAgent.toLowerCase();
      const isSunmi = userAgent.includes('sunmi');
      const isKozen = userAgent.includes('kozen');
      setIsTuuDevice(isSunmi || isKozen);
    };
    
    checkTuuDevice();
  }, []);

  const handleNativePayment = async () => {
    if (!isTuuDevice) {
      alert('Esta función solo está disponible en terminales TUU certificados');
      return;
    }

    setLoading(true);
    
    try {
      // Crear intent para app TUU nativa
      const response = await fetch('/api/tuu_native_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'create_intent',
          amount: Math.round(cart.total), // TUU requiere enteros
          method: paymentMethod,
          tip: 0, // Permitir propina en app TUU
          order_id: `RUTA11-${Date.now()}`,
          table: cart.table || 'Mesa 1',
          customer_name: cart.customer_name || 'Cliente',
          items: cart.items.map(item => ({
            name: item.name,
            quantity: item.quantity,
            price: item.price
          }))
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        // Llamar a la app TUU nativa usando Android Intent
        if (window.Android && window.Android.startPaymentIntent) {
          // Interfaz nativa Android
          window.Android.startPaymentIntent(
            JSON.stringify(result.intent_data),
            result.package_name,
            result.order_id
          );
        } else {
          // Fallback: crear enlace de intent
          const intentUrl = `intent://payment#Intent;` +
            `package=${result.package_name};` +
            `action=${result.action};` +
            `S.payment_data=${encodeURIComponent(JSON.stringify(result.intent_data))};` +
            `end`;
          
          window.location.href = intentUrl;
        }
      }
    } catch (error) {
      console.error('Error en pago nativo:', error);
      alert('Error al procesar el pago');
    } finally {
      setLoading(false);
    }
  };

  const handleWebPayment = async () => {
    // Fallback para dispositivos no-TUU (usar TUU Online)
    try {
      const response = await fetch('/api/tuu_online_bridge.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'create_payment',
          amount: cart.total,
          order_id: `RUTA11-WEB-${Date.now()}`,
          description: `Pedido La Ruta 11 - ${cart.items.length} productos`,
          return_url: `${window.location.origin}/payment/success`,
          cancel_url: `${window.location.origin}/payment/cancel`
        })
      });
      
      const result = await response.json();
      
      if (result.success && result.data.checkout_url) {
        window.location.href = result.data.checkout_url;
      }
    } catch (error) {
      console.error('Error en pago web:', error);
    }
  };

  return (
    <div className="bg-white rounded-lg shadow-lg p-6">
      <h3 className="text-xl font-bold mb-4">Procesar Pago</h3>
      
      {isTuuDevice ? (
        <div className="space-y-4">
          <div className="bg-green-50 border border-green-200 rounded-lg p-4">
            <div className="flex items-center gap-2 text-green-800">
              <Smartphone className="h-5 w-5" />
              <span className="font-medium">Terminal TUU Detectado</span>
            </div>
            <p className="text-sm text-green-600 mt-1">
              Pago nativo disponible con impresión automática
            </p>
          </div>
          
          <div className="space-y-3">
            <label className="block text-sm font-medium text-gray-700">
              Método de Pago
            </label>
            <div className="space-y-2">
              <label className="flex items-center">
                <input
                  type="radio"
                  name="method"
                  value={1}
                  checked={paymentMethod === 1}
                  onChange={(e) => setPaymentMethod(parseInt(e.target.value))}
                  className="mr-2"
                />
                <span>Débito</span>
              </label>
              <label className="flex items-center">
                <input
                  type="radio"
                  name="method"
                  value={2}
                  checked={paymentMethod === 2}
                  onChange={(e) => setPaymentMethod(parseInt(e.target.value))}
                  className="mr-2"
                />
                <span>Cualquier método</span>
              </label>
              <label className="flex items-center">
                <input
                  type="radio"
                  name="method"
                  value={3}
                  checked={paymentMethod === 3}
                  onChange={(e) => setPaymentMethod(parseInt(e.target.value))}
                  className="mr-2"
                />
                <span>Crédito</span>
              </label>
            </div>
          </div>
          
          <button
            onClick={handleNativePayment}
            disabled={loading}
            className="w-full bg-green-600 text-white py-3 rounded-lg font-medium hover:bg-green-700 disabled:opacity-50 flex items-center justify-center gap-2"
          >
            <Receipt className="h-5 w-5" />
            {loading ? 'Procesando...' : `Pagar $${cart.total.toLocaleString()} (Nativo)`}
          </button>
        </div>
      ) : (
        <div className="space-y-4">
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div className="flex items-center gap-2 text-blue-800">
              <CreditCard className="h-5 w-5" />
              <span className="font-medium">Pago Online</span>
            </div>
            <p className="text-sm text-blue-600 mt-1">
              Webpay, tarjetas de crédito y débito
            </p>
          </div>
          
          <button
            onClick={handleWebPayment}
            disabled={loading}
            className="w-full bg-blue-600 text-white py-3 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 flex items-center justify-center gap-2"
          >
            <CreditCard className="h-5 w-5" />
            {loading ? 'Redirigiendo...' : `Pagar $${cart.total.toLocaleString()} (Online)`}
          </button>
        </div>
      )}
      
      <div className="mt-4 text-xs text-gray-500 text-center">
        {isTuuDevice ? 'Terminal certificado TUU' : 'Dispositivo estándar - usando pago online'}
      </div>
    </div>
  );
}