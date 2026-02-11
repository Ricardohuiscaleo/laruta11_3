import { useEffect } from 'react';

const OrdersListener = ({ onOrdersUpdate }) => {
  useEffect(() => {
    const loadOrders = async () => {
      try {
        const response = await fetch(`/api/tuu/get_comandas_v2.php?t=${Date.now()}`);
        const data = await response.json();
        if (data.success) {
          const allOrders = data.orders || [];
          const activeCount = allOrders.filter(o => o.order_status !== 'delivered' && o.order_status !== 'cancelled').length;
          if (onOrdersUpdate) {
            onOrdersUpdate(activeCount);
          }
        }
      } catch (error) {
        console.error('Error cargando pedidos:', error);
      }
    };

    loadOrders();
    const interval = setInterval(loadOrders, 3000);
    return () => clearInterval(interval);
  }, [onOrdersUpdate]);

  return null;
};

export default OrdersListener;
