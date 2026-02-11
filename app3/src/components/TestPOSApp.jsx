import React, { useState } from 'react';
import { CreditCard, Loader2, CheckCircle, XCircle, AlertTriangle, RefreshCw, Trash2 } from 'lucide-react';

export default function TestPOSApp() {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [amount, setAmount] = useState(1000);
  const [description, setDescription] = useState('Test desde App');
  const [selectedDevice, setSelectedDevice] = useState('pos1');

  const sendPaymentToPOS = async () => {
    setLoading(true);
    setResult(null);

    try {
      const response = await fetch('/api/tuu_payment_gateway.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          amount: parseInt(amount),
          description: description,
          device: selectedDevice,
          dteType: 0
        })
      });

      const data = await response.json();
      setResult(data);
    } catch (error) {
      setResult({
        success: false,
        error: error.message
      });
    } finally {
      setLoading(false);
    }
  };

  const checkPaymentStatus = async (paymentId) => {
    if (!paymentId) return;
    
    setLoading(true);
    try {
      const response = await fetch(`/api/tuu_test_real.php?action=query_payment&key=${paymentId}`);
      const data = await response.json();
      
      setResult(prev => ({
        ...prev,
        statusCheck: data
      }));
    } catch (error) {
      console.error('Error checking status:', error);
    } finally {
      setLoading(false);
    }
  };

  const clearQueue = async () => {
    setLoading(true);
    try {
      const response = await fetch('/api/tuu_payment_gateway.php?action=clear_queue', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          device: selectedDevice
        })
      });

      const data = await response.json();
      setResult({
        success: data.success,
        message: data.message,
        action: 'clear_queue',
        device_used: data.device
      });
    } catch (error) {
      setResult({
        success: false,
        error: error.message,
        action: 'clear_queue'
      });
    } finally {
      setLoading(false);
    }
  };

  const getStatusIcon = (success, error) => {
    if (success) return <CheckCircle className="h-6 w-6 text-green-600" />;
    if (error) return <XCircle className="h-6 w-6 text-red-600" />;
    return <AlertTriangle className="h-6 w-6 text-yellow-600" />;
  };

  const getStatusColor = (success, error) => {
    if (success) return 'border-green-200 bg-green-50';
    if (error) return 'border-red-200 bg-red-50';
    return 'border-yellow-200 bg-yellow-50';
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-2xl mx-auto">
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div className="flex items-center gap-3 mb-6">
            <CreditCard className="h-8 w-8 text-blue-600" />
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Test POS Real</h1>
              <p className="text-gray-600">Enviar cobro real a la máquina POS</p>
            </div>
          </div>

          {/* Formulario */}
          <div className="space-y-4 mb-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Monto (CLP)
              </label>
              <input
                type="number"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                min="100"
                max="99999999"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Descripción
              </label>
              <input
                type="text"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                maxLength="50"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Dispositivo POS
              </label>
              <select
                value={selectedDevice}
                onChange={(e) => setSelectedDevice(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="pos1">POS Principal (6010B232541610747)</option>
                <option value="pos2">POS Secundario (6010B232541609909)</option>
              </select>
            </div>
          </div>

          {/* Botones */}
          <div className="space-y-3">
            <button
              onClick={sendPaymentToPOS}
              disabled={loading || !amount || !description}
              className="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              {loading ? (
                <>
                  <Loader2 className="h-5 w-5 animate-spin" />
                  Enviando a POS...
                </>
              ) : (
                <>
                  <CreditCard className="h-5 w-5" />
                  Enviar Cobro a POS
                </>
              )}
            </button>
            
            <button
              onClick={clearQueue}
              disabled={loading}
              className="w-full bg-orange-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              {loading ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Verificando...
                </>
              ) : (
                <>
                  <AlertTriangle className="h-4 w-4" />
                  Verificar Estado Cola
                </>
              )}
            </button>
          </div>

          {/* Resultado */}
          {result && (
            <div className={`mt-6 p-4 rounded-lg border ${getStatusColor(result.success, result.error)}`}>
              <div className="flex items-start gap-3">
                {getStatusIcon(result.success, result.error)}
                <div className="flex-1">
                  <h3 className="font-medium text-gray-900 mb-2">
                    {result.success ? 'Cobro Enviado Exitosamente' : 'Error al Enviar Cobro'}
                  </h3>
                  
                  {result.success && result.paymentRequestId && (
                    <div className="space-y-2 mb-4">
                      <p className="text-sm text-gray-700">
                        <strong>ID de Pago:</strong> {result.paymentRequestId}
                      </p>
                      <p className="text-sm text-gray-700">
                        <strong>Monto:</strong> ${result.amount?.toLocaleString()} CLP
                      </p>
                      <p className="text-sm text-gray-700">
                        <strong>Estado:</strong> Enviado al POS - Esperando confirmación del cliente
                      </p>
                      
                      <button
                        onClick={() => checkPaymentStatus(result.paymentRequestId)}
                        disabled={loading}
                        className="mt-2 bg-gray-100 text-gray-700 px-3 py-1 rounded text-sm hover:bg-gray-200 flex items-center gap-1"
                      >
                        <RefreshCw className="h-4 w-4" />
                        Verificar Estado
                      </button>
                    </div>
                  )}

                  {result.error && (
                    <div className="space-y-2">
                      <p className="text-sm text-red-700">
                        <strong>Error:</strong> {result.message || result.error}
                      </p>
                      {result.code === 'MR-180' && (
                        <p className="text-sm text-yellow-700">
                          💡 <strong>Recomendación:</strong> La cola del POS está llena. Espera 5-10 minutos e intenta nuevamente.
                        </p>
                      )}
                    </div>
                  )}

                  {result.statusCheck && (
                    <div className="mt-4 p-3 bg-gray-100 rounded">
                      <h4 className="font-medium text-gray-900 mb-2">Estado del Pago:</h4>
                      <pre className="text-xs text-gray-600 overflow-x-auto">
                        {JSON.stringify(result.statusCheck, null, 2)}
                      </pre>
                    </div>
                  )}

                  <details className="mt-4">
                    <summary className="cursor-pointer text-sm text-gray-600 hover:text-gray-800">
                      Ver respuesta completa
                    </summary>
                    <pre className="mt-2 p-3 bg-gray-100 rounded text-xs overflow-x-auto">
                      {JSON.stringify(result, null, 2)}
                    </pre>
                  </details>
                </div>
              </div>
            </div>
          )}

          {/* Instrucciones */}
          <div className="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <h3 className="font-medium text-blue-900 mb-2">📋 Instrucciones:</h3>
            <ul className="text-sm text-blue-800 space-y-1">
              <li>1. Configura el monto y descripción del cobro</li>
              <li>2. Selecciona el dispositivo POS a usar</li>
              <li>3. Haz clic en "Enviar Cobro a POS"</li>
              <li>4. El cobro aparecerá en la pantalla del POS físico</li>
              <li>5. El cliente puede pagar con tarjeta en el POS</li>
              <li>6. Usa "Verificar Estado" para ver el resultado</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}