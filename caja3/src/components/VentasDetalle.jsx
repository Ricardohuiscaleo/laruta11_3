import { useState, useEffect } from 'react';
import { ArrowLeft, BarChart3, Search, Banknote, CreditCard, Smartphone, Truck, Clock, User, Phone, MessageCircle, MapPin, ShoppingCart, FlaskConical, Store, Sparkles, Tag, Gift, MessageSquare, Edit, Trash2, ChevronDown, ChevronRight, ArrowUp, ArrowDown, Bike, Home } from 'lucide-react';

// Componente minimalista para ingredientes colapsables
function IngredientToggle({ ingredients }) {
  const [isOpen, setIsOpen] = useState(false);
  
  return (
    <div className="mt-1">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1"
      >
        {isOpen ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
        <span>Ingredientes ({ingredients.length})</span>
      </button>
      {isOpen && (
        <div className="ml-4 mt-1 text-xs text-gray-500">
          {ingredients.map((ing, idx) => {
            const qty = parseFloat(ing.quantity_needed || 0);
            let unit = ing.unit || '';
            if (unit === 'unidad') unit = 'U';
            if (unit === 'unit') unit = 'U';
            const displayQty = unit === 'g' && qty >= 1000
              ? `${(qty / 1000).toFixed(2)} kg`
              : `${Math.round(qty)} ${unit}`;
            return (
              <div key={idx} className="flex justify-between">
                <span>• {ing.ingredient_name}</span>
                <span className="font-semibold text-orange-600">{displayQty}</span>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

export default function VentasDetalle() {
  const [allOrders, setAllOrders] = useState([]);
  const [filteredOrders, setFilteredOrders] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [currentFilter, setCurrentFilter] = useState('all');
  const [showIngredients, setShowIngredients] = useState(false);
  const [period, setPeriod] = useState('');

  useEffect(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const startDate = urlParams.get('start');
    const endDate = urlParams.get('end');
    
    if (startDate && endDate) {
      loadDetail(startDate, endDate);
    }
  }, []);

  const loadDetail = async (startDate, endDate) => {
    try {
      const url = `/api/get_sales_detail.php?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&t=${Date.now()}`;
      const response = await fetch(url);
      const data = await response.json();
      
      if (data.success) {
        const uniqueOrders = {};
        (data.orders || []).forEach(o => {
          if (!uniqueOrders[o.order_number]) {
            uniqueOrders[o.order_number] = o;
          }
        });
        const orders = Object.values(uniqueOrders);
        setAllOrders(orders);
        setFilteredOrders(orders);
        setStats(data.stats);
        const totalSales = data.stats ? data.stats.total_sales : 0;
        setPeriod(`${data.total_orders || 0} pedidos`);
      }
      setLoading(false);
    } catch (error) {
      console.error('Error:', error);
      setLoading(false);
    }
  };

  const filterByPayment = (method) => {
    setCurrentFilter(method);
    if (method === 'all') {
      setFilteredOrders(allOrders);
    } else {
      setFilteredOrders(allOrders.filter(o => o.payment_method === method));
    }
  };

  const applySearch = (term) => {
    setSearchTerm(term);
    let orders = currentFilter === 'all' ? allOrders : allOrders.filter(o => o.payment_method === currentFilter);
    if (term) {
      orders = orders.filter(o =>
        o.customer_name.toLowerCase().includes(term.toLowerCase()) ||
        (o.customer_phone && o.customer_phone.includes(term)) ||
        o.order_number.toLowerCase().includes(term.toLowerCase())
      );
    }
    setFilteredOrders(orders);
  };

  const getMethodBadge = (method) => {
    const badges = {
      'cash': { icon: Banknote, label: 'Efectivo', color: 'bg-green-100 text-green-800' },
      'card': { icon: CreditCard, label: 'Tarjeta', color: 'bg-purple-100 text-purple-800' },
      'transfer': { icon: Smartphone, label: 'Transfer', color: 'bg-blue-100 text-blue-800' },
      'webpay': { icon: CreditCard, label: 'Webpay', color: 'bg-yellow-100 text-yellow-800' },
      'pedidosya': { icon: Truck, label: 'PedidosYA', color: 'bg-orange-100 text-orange-800' },
      'rl6_credit': { icon: CreditCard, label: 'Crédito RL6', color: 'bg-emerald-100 text-emerald-800' }
    };
    const badge = badges[method] || { icon: CreditCard, label: method, color: 'bg-gray-100 text-gray-800' };
    const Icon = badge.icon;
    return (
      <span className={`px-2 py-0.5 rounded-full text-xs font-semibold flex items-center gap-1 ${badge.color}`}>
        <Icon size={10} />
        {badge.label}
      </span>
    );
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500"></div>
          <p className="mt-4 text-gray-600">Cargando...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-black shadow-lg sticky top-0 z-10">
        <div className="max-w-6xl mx-auto p-3">
          <div className="flex items-center gap-3">
            <button onClick={() => window.location.href = '/arqueo'} className="text-yellow-400 hover:text-yellow-300 transition-colors">
              <ArrowLeft size={32} />
            </button>
            <div className="flex-1 flex items-center gap-2 text-xs flex-wrap">
              <span className="font-bold text-white text-sm">Detalle de Ventas</span>
              <span className="text-gray-500">|</span>
              <span className="text-gray-300">{period}</span>
              {stats && (
                <>
                  <span className="text-gray-500">|</span>
                  <span className="text-green-400 font-bold">${Math.round(stats.total_sales || 0).toLocaleString('es-CL')}</span>
                  <span className="text-gray-500">|</span>
                  <ArrowDown size={12} className="text-red-500" />
                  <span className="text-orange-400 font-semibold">${Math.round(stats.total_discounts || 0).toLocaleString('es-CL')}</span>
                  <span className="text-gray-500">|</span>
                  <Bike size={12} className="text-blue-400" />
                  <span className="text-blue-400 font-semibold">{(stats.delivery_types && stats.delivery_types.delivery) || 0}</span>
                  <span className="text-gray-500">|</span>
                  <Home size={12} className="text-green-400" />
                  <span className="text-green-400 font-semibold">{(stats.delivery_types && stats.delivery_types.pickup) || 0}</span>
                </>
              )}
            </div>
          </div>
          {/* Filtros en fila 2 */}
          <div className="flex gap-3 mt-2 text-xs overflow-x-auto pb-1">
            {[
              { key: 'all', icon: null, label: 'Todos' },
              { key: 'cash', icon: Banknote, label: 'Efectivo' },
              { key: 'card', icon: CreditCard, label: 'Tarjeta' },
              { key: 'transfer', icon: Smartphone, label: 'Transfer' },
              { key: 'pedidosya', icon: Truck, label: 'PedidosYA' },
              { key: 'rl6_credit', icon: CreditCard, label: 'RL6' }
            ].map(filter => {
              const Icon = filter.icon;
              const isActive = currentFilter === filter.key;
              return (
                <button
                  key={filter.key}
                  onClick={() => filterByPayment(filter.key)}
                  className={`flex items-center gap-1 whitespace-nowrap ${isActive ? 'text-orange-400' : 'text-gray-400 hover:text-gray-300'}`}
                >
                  {Icon ? <Icon size={14} /> : <span className="font-bold">|</span>}
                  <span>{filter.label}</span>
                  <span className="font-semibold">{isActive ? filteredOrders.length : ''}</span>
                </button>
              );
            })}
          </div>
        </div>
      </div>

      <div className="max-w-6xl mx-auto px-1 py-3 pb-20">
        {/* Ingredientes */}
        {stats?.ingredient_consumption?.length > 0 && (
          <div className="bg-white rounded-lg mb-3">
            <button onClick={() => setShowIngredients(!showIngredients)} className="w-full px-3 py-2 flex justify-between items-center text-left hover:bg-gray-50">
              <span className="text-sm text-gray-700">Consumo de Ingredientes</span>
              <span className="text-gray-400 text-sm">{showIngredients ? '▼' : '▶'}</span>
            </button>
            {showIngredients && (
              <div className="px-3 pb-3">
                <div className="grid grid-cols-2 gap-1.5 text-xs">
                  {stats.ingredient_consumption.map((ing, i) => {
                    const qty = parseFloat(ing.total || 0);
                    let unit = ing.unit || 'g';
                    if (unit === 'unidad') unit = 'U';
                    if (unit === 'unit') unit = 'U';
                    const displayQty = unit === 'g' && qty >= 1000 ? `${(qty / 1000).toFixed(2)} kg` : `${Math.round(qty)} ${unit}`;
                    return (
                      <div key={i} className="flex justify-between items-center py-1">
                        <span className="text-gray-700">{ing.name}</span>
                        <span className="font-semibold text-orange-600 whitespace-nowrap">{displayQty}</span>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}
          </div>
        )}

        {/* Búsqueda */}
        <div className="bg-white rounded-lg p-2 mb-3">
          <div className="relative">
            <Search className="absolute left-3 top-2.5 text-gray-400" size={16} />
            <input
              type="text"
              placeholder="Buscar cliente, teléfono, pedido..."
              className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent"
              value={searchTerm}
              onChange={(e) => applySearch(e.target.value)}
            />
          </div>
        </div>

        {/* Órdenes */}
        <div className="space-y-3">
          {filteredOrders.length === 0 ? (
            <div className="bg-white rounded-xl shadow-md p-12 text-center">
              <p className="text-gray-500 text-lg">No hay ventas</p>
            </div>
          ) : (
            filteredOrders.map(order => (
              <div key={order.id} className="bg-white rounded-xl overflow-hidden border border-gray-400">
                {/* Header */}
                <div className="bg-gradient-to-r from-gray-50 to-gray-100 px-3 py-2 border-b border-gray-200">
                  <div className="flex items-center justify-between text-xs gap-2">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="text-gray-500 flex items-center gap-1">
                        <Clock size={10} />
                        {order.hora_chile}
                      </span>
                      <span className="font-mono font-bold text-gray-700">{order.order_number}</span>
                      <span className={`px-2 py-0.5 rounded-full font-semibold flex items-center gap-1 ${order.delivery_type === 'delivery' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}`}>
                        {order.delivery_type === 'delivery' ? <Truck size={12} /> : <Store size={12} />}
                        {order.delivery_type === 'delivery' ? 'Delivery' : 'Retiro'}
                      </span>
                      {getMethodBadge(order.payment_method)}
                    </div>
                    <span className="text-lg font-bold text-green-600">${Math.round(order.installment_amount).toLocaleString('es-CL')}</span>
                  </div>
                </div>

                {/* Cliente */}
                <div className="px-3 py-2 border-b border-gray-100">
                  <div className="flex items-center gap-2 text-xs flex-wrap">
                    <User size={12} className="text-gray-600" />
                    <span className="font-semibold text-gray-800">{order.customer_name}</span>
                    {order.customer_phone && (
                      <>
                        <span className="text-gray-500">|</span>
                        <Phone size={10} />
                        <span className="text-gray-600">{order.customer_phone}</span>
                        <a href={`tel:${order.customer_phone}`} className="bg-blue-500 text-white px-2 py-0.5 rounded flex items-center gap-1">
                          <Phone size={8} />
                          Llamar
                        </a>
                        <a href={`https://wa.me/56${order.customer_phone.replace(/[^0-9]/g, '')}`} target="_blank" className="bg-green-500 text-white px-2 py-0.5 rounded flex items-center gap-1">
                          <MessageCircle size={8} />
                          WhatsApp
                        </a>
                      </>
                    )}
                    {order.delivery_type === 'delivery' && order.delivery_address && (
                      <>
                        <span className="text-gray-500">|</span>
                        <MapPin size={10} className="text-blue-600" />
                        <span className="text-blue-700 font-medium">{order.delivery_address}</span>
                      </>
                    )}
                    {order.delivery_type === 'delivery' && parseFloat(order.delivery_fee || 0) > 0 && (
                      <>
                        <span className="text-gray-500">|</span>
                        <Truck size={10} className="text-orange-600" />
                        <span className="text-orange-600 font-semibold">${Math.round(order.delivery_fee).toLocaleString('es-CL')}</span>
                      </>
                    )}
                  </div>
                  {order.customer_notes && (
                    <div className="mt-1 text-xs text-black bg-yellow-300 px-2 py-1 rounded flex items-start gap-1">
                      <MessageSquare size={10} className="mt-0.5 flex-shrink-0 text-black" />
                      <span><span className="font-semibold">Nota:</span> {order.customer_notes}</span>
                    </div>
                  )}
                </div>

                {/* Productos */}
                <div className="px-3 py-2 bg-gray-50">
                  <div className="text-xs font-semibold text-gray-600 mb-1 flex items-center gap-1">
                    <ShoppingCart size={12} />
                    Productos:
                  </div>
                  {order.items?.map((item, i) => (
                    <div key={i} className="mb-2 pb-2 border-b border-gray-200 last:border-0">
                      <div className="flex justify-between items-start">
                        <span className="text-sm font-medium text-gray-800">{item.product_name}</span>
                        <div className="text-right">
                          <div className="text-sm font-bold">x{item.quantity}</div>
                          <div className="text-xs text-gray-600">${Math.round(item.product_price || 0).toLocaleString('es-CL')}</div>
                        </div>
                      </div>
                      {/* Ingredientes por producto - colapsable */}
                      {item.ingredients && item.ingredients.length > 0 && (
                        <IngredientToggle ingredients={item.ingredients} />
                      )}
                    </div>
                  ))}
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}
