import React, { useState, useEffect } from 'react';
import { 
  Activity, 
  RefreshCw, 
  Database, 
  Server, 
  Globe, 
  Shield, 
  Zap,
  CheckCircle,
  AlertTriangle,
  XCircle,
  Clock,
  Wifi,
  HardDrive,
  Code,
  CreditCard,
  ShoppingCart,
  Users,
  BarChart3,
  TestTube
} from 'lucide-react';

const API_CATEGORIES = {
  database: {
    title: 'Bases de Datos',
    icon: Database,
    color: 'blue'
  },
  core: {
    title: 'APIs Core',
    icon: Server,
    color: 'green'
  },
  products: {
    title: 'Gestión de Productos',
    icon: ShoppingCart,
    color: 'purple'
  },
  orders: {
    title: 'Órdenes y Ventas',
    icon: BarChart3,
    color: 'orange'
  },
  tuu: {
    title: 'APIs TUU',
    icon: CreditCard,
    color: 'indigo'
  },
  payments: {
    title: 'Pagos y Transacciones',
    icon: Shield,
    color: 'emerald'
  },
  external: {
    title: 'APIs Externas',
    icon: Globe,
    color: 'pink'
  }
};

const STATUS_CONFIG = {
  ok: {
    icon: CheckCircle,
    color: 'text-green-600',
    bg: 'bg-green-50',
    border: 'border-green-200',
    label: 'Operativo'
  },
  warning: {
    icon: AlertTriangle,
    color: 'text-yellow-600',
    bg: 'bg-yellow-50',
    border: 'border-yellow-200',
    label: 'Advertencia'
  },
  error: {
    icon: XCircle,
    color: 'text-red-600',
    bg: 'bg-red-50',
    border: 'border-red-200',
    label: 'Error'
  }
};

export default function ApiMonitor() {
  const [loading, setLoading] = useState(true);
  const [lastCheck, setLastCheck] = useState(null);
  const [apiData, setApiData] = useState({
    databases: {},
    apis: []
  });
  const [testResults, setTestResults] = useState([]);
  const [showTestPanel, setShowTestPanel] = useState(false);

  const checkStatus = async () => {
    try {
      setLoading(true);
      const response = await fetch('/api/api_status.php');
      const data = await response.json();
      
      if (data.success) {
        setApiData({
          databases: data.databases || {},
          apis: data.apis || []
        });
        setLastCheck(data.timestamp || new Date().toLocaleString());
      }
    } catch (error) {
      console.error('Error checking status:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    checkStatus();
    const interval = setInterval(checkStatus, 30000);
    return () => clearInterval(interval);
  }, []);

  const categorizeApis = (apis) => {
    const categorized = {};
    
    apis.forEach(api => {
      let category = 'core';
      
      // APIs TUU - más específico
      if (api.file.includes('tuu_') || api.file.includes('tuu.') || 
          api.name.toLowerCase().includes('tuu') || 
          api.file.includes('haulmer') ||
          api.description?.toLowerCase().includes('tuu')) {
        category = 'tuu';
      } else if (api.file.includes('product') || api.file.includes('categoria')) {
        category = 'products';
      } else if (api.file.includes('venta') || api.file.includes('order') || api.file.includes('dashboard')) {
        category = 'orders';
      } else if (api.file.includes('payment') || api.file.includes('pago')) {
        category = 'payments';
      } else if (api.file.includes('external') || api.file.includes('gemini') || api.file.includes('location')) {
        category = 'external';
      }
      
      if (!categorized[category]) {
        categorized[category] = [];
      }
      categorized[category].push(api);
    });
    
    return categorized;
  };

  const getOverallStats = () => {
    const allApis = [...Object.values(apiData.databases), ...apiData.apis];
    const total = allApis.length;
    const ok = allApis.filter(api => api.status === 'ok').length;
    const warning = allApis.filter(api => api.status === 'warning').length;
    const error = allApis.filter(api => api.status === 'error').length;
    
    return { total, ok, warning, error };
  };

  const testTuuApi = async (action, key = null) => {
    try {
      let url;
      if (action === 'clear_queue') {
        url = '/api/tuu_clear_queue.php';
      } else {
        url = `/api/tuu_test_real.php?action=${action}`;
        if (key) url += `&key=${key}`;
      }
      
      const response = await fetch(url);
      const data = await response.json();
      
      const newResult = {
        id: Date.now(),
        timestamp: new Date().toLocaleString(),
        action,
        success: data.success || data.result?.success || false,
        data
      };
      
      setTestResults(prev => [newResult, ...prev.slice(0, 9)]);
      setShowTestPanel(true);
    } catch (error) {
      const errorResult = {
        id: Date.now(),
        timestamp: new Date().toLocaleString(),
        action,
        success: false,
        error: error.message
      };
      setTestResults(prev => [errorResult, ...prev.slice(0, 9)]);
      setShowTestPanel(true);
    }
  };

  const runFullTuuTest = async () => {
    try {
      const response = await fetch('/api/tuu_full_test.php');
      const data = await response.json();
      
      const newResult = {
        id: Date.now(),
        timestamp: new Date().toLocaleString(),
        action: 'full_test',
        success: data.overall_status === 'success',
        data,
        isFullTest: true
      };
      
      setTestResults(prev => [newResult, ...prev.slice(0, 9)]);
      setShowTestPanel(true);
    } catch (error) {
      const errorResult = {
        id: Date.now(),
        timestamp: new Date().toLocaleString(),
        action: 'full_test',
        success: false,
        error: error.message,
        isFullTest: true
      };
      setTestResults(prev => [errorResult, ...prev.slice(0, 9)]);
      setShowTestPanel(true);
    }
  };

  const renderStatusCard = (item, category = 'core') => {
    const statusConfig = STATUS_CONFIG[item.status] || STATUS_CONFIG.error;
    const StatusIcon = statusConfig.icon;
    const categoryConfig = API_CATEGORIES[category];
    const CategoryIcon = categoryConfig?.icon || Server;
    const isTuuApi = category === 'tuu';

    return (
      <div key={item.name || item.file} className={`bg-white rounded-lg border ${statusConfig.border} p-4 hover:shadow-md transition-shadow`}>
        <div className="flex items-start justify-between mb-3">
          <div className="flex items-center gap-2">
            <div className={`p-2 rounded-lg ${statusConfig.bg}`}>
              <CategoryIcon className={`h-5 w-5 ${statusConfig.color}`} />
            </div>
            <div>
              <h3 className="font-semibold text-gray-900">{item.name}</h3>
              <p className="text-sm text-gray-500">{item.file}</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <StatusIcon className={`h-5 w-5 ${statusConfig.color}`} />
            <span className={`text-xs font-medium px-2 py-1 rounded-full ${statusConfig.bg} ${statusConfig.color}`}>
              {statusConfig.label}
            </span>
          </div>
        </div>
        
        <div className="space-y-2">
          <p className="text-sm text-gray-600">{item.message}</p>
          {item.response_time && (
            <div className="flex items-center gap-2 text-xs text-gray-500">
              <Clock className="h-3 w-3" />
              <span>Tiempo de respuesta: {item.response_time}ms</span>
            </div>
          )}
          
          {isTuuApi && (
            <div className="flex gap-1 mt-2">
              <button 
                onClick={() => testTuuApi('create_payment')}
                className="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200"
              >
                Test Crear
              </button>
              <button 
                onClick={() => testTuuApi('query_payment')}
                className="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200"
              >
                Test Query (Auto)
              </button>
              <button 
                onClick={() => testTuuApi('clear_queue')}
                className="text-xs bg-red-100 text-red-700 px-2 py-1 rounded hover:bg-red-200"
              >
                🧹 Limpiar Cola
              </button>
              <button 
                onClick={() => testTuuApi('setup_table')}
                className="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded hover:bg-purple-200"
              >
                Setup BD
              </button>
              {testResults.length > 0 && (
                <button 
                  onClick={() => setShowTestPanel(true)}
                  className="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded hover:bg-gray-200"
                >
                  Ver Tests ({testResults.length})
                </button>
              )}
            </div>
          )}
        </div>
      </div>
    );
  };

  const stats = getOverallStats();
  const categorizedApis = categorizeApis(apiData.apis);

  if (loading && !lastCheck) {
    return (
      <div className="min-h-screen bg-gray-50 p-6">
        <div className="max-w-7xl mx-auto">
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <span className="ml-3 text-gray-600">Verificando estado de APIs...</span>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-7xl mx-auto">
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 mb-2">Monitor de APIs</h1>
            <p className="text-gray-600">Estado en tiempo real del sistema</p>
          </div>
          <button 
            onClick={checkStatus}
            disabled={loading}
            className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
          >
            <RefreshCw className={`h-5 w-5 ${loading ? 'animate-spin' : ''}`} />
            Actualizar
          </button>
        </div>

        {/* Stats Overview */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total APIs</p>
                <p className="text-3xl font-bold text-gray-900">{stats.total}</p>
              </div>
              <div className="p-3 bg-blue-50 rounded-lg">
                <Activity className="h-6 w-6 text-blue-600" />
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Operativas</p>
                <p className="text-3xl font-bold text-green-600">{stats.ok}</p>
              </div>
              <div className="p-3 bg-green-50 rounded-lg">
                <CheckCircle className="h-6 w-6 text-green-600" />
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Advertencias</p>
                <p className="text-3xl font-bold text-yellow-600">{stats.warning}</p>
              </div>
              <div className="p-3 bg-yellow-50 rounded-lg">
                <AlertTriangle className="h-6 w-6 text-yellow-600" />
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Con Errores</p>
                <p className="text-3xl font-bold text-red-600">{stats.error}</p>
              </div>
              <div className="p-3 bg-red-50 rounded-lg">
                <XCircle className="h-6 w-6 text-red-600" />
              </div>
            </div>
          </div>
        </div>

        {/* Database Status */}
        {Object.keys(apiData.databases).length > 0 && (
          <div className="mb-8">
            <div className="flex items-center gap-2 mb-4">
              <Database className="h-6 w-6 text-blue-600" />
              <h2 className="text-xl font-semibold text-gray-900">Bases de Datos</h2>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {Object.entries(apiData.databases).map(([key, db]) => 
                renderStatusCard({ ...db, name: `BD ${key.toUpperCase()}` }, 'database')
              )}
            </div>
          </div>
        )}

        {/* Categorized APIs */}
        {Object.entries(categorizedApis).map(([category, apis]) => {
          const categoryConfig = API_CATEGORIES[category];
          const CategoryIcon = categoryConfig.icon;
          
          return (
            <div key={category} className="mb-8">
              <div className="flex items-center gap-2 mb-4">
                <CategoryIcon className="h-6 w-6 text-gray-600" />
                <h2 className="text-xl font-semibold text-gray-900">{categoryConfig.title}</h2>
                <span className="bg-gray-100 text-gray-600 text-sm px-2 py-1 rounded-full">
                  {apis.length}
                </span>
                {category === 'tuu' && (
                  <button 
                    onClick={runFullTuuTest}
                    className="ml-auto bg-indigo-600 text-white px-3 py-1 rounded-lg text-sm hover:bg-indigo-700 transition-colors flex items-center gap-1"
                  >
                    <TestTube className="h-4 w-4" />
                    Test Completo
                  </button>
                )}
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {apis.map(api => renderStatusCard(api, category))}
              </div>
            </div>
          );
        })}

        {/* Last Check */}
        {lastCheck && (
          <div className="text-center text-sm text-gray-500 mt-8 p-4 bg-white rounded-lg border border-gray-200">
            <div className="flex items-center justify-center gap-2">
              <Clock className="h-4 w-4" />
              <span>Última verificación: {lastCheck}</span>
            </div>
          </div>
        )}
        
        {/* Test Results Panel */}
        {showTestPanel && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[80vh] overflow-hidden">
              <div className="flex items-center justify-between p-4 border-b">
                <h3 className="text-lg font-semibold text-gray-900">Resultados Tests TUU</h3>
                <button 
                  onClick={() => setShowTestPanel(false)}
                  className="text-gray-400 hover:text-gray-600"
                >
                  <XCircle className="h-6 w-6" />
                </button>
              </div>
              
              <div className="p-4 max-h-96 overflow-y-auto">
                {testResults.length === 0 ? (
                  <p className="text-gray-500 text-center py-8">No hay tests ejecutados</p>
                ) : (
                  <div className="space-y-3">
                    {testResults.map((result) => (
                      <div key={result.id} className={`border rounded-lg p-4 ${
                        result.success ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'
                      } ${result.isFullTest ? 'border-2' : ''}`}>
                        <div className="flex items-center justify-between mb-2">
                          <div className="flex items-center gap-2">
                            {result.success ? (
                              <CheckCircle className="h-5 w-5 text-green-600" />
                            ) : (
                              <XCircle className="h-5 w-5 text-red-600" />
                            )}
                            <span className="font-medium">{result.action}</span>
                          </div>
                          <span className="text-sm text-gray-500">{result.timestamp}</span>
                        </div>
                        
                        {result.error ? (
                          <p className="text-red-600 text-sm">{result.error}</p>
                        ) : result.isFullTest ? (
                          <div className="space-y-3">
                            <div className="flex items-center justify-between">
                              <span className="font-medium text-gray-700">Estado General: {result.data.summary?.overall_health}</span>
                              <span className="text-sm text-gray-500">
                                {result.data.summary?.successful}/{result.data.summary?.total_tests} tests exitosos
                              </span>
                            </div>
                            
                            <div className="space-y-2">
                              {result.data.tests?.map((test, idx) => (
                                <div key={idx} className={`flex items-center justify-between p-2 rounded ${
                                  test.status === 'success' ? 'bg-green-100' : 
                                  test.status === 'warning' ? 'bg-yellow-100' : 'bg-red-100'
                                }`}>
                                  <span className="text-sm font-medium">{test.test}</span>
                                  <div className="flex items-center gap-2">
                                    {test.status === 'success' ? (
                                      <CheckCircle className="h-4 w-4 text-green-600" />
                                    ) : test.status === 'warning' ? (
                                      <AlertTriangle className="h-4 w-4 text-yellow-600" />
                                    ) : (
                                      <XCircle className="h-4 w-4 text-red-600" />
                                    )}
                                    <span className="text-xs text-gray-600">{test.message}</span>
                                  </div>
                                </div>
                              ))}
                            </div>
                            
                            <details className="text-sm">
                              <summary className="cursor-pointer text-gray-600 hover:text-gray-800">Ver detalles técnicos</summary>
                              <pre className="mt-2 p-2 bg-gray-100 rounded text-xs overflow-x-auto">
                                {JSON.stringify(result.data, null, 2)}
                              </pre>
                            </details>
                          </div>
                        ) : (
                          <div className="space-y-2">
                            {result.data.result && (
                              <div>
                                <p className="text-sm font-medium text-gray-700">HTTP: {result.data.result.http_code}</p>
                                {result.data.result.response?.code && (
                                  <p className="text-sm text-red-600">Error: {result.data.result.response.code} - {result.data.result.response.message}</p>
                                )}
                              </div>
                            )}
                            <details className="text-sm">
                              <summary className="cursor-pointer text-gray-600 hover:text-gray-800">Ver detalles</summary>
                              <pre className="mt-2 p-2 bg-gray-100 rounded text-xs overflow-x-auto">
                                {JSON.stringify(result.data, null, 2)}
                              </pre>
                            </details>
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </div>
              
              <div className="p-4 border-t bg-gray-50">
                <button 
                  onClick={() => setTestResults([])}
                  className="text-sm text-gray-600 hover:text-gray-800"
                >
                  Limpiar historial
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}