import React, { useState, useEffect } from 'react';

export default function TUUTransactions() {
  const [loading, setLoading] = useState(true);
  const [transactions, setTransactions] = useState([]);
  const [stats, setStats] = useState({
    total_transactions: 0,
    total_sales: 0,
    completed_transactions: 0
  });
  const [lastUpdate, setLastUpdate] = useState(null);

  const loadTransactions = async () => {
    try {
      setLoading(true);
      
      const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000);
      const endDate = yesterday.toISOString().split('T')[0];
      const startDate = new Date(Date.now() - 8 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
      
      const queryParams = new URLSearchParams({
        start_date: startDate,
        end_date: endDate
      });
      
      // Usar la nueva API súper rápida desde MySQL
      const response = await fetch(`/api/tuu/get_from_mysql.php?${queryParams}`);
      const data = await response.json();
      
      if (data.success && data.data) {
        const reports = data.data.all_transactions || [];
        const stats = data.data.combined_stats || {};
        
        setTransactions(reports);
        setStats({
          total_transactions: stats.total_transactions || 0,
          total_sales: stats.total_revenue || 0,
          completed_transactions: stats.pos_transactions + stats.online_transactions || 0
        });
        setLastUpdate(new Date());
      } else {
        setTransactions([]);
        setStats({ total_transactions: 0, total_sales: 0, completed_transactions: 0 });
      }
    } catch (error) {
      console.error('Error:', error);
      setTransactions([]);
      setStats({ total_transactions: 0, total_sales: 0, completed_transactions: 0 });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadTransactions();
  }, []);

  const formatAmount = (amount) => {
    return `$${parseInt(amount).toLocaleString('es-CL')}`;
  };

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-CL', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    }) + ', ' + date.toLocaleTimeString('es-CL', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: true
    });
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-full mx-auto">
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 mb-2">Transacciones TUU - Reportes Completos</h1>
            <p className="text-gray-600">Datos completos de la API de Branch Reports</p>
            {lastUpdate && (
              <p className="text-sm text-gray-500">
                Última actualización: {lastUpdate.toLocaleTimeString('es-CL')}
              </p>
            )}
          </div>
          <button 
            onClick={loadTransactions}
            disabled={loading}
            className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            <span className={loading ? 'animate-spin' : ''}>🔄</span>
            Actualizar
          </button>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <div className="bg-white rounded-lg shadow-sm border p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Transacciones</p>
                <p className="text-2xl font-bold text-gray-900">{stats.total_transactions}</p>
              </div>
              <span className="text-2xl">📊</span>
            </div>
          </div>
          <div className="bg-white rounded-lg shadow-sm border p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Ventas Totales</p>
                <p className="text-2xl font-bold text-green-600">{formatAmount(stats.total_sales)}</p>
              </div>
              <span className="text-2xl">💰</span>
            </div>
          </div>
          <div className="bg-white rounded-lg shadow-sm border p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Completadas</p>
                <p className="text-2xl font-bold text-blue-600">{stats.completed_transactions}</p>
              </div>
              <span className="text-2xl">✅</span>
            </div>
          </div>
        </div>

        {/* Transactions Table */}
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <h3 className="text-lg font-semibold text-gray-900">Transacciones Detalladas</h3>
          </div>
          
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">ID VENTA</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">CLIENTE</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">FECHA</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">MONTO</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">ESTADO</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">MEDIO</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">TUU ID</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">DETALLES</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {loading ? (
                  <tr>
                    <td colSpan="8" className="px-4 py-12 text-center">
                      <span className="text-2xl animate-spin mr-2">🔄</span>
                      <span className="text-gray-600">Cargando...</span>
                    </td>
                  </tr>
                ) : transactions.length === 0 ? (
                  <tr>
                    <td colSpan="8" className="px-4 py-12 text-center text-gray-500">
                      <span className="text-4xl mb-4 block">📋</span>
                      <p>No hay transacciones disponibles</p>
                    </td>
                  </tr>
                ) : (
                  transactions.map((tx, index) => {
                    const items = tx.items || [];
                    return (
                      <tr key={tx.sale_id || tx.order_reference || index} className="hover:bg-gray-50">
                        {/* ID Venta */}
                        <td className="px-4 py-4">
                          <div className="font-medium text-gray-900">
                            {tx.sale_id || tx.order_reference}
                          </div>
                          {tx.payment_source === 'app' && (
                            <span className="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">APP</span>
                          )}
                        </td>
                        
                        {/* Cliente */}
                        <td className="px-4 py-4">
                          <div className="text-sm font-medium text-gray-900">
                            {tx.customer_name || 'Cliente POS'}
                          </div>
                          {tx.customer_phone && (
                            <div className="text-xs text-gray-500">{tx.customer_phone}</div>
                          )}
                        </td>
                        
                        {/* Fecha */}
                        <td className="px-4 py-4">
                          <div className="text-sm text-gray-900">
                            {formatDate(tx.payment_date_time || tx.created_at)}
                          </div>
                        </td>
                        
                        {/* Monto */}
                        <td className="px-4 py-4">
                          <div className="font-bold text-green-600">
                            {formatAmount(tx.amount)}
                          </div>
                        </td>
                        
                        {/* Estado */}
                        <td className="px-4 py-4">
                          <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                            tx.status.toLowerCase() === 'completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                          }`}>
                            {tx.status}
                          </span>
                        </td>
                        
                        {/* Medio */}
                        <td className="px-4 py-4">
                          <div className="text-sm text-gray-900">
                            {tx.payment_source === 'pos' ? 'POS' : 'APP'}
                          </div>
                          {tx.transaction_type && (
                            <div className="text-xs text-gray-500">{tx.transaction_type}</div>
                          )}
                        </td>
                        
                        {/* TUU ID */}
                        <td className="px-4 py-4">
                          <div className="text-sm text-gray-500">
                            {tx.tuu_transaction_id || '-'}
                          </div>
                        </td>
                        
                        {/* Items */}
                        <td className="px-4 py-4">
                          <div className="text-sm text-gray-900">
                            {tx.detailed_items || tx.product_name || 'Ver detalles'}
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}