import { CreditCard, Plus } from 'lucide-react';

export default function PaymentsView() {
  return (
    <div className="payments-container">
      <div className="payments-header">
        <h2>
          <CreditCard size={20} className="icon" />
          Sistema de Pagos
        </h2>
        <p>Gestión de pagos y facturación</p>
      </div>
      
      <div className="payments-placeholder">
        <div className="placeholder-card">
          <h3>
            <Plus size={18} className="icon" />
            En Desarrollo
          </h3>
          <p>Esta sección incluirá:</p>
          <ul>
            <li>Procesamiento de pagos</li>
            <li>Historial de transacciones</li>
            <li>Reportes financieros</li>
            <li>Integración con pasarelas</li>
          </ul>
        </div>
      </div>
    </div>
  );
}