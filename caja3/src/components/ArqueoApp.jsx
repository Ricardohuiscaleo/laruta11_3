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
      const response = await fetch(url, { signal: AbortSignal.timeout(30000) });
      const data = await response.json();
      if (data.success) { setSalesData(data); setInitialLoading(false); setTimeout(() => setIsTransitioning(false), 300); }
    } catch (e) { setInitialLoading(false); setIsTransitioning(false); }
  };

  const loadSaldoCaja = async () => {
    try {
      const response = await fetch('/api/get_saldo_caja.php');
      const data = await response.json();
      if (data.success) { setSaldoCaja(data.saldo_actual); setIngresosHoy(data.ingresos_automaticos_dia || 0); }
    } catch (e) {}
  };

  const openCajaModal = () => window.dispatchEvent(new CustomEvent('openSaldoCajaModal'));

  const showDetail = () => {
    if (!salesData) return;
    window.location.href = `/ventas-detalle?start=${encodeURIComponent(salesData.period.start)}&end=${encodeURIComponent(salesData.period.end)}`;
  };

  const sendWhatsApp = () => {
    if (!salesData) return;
    const f = (n) => Math.round(n).toLocaleString('es-CL');
    const totalRuta11 = salesData.total_general - (salesData.delivery_fees || 0);
    const reportUrl = `https://caja.laruta11.cl/arqueo-resumen?days_ago=${currentDaysAgo}`;
    let message = `> 📊 *ARQUEO DE CAJA - LA RUTA 11*%0A%0A`;
    message += `_${new Date().toLocaleDateString('es-CL')} - ${new Date().toLocaleTimeString('es-CL')}__%0A%0A`;
    message += `*💰 Resumen de ventas:*%0A`;
    message += `- *Total Ventas:* ${f(totalRuta11)}%0A`;
    message += `- *Pedidos:* ${salesData.total_orders}%0A`;
    message += `- *Saldo en Caja:* ${f(saldoCaja)}%0A%0A`;
    message += `> 📄 Ver reporte completo:%0A> ${reportUrl}`;
    window.open(`https://wa.me/56936227422?text=${message}`, '_blank');
  };

  const fmt = (n) => Math.round(n).toLocaleString('es-CL');

  if (initialLoading) {
    return (
      <div style={{ maxWidth: 600, margin: '0 auto', padding: 4 }}>
        {[...Array(9)].map((_, i) => (
          <div key={i} style={{ background: 'white', height: 40, marginBottom: 1, borderRadius: i === 0 ? '10px 10px 0 0' : i === 8 ? '0 0 10px 10px' : 0, animation: 'pulse 1.5s ease-in-out infinite' }} />
        ))}
        <style>{`@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }`}</style>
      </div>
    );
  }

  if (!salesData) return null;

  const deliveryTotal = salesData.delivery_fees || 0;
  const deliveryCount = salesData.delivery_count || 0;
  const deliveryExtras = salesData.delivery_extras || 0;
  const totalRuta11 = salesData.total_general - deliveryTotal - deliveryExtras;
  const label = currentDaysAgo === 0 ? 'Turno Actual' : `Turno hace ${currentDaysAgo} día${currentDaysAgo > 1 ? 's' : ''}`;

  const rows = [
    { icon: <CreditCard size={16} />, label: 'Tarjetas', data: salesData.summary.card },
    { icon: <Building2 size={16} />, label: 'Transferencia', data: salesData.summary.transfer },
    { icon: <Banknote size={16} />, label: 'Efectivo', data: salesData.summary.cash },
    { icon: <Smartphone size={16} />, label: 'Webpay', data: salesData.summary.webpay },
    { icon: <Bike size={16} />, label: 'PedidosYA Online', data: salesData.summary.pedidosya },
    ...(salesData.summary.pedidosya_cash && salesData.summary.pedidosya_cash.count > 0
      ? [{ icon: <Banknote size={16} />, label: 'PedidosYA Efectivo', data: salesData.summary.pedidosya_cash, cls: 'pya' }]
      : []),
    { icon: <BadgeDollarSign size={16} />, label: 'Crédito RL6', data: salesData.summary.rl6_credit },
    { icon: <BadgeDollarSign size={16} />, label: 'Crédito R11', data: salesData.summary.r11_credit },
    { icon: <Bike size={16} />, label: 'Delivery', data: { total: deliveryTotal, count: deliveryCount }, cls: 'dlv', extra: deliveryExtras > 0 ? `Extras $${fmt(deliveryExtras)}` : null },
  ];

  return (
    <div className="aq">
      <div className="hd">
        <div className="hd-top">
          <h1><Wallet size={20} /> Arqueo de Caja</h1>
          <span className="tm"><Clock size={14} /> {currentTime}</span>
        </div>
        <div className="pd"><Moon size={12} /> {label}: {salesData.shift_hours} <span className="dt"><Calendar size={11} /> {salesData.shift_date}</span></div>
      </div>

      <div className={`sl ${isTransitioning ? 'fade' : ''}`}>
        {rows.map((r, i) => (
          <div key={i} className={`sr ${r.cls || ''}`}>
            <div className="sr-l"><span className="si">{r.icon}</span><span className="sn">{r.label}</span></div>
            <div className="sr-r"><span className="sa">${fmt(r.data.total)}</span><span className="sc">{r.data.count}{r.extra ? ` · ${r.extra}` : ''}</span></div>
          </div>
        ))}
      </div>

      <div className="tc">
        <div className="tc-t"><TrendingUp size={16} /> TOTAL VENTAS</div>
        <div className="tc-a">${fmt(totalRuta11)}</div>
        <div className="tc-c">{salesData.total_orders} pedidos</div>
      </div>

      <div className="sb" onClick={openCajaModal}>
        <div className="sb-l"><Wallet size={16} /> Saldo en Caja</div>
        <div className="sb-r"><span>${fmt(saldoCaja)}</span> <Pencil size={12} /></div>
      </div>

      <div className="nv">
        <button className="bt nv-b" onClick={() => setCurrentDaysAgo(currentDaysAgo + 1)}><ChevronLeft size={18} /> Ayer</button>
        <button className="bt nv-b" onClick={() => currentDaysAgo > 0 && setCurrentDaysAgo(currentDaysAgo - 1)} disabled={currentDaysAgo === 0}>Hoy <ChevronRight size={18} /></button>
      </div>
      <button className="bt bt-det" onClick={showDetail}><BarChart3 size={18} /> Ver Detalle de Ventas</button>
      <button className="bt bt-wa" onClick={sendWhatsApp}><MessageCircle size={18} /> Enviar Arqueo por WhatsApp</button>
      <button className="bt bt-bk" onClick={() => window.location.href = 'https://caja.laruta11.cl'}><ArrowLeft size={18} /> Volver a Caja</button>

      <style jsx>{`
        .aq { max-width:600px; margin:0 auto; padding:4px; }
        .hd { background:white; padding:10px 12px; border-radius:10px; margin-bottom:8px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        .hd-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; }
        .hd h1 { font-size:18px; color:#333; display:flex; align-items:center; gap:6px; }
        .pd { font-size:11px; color:#666; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .dt { background:#f3f4f6; padding:2px 6px; border-radius:4px; font-size:10px; display:inline-flex; align-items:center; gap:3px; }
        .tm { color:#10b981; font-weight:600; font-size:11px; display:flex; align-items:center; gap:3px; background:#ecfdf5; padding:2px 6px; border-radius:4px; }
        .sl { background:white; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,.08); margin-bottom:8px; overflow:hidden; transition:opacity .3s; }
        .sl.fade { opacity:.5; pointer-events:none; }
        .sr { display:flex; justify-content:space-between; align-items:center; padding:8px 12px; border-bottom:1px solid #f3f4f6; }
        .sr:last-child { border-bottom:none; }
        .sr-l { display:flex; align-items:center; gap:8px; }
        .si { color:#6b7280; display:flex; align-items:center; }
        .sn { font-size:13px; font-weight:600; color:#374151; }
        .sr-r { text-align:right; }
        .sa { font-size:16px; font-weight:700; color:#111827; display:block; line-height:1.2; }
        .sc { font-size:10px; color:#9ca3af; }
        .sr.pya { background:#fffbeb; }
        .sr.pya .si { color:#d97706; }
        .sr.pya .sn { color:#92400e; }
        .sr.dlv { background:#fef3c7; }
        .sr.dlv .si { color:#d97706; }
        .sr.dlv .sn { color:#92400e; }
        .sr.dlv .sa { color:#92400e; }
        .tc { background:linear-gradient(135deg,#667eea,#764ba2); color:white; border-radius:10px; padding:12px; margin-bottom:8px; box-shadow:0 2px 8px rgba(102,126,234,.3); }
        .tc-t { font-size:11px; color:rgba(255,255,255,.85); display:flex; align-items:center; gap:4px; margin-bottom:4px; }
        .tc-a { font-size:28px; font-weight:800; line-height:1.1; }
        .tc-c { font-size:11px; color:rgba(255,255,255,.7); }
        .sb { display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg,#10b981,#059669); color:white; border-radius:10px; padding:10px 12px; margin-bottom:8px; cursor:pointer; box-shadow:0 2px 8px rgba(16,185,129,.3); }
        .sb-l { display:flex; align-items:center; gap:6px; font-weight:600; font-size:13px; }
        .sb-r { display:flex; align-items:center; gap:6px; font-size:18px; font-weight:700; }
        .nv { display:flex; gap:6px; margin-bottom:6px; }
        .bt { width:100%; padding:12px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:6px; }
        .nv-b { background:#6b7280; color:white; flex:1; padding:10px; }
        .nv-b:hover:not(:disabled) { background:#4b5563; }
        .nv-b:disabled { opacity:.5; cursor:not-allowed; }
        .bt-det { background:#8b5cf6; color:white; margin-bottom:6px; }
        .bt-det:hover { background:#7c3aed; }
        .bt-wa { background:#10b981; color:white; margin-bottom:6px; }
        .bt-wa:hover { background:#059669; }
        .bt-bk { background:#6b7280; color:white; }
        .bt-bk:hover { background:#4b5563; }
      `}</style>
    </div>
  );
}
