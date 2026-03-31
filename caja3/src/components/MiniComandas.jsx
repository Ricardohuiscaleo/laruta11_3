import React, { useState, useEffect } from 'react';
import { DollarSign, User, Package, Phone, MessageSquare, Copy, CreditCard, Banknote, Smartphone, Store, Truck, Clock, XCircle, CheckCircle, X, Send, Bike, Camera } from 'lucide-react';
import ChecklistCard from './ChecklistCard.jsx';

function MiniComandas({ onOrdersUpdate, onClose, activeOrdersCount }) {
  const [orders, setOrders] = useState([]);
  const [checklists, setChecklists] = useState([]);
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState(null);
  const [currentTime, setCurrentTime] = useState(Date.now());
  const [dispatchModal, setDispatchModal] = useState(null);
  const [checkedItems, setCheckedItems] = useState({});
  const [photoModal, setPhotoModal] = useState(null);
  const [viewingOrderPhotos, setViewingOrderPhotos] = useState(null); // { photos: [], currentIndex: 0 }
  const [showNewFeaturePopup, setShowNewFeaturePopup] = useState(() => {
    const today = new Date();
    const d = today.getDate(), m = today.getMonth() + 1, y = today.getFullYear();
    const isValidDay = y === 2026 && m === 2 && (d === 20 || d === 21);
    return isValidDay && !sessionStorage.getItem('dispatch_feature_seen');
  });

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

      const results = [];

      // Checklists normales
      if (shouldLoadApertura || shouldLoadCierre) {
        const types = [];
        if (shouldLoadApertura) types.push('apertura');
        if (shouldLoadCierre) types.push('cierre');

        for (const type of types) {
          const res = await fetch(`/api/checklist.php?action=get_active&type=${type}&date=${now.toISOString().split('T')[0]}`);
          const data = await res.json();
          if (data.success && data.checklist) {
            results.push(data.checklist);
          }
        }
      }

      // Recordatorio de basura (8:55 PM - 10:00 PM)
      const shouldShowTrash = currentTime >= 1255 && currentTime < 1320; // 20:55 - 22:00
      if (shouldShowTrash) {
        const dayOfWeek = now.getDay(); // 0=Domingo, 1=Lunes, etc.
        const location = [2, 4, 6].includes(dayOfWeek) ? 'Codpa' : 'Tucapel'; // Mar, Jue, Sab = Codpa

        // Verificar si ya fue marcado como listo hoy
        const today = now.toISOString().split('T')[0];
        const trashDone = localStorage.getItem(`trash_done_${today}`);

        if (!trashDone) {
          // Calcular tiempo restante hasta las 9:30 PM
          const targetTime = new Date(now);
          targetTime.setHours(21, 30, 0, 0);
          const diffMs = targetTime - now;
          const diffMins = Math.floor(diffMs / 60000);
          const hours = Math.floor(diffMins / 60);
          const mins = diffMins % 60;
          const countdown = hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;

          results.push({
            id: 'trash_reminder',
            type: 'trash',
            title: `🗑️ Botar la basura en ${location}`,
            description: `Entre 9:00 PM y 9:30 PM (quedan ${countdown})`,
            status: 'pending',
            location: location
          });
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
    if (minutes >= 60) {
      return { icon: '🚨', color: 'bg-red-100 border-red-500', textColor: 'text-red-800', status: 'MUY ATRASADO', statusColor: 'text-red-800' };
    } else if (minutes >= 30) {
      return { icon: '⚠️', color: 'bg-orange-100 border-orange-500', textColor: 'text-orange-800', status: 'ATRASADO', statusColor: 'text-orange-800' };
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

  const renderProductDetails = (item, orderId) => {
    const comboData = item.combo_data ? JSON.parse(item.combo_data) : null;
    const isCombo = item.item_type === 'combo' && comboData;
    const isChecked = !!checkedItems[`${orderId}-${item.id}`];
    const imageUrl = item.image_url || item.image || 'https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png';

    const toggleAll = (checked) => {
      const updates = { [`${orderId}-${item.id}`]: checked };

      if (isCombo) {
        if (comboData.fixed_items) {
          comboData.fixed_items.forEach((_, idx) => {
            updates[`${orderId}-fixed-${item.id}-${idx}`] = checked;
          });
        }
        if (comboData.selections) {
          Object.entries(comboData.selections).forEach(([group, selection]) => {
            const selectionsArray = Array.isArray(selection) ? selection : [selection];
            selectionsArray.forEach((_, sidx) => {
              updates[`${orderId}-sel-${item.id}-${group}-${sidx}`] = checked;
            });
          });
        }
      }

      if (comboData && comboData.customizations) {
        comboData.customizations.forEach((_, idx) => {
          updates[`${orderId}-cust-${item.id}-${idx}`] = checked;
        });
      }

      setCheckedItems(prev => ({ ...prev, ...updates }));
    };

    return (
      <div key={item.id} className="transition-all">
        <label className={`block cursor-pointer rounded-lg border-2 overflow-hidden transition-all ${
          isChecked 
            ? 'bg-green-50 border-green-400 opacity-60' 
            : 'bg-white border-gray-300 hover:border-orange-400 hover:shadow-md'
        }`}>
          <input
            type="checkbox"
            checked={isChecked}
            onChange={e => toggleAll(e.target.checked)}
            className="hidden"
          />
          <div className="relative aspect-square">
            <img 
              src={imageUrl} 
              alt={item.product_name} 
              className="w-full h-full object-cover" 
              onError={(e) => { e.target.src = 'https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png'; }}
            />
            {isChecked && (
              <div className="absolute inset-0 bg-green-600/80 flex items-center justify-center">
                <CheckCircle size={32} className="text-white" />
              </div>
            )}
          </div>
          <div className="p-1.5">
            <div className={`font-bold text-[10px] mb-0.5 line-clamp-2 ${isChecked ? 'line-through text-gray-500' : 'text-gray-800'}`}>
              {item.product_name}
            </div>
            <div className="flex items-center justify-between text-[9px]">
              <span className="text-gray-600">x<span className="font-bold">{item.quantity}</span></span>
              <span className="font-bold text-orange-600">${parseInt(item.product_price || item.price || 0).toLocaleString('es-CL')}</span>
            </div>
          </div>
        </label>

        {/* Detalles de combo y extras */}
        {isCombo && (
          <div className="mt-1 text-[9px] space-y-1">
            {comboData.fixed_items && comboData.fixed_items.length > 0 && (
              <div className="bg-blue-50 rounded p-1 border border-blue-200">
                <div className="font-bold text-blue-700 mb-0.5">Incluye:</div>
                {comboData.fixed_items.map((fixed, idx) => {
                  const itemKey = `${orderId}-fixed-${item.id}-${idx}`;
                  return (
                    <label key={idx} className="flex items-center gap-1 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={!!checkedItems[itemKey]}
                        onChange={e => setCheckedItems(prev => ({ ...prev, [itemKey]: e.target.checked }))}
                        className="w-2.5 h-2.5 accent-blue-500"
                      />
                      <span className={checkedItems[itemKey] ? 'line-through text-gray-400' : 'text-gray-700'}>
                        {item.quantity * (fixed.quantity || 1)}x {fixed.product_name || fixed.name}
                      </span>
                    </label>
                  );
                })}
              </div>
            )}
            {comboData.selections && Object.keys(comboData.selections).length > 0 && (
              <div className="bg-purple-50 rounded p-1 border border-purple-200">
                <div className="font-bold text-purple-700 mb-0.5">Seleccionado:</div>
                {Object.entries(comboData.selections).map(([group, selection], idx) => {
                  const selectionsArray = Array.isArray(selection) ? selection : [selection];
                  return selectionsArray.map((sel, sidx) => {
                    if (!sel || !sel.name) return null;
                    const itemKey = `${orderId}-sel-${item.id}-${group}-${sidx}`;
                    const selImageUrl = sel.image_url || sel.image || 'https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png';
                    return (
                      <label key={`${idx}-${sidx}`} className="flex items-center gap-1 cursor-pointer mb-0.5">
                        <input
                          type="checkbox"
                          checked={!!checkedItems[itemKey]}
                          onChange={e => setCheckedItems(prev => ({ ...prev, [itemKey]: e.target.checked }))}
                          className="w-2.5 h-2.5 accent-purple-500 flex-shrink-0"
                        />
                        <img 
                          src={selImageUrl} 
                          alt={sel.name} 
                          className="w-6 h-6 object-cover rounded border border-purple-300" 
                          onError={(e) => { e.target.src = 'https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png'; }}
                        />
                        <span className={`text-[9px] ${checkedItems[itemKey] ? 'line-through text-gray-400' : 'text-gray-700'}`}>
                          {item.quantity}x {sel.name}
                        </span>
                      </label>
                    );
                  });
                })}
              </div>
            )}
          </div>
        )}

        {comboData && comboData.customizations && comboData.customizations.length > 0 && (
          <div className="mt-1 bg-orange-50 rounded p-1 border border-orange-300">
            <div className="text-[9px] font-bold text-orange-700 mb-0.5">❗ Extras:</div>
            {comboData.customizations.map((custom, idx) => {
              const itemKey = `${orderId}-cust-${item.id}-${idx}`;
              return (
                <label key={idx} className="flex items-center gap-1 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={!!checkedItems[itemKey]}
                    onChange={e => setCheckedItems(prev => ({ ...prev, [itemKey]: e.target.checked }))}
                    className="w-2.5 h-2.5 accent-orange-600"
                  />
                  <span className={`text-[9px] font-bold ${checkedItems[itemKey] ? 'line-through text-orange-300' : 'text-orange-800'}`}>
                    {custom.quantity || item.quantity}x {custom.name}
                  </span>
                </label>
              );
            })}
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
    switch (method) {
      case 'card': return <CreditCard size={14} />;
      case 'cash': return <Banknote size={14} />;
      case 'transfer': return <Smartphone size={14} />;
      case 'pedidosya': return <Package size={14} />;
      default: return <DollarSign size={14} />;
    }
  };

  const getPaymentText = (method) => {
    switch (method) {
      case 'card': return 'Tarjeta';
      case 'cash': return 'Efectivo';
      case 'transfer': return 'Transferencia';
      case 'pedidosya': return 'PedidosYA';
      default: return method;
    }
  };

  const activeOrders = orders.filter(o =>
    o.order_status !== 'delivered' &&
    o.order_status !== 'cancelled' &&
    !o.order_number.startsWith('RL6-') // Ocultar pagos de crédito RL6
  );
  const activeChecklists = checklists.filter(c => c.status !== 'completed' && c.status !== 'missed');
  const immediateOrders = activeOrders.filter(o => !isScheduledOrder(o));
  const scheduledOrders = activeOrders.filter(o => isScheduledOrder(o));

  const renderOrderCard = (order, isScheduled = false) => {
    // Validar pago: para webpay/credit debe tener tuu_message === "Transaccion aprobada"
    const isPaid = order.payment_status === 'paid' &&
      (order.payment_method === 'webpay' || order.payment_method === 'credit'
        ? order.tuu_message === 'Transaccion aprobada'
        : true);

    // Detectar si el callback de TUU falló
    const tuuCallbackFailed = (order.payment_method === 'webpay' || order.payment_method === 'credit') &&
      order.payment_status === 'paid' &&
      order.tuu_message !== 'Transaccion aprobada';
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
          <div className="grid grid-cols-3 gap-2">
            {order.items && order.items.map(item => renderProductDetails(item, order.id))}
          </div>
        </div>

        {renderDeliveryExtras(order)}

        {order.delivery_type === 'delivery' ? (
          <div className="space-y-2">
            <div className="text-xs bg-blue-50 border border-blue-200 rounded p-2">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-blue-800">
                  <Bike size={12} className="flex-shrink-0" />
                  <span className="font-medium">Delivery</span>
                  {order.pickup_time ? (
                    <div className="flex items-center gap-1 bg-red-100 px-2 py-0.5 rounded border border-red-300">
                       <Clock size={14} className="text-red-600" />
                       <span className="font-bold text-red-600 text-[10px]">PROGRAMADO A LAS: {order.pickup_time.substring(0, 5)}</span>
                    </div>
                  ) : !isScheduled && order.created_at && (
                    <><Clock size={12} /><span>{new Date(new Date(order.created_at).getTime() - 3 * 60 * 60 * 1000).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })}</span></>
                  )}
                  {order.delivery_address && <span className="font-semibold text-blue-900">• {order.delivery_address}</span>}
                </div>
              </div>
            </div>
            {order.delivery_address && (
              <div className="flex items-center justify-between bg-white border border-gray-200 rounded p-2">
                <span className="text-xs font-bold text-gray-800">Envía el pedido al delivery 😎👉🏻</span>
                <button
                  onClick={() => {
                    // Normalizar direcciones pre-establecidas
                    let normalizedAddress = order.delivery_address;
                    if (normalizedAddress.toLowerCase().includes('ctel.')) {
                      if (normalizedAddress.toLowerCase().includes('domeyco')) {
                        normalizedAddress = 'Domeyco 1540, Arica, Chile';
                      } else if (normalizedAddress.toLowerCase().includes('oscar quina')) {
                        normalizedAddress = 'Oscar Quina 1333, Arica, Chile';
                      } else if (normalizedAddress.toLowerCase().includes('santa maría') || normalizedAddress.toLowerCase().includes('santa maria')) {
                        normalizedAddress = 'Av. Santa María 3000, Arica, Chile';
                      }
                    } else {
                      // Para direcciones normales, agregar ", Arica, Chile" si no lo tiene
                      if (!normalizedAddress.toLowerCase().includes('arica') && !normalizedAddress.toLowerCase().includes('chile')) {
                        normalizedAddress = `${normalizedAddress}, Arica, Chile`;
                      }
                    }

                    const address = encodeURIComponent(normalizedAddress);
                    const mapsUrl = `https://www.google.com/maps/search/?api=1&query=${address}`;
                    // Calcular delivery fee real (con descuento aplicado)
                    const deliveryFeeReal = parseInt(order.delivery_fee) - parseInt(order.delivery_discount || 0);
                    const subtotal = parseInt(order.subtotal || 0).toLocaleString('es-CL');
                    const deliveryFee = deliveryFeeReal.toLocaleString('es-CL');
                    const total = parseInt(order.installment_amount || 0).toLocaleString('es-CL');

                    // Construir lista de productos desde items array
                    const items = order.items?.map(item => {
                      const isCombo = item.item_type === 'combo' && item.combo_data;
                      let itemText = `${item.quantity}x ${item.product_name}`;

                      if (isCombo) {
                        try {
                          const comboData = JSON.parse(item.combo_data);
                          if (comboData.fixed_items) {
                            itemText += '\n  Incluye:';
                            comboData.fixed_items.forEach(fixed => {
                              itemText += `\n  • ${item.quantity}x ${fixed.product_name}`;
                            });
                          }
                          if (comboData.selections) {
                            Object.entries(comboData.selections).forEach(([group, selection]) => {
                              if (selection && selection.name) {
                                itemText += `\n  • ${item.quantity}x ${selection.name}`;
                              }
                            });
                          }
                        } catch (e) {
                          // Si falla el parsing, usar solo el nombre del producto
                        }
                      }

                      return itemText;
                    }).join('\n\n') || order.product_name;

                    // Determinar instrucción de pago
                    let paymentInstruction = '';
                    const isPaid = order.payment_status === 'paid';
                    if (isPaid) {
                      paymentInstruction = '✅ *YA ESTÁ PAGADO*';
                    } else {
                      switch (order.payment_method) {
                        case 'card':
                          paymentInstruction = '💳 *Tarjeta* - No olvides llevar máquina de pagos';
                          break;
                        case 'cash':
                          paymentInstruction = '💵 *Efectivo* - No olvides llevar vuelto';
                          break;
                        case 'webpay':
                          paymentInstruction = '💻 *Webpay* - Ya está pagado';
                          break;
                        case 'credit':
                          paymentInstruction = '🏦 *Crédito* - Ya está pagado';
                          break;
                        case 'transfer':
                          paymentInstruction = '🔄 *Transferencia* - Preguntar en caja si está pagado';
                          break;
                        default:
                          paymentInstruction = `💰 *${order.payment_method}*`;
                      }
                    }

                    const message = `> 🚚 *Pedido ${order.order_number}*\n\n*👤 Cliente:*\n- *Nombre:* ${order.customer_name}\n- *Teléfono:* ${order.customer_phone || 'No disponible'}\n\n*📦 Productos:*\n${items}\n\n*💰 Montos:*\n- Subtotal: $${subtotal}\n- Delivery: $${deliveryFee}\n\n> *💰 TOTAL: $${total}*\n\n${paymentInstruction}\n\n*📍 Dirección:*\n> ${order.delivery_address}\n\n🗺️ Ver en mapa:\n${mapsUrl}`;

                    // Debug: mostrar el mensaje completo antes de enviarlo
                    console.log('=== MENSAJE COMPLETO PARA RIDER ===');
                    console.log(message);
                    console.log('=== LONGITUD DEL MENSAJE ===');
                    console.log(message.length);

                    window.location.href = `whatsapp://send?text=${encodeURIComponent(message)}`;
                  }}
                  className="flex items-center gap-1 bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded text-xs font-medium transition-colors"
                >
                  <Send size={12} />
                  Rider
                </button>
              </div>
            )}
          </div>
        ) : order.delivery_type === 'cuartel' ? (
          <div className="text-xs bg-green-50 border border-green-200 rounded p-2 mb-2">
            <div className="flex items-center gap-2 text-green-800">
              <span className="font-medium">🎖️ Retirado en Cuartel RL6</span>
              {!isScheduled && order.created_at && (
                <><Clock size={12} /><span>{new Date(new Date(order.created_at).getTime() - 3 * 60 * 60 * 1000).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })}</span></>
              )}
            </div>
          </div>
        ) : (
          <div className="flex items-center gap-2 mb-2 text-xs p-1.5 bg-orange-50 rounded border border-orange-200">
            {order.pickup_time ? (
              <>
                <Clock size={16} className="text-red-600" />
                <span className="font-bold text-red-600 text-sm">
                  PARA RETIRO A LAS: {order.pickup_time.substring(0, 5)}
                </span>
                <span className="bg-red-600 text-white px-1.5 py-0.5 rounded text-[10px]">¡ATENCIÓN!</span>
              </>
            ) : (
              <>
                <Store size={14} className="text-green-600" />
                <span>Retiro Inmediato</span>
                {order.created_at && (
                  <><Clock size={14} className="text-gray-500" /><span>Recibido {new Date(new Date(order.created_at).getTime() - 3 * 60 * 60 * 1000).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })}</span></>
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
                  <div className="flex flex-col gap-0.5">
                    <span className="text-xs text-orange-600 flex items-center gap-1">+ <Bike size={12} /> <span className="font-semibold">${parseInt(order.delivery_fee - (order.delivery_discount || 0)).toLocaleString('es-CL')}</span></span>
                    {order.delivery_extras_items && (
                      <div className="flex flex-col gap-0.5 pl-4 border-l border-orange-200">
                        {(() => {
                          try {
                            const extras = JSON.parse(order.delivery_extras_items);
                            return Array.isArray(extras) ? extras.map((ex, i) => (
                              <span key={i} className="text-[9px] text-orange-700 italic flex items-center gap-1">• {ex.name} (+${parseInt(ex.price).toLocaleString('es-CL')})</span>
                            )) : null;
                          } catch (e) { return null; }
                        })()}
                      </div>
                    )}
                  </div>
                )}
                {order.discount_10 > 0 && (
                  <span className="text-xs text-green-600 flex items-center gap-1">- ⭐10% descuento <span className="font-semibold">${parseInt(order.discount_10).toLocaleString('es-CL')}</span></span>
                )}
                {order.discount_30 > 0 && (
                  <span className="text-xs text-yellow-600 flex items-center gap-1">- ⭐30% descuento <span className="font-semibold">${parseInt(order.discount_30).toLocaleString('es-CL')}</span></span>
                )}
                {order.discount_birthday > 0 && (
                  <span className="text-xs text-pink-600 flex items-center gap-1">- 🎂 Cumpleaños <span className="font-semibold">${parseInt(order.discount_birthday).toLocaleString('es-CL')}</span></span>
                )}
                {order.discount_pizza > 0 && (
                  <span className="text-xs text-purple-600 flex items-center gap-1">- 🍕 Pizza <span className="font-semibold">${parseInt(order.discount_pizza).toLocaleString('es-CL')}</span></span>
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
          {tuuCallbackFailed && (
            <div className="mt-2 bg-white border-2 border-red-500 p-2 rounded">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className="text-red-600 font-bold text-sm">❌</span>
                  <span className="text-xs text-red-800 font-bold">Pago online falló contacta al cliente 👉🏻</span>
                </div>
                <button
                  onClick={() => {
                    const message = `Hola ${order.customer_name}, somos *La Ruta 11 Food Truck* 🍔\n\nTe contactamos porque tu pago online del pedido *${order.order_number}* no fue procesado por Transbank.\n\n❌ *Tu pedido NO ha sido cobrado*\n\nPara continuar con tu pedido, puedes pagar con:\n\n💳 *Transferencia bancaria:*\n> Titular: La Ruta once Spa\n> RUT: 78.194.739-3\n> Banco: Banco BCI\n> Cuenta Corriente: 97618110\n> Email: SABORESDELARUTA11@GMAIL.COM\n> *Monto: $${parseInt(order.installment_amount || 0).toLocaleString('es-CL')}*\n\n_Otras opciones de pago:_\n- 💵 Efectivo al recibir\n- 💳 Tarjeta al recibir\n\n_¡Disculpa las molestias!_ 🙏`;
                    navigator.clipboard.writeText(message);
                    alert('✓ Mensaje copiado al portapapeles');
                  }}
                  className="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs font-medium flex items-center gap-1"
                >
                  <Copy size={12} />
                  Copiar mensaje
                </button>
              </div>
            </div>
          )}
          {!isScheduled && (order.discount_10 > 0 || order.discount_30 > 0 || order.discount_birthday > 0 || order.discount_pizza > 0 || order.discount_amount > 0 || order.cashback_used > 0) && (
            <div className="text-xs space-y-1 pt-2 border-t border-gray-200">
              {order.discount_10 > 0 && (
                <div className="flex justify-between text-green-600">
                  <span>⭐ Descuento 10%:</span>
                  <span className="font-semibold">-${parseInt(order.discount_10).toLocaleString('es-CL')}</span>
                </div>
              )}
              {order.discount_30 > 0 && (
                <div className="flex justify-between text-yellow-600">
                  <span>⭐ Descuento 30%:</span>
                  <span className="font-semibold">-${parseInt(order.discount_30).toLocaleString('es-CL')}</span>
                </div>
              )}
              {order.discount_birthday > 0 && (
                <div className="flex justify-between text-pink-600">
                  <span>🎂 Descuento Cumpleaños:</span>
                  <span className="font-semibold">-${parseInt(order.discount_birthday).toLocaleString('es-CL')}</span>
                </div>
              )}
              {order.discount_pizza > 0 && (
                <div className="flex justify-between text-orange-600">
                  <span>🍕 Descuento Pizza:</span>
                  <span className="font-semibold">-${parseInt(order.discount_pizza).toLocaleString('es-CL')}</span>
                </div>
              )}
              {order.discount_amount > 0 && !order.discount_10 && !order.discount_30 && !order.discount_birthday && !order.discount_pizza && (
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

        <div className="mb-2 border border-blue-200 rounded-lg overflow-hidden bg-white shadow-sm">
          <div className="bg-blue-600 px-2 py-1 text-[10px] font-black text-white flex items-center justify-between">
            <span className="flex items-center gap-1">
              <Camera size={12} /> FOTOS DEL PEDIDO
            </span>
          </div>

          <div className="p-1.5">
            <div className="grid grid-cols-3 gap-1.5">
              {(() => {
                let photos = [];
                try {
                  const url = order.dispatch_photo_url;
                  if (url) {
                    const decoded = JSON.parse(url);
                    photos = Array.isArray(decoded) ? decoded : [url];
                  }
                } catch (e) {
                  photos = [order.dispatch_photo_url];
                }

                return photos.map((url, idx) => (
                  <div
                    key={idx}
                    className="aspect-square rounded-md overflow-hidden border border-gray-100 relative group cursor-pointer shadow-sm active:scale-95 transition-all"
                    onClick={() => setViewingOrderPhotos({ photos, currentIndex: idx })}
                  >
                    <img src={url} alt={`foto-${idx}`} className="w-full h-full object-cover" />
                    <div className="absolute inset-0 bg-black/5 group-hover:bg-black/0 transition-colors" />
                  </div>
                ));
              })()}

              <label className="aspect-square flex flex-col items-center justify-center gap-0.5 cursor-pointer rounded-md border-2 border-dashed border-gray-300 bg-gray-50 hover:bg-orange-50 hover:border-orange-300 text-gray-400 hover:text-orange-500 transition-all active:scale-95">
                <Camera size={18} />
                <span className="text-[9px] font-black uppercase">Subir</span>
                <input type="file" accept="image/*" className="hidden"
                  onChange={e => {
                    const f = e.target.files[0];
                    if (!f) return;
                    const fd = new FormData();
                    fd.append('photo', f);
                    fd.append('order_id', order.id);
                    fetch('/api/orders/save_dispatch_photo.php', { method: 'POST', body: fd })
                      .then(r => r.json())
                      .then(d => { if (d.success) loadOrders(); else alert('Error: ' + d.error); })
                      .catch(() => alert('Error al subir foto'));
                  }} />
              </label>
            </div>
          </div>
        </div>

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
      {/* Visor de Fotos Pro */}
      {viewingOrderPhotos && (
        <div className="fixed inset-0 bg-black/95 z-[60] flex flex-col items-center justify-center p-4 select-none" onClick={() => setViewingOrderPhotos(null)}>
          <div className="absolute top-4 right-4 z-10 flex gap-2">
            <button className="bg-white/20 hover:bg-white/40 text-white p-3 rounded-full backdrop-blur-lg transition-all active:scale-90">
              <X size={28} />
            </button>
          </div>

          <div className="w-full h-[70vh] flex items-center justify-center relative px-2" onClick={e => e.stopPropagation()}>
            {viewingOrderPhotos.photos.length > 1 && (
              <button
                onClick={() => setViewingOrderPhotos(prev => ({ ...prev, currentIndex: (prev.currentIndex - 1 + prev.photos.length) % prev.photos.length }))}
                className="absolute left-2 bg-white/10 hover:bg-white/20 text-white p-5 rounded-full z-10 transition-colors"
              >
                <X size={24} className="rotate-45" /> {/* Generic arrow-like or use a real icon if preferred, but X is already imported. I'll use text or Chevron if available */}
                <span className="text-2xl font-black">{"<"}</span>
              </button>
            )}

            <img
              src={viewingOrderPhotos.photos[viewingOrderPhotos.currentIndex]}
              alt="full photo"
              className="max-w-full max-h-full object-contain rounded-xl shadow-[0_0_50px_rgba(0,0,0,0.5)] border border-white/10"
            />

            {viewingOrderPhotos.photos.length > 1 && (
              <button
                onClick={() => setViewingOrderPhotos(prev => ({ ...prev, currentIndex: (prev.currentIndex + 1) % prev.photos.length }))}
                className="absolute right-2 bg-white/10 hover:bg-white/20 text-white p-5 rounded-full z-10 transition-colors"
              >
                <span className="text-2xl font-black">{">"}</span>
              </button>
            )}
          </div>

          <div className="mt-8 flex flex-col items-center gap-3">
            <div className="text-white/90 font-black text-xs uppercase tracking-[0.2em] bg-white/10 px-6 py-2 rounded-full border border-white/5 backdrop-blur-md">
              Archivo {viewingOrderPhotos.currentIndex + 1} de {viewingOrderPhotos.photos.length}
            </div>
            <div className="flex gap-1.5">
              {viewingOrderPhotos.photos.map((_, i) => (
                <div key={i} className={`h-1 rounded-full transition-all duration-300 ${i === viewingOrderPhotos.currentIndex ? 'w-8 bg-orange-500' : 'w-2 bg-white/20'}`} />
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Visor Legacy (para compatibilidad) */}
      {photoModal && (
        <div className="fixed inset-0 bg-black/90 z-[60] flex items-center justify-center p-4" onClick={() => setPhotoModal(null)}>
          <div className="max-w-4xl w-full relative">
            <button className="absolute -top-12 right-0 text-white" onClick={() => setPhotoModal(null)}>
              <X size={32} />
            </button>
            <img src={photoModal} alt="Despacho" className="w-full h-auto rounded-lg shadow-2xl" />
          </div>
        </div>
      )}
      <div className="bg-gradient-to-r from-orange-500 to-red-500 text-white px-6 py-4 shadow-lg flex items-center justify-between" style={{ paddingTop: 'max(1rem, env(safe-area-inset-top))' }}>
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
}

export default MiniComandas;
