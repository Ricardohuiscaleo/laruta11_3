import { Package, Plus } from 'lucide-react';

export default function OrdersView() {
  return (
    <div className="orders-container">
      <div className="orders-header">
        <h2>
          <Package size={20} className="icon" />
          Gestión de Pedidos
        </h2>
        <p>Sistema de pedidos en desarrollo</p>
      </div>
      
      <div className="orders-placeholder">
        <div className="placeholder-card">
          <h3>
            <Plus size={18} className="icon" />
            Próximamente
          </h3>
          <p>Esta sección incluirá:</p>
          <ul>
            <li>Lista de pedidos activos</li>
            <li>Historial de pedidos</li>
            <li>Estados de preparación</li>
            <li>Tiempos de entrega</li>
          </ul>
        </div>
      </div>
    </div>
  );
}