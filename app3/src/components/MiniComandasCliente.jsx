import React, { useState, useEffect } from 'react';
import { DollarSign, User, Package, Phone, MessageSquare, CreditCard, Banknote, Smartphone, Store, Truck, Clock, CheckCircle, X } from 'lucide-react';

const MiniComandasCliente = ({ customerName, userId, onOrdersUpdate, isOpen, onClose, onOpenLogin }) => {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentTime, setCurrentTime] = useState(Date.now());
  const [previousOrderStates, setPreviousOrderStates] = useState({});
  const [showFloatingNotif, setShowFloatingNotif] = useState(false);
  const [floatingNotif, setFloatingNotif] = useState(null);

  useEffect(() => {
    if (!customerName && !userId) return;
    loadOrders();
    const interval = setInterval(loadOrders, 10000);
    const timeInterval = setInterval(() => setCurrentTime(Date.now()), 1000);
    return () => {
      clearInterval(interval);
      clearInterval(timeInterval);
    };
  }, [customerName, userId]);

  const loadOrders = async () => {
    try {
      let response;
      if (userId) {
        // Usuario autenticado: usar API que busca por user_id
        response = await fetch(`/api/tuu/get_comandas_v2.php?user_id=${userId}&t=${Date.now()}`);
      } else {
        // Usuario invitado: usar API que busca por customer_name
        response = await fetch(`/api/tuu/get_comandas_v2.php?customer_name=${encodeURIComponent(customerName)}&t=${Date.now()}`);
      }
      console.log('🔍 [MiniComandasCliente] Response status:', response.status, response.ok);
      const responseText = await response.text();
      console.log('🔍 [MiniComandasCliente] Response text:', responseText);
      const data = JSON.parse(responseText);
      console.log('🔍 [MiniComandasCliente] Respuesta API completa:', data);
      console.log('🔍 [MiniComandasCliente] Pedidos cargados:', { userId, customerName, ordersCount: data.orders?.length, success: data.success });
      
      if (data.success) {
        const freshOrders = data.orders || [];
        
        // Detectar cambios de estado
        if (!loading && Object.keys(previousOrderStates).length > 0) {
          freshOrders.forEach(order => {
            const prevStatus = previousOrderStates[order.id];
            if (prevStatus && prevStatus !== order.order_status) {
              // Estado cambió - mostrar notificación flotante
              const statusMessages = {
                'sent_to_kitchen': '🔥 Tu pedido está en cocina',
                'preparing': '🔥 Tu pedido está siendo preparado',
                'ready': '✅ ¡Tu pedido está listo!',
                'out_for_delivery': '🚴 Tu pedido está en camino',
                'delivered': '🎉 Tu pedido ha sido entregado'
              };
              
              setFloatingNotif({
                order_number: order.order_number,
                message: statusMessages[order.order_status] || 'Estado actualizado',
                status: order.order_status
              });
              setShowFloatingNotif(true);
              
              // Reproducir sonido
              try {
                const audio = new Audio('/notificacion.mp3');
                audio.volume = 0.7;
                audio.play().catch(() => {});
              } catch (e) {}
              
              setTimeout(() => setShowFloatingNotif(false), 5000);
            }
          });
        }
        
        // Guardar estados actuales
        const newStates = {};
        freshOrders.forEach(order => {
          newStates[order.id] = order.order_status;
        });
        setPreviousOrderStates(newStates);
        
        setOrders(freshOrders);
        
        const activeCount = freshOrders.filter(
          o => o.order_status !== 'delivered' && o.order_status !== 'cancelled'
        ).length;
        
        if (onOrdersUpdate) {
          onOrdersUpdate(activeCount);
        }
      }
    } catch (error) {
      console.error('Error cargando pedidos:', error);
    } finally {
      setLoading(false);
    }
  };

  const getTimeElapsed = (createdAt) => {
    const created = new Date(createdAt);
    created.setHours(created.getHours() - 3);
    const diffMs = currentTime - created.getTime();
    const totalSeconds = Math.floor(diffMs / 1000);
    return Math.max(0, totalSeconds);
  };

  const formatTime = (seconds) => {
    if (seconds < 60) return `${seconds}s`;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const getStatusInfo = (status, paymentStatus) => {
    // Si no está pagado, mostrar "Procesando" en amarillo
    if (paymentStatus !== 'paid') {
      return { icon: '⏳', text: 'Procesando', color: 'bg-yellow-100 border-yellow-500 text-yellow-800' };
    }
    
    switch(status) {
      case 'pending':
        return { icon: '⏳', text: 'Pendiente', color: 'bg-yellow-100 border-yellow-500 text-yellow-800' };
      case 'sent_to_kitchen':
        return { icon: '🔥', text: 'En Cocina', color: 'bg-orange-100 border-orange-500 text-orange-800' };
      case 'preparing':
        return { icon: '🔥', text: 'Preparando', color: 'bg-orange-100 border-orange-500 text-orange-800' };
      case 'ready':
        return { icon: '✅', text: '¡Listo!', color: 'bg-green-100 border-green-500 text-green-800' };
      case 'out_for_delivery':
        return { icon: '🚴', text: 'En Camino', color: 'bg-blue-100 border-blue-500 text-blue-800' };
      case 'delivered':
        return { icon: '🎉', text: 'Entregado', color: 'bg-blue-100 border-blue-500 text-blue-800' };
      case 'cancelled':
        return { icon: '❌', text: 'Cancelado', color: 'bg-red-100 border-red-500 text-red-800' };
      default:
        return { icon: '📦', text: status, color: 'bg-gray-100 border-gray-500 text-gray-800' };
    }
  };

  const renderProductDetails = (item) => {
    let comboData = null;
    const hasComboData = item.combo_data && item.combo_data !== 'null';
    
    if (hasComboData) {
      try {
        comboData = typeof item.combo_data === 'string' ? JSON.parse(item.combo_data) : item.combo_data;
        console.log('Parsed combo_data for', item.product_name, ':', comboData);
      } catch (e) {
        console.error('Error parsing combo_data:', e);
      }
    }

    return (
      <div key={item.id} className="mb-2 pb-2 border-b border-gray-200 last:border-0 last:mb-0 last:pb-0">
        <div className="flex justify-between items-center">
          <div className="flex items-center gap-1.5">
            <span className="font-medium text-sm text-gray-900">{item.product_name}</span>
            <span className="bg-white text-gray-900 text-xs font-bold px-2 py-0.5 rounded">x{item.quantity}</span>
          </div>
          <span className="text-sm font-bold text-gray-900">${parseInt(item.product_price || item.price || 0).toLocaleString('es-CL')}</span>
        </div>
        
        {comboData?.customizations && comboData.customizations.length > 0 && (
          <div className="ml-3 mt-1 text-xs text-gray-600">
            <div className="font-medium mb-0.5">Incluye:</div>
            {comboData.customizations.map((custom, idx) => (
              <div key={idx} className="flex justify-between items-center">
                <span>• {custom.quantity || item.quantity}x {custom.name}</span>
                <span className="font-semibold text-gray-900">${parseInt(custom.price || 0).toLocaleString('es-CL')}</span>
              </div>
            ))}
          </div>
        )}
        {comboData?.fixed_items && comboData.fixed_items.length > 0 && (
          <div className="ml-3 mt-1 text-xs text-gray-600">
            <div className="font-medium mb-0.5">Incluye:</div>
            {comboData.fixed_items.map((fixed, idx) => (
              <div key={idx}>• {item.quantity}x {fixed.product_name || fixed.name}</div>
            ))}
          </div>
        )}
        {comboData?.selections && Object.keys(comboData.selections).length > 0 && (
          <div className="ml-3 mt-1 text-xs text-gray-600">
            <div className="font-medium mb-0.5">Seleccionado:</div>
            {Object.entries(comboData.selections).map(([group, selection], idx) => {
              if (Array.isArray(selection)) {
                return selection.map((sel, sidx) => (
                  <div key={`${idx}-${sidx}`}>• {item.quantity}x {sel.name}</div>
                ));
              } else if (selection && selection.name) {
                return <div key={idx}>• {item.quantity}x {selection.name}</div>;
              }
              return null;
            })}
          </div>
        )}
      </div>
    );
  };

  const getPaymentIcon = (method) => {
    switch(method) {
      case 'card': return <CreditCard size={14} />;
      case 'cash': return <Banknote size={14} />;
      case 'transfer': return <Smartphone size={14} />;
      case 'pedidosya': return <Package size={14} />;
      default: return <DollarSign size={14} />;
    }
  };

  const getPaymentText = (method) => {
    switch(method) {
      case 'card': return 'Tarjeta';
      case 'cash': return 'Efectivo';
      case 'transfer': return 'Transferencia';
      case 'pedidosya': return 'PedidosYA';
      default: return method;
    }
  };

  const activeOrders = orders.filter(o => 
    o.order_status !== 'cancelled' && 
    o.order_status !== 'delivered' &&
    !o.order_number.startsWith('RL6-')
  );
  
  // Mensaje para usuarios no logueados
  if (!customerName) {
    return (
      <>
        <div 
          className={`fixed inset-0 bg-transparent transition-opacity duration-300 z-50 ${
            isOpen ? 'opacity-50' : 'opacity-0 pointer-events-none'
          }`}
          onClick={onClose}
        />
        <div 
          className={`fixed top-0 right-0 h-full w-full bg-white z-50 transform transition-transform duration-300 ease-out ${
            isOpen ? 'translate-x-0' : 'translate-x-full'
          }`}
        >
          <div className="flex flex-col h-full">
            <div className="border-b flex justify-between items-center p-4 bg-gradient-to-r from-orange-500 to-orange-600">
              <h2 className="font-bold text-white flex items-center gap-2 text-lg">
                <Package size={20} /> Mis Pedidos
              </h2>
              <button onClick={onClose} className="p-1 text-white hover:text-orange-100">
                <X size={24} />
              </button>
            </div>
            <div className="flex-1 flex items-center justify-center p-8">
              <div className="text-center">
                <Package size={64} className="mx-auto mb-4 text-gray-300" />
                <h3 className="text-lg font-bold text-gray-800 mb-2">Inicia sesión para ver tus pedidos</h3>
                <p className="text-sm text-gray-600 mb-4">
                  Regístrate o inicia sesión para hacer seguimiento en tiempo real del estado de tus pedidos.
                </p>
                <button
                  onClick={() => {
                    onClose();
                    if (onOpenLogin) onOpenLogin();
                  }}
                  className="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-6 rounded-lg transition-colors"
                >
                  Iniciar Sesión
                </button>
              </div>
            </div>
          </div>
        </div>
      </>
    );
  }
  
  return (
    <>
      {/* Mini-pestaña flotante de notificación */}
      {showFloatingNotif && floatingNotif && (
        <div className="fixed top-20 right-4 z-50 animate-slide-in">
          <div className="bg-white border-l-4 border-orange-500 rounded-lg shadow-lg p-4 max-w-sm">
            <div className="flex items-start">
              <div className="text-2xl mr-3">{getStatusInfo(floatingNotif.status).icon}</div>
              <div className="flex-1">
                <div className="font-semibold text-gray-800 text-sm">{floatingNotif.order_number}</div>
                <div className="text-gray-600 text-sm mt-1">{floatingNotif.message}</div>
              </div>
              <button onClick={() => setShowFloatingNotif(false)} className="text-gray-400 hover:text-gray-600 ml-2">×</button>
            </div>
          </div>
        </div>
      )}
      
      {/* Overlay */}
      <div 
        className={`fixed inset-0 bg-transparent transition-opacity duration-300 z-50 ${
          isOpen ? 'opacity-50' : 'opacity-0 pointer-events-none'
        }`}
        onClick={onClose}
      />
      
      {/* Drawer Panel */}
      <div 
        className={`fixed top-0 right-0 h-full w-full bg-white z-50 transform transition-transform duration-300 ease-out ${
          isOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        <div className="flex flex-col h-full">
          {/* Header */}
          <div className="border-b flex justify-between items-center p-4 bg-gradient-to-r from-orange-500 to-orange-600">
            <h2 className="font-bold text-white flex items-center gap-2 text-lg">
              <Package size={20} /> Mis Pedidos
            </h2>
            <button onClick={onClose} className="p-1 text-white hover:text-orange-100">
              <X size={24} />
            </button>
          </div>
          
          {/* Content */}
          <div className="flex-1 overflow-y-auto">
          {activeOrders.length === 0 ? (
            <div className="p-8 text-center text-gray-500">
              <Package size={48} className="mx-auto mb-2 opacity-50" />
              <p>No tienes pedidos activos</p>
            </div>
          ) : (
            <div className="divide-y divide-gray-200">
              {activeOrders.map(order => {
                const seconds = getTimeElapsed(order.created_at);
                const isPaid = order.payment_status === 'paid';
                const statusInfo = getStatusInfo(order.order_status, order.payment_status);

                return (
                  <div key={order.id} className="p-4 bg-white hover:bg-gray-50 transition-colors">
                    {/* Header: Pedido + Contador + Delivery/Retiro */}
                    <div className="mb-3">
                      <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center gap-2">
                          <span className="text-xs text-gray-500">Pedido:</span>
                          <span className="font-bold text-gray-800 text-sm">{order.order_number}</span>
                        </div>
                        <div className="bg-orange-100 text-orange-800 font-bold text-sm px-2 py-1 rounded">{formatTime(seconds)}</div>
                      </div>
                      {order.delivery_type === 'delivery' && order.delivery_address ? (
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-2">
                          <div className="flex items-center gap-2 text-xs">
                            <Truck size={14} className="text-blue-600 flex-shrink-0" />
                            <span className="font-semibold text-gray-900">Delivery</span>
                            <span className="text-gray-700">• {order.delivery_address}</span>
                            {order.created_at && (
                              <><span className="text-gray-400">•</span><span className="text-gray-600">{new Date(new Date(order.created_at).getTime() - 3 * 60 * 60 * 1000).toLocaleTimeString('es-CL', {hour: '2-digit', minute: '2-digit'})}</span></>
                            )}
                          </div>
                        </div>
                      ) : (
                        <div className="bg-green-50 border border-green-200 rounded-lg p-2">
                          <div className="flex items-center gap-2 text-xs">
                            <Store size={14} className="text-green-600 flex-shrink-0" />
                            <span className="font-semibold text-gray-900">Retiro</span>
                            {(order.pickup_time || order.created_at) && (
                              <><span className="text-gray-400">•</span><span className="text-gray-600">{order.pickup_time || new Date(new Date(order.created_at).getTime() - 3 * 60 * 60 * 1000).toLocaleTimeString('es-CL', {hour: '2-digit', minute: '2-digit'})}</span></>
                            )}
                          </div>
                        </div>
                      )}
                    </div>

                    {/* Productos y Totales */}
                    <div className="bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg p-3 mb-3 border border-gray-200">
                      {order.items && order.items.map(item => renderProductDetails(item))}
                      
                      {/* Totales integrados */}
                      {(() => {
                        const subtotal = order.items?.reduce((sum, item) => {
                          const price = parseInt(item.product_price || item.price || 0);
                          let itemTotal = price * item.quantity;
                          
                          // Sumar customizations si existen
                          if (item.combo_data && item.combo_data !== 'null') {
                            try {
                              const comboData = typeof item.combo_data === 'string' ? JSON.parse(item.combo_data) : item.combo_data;
                              if (comboData.customizations) {
                                comboData.customizations.forEach(custom => {
                                  itemTotal += (custom.price || 0) * (custom.quantity || 1);
                                });
                              }
                            } catch (e) {}
                          }
                          
                          return sum + itemTotal;
                        }, 0) || 0;
                        const total = parseInt(order.installment_amount || 0);
                        const deliveryCost = order.delivery_type === 'delivery' ? parseInt(order.delivery_fee || 0) : 0;
                        const discountAmount = parseFloat(order.discount_amount || 0);
                        const deliveryDiscount = parseFloat(order.delivery_discount || 0);
                        const cashbackUsed = parseFloat(order.cashback_used || 0);
                        const deliveryExtras = parseFloat(order.delivery_extras || 0);
                        let deliveryExtrasItems = [];
                        try {
                          if (order.delivery_extras_items) {
                            deliveryExtrasItems = typeof order.delivery_extras_items === 'string' ? JSON.parse(order.delivery_extras_items) : order.delivery_extras_items;
                          }
                        } catch (e) {}
                        
                        return (
                          <div className="mt-3 pt-3 border-t border-gray-300">
                            {discountAmount > 0 && (
                              <div className="flex justify-between items-center text-sm mb-1">
                                <span className="text-green-600">🎉 Descuento:</span>
                                <span className="font-semibold text-green-600">-${discountAmount.toLocaleString('es-CL')}</span>
                              </div>
                            )}
                            {cashbackUsed > 0 && (
                              <div className="flex justify-between items-center text-sm mb-1">
                                <span className="text-green-600">💰 Cashback:</span>
                                <span className="font-semibold text-green-600">-${cashbackUsed.toLocaleString('es-CL')}</span>
                              </div>
                            )}
                            <div className="flex justify-between items-center text-sm mb-1">
                              <span className="text-gray-600">Subtotal:</span>
                              <span className="font-semibold text-gray-900">${(subtotal - discountAmount - cashbackUsed).toLocaleString('es-CL')}</span>
                            </div>
                            {deliveryCost > 0 && (
                              <div className="flex justify-between items-center text-sm mb-1">
                                <span className="text-gray-600">Delivery:</span>
                                <span className="font-semibold text-gray-900">${deliveryCost.toLocaleString('es-CL')}</span>
                              </div>
                            )}
                            {deliveryDiscount > 0 && (
                              <div className="flex justify-between items-center text-sm mb-1">
                                <span className="text-green-600">🎉 Descuento Delivery:</span>
                                <span className="font-semibold text-green-600">-${deliveryDiscount.toLocaleString('es-CL')}</span>
                              </div>
                            )}
                            {deliveryExtras > 0 && deliveryExtrasItems.length > 0 && (
                              <div className="text-sm mb-1">
                                <div className="text-gray-600 font-medium mb-1">Extras delivery:</div>
                                {deliveryExtrasItems.map((extra, idx) => (
                                  <div key={idx} className="flex justify-between items-center ml-3 text-xs text-gray-600">
                                    <span>{extra.quantity}x {extra.name}</span>
                                    <span className="font-semibold">${(extra.price * extra.quantity).toLocaleString('es-CL')}</span>
                                  </div>
                                ))}
                              </div>
                            )}
                            <div className={`rounded-lg px-3 py-2 -mx-3 -mb-3 ${isPaid ? 'bg-gradient-to-r from-green-200 via-green-300 to-green-200' : 'bg-gradient-to-r from-yellow-200 via-yellow-300 to-yellow-200'}`}>
                              <div className="flex justify-between items-center">
                                <div className="flex items-center gap-2">
                                  <span className="font-bold text-gray-900">Total:</span>
                                  <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${isPaid ? 'bg-green-600 text-white' : 'bg-orange-600 text-white'}`}>
                                    {isPaid ? 'Pagado' : 'Pendiente de pago'}
                                  </span>
                                </div>
                                <span className={`font-bold text-lg ${isPaid ? 'text-green-800' : 'text-yellow-800'}`}>${total.toLocaleString('es-CL')}</span>
                              </div>
                            </div>
                          </div>
                        );
                      })()}
                    </div>

                    {/* Pago */}
                    <div className="flex items-center gap-2 mb-3">
                      <div className="flex items-center gap-1 text-xs bg-white px-2 py-1 rounded-full border border-gray-200">
                        {getPaymentIcon(order.payment_method)}
                        <span className="font-medium">{getPaymentText(order.payment_method)}</span>
                      </div>
                      <div className={`flex items-center gap-1 text-xs px-2 py-1 rounded-full font-semibold ${isPaid ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`}>
                        <CheckCircle size={12} />
                        <span>{isPaid ? 'Pagado' : 'Pendiente'}</span>
                      </div>
                    </div>

                    {/* Notas */}
                    {order.customer_notes && (
                      <div className="bg-amber-50 border border-amber-200 rounded-lg p-2">
                        <div className="flex items-center gap-2 text-xs">
                          <MessageSquare size={14} className="text-amber-600 flex-shrink-0" />
                          <span className="font-semibold text-gray-900">Notas:</span>
                          <span className="text-gray-700">{order.customer_notes}</span>
                        </div>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
          </div>
        </div>
      </div>
    </>
  );
};

export default MiniComandasCliente;
