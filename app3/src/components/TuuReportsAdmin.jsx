import React, { useState, useEffect } from 'react';

const TuuReportsAdmin = () => {
  const [reports, setReports] = useState([]);
  const [devices, setDevices] = useState([]);
  const [loading, setLoading] = useState(false);
  const [filters, setFilters] = useState({
    start_date: '',
    end_date: '',
    serial_number: '',
    page: 1,
    page_size: 10,
    sort_by: 'date',
    sort_order: 'desc'
  });
  const [pagination, setPagination] = useState({
    totalItems: 0,
    totalPages: 0,
    currentPage: 1
  });

  useEffect(() => {
    loadDevices();
    // Establecer fechas por defecto (último mes)
    const today = new Date();
    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
    setFilters(prev => ({
      ...prev,
      start_date: lastMonth.toISOString().split('T')[0],
      end_date: today.toISOString().split('T')[0]
    }));
  }, []);

  const loadDevices = async () => {
    try {
      const response = await fetch('/api/tuu/get_devices.php');
      const data = await response.json();
      if (data.success) {
        setDevices(data.devices || []);
      }
    } catch (error) {
      console.error('Error loading devices:', error);
    }
  };

  const loadReports = async () => {
    if (!filters.start_date || !filters.end_date) {
      alert('Por favor selecciona las fechas de inicio y fin');
      return;
    }

    setLoading(true);
    try {
      // Usar la nueva API súper rápida desde MySQL
      const queryParams = new URLSearchParams({
        start_date: filters.start_date,
        end_date: filters.end_date,
        page: filters.page,
        limit: filters.page_size,
        sort_by: filters.sort_by,
        sort_order: filters.sort_order
      }).toString();
      
      const response = await fetch(`/api/tuu/get_from_mysql.php?${queryParams}`);
      const data = await response.json();
      
      if (data.success && data.data) {
        const allTransactions = data.data.all_transactions || [];
        
        // Filtrar por dispositivo si se especifica
        const filteredReports = filters.serial_number ? 
          allTransactions.filter(t => 
            (t.posSerialNumber || t.pos_serial_number) === filters.serial_number
          ) : allTransactions;
        
        setReports(filteredReports);
        setPagination({
          totalItems: data.data.pagination?.total_records || filteredReports.length,
          totalPages: data.data.pagination?.total_pages || 1,
          currentPage: data.data.pagination?.current_page || 1
        });
      } else {
        alert(`Error: ${data.error}`);
        setReports([]);
      }
    } catch (error) {
      console.error('Error loading reports:', error);
      alert('Error al cargar reportes');
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (field, value) => {
    setFilters(prev => ({
      ...prev,
      [field]: value,
      page: field !== 'page' ? 1 : value // Reset page when other filters change
    }));
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
      style: 'currency',
      currency: 'CLP'
    }).format(amount);
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString('es-CL');
  };

  const handleSort = (field) => {
    const newOrder = filters.sort_by === field && filters.sort_order === 'desc' ? 'asc' : 'desc';
    setFilters(prev => ({
      ...prev,
      sort_by: field,
      sort_order: newOrder
    }));
    // Recargar datos automáticamente
    setTimeout(loadReports, 100);
  };

  const getSortIcon = (field) => {
    if (filters.sort_by !== field) return '↕️';
    return filters.sort_order === 'asc' ? '↑' : '↓';
  };

  const getStatusBadge = (status) => {
    const statusColors = {
      'Completed': 'bg-green-100 text-green-800',
      'completed': 'bg-green-100 text-green-800',
      'Pending': 'bg-yellow-100 text-yellow-800',
      'Failed': 'bg-red-100 text-red-800'
    };
    
    return (
      <span className={`px-2 py-1 rounded-full text-xs font-medium ${statusColors[status] || 'bg-gray-100 text-gray-800'}`}>
        {status}
      </span>
    );
  };

  const exportToCSV = () => {
    if (reports.length === 0) return;
    
    const headers = ['ID Venta', 'Cliente', 'Teléfono', 'Fecha', 'Monto', 'Tipo', 'Estado', 'Items'];
    const csvData = reports.map(report => [
      report.saleId || report.sale_id || report.order_reference,
      report.customer_name || 'Cliente POS',
      report.customer_phone || '',
      formatDate(report.paymentDataTime || report.payment_date_time || report.created_at),
      report.amount,
      report.typeTransaction || report.transaction_type || report.payment_source,
      report.status,
      report.extraData?.items?.map(item => `${item.name} (${item.quantity})`).join('; ') || report.detailed_items || report.product_name || ''
    ]);
    
    const csvContent = [headers, ...csvData]
      .map(row => row.map(cell => `"${cell}"`).join(','))
      .join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `reportes_tuu_${filters.start_date}_${filters.end_date}.csv`;
    link.click();
  };

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Reportes de Transacciones TUU</h1>
        <p className="text-gray-600">Consulta y exporta reportes de transacciones realizadas</p>
      </div>

      {/* Filtros */}
      <div className="bg-white p-6 rounded-lg shadow-sm border mb-6">
        <h2 className="text-lg font-semibold mb-4">Filtros de Búsqueda</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Fecha Inicio *
            </label>
            <input
              type="date"
              value={filters.start_date}
              onChange={(e) => handleFilterChange('start_date', e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Fecha Fin *
            </label>
            <input
              type="date"
              value={filters.end_date}
              onChange={(e) => handleFilterChange('end_date', e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Dispositivo POS
            </label>
            <select
              value={filters.serial_number}
              onChange={(e) => handleFilterChange('serial_number', e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Todos los dispositivos</option>
              {devices.map((device, index) => (
                <option key={index} value={device.serialNumber || device}>
                  {device.serialNumber || device}
                </option>
              ))}
            </select>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Registros por página
            </label>
            <select
              value={filters.page_size}
              onChange={(e) => handleFilterChange('page_size', parseInt(e.target.value))}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value={10}>10</option>
              <option value={20}>20</option>
            </select>
          </div>
        </div>
        
        <div className="flex gap-3 mt-4">
          <button
            onClick={loadReports}
            disabled={loading}
            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? 'Cargando...' : 'Buscar Reportes'}
          </button>
          
          {reports.length > 0 && (
            <button
              onClick={exportToCSV}
              className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
            >
              Exportar CSV
            </button>
          )}
        </div>
      </div>

      {/* Resultados */}
      {reports.length > 0 && (
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-4 border-b">
            <div className="flex justify-between items-center">
              <h3 className="text-lg font-semibold">
                Resultados ({pagination.totalItems} transacciones)
              </h3>
              <div className="text-sm text-gray-600">
                Página {pagination.currentPage} de {pagination.totalPages}
              </div>
            </div>
          </div>
          
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Venta</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer" onClick={() => handleSort('date')}>Fecha {getSortIcon('date')}</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer" onClick={() => handleSort('amount')}>Monto {getSortIcon('amount')}</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Medio</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {reports.map((report, index) => {
                  const isOnline = report.payment_source === 'online';
                  return (
                    <tr key={report.saleId || report.sale_id || report.order_reference || index} className="hover:bg-gray-50">
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">
                        {report.saleId || report.sale_id || report.order_reference}
                        {isOnline && <span className="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">APP</span>}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-900">
                        {report.customer_name || 'Cliente POS'}
                        {report.customer_phone && <div className="text-xs text-gray-500">{report.customer_phone}</div>}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-500">
                        {formatDate(report.paymentDataTime || report.payment_date_time || report.created_at)}
                      </td>
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">
                        {formatCurrency(report.amount)}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-500">
                        {report.typeTransaction || report.transaction_type || report.payment_source}
                      </td>
                      <td className="px-4 py-3">
                        {getStatusBadge(report.status)}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-500">
                        {report.payment_source === 'pos' ? 'POS' : 'APP'}
                      </td>

                      <td className="px-4 py-3 text-sm text-gray-500">
                        {isOnline ? 
                          (report.detailed_items || report.product_name || 'Pedido Online') :
                          (report.extraData?.items?.map(item => `${item.name} (${item.quantity})`).join(', ') || 'N/A')
                        }
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          
          {/* Paginación */}
          {pagination.totalPages > 1 && (
            <div className="px-4 py-3 border-t bg-gray-50 flex items-center justify-between">
              <div className="text-sm text-gray-700">
                Mostrando {((pagination.currentPage - 1) * filters.page_size) + 1} a {Math.min(pagination.currentPage * filters.page_size, pagination.totalItems)} de {pagination.totalItems} resultados
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => handleFilterChange('page', pagination.currentPage - 1)}
                  disabled={pagination.currentPage <= 1}
                  className="px-3 py-1 text-sm border rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100"
                >
                  Anterior
                </button>
                <span className="px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-md">
                  {pagination.currentPage}
                </span>
                <button
                  onClick={() => handleFilterChange('page', pagination.currentPage + 1)}
                  disabled={pagination.currentPage >= pagination.totalPages}
                  className="px-3 py-1 text-sm border rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100"
                >
                  Siguiente
                </button>
              </div>
            </div>
          )}
        </div>
      )}
      
      {/* Estado vacío */}
      {!loading && reports.length === 0 && filters.start_date && filters.end_date && (
        <div className="bg-white rounded-lg shadow-sm border p-8 text-center">
          <div className="text-gray-500 mb-2">
            <svg className="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-1">No se encontraron reportes</h3>
          <p className="text-gray-500">No hay transacciones para el rango de fechas seleccionado.</p>
        </div>
      )}
    </div>
  );
};

export default TuuReportsAdmin;