import React, { useState } from 'react';

const TUUPaymentIntegration = ({ cartItems, total, customerInfo, onPaymentSuccess, deliveryFee = 0 }) => {
  const [isProcessing, setIsProcessing] = useState(false);
  const [paymentUrl, setPaymentUrl] = useState(null);

  const initiateTUUPayment = async () => {
    setIsProcessing(true);
    
    try {
      const paymentData = {
        amount: total,
        customer_name: customerInfo.name,
        customer_phone: customerInfo.phone,
        customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
        user_id: null, // Se puede agregar si hay usuario logueado
        cart_items: cartItems.map(item => ({
          id: item.id,
          name: item.name,
          price: item.price,
          quantity: item.quantity,
          type: item.type,
          fixed_items: item.fixed_items,
          selections: item.selections,
          customizations: item.customizations,
          combo_id: item.combo_id,
          category_name: item.category_name
        })),
        delivery_fee: deliveryFee
      };

      const response = await fetch('/api/tuu/create_payment_direct.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(paymentData)
      });

      const result = await response.json();
      
      if (result.success && result.payment_url) {
        setPaymentUrl(result.payment_url);
        // Redirigir a TUU
        window.location.href = result.payment_url;
      } else {
        throw new Error(result.error || 'Error al crear el pago');
      }
    } catch (error) {
      console.error('Error TUU:', error);
      alert('Error al procesar el pago: ' + error.message);
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <div className="tuu-payment-container">
      <div className="payment-summary bg-white p-6 rounded-lg shadow-lg">
        <h3 className="text-xl font-bold mb-4">Resumen del Pedido</h3>
        
        <div className="order-items mb-4">
          {cartItems.map((item, index) => (
            <div key={index} className="flex justify-between py-2 border-b">
              <span>{item.name} x{item.quantity}</span>
              <span>${(item.price * item.quantity).toLocaleString()}</span>
            </div>
          ))}
        </div>
        
        <div className="pricing-breakdown mb-4">
          <div className="flex justify-between py-1">
            <span>Subtotal productos:</span>
            <span>${(total - deliveryFee).toLocaleString()}</span>
          </div>
          {deliveryFee > 0 && (
            <div className="flex justify-between py-1 text-blue-600">
              <span>🚚 Delivery:</span>
              <span>${deliveryFee.toLocaleString()}</span>
            </div>
          )}
        </div>
        
        <div className="total-amount text-xl font-bold border-t pt-4">
          <div className="flex justify-between">
            <span>Total:</span>
            <span>${total.toLocaleString()}</span>
          </div>
        </div>

        <div className="customer-info mt-4 p-4 bg-gray-50 rounded">
          <h4 className="font-semibold mb-2">Datos del Cliente:</h4>
          <p><strong>Nombre:</strong> {customerInfo.name}</p>
          <p><strong>Teléfono:</strong> {customerInfo.phone}</p>
          {customerInfo.table && <p><strong>Mesa:</strong> {customerInfo.table}</p>}
        </div>

        <button
          onClick={initiateTUUPayment}
          disabled={isProcessing}
          className="w-full mt-6 bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-lg transition-colors disabled:opacity-50"
        >
          {isProcessing ? (
            <div className="flex items-center justify-center">
              <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
              Procesando...
            </div>
          ) : (
            <>
              <div className="flex items-center justify-center">
                <img 
                  src="/tuu-logo.png" 
                  alt="TUU" 
                  className="h-6 mr-2"
                  onError={(e) => e.target.style.display = 'none'}
                />
                Pagar con TUU
              </div>
            </>
          )}
        </button>

        <div className="payment-methods mt-4 text-center">
          <div className="p-3 bg-green-50 rounded-lg">
            <p className="text-xs text-green-700">
              🔒 Pago 100% seguro SSL con TUU Webpay
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TUUPaymentIntegration;