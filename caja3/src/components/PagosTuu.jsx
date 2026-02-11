import { useState, useEffect } from 'react';

export default function PagosTuu() {
  const [stats, setStats] = useState({
    totalSales: 0,
    totalCost: 0,
    totalProfit: 0,
    totalOrders: 0,
    lastUpdate: '-'
  });
  const [paymentMethods, setPaymentMethods] = useState([]);
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [period, setPeriod] = useState('shift_today');
  const [shiftDate, setShiftDate] = useState('');
  const [shiftOptions, setShiftOptions] = useState([]);
  const [isPaymentMethodsExpanded, setIsPaymentMethodsExpanded] = useState(true);

  useEffect(() => {
    loadData();
    const interval = setInterval(loadData, 5000);
    return () => clearInterval(interval);
  }, [period, shiftDate]);

  useEffect(() => {
    if (period === 'shift_select') {
      generateShiftOptions();
    }
  }, [period]);

  const generateShiftOptions = () => {
    const today = new Date();
    const currentHour = today.getHours();
    let currentShiftDate = new Date(today);
    if (currentHour >= 0 && currentHour < 4) {
      currentShiftDate.setDate(currentShiftDate.getDate() - 1);
    }
    
    const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const shifts = [];
    let shiftDate = new Date(currentShiftDate);
    
    while (shiftDate >= firstDayOfMonth) {
      const dateStr = shiftDate.toISOString().split('T')[0];
      const dayName = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][shiftDate.getDay()];
      const dayNum = shiftDate.getDate();
      const monthName = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'][shiftDate.getMonth()];
      
      shifts.push({
        value: dateStr,
        label: `${dayName} ${dayNum} ${monthName} (17:30-04:00)`,
        isCurrent: dateStr === currentShiftDate.toISOString().split('T')[0]
      });
      
      shiftDate.setDate(shiftDate.getDate() - 1);
    }
    
    setShiftOptions(shifts);
    if (shifts.length > 0) {
      setShiftDate(shifts[0].value);
    }
  };

  const loadData = async () => {
    try {
      let startDate, endDate;
      const today = new Date();
      
      if (period === 'shift_today' || (period === 'shift_select' && shiftDate)) {
        const currentHour = today.getHours();
        let shiftStartDate = new Date(today);
        if (currentHour >= 0 && currentHour < 4) {
          shiftStartDate.setDate(shiftStartDate.getDate() - 1);
        }
        
        startDate = period === 'shift_select' ? shiftDate : shiftStartDate.toISOString().split('T')[0];
        const nextDay = new Date(startDate);
        nextDay.setDate(nextDay.getDate() + 1);
        endDate = nextDay.toISOString().split('T')[0];
      } else {
        endDate = today.toISOString().split('T')[0];
        switch(period) {
          case 'today':
            startDate = endDate;
            break;
          case 'week':
            const weekAgo = new Date(today);
            weekAgo.setDate(today.getDate() - 7);
            startDate = weekAgo.toISOString().split('T')[0];
            break;
          case 'month':
            const startOfMonth = new Date(today);
            startOfMonth.setDate(1);
            startDate = startOfMonth.toISOString().split('T')[0];
            break;
          case 'all':
          default:
            startDate = '2024-01-01';
            break;
        }
      }
      
      const response = await fetch(`/api/tuu/get_orders_with_inventory.php?start_date=${startDate}&end_date=${endDate}&t=${Date.now()}`);
      const data = await response.json();
      
      if (data.success && data.data.orders) {
        processData(data.data.orders);
      }
      setLoading(false);
    } catch (error) {
      console.error('Error:', error);
      setLoading(false);
    }
  };

  const processData = (ordersData) => {
    setOrders(ordersData);
    
    const methods = {
      card: { icon: '💳', label: 'Tarjetas', sales: 0, cost: 0, orders: 0 },
      transfer: { icon: '🏦', label: 'Transfer', sales: 0, cost: 0, orders: 0 },
      cash: { icon: '💵', label: 'Efectivo', sales: 0, cost: 0, orders: 0 },
      webpay: { icon: '💳', label: 'Webpay', sales: 0, cost: 0, orders: 0 },
      pedidosya: { icon: '🛵', label: 'PedidosYA', sales: 0, cost: 0, orders: 0 }
    };
    
    let totalCost = 0, totalDeliveryFee = 0, totalSales = 0;
    
    ordersData.forEach(order => {
      const method = order.payment_method || 'cash';
      const amount = parseFloat(order.total_amount || 0);
      const estimatedCost = amount * 0.35;
      const deliveryFee = parseFloat(order.delivery_fee || 0);
      
      if (methods[method]) {
        methods[method].sales += amount;
        methods[method].cost += estimatedCost;
        methods[method].orders += 1;
      }
      
      totalCost += estimatedCost;
      totalDeliveryFee += deliveryFee;
      totalSales += amount;
    });
    
    setStats({
      totalSales,
      totalCost,
      totalProfit: totalSales - totalCost,
      totalOrders: ordersData.length,
      lastUpdate: new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
    });
    
    setPaymentMethods(Object.entries(methods).map(([key, data]) => ({
      ...data,
      profit: data.sales - data.cost,
      profitPercent: data.sales > 0 ? ((data.sales - data.cost) / data.sales * 100).toFixed(1) : 0
    })));
  };

  const deleteOrder = async (orderNumber, orderId) => {
    if (!confirm(`¿Eliminar orden ${orderNumber}?`)) return;
    
    try {
      const response = await fetch('/api/tuu/delete_transaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: orderId })
      });
      const data = await response.json();
      if (data.success) {
        alert('✓ Orden eliminada');
        loadData();
      } else {
        alert('❌ Error: ' + (data.error || 'No se pudo eliminar'));
      }
    } catch (error) {
      alert('❌ Error de conexión');
    }
  };

  return (
    <div style={{minHeight: '100vh', background: '#fafafa'}}>
      {/* Header */}
      <div className="bg-white border-b px-8 py-4">
        <h1 className="text-2xl font-semibold">💳 Pagos</h1>
      </div>

      <div className="max-w-7xl mx-auto p-8">
        {/* Stats Grid */}
        <div className="grid grid-cols-5 gap-6 mb-8">
          <div className="bg-white border rounded-lg p-6">
            <div className="text-3xl font-bold text-green-600">${stats.totalSales.toLocaleString('es-CL')}</div>
            <div className="text-sm text-gray-600">💰 Ventas Total</div>
          </div>
          <div className="bg-white border rounded-lg p-6">
            <div className="text-3xl font-bold text-red-600">${stats.totalCost.toLocaleString('es-CL')}</div>
            <div className="text-sm text-gray-600">📦 Costo Total</div>
          </div>
          <div className="bg-white border rounded-lg p-6">
            <div className="text-3xl font-bold text-blue-600">${stats.totalProfit.toLocaleString('es-CL')}</div>
            <div className="text-sm text-gray-600">📈 Utilidad Total</div>
          </div>
          <div className="bg-white border rounded-lg p-6">
            <div className="text-3xl font-bold">{stats.totalOrders}</div>
            <div className="text-sm text-gray-600">🧾 Pedidos</div>
          </div>
          <div className="bg-white border rounded-lg p-6">
            <div className="text-2xl font-bold">{stats.lastUpdate}</div>
            <div className="text-sm text-gray-600">
              <span className="inline-block w-2 h-2 bg-green-500 rounded-full mr-1 animate-pulse"></span>
              Tiempo Real (5s)
            </div>
          </div>
        </div>

        {/* Payment Methods */}
        <div className="bg-white border rounded-lg mb-8">
          <button 
            onClick={() => setIsPaymentMethodsExpanded(!isPaymentMethodsExpanded)}
            className="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
          >
            <h2 className="text-lg font-semibold">💳 Desglose por Método de Pago</h2>
            <span className="text-2xl">{isPaymentMethodsExpanded ? '▼' : '▶'}</span>
          </button>
          {isPaymentMethodsExpanded && (
            <div className="px-6 pb-6">
              <div className="grid grid-cols-5 gap-4">
                {paymentMethods.map((method, i) => (
                  <div key={i} className="border-l-4 border-green-500 bg-gray-50 p-4 rounded">
                    <div className="text-2xl font-bold text-green-600">
                      {method.icon} ${method.sales.toLocaleString('es-CL')}
                    </div>
                    <div className="text-sm text-gray-600 mb-2">{method.label}</div>
                    <div className="text-xs text-gray-500">📦 Costo: ${method.cost.toLocaleString('es-CL')}</div>
                    <div className="text-xs text-blue-600">📈 Utilidad: ${method.profit.toLocaleString('es-CL')} ({method.profitPercent}%)</div>
                    <div className="text-xs text-gray-600">🧾 {method.orders} pedidos</div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Filters */}
        <div className="bg-white border rounded-lg p-6 mb-8">
          <div className="flex items-center gap-4 flex-wrap">
            <h2 className="text-lg font-semibold">Reportes de Pagos Multicanal</h2>
            <select value={period} onChange={(e) => setPeriod(e.target.value)} className="px-3 py-2 border rounded">
              <option value="shift_today">🕐 Turno Hoy (17:30-04:00)</option>
              <option value="shift_select">📅 Seleccionar Turno</option>
              <option value="today">Hoy (Calendario)</option>
              <option value="week">Esta Semana</option>
              <option value="month">Este Mes</option>
              <option value="all">Todo el Periodo</option>
            </select>
            {period === 'shift_select' && (
              <select value={shiftDate} onChange={(e) => setShiftDate(e.target.value)} className="px-3 py-2 border rounded">
                {shiftOptions.map((opt, i) => (
                  <option key={i} value={opt.value}>{opt.isCurrent ? '🕐 ' : ''}{opt.label}</option>
                ))}
              </select>
            )}
            <button onClick={loadData} className="px-4 py-2 bg-black text-white rounded hover:bg-gray-800">
              🔄 Actualizar
            </button>
          </div>
        </div>

        {/* Orders List */}
        {loading ? (
          <div className="text-center py-12 text-gray-500">Cargando reportes...</div>
        ) : (
          <div className="space-y-3">
            {orders.map((order, i) => {
              const date = new Date(order.created_at);
              const paymentIcons = { cash: '💵', card: '💳', transfer: '🏦', webpay: '💳', pedidosya: '🛵' };
              const amount = parseFloat(order.total_amount || 0);
              const deliveryFee = parseFloat(order.delivery_fee || 0);
              const netAmount = amount - deliveryFee;

              return (
                <div key={i} className="bg-white border rounded-lg shadow-sm hover:shadow-md transition-shadow">
                  <div className="bg-gray-50 px-4 py-3 border-b flex flex-wrap items-center gap-3 text-sm">
                    <span className="font-semibold">📅 {date.toLocaleDateString('es-CL')} {date.toLocaleTimeString('es-CL', {hour: '2-digit', minute: '2-digit'})}</span>
                    <span className="font-mono text-blue-600">{paymentIcons[order.payment_method] || '💳'} {order.order_number}</span>
                    <span>👤 {order.customer_name || 'Cliente'}</span>
                    {order.customer_phone && <span className="text-gray-500">📞 {order.customer_phone}</span>}
                    <span className="ml-auto font-bold text-green-600">💰 ${netAmount.toLocaleString('es-CL')}</span>
                    {deliveryFee > 0 && <span className="text-orange-500">🏍️ +${deliveryFee.toLocaleString('es-CL')}</span>}
                    <button onClick={() => deleteOrder(order.order_number, order.id)} className="px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600">
                      🗑️
                    </button>
                  </div>
                  <div className="px-4 py-3 space-y-2">
                    {order.items.map((item, j) => (
                      <div key={j} className="flex justify-between bg-gray-50 px-3 py-2 rounded">
                        <span className="font-medium text-sm">🍽️ {item.product_name} x{item.quantity}</span>
                        <span className="text-green-600 font-semibold">${parseFloat(item.subtotal).toLocaleString('es-CL')}</span>
                      </div>
                    ))}
                    
                    {/* Mostrar transacciones de inventario al final (una sola vez por orden) */}
                    {order.items[0]?.has_inventory_data && order.items[0]?.inventory_transactions?.length > 0 && (
                      <div className="mt-4 pt-4 border-t">
                        <div className="text-xs font-semibold text-gray-700 mb-2">📋 Consumo de Inventario (Orden Completa):</div>
                        <div className="space-y-1">
                          {order.items[0].inventory_transactions.map((trans, k) => {
                            const isIngredient = trans.item_type === 'ingredient';
                            const icon = isIngredient ? '🧂' : '🥤';
                            const qtyChange = Math.abs(parseFloat(trans.quantity));
                            
                            return (
                              <div key={k} className="ml-3 text-xs flex justify-between py-1 border-l-2 border-blue-300 pl-3 bg-blue-50">
                                <span className="text-gray-700 font-medium">
                                  {icon} {trans.item_name}
                                </span>
                                <span className="text-gray-600">
                                  Había: <span className="font-semibold">{parseFloat(trans.previous_stock).toFixed(isIngredient ? 1 : 0)}{trans.unit}</span> | 
                                  <span className="text-red-600 font-semibold">-{qtyChange.toFixed(isIngredient ? 1 : 0)}{trans.unit}</span> | 
                                  Quedan: <span className="font-semibold">{parseFloat(trans.new_stock).toFixed(isIngredient ? 1 : 0)}{trans.unit}</span> ✅
                                </span>
                              </div>
                            );
                          })}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
