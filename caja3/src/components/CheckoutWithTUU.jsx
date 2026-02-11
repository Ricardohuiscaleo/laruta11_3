import React, { useState } from 'react';
import TUUPaymentGateway from './TUUPaymentGateway';

const CheckoutWithTUU = ({ cartItems, onOrderComplete }) => {
  const [showPayment, setShowPayment] = useState(false);
  const [customerInfo, setCustomerInfo] = useState({
    name: '',
    phone: '',
    email: ''
  });

  const totalAmount = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);

  const handlePaymentSuccess = (paymentData) => {
    // Limpiar carrito y mostrar confirmación
    onOrderComplete({
      ...paymentData,
      customer: customerInfo,
      items: cartItems,
      total: totalAmount
    });
  };

  const handlePaymentError = (error) => {
    console.error('Payment error:', error);
    // Mostrar mensaje de error al usuario
  };

  if (showPayment) {
    return (
      <div className="min-h-screen bg-gray-100 py-8">
        <div className="container mx-auto px-4">
          <button
            onClick={() => setShowPayment(false)}
            className="mb-4 text-blue-600 hover:text-blue-800 flex items-center"
          >
            ← Volver al carrito
          </button>
          
          <TUUPaymentGateway
            cartItems={cartItems}
            totalAmount={totalAmount}
            customerName={customerInfo.name}
            onPaymentSuccess={handlePaymentSuccess}
            onPaymentError={handlePaymentError}
          />
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-100 py-8">
      <div className="container mx-auto px-4 max-w-2xl">
        <h1 className="text-3xl font-bold text-center mb-8">Finalizar Pedido</h1>
        
        {/* Resumen del carrito */}
        <div className="bg-white rounded-lg shadow-md p-6 mb-6">
          <h2 className="text-xl font-semibold mb-4">Tu Pedido</h2>
          {cartItems.map((item, index) => (
            <div key={index} className="flex justify-between items-center py-2 border-b">
              <div>
                <span className="font-medium">{item.name}</span>
                <span className="text-gray-500 ml-2">x{item.quantity}</span>
              </div>
              <span className="font-semibold">
                ${(item.price * item.quantity).toLocaleString('es-CL')}
              </span>
            </div>
          ))}
          <div className="flex justify-between items-center pt-4 text-xl font-bold">
            <span>Total:</span>
            <span className="text-green-600">
              ${totalAmount.toLocaleString('es-CL')}
            </span>
          </div>
        </div>

        {/* Información del cliente */}
        <div className="bg-white rounded-lg shadow-md p-6 mb-6">
          <h2 className="text-xl font-semibold mb-4">Información del Cliente</h2>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Nombre *
              </label>
              <input
                type="text"
                value={customerInfo.name}
                onChange={(e) => setCustomerInfo({...customerInfo, name: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Tu nombre completo"
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
                onChange={(e) => setCustomerInfo({...customerInfo, phone: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="+56 9 1234 5678"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Email
              </label>
              <input
                type="email"
                value={customerInfo.email}
                onChange={(e) => setCustomerInfo({...customerInfo, email: e.target.value})}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="tu@email.com"
              />
            </div>
          </div>
        </div>

        {/* Botón de pago */}
        <button
          onClick={() => setShowPayment(true)}
          disabled={!customerInfo.name || cartItems.length === 0}
          className="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-4 px-6 rounded-lg text-lg transition-colors"
        >
          Pagar ${totalAmount.toLocaleString('es-CL')} en POS
        </button>

        <div className="mt-4 text-center text-sm text-gray-600">
          <p>✓ Pago seguro con tarjeta de débito o crédito</p>
          <p>✓ Procesado por TUU - Sistema certificado</p>
        </div>
      </div>
    </div>
  );
};

export default CheckoutWithTUU;