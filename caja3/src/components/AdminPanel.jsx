import { useState, useEffect } from 'react';

function CompanyLogo() {
  return (
    <div className="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
      <span className="text-orange-600 font-bold text-sm">R11</span>
    </div>
  );
}

export default function AdminPanel() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [credentials, setCredentials] = useState({ username: '', password: '' });
  const [stats, setStats] = useState({ orders_today: 0, sales_today: 0, total_products: 0, low_stock: 0 });
  const [activeTab, setActiveTab] = useState('dashboard');
  const [products, setProducts] = useState([]);
  const [orders, setOrders] = useState([]);

  const adminUser = 'admin';
  const adminPass = 'ruta11admin';

  useEffect(() => {
    if (isAuthenticated) {
      fetchDashboard();
      fetchProducts();
    }
  }, [isAuthenticated]);

  const handleLogin = (e) => {
    e.preventDefault();
    if (credentials.username === adminUser && credentials.password === adminPass) {
      setIsAuthenticated(true);
    } else {
      alert('Credenciales incorrectas');
    }
  };

  const fetchDashboard = async () => {
    try {
      const response = await fetch('/api/admin_dashboard.php');
      const data = await response.json();
      if (data.success) {
        setStats(data.stats);
        setOrders(data.recent_orders || []);
      }
    } catch (error) {
      console.error('Error:', error);
    }
  };

  const fetchProducts = async () => {
    try {
      const response = await fetch('/api/products.php');
      const data = await response.json();
      if (data.success) {
        setProducts(data.products || []);
      }
    } catch (error) {
      console.error('Error:', error);
    }
  };

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-lg shadow-lg p-8 w-full max-w-md">
          <div className="text-center mb-8">
            <div className="w-16 h-16 bg-orange-100 rounded-lg flex items-center justify-center mx-auto mb-4">
              <span className="text-orange-600 font-bold text-xl">🍔</span>
            </div>
            <h1 className="text-2xl font-bold text-gray-900 mb-2">La Ruta 11 Admin</h1>
            <p className="text-gray-600">Panel de administración del restaurante</p>
          </div>
          
          <form onSubmit={handleLogin} className="space-y-6">
            <div>
              <label className="block text-gray-700 text-sm font-medium mb-2">Usuario</label>
              <input
                type="text"
                value={credentials.username}
                onChange={(e) => setCredentials({...credentials, username: e.target.value})}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                placeholder="Ingresa tu usuario"
                required
              />
            </div>
            
            <div>
              <label className="block text-gray-700 text-sm font-medium mb-2">Contraseña</label>
              <input
                type="password"
                value={credentials.password}
                onChange={(e) => setCredentials({...credentials, password: e.target.value})}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                placeholder="Ingresa tu contraseña"
                required
              />
            </div>
            
            <button
              type="submit"
              className="w-full bg-orange-600 text-white py-3 rounded-lg font-medium hover:bg-orange-700 transition-colors"
            >
              Iniciar Sesión
            </button>
          </form>
          
          <div className="mt-6 text-center text-gray-500 text-sm">
            <p>Demo: admin / ruta11admin</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Sidebar */}
      <div className="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg">
        <div className="flex h-16 items-center px-6 border-b">
          <div className="flex items-center">
            <CompanyLogo />
            <div className="ml-3">
              <h1 className="text-lg font-semibold text-gray-900">La Ruta 11</h1>
              <p className="text-xs text-gray-500">Panel Admin</p>
            </div>
          </div>
        </div>
        
        <nav className="mt-6 px-3">
          {[
            { id: 'dashboard', label: '📊 Dashboard', icon: '📊' },
            { id: 'orders', label: '🛒 Órdenes', icon: '🛒' },
            { id: 'products', label: '📦 Productos', icon: '📦' },
            { id: 'payments', label: '💳 Pagos TUU', icon: '💳' },
            { id: 'inventory', label: '📋 Inventario', icon: '📋' },
            { id: 'customers', label: '👥 Clientes', icon: '👥' }
          ].map((item) => (
            <button
              key={item.id}
              onClick={() => setActiveTab(item.id)}
              className={`w-full flex items-center px-3 py-2 mb-1 text-sm font-medium rounded-lg transition-colors ${
                activeTab === item.id
                  ? 'bg-orange-100 text-orange-900'
                  : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
              }`}
            >
              {item.label}
            </button>
          ))}
        </nav>
        
        <div className="absolute bottom-0 left-0 right-0 p-4 border-t">
          <div className="flex items-center justify-between">
            <a href="/" className="text-sm text-gray-500 hover:text-gray-700">Ver App</a>
            <button
              onClick={() => setIsAuthenticated(false)}
              className="text-sm text-red-600 hover:text-red-700"
            >
              Salir
            </button>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="ml-64">
        <header className="bg-white shadow-sm border-b">
          <div className="px-6 py-4">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-xl font-semibold text-gray-900">
                  {activeTab === 'dashboard' ? 'Dashboard' : 
                   activeTab === 'orders' ? 'Gestión de Órdenes' : 
                   activeTab === 'products' ? 'Gestión de Productos' :
                   activeTab === 'payments' ? 'Pagos TUU.cl' :
                   activeTab === 'inventory' ? 'Control de Inventario' :
                   activeTab === 'customers' ? 'Gestión de Clientes' : 'Panel'}
                </h2>
                <p className="text-sm text-gray-500 mt-1">
                  {activeTab === 'dashboard' ? 'Resumen de tu restaurante' : 
                   activeTab === 'orders' ? 'Administra las órdenes de clientes' : 
                   activeTab === 'products' ? 'Gestiona tu menú y productos' :
                   activeTab === 'payments' ? 'Monitorea los pagos con TUU.cl' :
                   activeTab === 'inventory' ? 'Controla ingredientes y stock' :
                   activeTab === 'customers' ? 'Administra tu base de clientes' : ''}
                </p>
              </div>
              <div className="flex items-center space-x-3">
                <div className="flex items-center text-sm text-gray-500">
                  <div className="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                  Sistema Activo
                </div>
              </div>
            </div>
          </div>
        </header>

        <main className="p-6">
          {activeTab === 'dashboard' && (
            <div className="space-y-6">
              {/* Stats Cards */}
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div className="bg-white rounded-lg p-6 shadow-sm">
                  <div className="flex items-center">
                    <div className="p-2 bg-blue-100 rounded-lg">
                      <span className="text-2xl">📊</span>
                    </div>
                    <div className="ml-4">
                      <p className="text-sm font-medium text-gray-600">Órdenes Hoy</p>
                      <p className="text-2xl font-bold text-gray-900">{stats.orders_today}</p>
                    </div>
                  </div>
                </div>

                <div className="bg-white rounded-lg p-6 shadow-sm">
                  <div className="flex items-center">
                    <div className="p-2 bg-green-100 rounded-lg">
                      <span className="text-2xl">💰</span>
                    </div>
                    <div className="ml-4">
                      <p className="text-sm font-medium text-gray-600">Ventas Hoy</p>
                      <p className="text-2xl font-bold text-gray-900">${Number(stats.sales_today).toLocaleString()}</p>
                    </div>
                  </div>
                </div>

                <div className="bg-white rounded-lg p-6 shadow-sm">
                  <div className="flex items-center">
                    <div className="p-2 bg-purple-100 rounded-lg">
                      <span className="text-2xl">📦</span>
                    </div>
                    <div className="ml-4">
                      <p className="text-sm font-medium text-gray-600">Productos</p>
                      <p className="text-2xl font-bold text-gray-900">{stats.total_products}</p>
                    </div>
                  </div>
                </div>

                <div className="bg-white rounded-lg p-6 shadow-sm">
                  <div className="flex items-center">
                    <div className="p-2 bg-red-100 rounded-lg">
                      <span className="text-2xl">⚠️</span>
                    </div>
                    <div className="ml-4">
                      <p className="text-sm font-medium text-gray-600">Stock Bajo</p>
                      <p className="text-2xl font-bold text-gray-900">{stats.low_stock}</p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Recent Orders */}
              <div className="bg-white rounded-lg shadow-sm">
                <div className="px-6 py-4 border-b">
                  <h3 className="text-lg font-medium text-gray-900">Órdenes Recientes</h3>
                </div>
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Orden</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {orders.map((order) => (
                        <tr key={order.id}>
                          <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {order.order_number}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {order.customer_name || 'Cliente'}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${Number(order.total_amount).toLocaleString()}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                              order.status === 'completed' ? 'bg-green-100 text-green-800' :
                              order.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                              'bg-gray-100 text-gray-800'
                            }`}>
                              {order.status}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'products' && (
            <div className="bg-white rounded-lg shadow-sm p-6">
              <div className="flex justify-between items-center mb-6">
                <h3 className="text-lg font-medium text-gray-900">Gestión de Productos</h3>
                <button className="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700">
                  + Nuevo Producto
                </button>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {products.map((product) => (
                  <div key={product.id} className="border rounded-lg p-4">
                    <h4 className="font-medium text-gray-900">{product.name}</h4>
                    <p className="text-sm text-gray-500 mt-1">{product.description}</p>
                    <div className="flex justify-between items-center mt-4">
                      <span className="text-lg font-bold text-green-600">
                        ${Number(product.price).toLocaleString()}
                      </span>
                      <span className={`text-sm ${product.stock_quantity <= product.min_stock_level ? 'text-red-600' : 'text-gray-600'}`}>
                        Stock: {product.stock_quantity}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {activeTab === 'payments' && (
            <div className="bg-white rounded-lg shadow-sm p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-6">Pagos TUU.cl</h3>
              <div className="text-center py-12">
                <span className="text-4xl mb-4 block">💳</span>
                <p className="text-gray-500">Monitoreo de pagos TUU.cl en desarrollo</p>
              </div>
            </div>
          )}

          {['orders', 'inventory', 'customers'].includes(activeTab) && (
            <div className="bg-white rounded-lg shadow-sm p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-6">
                {activeTab === 'orders' ? 'Gestión de Órdenes' :
                 activeTab === 'inventory' ? 'Control de Inventario' :
                 'Gestión de Clientes'}
              </h3>
              <div className="text-center py-12">
                <span className="text-4xl mb-4 block">
                  {activeTab === 'orders' ? '🛒' :
                   activeTab === 'inventory' ? '📋' : '👥'}
                </span>
                <p className="text-gray-500">Sección en desarrollo</p>
              </div>
            </div>
          )}
        </main>
      </div>
    </div>
  );
}