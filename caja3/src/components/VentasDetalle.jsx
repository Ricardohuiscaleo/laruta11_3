import { useState, useEffect } from 'react';
import { ArrowLeft, BarChart3, Search, Banknote, CreditCard, Smartphone, Truck, Clock, User, Phone, MessageCircle, MapPin, ShoppingCart, FlaskConical, Store, Sparkles, Tag, Gift, MessageSquare, Edit, Trash2, ChevronDown, ChevronRight, ArrowUp, ArrowDown, Bike, Home, Camera, Check, X } from 'lucide-react';

// Componente minimalista para ingredientes colapsables
function IngredientToggle({ ingredients, label }) {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <div className="mt-1">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1"
      >
        {isOpen ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
        <span>{label || `Ingredientes (${ingredients.length})`}</span>
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
  const [viewingPhotos, setViewingPhotos] = useState(null); // { photos: [], currentIndex: 0 }

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
          <div className="bg-white rounded-lg mb-3 shadow-md border-2 border-orange-200 overflow-hidden">
            <button
              onClick={() => setShowIngredients(!showIngredients)}
              className="w-full px-3 py-2 flex justify-between items-center text-left hover:bg-orange-50 transition-colors"
            >
              <div className="flex items-center gap-2">
                <BarChart3 size={16} className="text-orange-600" />
                <span className="text-xs font-black text-gray-900 uppercase tracking-tighter">Inventario y Consumo (Control v4.3)</span>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-[10px] bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded-full font-bold">
                  {showIngredients ? 'OCULTAR' : 'VER'}
                </span>
                <span className={`text-orange-400 transition-transform duration-300 ${showIngredients ? 'rotate-180' : ''}`}>▼</span>
              </div>
            </button>

            {showIngredients && (
              <div className="px-0 pb-2 overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="bg-gray-50 text-[9px] uppercase tracking-wider font-bold text-gray-500 border-b border-gray-100">
                      <th className="px-2 py-1.5">Insumo</th>
                      <th className="px-1 py-1.5 text-right">Cons.</th>
                      <th className="px-1 py-1.5 text-right">Stock</th>
                      <th className="px-1 py-1.5 text-right">MaxD</th>
                      <th className="px-2 py-1.5 text-center">Estado</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {stats.ingredient_consumption.map((ing, i) => {
                      const consumed = parseFloat(ing.total || 0);
                      const stock = parseFloat(ing.stock_actual || 0);
                      const maxDaily = parseFloat(ing.max_daily_consumption || 0);
                      let unit = ing.unit || 'g';
                      if (unit === 'unidad') unit = 'U';
                      if (unit === 'unit') unit = 'U';

                      const formatQty = (val) => {
                        return unit === 'g' && val >= 1000
                          ? `${(val / 1000).toFixed(1)}kg`
                          : `${Math.round(val)}${unit}`;
                      };

                      // Lógica de indicadores corregida (v4.3)
                      let statusColor = "bg-green-500";
                      let statusText = "OK";

                      if (maxDaily > 0) {
                        if (stock < maxDaily) {
                          statusColor = "bg-red-500 animate-pulse";
                          statusText = "Crítico";
                        } else if (stock < (maxDaily * 3)) {
                          statusColor = "bg-yellow-400";
                          statusText = "Bajo";
                        }
                      } else if (stock <= 0) {
                        statusColor = "bg-red-500";
                        statusText = "S/S";
                      }

                      return (
                        <tr key={i} className="hover:bg-orange-50/30 transition-colors text-[10px]">
                          <td className="px-2 py-2 font-medium text-gray-700 truncate max-w-[100px]">
                            {ing.name}
                          </td>
                          <td className="px-1 py-2 text-right font-bold text-orange-600">
                            {formatQty(consumed)}
                          </td>
                          <td className={`px-1 py-2 text-right font-semibold ${stock <= 0 ? 'text-red-500' : 'text-gray-600'}`}>
                            {formatQty(stock)}
                          </td>
                          <td className="px-1 py-2 text-right text-gray-400 italic">
                            {formatQty(maxDaily)}
                          </td>
                          <td className="px-2 py-2">
                            <div className="flex flex-col items-center gap-0">
                              <div className={`w-2 h-2 rounded-full ${statusColor} shadow-sm`}></div>
                              <span className="text-[7px] font-bold text-gray-400 uppercase leading-none">{statusText}</span>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
                <div className="mt-1 px-2 flex items-center justify-center gap-2 text-[8px] text-gray-400 border-t border-gray-50 pt-1">
                  <div className="flex items-center gap-0.5">
                    <div className="w-1.5 h-1.5 rounded-full bg-red-500"></div> Crítico (&lt;1d)
                  </div>
                  <div className="flex items-center gap-0.5">
                    <div className="w-1.5 h-1.5 rounded-full bg-yellow-400"></div> Bajo (&lt;3d)
                  </div>
                  <div className="flex items-center gap-0.5">
                    <div className="w-1.5 h-1.5 rounded-full bg-green-500"></div> OK
                  </div>
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
                      <div className="flex gap-3">
                        <div className="flex-1">
                          <div className="flex justify-between items-start">
                            <span className="text-sm font-medium text-gray-800">{item.product_name}</span>
                            <div className="text-right">
                              <div className="text-sm font-bold">x{item.quantity}</div>
                              <div className="text-xs text-gray-600">${Math.round(item.product_price || 0).toLocaleString('es-CL')}</div>
                            </div>
                          </div>
                        </div>
                      </div>
                      {(() => {
                        try {
                          const combo = typeof item.combo_data === 'string' ? JSON.parse(item.combo_data) : item.combo_data;
                          if (!combo) return null;

                          return (
                            <div className="mt-1 space-y-1">
                              {/* Secciones del combo (ej: Bebidas) */}
                              {combo.selections && Object.entries(combo.selections).map(([group, selection], idx) => {
                                // Caso 1: Una selección única (ej: Bebidas: {id: 99, name: "Coca-Cola"})
                                if (selection && typeof selection === 'object' && !Array.isArray(selection) && selection.name) {
                                  return (
                                    <div key={idx} className="flex items-center gap-1 text-xs text-blue-700 bg-blue-50 px-2 py-0.5 rounded w-fit">
                                      <Tag size={10} />
                                      <span className="font-semibold">{group}:</span>
                                      <span>{selection.name}</span>
                                    </div>
                                  );
                                }
                                // Caso 2: Múltiples selecciones (ej: Bebidas: [{name: "Coca-Cola"}, {name: "Fanta"}])
                                if (Array.isArray(selection)) {
                                  return selection.map((s, sIdx) => (
                                    <div key={`${idx}-${sIdx}`} className="flex items-center gap-1 text-xs text-blue-700 bg-blue-50 px-2 py-0.5 rounded w-fit">
                                      <Tag size={10} />
                                      <span className="font-semibold">{group}:</span>
                                      <span>{s.name}</span>
                                    </div>
                                  ));
                                }
                                return null;
                              })}

                              {/* Personalizaciones del Item */}
                              {combo.customizations && combo.customizations.length > 0 && (
                                <div className="text-[10px] text-orange-700 bg-orange-50 px-2 py-0.5 rounded flex items-center gap-1 w-fit border border-orange-100 italic">
                                  <Edit size={10} />
                                  <span>
                                    {combo.customizations.map(c => {
                                      const name = typeof c === 'string' ? c : (c.name || 'Extra');
                                      const qty = c.quantity || 1;
                                      return qty > 1 ? `${qty}x ${name}` : name;
                                    }).join(', ')}
                                  </span>
                                </div>
                              )}
                            </div>
                          );
                        } catch (e) {
                          return null;
                        }
                      })()}
                      {item.ingredients?.length > 0 && (
                        <IngredientToggle ingredients={item.ingredients} />
                      )}
                    </div>
                  ))}
                  {order.order_ingredients?.length > 0 && (
                    <div style={{ marginTop: '8px', paddingTop: '8px', borderTop: '1px dashed #e5e7eb' }}>
                      <IngredientToggle ingredients={order.order_ingredients} label="Ingredientes totales (orden antigua)" />
                    </div>
                  )}

                  {/* Galería de Fotos del Pedido (Pie) */}
                  {(() => {
                    let photos = [];
                    try {
                      if (order.dispatch_photo_url) {
                        const decoded = JSON.parse(order.dispatch_photo_url);
                        photos = Array.isArray(decoded) ? decoded : [order.dispatch_photo_url];
                      }
                    } catch (e) {
                      photos = [order.dispatch_photo_url];
                    }

                    if (photos.length === 0) return null;

                    return (
                      <div className="mt-3 pt-3 border-t border-gray-200">
                        <div className="text-[10px] font-black text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1">
                          <Camera size={12} /> Fotos de Entrega:
                        </div>
                        <div className="flex flex-wrap gap-2">
                          {photos.map((url, idx) => (
                            <div
                              key={idx}
                              className="w-14 h-14 rounded-lg overflow-hidden border-2 border-white shadow-sm cursor-pointer hover:scale-105 active:scale-95 transition-all"
                              onClick={() => setViewingPhotos({ photos, currentIndex: idx })}
                            >
                              <img src={url} alt={`entrega-${idx}`} className="w-full h-full object-cover" />
                            </div>
                          ))}
                        </div>
                      </div>
                    );
                  })()}
                </div>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Visor de Fotos */}
      {viewingPhotos && (
        <div className="fixed inset-0 bg-black/95 z-[60] flex flex-col items-center justify-center p-4 animate-in fade-in duration-300" onClick={() => setViewingPhotos(null)}>
          <button className="absolute top-6 right-6 text-white/70 hover:text-white transition-colors">
            <X size={32} />
          </button>

          <div className="w-full max-w-4xl aspect-square md:aspect-video flex items-center justify-center relative" onClick={e => e.stopPropagation()}>
            {viewingPhotos.photos.length > 1 && (
              <button
                onClick={() => setViewingPhotos(prev => ({ ...prev, currentIndex: (prev.currentIndex - 1 + prev.photos.length) % prev.photos.length }))}
                className="absolute left-0 bg-white/10 hover:bg-white/20 text-white p-4 rounded-full z-10 transition-colors"
              >
                <span className="text-2xl font-black">{"<"}</span>
              </button>
            )}

            <img
              src={viewingPhotos.photos[viewingPhotos.currentIndex]}
              alt="full photo"
              className="max-w-full max-h-full object-contain rounded-xl shadow-2xl border border-white/5"
            />

            {viewingPhotos.photos.length > 1 && (
              <button
                onClick={() => setViewingPhotos(prev => ({ ...prev, currentIndex: (prev.currentIndex + 1) % prev.photos.length }))}
                className="absolute right-0 bg-white/10 hover:bg-white/20 text-white p-4 rounded-full z-10 transition-colors"
              >
                <span className="text-2xl font-black">{">"}</span>
              </button>
            )}
          </div>

          <div className="mt-8 flex flex-col items-center gap-2">
            <span className="text-white/80 font-bold text-xs bg-white/10 px-4 py-1.5 rounded-full border border-white/5 backdrop-blur-md">
              Foto {viewingPhotos.currentIndex + 1} de {viewingPhotos.photos.length}
            </span>
            <div className="flex gap-1.5">
              {viewingPhotos.photos.map((_, i) => (
                <div key={i} className={`h-1 rounded-full transition-all duration-300 ${i === viewingPhotos.currentIndex ? 'w-8 bg-orange-500' : 'w-2 bg-white/20'}`} />
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
