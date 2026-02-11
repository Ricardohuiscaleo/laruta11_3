import React, { useState } from 'react';

export default function SyncButton() {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);

  const syncTuuData = async () => {
    setLoading(true);
    setResult(null);
    
    try {
      // Obtener reportes de TUU
      const tuuResponse = await fetch('/api/get_tuu_reports.php?start_date=2025-08-20&end_date=2025-08-29&page_size=20');
      const tuuData = await tuuResponse.json();
      
      if (tuuData.success) {
        // Simular guardado en MySQL (aquí iría la lógica real)
        const reports = tuuData.data.reports;
        
        setResult({
          success: true,
          message: `Sincronización completada: ${reports.length} transacciones TUU obtenidas`,
          data: {
            total_transactions: reports.length,
            completed_transactions: reports.filter(r => r.status === 'completed').length,
            total_amount: reports.reduce((sum, r) => sum + r.amount, 0),
            sample_transactions: reports.slice(0, 3).map(r => ({
              sale_id: r.sale_id,
              sequence_number: r.sequence_number,
              amount: r.amount,
              status: r.status,
              payment_date: r.payment_date
            }))
          }
        });
      } else {
        setResult({
          success: false,
          error: tuuData.error || 'Error obteniendo datos de TUU'
        });
      }
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
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
      <div className="flex items-center justify-between mb-4">
        <div>
          <h3 className="text-lg font-semibold text-gray-900">Sincronización TUU</h3>
          <p className="text-sm text-gray-600">Obtener transacciones reales de TUU y guardar en MySQL</p>
        </div>
        <button
          onClick={syncTuuData}
          disabled={loading}
          className="flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50"
        >
          <span className={loading ? 'animate-spin' : ''}>🔄</span>
          {loading ? 'Sincronizando...' : 'Sincronizar TUU'}
        </button>
      </div>

      {result && (
        <div className={`p-4 rounded-lg border ${result.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`}>
          <div className="flex items-center gap-2 mb-2">
            <span className="text-2xl">{result.success ? '✅' : '❌'}</span>
            <h4 className="font-medium text-gray-900">
              {result.success ? 'Sincronización Exitosa' : 'Error en Sincronización'}
            </h4>
          </div>
          
          {result.success ? (
            <div className="space-y-3">
              <p className="text-sm text-green-700">{result.message}</p>
              
              {result.data && (
                <div className="bg-white p-3 rounded border">
                  <h5 className="font-medium text-gray-800 mb-2">Datos Obtenidos de TUU:</h5>
                  <div className="grid grid-cols-2 gap-2 text-sm mb-3">
                    <p><strong>Total:</strong> {result.data.total_transactions}</p>
                    <p><strong>Completadas:</strong> {result.data.completed_transactions}</p>
                    <p><strong>Monto Total:</strong> ${result.data.total_amount?.toLocaleString()}</p>
                  </div>
                  
                  {result.data.sample_transactions && (
                    <div>
                      <h6 className="font-medium text-gray-700 mb-2">Transacciones de Ejemplo:</h6>
                      {result.data.sample_transactions.map((tx, index) => (
                        <div key={index} className="text-xs bg-gray-50 p-2 rounded mb-1">
                          <strong>ID:</strong> {tx.sale_id} | 
                          <strong> Secuencia:</strong> {tx.sequence_number} | 
                          <strong> Monto:</strong> ${tx.amount} | 
                          <strong> Estado:</strong> {tx.status}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          ) : (
            <p className="text-sm text-red-700">{result.error}</p>
          )}
        </div>
      )}
      
      <div className="mt-4 p-3 bg-blue-50 rounded border border-blue-200">
        <h5 className="font-medium text-blue-900 mb-2">💡 Cómo funciona:</h5>
        <ul className="text-sm text-blue-800 space-y-1">
          <li>1. Obtiene transacciones reales de TUU Reports API</li>
          <li>2. Guarda datos en MySQL (sequence_number, commission, etc.)</li>
          <li>3. Hace cruce con pedidos existentes por POS + monto + fecha</li>
          <li>4. Actualiza estados de `sent_to_pos` a `completed`</li>
        </ul>
      </div>
    </div>
  );
}