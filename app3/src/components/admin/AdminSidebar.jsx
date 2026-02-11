import { BarChart3, Utensils, Package, CreditCard, FileText } from 'lucide-react';

export default function AdminSidebar({ currentView, onViewChange }) {
  const menuItems = [
    { id: 'dashboard', label: 'Dashboard', icon: BarChart3 },
    { id: 'products', label: 'Productos', icon: Utensils },
    { id: 'orders', label: 'Pedidos', icon: Package },
    { id: 'payments', label: 'Pagos', icon: CreditCard },
    { id: 'technical-report', label: 'Informe Técnico', icon: FileText }
  ];

  return (
    <aside className="sidebar">
      <div className="sidebar-header">
        <div className="logo">
          <Utensils size={20} />
          La Ruta 11
        </div>
      </div>
      <nav className="nav">
        {menuItems.map(item => {
          const Icon = item.icon;
          return (
            <button
              key={item.id}
              className={`nav-item ${currentView === item.id ? 'active' : ''}`}
              onClick={() => onViewChange(item.id)}
            >
              <Icon size={20} className="nav-icon" />
              {item.label}
            </button>
          );
        })}
      </nav>
    </aside>
  );
}