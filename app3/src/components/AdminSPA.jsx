import React, { useState, useEffect } from 'react';
import AdminDashboard from './AdminDashboard.jsx';
import ProductsManager from './ProductsManager.jsx';
import TUUTransactions from './TUUTransactions.jsx';
import TechnicalReport from './TechnicalReport.jsx';

const AdminSPA = () => {
  const [currentView, setCurrentView] = useState('dashboard');
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      const response = await fetch('/api/check_admin_auth.php');
      const data = await response.json();
      
      if (data.authenticated) {
        setUser(data.user);
        setLoading(false);
      } else {
        window.location.href = '/admin/login';
      }
    } catch (error) {
      window.location.href = '/admin/login';
    }
  };

  const logout = async () => {
    await fetch('/api/admin_logout.php');
    window.location.href = '/admin/login';
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Verificando acceso...</p>
        </div>
      </div>
    );
  }

  const menuItems = [
    { id: 'dashboard', label: '📊 Dashboard', icon: '📊' },
    { id: 'products', label: '🍔 Productos', icon: '🍔' },
    { id: 'orders', label: '📋 Órdenes', icon: '📋' },
    { id: 'payments', label: '💳 Pagos TUU', icon: '💳' },
    { id: 'technical-report', label: '📋 Informe Técnico', icon: '📋' },
    { id: 'monitor', label: '📈 Monitor', icon: '📈' }
  ];

  const renderContent = () => {
    switch (currentView) {
      case 'dashboard':
        return <AdminDashboard />;
      case 'products':
        return <ProductsManager />;
      case 'payments':
        return <TUUTransactions />;
      case 'technical-report':
        return <TechnicalReport />;
      case 'orders':
        return <div className="p-6"><h2 className="text-2xl font-bold">Órdenes</h2><p>Gestión de órdenes en desarrollo...</p></div>;
      case 'monitor':
        return <div className="p-6"><h2 className="text-2xl font-bold">Monitor</h2><p>Sistema de monitoreo en desarrollo...</p></div>;
      default:
        return <AdminDashboard />;
    }
  };

  return (
    <div className="min-h-screen bg-gray-100 flex">
      {/* Sidebar */}
      <div className="w-64 bg-white shadow-lg">
        <div className="p-6 border-b">
          <h1 className="text-xl font-bold text-gray-800">🚚 La Ruta 11</h1>
          <p className="text-sm text-gray-600">Panel Admin</p>
        </div>
        
        <nav className="mt-6">
          {menuItems.map((item) => (
            <button
              key={item.id}
              onClick={() => setCurrentView(item.id)}
              className={`w-full text-left px-6 py-3 hover:bg-gray-50 transition-colors ${
                currentView === item.id ? 'bg-blue-50 border-r-2 border-blue-500 text-blue-600' : 'text-gray-700'
              }`}
            >
              <span className="mr-3">{item.icon}</span>
              {item.label}
            </button>
          ))}
        </nav>

        <div className="absolute bottom-0 w-64 p-6 border-t">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-700">{user}</p>
              <p className="text-xs text-gray-500">Administrador</p>
            </div>
            <button
              onClick={logout}
              className="text-red-600 hover:text-red-800 text-sm"
            >
              Salir
            </button>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="flex-1 overflow-auto">
        {renderContent()}
      </div>
    </div>
  );
};

export default AdminSPA;