import { useState, useEffect } from 'react';

const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const DIAS_SEMANA = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];

const COLORES = {
  1: { bg: '#3b82f6', light: '#eff6ff', border: '#bfdbfe', text: '#1d4ed8' }, // Camila - azul
  2: { bg: '#8b5cf6', light: '#f5f3ff', border: '#ddd6fe', text: '#6d28d9' }, // Neit - violeta
  3: { bg: '#f59e0b', light: '#fffbeb', border: '#fde68a', text: '#b45309' }, // Andrés - amarillo
  4: { bg: '#10b981', light: '#ecfdf5', border: '#a7f3d0', text: '#047857' }, // Gabriel - verde
};

export default function PersonalApp() {
  const [tab, setTab] = useState('calendario');
  const [mes, setMes] = useState(() => new Date().getMonth());
  const [anio, setAnio] = useState(() => new Date().getFullYear());
  const [personal, setPersonal] = useState([]);
  const [turnos, setTurnos] = useState([]);
  const [ajustes, setAjustes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [pagosNomina, setPagosNomina] = useState([]);
  const [presupuestoNomina, setPresupuestoNomina] = useState(1200000);
  const [modalAjuste, setModalAjuste] = useState(null);
  const [formAjuste, setFormAjuste] = useState({ monto: '', concepto: '' });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);

  useEffect(() => { loadData(); }, [mes, anio]);

  async function loadData() {
    setLoading(true);
    try {
      const mesStr = String(mes + 1).padStart(2, '0');
      const [pRes, tRes, aRes, pnRes] = await Promise.all([
        fetch('/api/personal/get_personal.php'),
        fetch(`/api/personal/get_turnos.php?mes=${anio}-${mesStr}`),
        fetch(`/api/personal/get_ajustes.php?mes=${anio}-${mesStr}`),
        fetch(`/api/personal/get_pagos_nomina.php?mes=${anio}-${mesStr}`),
      ]);
      const [p, t, a, pn] = await Promise.all([pRes.json(), tRes.json(), aRes.json(), pnRes.json()]);
      if (p.success) setPersonal(p.data);
      if (t.success) setTurnos(t.data);
      if (a.success) setAjustes(a.data);
      if (pn.success) { setPagosNomina(pn.data); setPresupuestoNomina(pn.presupuesto ?? 1200000); }
    } catch (e) {
      showToast('Error cargando datos', 'error');
    }
    setLoading(false);
  }

  function showToast(msg, type = 'success') {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3000);
  }

  async function saveAjuste() {
    if (!formAjuste.monto || !formAjuste.concepto) return;
    setSaving(true);
    const mesStr = String(mes + 1).padStart(2, '0');
    try {
      const res = await fetch('/api/personal/save_ajuste.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          personal_id: modalAjuste.id,
          mes: `${anio}-${mesStr}-01`,
          monto: parseFloat(formAjuste.monto),
          concepto: formAjuste.concepto,
        }),
      });
      const data = await res.json();
      if (data.success) {
        showToast('Ajuste guardado');
        setModalAjuste(null);
        setFormAjuste({ monto: '', concepto: '' });
        loadData();
      } else {
        showToast(data.error || 'Error', 'error');
      }
    } catch (e) {
      showToast('Error de conexión', 'error');
    }
    setSaving(false);
  }

  async function deleteAjuste(id) {
    if (!confirm('¿Eliminar este ajuste?')) return;
    try {
      const res = await fetch('/api/personal/delete_ajuste.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
      });
      const data = await res.json();
      if (data.success) { showToast('Ajuste eliminado'); loadData(); }
    } catch (e) { showToast('Error', 'error'); }
  }

  // Calcular días en el mes
  const diasEnMes = new Date(anio, mes + 1, 0).getDate();
  const primerDia = new Date(anio, mes, 1).getDay();

  // Mapa de turnos por fecha
  const turnosPorFecha = {};
  turnos.forEach(t => {
    const d = t.fecha.split('T')[0].split('-')[2];
    const dia = parseInt(d);
    if (!turnosPorFecha[dia]) turnosPorFecha[dia] = [];
    turnosPorFecha[dia].push(t);
  });

  // Calcular liquidación por persona
  function getLiquidacion(p) {
    const tPersonal = turnos.filter(t => t.personal_id == p.id);
    const diasNormales = tPersonal.filter(t => t.tipo === 'normal').length;
    // Días que fue reemplazado (no trabajó) — con info de quién lo reemplazó
    const turnosReemplazados = tPersonal.filter(t => t.tipo === 'reemplazo').map(t => ({
      ...t,
      reemplazante: personal.find(x => x.id == t.reemplazado_por) || { nombre: t.reemplazante_nombre || t.notas || '?' },
    }));
    const diasReemplazados = turnosReemplazados.length;
    // Días que reemplazó a otro — con info de a quién reemplazó
    const turnosReemplazando = turnos.filter(t => t.reemplazado_por == p.id).map(t => ({
      ...t,
      reemplazado: personal.find(x => x.id == t.personal_id),
    }));
    const reemplazosHechos = turnosReemplazando.length;
    const diasTrabajados = diasNormales + reemplazosHechos;
    const ajustesPer = ajustes.filter(a => a.personal_id == p.id);
    const totalAjustes = ajustesPer.reduce((s, a) => s + parseFloat(a.monto), 0);
    const sueldoBase = parseFloat(p.sueldo_base);
    const total = sueldoBase + totalAjustes;
    return { diasNormales, diasReemplazados, reemplazosHechos, diasTrabajados, ajustesPer, totalAjustes, sueldoBase, turnosReemplazados, turnosReemplazando, total };
  }

  const cajeros = personal.filter(p => p.rol === 'cajero' && p.activo == 1);
  const plancheros = personal.filter(p => p.rol === 'planchero' && p.activo == 1);

  return (
    <div style={{ fontFamily: 'system-ui, sans-serif', minHeight: '100vh', background: '#f8fafc' }}>
      {/* Header */}
      <div style={{ background: 'linear-gradient(135deg, #1e293b 0%, #334155 100%)', color: 'white', padding: '20px 24px' }}>
        <div style={{ maxWidth: 1100, margin: '0 auto' }}>
          <h1 style={{ margin: 0, fontSize: 22, fontWeight: 700 }}>👥 Gestión de Personal</h1>
          <p style={{ margin: '4px 0 0', opacity: 0.7, fontSize: 13 }}>Turnos 4x4 · Sueldo $300.000/mes</p>
        </div>
      </div>

      {/* Tabs */}
      <div style={{ background: 'white', borderBottom: '1px solid #e2e8f0', position: 'sticky', top: 0, zIndex: 10 }}>
        <div style={{ maxWidth: 1100, margin: '0 auto', display: 'flex', gap: 0 }}>
          {[['calendario','📅 Calendario'],['liquidacion','💰 Liquidación'],['equipo','👤 Equipo']].map(([key, label]) => (
            <button key={key} onClick={() => setTab(key)} style={{
              padding: '14px 20px', border: 'none', background: 'none', cursor: 'pointer',
              fontSize: 14, fontWeight: 600,
              color: tab === key ? '#3b82f6' : '#64748b',
              borderBottom: tab === key ? '2px solid #3b82f6' : '2px solid transparent',
            }}>{label}</button>
          ))}
        </div>
      </div>

      <div style={{ maxWidth: 1100, margin: '0 auto', padding: '20px 16px' }}>
        {/* Navegación de mes */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 20 }}>
          <button onClick={() => { if (mes === 0) { setMes(11); setAnio(a => a-1); } else setMes(m => m-1); }}
            style={{ padding: '8px 14px', border: '1px solid #e2e8f0', borderRadius: 8, background: 'white', cursor: 'pointer', fontSize: 16 }}>‹</button>
          <span style={{ fontWeight: 700, fontSize: 18, minWidth: 160, textAlign: 'center' }}>{MESES[mes]} {anio}</span>
          <button onClick={() => { if (mes === 11) { setMes(0); setAnio(a => a+1); } else setMes(m => m+1); }}
            style={{ padding: '8px 14px', border: '1px solid #e2e8f0', borderRadius: 8, background: 'white', cursor: 'pointer', fontSize: 16 }}>›</button>
        </div>

        {loading ? (
          <div style={{ textAlign: 'center', padding: 60, color: '#94a3b8' }}>
            <div style={{ fontSize: 32, marginBottom: 8 }}>⏳</div>Cargando...
          </div>
        ) : (
          <>
            {tab === 'calendario' && <CalendarioView diasEnMes={diasEnMes} primerDia={primerDia} turnosPorFecha={turnosPorFecha} personal={personal} colores={COLORES} />}
            {tab === 'liquidacion' && <LiquidacionView personal={personal} cajeros={cajeros} plancheros={plancheros} getLiquidacion={getLiquidacion} colores={COLORES} onAjuste={setModalAjuste} onDeleteAjuste={deleteAjuste} mes={mes} anio={anio} pagosNomina={pagosNomina} onReloadPagos={loadData} showToast={showToast} presupuesto={presupuestoNomina} />}
            {tab === 'equipo' && <EquipoView personal={personal} turnos={turnos} colores={COLORES} getLiquidacion={getLiquidacion} />}
          </>
        )}
      </div>

      {/* Modal ajuste */}
      {modalAjuste && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 50, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}>
          <div style={{ background: 'white', borderRadius: 16, padding: 24, width: '100%', maxWidth: 400, boxShadow: '0 20px 60px rgba(0,0,0,0.3)' }}>
            <h3 style={{ margin: '0 0 4px', fontSize: 17, fontWeight: 700 }}>Agregar Ajuste</h3>
            <p style={{ margin: '0 0 20px', color: '#64748b', fontSize: 13 }}>{modalAjuste.nombre} · {MESES[mes]} {anio}</p>
            <div style={{ marginBottom: 14 }}>
              <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 6, color: '#374151' }}>Monto (negativo = descuento)</label>
              <input type="number" value={formAjuste.monto} onChange={e => setFormAjuste(f => ({...f, monto: e.target.value}))}
                placeholder="Ej: -40000 o 20000"
                style={{ width: '100%', padding: '10px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 15, boxSizing: 'border-box' }} />
            </div>
            <div style={{ marginBottom: 20 }}>
              <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 6, color: '#374151' }}>Concepto</label>
              <input type="text" value={formAjuste.concepto} onChange={e => setFormAjuste(f => ({...f, concepto: e.target.value}))}
                placeholder="Ej: Descuento reemplazo día 7"
                style={{ width: '100%', padding: '10px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }} />
            </div>
            <div style={{ display: 'flex', gap: 10 }}>
              <button onClick={() => { setModalAjuste(null); setFormAjuste({ monto: '', concepto: '' }); }}
                style={{ flex: 1, padding: '10px', border: '1px solid #e2e8f0', borderRadius: 8, background: 'white', cursor: 'pointer', fontWeight: 600 }}>Cancelar</button>
              <button onClick={saveAjuste} disabled={saving}
                style={{ flex: 1, padding: '10px', border: 'none', borderRadius: 8, background: '#3b82f6', color: 'white', cursor: 'pointer', fontWeight: 600 }}>
                {saving ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Toast */}
      {toast && (
        <div style={{
          position: 'fixed', bottom: 24, right: 24, zIndex: 100,
          background: toast.type === 'error' ? '#ef4444' : '#10b981',
          color: 'white', padding: '12px 20px', borderRadius: 10, fontWeight: 600, fontSize: 14,
          boxShadow: '0 4px 20px rgba(0,0,0,0.2)',
        }}>{toast.msg}</div>
      )}
    </div>
  );
}

function CalendarioView({ diasEnMes, primerDia, turnosPorFecha, personal, colores }) {
  const celdas = [];
  for (let i = 0; i < primerDia; i++) celdas.push(null);
  for (let d = 1; d <= diasEnMes; d++) celdas.push(d);
  const DIAS_LABEL = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];

  return (
    <div>
      {/* Leyenda */}
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 14 }}>
        {personal.filter(p => p.activo == 1).map(p => (
          <div key={p.id} style={{ display: 'flex', alignItems: 'center', gap: 5, padding: '4px 10px', borderRadius: 20, background: colores[p.id]?.light, border: `1px solid ${colores[p.id]?.border}` }}>
            <div style={{ width: 8, height: 8, borderRadius: '50%', background: colores[p.id]?.bg }} />
            <span style={{ fontSize: 12, fontWeight: 600, color: colores[p.id]?.text }}>{p.nombre}</span>
          </div>
        ))}
      </div>

      {/* Vista lista móvil */}
      <div style={{ display: 'block' }} className="cal-mobile">
        <style>{`
          @media (min-width: 640px) { .cal-mobile { display: none !important; } .cal-grid { display: block !important; } }
          @media (max-width: 639px) { .cal-mobile { display: block !important; } .cal-grid { display: none !important; } }
        `}</style>
        <div style={{ background: 'white', borderRadius: 12, overflow: 'hidden', border: '1px solid #e2e8f0' }}>
          {Array.from({ length: diasEnMes }, (_, i) => i + 1).map(dia => {
            const trabajando = turnosPorFecha[dia] || [];
            const diaSemana = new Date(new Date().getFullYear(), new Date().getMonth(), dia).getDay();
            // Calcular día de semana real
            const fecha = new Date();
            fecha.setDate(1);
            const primerDiaSemana = fecha.getDay();
            const diaLabel = DIAS_LABEL[(primerDia + dia - 1) % 7];
            const esFinSemana = (primerDia + dia - 1) % 7 === 0 || (primerDia + dia - 1) % 7 === 6;
            return (
              <div key={dia} style={{
                display: 'flex', alignItems: 'center', gap: 12, padding: '10px 14px',
                borderBottom: '1px solid #f1f5f9',
                background: esFinSemana ? '#fafafa' : 'white',
              }}>
                <div style={{ minWidth: 44, textAlign: 'center' }}>
                  <div style={{ fontSize: 11, color: '#94a3b8', fontWeight: 600 }}>{diaLabel}</div>
                  <div style={{ fontSize: 20, fontWeight: 800, color: esFinSemana ? '#94a3b8' : '#1e293b', lineHeight: 1.2 }}>{dia}</div>
                </div>
                <div style={{ flex: 1, display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                  {trabajando.length === 0 ? (
                    <span style={{ fontSize: 12, color: '#cbd5e1' }}>—</span>
                  ) : trabajando.map(t => {
                    const p = personal.find(p => p.id == t.personal_id);
                    if (!p) return null;
                    const c = colores[p.id];
                    return (
                      <div key={t.id} style={{
                        display: 'flex', alignItems: 'center', gap: 5,
                        background: c?.light, border: `1px solid ${c?.border}`,
                        borderRadius: 20, padding: '3px 10px',
                      }}>
                        <div style={{ width: 7, height: 7, borderRadius: '50%', background: c?.bg }} />
                        <span style={{ fontSize: 13, fontWeight: 600, color: c?.text }}>
                          {p.nombre}{t.tipo === 'reemplazo' ? ' ↔' : ''}
                        </span>
                      </div>
                    );
                  })}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Vista grid desktop */}
      <div style={{ display: 'none' }} className="cal-grid">
        <div style={{ background: 'white', borderRadius: 12, overflow: 'hidden', border: '1px solid #e2e8f0' }}>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', background: '#f1f5f9' }}>
            {['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'].map(d => (
              <div key={d} style={{ padding: '10px 4px', textAlign: 'center', fontSize: 12, fontWeight: 700, color: '#64748b' }}>{d}</div>
            ))}
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 1, background: '#e2e8f0' }}>
            {celdas.map((dia, i) => {
              const trabajando = dia ? (turnosPorFecha[dia] || []) : [];
              return (
                <div key={i} style={{ background: dia ? 'white' : '#f8fafc', minHeight: 80, padding: '6px 5px' }}>
                  {dia && (
                    <>
                      <div style={{ fontSize: 12, fontWeight: 700, color: '#374151', marginBottom: 4 }}>{dia}</div>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        {trabajando.map(t => {
                          const p = personal.find(p => p.id == t.personal_id);
                          if (!p) return null;
                          const c = colores[p.id];
                          return (
                            <div key={t.id} style={{
                              background: c?.light, borderLeft: `3px solid ${c?.bg}`,
                              borderRadius: 3, padding: '1px 5px', fontSize: 11, fontWeight: 600,
                              color: c?.text, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                            }}>
                              {p.nombre}{t.tipo === 'reemplazo' ? ' ↔' : ''}
                            </div>
                          );
                        })}
                      </div>
                    </>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}

function LiquidacionView({ personal, cajeros, plancheros, getLiquidacion, colores, onAjuste, onDeleteAjuste, mes, anio, pagosNomina, onReloadPagos, showToast, presupuesto }) {
  const MESES_L = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  const [modalPago, setModalPago] = useState(null); // null | 'nuevo'
  const [formPago, setFormPago] = useState({ nombre: '', monto: '', es_externo: false, personal_id: '', notas: '' });
  const [savingPago, setSavingPago] = useState(false);
  const [expandidos, setExpandidos] = useState({});
  const [copied, setCopied] = useState(false);

  function toggleCard(id) { setExpandidos(e => ({ ...e, [id]: !e[id] })); }

  function generarMarkdown() {
    const mesLabel = `${MESES_L[mes]} ${anio}`;
    let md = `*💰 Liquidación Nómina — ${mesLabel}*\n━━━━━━━━━━━━━━━━━━━━\n`;
    personal.forEach(p => {
      const { diasTrabajados, sueldoBase, ajustesPer, turnosReemplazando, turnosReemplazados, total } = getLiquidacion(p);
      md += `\n*${p.nombre}* (${p.rol})\n📅 Días: ${diasTrabajados}\nBase: $${sueldoBase.toLocaleString('es-CL')}\n`;
      turnosReemplazando.forEach(t => { md += `↔ Reemplazó a ${t.reemplazado?.nombre ?? '?'} (día ${t.fecha?.split('T')[0].split('-')[2]}): +$20.000\n`; });
      turnosReemplazados.forEach(t => { md += `↔ Reemplazado por ${t.reemplazante?.nombre ?? '?'} (día ${t.fecha?.split('T')[0].split('-')[2]}): -$20.000\n`; });
      ajustesPer.forEach(a => {
        const m = parseFloat(a.monto);
        md += `${m < 0 ? '🔻' : '🔺'} ${a.concepto}: ${m < 0 ? '-' : '+'}$${Math.abs(m).toLocaleString('es-CL')}\n`;
      });
      md += `*Total: $${total.toLocaleString('es-CL')}*\n`;
    });
    if (pagosNomina.length > 0) {
      const tp = pagosNomina.reduce((s, p) => s + parseFloat(p.monto), 0);
      md += `\n━━━━━━━━━━━━━━━━━━━━\n*💸 Pagos Reales*\n`;
      pagosNomina.forEach(p => { md += `• ${p.nombre}${p.es_externo ? ' (ext)' : ''}: $${parseFloat(p.monto).toLocaleString('es-CL')}\n`; });
      md += `*Total: $${tp.toLocaleString('es-CL')} / Presupuesto: $${presupuesto.toLocaleString('es-CL')}*\n`;
    }
    return md;
  }

  function copiarMarkdown() {
    navigator.clipboard.writeText(generarMarkdown()).then(() => { setCopied(true); setTimeout(() => setCopied(false), 2500); });
  }

  const totalCalculado = personal.reduce((s, p) => s + getLiquidacion(p).total, 0);
  const totalPagado = pagosNomina.reduce((s, p) => s + parseFloat(p.monto), 0);
  const diferencia = totalPagado - presupuesto;
  const hayPagos = pagosNomina.length > 0;

  async function savePago() {
    if (!formPago.nombre || !formPago.monto) return;
    setSavingPago(true);
    const mesStr = String(mes + 1).padStart(2, '0');
    try {
      const res = await fetch('/api/personal/save_pago_nomina.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          mes: `${anio}-${mesStr}`,
          personal_id: formPago.personal_id || null,
          nombre: formPago.nombre,
          monto: parseFloat(formPago.monto),
          es_externo: formPago.es_externo ? 1 : 0,
          notas: formPago.notas,
        }),
      });
      const data = await res.json();
      if (data.success) { showToast('Pago registrado'); setModalPago(null); setFormPago({ nombre: '', monto: '', es_externo: false, personal_id: '', notas: '' }); onReloadPagos(); }
      else showToast(data.error || 'Error', 'error');
    } catch { showToast('Error de conexión', 'error'); }
    setSavingPago(false);
  }

  async function deletePago(id) {
    if (!confirm('¿Eliminar este pago?')) return;
    try {
      const res = await fetch('/api/personal/delete_pago_nomina.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
      const data = await res.json();
      if (data.success) { showToast('Pago eliminado'); onReloadPagos(); }
    } catch { showToast('Error', 'error'); }
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
      {/* Centro de costos */}
      <div style={{ background: 'linear-gradient(135deg, #1e293b, #334155)', borderRadius: 16, padding: '20px 24px', color: 'white' }}>
        <div style={{ fontSize: 13, opacity: 0.7, marginBottom: 12 }}>💼 Centro de Costos — Nómina {MESES_L[mes]} {anio}</div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 16 }}>
          <div>
            <div style={{ fontSize: 12, opacity: 0.6 }}>Presupuesto</div>
            <div style={{ fontSize: 22, fontWeight: 800 }}>${presupuesto.toLocaleString('es-CL')}</div>
          </div>
          <div>
            <div style={{ fontSize: 12, opacity: 0.6 }}>{hayPagos ? 'Pagado real' : 'Calculado'}</div>
            <div style={{ fontSize: 22, fontWeight: 800 }}>${(hayPagos ? totalPagado : totalCalculado).toLocaleString('es-CL')}</div>
          </div>
          <div>
            <div style={{ fontSize: 12, opacity: 0.6 }}>Diferencia</div>
            <div style={{ fontSize: 22, fontWeight: 800, color: diferencia > 0 ? '#f87171' : '#4ade80' }}>
              {hayPagos ? (diferencia > 0 ? '+' : '') + '$' + diferencia.toLocaleString('es-CL') : '—'}
            </div>
          </div>
        </div>
        <button onClick={copiarMarkdown} style={{ marginTop: 4, padding: '8px 16px', background: copied ? '#4ade80' : 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.3)', borderRadius: 8, color: 'white', cursor: 'pointer', fontSize: 13, fontWeight: 600 }}>
          {copied ? '✅ Copiado!' : '📱 Copiar para WhatsApp'}
        </button>
      </div>

      {/* Pagos reales */}
      <div style={{ background: 'white', borderRadius: 16, padding: '20px 24px', boxShadow: '0 1px 4px rgba(0,0,0,0.08)', border: '1px solid #e2e8f0' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <h2 style={{ margin: 0, fontSize: 16, fontWeight: 700, color: '#1e293b' }}>💸 Pagos Reales Registrados</h2>
          <button onClick={() => setModalPago('nuevo')} style={{ padding: '8px 14px', background: '#3b82f6', color: 'white', border: 'none', borderRadius: 8, cursor: 'pointer', fontSize: 13, fontWeight: 600 }}>+ Agregar pago</button>
        </div>
        {pagosNomina.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '24px 0', color: '#94a3b8', fontSize: 14 }}>No hay pagos registrados para este mes</div>
        ) : (
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 14 }}>
            <thead>
              <tr style={{ background: '#f8fafc' }}>
                {['Nombre','Tipo','Monto','Notas',''].map(h => (
                  <th key={h} style={{ padding: '8px 12px', textAlign: 'left', fontWeight: 600, color: '#64748b', fontSize: 12 }}>{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {pagosNomina.map(p => (
                <tr key={p.id} style={{ borderTop: '1px solid #f1f5f9' }}>
                  <td style={{ padding: '10px 12px', fontWeight: 600 }}>{p.nombre}</td>
                  <td style={{ padding: '10px 12px' }}>
                    <span style={{ fontSize: 11, padding: '2px 8px', borderRadius: 20, background: p.es_externo ? '#fef3c7' : '#eff6ff', color: p.es_externo ? '#b45309' : '#1d4ed8', fontWeight: 600 }}>
                      {p.es_externo ? 'Externo' : 'Titular'}
                    </span>
                  </td>
                  <td style={{ padding: '10px 12px', fontWeight: 700, color: '#10b981' }}>${parseFloat(p.monto).toLocaleString('es-CL')}</td>
                  <td style={{ padding: '10px 12px', color: '#94a3b8', fontSize: 13 }}>{p.notas || '—'}</td>
                  <td style={{ padding: '10px 12px' }}>
                    <button onClick={() => deletePago(p.id)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#ef4444', fontSize: 16 }}>×</button>
                  </td>
                </tr>
              ))}
              <tr style={{ borderTop: '2px solid #e2e8f0', background: '#f8fafc' }}>
                <td colSpan={2} style={{ padding: '10px 12px', fontWeight: 700 }}>Total pagado</td>
                <td style={{ padding: '10px 12px', fontWeight: 800, fontSize: 16, color: '#1e293b' }}>${totalPagado.toLocaleString('es-CL')}</td>
                <td colSpan={2} />
              </tr>
            </tbody>
          </table>
        )}
      </div>

      {/* Modal nuevo pago */}
      {modalPago && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 50, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}>
          <div style={{ background: 'white', borderRadius: 16, padding: 24, width: '100%', maxWidth: 400, boxShadow: '0 20px 60px rgba(0,0,0,0.3)' }}>
            <h3 style={{ margin: '0 0 20px', fontSize: 17, fontWeight: 700 }}>Registrar Pago Real</h3>
            <div style={{ marginBottom: 14 }}>
              <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 6, color: '#374151' }}>Nombre</label>
              <input value={formPago.nombre} onChange={e => setFormPago(f => ({...f, nombre: e.target.value}))}
                placeholder="Ej: Camila, Coni (externa)"
                style={{ width: '100%', padding: '10px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }} />
            </div>
            <div style={{ marginBottom: 14 }}>
              <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 6, color: '#374151' }}>Monto</label>
              <input type="number" value={formPago.monto} onChange={e => setFormPago(f => ({...f, monto: e.target.value}))}
                placeholder="Ej: 360000"
                style={{ width: '100%', padding: '10px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 15, boxSizing: 'border-box' }} />
            </div>
            <div style={{ marginBottom: 14 }}>
              <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', fontSize: 14 }}>
                <input type="checkbox" checked={formPago.es_externo} onChange={e => setFormPago(f => ({...f, es_externo: e.target.checked}))} />
                Es trabajador externo
              </label>
            </div>
            <div style={{ marginBottom: 20 }}>
              <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 6, color: '#374151' }}>Notas (opcional)</label>
              <input value={formPago.notas} onChange={e => setFormPago(f => ({...f, notas: e.target.value}))}
                placeholder="Ej: Pagado el 5 de marzo"
                style={{ width: '100%', padding: '10px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }} />
            </div>
            <div style={{ display: 'flex', gap: 10 }}>
              <button onClick={() => { setModalPago(null); setFormPago({ nombre: '', monto: '', es_externo: false, personal_id: '', notas: '' }); }}
                style={{ flex: 1, padding: '10px', border: '1px solid #e2e8f0', borderRadius: 8, background: 'white', cursor: 'pointer', fontWeight: 600 }}>Cancelar</button>
              <button onClick={savePago} disabled={savingPago}
                style={{ flex: 1, padding: '10px', border: 'none', borderRadius: 8, background: '#3b82f6', color: 'white', cursor: 'pointer', fontWeight: 600 }}>
                {savingPago ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </div>
        </div>
      )}

      {[['🧾 Cajeros', cajeros], ['🍳 Plancheros', plancheros]].map(([titulo, grupo]) => (
        <div key={titulo}>
          <h2 style={{ margin: '0 0 12px', fontSize: 16, fontWeight: 700, color: '#1e293b' }}>{titulo}</h2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: 16 }}>
            {grupo.map(p => {
              const { diasTrabajados, ajustesPer, sueldoBase, turnosReemplazados, turnosReemplazando, total } = getLiquidacion(p);
              const c = colores[p.id];
              const abierto = expandidos[p.id] !== false;
              return (
                <div key={p.id} style={{ background: 'white', borderRadius: 16, overflow: 'hidden', boxShadow: '0 1px 4px rgba(0,0,0,0.08)', border: `1px solid ${c?.border}` }}>
                  <div onClick={() => toggleCard(p.id)} style={{ background: c?.bg, padding: '16px 20px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', cursor: 'pointer' }}>
                    <div>
                      <div style={{ color: 'white', fontWeight: 700, fontSize: 18 }}>{p.nombre}</div>
                      <div style={{ color: 'rgba(255,255,255,0.8)', fontSize: 12, textTransform: 'capitalize' }}>{p.rol} · {diasTrabajados} días trabajados</div>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                      <div style={{ background: 'rgba(255,255,255,0.2)', borderRadius: 10, padding: '8px 12px', textAlign: 'right' }}>
                        <div style={{ color: 'rgba(255,255,255,0.8)', fontSize: 11 }}>Total</div>
                        <div style={{ color: 'white', fontWeight: 800, fontSize: 20 }}>${total.toLocaleString('es-CL')}</div>
                      </div>
                      <span style={{ color: 'white', fontSize: 18, opacity: 0.8 }}>{abierto ? '▾' : '▸'}</span>
                    </div>
                  </div>
                  {abierto && (
                    <div style={{ padding: '0 20px 16px' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', borderBottom: '1px solid #f1f5f9' }}>
                        <span style={{ fontSize: 14, color: '#64748b' }}>Sueldo base</span>
                        <span style={{ fontSize: 14, fontWeight: 600 }}>${sueldoBase.toLocaleString('es-CL')}</span>
                      </div>
                      {turnosReemplazando.map(t => (
                        <div key={t.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #f1f5f9' }}>
                          <span style={{ fontSize: 13, color: '#64748b' }}>↔ Reemplazó a {t.reemplazado?.nombre ?? '?'} (día {t.fecha?.split('T')[0].split('-')[2]})</span>
                          <span style={{ fontSize: 13, fontWeight: 600, color: '#10b981' }}>+$20.000</span>
                        </div>
                      ))}
                      {turnosReemplazados.map(t => (
                        <div key={t.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #f1f5f9' }}>
                          <span style={{ fontSize: 13, color: '#64748b' }}>↔ Reemplazado por {t.reemplazante?.nombre ?? '?'} (día {t.fecha?.split('T')[0].split('-')[2]})</span>
                          <span style={{ fontSize: 13, fontWeight: 600, color: '#ef4444' }}>-$20.000</span>
                        </div>
                      ))}
                      {ajustesPer.map(a => (
                        <div key={a.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 0', borderBottom: '1px solid #f1f5f9', gap: 8 }}>
                          <span style={{ fontSize: 13, color: '#64748b', flex: 1 }}>{a.concepto}</span>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ fontSize: 14, fontWeight: 600, color: parseFloat(a.monto) < 0 ? '#ef4444' : '#10b981' }}>
                              {parseFloat(a.monto) < 0 ? '-' : '+'}${Math.abs(parseFloat(a.monto)).toLocaleString('es-CL')}
                            </span>
                            <button onClick={() => onDeleteAjuste(a.id)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#94a3b8', fontSize: 16, padding: 2 }}>×</button>
                          </div>
                        </div>
                      ))}
                      <div style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 0 8px', fontWeight: 700 }}>
                        <span style={{ fontSize: 15 }}>Total a pagar</span>
                        <span style={{ fontSize: 18, color: c?.text }}>${total.toLocaleString('es-CL')}</span>
                      </div>
                      <button onClick={() => onAjuste(p)} style={{
                        width: '100%', padding: '10px', border: `1px dashed ${c?.border}`,
                        borderRadius: 8, background: c?.light, color: c?.text,
                        cursor: 'pointer', fontSize: 13, fontWeight: 600, marginTop: 4,
                      }}>+ Agregar ajuste / descuento</button>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      ))}
    </div>
  );
}

function EquipoView({ personal, turnos, colores, getLiquidacion }) {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: 16 }}>
      {personal.map(p => {
        const c = colores[p.id];
        const { diasNormales, diasReemplazados, reemplazosHechos, diasTrabajados, total } = getLiquidacion(p);
        return (
          <div key={p.id} style={{ background: 'white', borderRadius: 16, overflow: 'hidden', boxShadow: '0 1px 4px rgba(0,0,0,0.08)', border: `1px solid ${c?.border}` }}>
            <div style={{ height: 6, background: c?.bg }} />
            <div style={{ padding: 20 }}>
              <div style={{ width: 52, height: 52, borderRadius: '50%', background: c?.light, border: `2px solid ${c?.border}`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 22, marginBottom: 12 }}>
                {p.nombre[0]}
              </div>
              <div style={{ fontWeight: 700, fontSize: 18, color: '#1e293b' }}>{p.nombre}</div>
              <div style={{ fontSize: 13, color: '#64748b', textTransform: 'capitalize', marginBottom: 16 }}>{p.rol}</div>
              <div style={{ background: '#f8fafc', borderRadius: 8, padding: '10px 12px', marginBottom: 8 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, color: '#64748b', marginBottom: 4 }}>
                  <span>Días normales</span><span style={{ fontWeight: 600, color: '#374151' }}>{diasNormales}</span>
                </div>
                {diasReemplazados > 0 && (
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, color: '#ef4444', marginBottom: 4 }}>
                    <span>Reemplazado</span><span style={{ fontWeight: 600 }}>-{diasReemplazados} días</span>
                  </div>
                )}
                {reemplazosHechos > 0 && (
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, color: '#10b981', marginBottom: 4 }}>
                    <span>Reemplazos extra</span><span style={{ fontWeight: 600 }}>+{reemplazosHechos}</span>
                  </div>
                )}
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, borderTop: '1px solid #e2e8f0', paddingTop: 4, marginTop: 4 }}>
                  <span style={{ fontWeight: 700 }}>Total días</span><span style={{ fontWeight: 800, color: c?.text }}>{diasTrabajados}</span>
                </div>
              </div>
              <div style={{ background: c?.light, borderRadius: 8, padding: '10px 12px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span style={{ fontSize: 12, color: '#94a3b8' }}>Sueldo</span>
                <span style={{ fontWeight: 700, fontSize: 15, color: c?.text }}>${total.toLocaleString('es-CL')}</span>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}
