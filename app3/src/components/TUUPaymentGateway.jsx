import React, { useState, useEffect } from 'react';

const TUUPaymentGateway = ({ 
  orderData = {}, // {items, total, customer, orderNumber}
  onPaymentSuccess = () => {}, 
  onPaymentError = () => {} 
}) => {
  const { items: cartItems = [], total: totalAmount = 0, customer = {}, orderNumber = '' } = orderData;
  const [paymentState, setPaymentState] = useState('idle'); // idle, processing, waiting, success, error
  const [paymentId, setPaymentId] = useState(null);
  const [idempotencyKey, setIdempotencyKey] = useState(null);
  const [errorMessage, setErrorMessage] = useState('');
  const [statusMessage, setStatusMessage] = useState('');

  const formatPrice = (price) => {
    return new Intl.NumberFormat('es-CL', {
      style: 'currency',
      currency: 'CLP'
    }).format(price);
  };

  const createPayment = async () => {
    if (totalAmount < 100) {
      setErrorMessage('El monto mínimo es $100');
      return;
    }

    setPaymentState('processing');
    setErrorMessage('');

    const orderItems = cartItems.map(item => `${item.name} x${item.quantity}`).join(', ');

    try {
      const response = await fetch('/api/tuu_payment_gateway.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'create',
          amount: totalAmount,
          description: `Pedido #${orderNumber} - La Ruta 11`,
          customer_name: customer.name || 'Cliente',
          order_items: orderItems,
          order_number: orderNumber
        })
      });

      const data = await response.json();

      if (data.success) {
        setPaymentId(data.payment_id);
        setIdempotencyKey(data.idempotency_key);
        setPaymentState('waiting');
        setStatusMessage('Pago enviado al POS. Proceda con su tarjeta.');
        
        // Iniciar polling del estado
        startStatusPolling(data.idempotency_key);
      } else {
        setPaymentState('error');
        setErrorMessage(data.error || 'Error al procesar el pago');
        onPaymentError(data);
      }
    } catch (error) {
      setPaymentState('error');
      setErrorMessage('Error de conexión. Intente nuevamente.');
      onPaymentError({ error: error.message });
    }
  };

  const startStatusPolling = (key) => {
    const pollInterval = setInterval(async () => {
      try {
        const response = await fetch(`/api/tuu_payment_gateway.php?action=status&idempotency_key=${key}`);
        const data = await response.json();

        if (data.success) {
          const status = data.status;
          
          switch (status) {
            case 0: // Pending
              setStatusMessage('Esperando procesamiento...');
              break;
            case 1: // Sent
              setStatusMessage('Pago enviado al POS. Use su tarjeta.');
              break;
            case 2: // Canceled
              setPaymentState('error');
              setErrorMessage('Pago cancelado en el POS');
              clearInterval(pollInterval);
              onPaymentError({ status: 'canceled' });
              break;
            case 3: // Processing
              setStatusMessage('Procesando pago con el banco...');
              break;
            case 4: // Failed
              setPaymentState('error');
              setErrorMessage('Pago rechazado. Intente con otra tarjeta.');
              clearInterval(pollInterval);
              onPaymentError({ status: 'failed' });
              break;
            case 5: // Completed
              setPaymentState('success');
              setStatusMessage('¡Pago exitoso!');
              clearInterval(pollInterval);
              onPaymentSuccess({
                payment_id: paymentId,
                amount: totalAmount,
                status: 'completed'
              });
              break;
          }
        }
      } catch (error) {
        console.error('Error checking payment status:', error);
      }
    }, 3000); // Consultar cada 3 segundos

    // Limpiar después de 5 minutos
    setTimeout(() => {
      clearInterval(pollInterval);
      if (paymentState === 'waiting') {
        setPaymentState('error');
        setErrorMessage('Tiempo de espera agotado. Consulte con el personal.');
      }
    }, 300000);
  };

  const resetPayment = () => {
    setPaymentState('idle');
    setPaymentId(null);
    setIdempotencyKey(null);
    setErrorMessage('');
    setStatusMessage('');
  };

  return (
    <div className="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6">
      <div className="text-center mb-6">
        <h2 className="text-2xl font-bold text-gray-800 mb-2">Pagar Pedido</h2>
        <div className="text-lg text-gray-600 mb-2">Pedido #{orderNumber}</div>
        <div className="text-3xl font-bold text-green-600">
          {formatPrice(totalAmount)}
        </div>
      </div>

      {/* Resumen del pedido */}
      {cartItems.length > 0 && (
        <div className="mb-6 p-4 bg-gray-50 rounded-lg">
          <h3 className="font-semibold text-gray-700 mb-2">Resumen del pedido:</h3>
          {cartItems.map((item, index) => (
            <div key={index} className="flex justify-between text-sm text-gray-600">
              <span>{item.name} x{item.quantity}</span>
              <span>{formatPrice(item.price * item.quantity)}</span>
            </div>
          ))}
        </div>
      )}

      {/* Estados del pago */}
      {paymentState === 'idle' && (
        <button
          onClick={createPayment}
          disabled={totalAmount < 100}
          className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded-lg transition-colors"
        >
          Pagar en POS
        </button>
      )}

      {paymentState === 'processing' && (
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
          <p className="text-gray-600">Procesando pago...</p>
        </div>
      )}

      {paymentState === 'waiting' && (
        <div className="text-center">
          <div className="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
            <div className="flex items-center justify-center mb-2">
              <div className="animate-pulse bg-yellow-500 rounded-full h-3 w-3 mr-2"></div>
              <span className="font-semibold">Esperando pago en POS</span>
            </div>
            <p className="text-sm">{statusMessage}</p>
            <p className="text-xs mt-1">ID: {paymentId}</p>
          </div>
          <button
            onClick={resetPayment}
            className="text-gray-500 hover:text-gray-700 text-sm underline"
          >
            Cancelar
          </button>
        </div>
      )}

      {paymentState === 'success' && (
        <div className="text-center">
          <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <div className="flex items-center justify-center mb-2">
              <svg className="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
              </svg>
              <span className="font-semibold">¡Pago Exitoso!</span>
            </div>
            <p className="text-sm">{formatPrice(totalAmount)} pagado correctamente</p>
          </div>
          <button
            onClick={resetPayment}
            className="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg"
          >
            Nuevo Pago
          </button>
        </div>
      )}

      {paymentState === 'error' && (
        <div className="text-center">
          <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <div className="flex items-center justify-center mb-2">
              <svg className="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
              </svg>
              <span className="font-semibold">Error en el pago</span>
            </div>
            <p className="text-sm">{errorMessage}</p>
          </div>
          <button
            onClick={resetPayment}
            className="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg"
          >
            Intentar Nuevamente
          </button>
        </div>
      )}

      {/* Información adicional */}
      <div className="mt-6 text-xs text-gray-500 text-center">
        <p>Pago seguro procesado por TUU</p>
        <p>Aceptamos débito y crédito</p>
      </div>
    </div>
  );
};

export default TUUPaymentGateway;