import React, { useState } from 'react';
import MiniComandas from './MiniComandas.jsx';

export default function OrderPOSApp() {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  
  // Datos del cliente y producto
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [tableNumber, setTableNumber] = useState('');
  const [productName, setProductName] = useState('Tomahawk Full Ruta 11');
  const [productPrice, setProductPrice] = useState(1500);
  const [installmentsTotal, setInstallmentsTotal] = useState(3);
  const [installmentCurrent, setInstallmentCurrent] = useState(1);
  const [amount, setAmount] = useState(500);
  const [device, setDevice] = useState('6010B232541610747');

  const sendPayment = async () => {
    if (!amount || amount <= 0) {
      alert('Por favor ingresa un monto válido');
      return;
    }
    
    if (!customerName.trim()) {
      alert('Por favor ingresa el nombre del cliente');
      return;
    }

    setLoading(true);
    setResult(null);
    
    try {
      // Primero crear el pedido en nuestra base de datos
      const orderResponse = await fetch('/api/create_order.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          customer_name: customerName,
          customer_phone: customerPhone,
          table_number: tableNumber,
          product_name: productName,
          product_price: productPrice,
          installments_total: installmentsTotal,
          installment_current: installmentCurrent,
          installment_amount: amount
        })
      });
      
      const orderData = await orderResponse.json();
      
      if (!orderData.success) {
        throw new Error(orderData.error || 'Error creando pedido');
      }
      
      // Luego enviar el pago a TUU usando el endpoint que funciona
      const response = await fetch('/api/tuu_payment_gateway.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          amount: parseInt(amount),
          description: `${productName} - ${customerName}`,
          device: device === '6010B232541610747' ? 'pos1' : 'pos2',
          dteType: 0,
          order_id: orderData.order_id
        })
      });
      
      const data = await response.json();
      setResult({
        ...data,
        order_id: orderData.order_id,
        customer_info: {
          name: customerName,
          phone: customerPhone,
          table: tableNumber,
          product: productName,
          installment: `${installmentCurrent}/${installmentsTotal}`
        }
      });
      
    } catch (error) {
      setResult({
        success: false,
        error: 'Error de conexión: ' + error.message
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-7xl mx-auto">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2">
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h2 className="text-2xl font-semibold text-gray-900 mb-6">Crear Pedido y Enviar Pago</h2>
          
          <div className="space-y-6">
            {/* Datos del Cliente */}
            <div className="border-b pb-6">
              <h3 className="text-lg font-medium text-gray-800 mb-4">👤 Datos del Cliente</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Nombre Cliente *
                  </label>
                  <input
                    type="text"
                    value={customerName}
                    onChange={(e) => setCustomerName(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Juan Pérez"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Teléfono
                  </label>
                  <input
                    type="tel"
                    value={customerPhone}
                    onChange={(e) => setCustomerPhone(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="+56912345678"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Mesa
                  </label>
                  <input
                    type="text"
                    value={tableNumber}
                    onChange={(e) => setTableNumber(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Mesa 1"
                  />
                </div>
              </div>
            </div>
            
            {/* Datos del Producto */}
            <div className="border-b pb-6">
              <h3 className="text-lg font-medium text-gray-800 mb-4">🍖 Producto</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Producto
                  </label>
                  <select
                    value={productName}
                    onChange={(e) => {
                      setProductName(e.target.value);
                      if (e.target.value === 'Tomahawk Full Ruta 11') {
                        setProductPrice(1500);
                        setAmount(500);
                      } else if (e.target.value === 'Completo Italiano') {
                        setProductPrice(800);
                        setAmount(800);
                      }
                    }}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="Tomahawk Full Ruta 11">🥩 Tomahawk Full Ruta 11</option>
                    <option value="Completo Italiano">🌭 Completo Italiano</option>
                    <option value="Salchipapas">🍟 Salchipapas</option>
                    <option value="Papas Ruta 11">🥔 Papas Ruta 11</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Precio Total
                  </label>
                  <input
                    type="number"
                    value={productPrice}
                    onChange={(e) => setProductPrice(parseInt(e.target.value))}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>
            </div>
            
            {/* Cuotas */}
            <div className="border-b pb-6">
              <h3 className="text-lg font-medium text-gray-800 mb-4">💳 Cuotas</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Total Cuotas
                  </label>
                  <select
                    value={installmentsTotal}
                    onChange={(e) => {
                      const total = parseInt(e.target.value);
                      setInstallmentsTotal(total);
                      setAmount(Math.round(productPrice / total));
                    }}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value={1}>1 cuota</option>
                    <option value={2}>2 cuotas</option>
                    <option value={3}>3 cuotas</option>
                    <option value={4}>4 cuotas</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Cuota Actual
                  </label>
                  <select
                    value={installmentCurrent}
                    onChange={(e) => setInstallmentCurrent(parseInt(e.target.value))}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    {Array.from({length: installmentsTotal}, (_, i) => (
                      <option key={i+1} value={i+1}>Cuota {i+1}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Monto Cuota (CLP)
                  </label>
                  <input
                    type="number"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>
            </div>
            
            {/* POS */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                🖥️ Dispositivo POS
              </label>
              <select
                value={device}
                onChange={(e) => setDevice(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="6010B232541610747">POS 1 - 6010B232541610747</option>
                <option value="6010B232541609909">POS 2 - 6010B232541609909</option>
              </select>
            </div>
            
            <button
              onClick={sendPayment}
              disabled={loading}
              className="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 text-lg font-medium"
            >
              {loading ? (
                <>
                  <span className="animate-spin">🔄</span>
                  Creando pedido y enviando pago...
                </>
              ) : (
                <>
                  <span>💳</span>
                  Crear Pedido y Enviar Pago
                </>
              )}
            </button>
          </div>
        </div>

        {/* Resultado */}
        {result && (
          <div className={`mt-6 rounded-lg p-6 ${result.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}`}>
            <div className="flex items-center gap-2 mb-4">
              <span className="text-2xl">{result.success ? '✅' : '❌'}</span>
              <h3 className="text-xl font-semibold">
                {result.success ? 'Pedido Creado y Pago Enviado' : 'Error'}
              </h3>
            </div>
            
            {result.success ? (
              <div className="space-y-4">
                {result.customer_info && (
                  <div className="bg-white p-4 rounded border">
                    <h4 className="font-medium text-gray-800 mb-3">📋 Información del Pedido</h4>
                    <div className="grid grid-cols-2 gap-3 text-sm">
                      <p><strong>Cliente:</strong> {result.customer_info.name}</p>
                      <p><strong>Mesa:</strong> {result.customer_info.table}</p>
                      <p><strong>Producto:</strong> {result.customer_info.product}</p>
                      <p><strong>Cuota:</strong> {result.customer_info.installment}</p>
                    </div>
                  </div>
                )}
                <div className="space-y-2 text-sm">
                  <p><strong>🆔 ID Pedido:</strong> {result.order_id}</p>
                  <p><strong>💳 ID Pago TUU:</strong> {result.paymentRequestId}</p>
                  <p><strong>💰 Monto:</strong> ${result.amount?.toLocaleString()} CLP</p>
                  <p><strong>🖥️ Dispositivo:</strong> {result.device_used}</p>
                  <p className="text-green-700 font-medium">{result.message}</p>
                </div>
              </div>
            ) : (
              <p className="text-red-700">{result.error}</p>
            )}
          </div>
        )}
          </div>
          
          <div className="lg:col-span-1">
            <div className="sticky top-6">
              <MiniComandas />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}