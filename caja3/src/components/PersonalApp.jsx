import { useState, useEffect } from 'react';

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

const COLORES = {
  1: { bg: '#3b82f6', light: '#eff6ff', border: '#bfdbfe', text: '#1d4ed8' }, // Camila - azul
  2: { bg: '#8b5cf6', light: '#f5f3ff', border: '#ddd6fe', text: '#6d28d9' }, // Neit - violeta
  3: { bg: '#f59e0b', light: '#fffbeb', border: '#fde68a', text: '#b45309' }, // Andrés - amarillo
  4: { bg: '#10b981', light: '#ecfdf5', border: '#a7f3d0', text: '#047857' }, // Gabriel - verde
  5: { bg: '#ef4444', light: '#fef2f2', border: '#fecaca', text: '#b91c1c' }, // Ricardo - rojo
};

export default function PersonalApp() {
  const [tab, setTab] = useState('calendario');
  const [mes, setMes] = useState(() => new Date().getMonth());
  const [anio, setAnio] = useState(() => new Date().getFullYear());
  const [personal, setPersonal] = useState([]);
  const [turnos, setTurnos] = useState([]);
  const [ajustes, setAjustes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [pagosNomina, setPagosNomina] = useState({ ruta11: [], seguridad: [] });
  const [presupuestoNomina, setPresupuestoNomina] = useState({ ruta11: 1200000, seguridad: 1200000 });
  const [modalAjuste, setModalAjuste] = useState(null);
  const [formAjuste, setFormAjuste] = useState({ monto: '', concepto: '' });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);
  const [modalTurno, setModalTurno] = useState(null); // {dia, fecha, isSeguridad, titularId}
  const [formTurno, setFormTurno] = useState({ personal_id: '', tipo: 'normal', reemplazado_por: '', monto_reemplazo: 20000, pago_por: 'empresa', fecha_fin: '' });
  const [modalPersonal, setModalPersonal] = useState(null); // null | 'new' | persona
  const [formPersonal, setFormPersonal] = useState({ nombre: '', rol: ['cajero'], sueldo_base_cajero: '', sueldo_base_planchero: '', sueldo_base_admin: '', sueldo_base_seguridad: '', activo: 1 });

  useEffect(() => { loadData(); }, [mes, anio]);

  async function loadData() {
    setLoading(true);
    try {
      const mesStr = String(mes + 1).padStart(2, '0');
      const [pRes, tRes, aRes, pnRes, pnSegRes] = await Promise.all([
        fetch('/api/personal/get_personal.php'),
        fetch(`/api/personal/get_turnos.php?mes=${anio}-${mesStr}`),
        fetch(`/api/personal/get_ajustes.php?mes=${anio}-${mesStr}`),
        fetch(`/api/personal/get_pagos_nomina.php?mes=${anio}-${mesStr}&centro_costo=ruta11`),
        fetch(`/api/personal/get_pagos_nomina.php?mes=${anio}-${mesStr}&centro_costo=seguridad`),
      ]);
      const [p, t, a, pn, pnSeg] = await Promise.all([pRes.json(), tRes.json(), aRes.json(), pnRes.json(), pnSegRes.json()]);
      if (p.success) setPersonal(p.data);
      if (t.success) setTurnos(t.data);
      if (a.success) setAjustes(a.data);
      if (pn.success && pnSeg.success) {
        setPagosNomina({ ruta11: pn.data, seguridad: pnSeg.data });
        setPresupuestoNomina({ ruta11: pn.presupuesto ?? 1200000, seguridad: pnSeg.presupuesto ?? 1200000 });
      }
    } catch (e) {
      showToast('Error cargando datos', 'error');
    }
    setLoading(false);
  }

  function showToast(msg, type = 'success') {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3000);
  }

  async function saveTurno() {
    setSaving(true);
    try {
      const fechaInicio = new Date(modalTurno.fecha);
      const fechaFin = formTurno.fecha_fin ? new Date(formTurno.fecha_fin) : fechaInicio;

      const requests = [];
      let currentDate = new Date(fechaInicio);

      while (currentDate <= fechaFin) {
        const fechaStr = currentDate.toISOString().split('T')[0];
        requests.push(
          fetch('/api/personal/personal_api.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_turno', ...formTurno, personal_id: parseInt(formTurno.personal_id), fecha: fechaStr }),
          }).then(res => res.json())
        );
        currentDate.setDate(currentDate.getDate() + 1);
      }

      const results = await Promise.all(requests);
      const allSuccess = results.every(r => r.success);

      if (allSuccess) { showToast('Turnos guardados'); setModalTurno(null); loadData(); }
      else showToast('Error al guardar algunos turnos', 'error');
    } catch { showToast('Error de conexión', 'error'); }
    setSaving(false);
  }

  async function deleteTurno(id) {
    if (!confirm('¿Eliminar turno?')) return;
    try {
      const res = await fetch('/api/personal/personal_api.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_turno', id }),
      });
      const data = await res.json();
      if (data.success) { showToast('Turno eliminado'); loadData(); }
    } catch { showToast('Error', 'error'); }
  }

  async function savePersonal() {
    if (!formPersonal.nombre) return;
    setSaving(true);
    const isEdit = modalPersonal && modalPersonal !== 'new';
    try {
      const res = await fetch('/api/personal/personal_api.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: isEdit ? 'update_personal' : 'save_personal', ...formPersonal, id: isEdit ? modalPersonal.id : undefined }),
      });
      const data = await res.json();
      if (data.success) { showToast(isEdit ? 'Actualizado' : 'Persona agregada'); setModalPersonal(null); loadData(); }
      else showToast(data.error || 'Error', 'error');
    } catch { showToast('Error de conexión', 'error'); }
    setSaving(false);
  }

  async function savePresupuesto(monto, centro_costo = 'ruta11') {
    const mesStr = String(mes + 1).padStart(2, '0');
    try {
      await fetch('/api/personal/personal_api.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_presupuesto', mes: `${anio}-${mesStr}-01`, monto, centro_costo }),
      });
      loadData();
    } catch { showToast('Error', 'error'); }
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
  const primerDiaLunes = (primerDia + 6) % 7;

  // Mapa de turnos divididos por calendario
  const turnosNoSeguridad = {};
  const turnosSeguridad = {};
  turnos.forEach(t => {
    const d = t.fecha.split('T')[0].split('-')[2];
    const dia = parseInt(d);
    const p = personal.find(x => x.id == t.personal_id);
    const esSeguridad = p?.rol?.includes('seguridad') || t.tipo === 'seguridad';

    if (esSeguridad) {
      if (!turnosSeguridad[dia]) turnosSeguridad[dia] = [];
      turnosSeguridad[dia].push(t);
    } else {
      if (!turnosNoSeguridad[dia]) turnosNoSeguridad[dia] = [];
      turnosNoSeguridad[dia].push(t);
    }
  });

  // Calcular liquidación por persona
  function getLiquidacion(p, modoContexto = 'all') {
    const isShiftSeguridad = (t) => {
      if (t.tipo === 'seguridad') return true;
      if (t.tipo === 'reemplazo') {
        // Usar personal_id = el dueño del turno original (no el que cubrió)
        const titular = personal.find(x => x.id == t.personal_id);
        return titular?.rol?.includes('seguridad') || false;
      }
      const tPersona = personal.find(x => x.id == t.personal_id);
      return tPersona?.rol?.includes('seguridad') && (!tPersona?.rol?.includes('cajero') && !tPersona?.rol?.includes('planchero') && !tPersona?.rol?.includes('administrador'));
    };

    const turnosFiltrados = turnos.filter(t => {
      if (modoContexto === 'seguridad') return isShiftSeguridad(t);
      if (modoContexto === 'ruta11') return !isShiftSeguridad(t);
      return true;
    });

    const tPersonal = turnosFiltrados.filter(t => t.personal_id == p.id);
    const diasNormales = tPersonal.filter(t => t.tipo === 'normal' || t.tipo === 'seguridad').length;
    // Agrupado: días que fue reemplazado, por quien lo reemplazó
    const rawReemplazados = tPersonal.filter(t => t.tipo === 'reemplazo');
    const diasReemplazados = rawReemplazados.length;
    const gruposReemplazados = {};
    rawReemplazados.forEach(t => {
      const key = t.reemplazado_por ?? 'ext';
      if (!gruposReemplazados[key]) gruposReemplazados[key] = { persona: personal.find(x => x.id == t.reemplazado_por) || { nombre: t.reemplazante_nombre || '?' }, dias: [], monto: 0, pago_por: t.pago_por || 'empresa' };
      gruposReemplazados[key].dias.push(parseInt(t.fecha.split('T')[0].split('-')[2]));
      gruposReemplazados[key].monto += parseFloat(t.monto_reemplazo || 20000);
    });
    // Agrupado: días que reemplazó a otro, por a quién reemplazó
    const rawReemplazando = turnosFiltrados.filter(t => t.reemplazado_por == p.id);
    const reemplazosHechos = rawReemplazando.length;
    const gruposReemplazando = {};
    rawReemplazando.forEach(t => {
      const key = t.personal_id;
      if (!gruposReemplazando[key]) gruposReemplazando[key] = { persona: personal.find(x => x.id == t.personal_id), dias: [], monto: 0, pago_por: t.pago_por || 'empresa' };
      gruposReemplazando[key].dias.push(parseInt(t.fecha.split('T')[0].split('-')[2]));
      gruposReemplazando[key].monto += parseFloat(t.monto_reemplazo);
    });
    const diasTrabajados = diasNormales + reemplazosHechos;

    // Ajustes per context object... just leave them global or split them?
    const ajustesPer = ajustes.filter(a => a.personal_id == p.id);
    const totalAjustes = ajustesPer.reduce((s, a) => s + parseFloat(a.monto), 0);

    const primerRol = typeof p.rol === 'string' ? p.rol.split(',')[0].trim() : (Array.isArray(p.rol) ? p.rol[0] : '');
    const isMainSeguridad = primerRol === 'seguridad';

    // Asignar el sueldo de acuerdo al contexto de la vista (La Ruta 11 vs Seguridad)
    let sueldoBase = 0;
    if (modoContexto === 'seguridad') {
      sueldoBase = parseFloat(p.sueldo_base_seguridad) || 0;
    } else {
      // Para Ruta 11: buscar el primer sueldo base de rol que tenga valor (admin > cajero > planchero)
      const roles = typeof p.rol === 'string' ? p.rol.split(',').map(r => r.trim()) : (Array.isArray(p.rol) ? p.rol : []);
      if (roles.includes('administrador') && parseFloat(p.sueldo_base_admin) > 0) {
        sueldoBase = parseFloat(p.sueldo_base_admin);
      } else if (roles.includes('cajero') && parseFloat(p.sueldo_base_cajero) > 0) {
        sueldoBase = parseFloat(p.sueldo_base_cajero);
      } else if (roles.includes('planchero') && parseFloat(p.sueldo_base_planchero) > 0) {
        sueldoBase = parseFloat(p.sueldo_base_planchero);
      }
      // Guardias puros (solo seguridad) en Ruta 11 → 0
    }

    const totalReemplazando = Object.values(gruposReemplazando).filter(g => g.pago_por === 'empresa').reduce((s, g) => s + g.monto, 0);
    const totalReemplazados = Object.values(gruposReemplazados).filter(g => g.pago_por === 'empresa').reduce((s, g) => s + g.monto, 0);
    const total = sueldoBase + totalReemplazando - totalReemplazados + totalAjustes;
    return { diasNormales, diasReemplazados, reemplazosHechos, diasTrabajados, ajustesPer, totalAjustes, sueldoBase, gruposReemplazados, gruposReemplazando, total };
  }

  const administradores = personal.filter(p => p.rol?.includes('administrador') && p.activo == 1);
  const cajeros = personal.filter(p => p.rol?.includes('cajero') && !p.rol?.includes('administrador') && p.activo == 1);
  const plancheros = personal.filter(p => p.rol?.includes('planchero') && p.activo == 1);
  const guardias = personal.filter(p => p.rol?.includes('seguridad') && p.activo == 1);

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
        <div style={{ maxWidth: 1100, margin: '0 auto', display: 'flex', gap: 0, overflowX: 'auto', whiteSpace: 'nowrap' }}>
          {[
            ['calendario', '📅 Calendario'],
            ['liquidacion', '🇨🇱 La Ruta 11'],
            ['seguridad', '📷 Cam Seguridad'],
            ['equipo', '👤 Equipo']
          ].map(([key, label]) => (
            <button key={key} onClick={() => setTab(key)} style={{
              padding: '14px 20px', border: 'none', background: 'none', cursor: 'pointer',
              fontSize: 14, fontWeight: 600,
              color: tab === key ? '#3b82f6' : '#64748b',
              borderBottom: tab === key ? '2px solid #3b82f6' : '2px solid transparent',
              flexShrink: 0
            }}>{label}</button>
          ))}
        </div>
      </div>

      <div style={{ maxWidth: 1100, margin: '0 auto', padding: '20px 16px' }}>
        {/* Navegación de mes */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 20 }}>
          <button onClick={() => { if (mes === 0) { setMes(11); setAnio(a => a - 1); } else setMes(m => m - 1); }}
            style={{ padding: '8px 14px', border: '1px solid #e2e8f0', borderRadius: 8, background: 'white', cursor: 'pointer', fontSize: 16 }}>‹</button>
          <span style={{ fontWeight: 700, fontSize: 18, minWidth: 160, textAlign: 'center' }}>{MESES[mes]} {anio}</span>
          <button onClick={() => { if (mes === 11) { setMes(0); setAnio(a => a + 1); } else setMes(m => m + 1); }}
            style={{ padding: '8px 14px', border: '1px solid #e2e8f0', borderRadius: 8, background: 'white', cursor: 'pointer', fontSize: 16 }}>›</button>
        </div>

        {loading ? (
          <div style={{ textAlign: 'center', padding: 60, color: '#94a3b8' }}>
            <div style={{ fontSize: 32, marginBottom: 8 }}>⏳</div>Cargando...
          </div>
        ) : (
          <>
            {tab === 'calendario' && <CalendarioView diasEnMes={diasEnMes} primerDia={primerDia} turnosPorFecha={turnosNoSeguridad} personal={personal} colores={COLORES} mes={mes} anio={anio} onAddTurno={(dia, fecha) => { setModalTurno({ dia, fecha }); setFormTurno({ personal_id: '', tipo: 'normal', reemplazado_por: '', monto_reemplazo: 20000, pago_por: 'empresa', fecha_fin: fecha }); }} onDeleteTurno={deleteTurno} />}
            {tab === 'liquidacion' && (
              <>
                <LiquidacionView personal={personal} cajeros={cajeros} plancheros={plancheros} administradores={administradores} getLiquidacion={(p) => getLiquidacion(p, 'ruta11')} colores={COLORES} onAjuste={setModalAjuste} onDeleteAjuste={deleteAjuste} mes={mes} anio={anio} pagosNomina={pagosNomina.ruta11} onReloadPagos={loadData} showToast={showToast} presupuesto={presupuestoNomina.ruta11} onSavePresupuesto={(monto) => savePresupuesto(monto, 'ruta11')} centroCosto="ruta11" />
                <div style={{ marginTop: 40, borderTop: '2px solid #e2e8f0', paddingTop: 24 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                    <h2 style={{ margin: 0, fontSize: 18, fontWeight: 700, color: '#1e293b' }}>📅 Calendario de Turnos (La Ruta 11)</h2>
                  </div>
                  <CalendarioView diasEnMes={diasEnMes} primerDia={primerDia} turnosPorFecha={turnosNoSeguridad} personal={personal} colores={COLORES} mes={mes} anio={anio} onAddTurno={(dia, fecha) => { setModalTurno({ dia, fecha }); setFormTurno({ personal_id: '', tipo: 'normal', reemplazado_por: '', monto_reemplazo: 20000, pago_por: 'empresa', fecha_fin: fecha }); }} onDeleteTurno={deleteTurno} />
                </div>
              </>
            )}
            {tab === 'seguridad' && (
              <>
                <LiquidacionSeguridad guardias={guardias} getLiquidacion={(p) => getLiquidacion(p, 'seguridad')} colores={COLORES} onAjuste={setModalAjuste} onDeleteAjuste={deleteAjuste} mes={mes} anio={anio} pagosNomina={pagosNomina.seguridad} onReloadPagos={loadData} showToast={showToast} presupuesto={presupuestoNomina.seguridad} onSavePresupuesto={(monto) => savePresupuesto(monto, 'seguridad')} centroCosto="seguridad" />
                <div style={{ marginTop: 40, borderTop: '2px solid #e0e7ff', paddingTop: 24 }}>
                  <CalendarioSeguridad diasEnMes={diasEnMes} primerDiaLunes={primerDiaLunes} turnosSeguridad={turnosSeguridad} personal={personal} mes={mes} anio={anio} onAddTurno={(params) => { setModalTurno(params); setFormTurno({ personal_id: '', tipo: 'reemplazo', reemplazado_por: params.titularId || '', monto_reemplazo: 17966.666, pago_por: 'empresa', fecha_fin: params.fecha }); }} onDeleteTurno={deleteTurno} />
                </div>
              </>
            )}
            {tab === 'equipo' && <EquipoView personal={personal} onAddPersonal={() => { setModalPersonal('new'); setFormPersonal({ nombre: '', rol: ['cajero'], sueldo_base_cajero: '', sueldo_base_planchero: '', sueldo_base_admin: '', sueldo_base_seguridad: '', activo: 1 }); }} onEditPersonal={(p) => { setModalPersonal(p); setFormPersonal({ nombre: p.nombre, rol: typeof p.rol === 'string' ? p.rol.split(',') : p.rol, sueldo_base_cajero: p.sueldo_base_cajero || '', sueldo_base_planchero: p.sueldo_base_planchero || '', sueldo_base_admin: p.sueldo_base_admin || '', sueldo_base_seguridad: p.sueldo_base_seguridad || '', activo: p.activo }); }} />}
          </>
        )}
      </div>

      {/* Modal turno */}
      {modalTurno && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 50, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}>
          <div style={{ background: 'white', borderRadius: 16, padding: 24, width: '100%', maxWidth: 400, boxShadow: '0 20px 60px rgba(0,0,0,0.3)' }}>
            <h3 style={{ margin: '0 0 4px', fontSize: 17, fontWeight: 700 }}>{modalTurno.isSeguridad ? 'Reemplazo Seguridad' : 'Agregar Turno'}</h3>
            <p style={{ margin: '0 0 16px', color: '#64748b', fontSize: 13 }}>{modalTurno.isSeguridad ? 'Seleccione rango de fechas' : `Día ${modalTurno.dia} · ${modalTurno.fecha}`}</p>

            <div style={{ display: 'flex', gap: 10, marginBottom: 12 }}>
              <div style={{ flex: 1 }}>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Desde</label>
                <input type='date' value={modalTurno.fecha} readOnly style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box', background: '#f8fafc', color: '#64748b' }} />
              </div>
              <div style={{ flex: 1 }}>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Hasta</label>
                <input type='date' value={formTurno.fecha_fin} min={modalTurno.fecha} onChange={e => setFormTurno(f => ({ ...f, fecha_fin: e.target.value }))} style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }} />
              </div>
            </div>

            <div style={{ marginBottom: 12 }}>
              <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Persona (Reemplazante)</label>
              <select value={formTurno.personal_id} onChange={e => setFormTurno(f => ({ ...f, personal_id: e.target.value }))}
                style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }}>
                <option value=''>Seleccionar...</option>
                {personal.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
              </select>
            </div>

            {!modalTurno.isSeguridad && (
              <div style={{ marginBottom: 12 }}>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Tipo</label>
                <select value={formTurno.tipo} onChange={e => setFormTurno(f => ({ ...f, tipo: e.target.value }))}
                  style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }}>
                  <option value='normal'>Normal</option>
                  <option value='reemplazo'>Reemplazo</option>
                </select>
              </div>
            )}

            {formTurno.tipo === 'reemplazo' && (<>
              <div style={{ marginBottom: 12 }}>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Reemplazado por (Faltante)</label>
                <select value={formTurno.reemplazado_por} onChange={e => {
                  const newId = e.target.value;
                  const personaR = personal.find(x => x.id == newId);
                  const titular = personal.find(x => x.id == formTurno.personal_id);
                  let monto = 20000;

                  if (titular?.rol?.includes('seguridad') || modalTurno.isSeguridad) {
                    if (personaR && personaR.rol?.includes('seguridad')) {
                      monto = 17966.666;
                    } else {
                      monto = 30000;
                    }
                  }

                  setFormTurno(f => ({ ...f, reemplazado_por: newId, monto_reemplazo: monto }));
                }}
                  style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }}>
                  <option value=''>Seleccionar...</option>
                  {personal.filter(p => p.id != formTurno.personal_id).map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                </select>
              </div>
              <div style={{ marginBottom: 12 }}>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Monto reemplazo</label>
                {modalTurno.isSeguridad ? (
                  <select value={formTurno.monto_reemplazo} onChange={e => setFormTurno(f => ({ ...f, monto_reemplazo: parseFloat(e.target.value) }))}
                    style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }}>
                    <option value={17966.666}>Turno 4x4 ($17.966)</option>
                    <option value={30000}>El Día ($30.000)</option>
                  </select>
                ) : (
                  <input type='number' value={formTurno.monto_reemplazo} onChange={e => setFormTurno(f => ({ ...f, monto_reemplazo: e.target.value }))}
                    style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }} />
                )}
              </div>
              <div style={{ marginBottom: 16 }}>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Pago por</label>
                <select value={formTurno.pago_por} onChange={e => setFormTurno(f => ({ ...f, pago_por: e.target.value }))}
                  style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }}>
                  <option value='empresa'>Empresa</option>
                  <option value='titular'>Titular (entre ellos)</option>
                </select>
              </div>
            </>)}
            <div style={{ display: 'flex', gap: 10 }}>
              <button onClick={() => setModalTurno(null)} style={{ flex: 1, padding: '10px', border: '1px solid #e2e8f0', borderRadius: 8, background: 'white', cursor: 'pointer', fontWeight: 600 }}>Cancelar</button>
              <button onClick={saveTurno} disabled={saving || !formTurno.personal_id} style={{ flex: 1, padding: '10px', border: 'none', borderRadius: 8, background: '#3b82f6', color: 'white', cursor: 'pointer', fontWeight: 600 }}>
                {saving ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal personal */}
      {modalPersonal && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 50, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}>
          <div style={{ background: 'white', borderRadius: 16, padding: 24, width: '100%', maxWidth: 400, boxShadow: '0 20px 60px rgba(0,0,0,0.3)' }}>
            <h3 style={{ margin: '0 0 16px', fontSize: 17, fontWeight: 700 }}>{modalPersonal === 'new' ? 'Agregar Persona' : 'Editar Persona'}</h3>
            <div style={{ marginBottom: 12 }}>
              <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Nombre</label>
              <input type='text' value={formPersonal.nombre} onChange={e => setFormPersonal(f => ({ ...f, nombre: e.target.value }))}
                style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }} />
            </div>
            <div style={{ marginBottom: 12 }}>
              <label style={{ display: 'block', marginBottom: 4, fontWeight: 600, fontSize: 13, color: '#475569' }}>Roles (Múltiples)</label>
              <div style={{ display: 'flex', gap: '15px', flexWrap: 'wrap' }}>
                {['cajero', 'planchero', 'administrador', 'seguridad'].map(r => (
                  <label key={r} style={{ display: 'flex', alignItems: 'center', gap: '5px', fontSize: 14 }}>
                    <input
                      type="checkbox"
                      checked={Array.isArray(formPersonal.rol) ? formPersonal.rol.includes(r) : formPersonal.rol === r}
                      onChange={(e) => {
                        const currentRoles = Array.isArray(formPersonal.rol) ? formPersonal.rol : [formPersonal.rol];
                        if (e.target.checked) {
                          setFormPersonal(f => ({ ...f, rol: [...currentRoles, r] }));
                        } else {
                          setFormPersonal(f => ({ ...f, rol: currentRoles.filter(role => role !== r) }));
                        }
                      }}
                    />
                    <span style={{ textTransform: 'capitalize' }}>{r}</span>
                  </label>
                ))}
              </div>
            </div>
            {/* Salarios por rol — solo muestra los campos relevantes */}
            {(Array.isArray(formPersonal.rol) ? formPersonal.rol : [formPersonal.rol]).includes('cajero') && (
              <div style={{ marginBottom: 12 }}>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Sueldo base Cajero/a</label>
                <input type='number' value={formPersonal.sueldo_base_cajero} onChange={e => setFormPersonal(f => ({ ...f, sueldo_base_cajero: e.target.value }))}
                  placeholder='Ej: 300000' style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }} />
              </div>
            )}
            {(Array.isArray(formPersonal.rol) ? formPersonal.rol : [formPersonal.rol]).includes('planchero') && (
              <div style={{ marginBottom: 12 }}>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Sueldo base Planchero/a</label>
                <input type='number' value={formPersonal.sueldo_base_planchero} onChange={e => setFormPersonal(f => ({ ...f, sueldo_base_planchero: e.target.value }))}
                  placeholder='Ej: 300000' style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }} />
              </div>
            )}
            {(Array.isArray(formPersonal.rol) ? formPersonal.rol : [formPersonal.rol]).includes('administrador') && (
              <div style={{ marginBottom: 12 }}>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Sueldo base Administrador/a</label>
                <input type='number' value={formPersonal.sueldo_base_admin} onChange={e => setFormPersonal(f => ({ ...f, sueldo_base_admin: e.target.value }))}
                  placeholder='Ej: 300000' style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }} />
              </div>
            )}
            {(Array.isArray(formPersonal.rol) ? formPersonal.rol : [formPersonal.rol]).includes('seguridad') && (
              <div style={{ marginBottom: 12 }}>
                <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 }}>Sueldo base Seguridad</label>
                <input type='number' value={formPersonal.sueldo_base_seguridad} onChange={e => setFormPersonal(f => ({ ...f, sueldo_base_seguridad: e.target.value }))}
                  placeholder='Ej: 539000' style={{ width: '100%', padding: '9px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 14, boxSizing: 'border-box' }} />
              </div>
            )}
            <div style={{ marginBottom: 20 }}>
              <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 14, cursor: 'pointer' }}>
                <input type='checkbox' checked={formPersonal.activo == 1} onChange={e => setFormPersonal(f => ({ ...f, activo: e.target.checked ? 1 : 0 }))} />
                Activo (aparece en calendario y liquidación)
              </label>
            </div>
            <div style={{ display: 'flex', gap: 10 }}>
              <button onClick={() => setModalPersonal(null)} style={{ flex: 1, padding: '10px', border: '1px solid #e2e8f0', borderRadius: 8, background: 'white', cursor: 'pointer', fontWeight: 600 }}>Cancelar</button>
              <button onClick={savePersonal} disabled={saving || !formPersonal.nombre} style={{ flex: 1, padding: '10px', border: 'none', borderRadius: 8, background: '#3b82f6', color: 'white', cursor: 'pointer', fontWeight: 600 }}>
                {saving ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal ajuste */}
      {modalAjuste && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', zIndex: 50, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}>
          <div style={{ background: 'white', borderRadius: 16, padding: 24, width: '100%', maxWidth: 400, boxShadow: '0 20px 60px rgba(0,0,0,0.3)' }}>
            <h3 style={{ margin: '0 0 4px', fontSize: 17, fontWeight: 700 }}>Agregar Ajuste</h3>
            <p style={{ margin: '0 0 20px', color: '#64748b', fontSize: 13 }}>{modalAjuste.nombre} · {MESES[mes]} {anio}</p>
            <div style={{ marginBottom: 14 }}>
              <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 6, color: '#374151' }}>Monto (negativo = descuento)</label>
              <input type="number" value={formAjuste.monto} onChange={e => setFormAjuste(f => ({ ...f, monto: e.target.value }))}
                placeholder="Ej: -40000 o 20000"
                style={{ width: '100%', padding: '10px 12px', border: '1px solid #d1d5db', borderRadius: 8, fontSize: 15, boxSizing: 'border-box' }} />
            </div>
            <div style={{ marginBottom: 20 }}>
              <label style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 6, color: '#374151' }}>Concepto</label>
              <input type="text" value={formAjuste.concepto} onChange={e => setFormAjuste(f => ({ ...f, concepto: e.target.value }))}
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

function CalendarioView({ diasEnMes, primerDia, turnosPorFecha, personal, colores, mes, anio, onAddTurno, onDeleteTurno }) {
  const DIAS_LABEL = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
  const celdas = [];
  for (let i = 0; i < primerDia; i++) celdas.push(null);
  for (let d = 1; d <= diasEnMes; d++) celdas.push(d);

  return (
    <div>
      {/* Leyenda */}
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 16 }}>
        {personal.filter(p => p.activo == 1).map(p => (
          <div key={p.id} style={{ display: 'flex', alignItems: 'center', gap: 5, padding: '4px 10px', borderRadius: 20, background: colores[p.id]?.light, border: `1px solid ${colores[p.id]?.border}` }}>
            <div style={{ width: 8, height: 8, borderRadius: '50%', background: colores[p.id]?.bg }} />
            <span style={{ fontSize: 12, fontWeight: 600, color: colores[p.id]?.text }}>{p.nombre}</span>
          </div>
        ))}
      </div>

      {/* Grid calendario — funciona en móvil y desktop */}
      <div style={{ background: 'white', borderRadius: 12, overflow: 'hidden', border: '1px solid #e2e8f0' }}>
        {/* Header días */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', background: '#f1f5f9', borderBottom: '1px solid #e2e8f0' }}>
          {DIAS_LABEL.map(d => (
            <div key={d} style={{ padding: '10px 4px', textAlign: 'center', fontSize: 12, fontWeight: 700, color: '#64748b' }}>{d}</div>
          ))}
        </div>
        {/* Celdas */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 1, background: '#e2e8f0' }}>
          {celdas.map((dia, i) => {
            const trabajando = dia ? (turnosPorFecha[dia] || []) : [];
            const esFinSemana = i % 7 === 0 || i % 7 === 6;
            const fecha = dia ? `${anio}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}` : '';
            return (
              <div key={i} style={{
                background: dia ? (esFinSemana ? '#fafafa' : 'white') : '#f8fafc',
                minHeight: 90, padding: '6px 5px',
                display: 'flex', flexDirection: 'column',
              }}>
                {dia && (
                  <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
                      <span style={{ fontSize: 13, fontWeight: 700, color: esFinSemana ? '#94a3b8' : '#374151' }}>{dia}</span>
                      <button onClick={() => onAddTurno(dia, fecha)} style={{
                        fontSize: 14, lineHeight: 1, padding: '1px 5px', borderRadius: 4,
                        border: '1px dashed #cbd5e1', background: 'transparent', cursor: 'pointer', color: '#94a3b8'
                      }}>+</button>
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 2, flex: 1 }}>
                      {trabajando.map(t => {
                        const p = personal.find(x => x.id == t.personal_id);
                        if (!p) return null;
                        const c = colores[p.id];
                        return (
                          <div key={t.id} style={{
                            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                            background: t.tipo === 'reemplazo' ? '#fee2e2' : c?.light,
                            borderLeft: `3px solid ${t.tipo === 'reemplazo' ? '#ef4444' : c?.bg}`,
                            borderRadius: 3, padding: '2px 4px',
                          }}>
                            <span style={{ fontSize: 11, fontWeight: 600, color: t.tipo === 'reemplazo' ? '#b91c1c' : c?.text, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', flex: 1 }}>
                              {p.nombre}{t.tipo === 'reemplazo' ? ' ↔' : ''}
                            </span>
                            <button onClick={() => onDeleteTurno(t.id)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: t.tipo === 'reemplazo' ? '#f87171' : '#94a3b8', fontSize: 12, padding: '0 0 0 2px', lineHeight: 1, flexShrink: 0 }}>×</button>
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

function LiquidacionView({ personal, cajeros, plancheros, administradores = [], getLiquidacion, colores, onAjuste, onDeleteAjuste, mes, anio, pagosNomina, onReloadPagos, showToast, presupuesto, onSavePresupuesto, centroCosto = 'ruta11' }) {
  const MESES_L = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  const [savingPago, setSavingPago] = useState(false);
  const [expandidos, setExpandidos] = useState({});
  const [copied, setCopied] = useState(false);
  const [editPresupuesto, setEditPresupuesto] = useState(false);
  const [presupuestoInput, setPresupuestoInput] = useState('');

  function toggleCard(id) { setExpandidos(e => ({ ...e, [id]: !e[id] })); }

  function generarMarkdown() {
    const mesLabel = `${MESES_L[mes]} ${anio}`;
    let md = `*💰 Liquidación Nómina — ${mesLabel}*\n━━━━━━━━━━━━━━━━━━━━\n`;
    personal.forEach(p => {
      const { diasTrabajados, sueldoBase, ajustesPer, gruposReemplazando, gruposReemplazados, total } = getLiquidacion(p);
      let roles = p.rol;
      if (typeof p.rol === 'string') {
        roles = p.rol.split(',').map(r => r.trim()).join(', ');
      }
      md += `\n*${p.nombre}* (${roles})\n📅 Días: ${diasTrabajados}\nBase: $${sueldoBase.toLocaleString('es-CL')}\n`;
      Object.values(gruposReemplazando).forEach(g => { md += `↔ Reemplazó a ${g.persona?.nombre ?? '?'} (días ${g.dias.sort((a, b) => a - b).join(',')}): +$${g.monto.toLocaleString('es-CL')}\n`; });
      Object.values(gruposReemplazados).forEach(g => { md += `↔ ${g.persona?.nombre ?? '?'} cubrió días ${g.dias.sort((a, b) => a - b).join(',')}: -$${g.monto.toLocaleString('es-CL')}\n`; });
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

  function generarNotas(p) {
    const { sueldoBase, gruposReemplazando, gruposReemplazados, ajustesPer } = getLiquidacion(p);
    const partes = [`Base $${(sueldoBase / 1000).toFixed(0)}k`];
    Object.values(gruposReemplazando).forEach(g => partes.push(`+${g.dias.length} días ${g.persona?.nombre ?? '?'} +$${(g.monto / 1000).toFixed(0)}k`));
    Object.values(gruposReemplazados).filter(g => g.pago_por === 'empresa').forEach(g => partes.push(`-${g.dias.length} días ${g.persona?.nombre ?? '?'} -$${(g.monto / 1000).toFixed(0)}k`));
    ajustesPer.forEach(a => partes.push(a.concepto));
    return partes.join(' | ');
  }

  async function marcarPagado(p) {
    const { total } = getLiquidacion(p);
    setSavingPago(p.id);
    const mesStr = String(mes + 1).padStart(2, '0');
    try {
      const res = await fetch('/api/personal/save_pago_nomina.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          mes: `${anio}-${mesStr}`,
          personal_id: p.id,
          nombre: p.nombre,
          monto: total,
          es_externo: p.activo == 0 ? 1 : 0,
          notas: generarNotas(p),
        }),
      });
      const data = await res.json();
      if (data.success) { showToast(`${p.nombre} marcado como pagado`); onReloadPagos(); }
      else showToast(data.error || 'Error', 'error');
    } catch { showToast('Error de conexión', 'error'); }
    setSavingPago(null);
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
            {editPresupuesto
              ? <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                <input type='number' value={presupuestoInput} onChange={e => setPresupuestoInput(e.target.value)}
                  style={{ width: 120, padding: '4px 8px', borderRadius: 6, border: 'none', fontSize: 16, fontWeight: 700, color: '#1e293b', background: 'white' }} />
                <button onClick={() => { onSavePresupuesto(parseFloat(presupuestoInput)); setEditPresupuesto(false); }} style={{ padding: '4px 10px', borderRadius: 6, border: 'none', background: '#4ade80', color: '#065f46', cursor: 'pointer', fontWeight: 700 }}>✓</button>
                <button onClick={() => setEditPresupuesto(false)} style={{ padding: '4px 8px', borderRadius: 6, border: 'none', background: 'rgba(255,255,255,0.2)', color: 'white', cursor: 'pointer' }}>✕</button>
              </div>
              : <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <div style={{ fontSize: 22, fontWeight: 800 }}>${presupuesto.toLocaleString('es-CL')}</div>
                <button onClick={() => { setPresupuestoInput(presupuesto); setEditPresupuesto(true); }} style={{ padding: '2px 8px', borderRadius: 6, border: 'none', background: 'rgba(255,255,255,0.2)', color: 'white', cursor: 'pointer', fontSize: 12 }}>✏️</button>
              </div>
            }
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

      {/* Liquidación agrupada por roles */}

      {[['👔 Administradores', administradores], ['🧾 Cajeros', cajeros], ['🍳 Plancheros', plancheros]].filter(([, grupo]) => grupo.length > 0).map(([titulo, grupo]) => (
        <div key={titulo}>
          <h2 style={{ margin: '0 0 10px', fontSize: 15, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: 1 }}>{titulo}</h2>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {grupo.map(p => {
              const { diasTrabajados, ajustesPer, sueldoBase, gruposReemplazados, gruposReemplazando, total } = getLiquidacion(p);
              const c = colores[p.id];
              const abierto = expandidos[p.id] !== false;
              const pagado = pagosNomina.find(pn => pn.personal_id == p.id);
              // Get most specific ruta11 role label
              const roles = typeof p.rol === 'string' ? p.rol.split(',').map(r => r.trim()) : (Array.isArray(p.rol) ? p.rol : []);
              const rolLabel = roles.includes('administrador') ? 'Administrador' : roles.includes('cajero') ? 'Cajero/a' : roles.includes('planchero') ? 'Planchero/a' : '';
              return (
                <div key={p.id} style={{ background: 'white', borderRadius: 12, overflow: 'hidden', boxShadow: '0 1px 3px rgba(0,0,0,0.07)', border: `1px solid ${c?.border || '#e2e8f0'}` }}>
                  {/* Header row */}
                  <div onClick={() => toggleCard(p.id)} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 16px', cursor: 'pointer', background: c?.light || '#f8fafc', borderBottom: abierto ? `1px solid ${c?.border || '#e2e8f0'}` : 'none' }}>
                    {/* Color accent */}
                    <div style={{ width: 4, height: 36, borderRadius: 4, background: c?.bg || '#e2e8f0', flexShrink: 0 }} />
                    {/* Name + meta */}
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                        <span style={{ fontWeight: 700, fontSize: 15, color: '#1e293b' }}>{p.nombre}</span>
                        {pagado && <span style={{ fontSize: 12 }}>✅</span>}
                        <span style={{ fontSize: 11, padding: '2px 8px', borderRadius: 20, background: c?.bg || '#e2e8f0', color: 'white', fontWeight: 600, textTransform: 'capitalize' }}>{rolLabel}</span>
                      </div>
                      <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 2 }}>{diasTrabajados} días trabajados</div>
                    </div>
                    {/* Salary summary inline */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: 16, flexShrink: 0 }}>
                      <div style={{ textAlign: 'right' }}>
                        <div style={{ fontSize: 11, color: '#94a3b8' }}>Base {rolLabel}</div>
                        <div style={{ fontSize: 14, fontWeight: 600, color: '#334155' }}>${sueldoBase.toLocaleString('es-CL')}</div>
                      </div>
                      <div style={{ textAlign: 'right' }}>
                        <div style={{ fontSize: 11, color: '#94a3b8' }}>Total</div>
                        <div style={{ fontSize: 16, fontWeight: 800, color: c?.text || '#1e293b' }}>${total.toLocaleString('es-CL')}</div>
                      </div>
                      <span style={{ color: '#94a3b8', fontSize: 16 }}>{abierto ? '▾' : '▸'}</span>
                    </div>
                  </div>
                  {/* Expanded detail */}
                  {abierto && (
                    <div style={{ padding: '4px 16px 14px' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #f1f5f9' }}>
                        <span style={{ fontSize: 13, color: '#64748b' }}>Sueldo base ({rolLabel})</span>
                        <span style={{ fontSize: 13, fontWeight: 600 }}>${sueldoBase.toLocaleString('es-CL')}</span>
                      </div>
                      {Object.values(gruposReemplazando).map((g, i) => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '7px 0', borderBottom: '1px solid #f1f5f9' }}>
                          <span style={{ fontSize: 13, color: '#64748b' }}>↔ Reemplazó a {g.persona?.nombre ?? '?'} ({g.dias.length} {g.dias.length === 1 ? 'día' : 'días'}: {g.dias.sort((a, b) => a - b).join(',')})</span>
                          <span style={{ fontSize: 13, fontWeight: 600, color: '#10b981' }}>+${g.monto.toLocaleString('es-CL')}</span>
                        </div>
                      ))}
                      {Object.values(gruposReemplazados).map((g, i) => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '7px 0', borderBottom: '1px solid #f1f5f9', gap: 8 }}>
                          <span style={{ fontSize: 13, color: '#64748b' }}>
                            {g.persona?.nombre ?? '?'} cubrió días {g.dias.sort((a, b) => a - b).join(',')}
                            {g.pago_por === 'titular' && <span style={{ marginLeft: 6, fontSize: 11, padding: '1px 6px', borderRadius: 10, background: '#fef3c7', color: '#b45309', fontWeight: 600 }}>entre ellos</span>}
                          </span>
                          {g.pago_por === 'empresa'
                            ? <span style={{ fontSize: 13, fontWeight: 600, color: '#ef4444' }}>-${g.monto.toLocaleString('es-CL')}</span>
                            : <span style={{ fontSize: 13, color: '#94a3b8' }}>—</span>}
                        </div>
                      ))}
                      {ajustesPer.map(a => (
                        <div key={a.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '7px 0', borderBottom: '1px solid #f1f5f9', gap: 8 }}>
                          <span style={{ fontSize: 13, color: '#64748b', flex: 1 }}>{a.concepto}</span>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ fontSize: 13, fontWeight: 600, color: parseFloat(a.monto) < 0 ? '#ef4444' : '#10b981' }}>
                              {parseFloat(a.monto) < 0 ? '-' : '+'}${Math.abs(parseFloat(a.monto)).toLocaleString('es-CL')}
                            </span>
                            <button onClick={() => onDeleteAjuste(a.id)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#94a3b8', fontSize: 16, padding: 2 }}>×</button>
                          </div>
                        </div>
                      ))}
                      <div style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0 8px', fontWeight: 700 }}>
                        <span style={{ fontSize: 14 }}>Total a pagar</span>
                        <span style={{ fontSize: 16, color: c?.text }}>${total.toLocaleString('es-CL')}</span>
                      </div>
                      <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                        <button onClick={() => onAjuste(p)} style={{ flex: 1, padding: '9px', border: `1px dashed ${c?.border}`, borderRadius: 8, background: c?.light, color: c?.text, cursor: 'pointer', fontSize: 13, fontWeight: 600 }}>+ Ajuste</button>
                        {pagado ? (
                          <button onClick={() => deletePago(pagado.id)} style={{ flex: 1, padding: '9px', border: '1px solid #fca5a5', borderRadius: 8, background: '#fef2f2', color: '#ef4444', cursor: 'pointer', fontSize: 13, fontWeight: 700 }}>Desmarcar Pago</button>
                        ) : (
                          <button onClick={() => marcarPagado(p)} disabled={savingPago === p.id} style={{ flex: 1, padding: '9px', border: 'none', borderRadius: 8, background: '#10b981', color: 'white', cursor: 'pointer', fontSize: 13, fontWeight: 700 }}>
                            {savingPago === p.id ? 'Cargando...' : 'Marcar Pagado'}
                          </button>
                        )}
                      </div>
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

function LiquidacionSeguridad({ guardias, getLiquidacion, colores, onAjuste, onDeleteAjuste, mes, anio, pagosNomina, onReloadPagos, showToast, presupuesto, onSavePresupuesto, centroCosto = 'seguridad' }) {
  const MESES_L = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  const [savingPago, setSavingPago] = useState(false);
  const [expandidos, setExpandidos] = useState({});
  const [copied, setCopied] = useState(false);
  const [editPresupuesto, setEditPresupuesto] = useState(false);
  const [presupuestoInput, setPresupuestoInput] = useState('');

  function toggleCard(id) { setExpandidos(e => ({ ...e, [id]: !e[id] })); }

  function generarMarkdown() {
    const mesLabel = `${MESES_L[mes]} ${anio}`;
    let md = `*🛡️ Liquidación Seguridad — ${mesLabel}*\n━━━━━━━━━━━━━━━━━━━━\n`;
    guardias.forEach(p => {
      const { diasTrabajados, sueldoBase, ajustesPer, gruposReemplazando, gruposReemplazados, total } = getLiquidacion(p);
      let roles = p.rol;
      if (typeof p.rol === 'string') {
        roles = p.rol.split(',').map(r => r.trim()).join(', ');
      }
      md += `\n*${p.nombre}* (${roles})\n📅 Días: ${diasTrabajados}\nBase: $${sueldoBase.toLocaleString('es-CL')}\n`;
      Object.values(gruposReemplazando).forEach(g => { md += `↔ Reemplazó a ${g.persona?.nombre ?? '?'} (días ${g.dias.sort((a, b) => a - b).join(',')}): +$${g.monto.toLocaleString('es-CL')}\n`; });
      Object.values(gruposReemplazados).forEach(g => { md += `↔ ${g.persona?.nombre ?? '?'} cubrió días ${g.dias.sort((a, b) => a - b).join(',')}: -$${g.monto.toLocaleString('es-CL')}\n`; });
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

  const totalCalculado = guardias.reduce((s, p) => s + getLiquidacion(p).total, 0);
  const pagosGuardias = pagosNomina.filter(pn => guardias.some(g => g.id == pn.personal_id));
  const totalPagado = pagosGuardias.reduce((s, p) => s + parseFloat(p.monto), 0);
  const diferencia = totalPagado - presupuesto;
  const hayPagos = pagosGuardias.length > 0;

  function generarNotas(p) {
    const { sueldoBase, gruposReemplazando, gruposReemplazados, ajustesPer } = getLiquidacion(p);
    const partes = [`Base $${(sueldoBase / 1000).toFixed(0)}k`];
    Object.values(gruposReemplazando).forEach(g => partes.push(`+${g.dias.length} días ${g.persona?.nombre ?? '?'} +$${(g.monto / 1000).toFixed(0)}k`));
    Object.values(gruposReemplazados).filter(g => g.pago_por === 'empresa').forEach(g => partes.push(`-${g.dias.length} días ${g.persona?.nombre ?? '?'} -$${(g.monto / 1000).toFixed(0)}k`));
    ajustesPer.forEach(a => partes.push(a.concepto));
    return partes.join(' | ');
  }

  async function marcarPagado(p) {
    const { total } = getLiquidacion(p);
    setSavingPago(p.id);
    const mesStr = String(mes + 1).padStart(2, '0');
    try {
      const res = await fetch('/api/personal/save_pago_nomina.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          mes: `${anio}-${mesStr}`,
          personal_id: p.id,
          nombre: p.nombre,
          monto: total,
          es_externo: p.activo == 0 ? 1 : 0,
          notas: generarNotas(p),
        }),
      });
      const data = await res.json();
      if (data.success) { showToast(`${p.nombre} marcado como pagado`); onReloadPagos(); }
      else showToast(data.error || 'Error', 'error');
    } catch { showToast('Error de conexión', 'error'); }
    setSavingPago(null);
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
      <div style={{ background: 'linear-gradient(135deg, #1e1b4b, #312e81)', borderRadius: 16, padding: '20px 24px', color: 'white' }}>
        <div style={{ fontSize: 13, opacity: 0.7, marginBottom: 12 }}>💼 Centro de Costos — Seguridad {MESES_L[mes]} {anio}</div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 16 }}>
          <div>
            <div style={{ fontSize: 12, opacity: 0.6 }}>Presupuesto</div>
            {editPresupuesto
              ? <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                <input type='number' value={presupuestoInput} onChange={e => setPresupuestoInput(e.target.value)}
                  style={{ width: 120, padding: '4px 8px', borderRadius: 6, border: 'none', fontSize: 16, fontWeight: 700, color: '#1e293b', background: 'white' }} />
                <button onClick={() => { onSavePresupuesto(parseFloat(presupuestoInput)); setEditPresupuesto(false); }} style={{ padding: '4px 10px', borderRadius: 6, border: 'none', background: '#4ade80', color: '#065f46', cursor: 'pointer', fontWeight: 700 }}>✓</button>
                <button onClick={() => setEditPresupuesto(false)} style={{ padding: '4px 8px', borderRadius: 6, border: 'none', background: 'rgba(255,255,255,0.2)', color: 'white', cursor: 'pointer' }}>✕</button>
              </div>
              : <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <div style={{ fontSize: 22, fontWeight: 800 }}>${presupuesto.toLocaleString('es-CL')}</div>
                <button onClick={() => { setPresupuestoInput(presupuesto); setEditPresupuesto(true); }} style={{ padding: '2px 8px', borderRadius: 6, border: 'none', background: 'rgba(255,255,255,0.2)', color: 'white', cursor: 'pointer', fontSize: 12 }}>✏️</button>
              </div>
            }
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

      {[['🛡️ Guardias', guardias]].map(([titulo, grupo]) => (
        <div key={titulo}>
          <h2 style={{ margin: '0 0 12px', fontSize: 16, fontWeight: 700, color: '#1e293b' }}>{titulo}</h2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: 16 }}>
            {grupo.map(p => {
              const { diasTrabajados, ajustesPer, sueldoBase, gruposReemplazados, gruposReemplazando, total } = getLiquidacion(p);
              const c = colores[p.id] || { bg: '#e0e7ff', border: '#c7d2fe', light: '#eef2ff', text: '#3730a3' };
              const abierto = expandidos[p.id] !== false;
              return (
                <div key={p.id} style={{ background: 'white', borderRadius: 16, overflow: 'hidden', boxShadow: '0 1px 4px rgba(0,0,0,0.08)', border: `1px solid ${c.border}` }}>
                  <div onClick={() => toggleCard(p.id)} style={{ background: c.bg, padding: '16px 20px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', cursor: 'pointer' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                      {pagosNomina.find(pn => pn.personal_id == p.id) && <span style={{ fontSize: 18 }}>✅</span>}
                      <div>
                        <div style={{ color: 'white', fontWeight: 700, fontSize: 18 }}>{p.nombre}</div>
                        <div style={{ color: 'rgba(255,255,255,0.8)', fontSize: 12, textTransform: 'capitalize' }}>{typeof p.rol === 'string' ? p.rol.split(',').join(', ') : p.rol} · {diasTrabajados} días trabajados</div>
                      </div>
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
                      {Object.values(gruposReemplazando).map((g, i) => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #f1f5f9' }}>
                          <span style={{ fontSize: 13, color: '#64748b' }}>↔ Reemplazó a {g.persona?.nombre ?? '?'} ({g.dias.sort((a, b) => a - b).length} {g.dias.length === 1 ? 'día' : 'días'}: {g.dias.sort((a, b) => a - b).join(',')})</span>
                          <span style={{ fontSize: 13, fontWeight: 600, color: '#10b981' }}>+${g.monto.toLocaleString('es-CL')}</span>
                        </div>
                      ))}
                      {Object.values(gruposReemplazados).map((g, i) => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #f1f5f9', gap: 8 }}>
                          <span style={{ fontSize: 13, color: '#64748b' }}>
                            {g.persona?.nombre ?? '?'} cubrió días {g.dias.sort((a, b) => a - b).join(',')}
                            {g.pago_por === 'titular' && <span style={{ marginLeft: 6, fontSize: 11, padding: '1px 6px', borderRadius: 10, background: '#fef3c7', color: '#b45309', fontWeight: 600 }}> entre ellos</span>}
                          </span>
                          {g.pago_por === 'empresa'
                            ? <span style={{ fontSize: 13, fontWeight: 600, color: '#ef4444' }}>-${g.monto.toLocaleString('es-CL')}</span>
                            : <span style={{ fontSize: 13, color: '#94a3b8' }}>—</span>
                          }
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
                        <span style={{ fontSize: 18, color: c.text }}>${total.toLocaleString('es-CL')}</span>
                      </div>
                      <div style={{ display: 'flex', gap: 10, marginTop: 12 }}>
                        <button onClick={() => onAjuste(p)} style={{
                          flex: 1, padding: '10px', border: `1px dashed ${c.border}`,
                          borderRadius: 8, background: c.light, color: c.text,
                          cursor: 'pointer', fontSize: 13, fontWeight: 600,
                        }}>+ Ajuste / Descuento</button>

                        {pagosNomina.find(pn => pn.personal_id == p.id) ? (
                          <button onClick={() => deletePago(pagosNomina.find(pn => pn.personal_id == p.id).id)} style={{
                            flex: 1, padding: '10px', border: '1px solid #fca5a5',
                            borderRadius: 8, background: '#fef2f2', color: '#ef4444',
                            cursor: 'pointer', fontSize: 13, fontWeight: 700,
                          }}>Desmarcar Pago</button>
                        ) : (
                          <button onClick={() => marcarPagado(p)} disabled={savingPago === p.id} style={{
                            flex: 1, padding: '10px', border: 'none',
                            borderRadius: 8, background: '#10b981', color: 'white',
                            cursor: 'pointer', fontSize: 13, fontWeight: 700,
                          }}>
                            {savingPago === p.id ? 'Cargando...' : 'Marcar Pagado'}
                          </button>
                        )}
                      </div>
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

function EquipoView({ personal, onAddPersonal, onEditPersonal }) {
  return (
    <div style={{ background: 'white', borderRadius: 16, padding: 24, boxShadow: '0 1px 4px rgba(0,0,0,0.08)', border: '1px solid #e2e8f0' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
        <h2 style={{ margin: 0, fontSize: 18, fontWeight: 700, color: '#1e293b' }}>👤 Equipo La Ruta 11</h2>
        <button onClick={onAddPersonal} style={{ padding: '10px 18px', border: 'none', borderRadius: 10, background: '#3b82f6', color: 'white', cursor: 'pointer', fontWeight: 700, fontSize: 14 }}>+ Agregar persona</button>
      </div>

      <div style={{ overflowX: 'auto' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
          <thead>
            <tr style={{ borderBottom: '2px solid #f1f5f9' }}>
              <th style={{ padding: '12px 16px', fontSize: 13, color: '#64748b', fontWeight: 600 }}>Nombre</th>
              <th style={{ padding: '12px 16px', fontSize: 13, color: '#64748b', fontWeight: 600 }}>Rol</th>
              <th style={{ padding: '12px 16px', fontSize: 13, color: '#64748b', fontWeight: 600 }}>Sueldo Base</th>
              <th style={{ padding: '12px 16px', fontSize: 13, color: '#64748b', fontWeight: 600 }}>Estado</th>
              <th style={{ padding: '12px 16px', fontSize: 13, color: '#64748b', fontWeight: 600, textAlign: 'right' }}>Acciones</th>
            </tr>
          </thead>
          <tbody>
            {personal.map(p => (
              <tr key={p.id} style={{ borderBottom: '1px solid #f1f5f9' }}>
                <td style={{ padding: '16px', fontSize: 15, fontWeight: 700, color: '#1e293b' }}>{p.nombre}</td>
                <td style={{ padding: '16px', fontSize: 14, color: '#64748b', textTransform: 'capitalize' }}>{typeof p.rol === 'string' ? p.rol.split(',').join(', ') : p.rol}</td>
                <td style={{ padding: '16px', fontSize: 14, fontWeight: 600 }}>${parseFloat(p.sueldo_base).toLocaleString('es-CL')}</td>
                <td style={{ padding: '16px' }}>
                  <span style={{
                    fontSize: 11, padding: '4px 10px', borderRadius: 20, fontWeight: 700,
                    background: p.activo == 1 ? '#eef2ff' : '#fff7ed',
                    color: p.activo == 1 ? '#4f46e5' : '#c2410c'
                  }}>
                    {p.activo == 1 ? 'Activo' : 'Inactivo/Externo'}
                  </span>
                </td>
                <td style={{ padding: '16px', textAlign: 'right' }}>
                  <button onClick={() => onEditPersonal(p)} style={{ padding: '6px 14px', border: '1px solid #e2e8f0', borderRadius: 8, background: 'white', cursor: 'pointer', fontSize: 12, fontWeight: 600, color: '#3b82f6' }}>Editar</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function CalendarioSeguridad({ diasEnMes, primerDiaLunes, turnosSeguridad, personal, mes, anio, onAddTurno, onDeleteTurno }) {
  const DIAS_LABEL = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
  const celdas = [];
  for (let i = 0; i < primerDiaLunes; i++) celdas.push(null);
  for (let d = 1; d <= diasEnMes; d++) celdas.push(d);

  // Colores dedicados a seguridad
  const seguridadColors = {
    'Ricardo': { bg: '#bae6fd', border: '#7dd3fc', text: '#0369a1', line: '#0ea5e9' },
    'Claudio': { bg: '#ddd6fe', border: '#c4b5fd', text: '#5b21b6', line: '#8b5cf6' },
    'default': { bg: '#fef08a', border: '#fde047', text: '#a16207', line: '#eab308' }
  };

  return (
    <div style={{ background: 'white', borderRadius: 16, padding: 24, boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)', border: '1px solid #e0e7ff' }}>
      <div style={{ marginBottom: 20 }}>
        <h2 style={{ margin: 0, fontSize: 24, fontWeight: 800, color: '#1e1b4b' }}>🛡️ Club de Yates</h2>
        <p style={{ margin: '4px 0 16px', color: '#4f46e5', fontSize: 14, fontWeight: 600 }}>Calendario de Rondas y Seguridad · Turnos 4x4 (Lunes - Domingo)</p>

        {/* Leyenda */}
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10 }}>
          {personal.filter(p => (typeof p.rol === 'string' ? p.rol.includes('seguridad') : Array.isArray(p.rol) ? p.rol.includes('seguridad') : false) && p.activo == 1).map(p => {
            const c = seguridadColors[p.nombre] || seguridadColors['default'];
            return (
              <div key={p.id} style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '4px 12px', borderRadius: 20, background: c.bg, border: `1px solid ${c.border}` }}>
                <div style={{ width: 10, height: 10, borderRadius: '50%', background: c.line }} />
                <span style={{ fontSize: 13, fontWeight: 700, color: c.text }}>{p.nombre}</span>
              </div>
            );
          })}
        </div>
      </div>

      <div style={{ borderRadius: 12, overflow: 'hidden', border: '1px solid #c7d2fe', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', background: '#4f46e5', color: 'white' }}>
          {DIAS_LABEL.map(d => (
            <div key={d} style={{ padding: '12px 4px', textAlign: 'center', fontSize: 13, fontWeight: 800 }}>{d}</div>
          ))}
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 1, background: '#c7d2fe' }}>
          {celdas.map((dia, i) => {
            const trabajando = dia ? (turnosSeguridad[dia] || []) : [];
            const fecha = dia ? `${anio}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}` : '';
            return (
              <div key={i} style={{
                background: dia ? 'white' : '#f8fafc',
                minHeight: 110, padding: '8px 6px',
                display: 'flex', flexDirection: 'column',
                transition: 'background 0.2s',
              }}>
                {dia && (
                  <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 }}>
                      <span style={{ fontSize: 14, fontWeight: 800, color: '#312e81' }}>{dia}</span>
                      <button onClick={() => {
                        const titularObj = trabajando.find(t => t.is_dynamic);
                        const titularId = titularObj ? titularObj.personal_id : '';
                        onAddTurno({ dia, fecha, isSeguridad: true, titularId });
                      }} style={{
                        fontSize: 14, width: 22, height: 22, display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: 6,
                        border: '1px solid #a5b4fc', background: '#e0e7ff', cursor: 'pointer', color: '#4f46e5', fontWeight: 800
                      }} title="Agregar Reemplazo">+</button>
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 4, flex: 1 }}>
                      {trabajando.map(t => {
                        const p = personal.find(x => x.id == t.personal_id);
                        if (!p) return null;
                        const c = seguridadColors[p.nombre] || seguridadColors['default'];
                        const isDynamic = t.is_dynamic;
                        const isReemplazo = t.tipo === 'reemplazo';

                        return (
                          <div key={t.id || (dia + p.id)} style={{
                            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                            background: c.bg, borderLeft: `4px solid ${c.line}`,
                            borderRadius: 4, padding: '4px 6px',
                            opacity: isDynamic && !isReemplazo ? 0.8 : 1,
                            boxShadow: isReemplazo ? '0 1px 2px rgba(0,0,0,0.1)' : 'none'
                          }}>
                            <span style={{ fontSize: 12, fontWeight: 700, color: c.text, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', flex: 1 }}>
                              {p.nombre}{isReemplazo ? ' (R)' : ''}
                            </span>
                            {!isDynamic && (
                              <button onClick={() => onDeleteTurno(t.id)} style={{ background: 'white', border: 'none', borderRadius: '50%', width: 16, height: 16, display: 'flex', justifyContent: 'center', alignItems: 'center', cursor: 'pointer', color: '#ef4444', fontSize: 13, padding: 0, fontWeight: 800, marginLeft: 2 }} title="Eliminar Reemplazo">×</button>
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
