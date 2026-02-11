import React, { useState, useEffect } from 'react';
import TUUPaymentGateway from './TUUPaymentGateway';

const OrderManagement = () => {
  const [orders, setOrders] = useState([]);
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [showPayment, setShowPayment] = useState(false);

  useEffect(() => {
    loadPendingOrders();
  }, []);

  const loadPendingOrders = async () => {
    try {
      const response = await fetch('/api/get_pending_orders.php');
      const data = await response.json();
      if (data.success) {
        setOrders(data.orders);
      }
    } catch (error) {
      console.error('Error loading orders:', error);
    }
  };

  const handlePayOrder = (order) => {
    setSelectedOrder(order);
    setShowPayment(true);
  };

  const handlePaymentSuccess = async (paymentData) => {
    // Actualizar estado del pedido
    try {
      await fetch('/api/update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          order_id: selectedOrder.id,
          status: 'paid',
          payment_data: JSON.stringify(paymentData)
        })
      });
      
      // Recargar pedidos
      loadPendingOrders();
      setShowPayment(false);
      setSelectedOrder(null);
      
      alert(`¡Pedido #${selectedOrder.order_number} pagado exitosamente!`);
    } catch (error) {
      console.error('Error updating order:', error);
    }
  };

  const formatPrice = (price) => {
    return new Intl.NumberFormat('es-CL', {
      style: 'currency',
      currency: 'CLP'
    }).format(price);
  };

  if (showPayment && selectedOrder) {
    return (
      <div className="min-h-screen bg-gray-100 py-8">
        <div className="container mx-auto px-4">
          <button
            onClick={() => setShowPayment(false)}
            className="mb-4 text-blue-600 hover:text-blue-800 flex items-center"
          >
            ← Volver a pedidos
          </button>
          
          <TUUPaymentGateway
            orderData={selectedOrder}
            onPaymentSuccess={handlePaymentSuccess}
            onPaymentError={() => setShowPayment(false)}
          />
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-100 py-8">
      <div className="container mx-auto px-4">
        <h1 className="text-3xl font-bold text-center mb-8">Gestión de Pedidos</h1>
        
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {orders.map((order) => (
            <div key={order.id} className="bg-white rounded-lg shadow-md p-6">
              <div className="flex justify-between items-start mb-4">
                <div>
                  <h3 className="text-xl font-bold">Pedido #{order.order_number}</h3>
                  <p className="text-gray-600">{order.customer.name}</p>
                  <p className="text-sm text-gray-500">{order.created_at}</p>
                </div>
                <span className={`px-2 py-1 rounded text-xs font-semibold ${
                  order.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                  order.status === 'paid' ? 'bg-green-100 text-green-800' :
                  'bg-gray-100 text-gray-800'
                }`}>
                  {order.status === 'pending' ? 'Pendiente' : 
                   order.status === 'paid' ? 'Pagado' : order.status}
                </span>
              </div>

              <div className="mb-4">
                <h4 className="font-semibold mb-2">Items:</h4>
                {order.items.map((item, index) => (
                  <div key={index} className="flex justify-between text-sm">
                    <span>{item.name} x{item.quantity}</span>
                    <span>{formatPrice(item.price * item.quantity)}</span>
                  </div>
                ))}
              </div>

              <div className="border-t pt-4">
                <div className="flex justify-between items-center mb-4">
                  <span className="text-lg font-bold">Total:</span>
                  <span className="text-lg font-bold text-green-600">
                    {formatPrice(order.total)}
                  </span>
                </div>

                {order.status === 'pending' && (
                  <button
                    onClick={() => handlePayOrder(order)}
                    className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg"
                  >
                    Procesar Pago
                  </button>
                )}

                {order.status === 'paid' && (
                  <div className="text-center text-green-600 font-semibold">
                    ✓ Pagado - Listo para entregar
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>

        {orders.length === 0 && (
          <div className="text-center py-12">
            <p className="text-gray-500 text-lg">No hay pedidos pendientes</p>
          </div>
        )}
      </div>
    </div>
  );
};

export default OrderManagement;