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
  const [modalAjuste, setModalAjuste] = useState(null);
  const [formAjuste, setFormAjuste] = useState({ monto: '', concepto: '' });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);

  useEffect(() => { loadData(); }, [mes, anio]);

  async function loadData() {
    setLoading(true);
    try {
      const mesStr = String(mes + 1).padStart(2, '0');
      const [pRes, tRes, aRes] = await Promise.all([
        fetch('/api/personal/get_personal.php'),
        fetch(`/api/personal/get_turnos.php?mes=${anio}-${mesStr}`),
        fetch(`/api/personal/get_ajustes.php?mes=${anio}-${mesStr}`),
      ]);
      const [p, t, a] = await Promise.all([pRes.json(), tRes.json(), aRes.json()]);
      if (p.success) setPersonal(p.data);
      if (t.success) setTurnos(t.data);
      if (a.success) setAjustes(a.data);
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
    const diasTrabajados = turnos.filter(t => t.personal_id == p.id).length;
    const ajustesPer = ajustes.filter(a => a.personal_id == p.id);
    const totalAjustes = ajustesPer.reduce((s, a) => s + parseFloat(a.monto), 0);
    const sueldoBase = parseFloat(p.sueldo_base);
    const total = sueldoBase + totalAjustes;
    return { diasTrabajados, ajustesPer, totalAjustes, sueldoBase, total };
  }

  const cajeros = personal.filter(p => p.rol === 'cajero');
  const plancheros = personal.filter(p => p.rol === 'planchero');

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
            {tab === 'liquidacion' && <LiquidacionView personal={personal} cajeros={cajeros} plancheros={plancheros} getLiquidacion={getLiquidacion} colores={COLORES} onAjuste={setModalAjuste} onDeleteAjuste={deleteAjuste} mes={mes} anio={anio} />}
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

  return (
    <div>
      {/* Leyenda */}
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, marginBottom: 16 }}>
        {personal.map(p => (
          <div key={p.id} style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', borderRadius: 20, background: colores[p.id]?.light, border: `1px solid ${colores[p.id]?.border}` }}>
            <div style={{ width: 10, height: 10, borderRadius: '50%', background: colores[p.id]?.bg }} />
            <span style={{ fontSize: 13, fontWeight: 600, color: colores[p.id]?.text }}>{p.nombre}</span>
            <span style={{ fontSize: 11, color: '#94a3b8', textTransform: 'capitalize' }}>({p.rol})</span>
          </div>
        ))}
      </div>

      {/* Grid días semana */}
      <div style={{ background: 'white', borderRadius: 16, overflow: 'hidden', boxShadow: '0 1px 4px rgba(0,0,0,0.08)', border: '1px solid #e2e8f0' }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', background: '#f1f5f9' }}>
          {['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'].map(d => (
            <div key={d} style={{ padding: '10px 4px', textAlign: 'center', fontSize: 12, fontWeight: 700, color: '#64748b' }}>{d}</div>
          ))}
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 1, background: '#e2e8f0' }}>
          {celdas.map((dia, i) => {
            const trabajando = dia ? (turnosPorFecha[dia] || []) : [];
            return (
              <div key={i} style={{
                background: dia ? 'white' : '#f8fafc',
                minHeight: 90, padding: '8px 6px',
              }}>
                {dia && (
                  <>
                    <div style={{ fontSize: 13, fontWeight: 700, color: '#374151', marginBottom: 4 }}>{dia}</div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                      {trabajando.map(t => {
                        const p = personal.find(p => p.id == t.personal_id);
                        if (!p) return null;
                        const c = colores[p.id];
                        return (
                          <div key={t.id} style={{
                            background: c?.light, border: `1px solid ${c?.border}`,
                            borderRadius: 4, padding: '2px 5px', fontSize: 11, fontWeight: 600,
                            color: c?.text, display: 'flex', alignItems: 'center', gap: 3,
                          }}>
                            <div style={{ width: 6, height: 6, borderRadius: '50%', background: c?.bg, flexShrink: 0 }} />
                            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{p.nombre}</span>
                            {t.tipo === 'reemplazo' && (
                              <span title={t.notas} style={{ fontSize: 9, background: 'rgba(0,0,0,0.15)', borderRadius: 3, padding: '0 3px' }}>
                                {t.reemplazante_nombre ? `↔${t.reemplazante_nombre}` : '↔'}
                              </span>
                            )}
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
  );
}

function LiquidacionView({ personal, cajeros, plancheros, getLiquidacion, colores, onAjuste, onDeleteAjuste, mes, anio }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
      {[['🧾 Cajeros', cajeros], ['🍳 Plancheros', plancheros]].map(([titulo, grupo]) => (
        <div key={titulo}>
          <h2 style={{ margin: '0 0 12px', fontSize: 16, fontWeight: 700, color: '#1e293b' }}>{titulo}</h2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: 16 }}>
            {grupo.map(p => {
              const { diasTrabajados, ajustesPer, totalAjustes, sueldoBase, total } = getLiquidacion(p);
              const c = colores[p.id];
              return (
                <div key={p.id} style={{ background: 'white', borderRadius: 16, overflow: 'hidden', boxShadow: '0 1px 4px rgba(0,0,0,0.08)', border: `1px solid ${c?.border}` }}>
                  {/* Header tarjeta */}
                  <div style={{ background: c?.bg, padding: '16px 20px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div>
                      <div style={{ color: 'white', fontWeight: 700, fontSize: 18 }}>{p.nombre}</div>
                      <div style={{ color: 'rgba(255,255,255,0.8)', fontSize: 12, textTransform: 'capitalize' }}>{p.rol} · {diasTrabajados} días trabajados</div>
                    </div>
                    <div style={{ background: 'rgba(255,255,255,0.2)', borderRadius: 10, padding: '8px 12px', textAlign: 'right' }}>
                      <div style={{ color: 'rgba(255,255,255,0.8)', fontSize: 11 }}>Total</div>
                      <div style={{ color: 'white', fontWeight: 800, fontSize: 20 }}>${total.toLocaleString('es-CL')}</div>
                    </div>
                  </div>

                  {/* Desglose */}
                  <div style={{ padding: '16px 20px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #f1f5f9' }}>
                      <span style={{ fontSize: 14, color: '#64748b' }}>Sueldo base</span>
                      <span style={{ fontSize: 14, fontWeight: 600 }}>${sueldoBase.toLocaleString('es-CL')}</span>
                    </div>

                    {ajustesPer.map(a => (
                      <div key={a.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 0', borderBottom: '1px solid #f1f5f9', gap: 8 }}>
                        <span style={{ fontSize: 13, color: '#64748b', flex: 1 }}>{a.concepto}</span>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                          <span style={{ fontSize: 14, fontWeight: 600, color: parseFloat(a.monto) < 0 ? '#ef4444' : '#10b981' }}>
                            {parseFloat(a.monto) > 0 ? '+' : ''}${Math.abs(parseFloat(a.monto)).toLocaleString('es-CL')}
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
        const { diasTrabajados, total } = getLiquidacion(p);
        return (
          <div key={p.id} style={{ background: 'white', borderRadius: 16, overflow: 'hidden', boxShadow: '0 1px 4px rgba(0,0,0,0.08)', border: `1px solid ${c?.border}` }}>
            <div style={{ height: 6, background: c?.bg }} />
            <div style={{ padding: 20 }}>
              <div style={{ width: 52, height: 52, borderRadius: '50%', background: c?.light, border: `2px solid ${c?.border}`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 22, marginBottom: 12 }}>
                {p.nombre[0]}
              </div>
              <div style={{ fontWeight: 700, fontSize: 18, color: '#1e293b' }}>{p.nombre}</div>
              <div style={{ fontSize: 13, color: '#64748b', textTransform: 'capitalize', marginBottom: 16 }}>{p.rol}</div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                <div style={{ background: '#f8fafc', borderRadius: 8, padding: '10px 12px' }}>
                  <div style={{ fontSize: 11, color: '#94a3b8', marginBottom: 2 }}>Días</div>
                  <div style={{ fontWeight: 700, fontSize: 20, color: c?.text }}>{diasTrabajados}</div>
                </div>
                <div style={{ background: c?.light, borderRadius: 8, padding: '10px 12px' }}>
                  <div style={{ fontSize: 11, color: '#94a3b8', marginBottom: 2 }}>Sueldo</div>
                  <div style={{ fontWeight: 700, fontSize: 14, color: c?.text }}>${total.toLocaleString('es-CL')}</div>
                </div>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}
