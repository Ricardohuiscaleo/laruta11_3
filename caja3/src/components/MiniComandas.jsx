import React, { useState, useEffect } from 'react';
import { DollarSign, User, Package, Phone, MessageSquare, Copy, CreditCard, Banknote, Smartphone, Store, Truck, Clock, XCircle, CheckCircle, X, Send, Bike, Camera, List, LayoutGrid, Trash2, AlertTriangle, Loader2, ImagePlus, ShieldCheck, ShieldAlert, ChevronDown, ChevronUp, MapPin } from 'lucide-react';
import ChecklistCard from './ChecklistCard.jsx';
import { generatePhotoRequirements, getButtonState, formatPhotoProgress } from '../utils/photoRequirements.js';

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
  const [cashModalOrder, setCashModalOrder] = useState(null);
  const [cashAmount, setCashAmount] = useState('');
  const [cashStep, setCashStep] = useState('input');
  const [photoSlots, setPhotoSlots] = useState({}); // keyed by order ID → {productos: {status, photoUrl, verification}, bolsa: {status, photoUrl, verification}}
  const [viewMode, setViewMode] = useState('list');
  const [riderMonitor, setRiderMonitor] = useState(null); // order.id of expanded rider monitor
  const [packagingQty, setPackagingQty] = useState({}); // { [orderId]: { bolsa_grande: number, bolsa_mediana: number } }
  const [bagInfoModal, setBagInfoModal] = useState(null); // { label, img, description }
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
        const orders = data.orders || [];
        setOrders(orders);
        
        // Restore photoSlots from dispatch_photo_url for delivery orders
        setPhotoSlots(prev => {
          const restored = { ...prev };
          orders.forEach(order => {
            if (order.delivery_type === 'delivery' && order.dispatch_photo_url && !restored[order.id]) {
              try {
                const urls = JSON.parse(order.dispatch_photo_url);
                if (urls && typeof urls === 'object') {
                  const slots = {};
                  ['productos', 'bolsa'].forEach(type => {
                    const entry = urls[type];
                    if (!entry) return;
                    // New format: {url, verification} or legacy string
                    const photoUrl = typeof entry === 'string' ? entry : entry.url;
                    const verification = (typeof entry === 'object' && entry.verification) ? entry.verification : null;
                    const p = verification?.puntaje ?? 100;
                    slots[type] = { status: p >= 80 ? 'approved' : 'warning', photoUrl, verification };
                  });
                  if (Object.keys(slots).length > 0) restored[order.id] = slots;
                }
              } catch (e) {
                // Legacy single URL string
                if (typeof order.dispatch_photo_url === 'string' && order.dispatch_photo_url.startsWith('http')) {
                  restored[order.id] = { productos: { status: 'approved', photoUrl: order.dispatch_photo_url, verification: null } };
                }
              }
            }
          });
          return restored;
        });
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
    if (!createdAt) return 0;
    const created = new Date(createdAt.replace(' ', 'T') + 'Z');
    if (isNaN(created.getTime())) return 0;
    const diffMs = currentTime - created.getTime();
    const totalSeconds = Math.floor(diffMs / 1000);
    return Math.max(0, totalSeconds);
  };

  const formatTime = (seconds) => {
    if (seconds < 60) return `${seconds}s`;
    const totalMins = Math.floor(seconds / 60);
    if (totalMins >= 60) {
      const hours = Math.floor(totalMins / 60);
      const mins = totalMins % 60;
      return `${hours}h ${mins}m`;
    }
    const secs = seconds % 60;
    return `${totalMins}:${secs.toString().padStart(2, '0')}`;
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

  const formatCurrency = (value) => {
    const numbers = value.replace(/\D/g, '');
    return numbers.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  };

  const handleCashInput = (e) => {
    const formatted = formatCurrency(e.target.value);
    setCashAmount(formatted);
  };

  const setCashExactAmount = () => {
    if (!cashModalOrder) return;
    setCashAmount(cashModalOrder.total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
  };

  const setCashQuickAmount = (amount) => {
    setCashAmount(amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
  };

  const handleContinueCash = () => {
    const numericAmount = parseInt(cashAmount.replace(/\./g, ''));
    if (!numericAmount || numericAmount === 0) {
      alert('⚠️ Debe ingresar un monto o seleccionar "Monto Exacto"');
      return;
    }
    if (numericAmount < cashModalOrder.total) {
      const faltante = cashModalOrder.total - numericAmount;
      alert(`⚠️ Monto insuficiente. Faltan $${faltante.toLocaleString('es-CL')}`);
      return;
    }
    if (numericAmount === cashModalOrder.total) {
      processCashPayment();
    } else {
      setCashStep('confirm');
    }
  };

  const processCashPayment = async () => {
    if (!cashModalOrder) return;
    setProcessing(cashModalOrder.id);
    try {
      const response = await fetch('/api/confirm_transfer_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: cashModalOrder.id })
      });
      const text = await response.text();
      if (text.trim().startsWith('<')) {
        alert('❌ Error del servidor.');
        return;
      }
      const result = JSON.parse(text);
      if (result.success) {
        closeCashModal();
        await loadOrders();
      } else {
        alert('Error: ' + (result.error || 'No se pudo confirmar el pago'));
      }
    } catch (error) {
      alert('Error al confirmar el pago');
    } finally {
      setProcessing(null);
    }
  };

  const closeCashModal = () => {
    setCashModalOrder(null);
    setCashAmount('');
    setCashStep('input');
  };

  const confirmPayment = async (orderId, orderNumber, paymentMethod) => {
    if (paymentMethod === 'pedidosya_cash') {
      const order = orders.find(o => o.id === orderId);
      setCashModalOrder({
        id: orderId,
        orderNumber,
        total: parseInt(order.installment_amount || 0)
      });
      setCashAmount('');
      setCashStep('input');
      return;
    }

    const methodName = {
      cash: 'efectivo',
      card: 'tarjeta',
      transfer: 'transferencia',
      pedidosya: 'PedidosYA',
      pedidosya_cash: 'PedidosYA Efectivo'
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
        alert('❌ Error del servidor.');
        return;
      }

      const result = JSON.parse(text);
      if (result.success) {
        await loadOrders();
      } else {
        alert('Error: ' + (result.error || 'No se pudo confirmar el pago'));
      }
    } catch (error) {
      alert('Error al confirmar el pago');
    } finally {
      setProcessing(null);
    }
  };

  const deliverOrder = async (orderId, orderNumber) => {
    if (!confirm(`¿Marcar pedido ${orderNumber} como entregado?`)) return;

    setProcessing(orderId);
    try {
      // Register packaging for pickup orders (delivery registers at dispatch phase)
      const order = orders.find(o => o.id === orderId);
      if (order && order.delivery_type !== 'delivery') {
        await registerPackaging(orderNumber, orderId, order.delivery_type);
      }

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

  const dispatchToDelivery = async (orderId, orderNumber) => {
    if (!confirm(`¿Despachar pedido ${orderNumber} al rider?`)) return;

    setProcessing(orderId);
    try {
      // Register packaging consumption for delivery orders
      await registerPackaging(orderNumber, orderId, 'delivery');

      const response = await fetch('/api/tuu/update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, order_status: 'ready' })
      });

      const result = await response.json();
      if (result.success) {
        await loadOrders();
      } else {
        alert('Error: ' + (result.error || 'No se pudo despachar el pedido'));
      }
    } catch (error) {
      alert('Error al despachar el pedido');
    } finally {
      setProcessing(null);
    }
  };

  const cancelOrder = async (orderId, orderNumber) => {
    const order = orders.find(o => o.id === orderId);
    const isRL6 = order?.payment_method === 'rl6_credit';
    const isR11 = order?.payment_method === 'r11_credit';

    const confirmMsg = isRL6
      ? `⚠️ ¿ANULAR el pedido ${orderNumber}?\n\n✅ Se reintegrará el crédito RL6 automáticamente.\n\nEsta acción no se puede deshacer.`
      : isR11
        ? `⚠️ ¿ANULAR el pedido ${orderNumber}?\n\n✅ Se reintegrará el crédito R11 automáticamente.\n\nEsta acción no se puede deshacer.`
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
      } else if (isR11) {
        // Para pedidos R11, llamar a la API de reintegro R11
        const refundResponse = await fetch('/api/r11_refund_credit.php', {
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
          alert('✅ Pedido anulado y crédito R11 reintegrado');
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
        <div className="mb-1 bg-orange-50 border border-orange-200 rounded p-2">
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
      case 'pedidosya_cash': return <Banknote size={14} />;
      default: return <DollarSign size={14} />;
    }
  };

  const getPaymentText = (method) => {
    switch (method) {
      case 'card': return 'Tarjeta';
      case 'cash': return 'Efectivo';
      case 'transfer': return 'Transferencia';
      case 'pedidosya': return 'PedidosYA';
      case 'pedidosya_cash': return 'PedidosYA Efectivo';
      case 'rl6_credit': return 'Crédito RL6';
      case 'r11_credit': return 'Crédito R11';
      default: return method;
    }
  };

  // ── Packaging helpers ──
  const getPackaging = (orderId, deliveryType) => {
    if (packagingQty[orderId]) return packagingQty[orderId];
    return { bolsa_grande: deliveryType === 'delivery' ? 1 : 0, bolsa_mediana: 0 };
  };

  const setPackagingValue = (orderId, bagType, delta, deliveryType) => {
    setPackagingQty(prev => {
      const current = prev[orderId] || getPackaging(orderId, deliveryType);
      const newVal = Math.max(0, Math.min(10, current[bagType] + delta));
      return { ...prev, [orderId]: { ...current, [bagType]: newVal } };
    });
  };

  const registerPackaging = async (orderNumber, orderId, deliveryType) => {
    const qty = getPackaging(orderId, deliveryType);
    if (qty.bolsa_grande === 0 && qty.bolsa_mediana === 0) return true;
    try {
      const response = await fetch('/api/register_packaging_consumption.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_number: orderNumber, bolsa_grande: qty.bolsa_grande, bolsa_mediana: qty.bolsa_mediana })
      });
      const result = await response.json();
      if (!result.success) {
        console.warn('Packaging registration failed:', result.error);
      }
      return true;
    } catch (error) {
      console.error('Error registrando packaging:', error);
      alert('⚠️ No se pudo registrar el consumo de bolsas. El pedido se procesará de todas formas.');
      return false;
    }
  };

  const activeOrders = orders.filter(o =>
    o.order_status !== 'delivered' &&
    o.order_status !== 'cancelled' &&
    !o.order_number.startsWith('RL6-') // Ocultar pagos de crédito RL6 (no son pedidos de comida)
  );
  const activeChecklists = checklists.filter(c => c.status !== 'completed' && c.status !== 'missed');
  const immediateOrders = activeOrders.filter(o => !isScheduledOrder(o));
  const scheduledOrders = activeOrders.filter(o => isScheduledOrder(o));

  const abbreviateName = (name) => {
    if (!name) return '';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0];
    return `${parts[0]} ${parts[1][0]}.`;
  };

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
      <div key={order.id} className={`p-1 ${isScheduled ? 'bg-purple-50 border-l-4 border-purple-500' : `${timeAlert.color} border-l-4`}`}>
        <div className="mb-1 pb-1 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-1 text-xs flex-1 min-w-0">
              <span className="font-bold text-gray-900 text-xs truncate">{abbreviateName(order.customer_name)}</span>
              {!isScheduled && (
                <>
                  <span className="text-gray-400">·</span>
                  <span className={`font-mono text-xs ${timeAlert.textColor}`}>{formatTime(seconds)}</span>
                </>
              )}
              {isScheduled && scheduledTimeDisplay && (
                <>
                  <span className="text-gray-400">·</span>
                  <span className="font-bold text-purple-700 text-xs">🕐 {scheduledTimeDisplay}</span>
                </>
              )}
              <button
                onClick={(e) => { e.stopPropagation(); setViewMode(v => v === 'card' ? 'list' : 'card'); }}
                className="text-gray-500 hover:text-gray-700 p-0.5 rounded"
                title={viewMode === 'card' ? 'Ver listado' : 'Ver tarjetas'}
              >
                {viewMode === 'card' ? <List size={14} /> : <LayoutGrid size={14} />}
              </button>
            </div>
            <button
              onClick={() => cancelOrder(order.id, order.order_number)}
              disabled={processing === order.id}
              className="bg-red-500 hover:bg-red-600 disabled:bg-gray-400 text-white font-bold py-0.5 px-1.5 rounded text-[10px] flex-shrink-0"
            >
              ANULAR
            </button>
          </div>
        </div>

        <div className="mb-1">
          <div className="flex items-center gap-1 text-xs flex-wrap">
            {order.customer_phone && (
              <>
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

        {viewMode === 'list' ? (
          <div className="bg-gray-50 rounded p-1 mb-1 space-y-0.5">
            {order.items && order.items.map(item => {
              const comboData = item.combo_data ? JSON.parse(item.combo_data) : null;
              const isCombo = item.item_type === 'combo' && comboData;
              const isChecked = !!checkedItems[`${order.id}-${item.id}`];
              const imageUrl = item.image_url || item.image || 'https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png';
              return (
                <div key={item.id}>
                  <label className={`flex items-center gap-2 px-1 py-1 rounded cursor-pointer ${isChecked ? 'bg-green-50 opacity-60' : 'bg-white'}`}>
                    <input type="checkbox" checked={isChecked} onChange={e => {
                      const updates = { [`${order.id}-${item.id}`]: e.target.checked };
                      if (isCombo && comboData) {
                        if (comboData.fixed_items) comboData.fixed_items.forEach((_, idx) => { updates[`${order.id}-fixed-${item.id}-${idx}`] = e.target.checked; });
                        if (comboData.selections) Object.entries(comboData.selections).forEach(([group, sel]) => { const arr = Array.isArray(sel) ? sel : [sel]; arr.forEach((_, sidx) => { updates[`${order.id}-sel-${item.id}-${group}-${sidx}`] = e.target.checked; }); });
                        if (comboData.customizations) comboData.customizations.forEach((_, idx) => { updates[`${order.id}-cust-${item.id}-${idx}`] = e.target.checked; });
                      }
                      setCheckedItems(prev => ({ ...prev, ...updates }));
                    }} className="w-3 h-3 accent-green-500 flex-shrink-0" />
                    <div className="w-11 h-11 flex-shrink-0 rounded overflow-hidden cursor-pointer" onClick={(e) => { e.preventDefault(); e.stopPropagation(); setViewingOrderPhotos({ photos: [imageUrl], currentIndex: 0 }); }}>
                      <img src={imageUrl} alt={item.product_name} className="w-full h-full object-cover" onError={(e) => { e.target.src = 'https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png'; }} />
                    </div>
                    <div className="flex-1 min-w-0">
                      <span className={`text-sm font-bold block truncate ${isChecked ? 'line-through text-gray-400' : 'text-gray-800'}`}>{item.quantity}x {item.product_name}</span>
                      <span className="text-xs text-orange-600 block">${parseInt(item.product_price || item.price || 0).toLocaleString('es-CL')}</span>
                    </div>
                    {isChecked && <CheckCircle size={16} className="text-green-500 flex-shrink-0" />}
                  </label>
                  {isCombo && !isChecked && (
                    <div className="ml-16 text-[9px] space-y-0.5 pb-1">
                      {comboData.fixed_items && comboData.fixed_items.map((fixed, idx) => (
                        <div key={idx} className="text-blue-700">• {item.quantity * (fixed.quantity || 1)}x {fixed.product_name || fixed.name}</div>
                      ))}
                      {comboData.selections && Object.entries(comboData.selections).map(([group, sel]) => {
                        const arr = Array.isArray(sel) ? sel : [sel];
                        return arr.map((s, sidx) => {
                          if (!s?.name) return null;
                          const selImg = s.image_url || s.image || null;
                          return (
                            <div key={`${group}-${sidx}`} className="flex items-center gap-1.5 text-purple-700">
                              {selImg && (
                                <img src={selImg} alt={s.name} className="w-8 h-8 rounded object-cover flex-shrink-0 border border-purple-300" onError={(e) => { e.target.style.display = 'none'; }} />
                              )}
                              <span className="text-[11px] font-medium">• {item.quantity}x {s.name}</span>
                            </div>
                          );
                        });
                      })}
                      {comboData.customizations && comboData.customizations.length > 0 && comboData.customizations.map((c, idx) => (
                        <div key={idx} className="text-orange-700 font-bold">+ {c.quantity || item.quantity}x {c.name}</div>
                      ))}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        ) : (
          <div className="bg-gray-50 rounded p-1 mb-1">
            <div className="grid grid-cols-3 gap-1">
              {order.items && order.items.map(item => renderProductDetails(item, order.id))}
            </div>
          </div>
        )}

        {renderDeliveryExtras(order)}

        {order.delivery_type === 'delivery' ? (
          <div className="space-y-2">
            <div className="text-xs bg-blue-50 border border-blue-200 rounded p-2">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-blue-800 flex-wrap">
                  <Bike size={12} className="flex-shrink-0" />
                  <span className="font-medium">Delivery</span>
                  {order.pickup_time ? (
                    <div className="flex items-center gap-1 bg-red-100 px-2 py-0.5 rounded border border-red-300">
                       <Clock size={14} className="text-red-600" />
                       <span className="font-bold text-red-600 text-[10px]">PROGRAMADO A LAS: {order.pickup_time.substring(0, 5)}</span>
                    </div>
                  ) : !isScheduled && order.created_at && (
                    <><Clock size={12} /><span>{new Date(order.created_at.replace(' ', 'T') + 'Z').toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Santiago' })}</span></>
                  )}
                  {order.delivery_address && <span className="font-semibold text-blue-900">• {order.delivery_address}</span>}
                  {order.delivery_distance_km && (
                    <span className="text-[10px] text-gray-500">📍 {order.delivery_distance_km} km · ~{order.delivery_duration_min} min</span>
                  )}
                </div>
              </div>
            </div>
            {order.delivery_type === 'delivery' && (
              <>
              <div className="flex items-center justify-between bg-white border border-gray-200 rounded p-2">
                <span className="text-xs font-bold text-gray-800">Enviar a Rider 👉🏻</span>
                <button
                  onClick={() => {
                    const riderUrl = `https://mi.laruta11.cl/rider/${order.id}`;
                    const delFee = parseInt(order.delivery_fee || 0);
                    const msg = `🛵 *Delivery #${order.order_number}*\n\n💰 Delivery: $${delFee.toLocaleString('es-CL')}\n📍 ${order.delivery_address || 'Sin dirección'}${order.delivery_distance_km ? ` (${order.delivery_distance_km}km)` : ''}\n\n👉 Tomar pedido:\n${riderUrl}`;
                    window.location.href = `whatsapp://send?text=${encodeURIComponent(msg)}`;
                  }}
                  className="flex items-center gap-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-medium transition-colors"
                >
                  <Send size={12} />
                  Rider
                </button>
              </div>
              {/* Rider monitor chevron */}
              <button
                onClick={() => setRiderMonitor(prev => prev === order.id ? null : order.id)}
                className="w-full flex items-center justify-between bg-gray-50 border border-gray-200 rounded p-2 mt-1 text-xs text-gray-600 hover:bg-gray-100 transition-colors"
              >
                <div className="flex items-center gap-1.5">
                  <MapPin size={12} className={order.order_status === 'ready' || order.order_status === 'out_for_delivery' ? 'text-blue-500' : 'text-gray-400'} />
                  <span className="font-medium">
                    {order.order_status === 'out_for_delivery'
                      ? '🛵 Rider en camino'
                      : order.order_status === 'ready'
                        ? '📦 Listo para rider'
                        : 'Ver pedido 👀'}
                  </span>
                </div>
                {riderMonitor === order.id ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
              </button>
              {riderMonitor === order.id && (
                <div className="mt-1 rounded border border-blue-200 overflow-hidden bg-white">
                  <iframe
                    src={`https://mi.laruta11.cl/rider/${order.id}/embed`}
                    className="w-full h-56 border-0"
                    title={`Rider map ${order.order_number}`}
                  />
                </div>
              )}
              </>
            )}
          </div>
        ) : order.delivery_type === 'cuartel' ? (
          <div className="text-xs bg-green-50 border border-green-200 rounded p-2 mb-2">
            <div className="flex items-center gap-2 text-green-800">
              <span className="font-medium">🎖️ Retirado en Cuartel RL6</span>
              {!isScheduled && order.created_at && (
                <><Clock size={12} /><span>{new Date(order.created_at.replace(' ', 'T') + 'Z').toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Santiago' })}</span></>
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
                  <><Clock size={14} className="text-gray-500" /><span>Recibido {new Date(order.created_at.replace(' ', 'T') + 'Z').toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Santiago' })}</span></>
                )}
              </>
            )}
          </div>
        )}

        <div className="mb-1 p-1 bg-white rounded">
          <div className="flex items-center justify-between mb-2">
            <div className="flex flex-col gap-1.5 flex-1">
              <div className="flex justify-between text-xs">
                <span className="text-gray-500">Subtotal</span>
                <span className="font-semibold text-gray-800">${parseInt(order.subtotal || 0).toLocaleString('es-CL')}</span>
              </div>
              {order.delivery_type === 'delivery' && order.delivery_fee > 0 && (
                <div className="bg-gray-50 rounded p-1.5 space-y-0.5">
                  <div className="flex justify-between text-xs">
                    <span className="text-gray-500">Delivery</span>
                    <span className={order.delivery_discount > 0 ? "font-semibold line-through text-gray-400" : "font-semibold"}>
                      ${(parseInt(order.delivery_fee) + parseInt(order.delivery_discount || 0)).toLocaleString('es-CL')}
                    </span>
                  </div>
                  {order.delivery_discount > 0 && (
                    <div className="flex justify-between text-xs text-green-600 ml-3">
                      <span>↳ Desc. RL6 ({Math.round(parseInt(order.delivery_discount) / (parseInt(order.delivery_fee) + parseInt(order.delivery_discount)) * 100)}%)</span>
                      <span className="font-semibold">-${parseInt(order.delivery_discount).toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  {order.payment_method === 'card' && (
                    <div className="flex justify-between text-xs text-red-500 ml-3">
                      <span>↳ 💳 Recargo</span>
                      <span className="font-semibold">+${parseInt(order.card_surcharge || 0).toLocaleString('es-CL')}</span>
                    </div>
                  )}
                  <div className="border-t border-gray-200 mt-1 pt-1 flex justify-between text-xs font-bold text-gray-800">
                    <span>Total Delivery</span>
                    <span>${(parseInt(order.delivery_fee) + (order.payment_method === 'card' ? parseInt(order.card_surcharge || 0) : 0)).toLocaleString('es-CL')}</span>
                  </div>
                </div>
              )}
              {order.discount_10 > 0 && (
                <div className="flex justify-between text-xs text-green-600">
                  <span>⭐ Desc. 10%</span>
                  <span className="font-semibold">-${parseInt(order.discount_10).toLocaleString('es-CL')}</span>
                </div>
              )}
              {order.discount_30 > 0 && (
                <div className="flex justify-between text-xs text-yellow-600">
                  <span>⭐ Desc. 30%</span>
                  <span className="font-semibold">-${parseInt(order.discount_30).toLocaleString('es-CL')}</span>
                </div>
              )}
              {order.discount_birthday > 0 && (
                <div className="flex justify-between text-xs text-pink-600">
                  <span>🎂 Cumpleaños</span>
                  <span className="font-semibold">-${parseInt(order.discount_birthday).toLocaleString('es-CL')}</span>
                </div>
              )}
              {order.discount_pizza > 0 && (
                <div className="flex justify-between text-xs text-purple-600">
                  <span>🍕 Pizza</span>
                  <span className="font-semibold">-${parseInt(order.discount_pizza).toLocaleString('es-CL')}</span>
                </div>
              )}
              <div className="flex justify-between border-t border-orange-200 pt-1.5 mt-0.5">
                <span className="text-sm font-bold text-gray-800">Total</span>
                <span className="text-sm font-black text-orange-500">${parseInt(order.installment_amount || 0).toLocaleString('es-CL')}</span>
              </div>
            </div>
          </div>
          <div className="flex items-center justify-between mt-2">
            <div className="flex items-center gap-1 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded w-fit">
              {getPaymentIcon(order.payment_method)}
              <span>{getPaymentText(order.payment_method)}</span>
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
          <div className={`mb-1 rounded p-2 border-2 ${
            timeAlert && (timeAlert.color.includes('red') || timeAlert.color.includes('orange'))
              ? 'bg-white border-black'
              : 'bg-yellow-200 border-yellow-400'
          }`}>
            <div className="flex items-start gap-1">
              <MessageSquare size={16} className="mt-0.5 flex-shrink-0 text-black" />
              <span className="text-base text-black font-black">{order.customer_notes}</span>
            </div>
          </div>
        )}

        {/* Photo slots — only for delivery orders */}
        {(() => {
          const photoReqs = generatePhotoRequirements(order.delivery_type);
          if (photoReqs.length === 0) return null;

          const orderSlots = photoSlots[order.id] || {};
          const getSlot = (reqId) => orderSlots[reqId] || { status: 'empty', photoUrl: null, verification: null };
          const allPhotoUrls = photoReqs.map(r => getSlot(r.id).photoUrl).filter(Boolean);
          const uploadedMap = {};
          photoReqs.forEach(r => { if (getSlot(r.id).photoUrl) uploadedMap[r.id] = getSlot(r.id).photoUrl; });
          const uploadedCount = Object.keys(uploadedMap).length;
          
          // Block uploads while any slot is uploading/analyzing
          const isAnyUploading = photoReqs.some(r => getSlot(r.id).status === 'uploading');

          const handleSlotUpload = (file, reqId, isRetake) => {
            if (!file || isAnyUploading) return;
            setPhotoSlots(prev => ({
              ...prev,
              [order.id]: { ...prev[order.id], [reqId]: { status: 'uploading', photoUrl: null, verification: null } }
            }));

            const fd = new FormData();
            fd.append('photo', file);
            fd.append('order_id', order.id);
            fd.append('photo_type', reqId);
            fd.append('order_items', JSON.stringify(order.items || []));
            fd.append('customer_notes', order.customer_notes || '');
            if (isRetake) fd.append('user_retook', 'true');

            // Timeout controller — 40s max
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 40000);

            fetch('/api/orders/save_dispatch_photo.php', { method: 'POST', body: fd, signal: controller.signal })
              .then(r => r.json())
              .then(d => {
                clearTimeout(timeout);
                if (d.success) {
                  const v = d.verification || null;
                  const status = v ? (v.aprobado ? 'approved' : 'warning') : 'approved';
                  setPhotoSlots(prev => ({
                    ...prev,
                    [order.id]: { ...prev[order.id], [reqId]: { status, photoUrl: d.url, verification: v } }
                  }));
                  loadOrders();
                } else {
                  alert('Error: ' + (d.error || 'No se pudo subir'));
                  setPhotoSlots(prev => ({
                    ...prev,
                    [order.id]: { ...prev[order.id], [reqId]: { status: 'empty', photoUrl: null, verification: null } }
                  }));
                }
              })
              .catch((err) => {
                clearTimeout(timeout);
                const isTimeout = err.name === 'AbortError';
                // On timeout: photo uploaded but IA failed — mark as uploaded with fallback
                setPhotoSlots(prev => ({
                  ...prev,
                  [order.id]: { ...prev[order.id], [reqId]: { 
                    status: 'approved', 
                    photoUrl: prev[order.id]?.[reqId]?.photoUrl || null, 
                    verification: isTimeout ? { aprobado: true, puntaje: 0, feedback: '⏳ Análisis tardó demasiado. Foto subida OK.' } : null 
                  }}
                }));
                if (!isTimeout) alert('Error al subir foto');
                loadOrders();
              });
          };

          const handleDeleteSlot = (reqId) => {
            if (isAnyUploading) return;
            setPhotoSlots(prev => ({
              ...prev,
              [order.id]: { ...prev[order.id], [reqId]: { status: 'empty', photoUrl: null, verification: null, _retake: true } }
            }));
          };

          return (
            <div className="mb-2 border border-blue-200 rounded-lg overflow-hidden bg-white shadow-sm">
              <div className="bg-red-600 px-2 py-0.5 text-[10px] font-black text-white flex items-center justify-between">
                <span className="flex items-center gap-1">
                  <Camera size={11} /> FOTOS DELIVERY
                </span>
                <span className="flex items-center gap-1">
                  <Package size={11} /> BOLSAS
                </span>
              </div>

              <div className="p-1">
                <div className="grid grid-cols-4 gap-1">
                  {photoReqs.map(req => {
                    const slot = getSlot(req.id);
                    const isRetake = !!(orderSlots[req.id] && orderSlots[req.id]._retake);

                    if (slot.status === 'uploading') {
                      return (
                        <div key={req.id} className="h-28 flex flex-col items-center justify-center rounded-lg border-2 border-blue-400 bg-gradient-to-br from-blue-50 to-indigo-50 relative overflow-hidden">
                          <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent animate-[shimmer_1.5s_infinite]" style={{animation: 'shimmer 1.5s infinite', backgroundSize: '200% 100%'}} />
                          <Loader2 size={24} className="text-blue-500 animate-spin mb-1" />
                          <span className="text-[10px] font-bold text-blue-600">Subiendo y analizando...</span>
                          <span className="text-[8px] text-blue-400 mt-0.5">{req.id === 'productos' ? 'Antes de empaquetar' : 'Pedido listo en bolsa'}</span>
                        </div>
                      );
                    }

                    if (slot.photoUrl) {
                      const p = slot.verification?.puntaje ?? (slot.status === 'approved' ? 100 : 50);
                      const borderColor = p >= 80 ? '#22c55e' : p >= 50 ? '#f59e0b' : '#ef4444';
                      const BadgeIcon = p >= 80 ? ShieldCheck : ShieldAlert;
                      const badgeColor = p >= 80 ? 'text-green-400' : p >= 50 ? 'text-amber-400' : 'text-red-400';
                      return (
                        <div key={req.id} className="h-28 rounded-lg overflow-hidden relative group shadow-sm"
                          style={{border: `2px solid ${borderColor}`}}>
                          <img
                            src={slot.photoUrl}
                            alt={req.label}
                            className="w-full h-full object-cover cursor-pointer"
                            onClick={() => setViewingOrderPhotos({ photos: allPhotoUrls, currentIndex: Math.max(0, allPhotoUrls.indexOf(slot.photoUrl)) })}
                          />
                          <div className="absolute top-0.5 left-0.5 flex items-center gap-0.5 bg-black/60 backdrop-blur-sm rounded px-1 py-0.5">
                            <BadgeIcon size={10} className={badgeColor} />
                            <span className="text-[8px] font-bold text-white">{req.id === 'productos' ? 'Productos' : 'En bolsa'}</span>
                          </div>
                          <button
                            onClick={(e) => { e.stopPropagation(); handleDeleteSlot(req.id); }}
                            disabled={isAnyUploading}
                            className="absolute top-0.5 right-0.5 bg-red-500/90 hover:bg-red-600 disabled:opacity-50 text-white rounded-full w-5 h-5 flex items-center justify-center shadow backdrop-blur-sm active:scale-90 transition-all"
                            aria-label={`Eliminar ${req.label}`}
                          ><Trash2 size={10} /></button>
                        </div>
                      );
                    }

                    return (
                      <label key={req.id} className={`h-28 flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed transition-all active:scale-95 ${
                        isAnyUploading 
                          ? 'border-gray-200 bg-gray-100 text-gray-300 cursor-not-allowed' 
                          : 'border-gray-300 bg-gray-50 hover:bg-orange-50 hover:border-orange-400 text-gray-400 hover:text-orange-500 cursor-pointer'
                      }`}>
                        <ImagePlus size={20} />
                        <span className="text-[9px] font-bold">{req.id === 'productos' ? 'Antes de empaquetar' : 'Pedido en bolsa'}</span>
                        <input type="file" accept="image/*" capture="environment" className="hidden" disabled={isAnyUploading}
                          onChange={e => { handleSlotUpload(e.target.files[0], req.id, isRetake); e.target.value = ''; }} />
                      </label>
                    );
                  })}
                  {/* Bag squares integrated into the grid */}
                  {(() => {
                    const pkg = getPackaging(order.id, order.delivery_type);
                    const bags = [
                      { key: 'bolsa_grande', label: 'Grande', img: '/bolsa_deliverys/BOLSA PAPEL CAFE CON MANILLA 36x20x39 CM..jpg', desc: 'Para pedidos grandes: combos, pichangas, o 3+ productos. Medida 36×20×39 cm.' },
                      { key: 'bolsa_mediana', label: 'Mediana', img: '/bolsa_deliverys/BOLSA PAPEL CAPE CON MANILLA 30x12x32.jpg', desc: 'Para pedidos chicos: 1-2 completos o papas medianas. Medida 30×12×32 cm.' }
                    ];
                    return bags.map(bag => {
                      const qty = pkg[bag.key];
                      return (
                        <div key={bag.key} className={`h-28 rounded-lg border-2 flex flex-col items-center justify-center gap-1 transition-all ${qty > 0 ? 'border-amber-400 bg-amber-50' : 'border-gray-200 bg-gray-50'}`}>
                          <img src={bag.img} alt={bag.label} className="w-12 h-12 rounded-lg object-cover cursor-pointer active:scale-95 transition-transform"
                            onClick={() => setBagInfoModal(bag)} />
                          <span className="text-[9px] font-bold text-gray-600">{bag.label}</span>
                          <div className="flex items-center gap-1">
                            <button onClick={() => setPackagingValue(order.id, bag.key, -1, order.delivery_type)} disabled={qty <= 0}
                              className="w-6 h-6 flex items-center justify-center rounded-full bg-gray-200 hover:bg-gray-300 disabled:opacity-30 text-sm font-bold active:scale-90 transition-all"
                              aria-label={`Reducir ${bag.label}`}>−</button>
                            <span className={`w-6 text-center text-sm font-black rounded ${qty > 0 ? 'text-amber-700' : 'text-gray-400'}`}>{qty}</span>
                            <button onClick={() => setPackagingValue(order.id, bag.key, 1, order.delivery_type)} disabled={qty >= 10}
                              className="w-6 h-6 flex items-center justify-center rounded-full bg-gray-200 hover:bg-gray-300 disabled:opacity-30 text-sm font-bold active:scale-90 transition-all"
                              aria-label={`Aumentar ${bag.label}`}>+</button>
                          </div>
                        </div>
                      );
                    });
                  })()}
                </div>

                {/* Inline feedback */}
                {photoReqs.some(r => getSlot(r.id).verification) && (
                  <div className="mt-1 space-y-0.5">
                    {photoReqs.map(req => {
                      const slot = getSlot(req.id);
                      if (!slot.verification) return null;
                      const p = slot.verification.puntaje ?? 0;
                      const borderColor = p >= 80 ? 'border-green-400' : p >= 50 ? 'border-amber-400' : 'border-red-400';
                      const Icon = p >= 80 ? CheckCircle : AlertTriangle;
                      const iconColor = p >= 80 ? 'text-green-500' : p >= 50 ? 'text-amber-500' : 'text-red-500';
                      // Render **text** as bold red, rest is black
                      const renderFeedback = (text) => {
                        if (!text) return null;
                        const parts = text.split(/\*\*(.*?)\*\*/g);
                        return parts.map((part, i) => i % 2 === 1 ? <b key={i} className="text-red-600 font-bold">{part}</b> : <span key={i} className="text-gray-800">{part}</span>);
                      };
                      return (
                        <div key={req.id} className={`flex items-start gap-1.5 rounded px-1.5 py-1 text-[10px] bg-white border ${borderColor}`}>
                          <Icon size={12} className={`${iconColor} flex-shrink-0 mt-0.5`} />
                          <span>{renderFeedback(slot.verification.feedback)}</span>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>
          );
        })()}

        {/* ── Packaging Stepper Area ── */}
        {(() => {
          const isDelivery = order.delivery_type === 'delivery';
          const isReadyPhase = order.order_status === 'ready';
          if (isDelivery && isReadyPhase) return null;
          // For delivery, packaging is integrated into the photo grid above
          if (isDelivery) return null;

          // Pickup: show standalone bag squares
          const pkg = getPackaging(order.id, order.delivery_type);
          const bags = [
            { key: 'bolsa_grande', label: 'Grande', img: '/bolsa_deliverys/BOLSA PAPEL CAFE CON MANILLA 36x20x39 CM..jpg', desc: 'Para pedidos grandes: combos, pichangas, o 3+ productos. Medida 36×20×39 cm.' },
            { key: 'bolsa_mediana', label: 'Mediana', img: '/bolsa_deliverys/BOLSA PAPEL CAPE CON MANILLA 30x12x32.jpg', desc: 'Para pedidos chicos: 1-2 completos o papas medianas. Medida 30×12×32 cm.' }
          ];

          return (
            <div className="mb-2 border border-amber-200 rounded-lg overflow-hidden bg-white shadow-sm">
              <div className="bg-amber-600 px-2 py-0.5 text-[10px] font-black text-white flex items-center gap-1">
                <Package size={11} /> BOLSAS DELIVERY
              </div>
              <div className="p-1">
                <div className="grid grid-cols-2 gap-1">
                  {bags.map(bag => {
                    const qty = pkg[bag.key];
                    return (
                      <div key={bag.key} className={`h-28 rounded-lg border-2 flex flex-col items-center justify-center gap-1 transition-all ${qty > 0 ? 'border-amber-400 bg-amber-50' : 'border-gray-200 bg-gray-50'}`}>
                        <img src={bag.img} alt={bag.label} className="w-12 h-12 rounded-lg object-cover cursor-pointer active:scale-95 transition-transform"
                          onClick={() => setBagInfoModal(bag)} />
                        <span className="text-[9px] font-bold text-gray-600">{bag.label}</span>
                        <div className="flex items-center gap-1">
                          <button onClick={() => setPackagingValue(order.id, bag.key, -1, order.delivery_type)} disabled={qty <= 0}
                            className="w-6 h-6 flex items-center justify-center rounded-full bg-gray-200 hover:bg-gray-300 disabled:opacity-30 text-sm font-bold active:scale-90 transition-all"
                            aria-label={`Reducir ${bag.label}`}>−</button>
                          <span className={`w-6 text-center text-sm font-black rounded ${qty > 0 ? 'text-amber-700' : 'text-gray-400'}`}>{qty}</span>
                          <button onClick={() => setPackagingValue(order.id, bag.key, 1, order.delivery_type)} disabled={qty >= 10}
                            className="w-6 h-6 flex items-center justify-center rounded-full bg-gray-200 hover:bg-gray-300 disabled:opacity-30 text-sm font-bold active:scale-90 transition-all"
                            aria-label={`Aumentar ${bag.label}`}>+</button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
          );
        })()}

        <div className="flex flex-col gap-2">
          <div className="flex gap-2">
            {(() => {
              const isDelivery = order.delivery_type === 'delivery';
              
              // Delivery: flujo en 2 fases
              // Fase 1 (sent_to_kitchen/pending): fotos + DESPACHAR A DELIVERY → cambia a 'ready'
              // Fase 2 (ready): ENTREGAR → cambia a 'delivered'
              if (isDelivery) {
                const isReadyForDelivery = order.order_status === 'ready';
                
                if (isReadyForDelivery) {
                  // Fase 2: ya despachado, esperando confirmación de entrega
                  return (
                    <>
                      {!isPaid && (
                        <button onClick={() => confirmPayment(order.id, order.order_number, order.payment_method)} disabled={processing === order.id} className="flex-1 bg-gray-800 hover:bg-gray-900 disabled:bg-gray-400 text-white font-bold py-2 px-3 rounded text-xs">
                          {processing === order.id ? '⏳' : '✓ CONFIRMAR PAGO'}
                        </button>
                      )}
                      <button onClick={() => deliverOrder(order.id, order.order_number)} disabled={processing === order.id} className="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-2 px-3 rounded text-xs">
                        {processing === order.id ? '⏳' : '✅ ENTREGAR'}
                      </button>
                    </>
                  );
                }
                
                // Fase 1: necesita fotos + despachar
                const photoReqs = generatePhotoRequirements('delivery');
                const orderSlots = photoSlots[order.id] || {};
                const uploadedMap = {};
                photoReqs.forEach(r => { if (orderSlots[r.id] && orderSlots[r.id].photoUrl) uploadedMap[r.id] = orderSlots[r.id].photoUrl; });
                const btnState = getButtonState(photoReqs, uploadedMap);
                const missingCount = photoReqs.length - Object.keys(uploadedMap).length;
                return (
                  <>
                    {!isPaid && (
                      <button onClick={() => confirmPayment(order.id, order.order_number, order.payment_method)} disabled={processing === order.id} className="flex-1 bg-gray-800 hover:bg-gray-900 disabled:bg-gray-400 text-white font-bold py-2 px-3 rounded text-xs">
                        {processing === order.id ? '⏳' : '✓ CONFIRMAR PAGO'}
                      </button>
                    )}
                    <button
                      onClick={() => {
                        if (btnState.enabled) {
                          dispatchToDelivery(order.id, order.order_number);
                        } else {
                          alert(`Faltan ${missingCount} de 2 fotos`);
                        }
                      }}
                      disabled={processing === order.id}
                      className={`flex-1 ${processing === order.id ? 'bg-gray-400 text-gray-200' : btnState.className} font-bold py-2 px-3 rounded text-xs`}
                    >
                      {processing === order.id ? '⏳' : btnState.text}
                    </button>
                  </>
                );
              }
              
              // Local: flujo original sin cambios
              if (!isPaid) {
                return (
                  <button onClick={() => confirmPayment(order.id, order.order_number, order.payment_method)} disabled={processing === order.id} className="flex-1 bg-gray-800 hover:bg-gray-900 disabled:bg-gray-400 text-white font-bold py-2 px-3 rounded text-xs">
                    {processing === order.id ? '⏳' : '✓ CONFIRMAR PAGO'}
                  </button>
                );
              }
              return (
                <button onClick={() => deliverOrder(order.id, order.order_number)} disabled={processing === order.id} className="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-2 px-3 rounded text-xs">
                  {processing === order.id ? '⏳' : '✅ ENTREGAR'}
                </button>
              );
            })()}
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
      <div className="bg-gradient-to-r from-orange-500 to-red-500 text-white px-3 py-2 shadow-lg flex items-center justify-between" style={{ paddingTop: 'max(0.5rem, env(safe-area-inset-top))' }}>
        <h2 className="text-sm font-bold flex items-center gap-1">
          📋 Comandas Activas
          {activeOrdersCount > 0 && (
            <span className="bg-white/20 px-2 py-0.5 rounded-full text-xs font-semibold">
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
                <div className="sticky top-0 bg-blue-100 border-b-2 border-blue-400 px-2 py-1 z-10">
                  <div className="flex items-center gap-2 text-blue-800 font-bold text-xs">
                    📋 Checklists Pendientes ({checklists.length})
                  </div>
                </div>
                {checklists.map(checklist => (
                  <div key={checklist.id} className="p-1">
                    <ChecklistCard checklist={checklist} />
                  </div>
                ))}
              </>
            )}
            {scheduledOrders.length > 0 && (
              <>
                <div className="sticky top-0 bg-purple-100 border-b-2 border-purple-400 px-2 py-1 z-10">
                  <div className="flex items-center gap-2 text-purple-800 font-bold text-xs">
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
                  <div className="sticky top-0 bg-orange-100 border-b-2 border-orange-400 px-2 py-1 z-10">
                    <div className="flex items-center gap-2 text-orange-800 font-bold text-xs">
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

      {/* Cash Modal for pedidosya_cash */}
      {cashModalOrder && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            {cashStep === 'input' ? (
              <>
                <h3 className="text-xl font-bold text-gray-800 mb-4">💵 PedidosYA - Pago en Efectivo</h3>
                <p className="text-xs text-gray-500 mb-2">Pedido {cashModalOrder.orderNumber}</p>

                <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                  <p className="text-sm text-gray-600 mb-1">Total a pagar:</p>
                  <p className="text-3xl font-bold text-orange-600">${(cashModalOrder.total || 0).toLocaleString('es-CL')}</p>
                </div>

                <div className="mb-4">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    ¿Con cuánto paga el cliente?
                  </label>
                  <div className="relative">
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-lg font-semibold">$</span>
                    <input
                      type="text"
                      value={cashAmount}
                      onChange={handleCashInput}
                      onKeyDown={(e) => e.key === 'Enter' && handleContinueCash()}
                      className="w-full pl-8 pr-3 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-lg font-semibold"
                      placeholder="0"
                      autoFocus
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-2 mb-4">
                  <button
                    onClick={setCashExactAmount}
                    className="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-3 rounded-lg transition-colors text-sm"
                  >
                    Monto Exacto
                  </button>
                  <button
                    onClick={() => setCashQuickAmount(5000)}
                    className="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-3 rounded-lg transition-colors text-sm"
                  >
                    $5.000
                  </button>
                  <button
                    onClick={() => setCashQuickAmount(10000)}
                    className="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-3 rounded-lg transition-colors text-sm"
                  >
                    $10.000
                  </button>
                  <button
                    onClick={() => setCashQuickAmount(20000)}
                    className="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-3 rounded-lg transition-colors text-sm"
                  >
                    $20.000
                  </button>
                </div>

                <div className="flex gap-3">
                  <button
                    onClick={closeCashModal}
                    className="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-4 rounded-lg transition-colors"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={handleContinueCash}
                    className="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition-colors"
                  >
                    Continuar
                  </button>
                </div>
              </>
            ) : (
              <>
                <h3 className="text-xl font-bold text-gray-800 mb-4">💰 Confirmar Vuelto</h3>
                <p className="text-xs text-gray-500 mb-2">Pedido {cashModalOrder.orderNumber}</p>

                <div className="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-sm text-gray-600">Total:</span>
                    <span className="text-lg font-semibold">${cashModalOrder.total.toLocaleString('es-CL')}</span>
                  </div>
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-sm text-gray-600">Paga con:</span>
                    <span className="text-lg font-semibold">${parseInt(cashAmount.replace(/\./g, '')).toLocaleString('es-CL')}</span>
                  </div>
                  <div className="border-t border-green-300 pt-2 mt-2">
                    <div className="flex justify-between items-center">
                      <span className="text-base font-semibold text-gray-700">Vuelto a entregar:</span>
                      <span className="text-2xl font-bold text-green-600">
                        ${(parseInt(cashAmount.replace(/\./g, '')) - cashModalOrder.total).toLocaleString('es-CL')}
                      </span>
                    </div>
                  </div>
                </div>

                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                  <p className="text-sm text-yellow-800 font-medium text-center">
                    ⚠️ Confirma que entregarás el vuelto correcto
                  </p>
                </div>

                <div className="flex gap-3">
                  <button
                    onClick={() => setCashStep('input')}
                    disabled={processing === cashModalOrder.id}
                    className="flex-1 bg-gray-300 hover:bg-gray-400 disabled:bg-gray-200 text-gray-800 font-bold py-3 px-4 rounded-lg transition-colors"
                  >
                    Volver
                  </button>
                  <button
                    onClick={processCashPayment}
                    disabled={processing === cashModalOrder.id}
                    className="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded-lg transition-colors"
                  >
                    {processing === cashModalOrder.id ? '⏳ Procesando...' : '✓ Confirmar Pago'}
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* Bag Info Modal */}
      {bagInfoModal && (
        <div className="fixed inset-0 bg-black/80 flex flex-col z-[9999]" onClick={() => setBagInfoModal(null)}>
          <div className="flex-1 flex items-center justify-center p-4" onClick={e => e.stopPropagation()}>
            <img src={bagInfoModal.img} alt={bagInfoModal.label} className="max-h-[60vh] max-w-full object-contain rounded-xl shadow-2xl" />
          </div>
          <div className="bg-white rounded-t-2xl p-5 safe-area-bottom" onClick={e => e.stopPropagation()}>
            <h3 className="text-xl font-bold text-gray-900 mb-1">Bolsa {bagInfoModal.label}</h3>
            <p className="text-sm text-gray-600 mb-4">{bagInfoModal.desc}</p>
            <button onClick={() => setBagInfoModal(null)}
              className="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 rounded-xl transition-colors active:scale-95 text-base"
            >Entendido</button>
          </div>
        </div>
      )}
    </div>
  );
}

export default MiniComandas;
