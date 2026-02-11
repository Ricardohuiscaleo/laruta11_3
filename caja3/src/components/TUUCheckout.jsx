import React, { useState } from 'react';
import { CreditCard, Smartphone } from 'lucide-react';

export default function TuuCheckout({ cart, onPaymentSuccess }) {
  const [paymentMethod, setPaymentMethod] = useState('online');
  const [loading, setLoading] = useState(false);

  const handlePayment = async () => {
    setLoading(true);
    
    try {
      if (paymentMethod === 'online') {
        // TUU Pago Online (Webpay)
        const response = await fetch('/api/tuu_online_bridge.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'create_payment',
            amount: cart.total,
            order_id: `ORDER-${Date.now()}`,
            description: `Pedido La Ruta 11 - ${cart.items.length} productos`,
            return_url: `${window.location.origin}/payment/success`,
            cancel_url: `${window.location.origin}/payment/cancel`
          })
        });
        
        const result = await response.json();
        
        if (result.success && result.data.checkout_url) {
          // Redirigir a TUU Checkout (Webpay)
          window.location.href = result.data.checkout_url;
        }
      } else {
        // TUU Presencial (POS)
        const response = await fetch('/api/tuu_create_payment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            amount: cart.total,
            description: `Pedido La Ruta 11 - Presencial`
          })
        });
        
        const result = await response.json();
        onPaymentSuccess(result);
      }
    } catch (error) {
      console.error('Error en pago:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-white rounded-lg shadow-lg p-6">
      <h3 className="text-xl font-bold mb-4">Método de Pago</h3>
      
      <div className="space-y-3 mb-6">
        <label className="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
          <input
            type="radio"
            name="payment"
            value="online"
            checked={paymentMethod === 'online'}
            onChange={(e) => setPaymentMethod(e.target.value)}
            className="mr-3"
          />
          <CreditCard className="h-5 w-5 mr-2 text-blue-600" />
          <div>
            <div className="font-medium">Pago Online</div>
            <div className="text-sm text-gray-500">Webpay, tarjetas, transferencia</div>
          </div>
        </label>
        
        <label className="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
          <input
            type="radio"
            name="payment"
            value="pos"
            checked={paymentMethod === 'pos'}
            onChange={(e) => setPaymentMethod(e.target.value)}
            className="mr-3"
          />
          <Smartphone className="h-5 w-5 mr-2 text-green-600" />
          <div>
            <div className="font-medium">Pago en Local</div>
            <div className="text-sm text-gray-500">POS TUU, efectivo</div>
          </div>
        </label>
      </div>
      
      <button
        onClick={handlePayment}
        disabled={loading}
        className="w-full bg-blue-600 text-white py-3 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50"
      >
        {loading ? 'Procesando...' : `Pagar $${cart.total.toLocaleString()}`}
      </button>
    </div>
  );
}