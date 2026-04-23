import { useState, useEffect } from 'react';
import { CreditCard, Building2, Banknote, Smartphone, Bike, TrendingUp, Pencil, ChevronLeft, ChevronRight, BarChart3, MessageCircle, ArrowLeft, Clock, Wallet, Moon, Calendar, BadgeDollarSign } from 'lucide-react';

export default function ArqueoApp() {
  const [salesData, setSalesData] = useState(null);
  const [saldoCaja, setSaldoCaja] = useState(0);
  const [ingresosHoy, setIngresosHoy] = useState(0);
  const [currentDaysAgo, setCurrentDaysAgo] = useState(0);
  const [initialLoading, setInitialLoading] = useState(true);
  const [isTransitioning, setIsTransitioning] = useState(false);
  const [currentTime, setCurrentTime] = useState('');

  useEffect(() => {
    loadSalesData();
    loadSaldoCaja();
    const timer = setInterval(updateClock, 1000);
    const saldoTimer = setInterval(loadSaldoCaja, 15000);
    return () => {
      clearInterval(timer);
      clearInterval(saldoTimer);
    };
  }, []);

  useEffect(() => {
    loadSalesData(currentDaysAgo);
  }, [currentDaysAgo]);

  const updateClock = () => {
    const now = new Date();
    const time = now.toLocaleTimeString('es-CL', {
      timeZone: 'America/Santiago',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
    setCurrentTime(time);
  };

  const loadSalesData = async (daysAgo = 0) => {
    if (salesData) setIsTransitioning(true);
    try {
      const url = daysAgo > 0 ? `/api/get_sales_summary.php?days_ago=${daysAgo}&v=${Date.now()}` : `/api/get_sales_summary.php?v=${Date.now()}`;
      const response = await fetch(url, { signal: AbortSignal.timeout(30000) });
      const data = await response.json();
      if (data.success) {
        setSalesData(data);
        setInitialLoading(false);
        setTimeout(() => setIsTransitioning(false), 300);
      }
    } catch (error) {
      console.error('Error:', error);
      setInitialLoading(false);
      setIsTransitioning(false);
    }
  };

  const loadSaldoCaja = async () => {
    try {
      const response = await fetch('/api/get_saldo_caja.php');
      const data = await response.json();
      if (data.success) {
        setSaldoCaja(data.saldo_actual);
        setIngresosHoy(data.ingresos_automaticos_dia || 0);
      }
    } catch (error) {
      console.error('Error:', error);
    }
  };

  const openCajaModal = () => {
    window.dispatchEvent(new CustomEvent('openSaldoCajaModal'));
  };

  const showDetail = () => {
    if (!salesData) return;
    // Las fechas vienen en UTC del API, pasarlas directamente
    window.location.href = `/ventas-detalle?start=${encodeURIComponent(salesData.period.start)}&end=${encodeURIComponent(salesData.period.end)}`;
  };

  const sendWhatsApp = () => {
    if (!salesData) return;
    const fmt = (n) => Math.round(n).toLocaleString('es-CL');
    const totalRuta11 = salesData.total_general - (salesData.delivery_fees || 0);
    const reportUrl = `https://caja.laruta11.cl/arqueo-resumen?days_ago=${currentDaysAgo}`;

    let message = `> 📊 *ARQUEO DE CAJA - LA RUTA 11*%0A%0A`;
    message += `_${new Date().toLocaleDateString('es-CL')} - ${new Date().toLocaleTimeString('es-CL')}__%0A%0A`;
    message += `*💰 Resumen de ventas:*%0A`;
    message += `- *Total Ventas:* $${fmt(totalRuta11)}%0A`;
    message += `- *Pedidos:* ${salesData.total_orders}%0A`;
    message += `- *Saldo en Caja:* $${fmt(saldoCaja)}%0A%0A`;
    message += `> 📄 Ver reporte completo:%0A> ${reportUrl}`;

    window.open(`https://wa.me/56936227422?text=${message}`, '_blank');
  };

  const fmt = (n) => Math.round(n).toLocaleString('es-CL');

  if (initialLoading) {
    return (
      <div className="container">
        <div className="skeleton-header"></div>
        <div className="skeleton-grid">
          {[...Array(8)].map((_, i) => (
            <div key={i} className="skeleton-card">
              <div className="skeleton-line short"></div>
              <div className="skeleton-line long"></div>
              <div className="skeleton-line short"></div>
            </div>
          ))}
        </div>
        <style jsx>{`
          .skeleton-header {
            height: 80px;
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            animation: pulse 1.5s ease-in-out infinite;
          }
          .skeleton-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
          }
          .skeleton-card {
            background: white;
            padding: 16px;
            border-radius: 10px;
            height: 100px;
          }
          .skeleton-line {
            height: 12px;
            background: #e5e7eb;
            border-radius: 4px;
            margin-bottom: 8px;
            animation: pulse 1.5s ease-in-out infinite;
          }
          .skeleton-line.short { width: 60%; }
          .skeleton-line.long { width: 80%; height: 24px; }
          @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
          }
        `}</style>
      </div>
    );
  }

  if (!salesData) return null;

  const deliveryTotal = salesData.delivery_fees || 0;
  const deliveryCount = salesData.delivery_count || 0;
  const deliveryExtras = salesData.delivery_extras || 0;
  const totalRuta11 = salesData.total_general - deliveryTotal - deliveryExtras;
  const label = currentDaysAgo === 0 ? 'Turno Actual' : `Turno hace ${currentDaysAgo} día${currentDaysAgo > 1 ? 's' : ''}`;

  return (
    <div className="container">
      <div className="header">
        <div className="header-top">
          <h1><Wallet size={24} /> Arqueo de Caja</h1>
          <span className="current-time">
            <Clock size={16} /> {currentTime}
          </span>
        </div>
        <div className="period">
          <Moon size={14} /> {label}: {salesData.shift_hours}
          <span className="date-badge">
            <Calendar size={12} /> {salesData.shift_date}
          </span>
        </div>
      </div>

      <div className={`sales-list ${isTransitioning ? 'transitioning' : ''}`}>
        {[
          { icon: <CreditCard size={18} />, label: 'Tarjetas', data: salesData.summary.card },
          { icon: <Building2 size={18} />, label: 'Transferencia', data: salesData.summary.transfer },
          { icon: <Banknote size={18} />, label: 'Efectivo', data: salesData.summary.cash },
          { icon: <Smartphone size={18} />, label: 'Webpay', data: salesData.summary.webpay },
          { icon: <Bike size={18} />, label: 'PedidosYA Online', data: salesData.summary.pedidosya },
          ...(salesData.summary.pedidosya_cash && salesData.summary.pedidosya_cash.count > 0
            ? [{ icon: <Banknote size={18} />, label: 'PedidosYA Efectivo', data: salesData.summary.pedidosya_cash, highlight: 'pedidosya-cash' }]
            : []),
          { icon: <BadgeDollarSign size={18} />, label: 'Crédito RL6', data: salesData.summary.rl6_credit },
          { icon: <BadgeDollarSign size={18} />, label: 'Crédito R11', data: salesData.summary.r11_credit },
        ].map((item, i) => (
          <div key={i} className={`list-row ${item.highlight || ''}`}>
            <div className="list-row-left">
              <span className="list-icon">{item.icon}</span>
              <span className="list-label">{item.label}</span>
            </div>
            <div className="list-row-right">
              <span className="list-amount">${fmt(item.data.total)}</span>
              <span className="list-count">{item.data.count} pedidos</span>
            </div>
          </div>
        ))}

        {deliveryTotal > 0 && (
          <div className="list-row delivery-row">
            <div className="list-row-left">
              <span className="list-icon"><Bike size={18} /></span>
              <span className="list-label">Delivery</span>
            </div>
            <div className="list-row-right">
              <span className="list-amount">${fmt(deliveryTotal)}</span>
              <span className="list-count">{deliveryCount} deliverys{deliveryExtras > 0 ? ` · Extras $${fmt(deliveryExtras)}` : ''}</span>
            </div>
          </div>
        )}
      </div>

      <div className="summary-cards">
        <div className="card card-total">
          <div className="card-title">
            <TrendingUp size={16} /> TOTAL VENTAS
          </div>
          <div className="card-amount">${fmt(totalRuta11)}</div>
          <div className="card-count">{salesData.total_orders} pedidos</div>
        </div>

        <div className="card card-saldo" onClick={openCajaModal}>
          <div className="card-title">
            <Wallet size={16} /> Saldo en Caja
            <Pencil size={14} />
          </div>
          <div className="card-amount">${fmt(saldoCaja)}</div>
          <div className="card-count">Ingresos hoy: ${fmt(ingresosHoy)}</div>
        </div>
      </div>

      <div className="nav-buttons">
        <button className="btn btn-nav" onClick={() => setCurrentDaysAgo(currentDaysAgo + 1)}>
          <ChevronLeft size={20} /> Ayer
        </button>
        <button
          className="btn btn-nav"
          onClick={() => currentDaysAgo > 0 && setCurrentDaysAgo(currentDaysAgo - 1)}
          disabled={currentDaysAgo === 0}
        >
          Hoy <ChevronRight size={20} />
        </button>
      </div>

      <button className="btn btn-detail" onClick={showDetail}>
        <BarChart3 size={20} /> Ver Detalle de Ventas
      </button>

      <button className="btn btn-primary" onClick={sendWhatsApp}>
        <MessageCircle size={20} /> Enviar Arqueo por WhatsApp
      </button>

      <button className="btn btn-secondary" onClick={() => window.location.href = 'https://caja.laruta11.cl'}>
        <ArrowLeft size={20} /> Volver a Caja
      </button>

      <style jsx>{`
        .container {
          max-width: 600px;
          margin: 0 auto;
          padding: clamp(12px, 3vw, 20px);
        }
        .header {
          background: white;
          padding: clamp(14px, 3.5vw, 18px);
          border-radius: 12px;
          margin-bottom: clamp(12px, 3vw, 16px);
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .header-top {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 8px;
        }
        .header h1 {
          font-size: clamp(18px, 4.5vw, 22px);
          color: #333;
          display: flex;
          align-items: center;
          gap: 8px;
        }
        .period {
          font-size: clamp(11px, 2.8vw, 13px);
          color: #666;
          display: flex;
          align-items: center;
          gap: 8px;
          flex-wrap: wrap;
        }
        .date-badge {
          background: #f3f4f6;
          padding: 4px 8px;
          border-radius: 6px;
          font-size: clamp(10px, 2.5vw, 12px);
          display: flex;
          align-items: center;
          gap: 4px;
        }
        .current-time {
          color: #10b981;
          font-weight: 600;
          font-size: clamp(11px, 2.8vw, 13px);
          display: flex;
          align-items: center;
          gap: 4px;
          background: #ecfdf5;
          padding: 4px 8px;
          border-radius: 6px;
        }
        .loading {
          text-align: center;
          padding: 40px;
          color: #666;
        }
        .sales-list {
          background: white;
          border-radius: 12px;
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
          margin-bottom: clamp(12px, 3vw, 15px);
          overflow: hidden;
          transition: opacity 0.3s ease;
        }
        .sales-list.transitioning {
          opacity: 0.6;
          pointer-events: none;
        }
        .list-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: clamp(12px, 3vw, 16px);
          border-bottom: 1px solid #f3f4f6;
        }
        .list-row:last-child {
          border-bottom: none;
        }
        .list-row-left {
          display: flex;
          align-items: center;
          gap: 10px;
        }
        .list-icon {
          color: #6b7280;
          display: flex;
          align-items: center;
        }
        .list-label {
          font-size: clamp(13px, 3.2vw, 15px);
          font-weight: 600;
          color: #374151;
        }
        .list-row-right {
          text-align: right;
        }
        .list-amount {
          font-size: clamp(16px, 4vw, 20px);
          font-weight: 700;
          color: #111827;
          display: block;
        }
        .list-count {
          font-size: clamp(10px, 2.5vw, 12px);
          color: #9ca3af;
        }
        .list-row.pedidosya-cash {
          background: #fffbeb;
        }
        .list-row.pedidosya-cash .list-icon { color: #d97706; }
        .list-row.pedidosya-cash .list-label { color: #92400e; }
        .list-row.delivery-row {
          background: #fef3c7;
        }
        .list-row.delivery-row .list-icon { color: #d97706; }
        .list-row.delivery-row .list-label { color: #92400e; }
        .list-row.delivery-row .list-amount { color: #92400e; }
        .summary-cards {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: clamp(10px, 2.5vw, 15px);
          margin-bottom: clamp(12px, 3vw, 15px);
        }
        .card {
          background: white;
          padding: clamp(12px, 3vw, 16px);
          border-radius: 10px;
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
          transition: transform 0.2s;
        }
        .card:hover {
          transform: translateY(-2px);
        }
        .card-title {
          font-size: clamp(11px, 2.5vw, 13px);
          color: #666;
          margin-bottom: 6px;
          display: flex;
          align-items: center;
          gap: 4px;
        }
        .card-amount {
          font-size: clamp(20px, 5vw, 24px);
          font-weight: bold;
          color: #333;
          line-height: 1.2;
        }
        .card-count {
          font-size: clamp(10px, 2.5vw, 12px);
          color: #999;
          margin-top: 4px;
        }
          text-align: center;
        }
        .card-total {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: white;
        }
        .card-total .card-title {
          color: rgba(255, 255, 255, 0.9);
        }
        .card-total .card-amount {
          color: white;
          font-size: clamp(24px, 6vw, 32px);
        }
        .card-total .card-count {
          color: rgba(255, 255, 255, 0.8);
        }
        .card-saldo {
          background: linear-gradient(135deg, #10b981 0%, #059669 100%);
          color: white;
          cursor: pointer;
        }
        .card-saldo .card-title {
          color: rgba(255, 255, 255, 0.9);
        }
        .card-saldo .card-amount {
          color: white;
        }
        .card-saldo .card-count {
          color: rgba(255, 255, 255, 0.8);
        }
        .card-credit-rl6 {
          background: linear-gradient(135deg, #10b981 0%, #059669 100%);
          color: white;
          border: 2px solid #047857;
        }
        .card-credit-rl6 .card-title {
          color: rgba(255, 255, 255, 0.95);
          font-weight: 600;
        }
        .card-credit-rl6 .card-amount {
          color: white;
        }
        .card-credit-rl6 .card-count {
          color: rgba(255, 255, 255, 0.85);
        }
        .nav-buttons {
          display: flex;
          gap: 10px;
          margin-bottom: 10px;
        }
        .btn {
          width: 100%;
          padding: 16px;
          border: none;
          border-radius: 12px;
          font-size: 16px;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.3s;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
        }
        .btn-nav {
          background: #6b7280;
          color: white;
          flex: 1;
          padding: 12px;
        }
        .btn-nav:hover:not(:disabled) {
          background: #4b5563;
        }
        .btn-nav:disabled {
          opacity: 0.5;
          cursor: not-allowed;
        }
        .btn-detail {
          background: #8b5cf6;
          color: white;
          margin-bottom: 10px;
        }
        .btn-detail:hover {
          background: #7c3aed;
        }
        .btn-primary {
          background: #10b981;
          color: white;
          margin-bottom: 10px;
        }
        .btn-primary:hover {
          background: #059669;
        }
        .btn-secondary {
          background: #6b7280;
          color: white;
        }
        .btn-secondary:hover {
          background: #4b5563;
        }
      `}</style>
    </div>
  );
}
