import { useState, useEffect } from 'react';

export default function SaldoCajaModal({ isOpen, onClose }) {
  const [activeTab, setActiveTab] = useState('movimiento');
  const [tipoMovimiento, setTipoMovimiento] = useState('retiro');
  const [monto, setMonto] = useState('');
  const [motivo, setMotivo] = useState('');
  const [historial, setHistorial] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (isOpen) {
      loadHistorial();
    }
  }, [isOpen]);

  const loadHistorial = async () => {
    try {
      const response = await fetch(`/api/get_historial_caja.php?limit=20&t=${Date.now()}`);
      const data = await response.json();
      if (data.success) {
        setHistorial(data.movimientos || []);
      }
    } catch (error) {
      console.error('Error cargando historial:', error);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!monto || !motivo) return;

    setLoading(true);
    try {
      const response = await fetch('/api/registrar_movimiento_caja.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          tipo: tipoMovimiento,
          monto: parseFloat(monto),
          motivo,
          usuario: 'Cajero'
        })
      });

      const data = await response.json();
      if (data.success) {
        alert(`✅ ${tipoMovimiento === 'ingreso' ? 'Ingreso' : 'Retiro'} registrado correctamente\nNuevo saldo: $${Math.round(data.saldo_nuevo).toLocaleString('es-CL')}`);
        setMonto('');
        setMotivo('');
        loadHistorial();
        onClose();
      } else {
        alert('❌ Error: ' + data.error);
      }
    } catch (error) {
      console.error('Error guardando movimiento:', error);
      alert('Error al guardar el movimiento');
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CL', {
      style: 'currency',
      currency: 'CLP'
    }).format(amount);
  };

  const formatDate = (dateString) => {
    const utcStr = dateString.replace(' ', 'T') + 'Z';
    const date = new Date(utcStr);
    return date.toLocaleString('es-CL', {
      timeZone: 'America/Santiago',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true
    });
  };

  if (!isOpen) return null;

  return (
    <div className="modal active" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h2 className="modal-title">💰 Saldo en Caja</h2>
          <button className="close-btn" onClick={onClose}>×</button>
        </div>

        <div className="tabs">
          <button
            className={`tab ${activeTab === 'movimiento' ? 'active' : ''}`}
            onClick={() => setActiveTab('movimiento')}
          >
            Movimiento
          </button>
          <button
            className={`tab ${activeTab === 'historial' ? 'active' : ''}`}
            onClick={() => setActiveTab('historial')}
          >
            Historial
          </button>
        </div>

        {activeTab === 'movimiento' ? (
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label className="form-label">Tipo de Movimiento</label>
              <select
                className="form-input"
                value={tipoMovimiento}
                onChange={(e) => setTipoMovimiento(e.target.value)}
              >
                <option value="retiro">Retiro</option>
                <option value="ingreso">Ingreso</option>
              </select>
            </div>

            <div className="form-group">
              <label className="form-label">Monto</label>
              <input
                type="number"
                className="form-input"
                placeholder="Ej: 10990"
                value={monto}
                onChange={(e) => setMonto(e.target.value)}
                min="0"
                step="10"
                required
              />
            </div>

            <div className="form-group">
              <label className="form-label">Motivo</label>
              <textarea
                className="form-input"
                placeholder="Ej: Retiro para cuadre de caja, Compra de insumos, etc."
                value={motivo}
                onChange={(e) => setMotivo(e.target.value)}
                rows="3"
                required
              />
            </div>

            <button
              type="submit"
              className={`submit-btn ${tipoMovimiento === 'ingreso' ? 'submit-ingreso' : 'submit-retiro'}`}
              disabled={loading}
            >
              {loading ? 'Guardando...' : `Registrar ${tipoMovimiento === 'ingreso' ? 'Ingreso' : 'Retiro'}`}
            </button>
          </form>
        ) : (
          <div className="historial-container">
            {historial.length === 0 ? (
              <p style={{ textAlign: 'center', color: '#9ca3af', padding: '20px' }}>
                No hay movimientos registrados
              </p>
            ) : (
              historial.map((item) => {
                const isAuto = item.usuario === 'Sistema' && item.order_reference;
                const tipoLabel = item.tipo === 'ingreso' ? '↗️ INGRESO' : '↘️ RETIRO';
                const badge = isAuto ? ' AUTO' : '';
                return (
                  <div key={item.id} className="historial-item">
                    <div className="historial-header">
                      <span className={`historial-tipo ${item.tipo}`}>
                        {tipoLabel}
                        {isAuto && <span style={{background: '#3b82f6', color: 'white', padding: '2px 6px', borderRadius: '4px', fontSize: '10px', marginLeft: '6px'}}>AUTO</span>}
                      </span>
                      <span className="historial-monto">{formatCurrency(item.monto)}</span>
                    </div>
                    <div className="historial-motivo">{item.motivo}</div>
                    <div className="historial-footer">
                      <div>
                        <div className="historial-fecha">{formatDate(item.fecha_movimiento)}</div>
                        <div className="historial-usuario">{item.usuario}</div>
                      </div>
                      <div className="historial-saldo">
                        {formatCurrency(item.saldo_anterior)} → {formatCurrency(item.saldo_nuevo)}
                      </div>
                    </div>
                  </div>
                );
              })
            )}
          </div>
        )}
      </div>

      <style jsx>{`
        .modal {
          display: none;
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.5);
          z-index: 1000;
          align-items: center;
          justify-content: center;
        }
        .modal.active {
          display: flex;
        }
        .modal-content {
          background: white;
          border-radius: 16px;
          padding: 24px;
          max-width: 500px;
          width: 90%;
          max-height: 90vh;
          overflow: hidden;
          display: flex;
          flex-direction: column;
        }
        .modal-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 20px;
        }
        .modal-title {
          font-size: 20px;
          font-weight: bold;
        }
        .close-btn {
          background: #f3f4f6;
          border: none;
          width: 32px;
          height: 32px;
          border-radius: 50%;
          cursor: pointer;
          font-size: 20px;
        }
        .tabs {
          display: flex;
          gap: 8px;
          margin-bottom: 20px;
        }
        .tab {
          flex: 1;
          padding: 12px;
          border: none;
          background: #f3f4f6;
          border-radius: 8px;
          cursor: pointer;
          font-weight: 600;
        }
        .tab.active {
          background: #10b981;
          color: white;
        }
        .form-group {
          margin-bottom: 16px;
        }
        .form-label {
          display: block;
          margin-bottom: 6px;
          font-weight: 600;
          color: #374151;
        }
        .form-input {
          width: 100%;
          padding: 12px;
          border: 2px solid #e5e7eb;
          border-radius: 8px;
          font-size: 16px;
          font-family: inherit;
        }
        .form-input:focus {
          outline: none;
          border-color: #10b981;
        }
        .submit-btn {
          width: 100%;
          padding: 14px;
          border: none;
          border-radius: 8px;
          font-weight: 600;
          font-size: 16px;
          cursor: pointer;
        }
        .submit-btn:disabled {
          opacity: 0.5;
          cursor: not-allowed;
        }
        .submit-ingreso {
          background: #10b981;
          color: white;
        }
        .submit-retiro {
          background: #ef4444;
          color: white;
        }
        .historial-container {
          max-height: 60vh;
          overflow-y: auto;
          padding-right: 8px;
          border: 2px solid #e5e7eb;
          border-radius: 8px;
          padding: 12px;
          background: #fafafa;
        }
        .historial-container::-webkit-scrollbar {
          width: 12px;
        }
        .historial-container::-webkit-scrollbar-track {
          background: #e5e7eb;
          border-radius: 10px;
          border: 1px solid #d1d5db;
        }
        .historial-container::-webkit-scrollbar-thumb {
          background: #10b981;
          border-radius: 10px;
          border: 2px solid #059669;
        }
        .historial-item {
          padding: 16px;
          background: white;
          border: 2px solid #e5e7eb;
          border-radius: 12px;
          margin-bottom: 12px;
          box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
          transition: transform 0.2s;
        }
        .historial-item:hover {
          transform: translateY(-2px);
          box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .historial-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 8px;
          padding-bottom: 8px;
          border-bottom: 3px solid #10b981;
        }
        .historial-tipo {
          font-weight: 700;
          font-size: 16px;
          display: flex;
          align-items: center;
          gap: 6px;
        }
        .historial-tipo.ingreso {
          color: #10b981;
        }
        .historial-tipo.retiro {
          color: #ef4444;
        }
        .historial-monto {
          font-weight: bold;
          font-size: 20px;
        }
        .historial-motivo {
          color: #1f2937;
          font-size: 14px;
          margin-bottom: 8px;
          line-height: 1.4;
          background: #fef3c7;
          padding: 12px;
          border-radius: 8px;
          border: 1px solid #fbbf24;
        }
        .historial-footer {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-top: 8px;
          padding-top: 8px;
          border-top: 2px solid #e5e7eb;
        }
        .historial-fecha {
          color: #9ca3af;
          font-size: 12px;
        }
        .historial-usuario {
          color: #6b7280;
          font-size: 12px;
          font-weight: 500;
        }
        .historial-saldo {
          font-size: 13px;
          color: #6b7280;
        }
      `}</style>
    </div>
  );
}
