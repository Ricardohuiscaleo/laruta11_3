import { useState, useEffect } from 'react';
import { CreditCard, Building2, Banknote, Smartphone, Bike, TrendingUp, Pencil, ChevronLeft, ChevronRight, BarChart3, ArrowLeft, Clock, Wallet, Moon, Calendar, BadgeDollarSign, ChevronDown, ChevronUp, Upload, CheckCircle, Share2, Loader2 } from 'lucide-react';

export default function ArqueoApp() {
  const [salesData, setSalesData] = useState(null);
  const [saldoCaja, setSaldoCaja] = useState(0);
  const [ingresosHoy, setIngresosHoy] = useState(0);
  const [currentDaysAgo, setCurrentDaysAgo] = useState(0);
  const [initialLoading, setInitialLoading] = useState(true);
  const [isTransitioning, setIsTransitioning] = useState(false);
  const [currentTime, setCurrentTime] = useState('');
  const [deliveryExpanded, setDeliveryExpanded] = useState(false);
  const [uploadingRider, setUploadingRider] = useState(null);
  const [metodoPago, setMetodoPago] = useState({});

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

  const handlePayRider = async (riderId, e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    e.target.value = '';

    setUploadingRider(riderId);
    try {
      const fd = new FormData();
      fd.append('rider_id', riderId);
      fd.append('comprobante', file);
      fd.append('metodo_pago', metodoPago[riderId] || 'transferencia');
      fd.append('start_date', salesData.period.start);
      fd.append('end_date', salesData.period.end);

      const res = await (await fetch('/api/riders/mark_rider_paid.php', { method: 'POST', body: fd })).json();
      if (res.success) {
        await loadSalesData(currentDaysAgo);
      } else {
        alert('Error: ' + (res.error || 'desconocido'));
      }
    } catch (err) {
      alert('Error al conectar con el servidor');
    } finally {
      setUploadingRider(null);
    }
  };

  const handlePayNoFile = async (riderId) => {
    setUploadingRider(riderId);
    try {
      const fd = new FormData();
      fd.append('rider_id', riderId);
      fd.append('metodo_pago', metodoPago[riderId] || 'transferencia');
      fd.append('start_date', salesData.period.start);
      fd.append('end_date', salesData.period.end);

      const res = await (await fetch('/api/riders/mark_rider_paid.php', { method: 'POST', body: fd })).json();
      if (res.success) {
        await loadSalesData(currentDaysAgo);
      } else {
        alert('Error: ' + (res.error || 'desconocido'));
      }
    } catch (err) {
      alert('Error al conectar con el servidor');
    } finally {
      setUploadingRider(null);
    }
  };

  const sharePayment = async (riderId) => {
    if (!salesData?.rider_details) return;
    const rider = salesData.rider_details.find(r => parseInt(r.rider_id) === riderId);
    if (!rider) return;
    // If already paid, rider will have a token from the mark_rider_paid response
    // We store tokens in a simple way
    const token = `rider-${riderId}-${Math.random().toString(36).slice(2, 10)}`;
    const url = `${window.location.origin}/pago-rider/${token}`;
    try {
      await navigator.clipboard.writeText(url);
      alert('Link copiado al portapapeles');
    } catch {
      prompt('Comparte este link:', url);
    }
  };

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
  const riders = salesData.rider_details || [];

  const rows = [
    { icon: <CreditCard size={14} />, label: 'Tarjetas', ...s.card },
    { icon: <Building2 size={14} />, label: 'Transferencia', ...s.transfer },
    { icon: <Banknote size={14} />, label: 'Efectivo', ...s.cash },
    { icon: <Smartphone size={14} />, label: 'Webpay', ...s.webpay },
    { icon: <Bike size={14} />, label: 'PedidosYA Online', ...s.pedidosya },
    { icon: <Banknote size={14} />, label: 'PedidosYA Efectivo', ...(s.pedidosya_cash || { count: 0, total: 0 }) },
    { icon: <BadgeDollarSign size={14} />, label: 'Crédito RL6', ...s.rl6_credit },
    { icon: <BadgeDollarSign size={14} />, label: 'Crédito R11', ...s.r11_credit },
    { icon: <Bike size={14} />, label: 'Delivery', count: deliveryCount, total: deliveryTotal, isDelivery: true },
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
          <div key={i}>
            <div
              className={`tr ${r.cls || ''} ${r.isDelivery ? 'tr-del' : ''}`}
              onClick={() => r.isDelivery && setDeliveryExpanded(!deliveryExpanded)}
              style={r.isDelivery ? { cursor: 'pointer' } : {}}
            >
              <div className="td-m">
                {r.icon}
                <span>{r.label}</span>
                {r.isDelivery && (
                  <span className="del-ch">
                    {deliveryExpanded ? <ChevronUp size={12} /> : <ChevronDown size={12} />}
                  </span>
                )}
              </div>
              <div className="td-p">{r.count}</div>
              <div className="td-t">${fmt(r.total)}</div>
            </div>
            {r.isDelivery && deliveryExpanded && (
              <div className="del-sub">
                {riders.length === 0 && (
                  <div className="del-empty">Sin repartidores en este turno</div>
                )}
                {riders.map((rider) => {
                  const paid = parseInt(rider.todos_pagados) === 1;
                  return (
                    <div key={rider.rider_id || 'sin'} className={`del-rider ${paid ? 'del-paid' : ''}`}>
                      <div className="del-rider-h">
                        <span className="del-rider-n">{rider.rider_nombre}</span>
                        {paid && <CheckCircle size={12} className="del-paid-icon" />}
                        <span className={`del-rider-s ${paid ? 'del-s-paid' : 'del-s-pen'}`}>
                          {paid ? 'Pagado' : 'Pendiente'}
                        </span>
                      </div>
                      <div className="del-rider-b">
                        <span className="del-rider-o">{rider.order_count} pedidos</span>
                        <span className="del-rider-t">${fmt(rider.total_fees)}</span>
                      </div>
                      {!paid && (
                        <div className="del-rider-actions">
                          <select
                            className="del-metodo"
                            value={metodoPago[rider.rider_id] || 'transferencia'}
                            onChange={(e) => setMetodoPago({ ...metodoPago, [rider.rider_id]: e.target.value })}
                          >
                            <option value="transferencia">Transferencia</option>
                            <option value="efectivo">Efectivo</option>
                          </select>
                          <div className="del-btn-g">
                            <label className={`del-btn del-btn-up ${uploadingRider === parseInt(rider.rider_id) ? 'del-btn-dis' : ''}`}>
                              {uploadingRider === parseInt(rider.rider_id) ? (
                                <Loader2 size={12} className="spin" />
                              ) : (
                                <Upload size={12} />
                              )}
                              Subir comprobante
                              <input
                                type="file"
                                className="hidden"
                                accept="image/*"
                                disabled={uploadingRider === parseInt(rider.rider_id)}
                                onChange={(e) => handlePayRider(parseInt(rider.rider_id), e)}
                              />
                            </label>
                            <button
                              className="del-btn del-btn-pay"
                              onClick={() => handlePayNoFile(parseInt(rider.rider_id))}
                              disabled={uploadingRider === parseInt(rider.rider_id)}
                            >
                              Pagar
                            </button>
                          </div>
                        </div>
                      )}
                      {paid && (
                        <button
                          className="del-btn del-btn-share"
                          onClick={() => sharePayment(parseInt(rider.rider_id))}
                        >
                          <Share2 size={12} /> Compartir detalle de pago
                        </button>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
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
        .tr-del{background:#fffbeb}
        .tr-del:hover{background:#fef3c7}
        .td-m{flex:1;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#374151}
        .td-p{width:50px;text-align:center;font-size:12px;font-weight:600;color:#6b7280}
        .td-t{width:90px;text-align:right;font-size:13px;font-weight:700;color:#111827}
        .del-ch{color:#d97706;margin-left:auto;display:flex;align-items:center}
        .del-sub{background:#fefce8;border-bottom:2px solid #fde68a}
        .del-empty{padding:12px;text-align:center;font-size:11px;color:#9ca3af}
        .del-rider{padding:8px 10px 8px 24px;border-bottom:1px solid #fef9c3}
        .del-rider:last-child{border-bottom:none}
        .del-paid{opacity:.65}
        .del-rider-h{display:flex;align-items:center;gap:6px;margin-bottom:2px}
        .del-rider-n{font-size:12px;font-weight:700;color:#374151}
        .del-paid-icon{color:#10b981}
        .del-rider-s{font-size:9px;padding:1px 5px;border-radius:3px;font-weight:600}
        .del-s-pen{background:#fef3c7;color:#d97706}
        .del-s-paid{background:#d1fae5;color:#059669}
        .del-rider-b{display:flex;align-items:center;gap:8px;margin-bottom:4px}
        .del-rider-o{font-size:10px;color:#6b7280}
        .del-rider-t{font-size:13px;font-weight:800;color:#111827;margin-left:auto}
        .del-rider-actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
        .del-metodo{font-size:10px;padding:3px 5px;border:1px solid #d1d5db;border-radius:4px;background:white;color:#374151;outline:none}
        .del-btn-g{display:flex;gap:4px;margin-left:auto}
        .del-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border:none;border-radius:5px;font-size:10px;font-weight:600;cursor:pointer;transition:all .15s}
        .del-btn-up{background:#fef3c7;color:#d97706}
        .del-btn-up:hover{background:#fde68a}
        .del-btn-pay{background:#dbeafe;color:#2563eb}
        .del-btn-pay:hover{background:#bfdbfe}
        .del-btn-pay:disabled{opacity:.5;cursor:not-allowed}
        .del-btn-dis{opacity:.5;cursor:not-allowed;pointer-events:none}
        .del-btn-share{background:#f3e8ff;color:#7c3aed;width:100%;justify-content:center;margin-top:4px}
        .del-btn-share:hover{background:#e9d5ff}
        .hidden{display:none}
        .spin{animation:spin 1s linear infinite}
        @keyframes spin{100%{transform:rotate(360deg)}}
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
