import { useState, useEffect } from 'react';
import { CreditCard, Building2, Banknote, Smartphone, Bike, TrendingUp, Pencil, ChevronLeft, ChevronRight, BarChart3, ArrowLeft, Clock, Wallet, Moon, Calendar, BadgeDollarSign, ChevronDown, ChevronUp, Upload, CheckCircle, Share2, Loader2, X, Phone } from 'lucide-react';

export default function ArqueoApp() {
  const [salesData, setSalesData] = useState(null);
  const [saldoCaja, setSaldoCaja] = useState(0);
  const [ingresosHoy, setIngresosHoy] = useState(0);
  const [currentDaysAgo, setCurrentDaysAgo] = useState(0);
  const [initialLoading, setInitialLoading] = useState(true);
  const [isTransitioning, setIsTransitioning] = useState(false);
  const [currentTime, setCurrentTime] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [deliveryOrders, setDeliveryOrders] = useState([]);
  const [allRiders, setAllRiders] = useState([]);
  const [loadingOrders, setLoadingOrders] = useState(false);
  const [assigningId, setAssigningId] = useState(null);
  const [uploadingId, setUploadingId] = useState(null);
  const [metodoPago, setMetodoPago] = useState({});
  const [comprobanteModal, setComprobanteModal] = useState(null);
  const [reportModal, setReportModal] = useState(null);

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
      } else {
        alert('Error: ' + (res.error || 'desconocido'));
      }
    } catch (e) {
      alert('Error al asignar rider');
    } finally {
      setAssigningId(null);
    }
  };

  const uploadComprobante = async (file) => {
    const fd = new FormData();
    fd.append('comprobante', file);
    const res = await (await fetch('/api/riders/upload_comprobante.php', { method: 'POST', body: fd })).json();
    if (!res.success) throw new Error(res.error || 'Error subiendo comprobante');
    return res.url;
  };

  const handlePayRider = async (riderId, e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    e.target.value = '';
    setUploadingId(riderId);
    try {
      const comprobanteUrl = await uploadComprobante(file);
      const fd = new FormData();
      fd.append('rider_id', riderId);
      fd.append('comprobante_url', comprobanteUrl);
      fd.append('metodo_pago', metodoPago[riderId] || 'transferencia');
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
      alert(err.message || 'Error al conectar con el servidor');
    } finally {
      setUploadingId(null);
    }
  };

  const handlePayNoFile = async (riderId, riderName) => {
    if (!confirm(`Pagar a ${riderName}?`)) return;
    setUploadingId(riderId);
    try {
      const fd = new FormData();
      fd.append('rider_id', riderId);
      fd.append('metodo_pago', metodoPago[riderId] || 'transferencia');
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

  const sharePayment = async (rider) => {
    if (!rider || !rider.token) return;
    const url = `${window.location.origin}/pago-rider.php?token=${rider.token}`;
    try {
      await navigator.clipboard.writeText(url);
      alert('Link copiado al portapapeles');
    } catch {
      prompt('Comparte este link:', url);
    }
  };

  const openCajaModal = () => window.dispatchEvent(new CustomEvent('openSaldoCajaModal'));
  const showDetail = () => {
    if (!salesData) return;
    window.location.href = `/ventas-detalle?start=${encodeURIComponent(salesData.period.start)}&end=${encodeURIComponent(salesData.period.end)}`;
  };
  const openReportModal = () => {
    if (!salesData) return;
    setReportModal(true);
  };
  const fmt = (n) => Math.round(n).toLocaleString('es-CL');

  // Group delivery orders by rider for modal view
  const groupByRider = (orders) => {
    const groups = {};
    orders.forEach(o => {
      const key = o.rider_id || 0;
      if (!groups[key]) {
        groups[key] = {
          rider_id: key,
          rider_name: o.rider_nombre || 'Sin asignar',
          orders: [],
          total_fees: 0,
        };
      }
      groups[key].orders.push(o);
      const fee = parseFloat(o.delivery_fee) + parseFloat(o.card_surcharge || 0);
      groups[key].total_fees += fee;
    });
    return Object.values(groups);
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
          <div
            key={i}
            className={`tr ${r.cls || ''} ${r.isDelivery ? 'tr-del' : ''}`}
            onClick={() => r.isDelivery && openDeliveryModal()}
            style={r.isDelivery ? { cursor: 'pointer' } : {}}
          >
            <div className="td-m">
              {r.icon}
              <span>{r.label}</span>
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

      {/* Delivery Modal */}
      {showModal && (
        <div className="modal-over" onClick={() => setShowModal(false)}>
          <div className="modal" onClick={e => e.stopPropagation()}>
            <div className="modal-h">
              <h2><Bike size={16} /> Delivery</h2>
              <div className="modal-h-acts">
                {deliveryOrders.length > 0 && <button className="modal-report-btn" onClick={openReportModal}>Reporte</button>}
                <button className="modal-x" onClick={() => setShowModal(false)}><X size={16} /></button>
              </div>
            </div>

            {loadingOrders ? (
              <div className="modal-load">Cargando pedidos...</div>
            ) : (
              <div className="modal-body">
                {groupByRider(deliveryOrders).map((group) => {
                  const riderRiders = riders.find(r => parseInt(r.rider_id) === group.rider_id);
                  const isPaid = riderRiders && parseInt(riderRiders.todos_pagados) === 1;
                  return (
                    <div key={group.rider_id} className={`del-g ${isPaid ? 'del-g-paid' : ''}`}>
                      <div className="del-g-h">
                        <div className="del-g-hl">
                          <span className="del-g-n">{group.rider_name}</span>
                          <span className={`del-g-s ${isPaid ? 'st-paid' : 'st-pen'}`}>
                            {isPaid ? 'Pagado' : 'Pendiente'}
                          </span>
                        </div>
                        <span className="del-g-t">${fmt(group.total_fees)}</span>
                      </div>

                      <div className="del-g-ords">
                        {group.orders.map(o => (
                          <div key={o.id} className="del-ord">
                            <div className="del-ord-h">
                              <span className="del-ord-n">{o.order_number}</span>
                              <span className="del-ord-hr">{o.hora}</span>
                            </div>
                            <div className="del-ord-c">
                              {o.customer_name && <span>{o.customer_name}</span>}
                              {o.customer_phone && (
                                <a href={`tel:${o.customer_phone}`} className="del-ord-tel">
                                  <Phone size={10} /> {o.customer_phone}
                                </a>
                              )}
                            </div>
                            {o.delivery_address && <div className="del-ord-ad">{o.delivery_address}</div>}
                            <div className="del-ord-f">
                              Delivery ${fmt(o.delivery_fee)}{parseFloat(o.card_surcharge || 0) > 0 && ` (+$${fmt(o.card_surcharge)} 💳)`}
                            </div>
                            {!group.rider_id && (
                              <div className="del-ord-asign">
                                <select
                                  className="del-asign-s"
                                  defaultValue=""
                                  onChange={(e) => e.target.value && assignRider(o.id, e.target.value)}
                                  disabled={assigningId === o.id}
                                >
                                  <option value="">Asignar rider...</option>
                                  {allRiders.map(r => (
                                    <option key={r.id} value={r.id}>{r.nombre}</option>
                                  ))}
                                </select>
                                {assigningId === o.id && <Loader2 size={12} className="spin" />}
                              </div>
                            )}
                          </div>
                        ))}
                      </div>

                      {!isPaid && (
                        <div className="del-g-actions">
                          <select
                            className="del-metodo"
                            value={metodoPago[group.rider_id] || 'transferencia'}
                            onChange={(e) => setMetodoPago({ ...metodoPago, [group.rider_id]: e.target.value })}
                          >
                            <option value="transferencia">Transferencia</option>
                            <option value="efectivo">Efectivo</option>
                          </select>
                          <label className={`del-btn del-btn-up ${uploadingId === group.rider_id ? 'del-btn-dis' : ''}`}>
                            {uploadingId === group.rider_id ? <Loader2 size={12} className="spin" /> : <Upload size={12} />}
                            Subir comprobante
                            <input type="file" className="hidden" accept="image/*" disabled={uploadingId === group.rider_id} onChange={(e) => handlePayRider(group.rider_id || 0, e)} />
                          </label>
                          <button className="del-btn del-btn-pay" onClick={() => handlePayNoFile(group.rider_id || 0, group.rider_name)} disabled={uploadingId === group.rider_id}>
                            Pagar
                          </button>
                        </div>
                      )}

                      {isPaid && riderRiders?.token && (
                        <>
                          {deliveryOrders.find(o => o.rider_id === group.rider_id && o.comprobante_url) && (
                            <div className="del-g-comprobante-container">
                              <span className="del-g-comprobante-label">Comprobante</span>
                              <img
                                src={deliveryOrders.find(o => o.rider_id === group.rider_id && o.comprobante_url)?.comprobante_url}
                                alt="Comprobante"
                                className="del-g-comprobante-thumb"
                                onClick={() => setComprobanteModal({ url: deliveryOrders.find(o => o.rider_id === group.rider_id && o.comprobante_url)?.comprobante_url, name: group.rider_name })}
                              />
                            </div>
                          )}
                          <button className="del-btn del-btn-share" onClick={() => sharePayment(riderRiders)}>
                            <Share2 size={12} /> Compartir detalle de pago
                          </button>
                        </>
                      )}
                    </div>
                  );
                })}
                {deliveryOrders.length === 0 && (
                  <div className="del-empty-modal">Sin pedidos delivery en este turno</div>
                )}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Comprobante Lightbox */}
      {comprobanteModal && (
        <div className="overlay" onClick={() => setComprobanteModal(null)}>
          <div className="comprobante-overlay" onClick={e => e.stopPropagation()}>
            <button className="comprobante-close" onClick={() => setComprobanteModal(null)}><X size={20} /></button>
            <div className="comprobante-header">{comprobanteModal.name}</div>
            <img src={comprobanteModal.url} alt="Comprobante de pago" className="comprobante-full" />
          </div>
        </div>
      )}

      {/* Report Modal */}
      {reportModal && (
        <div className="overlay" onClick={() => setReportModal(false)}>
          <div className="report-modal" onClick={e => e.stopPropagation()}>
            <div className="report-h">
              <h3><Bike size={16} /> Reporte Delivery</h3>
              <button onClick={() => setReportModal(false)}><X size={20} /></button>
            </div>
            <div className="report-body">
              <div className="report-period">{salesData.shift_date}</div>
              <div className="report-summary">
                <span>Total delivery: ${fmt(deliveryTotal)}</span>
                <span>Pedidos: {deliveryCount}</span>
              </div>
              {groupByRider(deliveryOrders).map(g => {
                const rr = riders.find(r => parseInt(r.rider_id) === g.rider_id);
                const paid = rr && parseInt(rr.todos_pagados) === 1;
                return (
                  <div key={g.rider_id} className="report-rider">
                    <div className="report-rider-h">
                      <strong>{g.rider_name}</strong>
                      <span>${fmt(g.total_fees)}</span>
                      <span className={`report-badge ${paid ? 'rbp' : 'rbpe'}`}>{paid ? 'Pagado' : 'Pendiente'}</span>
                    </div>
                    {g.orders.map(o => (
                      <div key={o.id} className="report-order">
                        <span>{o.order_number}</span>
                        <span>{o.customer_name}</span>
                        <span>${fmt(parseFloat(o.delivery_fee) + parseFloat(o.card_surcharge || 0))}</span>
                      </div>
                    ))}
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      )}

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
        .del-arrow{color:#d97706;margin-left:auto}
        /* Modal */
        .modal-over{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:1000;overflow-y:auto;padding:16px}
        .modal{max-width:520px;margin:0 auto;background:white;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.2);overflow:hidden}
        .modal-h{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #e5e7eb;position:sticky;top:0;background:white;z-index:2}
        .modal-h h2{font-size:14px;display:flex;align-items:center;gap:6px;color:#374151;margin:0}
        .modal-h-acts{display:flex;align-items:center;gap:6px}
        .modal-report-btn{background:#f3e8ff;color:#7c3aed;border:none;border-radius:5px;padding:4px 8px;font-size:10px;font-weight:600;cursor:pointer}
        .modal-report-btn:hover{background:#e9d5ff}
        .modal-x{background:none;border:none;color:#9ca3af;cursor:pointer;padding:4px}
        .modal-x:hover{color:#374151}
        .modal-load{padding:24px;text-align:center;font-size:13px;color:#9ca3af}
        .modal-body{padding:8px}
        .del-g{border:1px solid #e5e7eb;border-radius:10px;margin-bottom:8px;overflow:hidden}
        .del-g-paid{opacity:.65;background:#f9fafb}
        .del-g-h{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#f9fafb;border-bottom:1px solid #e5e7eb}
        .del-g-hl{display:flex;align-items:center;gap:6px}
        .del-g-n{font-size:13px;font-weight:700;color:#374151}
        .del-g-t{font-size:16px;font-weight:800;color:#111827}
        .st-paid{font-size:9px;background:#d1fae5;color:#059669;padding:1px 5px;border-radius:3px;font-weight:600}
        .st-pen{font-size:9px;background:#fef3c7;color:#d97706;padding:1px 5px;border-radius:3px;font-weight:600}
        .del-g-ords{padding:6px 12px}
        .del-ord{padding:6px 0;border-bottom:1px solid #f3f4f6}
        .del-ord:last-child{border-bottom:none}
        .del-ord-h{display:flex;justify-content:space-between;font-size:10px;color:#6b7280;margin-bottom:1px}
        .del-ord-n{font-weight:600;color:#374151}
        .del-ord-hr{color:#9ca3af}
        .del-ord-c{font-size:11px;color:#374151;display:flex;gap:6px;align-items:center;margin-bottom:1px}
        .del-ord-tel{color:#3b82f6;text-decoration:none;display:inline-flex;align-items:center;gap:2px}
        .del-ord-ad{font-size:10px;color:#6b7280;margin-bottom:2px}
        .del-ord-f{font-size:11px;font-weight:600;color:#059669;margin-bottom:4px}
        .del-ord-asign{display:flex;align-items:center;gap:6px}
        .del-asign-s{flex:1;font-size:10px;padding:4px 6px;border:1px solid #d1d5db;border-radius:5px;background:white;color:#374151;outline:none}
        .del-g-actions{display:flex;align-items:center;gap:6px;padding:8px 12px;border-top:1px solid #e5e7eb;flex-wrap:wrap}
        .del-metodo{font-size:10px;padding:4px 6px;border:1px solid #d1d5db;border-radius:5px;background:white;color:#374151;outline:none}
        .del-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border:none;border-radius:6px;font-size:10px;font-weight:600;cursor:pointer;transition:all .15s}
        .del-btn-up{background:#fef3c7;color:#d97706}
        .del-btn-up:hover{background:#fde68a}
        .del-btn-pay{background:#dbeafe;color:#2563eb}
        .del-btn-pay:hover{background:#bfdbfe}
        .del-btn-pay:disabled{opacity:.5;cursor:not-allowed}
        .del-btn-dis{opacity:.5;cursor:not-allowed;pointer-events:none}
        .del-btn-share{background:#f3e8ff;color:#7c3aed;margin:8px 12px;width:calc(100% - 24px);justify-content:center}
        .del-btn-share:hover{background:#e9d5ff}
        .del-g-comprobante-container{padding:4px 12px 0;display:flex;align-items:center;gap:8px}
        .del-g-comprobante-label{font-size:9px;color:#6b7280;font-weight:600}
        .del-g-comprobante-thumb{width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;cursor:pointer;transition:transform .15s}
        .del-g-comprobante-thumb:hover{transform:scale(1.1);box-shadow:0 2px 8px rgba(0,0,0,.15)}
        .overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.8);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px}
        .comprobante-overlay{position:relative;max-width:90vw;max-height:90vh;display:flex;flex-direction:column;align-items:center}
        .comprobante-close{position:absolute;top:-36px;right:0;background:none;border:none;color:white;cursor:pointer;padding:4px}
        .comprobante-header{color:white;font-size:14px;font-weight:700;margin-bottom:8px;text-align:center}
        .comprobante-full{max-width:100%;max-height:80vh;border-radius:8px;object-fit:contain}
        .report-modal{background:white;border-radius:14px;max-width:600px;width:100%;max-height:85vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 8px 30px rgba(0,0,0,.3)}
        .report-h{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #e5e7eb;background:linear-gradient(to right,#ef4444,#f97316);flex-shrink:0;padding-top:max(0.75rem,env(safe-area-inset-top))}
        .report-h h3{font-size:16px;display:flex;align-items:center;gap:6px;color:white;margin:0}
        .report-h button{background:none;border:none;color:white;cursor:pointer;padding:4px}
        .report-body{flex:1;overflow-y:auto;padding:12px 14px}
        .report-period{font-size:13px;font-weight:700;color:#374151;margin-bottom:8px;text-align:center}
        .report-summary{display:flex;justify-content:space-between;background:#f3f4f6;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;font-weight:700;color:#374151}
        .report-rider{border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px;overflow:hidden}
        .report-rider-h{display:flex;align-items:center;gap:8px;padding:8px 10px;background:#f9fafb;font-size:12px}
        .report-rider-h strong{flex:1}
        .report-rider-h span:last-child{margin-left:auto}
        .report-badge{font-size:8px;padding:1px 5px;border-radius:3px;font-weight:700}
        .rbp{background:#d1fae5;color:#059669}
        .rbpe{background:#fef3c7;color:#d97706}
        .report-order{display:flex;gap:8px;padding:4px 10px 4px 16px;font-size:10px;color:#6b7280;border-bottom:1px solid #f9fafb}
        .report-order:last-child{border-bottom:none}
        .report-order span:nth-child(1){min-width:120px;font-weight:600;color:#374151}
        .report-order span:nth-child(2){flex:1}
        .report-order span:nth-child(3){font-weight:700;color:#059669}
        .del-empty-modal{padding:32px;text-align:center;font-size:13px;color:#9ca3af}
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
