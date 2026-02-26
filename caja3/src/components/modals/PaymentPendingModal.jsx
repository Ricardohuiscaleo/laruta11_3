import React from 'react';
import { X } from 'lucide-react';

const PaymentPendingModal = ({ isOpen, onClose, paymentType, orderData }) => {
  if (!isOpen || !orderData) return null;

  const { orderId, cart, total, subtotal, deliveryFee, customerInfo } = orderData;

  const paymentConfig = {
    card: {
      icon: '💳',
      title: 'Pago con Tarjeta',
      bgColor: 'bg-purple-50',
      borderColor: 'border-purple-200',
      textColor: 'text-purple-800',
      iconBg: 'bg-purple-100',
      iconColor: 'text-purple-600',
      message: 'Por favor realiza el pago con tarjeta de crédito o débito en el local',
      status: 'Pendiente de pago con tarjeta'
    },
    cash: {
      icon: '💵',
      title: 'Pago en Efectivo',
      bgColor: 'bg-green-50',
      borderColor: 'border-green-200',
      textColor: 'text-green-800',
      iconBg: 'bg-green-100',
      iconColor: 'text-green-600',
      message: '✅ Pago recibido y confirmado\n✅ Pedido enviado a cocina',
      status: 'Pagado y en cocina'
    },
    transfer: {
      icon: '🏦',
      title: 'Transferencia Bancaria',
      bgColor: 'bg-blue-50',
      borderColor: 'border-blue-200',
      textColor: 'text-blue-800',
      iconBg: 'bg-blue-100',
      iconColor: 'text-blue-600',
      message: 'Por favor realiza la transferencia a la cuenta indicada',
      status: 'Pendiente de transferencia'
    }
  };

  const config = paymentConfig[paymentType] || paymentConfig.card;

  const generateWhatsAppMessage = () => {
    let message = `> ${config.icon} *PEDIDO ${paymentType === 'cash' ? 'CONFIRMADO' : 'PENDIENTE'} - LA RUTA 11*\n\n`;
    message += `*📋 Datos del pedido:*\n`;
    message += `- *Pedido:* ${orderId}\n`;
    message += `- *Cliente:* ${customerInfo.name}\n`;
    message += `- *Teléfono:* ${customerInfo.phone || 'No especificado'}\n`;
    message += `- *Tipo:* ${customerInfo.deliveryType === 'delivery' ? '🚴 Delivery' : '🏪 Retiro'}\n`;

    if (customerInfo.deliveryType === 'delivery' && customerInfo.address) {
      message += `- *Dirección:* ${customerInfo.address}\n`;
    }
    if (customerInfo.deliveryType === 'pickup' && customerInfo.pickupTime) {
      message += `- *Hora de retiro:* ${customerInfo.pickupTime}\n`;
    }

    message += `- *Estado:* ${config.status}\n`;
    message += `- *Método:* ${config.title}\n\n`;

    message += `*📦 Productos:*\n`;
    cart.forEach((item, index) => {
      const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
      message += `${index + 1}. ${item.name} x${item.quantity} - $${(item.price * item.quantity).toLocaleString('es-CL')}\n`;

      if (isCombo && (item.fixed_items || item.selections)) {
        if (item.fixed_items) {
          item.fixed_items.forEach(fixedItem => {
            message += `   - ${item.quantity}x ${fixedItem.product_name || fixedItem.name}\n`;
          });
        }
        if (item.selections) {
          Object.entries(item.selections).forEach(([group, selection]) => {
            if (Array.isArray(selection)) {
              selection.forEach(sel => {
                message += `   - ${item.quantity}x ${sel.name}\n`;
              });
            } else if (selection) {
              message += `   - ${item.quantity}x ${selection.name}\n`;
            }
          });
        }
      }

      if (item.customizations && item.customizations.length > 0) {
        item.customizations.forEach(custom => {
          message += `   - ${custom.quantity}x ${custom.name} (+$${(custom.price * custom.quantity).toLocaleString('es-CL')})\n`;
        });
      }
    });

    message += `\n*💰 Totales:*\n`;
    message += `- *Subtotal:* $${subtotal.toLocaleString('es-CL')}\n`;
    if (deliveryFee > 0) {
      message += `- *Delivery:* $${deliveryFee.toLocaleString('es-CL')}\n`;
    }
    message += `\n> *💰 TOTAL: $${total.toLocaleString('es-CL')}*\n\n`;
    message += `_Pedido realizado desde la app web._`;

    return encodeURIComponent(message);
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={onClose}>
      <div className="bg-white w-full max-w-md mx-4 rounded-2xl shadow-xl animate-slide-up max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="p-6">
          {/* Header */}
          <div className="mb-6 text-center">
            <div className={`w-16 h-16 ${config.iconBg} rounded-full flex items-center justify-center mx-auto mb-4`}>
              <svg className={`w-8 h-8 ${config.iconColor}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {paymentType === 'card' && (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                )}
                {paymentType === 'cash' && (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                )}
                {paymentType === 'transfer' && (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                )}
              </svg>
            </div>
            <h1 className="text-2xl font-bold text-gray-900 mb-2">
              {paymentType === 'cash' ? '¡Pedido Registrado!' : 'Pago Pendiente'}
            </h1>
            <p className="text-gray-600 mb-6">
              {paymentType === 'cash' ? 'Pago en efectivo recibido' : 'Tu pedido está esperando confirmación de pago'}
            </p>
          </div>

          {/* Payment Info */}
          <div className={`${config.bgColor} border ${config.borderColor} rounded-lg p-4 mb-6`}>
            <h3 className={`font-semibold ${config.textColor} mb-2`}>{config.icon} {config.title}</h3>
            <div className={`text-sm ${config.textColor} space-y-1`}>
              <p style={{ whiteSpace: 'pre-line' }}>{config.message}</p>
              <p><strong>Monto a pagar:</strong> ${total.toLocaleString('es-CL')}</p>
            </div>
          </div>

          {/* Customer Info */}
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
            <h3 className="font-semibold text-gray-900 mb-2">👤 Datos del Cliente</h3>
            <div className="text-sm text-gray-800 space-y-1">
              <p><strong>Cliente:</strong> {customerInfo.name}</p>
              <p><strong>Teléfono:</strong> {customerInfo.phone || 'No especificado'}</p>
              <p><strong>Tipo de entrega:</strong> {customerInfo.deliveryType === 'delivery' ? 'Delivery' : 'Retiro'}</p>
              {customerInfo.deliveryType === 'delivery' && customerInfo.address && (
                <p><strong>Dirección:</strong> {customerInfo.address}</p>
              )}
              {customerInfo.deliveryType === 'pickup' && customerInfo.pickupTime && (
                <p><strong>Hora de retiro:</strong> {customerInfo.pickupTime}</p>
              )}
            </div>
          </div>

          {/* Order Items */}
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
            <h3 className="font-semibold text-gray-900 mb-3">🛒 Tu Pedido</h3>
            <div className="space-y-3">
              {cart.map((item, index) => {
                const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
                let itemTotal = item.price * item.quantity;

                if (item.customizations && item.customizations.length > 0) {
                  itemTotal += item.customizations.reduce((sum, c) => sum + (c.price * c.quantity), 0);
                }

                return (
                  <div key={index} className="border-b border-gray-200 pb-3 last:border-b-0 last:pb-0">
                    <div className="flex justify-between items-start">
                      <div className="flex-1">
                        <div className="font-medium text-gray-900">{item.name}</div>
                        <div className="text-xs text-gray-500">Cantidad: {item.quantity}</div>

                        {/* Combos */}
                        {isCombo && (item.fixed_items || item.selections) && (
                          <div className="text-xs text-gray-500 mt-1">
                            <span className="font-medium">Incluye: </span>
                            {item.fixed_items && item.fixed_items.map((f, i) => (
                              <span key={i}>{item.quantity}x {f.product_name || f.name}{i < item.fixed_items.length - 1 || Object.keys(item.selections || {}).length > 0 ? ', ' : ''}</span>
                            ))}
                            {item.selections && Object.entries(item.selections).map(([group, selection], i) => {
                              if (Array.isArray(selection)) {
                                return selection.map((sel, j) => (
                                  <span key={`${i}-${j}`} className="text-blue-600">{item.quantity}x {sel.name}{j < selection.length - 1 ? ', ' : ''}</span>
                                ));
                              }
                              return <span key={i} className="text-blue-600">{item.quantity}x {selection.name}</span>;
                            })}
                          </div>
                        )}

                        {/* Customizations */}
                        {item.customizations && item.customizations.length > 0 && (
                          <div className="text-xs text-blue-600 mt-1">
                            {item.customizations.map((c, i) => (
                              <div key={i}>+ {c.quantity}x {c.name} (+${(c.price * c.quantity).toLocaleString('es-CL')})</div>
                            ))}
                          </div>
                        )}
                      </div>
                      <div className="font-semibold text-gray-900 ml-2">${itemTotal.toLocaleString('es-CL')}</div>
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Totals */}
            <div className="border-t border-gray-200 pt-3 mt-3 space-y-2">
              <div className="flex justify-between">
                <span className="text-gray-600">Subtotal:</span>
                <span className="font-semibold">${subtotal.toLocaleString('es-CL')}</span>
              </div>
              {deliveryFee > 0 && (
                <div className="flex justify-between">
                  <span className="text-gray-600">Delivery:</span>
                  <span className="font-semibold">${deliveryFee.toLocaleString('es-CL')}</span>
                </div>
              )}
              <div className="flex justify-between text-lg font-bold border-t border-gray-200 pt-2">
                <span>Total:</span>
                <span className="text-green-600">${total.toLocaleString('es-CL')}</span>
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="space-y-3">
            <a
              href={`https://wa.me/56936227422?text=${generateWhatsAppMessage()}`}
              target="_blank"
              rel="noopener noreferrer"
              className="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg flex items-center justify-center gap-2 transition-colors"
            >
              <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488" />
              </svg>
              {paymentType === 'cash' ? 'Notificar por WhatsApp' : 'Continuar en WhatsApp'}
            </a>

            <button
              onClick={onClose}
              className="w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded-lg transition-colors"
            >
              Cerrar
            </button>
          </div>

          {/* Footer */}
          <div className="text-xs text-gray-500 mt-6 text-center space-y-1">
            <p>Pedido: <span className="font-mono">{orderId}</span></p>
            <p>{paymentType === 'cash' ? 'El pedido está siendo preparado en cocina' : 'Una vez confirmado el pago, comenzaremos a preparar tu pedido'}</p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PaymentPendingModal;
