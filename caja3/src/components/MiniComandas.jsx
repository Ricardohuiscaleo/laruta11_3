import React, { useState, useEffect } from 'react';
import { DollarSign, User, Package, Phone, MessageSquare, Copy, CreditCard, Banknote, Smartphone, Store, Truck, Clock, XCircle, CheckCircle, X } from 'lucide-react';
import ChecklistCard from './ChecklistCard.jsx';

const MiniComandas = ({ onOrdersUpdate, onClose, activeOrdersCount }) => {
  const [orders, setOrders] = useState([]);
  const [checklists, setChecklists] = useState([]);
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState(null);
  const [currentTime, setCurrentTime] = useState(Date.now());

  useEffect(() => {
    // Cargar datos y quitar loading después
    Promise.all([loadOrders(), loadChecklists()]).finally(() => {
      setLoading(false);
    });
    
    const interval = setInterval(() => {
      loadOrders();
      loadChecklists();
    }, 3000);
    const timeInterval = setInterval(() => setCurrentTime(Date.now()), 1000);
    return () => {
      clearInterval(interval);
      clearInterval(timeInterval);
    };
  }, []);



  const loadOrders = async () => {
    try {
      const response = await fetch(`/api/tuu/get_comandas_v2.php?t=${Date.now()}`);
      const data = await response.json();
      if (data.success) {
        setOrders(data.orders || []);
      }
    } catch (error) {
      console.error('Error cargando pedidos:', error);
    }
  };

  const loadChecklists = async () => {
    try {
      const now = new Date();
      const hours = now.getHours();
      const minutes = now.getMinutes();
      const currentTime = hours * 60 + minutes;
      
      const shouldLoadApertura = currentTime >= 1020 && currentTime < 1140;
      const shouldLoadCierre = currentTime >= 30 && currentTime < 105;
      
      if (!shouldLoadApertura && !shouldLoadCierre) {
        setChecklists([]);
        return;
      }
      
      const types = [];
      if (shouldLoadApertura) types.push('apertura');
      if (shouldLoadCierre) types.push('cierre');
      
      const results = [];
      for (const type of types) {
        const res = await fetch(`/api/checklist.php?action=get_active&type=${type}&date=${now.toISOString().split('T')[0]}`);
        const data = await res.json();
        if (data.success && data.checklist) {
          results.push(data.checklist);
        }
      }
      setChecklists(results);
    } catch (error) {
      console.error('Error cargando checklists:', error);
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

  const getTimeAlert = (seconds) => {
    const minutes = Math.floor(seconds / 60);
    if (minutes >= 20) {
      return { icon: '🚨', color: 'bg-red-100 border-red-500', textColor: 'text-red-800', status: 'MUY ATRASADO', statusColor: 'text-red-800' };
    } else if (minutes >= 10) {
      return { icon: '⚠️', color: 'bg-yellow-100 border-yellow-500', textColor: 'text-yellow-800', status: 'ATRASADO', statusColor: 'text-yellow-800' };
    }
    return { icon: '⏱️', color: 'bg-white border-gray-300', textColor: 'text-gray-800', status: 'A TIEMPO', statusColor: 'text-green-600' };
  };

  const isScheduledOrder = (order) => {
    return order.is_scheduled === 1 || order.is_scheduled === true;
  };

  const getScheduledTimeDisplay = (scheduledTime) => {
    if (!scheduledTime) return null;
    try {
      const scheduled = new Date(scheduledTime);
      return scheduled.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return scheduledTime;
    }
  };

  const confirmPayment = async (orderId, orderNumber, paymentMethod) => {
    const methodName = {
      cash: 'efectivo',
      card: 'tarjeta',
      transfer: 'transferencia',
      pedidosya: 'PedidosYA'
    }[paymentMethod] || paymentMethod;

    if (!confirm(`¿Confirmar pago en ${methodName} del pedido ${orderNumber}?`)) return;

    setProcessing(orderId);
    try {
      const response = await fetch('/api/confirm_transfer_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId })
      });

      const text = await response.text();
      if (text.trim().startsWith('<')) {
        console.error('API devolvió HTML:', text.substring(0, 200));
        alert('❌ Error del servidor. Revisa la consola.');
        return;
      }

      const result = JSON.parse(text);
      if (result.success) {
        await loadOrders();
      } else {
        alert('Error: ' + (result.error || 'No se pudo confirmar el pago'));
      }
    } catch (error) {
      console.error('Error confirmando pago:', error);
      alert('Error al confirmar el pago');
    } finally {
      setProcessing(null);
    }
  };

  const deliverOrder = async (orderId, orderNumber) => {
    if (!confirm(`¿Marcar pedido ${orderNumber} como entregado?`)) return;

    setProcessing(orderId);
    try {
      const response = await fetch('/api/tuu/update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, order_status: 'delivered' })
      });

      const result = await response.json();
      if (result.success) {
        await loadOrders();
      } else {
        alert('Error: ' + (result.error || 'No se pudo entregar el pedido'));
      }
    } catch (error) {
      console.error('Error entregando pedido:', error);
      alert('Error al entregar el pedido');
    } finally {
      setProcessing(null);
    }
  };

  const cancelOrder = async (orderId, orderNumber) => {
    const order = orders.find(o => o.id === orderId);
    const isRL6 = order?.payment_method === 'rl6_credit';
    
    const confirmMsg = isRL6 
      ? `⚠️ ¿ANULAR el pedido ${orderNumber}?\n\n✅ Se reintegrará el crédito RL6 automáticamente.\n\nEsta acción no se puede deshacer.`
      : `⚠️ ¿ANULAR el pedido ${orderNumber}?\n\nEsta acción no se puede deshacer.`;
    
    if (!confirm(confirmMsg)) return;

    setProcessing(orderId);
    try {
      if (isRL6) {
        // Para pedidos RL6, solo llamar a la API de reintegro (ya cancela el pedido)
        const refundResponse = await fetch('/api/rl6_refund_credit.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ 
            order_number: orderNumber,
            admin_id: 1,
            reason: 'Pedido anulado desde comandas'
          })
        });
        
        const refundResult = await refundResponse.json();
        if (refundResult.success) {
          alert('✅ Pedido anulado y crédito RL6 reintegrado');
          await loadOrders();
        } else {
          alert('⚠️ Error: ' + refundResult.message);
        }
      } else {
        // Para pedidos normales, cancelar con restauración de inventario
        const response = await fetch('/api/cancel_order.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ order_id: orderId })
        });

        const result = await response.json();
        if (result.success) {
          alert(result.message);
          await loadOrders();
        } else {
          alert('Error: ' + (result.error || 'No se pudo anular el pedido'));
        }
      }
    } catch (error) {
      console.error('Error anulando pedido:', error);
      alert('Error al anular el pedido');
    } finally {
      setProcessing(null);
    }
  };

  const renderProductDetails = (item) => {
    const comboData = item.combo_data ? JSON.parse(item.combo_data) : null;
    const isCombo = item.item_type === 'combo' && comboData;

    return (
      <div key={item.id} className="mb-3 pb-3 border-b border-gray-200 last:border-0">
        <div className="flex justify-between items-start mb-1">
          <span className="font-medium text-sm">{item.product_name}</span>
          <div className="text-right">
            <div className="text-sm font-bold">x{item.quantity}</div>
            <div className="text-xs text-gray-600">${parseInt(item.product_price || item.price || 0).toLocaleString('es-CL')}</div>
          </div>
        </div>
        
        {isCombo && (
          <>
            {comboData.fixed_items && comboData.fixed_items.length > 0 && (
              <div className="ml-3 mt-1 text-xs text-gray-600">
                <div className="font-medium mb-0.5">Incluye:</div>
                {comboData.fixed_items.map((fixed, idx) => (
                  <div key={idx}>• {item.quantity}x {fixed.product_name || fixed.name}</div>
                ))}
              </div>
            )}
            {comboData.selections && Object.keys(comboData.selections).length > 0 && (
              <div className="ml-3 mt-1 space-y-1">
                <div className="font-medium mb-0.5 text-xs text-gray-600">Seleccionado:</div>
                {Object.entries(comboData.selections).map(([group, selection], idx) => {
                  if (Array.isArray(selection)) {
                    return selection.map((sel, sidx) => {
                      const imageUrl = sel.image_url || sel.image || 'https://laruta11-images.s3.amazonaws.com/menu/logo.png';
                      return (
                        <div key={`${idx}-${sidx}`} className="flex items-center gap-2 bg-blue-50 p-1.5 rounded border border-blue-200">
                          <img src={imageUrl} alt={sel.name} className="w-10 h-10 object-cover rounded border border-blue-300" onError={(e) => { e.target.src = 'https://laruta11-images.s3.amazonaws.com/menu/logo.png'; }} />
                          <span className="text-xs text-gray-700">{item.quantity}x {sel.name}</span>
                        </div>
                      );
                    });
                  } else if (selection && selection.name) {
                    const imageUrl = selection.image_url || selection.image || 'https://laruta11-images.s3.amazonaws.com/menu/logo.png';
                    return (
                      <div key={idx} className="flex items-center gap-2 bg-blue-50 p-1.5 rounded border border-blue-200">
                        <img src={imageUrl} alt={selection.name} className="w-10 h-10 object-cover rounded border border-blue-300" onError={(e) => { e.target.src = 'https://laruta11-images.s3.amazonaws.com/menu/logo.png'; }} />
                        <span className="text-xs text-gray-700">{item.quantity}x {selection.name}</span>
                      </div>
                    );
                  }
                  return null;
                })}
              </div>
            )}
          </>
        )}
        
        {comboData && comboData.customizations && comboData.customizations.length > 0 && (
          <div className="ml-3 mt-1 text-xs text-gray-600">
            <div className="font-medium mb-0.5">Incluye:</div>
            {comboData.customizations.map((custom, idx) => (
              <div key={idx}>• {item.quantity}x {custom.name}</div>
            ))}
          </div>
        )}
      </div>
    );
  };

  const renderDeliveryExtras = (order) => {
    if (!order.delivery_extras_items) return null;
    
    try {
      const extras = JSON.parse(order.delivery_extras_items);
      if (!Array.isArray(extras) || extras.length === 0) return null;
      
      return (
        <div className="mb-3 bg-orange-50 border border-orange-200 rounded p-2">
          <div className="text-xs font-semibold text-orange-800 mb-1">✨ Extras de Delivery:</div>
          {extras.map((extra, idx) => (
            <div key={idx} className="flex justify-between items-center text-xs text-orange-700">
              <span>• {extra.name} x{extra.quantity}</span>
              <span className="font-semibold">${parseInt(extra.price * extra.quantity).toLocaleString('es-CL')}</span>
            </div>
          ))}
        </div>
      );
    } catch (e) {
      return null;
    }
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

  const activeOrders = orders.filter(o => o.order_status !== 'delivered' && o.order_status !== 'cancelled');
  const activeChecklists = checklists.filter(c => c.status !== 'completed' && c.status !== 'missed');
  const immediateOrders = activeOrders.filter(o => !isScheduledOrder(o));
  const scheduledOrders = activeOrders.filter(o => isScheduledOrder(o));

  const renderOrderCard = (order, isScheduled = false) => {
    const isPaid = order.payment_status === 'paid';
    const seconds = isScheduled ? 0 : getTimeElapsed(order.created_at);
    const timeAlert = isScheduled ? null : getTimeAlert(seconds);
    const scheduledTimeDisplay = isScheduled ? getScheduledTimeDisplay(order.scheduled_time) : null;

    return (
      <div key={order.id} className={`p-4 ${isScheduled ? 'bg-purple-50 border-l-4 border-purple-500' : `${timeAlert.color} border-l-4`}`}>
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2 flex-wrap">
            <span className="text-lg">{isScheduled ? '🕐' : timeAlert.icon}</span>
            <span className="text-xs font-mono">{order.order_number}</span>
            <button 
              onClick={() => cancelOrder(order.id, order.order_number)} 
              disabled={processing === order.id}
              className="bg-red-500 hover:bg-red-600 disabled:bg-gray-400 text-white font-bold py-0.5 px-2 rounded text-xs"
            >
              ANULAR
            </button>
            {isScheduled && scheduledTimeDisplay && (
              <span className="text-xs font-bold text-purple-700 bg-purple-200 px-2 py-1 rounded">Para las {scheduledTimeDisplay}</span>
            )}
            {!isScheduled && (
              <>
                <span className={`text-xs font-mono ${timeAlert.textColor}`}>{formatTime(seconds)}</span>
                <span className={`text-xs font-bold ${timeAlert.statusColor}`}>({timeAlert.status})</span>
              </>
            )}
          </div>
          <button onClick={() => {
            const text = `Pedido ${order.order_number}\nCliente: ${order.customer_name}\nTotal: $${parseInt(order.installment_amount || 0).toLocaleString('es-CL')}`;
            navigator.clipboard.writeText(text);
            alert('✓ Copiado');
          }} className="bg-gray-500 hover:bg-gray-600 text-white p-1.5 rounded">
            <Copy size={14} />
          </button>
        </div>

        <div className="mb-3">
          <div className="flex items-center gap-1 text-xs flex-wrap">
            <User size={12} />
            <span className="font-medium">{order.customer_name}</span>
            {order.customer_phone && (
              <>
                <span className="text-gray-400">|</span>
                <span className="text-gray-600">{order.customer_phone}</span>
                <span className="text-gray-400">|</span>
                <a href={`tel:${order.customer_phone}`} className="bg-blue-500 hover:bg-blue-600 text-white py-0.5 px-2 rounded flex items-center gap-1">
                  <Phone size={10} />Llamar
                </a>
                <a href={`https://wa.me/56${order.customer_phone.replace(/[^0-9]/g, '')}`} target="_blank" rel="noopener noreferrer" className="bg-green-500 hover:bg-green-600 text-white py-0.5 px-2 rounded flex items-center gap-1">
                  <MessageSquare size={10} />Mensaje
                </a>
                {!isScheduled && order.delivery_type === 'delivery' && order.delivery_address && isPaid && (
                  <a
                    href={`https://wa.me/56${order.customer_phone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(`Hola ${order.customer_name}! 😊 Su pedido está en camino a ${order.delivery_address}.`)}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="bg-orange-500 hover:bg-orange-600 text-white py-0.5 px-2 rounded flex items-center gap-1"
                  >
                    <Truck size={10} />En Camino
                  </a>
                )}
              </>
            )}
          </div>
        </div>

        <div className="bg-gray-50 rounded p-3 mb-3">
          {order.items && order.items.map(item => renderProductDetails(item))}
        </div>

        {renderDeliveryExtras(order)}

        {order.delivery_type === 'delivery' ? (
          <div className="text-xs bg-blue-50 border border-blue-200 rounded p-2 mb-2">
            <div className="flex items-center gap-2 text-blue-800">
              <Truck size={12} className="flex-shrink-0" />
              <span className="font-medium">Delivery</span>
              {!isScheduled && order.created_at && (
                <><Clock size={12} /><span>{new Date(new Date(order.created_at).getTime() - 3 * 60 * 60 * 1000).toLocaleTimeString('es-CL', {hour: '2-digit', minute: '2-digit'})}</span></>
              )}
              {order.delivery_address && <span className="font-semibold">• {order.delivery_address}</span>}
            </div>
          </div>
        ) : order.delivery_type === 'cuartel' ? (
          <div className="text-xs bg-green-50 border border-green-200 rounded p-2 mb-2">
            <div className="flex items-center gap-2 text-green-800">
              <span className="font-medium">🎖️ Retirado en Cuartel RL6</span>
              {!isScheduled && order.created_at && (
                <><Clock size={12} /><span>{new Date(new Date(order.created_at).getTime() - 3 * 60 * 60 * 1000).toLocaleTimeString('es-CL', {hour: '2-digit', minute: '2-digit'})}</span></>
              )}
            </div>
          </div>
        ) : (
          <div className="flex items-center gap-2 mb-2 text-xs">
            <Store size={14} className="text-green-600" />
            <span>Retiro</span>
            {!isScheduled && (
              <>
                {order.pickup_time ? (
                  <><Clock size={14} className="text-orange-600" /><span className="font-bold text-orange-600">{order.pickup_time.substring(0, 5)}</span></>
                ) : order.created_at && (
                  <><Clock size={14} className="text-gray-500" /><span>{new Date(new Date(order.created_at).getTime() - 3 * 60 * 60 * 1000).toLocaleTimeString('es-CL', {hour: '2-digit', minute: '2-digit'})}</span></>
                )}
              </>
            )}
          </div>
        )}

        <div className="mb-3 p-2 bg-white rounded">
          <div className="flex items-center justify-between mb-2">
            <div className="flex flex-col gap-1">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="text-xs text-gray-600">Subtotal: <span className="font-semibold text-gray-800">${parseInt(order.subtotal || 0).toLocaleString('es-CL')}</span></span>
                {order.delivery_type === 'delivery' && order.delivery_fee > 0 && (
                  <span className="text-xs text-orange-600">+ Delivery: <span className="font-semibold">${parseInt(order.delivery_fee - (order.delivery_discount || 0)).toLocaleString('es-CL')}</span></span>
                )}
                <span className="text-xs text-gray-400">→</span>
                <span className="text-xs text-gray-600">Total: <span className="font-bold text-green-600">${parseInt(order.installment_amount || 0).toLocaleString('es-CL')}</span></span>
              </div>
              <div className="flex items-center gap-1 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded w-fit">
                {getPaymentIcon(order.payment_method)}
                <span>{getPaymentText(order.payment_method)}</span>
              </div>
            </div>
            <div className={`flex items-center gap-1 text-xs px-2 py-1 rounded ${isPaid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
              {isPaid ? <CheckCircle size={14} /> : <XCircle size={14} />}
              <span>{isPaid ? 'Pagado' : 'Pendiente'}</span>
            </div>
          </div>
          {!isScheduled && (order.discount_amount > 0 || order.cashback_used > 0) && (
            <div className="text-xs space-y-1 pt-2 border-t border-gray-200">
              {order.discount_amount > 0 && (
                <div className="flex justify-between text-green-600">
                  <span>💰 Descuento:</span>
                  <span className="font-semibold">-${parseInt(order.discount_amount).toLocaleString('es-CL')}</span>
                </div>
              )}
              {order.cashback_used > 0 && (
                <div className="flex justify-between text-purple-600">
                  <span>🎁 Cashback:</span>
                  <span className="font-semibold">-${parseInt(order.cashback_used).toLocaleString('es-CL')}</span>
                </div>
              )}
            </div>
          )}
        </div>

        {order.customer_notes && (
          <div className="mb-3 bg-yellow-200 border border-yellow-400 rounded p-2">
            <div className="flex items-start gap-1">
              <MessageSquare size={14} className="mt-0.5 flex-shrink-0 text-black" />
              <span className="text-sm text-black font-bold">{order.customer_notes}</span>
            </div>
          </div>
        )}

        <div className="flex flex-col gap-2">
          <div className="flex gap-2">
            {!isPaid ? (
              <button onClick={() => confirmPayment(order.id, order.order_number, order.payment_method)} disabled={processing === order.id} className="flex-1 bg-gray-800 hover:bg-gray-900 disabled:bg-gray-400 text-white font-bold py-2 px-3 rounded text-xs">
                {processing === order.id ? '⏳' : '✓ CONFIRMAR PAGO'}
              </button>
            ) : (
              <button onClick={() => deliverOrder(order.id, order.order_number)} disabled={processing === order.id} className="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-2 px-3 rounded text-xs">
                {processing === order.id ? '⏳' : '✅ ENTREGAR'}
              </button>
            )}
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="fixed inset-0 bg-white z-40 flex flex-col overflow-hidden">
      <div className="bg-gradient-to-r from-orange-500 to-red-500 text-white px-6 py-4 shadow-lg flex items-center justify-between">
        <h2 className="text-xl font-bold flex items-center gap-2">
          📋 Comandas Activas
          {activeOrdersCount > 0 && (
            <span className="bg-white/20 px-3 py-1 rounded-full text-sm font-semibold">
              {activeOrdersCount}
            </span>
          )}
        </h2>
        <button 
          onClick={onClose}
          className="bg-white/20 hover:bg-white/30 p-2 rounded-full transition-colors"
        >
          <X size={24} />
        </button>
      </div>
      
      <div className="flex-1 overflow-y-auto">
        {loading ? (
          <div className="p-4 space-y-4">
            {[1, 2, 3].map(i => (
              <div key={i} className="bg-white border border-gray-200 rounded-lg p-4 animate-pulse">
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center gap-2">
                    <div className="w-6 h-6 bg-gray-200 rounded"></div>
                    <div className="w-24 h-5 bg-gray-200 rounded"></div>
                  </div>
                  <div className="w-16 h-8 bg-gray-200 rounded"></div>
                </div>
                <div className="w-32 h-4 bg-gray-200 rounded mb-3"></div>
                <div className="bg-gray-50 rounded p-3 mb-3">
                  <div className="w-full h-4 bg-gray-200 rounded mb-2"></div>
                  <div className="w-3/4 h-4 bg-gray-200 rounded"></div>
                </div>
                <div className="flex gap-2">
                  <div className="flex-1 h-10 bg-gray-200 rounded"></div>
                </div>
              </div>
            ))}
          </div>
        ) : activeOrders.length === 0 && checklists.length === 0 ? (
          <div className="p-8 text-center text-gray-500">
            <Package size={48} className="mx-auto mb-2 opacity-50" />
            <p>No hay pedidos activos ni checklists pendientes</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-200">
            {checklists.length > 0 && (
              <>
                <div className="sticky top-0 bg-blue-100 border-b-2 border-blue-400 px-4 py-2 z-10">
                  <div className="flex items-center gap-2 text-blue-800 font-bold text-sm">
                    📋 Checklists Pendientes ({checklists.length})
                  </div>
                </div>
                {checklists.map(checklist => (
                  <div key={checklist.id} className="p-4">
                    <ChecklistCard checklist={checklist} />
                  </div>
                ))}
              </>
            )}
            {scheduledOrders.length > 0 && (
              <>
                <div className="sticky top-0 bg-purple-100 border-b-2 border-purple-400 px-4 py-2 z-10">
                  <div className="flex items-center gap-2 text-purple-800 font-bold text-sm">
                    <Clock size={16} />
                    Pedidos Programados ({scheduledOrders.length})
                  </div>
                </div>
                {scheduledOrders.map(order => renderOrderCard(order, true))}
              </>
            )}
            {immediateOrders.length > 0 && (
              <>
                {scheduledOrders.length > 0 && (
                  <div className="sticky top-0 bg-orange-100 border-b-2 border-orange-400 px-4 py-2 z-10">
                    <div className="flex items-center gap-2 text-orange-800 font-bold text-sm">
                      <Package size={16} />
                      Pedidos Inmediatos ({immediateOrders.length})
                    </div>
                  </div>
                )}
                {immediateOrders.map(order => renderOrderCard(order, false))}
              </>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default MiniComandas;
