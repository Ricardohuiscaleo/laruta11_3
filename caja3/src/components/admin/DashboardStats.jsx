import { TrendingUp, Package, Utensils, Clock } from 'lucide-react';

export default function DashboardStats() {
  const stats = [
    { icon: TrendingUp, label: 'Ventas Hoy', value: '$0', color: 'green' },
    { icon: Package, label: 'Pedidos', value: '0', color: 'blue' },
    { icon: Utensils, label: 'Productos', value: '0', color: 'purple' },
    { icon: Clock, label: 'Pendientes', value: '0', color: 'orange' }
  ];

  return (
    <div className="dashboard-stats">
      {stats.map((stat, index) => {
        const Icon = stat.icon;
        return (
          <div key={index} className="stat-card">
            <h3>
              <Icon size={16} className="icon" />
              {stat.label}
            </h3>
            <p className="stat-value">{stat.value}</p>
          </div>
        );
      })}
    </div>
  );
}