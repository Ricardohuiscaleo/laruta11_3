import { useState, useEffect } from 'react';
import { CreditCard, Building2, Banknote, Smartphone, Bike, TrendingUp, Pencil, ChevronLeft, ChevronRight, BarChart3, Clock, Wallet, Moon, Calendar, BadgeDollarSign, X, ChevronRight, Upload, CheckCircle, Share2, Loader2, Phone } from 'lucide-react';
import SaldoCajaModal from './modals/SaldoCajaModal.jsx';

export default function ArqueoPanel({ onClose, openPanel }) {
  const [salesData, setSalesData] = useState(null);
  const [saldoCaja, setSaldoCaja] = useState(0);
  const [ingresosHoy, setIngresosHoy] = useState(0);
  const [currentDaysAgo, setCurrentDaysAgo] = useState(0);
  const [initialLoading, setInitialLoading] = useState(true);
  const [isTransitioning, setIsTransitioning] = useState(false);
  const [currentTime, setCurrentTime] = useState('');
  const [showSaldoModal, setShowSaldoModal] = useState(false);
  const [showModal, setShowModal] = useState(false);
  const [deliveryOrders, setDeliveryOrders] = useState([]);
  const [allRiders, setAllRiders] = useState([]);
  const [loadingOrders, setLoadingOrders] = useState(false);
  const [assigningId, setAssigningId] = useState(null);
  const [uploadingId, setUploadingId] = useState(null);
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

  const openDeliveryModal = async () => {
    if (!salesData) return;
    setShowModal(true);
    setLoadingOrders(true);
    try {
      const url = `/api/riders/get_delivery_orders.php?start_date=${encodeURIComponent(salesData.period.start)}&end_date=${encodeURIComponent(salesData.period.end)}`;
      const data = await (await fetch(url)).json();
      if (data.success) {
        setDeliveryOrders(data.orders || []);
        setAllRiders(data.riders || []);
      }
    } catch (e) {
      console.error('Error loading delivery orders:', e);
    } finally {
      setLoadingOrders(false);
    }
  };

  const payOrder = async (order, file) => {
    if (!file && !confirm(`Pagar ${order.order_number} ($${fmt(totalFee(order))})?`)) return;
    setUploadingId(order.id);
    try {
      const fd = new FormData();
      fd.append('order_id', order.id);
      if (file) fd.append('comprobante', file);
      fd.append('metodo_pago', metodoPago[order.id] || 'transferencia');
      fd.append('start_date', salesData.period.start);
      fd.append('end_date', salesData.period.end);
      const res = await (await fetch('/api/riders/mark_rider_paid.php', { method: 'POST', body: fd })).json();
      if (res.success) {
        await loadSalesData(currentDaysAgo);
        await openDeliveryModal();
      } else {
        alert('Error: ' + (res.error || 'desconocido'));
      }
    } catch (err) {
      alert('Error al conectar con el servidor');
    } finally {
      setUploadingId(null);
    }
  };

  const assignRider = async (orderId, riderId) => {
    setAssigningId(orderId);
    try {
      const res = await (await fetch('/api/riders/assign_rider.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, rider_id: parseInt(riderId) }),
      })).json();
      if (res.success) {
        setDeliveryOrders(prev => prev.map(o =>
          o.id === orderId ? { ...o, rider_id: parseInt(riderId), rider_nombre: res.rider?.nombre || null } : o
        ));
        await loadSalesData(currentDaysAgo);
        await openDeliveryModal();
      } else {
        alert('Error: ' + (res.error || 'desconocido'));
      }
    } catch (e) {
      alert('Error al asignar rider');
    } finally {
      setAssigningId(null);
    }
  };

  const sharePayment = async (orderId) => {
    const url = `${window.location.origin}/pago-rider.php?order_id=${orderId}`;
    try {
      await navigator.clipboard.writeText(url);
      alert('Link copiado al portapapeles');
    } catch {
      prompt('Comparte este link:', url);
    }
  };

  const openCajaModal = () => setShowSaldoModal(true);
  const showDetail = () => {
    if (!salesData) return;
    if (openPanel) {
      openPanel('ventas-detalle', { start: salesData.period.start, end: salesData.period.end });
    } else {
      window.location.href = `/ventas-detalle?start=${encodeURIComponent(salesData.period.start)}&end=${encodeURIComponent(salesData.period.end)}`;
    }
  };
  const fmt = (n) => Math.round(n).toLocaleString('es-CL');

  const totalFee = (o) => parseFloat(o.delivery_fee) + parseFloat(o.card_surcharge || 0);

  if (initialLoading) {
    return (
      <div style={{ position: 'fixed', inset: 0, background: 'white', zIndex: 50, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <div className="aq-header"><h1>💰 Arqueo de Caja</h1><button className="aq-close" onClick={onClose}><X size={20} /></button></div>
        <div style={{ flex: 1, overflow: 'auto', padding: 4 }}>
          <div style={{ maxWidth: 600, margin: '0 auto' }}>
            {[...Array(10)].map((_, i) => (
              <div key={i} style={{ background: '#f3f4f6', height: 32, marginBottom: 1, borderRadius: i === 0 ? '10px 10px 0 0' : i === 9 ? '0 0 10px 10px' : 0, animation: 'pulse 1.5s ease-in-out infinite' }} />
            ))}
          </div>
        </div>
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
    <div style={{ position: 'fixed', inset: 0, background: 'white', zIndex: 50, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
      <div className="aq-header">
        <h1>💰 Arqueo de Caja</h1>
        <button className="aq-close" onClick={onClose}><X size={20} /></button>
      </div>

      <div style={{ flex: 1, overflow: 'auto' }}>
        <div className="aq">
          <div className="hd">
            <div className="hd-top">
              <h2><Wallet size={18} /> Arqueo de Caja</h2>
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
              <div
                key={i}
                className={`tr ${r.cls || ''} ${r.isDelivery ? 'tr-del' : ''}`}
                onClick={() => r.isDelivery && openDeliveryModal()}
                style={r.isDelivery ? { cursor: 'pointer' } : {}}
              >
                <div className="td-m">
                  {r.icon}<span>{r.label}</span>
                  {r.isDelivery && <ChevronRight size={12} className="del-arrow" />}
                </div>
                <div className="td-p">{r.count}</div>
                <div className="td-t">${fmt(r.total)}</div>
              </div>
            ))}
          </div>

          <div className="tc">
            <div className="tc-l"><TrendingUp size={16} /> TOTAL VENTAS</div>
            <div className="tc-r">
              <span className="tc-a">${fmt(totalRuta11)}</span>
              <span className="tc-c">{salesData.total_orders} pedidos</span>
            </div>
          </div>

          <div className="sb" onClick={openCajaModal} role="button" tabIndex={0} onKeyDown={(e) => e.key === 'Enter' && openCajaModal()}>
            <div className="sb-l"><Wallet size={14} /> Saldo en Caja</div>
            <div className="sb-r"><span>${fmt(saldoCaja)}</span> <Pencil size={11} /></div>
          </div>

          <div className="nv">
            <button className="bt nv-b" onClick={() => setCurrentDaysAgo(currentDaysAgo + 1)}><ChevronLeft size={16} /> Ayer</button>
            <button className="bt nv-b" onClick={() => currentDaysAgo > 0 && setCurrentDaysAgo(currentDaysAgo - 1)} disabled={currentDaysAgo === 0}>Hoy <ChevronRight size={16} /></button>
          </div>
          <button className="bt bt-det" onClick={showDetail}><BarChart3 size={16} /> Ver Detalle</button>
        </div>
      </div>

      <SaldoCajaModal isOpen={showSaldoModal} onClose={() => setShowSaldoModal(false)} />

      {/* Delivery Modal */}
      {showModal && (
        <div className="dm-over" onClick={() => setShowModal(false)}>
          <div className="dm" onClick={e => e.stopPropagation()}>
            <div className="dm-h">
              <h3><Bike size={16} /> Delivery</h3>
              <button className="dm-x" onClick={() => setShowModal(false)}><X size={16} /></button>
            </div>
            {loadingOrders ? (
              <div className="dm-load">Cargando pedidos...</div>
            ) : (
              <div className="dm-b">
                {deliveryOrders.map((o) => {
                  const fee = totalFee(o);
                  const paid = parseInt(o.is_paid) === 1;
                  return (
                    <div key={o.id} className={`do-card ${paid ? 'do-paid' : ''}`}>
                      <div className="do-h">
                        <span className="do-n">{o.order_number}</span>
                        <span className="do-hr">{o.hora}</span>
                        {paid && <span className="do-badge">Pagado</span>}
                      </div>
                      {o.customer_name && <div className="do-c">
                        <span>{o.customer_name}</span>
                        {o.customer_phone && <a href={`tel:${o.customer_phone}`} className="do-tel"><Phone size={10} /> {o.customer_phone}</a>}
                      </div>}
                      {o.delivery_address && <div className="do-ad">{o.delivery_address}</div>}
                      <div className="do-rider">
                        <Bike size={11} />
                        {o.rider_nombre ? <span>{o.rider_nombre}</span> : <span className="do-sin">Sin asignar</span>}
                        <span className="do-monto">${fmt(fee)}</span>
                      </div>
                      {paid && o.comprobante_url && (
                        <div className="do-comp">
                          <a href={o.comprobante_url} target="_blank" className="do-comp-link">Ver comprobante</a>
                        </div>
                      )}
                      {!paid && !o.rider_id ? (
                        <div className="do-asign">
                          <select className="do-sel" defaultValue="" onChange={e => e.target.value && assignRider(o.id, e.target.value)} disabled={assigningId === o.id}>
                            <option value="">Asignar rider...</option>
                            {allRiders.map(r => <option key={r.id} value={r.id}>{r.nombre}</option>)}
                          </select>
                          {assigningId === o.id && <Loader2 size={12} className="spin" />}
                        </div>
                      ) : null}
                      {!paid && o.rider_id ? (
                        <div className="do-acts">
                          <select className="dg-met" value={metodoPago[o.id] || 'transferencia'} onChange={e => setMetodoPago({ ...metodoPago, [o.id]: e.target.value })}>
                            <option value="transferencia">Transferencia</option>
                            <option value="efectivo">Efectivo</option>
                          </select>
                          <label className={`dg-btn up ${uploadingId === o.id ? 'dis' : ''}`}>
                            {uploadingId === o.id ? <Loader2 size={12} className="spin" /> : <Upload size={12} />}
                            Subir comprobante
                            <input type="file" className="hidden" accept="image/*" disabled={uploadingId === o.id} onChange={e => { const file = e.target.files?.[0]; if (!file) return; e.target.value = ''; payOrder(o, file); }} />
                          </label>
                          <button className="dg-btn pay" onClick={() => payOrder(o, null)} disabled={uploadingId === o.id}>Pagar</button>
                        </div>
                      ) : null}
                      {paid && (
                        <div className="do-share">
                          <button className="dg-btn shr" onClick={() => sharePayment(o.id)}><Share2 size={12} /> Compartir detalle de pago</button>
                        </div>
                      )}
                    </div>
                  );
                })}
                {deliveryOrders.length === 0 && <div className="dm-empty">Sin pedidos delivery en este turno</div>}
              </div>
            )}
          </div>
        </div>
      )}

      <style>{`
        .aq-header{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:linear-gradient(to right,#ef4444,#f97316);flex-shrink:0;padding-top:max(0.75rem,env(safe-area-inset-top))}
        .aq-header h1{font-size:18px;color:white;margin:0;display:flex;align-items:center;gap:6px;font-weight:700}
        .aq-close{background:none;border:none;cursor:pointer;padding:6px;border-radius:6px;color:white;display:flex;align-items:center;justify-content:center}
        .aq-close:hover{background:rgba(255,255,255,0.2);color:white}
        .aq{max-width:600px;margin:0 auto;padding:4px}
        .hd{background:white;padding:8px 10px;border-radius:10px;margin-bottom:6px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
        .hd-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:2px}
        .hd h2{font-size:16px;color:#333;display:flex;align-items:center;gap:5px;margin:0}
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
        .del-arrow{color:#d97706;margin-left:auto}
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
        /* delivery modal */
        .dm-over{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:1000;overflow-y:auto;padding:16px}
        .dm{max-width:520px;margin:0 auto;background:white;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.2);overflow:hidden}
        .dm-h{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #e5e7eb;position:sticky;top:0;background:white;z-index:2}
        .dm-h h3{font-size:14px;display:flex;align-items:center;gap:6px;color:#374151;margin:0}
        .dm-x{background:none;border:none;color:#9ca3af;cursor:pointer;padding:4px}
        .dm-x:hover{color:#374151}
        .dm-load{padding:24px;text-align:center;font-size:13px;color:#9ca3af}
        .dm-b{padding:8px;display:flex;flex-direction:column;gap:8px}
        .do-card{border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:white}
        .do-paid{opacity:.65;background:#f9fafb}
        .do-h{display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:#f9fafb;border-bottom:1px solid #e5e7eb;gap:6px}
        .do-n{font-size:10px;font-weight:700;color:#374151}
        .do-hr{font-size:9px;color:#9ca3af;margin-left:auto}
        .do-badge{font-size:8px;background:#d1fae5;color:#059669;padding:1px 5px;border-radius:3px;font-weight:700}
        .do-c{padding:4px 10px;font-size:11px;color:#374151;display:flex;gap:6px;align-items:center}
        .do-tel{color:#3b82f6;text-decoration:none;display:inline-flex;align-items:center;gap:2px}
        .do-ad{padding:0 10px 4px;font-size:10px;color:#6b7280}
        .do-rider{display:flex;align-items:center;gap:6px;padding:4px 10px;border-top:1px solid #f3f4f6;font-size:11px;color:#374151}
        .do-sin{color:#9ca3af;font-style:italic}
        .do-monto{margin-left:auto;font-size:14px;font-weight:800;color:#059669}
        .do-comp{padding:4px 10px 6px;border-top:1px solid #f3f4f6}
        .do-comp-link{font-size:10px;color:#3b82f6;text-decoration:underline}
        .do-asign{display:flex;align-items:center;gap:6px;padding:6px 10px;border-top:1px solid #f3f4f6}
        .do-sel{flex:1;font-size:10px;padding:4px 6px;border:1px solid #d1d5db;border-radius:5px;background:white;color:#374151;outline:none}
        .do-acts{display:flex;align-items:center;gap:6px;padding:6px 10px;border-top:1px solid #f3f4f6;flex-wrap:wrap}
        .do-share{padding:6px 10px;border-top:1px solid #f3f4f6}
        .dg-met{font-size:10px;padding:4px 6px;border:1px solid #d1d5db;border-radius:5px;background:white;color:#374151;outline:none}
        .dg-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border:none;border-radius:6px;font-size:10px;font-weight:600;cursor:pointer;transition:all .15s}
        .dg-btn.up{background:#fef3c7;color:#d97706}.dg-btn.up:hover{background:#fde68a}
        .dg-btn.pay{background:#dbeafe;color:#2563eb}.dg-btn.pay:hover{background:#bfdbfe}
        .dg-btn.pay:disabled,.dis{opacity:.5;cursor:not-allowed;pointer-events:none}
        .dg-btn.shr{background:#f3e8ff;color:#7c3aed;width:100%;justify-content:center}
        .dg-btn.shr:hover{background:#e9d5ff}
        .dm-empty{padding:32px;text-align:center;font-size:13px;color:#9ca3af}
        .hidden{display:none}
        .spin{animation:spin 1s linear infinite}
        @keyframes spin{100%{transform:rotate(360deg)}}
      `}</style>
    </div>
  );
}
