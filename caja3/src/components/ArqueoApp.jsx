import { useState, useEffect } from 'react';
import { CreditCard, Building2, Banknote, Smartphone, Bike, TrendingUp, Pencil, ChevronLeft, ChevronRight, BarChart3, ArrowLeft, Clock, Wallet, Moon, Calendar, BadgeDollarSign } from 'lucide-react';

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
    return () => { clearInterval(timer); clearInterval(saldoTimer); };
  }, []);

  useEffect(() => { loadSalesData(currentDaysAgo); }, [currentDaysAgo]);

  const updateClock = () => {
    const now = new Date();
    setCurrentTime(now.toLocaleTimeString('es-CL', { timeZone: 'America/Santiago', hour: '2-digit', minute: '2-digit', second: '2-digit' }));
  };

  const loadSalesData = async (daysAgo = 0) => {
    if (salesData) setIsTransitioning(true);
    try {
      const url = daysAgo > 0 ? `/api/get_sales_summary.php?days_ago=${daysAgo}&v=${Date.now()}` : `/api/get_sales_summary.php?v=${Date.now()}`;
      const data = await (await fetch(url, { signal: AbortSignal.timeout(30000) })).json();
      if (data.success) { setSalesData(data); setInitialLoading(false); setTimeout(() => setIsTransitioning(false), 300); }
    } catch (e) { setInitialLoading(false); setIsTransitioning(false); }
  };

  const loadSaldoCaja = async () => {
    try {
      const data = await (await fetch('/api/get_saldo_caja.php')).json();
      if (data.success) { setSaldoCaja(data.saldo_actual); setIngresosHoy(data.ingresos_automaticos_dia || 0); }
    } catch (e) {}
  };

  const openCajaModal = () => window.dispatchEvent(new CustomEvent('openSaldoCajaModal'));
  const showDetail = () => {
    if (!salesData) return;
    window.location.href = `/ventas-detalle?start=${encodeURIComponent(salesData.period.start)}&end=${encodeURIComponent(salesData.period.end)}`;
  };
  const fmt = (n) => Math.round(n).toLocaleString('es-CL');

  if (initialLoading) {
    return (
      <div style={{ maxWidth: 600, margin: '0 auto', padding: 4 }}>
        {[...Array(10)].map((_, i) => (
          <div key={i} style={{ background: 'white', height: 32, marginBottom: 1, borderRadius: i === 0 ? '10px 10px 0 0' : i === 9 ? '0 0 10px 10px' : 0, animation: 'pulse 1.5s ease-in-out infinite' }} />
        ))}
        <style>{`@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }`}</style>
      </div>
    );
  }

  if (!salesData) return null;

  const s = salesData.summary;
  const deliveryTotal = salesData.delivery_fees || 0;
  const deliveryCount = salesData.delivery_count || 0;
  const deliveryExtras = salesData.delivery_extras || 0;
  const totalRuta11 = salesData.total_general - deliveryTotal - deliveryExtras;
  const label = currentDaysAgo === 0 ? 'Turno Actual' : `Hace ${currentDaysAgo}d`;

  const rows = [
    { icon: <CreditCard size={14} />, label: 'Tarjetas', ...s.card },
    { icon: <Building2 size={14} />, label: 'Transferencia', ...s.transfer },
    { icon: <Banknote size={14} />, label: 'Efectivo', ...s.cash },
    { icon: <Smartphone size={14} />, label: 'Webpay', ...s.webpay },
    { icon: <Bike size={14} />, label: 'PedidosYA Online', ...s.pedidosya },
    { icon: <Banknote size={14} />, label: 'PedidosYA Efectivo', ...(s.pedidosya_cash || { count: 0, total: 0 }) },
    { icon: <BadgeDollarSign size={14} />, label: 'Crédito RL6', ...s.rl6_credit },
    { icon: <BadgeDollarSign size={14} />, label: 'Crédito R11', ...s.r11_credit },
    { icon: <Bike size={14} />, label: 'Delivery', count: deliveryCount, total: deliveryTotal },
  ];

  return (
    <div className="aq">
      <div className="hd">
        <div className="hd-top">
          <h1><Wallet size={18} /> Arqueo de Caja</h1>
          <span className="tm"><Clock size={12} /> {currentTime}</span>
        </div>
        <div className="pd"><Moon size={11} /> {label}: {salesData.shift_hours} <span className="dt"><Calendar size={10} /> {salesData.shift_date}</span></div>
      </div>

      <div className={`tbl ${isTransitioning ? 'fade' : ''}`}>
        <div className="th">
          <span className="th-m">Método</span>
          <span className="th-p">Pedidos</span>
          <span className="th-t">Total</span>
        </div>
        {rows.map((r, i) => (
          <div key={i} className={`tr ${r.cls || ''}`}>
            <div className="td-m">{r.icon}<span>{r.label}</span></div>
            <div className="td-p">{r.count}</div>
            <div className="td-t">${fmt(r.total)}</div>
          </div>
        ))}
        {deliveryExtras > 0 && (
          <div className="tr">
            <div className="td-m" style={{paddingLeft:30}}><span>Extras Delivery</span></div>
            <div className="td-p"></div>
            <div className="td-t">${fmt(deliveryExtras)}</div>
          </div>
        )}
      </div>

      <div className="tc">
        <div className="tc-l"><TrendingUp size={16} /> TOTAL VENTAS</div>
        <div className="tc-r">
          <span className="tc-a">${fmt(totalRuta11)}</span>
          <span className="tc-c">{salesData.total_orders} pedidos</span>
        </div>
      </div>

      <div className="sb" onClick={openCajaModal}>
        <div className="sb-l"><Wallet size={14} /> Saldo en Caja</div>
        <div className="sb-r"><span>${fmt(saldoCaja)}</span> <Pencil size={11} /></div>
      </div>

      <div className="nv">
        <button className="bt nv-b" onClick={() => setCurrentDaysAgo(currentDaysAgo + 1)}><ChevronLeft size={16} /> Ayer</button>
        <button className="bt nv-b" onClick={() => currentDaysAgo > 0 && setCurrentDaysAgo(currentDaysAgo - 1)} disabled={currentDaysAgo === 0}>Hoy <ChevronRight size={16} /></button>
      </div>
      <button className="bt bt-det" onClick={showDetail}><BarChart3 size={16} /> Ver Detalle</button>
      <button className="bt bt-bk" onClick={() => window.location.href = 'https://caja.laruta11.cl'}><ArrowLeft size={16} /> Volver a Caja</button>

      <style jsx>{`
        .aq{max-width:600px;margin:0 auto;padding:4px}
        .hd{background:white;padding:8px 10px;border-radius:10px;margin-bottom:6px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
        .hd-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:2px}
        .hd h1{font-size:16px;color:#333;display:flex;align-items:center;gap:5px;margin:0}
        .pd{font-size:10px;color:#888;display:flex;align-items:center;gap:5px;flex-wrap:wrap}
        .dt{background:#f3f4f6;padding:1px 5px;border-radius:3px;font-size:9px;display:inline-flex;align-items:center;gap:2px}
        .tm{color:#10b981;font-weight:600;font-size:10px;display:flex;align-items:center;gap:2px;background:#ecfdf5;padding:1px 5px;border-radius:3px}
        .tbl{background:white;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:6px;overflow:hidden;transition:opacity .3s}
        .tbl.fade{opacity:.5;pointer-events:none}
        .th{display:flex;padding:6px 10px;background:#f9fafb;border-bottom:2px solid #e5e7eb;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px}
        .th-m{flex:1}.th-p{width:50px;text-align:center}.th-t{width:90px;text-align:right}
        .tr{display:flex;align-items:center;padding:6px 10px;border-bottom:1px solid #f3f4f6}
        .tr:last-child{border-bottom:none}
        .td-m{flex:1;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#374151}
        .td-p{width:50px;text-align:center;font-size:12px;font-weight:600;color:#6b7280}
        .td-t{width:90px;text-align:right;font-size:13px;font-weight:700;color:#111827}
        .tc{display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border-radius:10px;padding:10px 12px;margin-bottom:6px;box-shadow:0 2px 6px rgba(102,126,234,.3)}
        .tc-l{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700;opacity:.9}
        .tc-r{text-align:right}
        .tc-a{font-size:24px;font-weight:800;display:block;line-height:1.1}
        .tc-c{font-size:10px;opacity:.7}
        .sb{display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,#10b981,#059669);color:white;border-radius:10px;padding:8px 10px;margin-bottom:6px;cursor:pointer;box-shadow:0 2px 6px rgba(16,185,129,.25)}
        .sb-l{display:flex;align-items:center;gap:5px;font-weight:600;font-size:12px}
        .sb-r{display:flex;align-items:center;gap:5px;font-size:16px;font-weight:700}
        .nv{display:flex;gap:4px;margin-bottom:4px}
        .bt{width:100%;padding:10px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:5px}
        .nv-b{background:#6b7280;color:white;flex:1;padding:8px}
        .nv-b:hover:not(:disabled){background:#4b5563}
        .nv-b:disabled{opacity:.5;cursor:not-allowed}
        .bt-det{background:#8b5cf6;color:white;margin-bottom:4px}
        .bt-det:hover{background:#7c3aed}
        .bt-bk{background:#6b7280;color:white}
        .bt-bk:hover{background:#4b5563}
      `}</style>
    </div>
  );
}
