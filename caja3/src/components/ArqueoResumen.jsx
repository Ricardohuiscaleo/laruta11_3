import { useState, useEffect } from 'react';
import { Calendar, Clock, Moon, CalendarDays, DollarSign, Banknote, CreditCard, Building2, Bike, Smartphone, TrendingUp, Wallet, FileText, ArrowDown, ArrowUp, User, ChevronLeft, ChevronRight, Search, ChevronDown as ChevronDownIcon, Truck, Home } from 'lucide-react';

export default function ArqueoResumen() {
  const [salesData, setSalesData] = useState(null);
  const [cajaData, setCajaData] = useState(null);
  const [movimientos, setMovimientos] = useState([]);
  const [ventas, setVentas] = useState([]);
  const [ventasStats, setVentasStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [daysAgo, setDaysAgo] = useState(0);
  const [selectedMonth, setSelectedMonth] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [showIngredients, setShowIngredients] = useState(false);
  const [ingredientConsumption, setIngredientConsumption] = useState([]);

  useEffect(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const monthParam = urlParams.get('month');
    const yearParam = urlParams.get('year');
    
    if (monthParam && yearParam) {
      setSelectedMonth({ month: parseInt(monthParam), year: parseInt(yearParam) });
      setDaysAgo(null);
    } else if (urlParams.get('month') === 'current') {
      const now = new Date();
      setSelectedMonth({ month: now.getMonth() + 1, year: now.getFullYear() });
      setDaysAgo(null);
    } else {
      const days = parseInt(urlParams.get('days_ago') || '0');
      setDaysAgo(days);
      setSelectedMonth(null);
    }
  }, []);

  useEffect(() => {
    if (selectedMonth || daysAgo >= 0) loadData();
  }, [daysAgo, selectedMonth]);

  const loadData = async () => {
    try {
      let param;
      if (selectedMonth) {
        param = `month=${selectedMonth.month}&year=${selectedMonth.year}`;
      } else {
        param = `days_ago=${daysAgo}`;
      }
      
      console.log('📅 Cargando datos con parámetro:', param);
      
      const [salesRes, cajaRes, movRes, ventasRes] = await Promise.all([
        fetch(`/api/get_sales_summary.php?${param}`),
        fetch('/api/get_saldo_caja.php'),
        fetch(`/api/get_movimientos_caja.php?${param}`),
        fetch(`/api/get_ventas_turno.php?${param}`)
      ]);

      const url = selectedMonth 
        ? `?month=${selectedMonth.month}&year=${selectedMonth.year}`
        : `?days_ago=${daysAgo}`;
      window.history.replaceState({}, '', url);

      const sales = await salesRes.json();
      const caja = await cajaRes.json();
      const mov = await movRes.json();
      const ventasData = await ventasRes.json();

      if (sales.success && caja.success) {
        setSalesData(sales);
        setCajaData(caja);
        setMovimientos(mov.success ? (mov.movimientos || []) : []);
        setVentas(ventasData.success ? (ventasData.ventas || []) : []);
        setVentasStats(ventasData.success ? {
          pickup_count: ventasData.pickup_count || 0,
          delivery_count: ventasData.delivery_count || 0,
          total_orders: ventasData.total_orders || 0,
          total_cost: ventasData.total_cost || 0
        } : null);
        setLoading(false);
        
        // Cargar ingredientes en segundo plano
        if (sales.period?.start && sales.period?.end) {
          fetch(`/api/get_sales_detail.php?start_date=${encodeURIComponent(sales.period.start)}&end_date=${encodeURIComponent(sales.period.end)}&t=${Date.now()}`)
            .then(res => res.json())
            .then(detail => {
              if (detail.success && detail.stats?.ingredient_consumption) {
                setIngredientConsumption(detail.stats.ingredient_consumption);
              }
            })
            .catch(err => console.log('Ingredientes no disponibles'));
        }
      }
      console.log('Movimientos:', mov);
    } catch (error) {
      console.error('Error:', error);
      setLoading(false);
    }
  };

  const fmt = (n) => Math.round(n).toLocaleString('es-CL');

  const normalize = (str) => {
    return str.toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\s]/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  };

  const similarity = (a, b) => {
    const longer = a.length > b.length ? a : b;
    const shorter = a.length > b.length ? b : a;
    if (longer.length === 0) return 1.0;
    const editDistance = (s1, s2) => {
      s1 = s1.toLowerCase();
      s2 = s2.toLowerCase();
      const costs = [];
      for (let i = 0; i <= s1.length; i++) {
        let lastValue = i;
        for (let j = 0; j <= s2.length; j++) {
          if (i === 0) costs[j] = j;
          else if (j > 0) {
            let newValue = costs[j - 1];
            if (s1.charAt(i - 1) !== s2.charAt(j - 1))
              newValue = Math.min(Math.min(newValue, lastValue), costs[j]) + 1;
            costs[j - 1] = lastValue;
            lastValue = newValue;
          }
        }
        if (i > 0) costs[s2.length] = lastValue;
      }
      return costs[s2.length];
    };
    return (longer.length - editDistance(longer, shorter)) / longer.length;
  };

  const matchesSearch = (item, isMovimiento) => {
    if (!searchTerm) return true;
    const term = normalize(searchTerm);
    const searchableText = isMovimiento
      ? normalize(`${item.motivo} ${item.usuario} ${item.monto} ${item.tipo}`)
      : normalize(`${item.order_number} ${item.customer_name} ${item.product_name} ${item.customer_notes || ''} ${item.payment_method} ${item.installment_amount}`);
    
    if (searchableText.includes(term)) return true;
    
    const words = searchableText.split(' ');
    return words.some(word => similarity(word, term) > 0.7);
  };

  const filteredMovimientos = movimientos.filter(m => matchesSearch(m, true));
  const filteredVentas = ventas.filter(v => matchesSearch(v, false));

  const navigateDay = (direction) => {
    setSelectedMonth(null);
    setDaysAgo(prev => Math.max(0, (prev || 0) + direction));
  };

  const getCurrentMonthName = () => {
    const date = new Date();
    return date.toLocaleDateString('es-CL', { month: 'long' });
  };

  const showCurrentMonth = () => {
    const now = new Date();
    setSelectedMonth({ month: now.getMonth() + 1, year: now.getFullYear() });
    setDaysAgo(null);
  };

  const showSpecificMonth = (monthOffset) => {
    const today = new Date();
    const targetDate = new Date(today.getFullYear(), today.getMonth() + monthOffset, 1);
    const month = targetDate.getMonth() + 1;
    const year = targetDate.getFullYear();
    console.log(`📅 Seleccionando mes: ${month}/${year}`);
    setSelectedMonth({ month, year });
    setDaysAgo(null);
  };

  const getMonthName = (offset) => {
    const date = new Date();
    date.setMonth(date.getMonth() + offset);
    return date.toLocaleDateString('es-CL', { month: 'long' });
  };

  if (loading) {
    return (
      <div className="loading">
        <div className="spinner"></div>
        <div>Cargando datos...</div>
      </div>
    );
  }

  if (!salesData || !cajaData) {
    return (
      <div className="error">
        ❌ Error al cargar datos
      </div>
    );
  }

  const d = salesData.summary;
  const deliveryTotal = salesData.delivery_fees || 0;
  const deliveryCount = salesData.delivery_count || 0;
  const deliveryExtras = salesData.delivery_extras || 0;
  const totalRuta11 = salesData.total_general - deliveryTotal - deliveryExtras;
  const now = new Date();

  return (
    <div>
      <style>{`
        * { margin: 0; padding: 0; box-sizing: border-box; }
        .page {
          min-height: 100vh;
          display: flex;
          flex-direction: column;
        }
        .header {
          background: #000;
          padding: 12px;
          border-bottom: 1px solid #333;
          position: sticky;
          top: 0;
          z-index: 10;
        }
        .header-top {
          display: flex;
          align-items: center;
          gap: 12px;
          margin-bottom: 8px;
        }
        .header-left {
          flex: 1;
          display: flex;
          align-items: center;
          gap: 8px;
          font-size: 12px;
          color: #9ca3af;
          flex-wrap: wrap;
        }
        .header-nav {
          display: flex;
          gap: 8px;
          align-items: center;
          font-size: 12px;
          overflow-x: auto;
        }
        .nav-btn {
          background: #1f2937;
          color: #9ca3af;
          border: none;
          border-radius: 4px;
          padding: 4px 6px;
          cursor: pointer;
          font-size: 10px;
          font-weight: 600;
          display: flex;
          align-items: center;
          gap: 4px;
          transition: all 0.2s;
          white-space: nowrap;
        }
        .nav-btn:hover {
          background: #374151;
          color: #fff;
        }
        .nav-btn:disabled {
          background: #111827;
          color: #4b5563;
          cursor: not-allowed;
        }
        .month-btn {
          background: #1f2937;
          color: #10b981;
          border: none;
          border-radius: 4px;
          padding: 4px 8px;
          cursor: pointer;
          font-size: 10px;
          font-weight: 700;
          text-transform: capitalize;
          transition: all 0.2s;
          white-space: nowrap;
        }
        .month-btn:hover {
          background: #374151;
        }
        .back-btn {
          background: none;
          border: none;
          color: #fbbf24;
          cursor: pointer;
          padding: 0;
          display: flex;
          align-items: center;
          transition: color 0.2s;
        }
        .back-btn:hover {
          color: #fcd34d;
        }
        .header-title {
          font-size: 14px;
          font-weight: 700;
          color: #fff;
        }
        .header-sep {
          color: #4b5563;
        }
        .header-value {
          color: #fff;
          font-weight: 600;
        }
        .header-value.green {
          color: #10b981;
        }
        .header-value.red {
          color: #ef4444;
        }
        .header-value.blue {
          color: #3b82f6;
        }

        .body {
          flex: 1;
          background: #f8fafc;
          padding: 4px;
        }
        .content {
          max-width: 1200px;
          margin: 0 auto;
        }
        .section { margin-bottom: 20px; }
        .section-title {
          font-size: 14px;
          font-weight: 900;
          color: #1e293b;
          margin-bottom: 12px;
          text-transform: uppercase;
          letter-spacing: 0.5px;
          padding-bottom: 6px;
          border-bottom: 2px solid #3b82f6;
          display: flex;
          align-items: center;
          gap: 6px;
        }
        .cards-grid {
          display: grid;
          grid-template-columns: repeat(6, 1fr);
          gap: 4px;
        }
        @media (max-width: 768px) {
          .cards-grid {
            grid-template-columns: repeat(3, 1fr);
          }
        }
        .card {
          background: white;
          border-radius: 6px;
          padding: 6px;
          display: flex;
          flex-direction: column;
          border: 1px solid #e2e8f0;
        }
        .card-label {
          font-size: 9px;
          color: #64748b;
          font-weight: 600;
          margin-bottom: 2px;
          display: flex;
          align-items: center;
          gap: 2px;
        }
        .card-amount {
          font-size: 13px;
          font-weight: 800;
          color: #0f172a;
          margin-bottom: 1px;
        }
        .card-count {
          font-size: 9px;
          color: #f97316;
          font-weight: 700;
        }
        .card.delivery {
          background: #fef2f2;
          border-color: #fecaca;
        }
        .card.delivery .card-amount { color: #dc2626; }
        .totals-grid {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 12px;
          margin: 20px 0;
        }
        .total-card {
          background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
          border-radius: 12px;
          padding: 16px;
          color: white;
          box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .total-label {
          font-size: 11px;
          opacity: 0.9;
          font-weight: 600;
          margin-bottom: 4px;
          display: flex;
          align-items: center;
          gap: 4px;
        }
        .total-amount {
          font-size: 24px;
          font-weight: 900;
          letter-spacing: -1px;
        }
        .total-count {
          font-size: 11px;
          opacity: 0.85;
          margin-top: 4px;
        }
        .total-card.saldo {
          background: linear-gradient(135deg, #059669 0%, #10b981 100%);
          box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .search-box {
          background: white;
          border-radius: 12px;
          padding: 12px;
          margin: 16px 0;
          display: flex;
          align-items: center;
          gap: 8px;
          border: 2px solid #e2e8f0;
        }
        .search-box:focus-within {
          border-color: #3b82f6;
        }
        .search-input {
          flex: 1;
          border: none;
          outline: none;
          font-size: 16px;
          color: #1e293b;
        }
        .search-input::placeholder {
          color: #94a3b8;
        }
        .mov-card {
          background: white;
          border-radius: 8px;
          padding: 10px 12px;
          margin-bottom: 6px;
          border-left: 3px solid #3b82f6;
          border: 1px solid #e2e8f0;
          border-left: 3px solid #3b82f6;
        }
        .mov-card.retiro {
          border-left-color: #ef4444;
        }
        .mov-card.ingreso {
          border-left-color: #10b981;
        }
        .mov-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 6px;
        }
        .mov-tipo {
          font-size: 11px;
          font-weight: 700;
          display: flex;
          align-items: center;
          gap: 3px;
        }
        .mov-card.retiro .mov-tipo { color: #dc2626; }
        .mov-card.ingreso .mov-tipo { color: #059669; }
        .mov-monto {
          font-size: 15px;
          font-weight: 800;
          color: #0f172a;
        }
        .mov-card.retiro .mov-monto { color: #dc2626; }
        .mov-card.ingreso .mov-monto { color: #059669; }
        .mov-motivo {
          font-size: 12px;
          color: #475569;
          margin-bottom: 6px;
          font-weight: 500;
        }
        .mov-footer {
          display: flex;
          justify-content: space-between;
          font-size: 10px;
          color: #64748b;
          margin-bottom: 6px;
        }
        .mov-usuario {
          display: flex;
          align-items: center;
          gap: 3px;
        }
        .mov-saldos {
          font-size: 11px;
          font-weight: 700;
          color: #475569;
          padding-top: 6px;
          border-top: 1px solid #e2e8f0;
        }
        .loading {
          text-align: center;
          padding: 60px 20px;
          color: white;
        }
        .spinner {
          border: 4px solid rgba(255,255,255,0.3);
          border-top: 4px solid white;
          border-radius: 50%;
          width: 40px;
          height: 40px;
          animation: spin 1s linear infinite;
          margin: 0 auto 16px;
        }
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
        .error {
          text-align: center;
          padding: 60px 20px;
          color: #dc2626;
          background: white;
          border-radius: 16px;
          margin: 20px;
        }
        .footer {
          text-align: center;
          padding: 16px;
          background: white;
          color: #64748b;
          font-size: 10px;
          border-top: 2px solid #e2e8f0;
        }
      `}</style>
      <div className="page">
        <div className="header">
          <div className="header-top">
            <button className="back-btn" onClick={() => window.location.href = '/arqueo'}>
              <ChevronLeft size={24} />
            </button>
            <div className="header-left">
              <span className="header-title">Arqueo</span>
              <span className="header-sep">|</span>
              <span style={{fontSize: '10px', color: '#9ca3af'}}>Delivery:</span>
              <Bike size={12} style={{color: '#f97316'}} />
              <span className="header-value" style={{color: '#f97316'}}>-${fmt(deliveryTotal)}</span>
              <span className="header-sep">|</span>
              <span style={{fontSize: '10px', color: '#9ca3af'}}>Costo:</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#dc2626" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{display: 'inline-block', verticalAlign: 'middle'}}><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
              <span className="header-value red">-${fmt(ventasStats?.total_cost || 0)}</span>
              <span className="header-sep">|</span>
              <span style={{fontSize: '10px', color: '#9ca3af'}}>Utilidad:</span>
              <span className="header-value green" style={{fontWeight: 800}}>💰${fmt(totalRuta11 - (ventasStats?.total_cost || 0))}</span>
            </div>
          </div>
          <div className="header-nav">
            <button className="month-btn" onClick={() => showSpecificMonth(-2)}>
              {getMonthName(-2)}
            </button>
            <button className="month-btn" onClick={() => showSpecificMonth(-1)}>
              {getMonthName(-1)}
            </button>
            <button className="month-btn" onClick={showCurrentMonth}>
              {getCurrentMonthName()}
            </button>
            <button className="nav-btn" onClick={() => navigateDay(1)}>
              <ChevronLeft size={12} /> Anterior
            </button>
            <button className="nav-btn" onClick={() => navigateDay(-1)} disabled={daysAgo === 0 || selectedMonth !== null}>
              Siguiente <ChevronRight size={12} />
            </button>
          </div>
        </div>



        <div className="body">
          <div className="content">

          <div style={{background: 'white', padding: '12px', textAlign: 'center', marginBottom: '12px', borderRadius: '8px'}}>
            <span style={{fontSize: '14px', fontWeight: 700, color: '#1e293b', textTransform: 'uppercase'}}>
              {selectedMonth ? (() => {
                const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                return `${monthNames[selectedMonth.month - 1].toUpperCase()} ${selectedMonth.year}`;
              })() : salesData.shift_date}
            </span>
          </div>

          {ingredientConsumption.length > 0 && (
            <div style={{background: 'white', borderRadius: '8px', marginBottom: '12px', overflow: 'hidden'}}>
              <button onClick={() => setShowIngredients(!showIngredients)} style={{width: '100%', padding: '12px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: 'none', border: 'none', cursor: 'pointer', textAlign: 'left'}}>
                <span style={{fontSize: '14px', color: '#475569', fontWeight: 600}}>Consumo de Ingredientes</span>
                <span style={{color: '#9ca3af', fontSize: '14px'}}>{showIngredients ? '▼' : '▶'}</span>
              </button>
              {showIngredients && (
                <div style={{padding: '0 12px 12px'}}>
                  <div style={{display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '6px', fontSize: '12px'}}>
                    {ingredientConsumption.map((ing, i) => {
                      const qty = parseFloat(ing.total || 0);
                      let unit = ing.unit || 'g';
                      if (unit === 'unidad') unit = 'U';
                      if (unit === 'unit') unit = 'U';
                      const displayQty = unit === 'g' && qty >= 1000 ? `${(qty / 1000).toFixed(2)} kg` : `${Math.round(qty)} ${unit}`;
                      return (
                        <div key={i} style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '4px 0'}}>
                          <span style={{color: '#475569'}}>{ing.name}</span>
                          <span style={{fontWeight: 700, color: '#ea580c', whiteSpace: 'nowrap'}}>{displayQty}</span>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>
          )}

          <div className="section">
            <div className="section-title">
              <DollarSign size={16} /> Desglose de Ventas
              {ventasStats && (
                <span style={{marginLeft: 'auto', fontSize: '11px', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '8px'}}>
                  <span style={{display: 'flex', alignItems: 'center', gap: '3px'}}>
                    <Home size={12} style={{color: '#10b981'}} />
                    <span style={{color: '#10b981'}}>{ventasStats.pickup_count}</span>
                  </span>
                  <span style={{color: '#64748b'}}>|</span>
                  <span style={{display: 'flex', alignItems: 'center', gap: '3px'}}>
                    <Bike size={12} style={{color: '#3b82f6'}} />
                    <span style={{color: '#3b82f6'}}>{ventasStats.delivery_count}</span>
                  </span>
                  <span style={{color: '#64748b'}}>|</span>
                  <span style={{color: '#f97316', fontWeight: 700}}>{ventasStats.total_orders}</span>
                </span>
              )}
            </div>
            <div className="cards-grid">
            <div className="card">
              <div className="card-label"><Banknote size={14} /> Efectivo</div>
              <div className="card-amount">${fmt(d.cash.total)}</div>
              <div className="card-count">{d.cash.count} pedidos</div>
            </div>

            <div className="card">
              <div className="card-label"><CreditCard size={14} /> Tarjetas</div>
              <div className="card-amount">${fmt(d.card.total)}</div>
              <div className="card-count">{d.card.count} pedidos</div>
            </div>

            <div className="card">
              <div className="card-label"><Building2 size={14} /> Transferencias</div>
              <div className="card-amount">${fmt(d.transfer.total)}</div>
              <div className="card-count">{d.transfer.count} pedidos</div>
            </div>

            <div className="card">
              <div className="card-label"><Bike size={14} /> PedidosYA</div>
              <div className="card-amount">${fmt(d.pedidosya.total)}</div>
              <div className="card-count">{d.pedidosya.count} pedidos</div>
            </div>

            <div className="card">
              <div className="card-label"><Smartphone size={14} /> App Webpay</div>
              <div className="card-amount">${fmt(d.webpay.total)}</div>
              <div className="card-count">{d.webpay.count} pedidos</div>
            </div>

            <div className="card">
              <div className="card-label"><CreditCard size={14} /> Crédito RL6</div>
              <div className="card-amount">${fmt(d.rl6_credit.total)}</div>
              <div className="card-count">{d.rl6_credit.count} pedidos</div>
            </div>
            </div>
          </div>

          <div className="totals-grid">
            <div className="total-card saldo">
              <div className="total-label"><Wallet size={14} /> SALDO EN CAJA</div>
              <div className="total-amount">${fmt(cajaData.saldo_actual)}</div>
              <div className="total-count">{movimientos.length} movimientos en turno</div>
            </div>

            <div className="total-card">
              <div className="total-label"><TrendingUp size={14} /> TOTAL VENTAS RUTA 11</div>
              <div className="total-amount">${fmt(totalRuta11)}</div>
              <div className="total-count">{salesData.total_orders} pedidos totales</div>
            </div>
          </div>

          <div className="search-box">
            <Search size={18} color="#64748b" />
            <input
              type="text"
              className="search-input"
              placeholder="Buscar en movimientos o ventas..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>

          <div className="section">
            <div className="section-title"><FileText size={16} /> Movimientos de Caja</div>
            {filteredMovimientos.length > 0 ? (
              filteredMovimientos.map((mov, idx) => (
                <div key={idx} className={`mov-card ${mov.tipo}`}>
                  <div className="mov-header">
                    <span className="mov-tipo">
                      {mov.tipo === 'ingreso' ? <ArrowUp size={14} /> : <ArrowDown size={14} />} {mov.tipo.toUpperCase()}
                    </span>
                    <span className="mov-monto">
                      {mov.tipo === 'ingreso' ? '+' : '-'}${fmt(mov.monto)}
                    </span>
                  </div>
                  <div className="mov-motivo">{mov.motivo}</div>
                  <div className="mov-footer">
                    <span className="mov-usuario"><User size={12} /> {mov.usuario}</span>
                    <span className="mov-hora">
                      {(() => {
                        const utcStr = mov.fecha_movimiento.replace(' ', 'T') + 'Z';
                        const utcDate = new Date(utcStr);
                        return utcDate.toLocaleDateString('es-CL', {
                          timeZone: 'America/Santiago',
                          day: '2-digit',
                          month: '2-digit',
                          year: 'numeric'
                        }) + ', ' + utcDate.toLocaleTimeString('es-CL', {
                          timeZone: 'America/Santiago',
                          hour: '2-digit',
                          minute: '2-digit',
                          hour12: true
                        });
                      })()}
                    </span>
                  </div>
                  <div className="mov-saldos">
                    ${fmt(mov.saldo_anterior)} → ${fmt(mov.saldo_nuevo)}
                  </div>
                </div>
              ))
            ) : (
              <p style={{textAlign: 'center', color: '#9ca3af', padding: '20px'}}>{searchTerm ? 'No se encontraron movimientos' : 'No hay movimientos en este turno'}</p>
            )}
          </div>

          <div className="section">
            <div className="section-title"><FileText size={16} /> Ventas del Turno</div>
            {filteredVentas.length > 0 ? (
              filteredVentas.map((venta, idx) => {
                const subtotal = parseFloat(venta.installment_amount || 0);
                const deliveryFee = parseFloat(venta.delivery_fee || 0);
                const cost = parseFloat(venta.total_cost || 0);
                const ingresoReal = subtotal - deliveryFee;
                const utilidad = ingresoReal - cost;
                
                return (
                  <div key={idx} className="mov-card">
                    <div className="mov-header" style={{flexWrap: 'wrap', gap: '6px'}}>
                      <span className="mov-tipo" style={{color: '#3b82f6', fontSize: '10px'}}>
                        #{venta.order_number}
                      </span>
                      <div style={{display: 'flex', alignItems: 'center', gap: '4px', fontSize: '11px', flexWrap: 'wrap'}}>
                        {(() => {
                          const method = venta.payment_method;
                          const icons = {
                            'cash': <Banknote size={11} style={{color: '#059669'}} />,
                            'card': <CreditCard size={11} style={{color: '#059669'}} />,
                            'transfer': <Smartphone size={11} style={{color: '#059669'}} />,
                            'webpay': <CreditCard size={11} style={{color: '#059669'}} />,
                            'pedidosya': <Truck size={11} style={{color: '#059669'}} />
                          };
                          return icons[method] || <CreditCard size={11} style={{color: '#059669'}} />;
                        })()}
                        <span style={{color: '#059669', fontWeight: 700}}>${fmt(subtotal)}</span>
                        {deliveryFee > 0 && (
                          <>
                            <span style={{color: '#64748b'}}>|</span>
                            <Bike size={11} style={{color: '#ef4444'}} />
                            <span style={{color: '#ef4444', fontWeight: 600}}>-${fmt(deliveryFee)}</span>
                          </>
                        )}
                        {cost > 0 && (
                          <>
                            <span style={{color: '#64748b'}}>|</span>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#dc2626" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{display: 'inline-block', verticalAlign: 'middle'}}><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                            <span style={{color: '#dc2626', fontWeight: 600}}>-${fmt(cost)}</span>
                            <span style={{color: '#64748b'}}>|</span>
                            <span style={{color: '#10b981', fontWeight: 900, fontSize: '13px'}}>💰${fmt(utilidad)}</span>
                          </>
                        )}
                      </div>
                    </div>
                    <div className="mov-motivo">{venta.customer_name} - {venta.product_name}</div>
                    {venta.customer_notes && (
                      <div style={{fontSize: '11px', color: '#1e293b', marginBottom: '6px', fontStyle: 'italic', background: '#fef08a', padding: '6px 8px', borderRadius: '6px', fontWeight: 500}}>
                        "{venta.customer_notes}"
                      </div>
                    )}
                    <div className="mov-footer">
                      <span style={{fontSize: '10px', background: '#000', color: '#fff', fontWeight: 700, padding: '2px 6px', borderRadius: '4px', textTransform: 'uppercase'}}>{venta.payment_method}</span>
                      <span className="mov-hora">
                        {(() => {
                          const utcStr = venta.created_at.replace(' ', 'T') + 'Z';
                          const utcDate = new Date(utcStr);
                          return utcDate.toLocaleTimeString('es-CL', {
                            timeZone: 'America/Santiago',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                          });
                        })()}
                      </span>
                    </div>
                  </div>
                );
              })
            ) : (
              <p style={{textAlign: 'center', color: '#9ca3af', padding: '20px'}}>{searchTerm ? 'No se encontraron ventas' : 'No hay ventas en este turno'}</p>
            )}
          </div>
          </div>
        </div>

        <div className="footer">
          Generado automáticamente desde App Caja<br />
          La Ruta 11 © {now.getFullYear()}
        </div>
      </div>
    </div>
  );
}
