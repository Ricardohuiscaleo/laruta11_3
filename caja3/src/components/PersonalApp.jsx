import React, { useState, useEffect } from 'react';
import {
  Users,
  Calendar,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  ChevronDown,
  DollarSign,
  Mail,
  Eye,
  Send,
  Plus,
  Trash2,
  Edit,
  Check,
  X,
  Image as ImageIcon,
  ShieldCheck,
  FileText,
  AlertCircle,
  Shield,
  Store
} from 'lucide-react';

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

const COLORES = {
  1: { bg: '#d946ef', light: '#fdf4ff', border: '#f5d0fe', text: '#a21caf' }, // Camila - Rosa Fucsia
  2: { bg: '#0ea5e9', light: '#f0f9ff', border: '#bae6fd', text: '#0369a1' }, // Neit - Azul Cian
  3: { bg: '#eab308', light: '#fefce8', border: '#fef08a', text: '#a16207' }, // Andrés - Amarillo Sol
  4: { bg: '#84cc16', light: '#f7fee7', border: '#d9f99d', text: '#4d7c0f' }, // Gabriel - Verde Lima
  5: { bg: '#8b5cf6', light: '#f5f3ff', border: '#ddd6fe', text: '#6d28d9' }, // Ricardo - Morado Eléctrico
  6: { bg: '#f97316', light: '#fff7ed', border: '#fed7aa', text: '#c2410c' }, // Claudio - Naranja Vitamínico
  7: { bg: '#f43f5e', light: '#fff1f2', border: '#fecdd3', text: '#be123c' }, // Rojo Coral
  8: { bg: '#14b8a6', light: '#f0fdfa', border: '#99f6e4', text: '#0f766e' }, // Azul Turquesa
};

function useWindowWidth() {
  const [width, setWidth] = React.useState(1200);
  React.useEffect(() => {
    setWidth(window.innerWidth);
    const handleResize = () => setWidth(window.innerWidth);
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);
  return width;
}



export default function PersonalApp() {
  const [tab, setTab] = useState('nomina');
  const [mes, setMes] = useState(() => new Date().getMonth());
  const [anio, setAnio] = useState(new Date().getFullYear());
  const [hasMounted, setHasMounted] = useState(false);
  const width = useWindowWidth();
  const isMobile = hasMounted && width < 768;

  useEffect(() => {
    setHasMounted(true);
  }, []);
  const [personal, setPersonal] = useState([]);
  const [turnos, setTurnos] = useState([]);
  const [ajustes, setAjustes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [pagosNomina, setPagosNomina] = useState({ ruta11: [], seguridad: [] });
  const [presupuestoNomina, setPresupuestoNomina] = useState({ ruta11: 1200000, seguridad: 1200000 });
  const [modalAjuste, setModalAjuste] = useState(null);
  const [formAjuste, setFormAjuste] = useState({ monto: '', concepto: '', notas: '', tipo: '-' });
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
          monto: parseFloat(formAjuste.monto) * (formAjuste.tipo === '-' ? -1 : 1),
          concepto: formAjuste.concepto,
          notas: formAjuste.notas || '',
        }),
      });
      const data = await res.json();
      if (data.success) {
        showToast('Ajuste guardado');
        setModalAjuste(null);
        setFormAjuste({ monto: '', concepto: '', notas: '', tipo: '-' });
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

  // Helpers de seguridad
  // tipo='seguridad' → turno 4x4 dinámico
  // tipo='reemplazo_seguridad' → reemplazo nuevo (creado desde el modal de Seguridad)
  // tipo='reemplazo' + titular con rol seguridad → reemplazo viejo en DB que pertenece a Seguridad
  // tipo='normal' → NUNCA es seguridad (esto causaba el bug de 36 días)
  const isShiftSeguridad = (t) => {
    if (t.tipo === 'seguridad' || t.tipo === 'reemplazo_seguridad') return true;
    if (t.tipo === 'reemplazo') {
      const titular = personal.find(x => x.id == t.personal_id);
      return titular?.rol?.includes('seguridad') || false;
    }
    return false;
  };

  // Mapa de turnos divididos por calendario
  const turnosNoSeguridad = {};
  const turnosSeguridad = {};
  turnos.forEach(t => {
    const d = t.fecha.split('T')[0].split('-')[2];
    const dia = parseInt(d);

    if (isShiftSeguridad(t)) {
      if (!turnosSeguridad[dia]) turnosSeguridad[dia] = [];
      turnosSeguridad[dia].push(t);
    } else {
      if (!turnosNoSeguridad[dia]) turnosNoSeguridad[dia] = [];
      turnosNoSeguridad[dia].push(t);
    }
  });

  // Calcular liquidación por persona
  function getLiquidacion(p, modoContexto = 'all') {
    const turnosFiltrados = turnos.filter(t => {
      if (modoContexto === 'seguridad') return isShiftSeguridad(t);
      if (modoContexto === 'ruta11') return !isShiftSeguridad(t);
      return true;
    });

    const tPersonal = turnosFiltrados.filter(t => t.personal_id == p.id);
    const diasNormales = tPersonal.filter(t => t.tipo === 'normal' || t.tipo === 'seguridad').length;
    const asTitular = (t) => t.personal_id;
    const asReplacer = (t) => t.reemplazado_por;

    const rawReemplazados = turnosFiltrados.filter(t => (t.tipo === 'reemplazo' || t.tipo === 'reemplazo_seguridad') && asTitular(t) == p.id);
    const diasReemplazados = rawReemplazados.length;
    const gruposReemplazados = {};
    rawReemplazados.forEach(t => {
      const key = asReplacer(t) ?? 'ext';
      if (!gruposReemplazados[key]) gruposReemplazados[key] = { persona: personal.find(x => x.id == asReplacer(t)), dias: [], monto: 0, pago_por: t.pago_por || 'empresa' };
      gruposReemplazados[key].dias.push(parseInt(t.fecha.split('T')[0].split('-')[2]));
      gruposReemplazados[key].monto += parseFloat(t.monto_reemplazo || 20000);
    });

    const rawReemplazando = turnosFiltrados.filter(t => (t.tipo === 'reemplazo' || t.tipo === 'reemplazo_seguridad') && asReplacer(t) == p.id);
    const reemplazosHechos = rawReemplazando.length;
    const gruposReemplazando = {};
    rawReemplazando.forEach(t => {
      const key = asTitular(t) ?? 'err';
      if (!gruposReemplazando[key]) gruposReemplazando[key] = { persona: personal.find(x => x.id == asTitular(t)), dias: [], monto: 0, pago_por: t.pago_por || 'empresa' };
      gruposReemplazando[key].dias.push(parseInt(t.fecha.split('T')[0].split('-')[2]));
      gruposReemplazando[key].monto += parseFloat(t.monto_reemplazo || 20000);
    });

    const diasTrabajados = modoContexto === 'seguridad' ? (30 - diasReemplazados) : (diasNormales + reemplazosHechos);

    // Salarios Base
    let sueldoBase = 0;
    if (modoContexto === 'seguridad') {
      sueldoBase = parseFloat(p.sueldo_base_seguridad) || 0;
    } else if (modoContexto === 'ruta11') {
      const roles = typeof p.rol === 'string' ? p.rol.split(',').map(r => r.trim()) : (Array.isArray(p.rol) ? p.rol : []);
      if (roles.includes('administrador')) sueldoBase = parseFloat(p.sueldo_base_admin) || 0;
      else if (roles.includes('cajero')) sueldoBase = parseFloat(p.sueldo_base_cajero) || 0;
      else if (roles.includes('planchero')) sueldoBase = parseFloat(p.sueldo_base_planchero) || 0;
    } else {
      // modoContexto === 'all'
      const roles = typeof p.rol === 'string' ? p.rol.split(',').map(r => r.trim()) : (Array.isArray(p.rol) ? p.rol : []);
      let b11 = 0;
      if (roles.includes('administrador')) b11 = parseFloat(p.sueldo_base_admin) || 0;
      else if (roles.includes('cajero')) b11 = parseFloat(p.sueldo_base_cajero) || 0;
      else if (roles.includes('planchero')) b11 = parseFloat(p.sueldo_base_planchero) || 0;
      sueldoBase = b11 + (parseFloat(p.sueldo_base_seguridad) || 0);
    }

    // Ajustes: Solo restar una vez. Por defecto en 'all' o 'ruta11'.
    const hasRuta11 = (typeof p.rol === 'string' ? p.rol : '').match(/administrador|cajero|planchero/);
    const includeAjustes = modoContexto === 'all' || modoContexto === 'ruta11' || (modoContexto === 'seguridad' && !hasRuta11);

    const ajustesPer = ajustes.filter(a => a.personal_id == p.id);
    const totalAjustes = includeAjustes ? ajustesPer.reduce((s, a) => s + parseFloat(a.monto), 0) : 0;
    const costoAjustes = includeAjustes ? ajustesPer.reduce((s, a) => {
      const m = parseFloat(a.monto);
      if (m > 0) return s + m;
      const desc = (a.concepto || '').toLowerCase();
      if (desc.includes('adelanto') || desc.includes('anticipo') || desc.includes('prestamo')) return s;
      return s + m;
    }, 0) : 0;

    const totalReemplazando = Object.values(gruposReemplazando).filter(g => g.pago_por === 'empresa').reduce((s, g) => s + g.monto, 0);
    const totalReemplazandoCosto = Object.values(gruposReemplazando).filter(g => g.pago_por === 'empresa' || g.pago_por === 'empresa_adelanto').reduce((s, g) => s + g.monto, 0);
    const totalReemplazados = Object.values(gruposReemplazados).filter(g => g.pago_por === 'empresa' || g.pago_por === 'empresa_adelanto').reduce((s, g) => s + g.monto, 0);

    const total = Math.round(sueldoBase + totalReemplazando - totalReemplazados + totalAjustes);
    const costoEmpresa = Math.round(sueldoBase + totalReemplazandoCosto - totalReemplazados + costoAjustes);
    const montoAdelantos = Math.max(0, costoEmpresa - total);
    return { diasNormales, diasReemplazados, reemplazosHechos, diasTrabajados, ajustesPer, totalAjustes, sueldoBase: Math.round(sueldoBase), gruposReemplazados, gruposReemplazando, total, costoEmpresa, montoAdelantos };
  }

  const administradores = personal.filter(p => p.rol?.includes('administrador') && p.activo == 1);
  const cajeros = personal.filter(p => p.rol?.includes('cajero') && !p.rol?.includes('administrador') && p.activo == 1);
  const plancheros = personal.filter(p => p.rol?.includes('planchero') && p.activo == 1);
  const guardias = personal.filter(p => p.rol?.includes('seguridad') && p.activo == 1);


  return (
    <div style={{ fontFamily: 'Inter, system-ui, sans-serif', minHeight: '100vh', background: '#f8fafd', color: '#1f1f1f' }}>
      <style>{`
        .app-header { background: white; border-bottom: 1px solid #e3e3e3; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; height: 64px; }
        .header-title-container { display: flex; align-items: center; gap: 16px; }
        .header-icon { background: #1a73e8; color: white; padding: 8px; border-radius: 12px; display: flex; }
        .header-title { margin: 0; font-size: 20px; font-weight: 500; color: #444746; }
        
        .date-nav { display: flex; flex-wrap: nowrap; align-items: center; gap: 8px; background: #f1f3f4; padding: 4px 8px; border-radius: 20px; }
        .date-text { font-weight: 600; font-size: 14px; min-width: 140px; text-align: center; color: #1f1f1f; }
        .profile-bubble { width: 40px; height: 40px; border-radius: 50%; background: #4285f4; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; flex-shrink: 0; }
        
        .main-nav { background: white; border-bottom: 1px solid #e3e3e3; position: sticky; top: 64px; z-index: 90; }
        .nav-container { max-width: 1100px; margin: 0 auto; display: flex; gap: 0; padding: 0 12px; overflow-x: auto; }
        .nav-tab { padding: 16px 20px; border: none; background: none; cursor: pointer; font-size: 14px; font-weight: 500; flex-shrink: 0; display: flex; align-items: center; gap: 8px; transition: all 0.2s; white-space: nowrap; color: #444746; border-bottom: 3px solid transparent; }
        .nav-tab.active { color: #1a73e8; border-bottom-color: #1a73e8; }
        
        @media (max-width: 450px) {
          .app-header { padding: 8px 12px; height: auto; min-height: 56px; flex-wrap: nowrap; gap: 4px; overflow-x: auto; }
          .header-title-container { gap: 6px; flex-shrink: 0; }
          .header-icon { padding: 4px; transform: scale(0.8); }
          .header-title { font-size: 15px; white-space: nowrap; }
          .date-nav { padding: 2px 4px; gap: 2px; flex-shrink: 0; margin-left: auto; }
          .date-text { min-width: 85px; font-size: 12px; white-space: nowrap; }
          .profile-bubble { display: none; }
          .main-nav { top: 56px; }
          .nav-container { padding: 0 4px; justify-content: space-between; }
          .nav-tab { padding: 12px 4px; font-size: 12px; gap: 4px; flex: 1; justify-content: center; }
          .nav-tab svg, .nav-tab img { transform: scale(0.85); flex-shrink: 0; }
          
          /* Tarjetas Nómina (Resumen) */
          .nomina-card-header { flex-wrap: nowrap !important; align-items: flex-start !important; gap: 6px !important; }
          .nomina-card-actions { margin-left: auto; flex-shrink: 0; }
          .nomina-card-grid { grid-template-columns: 1fr 1fr 1fr !important; gap: 4px !important; padding: 10px !important; }
          .nomina-card-grid > div > div:nth-child(1) { font-size: 8px !important; letter-spacing: 0 !important; }
          .nomina-card-grid > div > div:nth-child(2) { font-size: 12px !important; }
          .nomina-card-name { font-size: 14px !important; white-space: normal !important; word-wrap: break-word !important; line-height: 1.2; }
          .nomina-card-rol { font-size: 10px !important; white-space: normal !important; word-break: break-all; }
          .nomina-card-avatar { width: 36px !important; height: 36px !important; font-size: 14px !important; }
          
          /* Tarjetas Detalle (Ruta 11 y Seguridad) */
          .detail-card-header { padding: 12px 14px !important; align-items: stretch !important; flex-direction: column; gap: 12px !important; }
          .detail-card-name-section { gap: 6px !important; }
          .detail-card-name-text { font-size: 15px !important; }
          .detail-card-meta { flex-direction: row !important; justify-content: space-between; align-items: center; width: 100%; border-top: 1px dashed #e2e8f0; padding-top: 12px; }
          .detail-card-base-col, .detail-card-total-col { text-align: left !important; }
          .detail-card-base-col > div:first-child, .detail-card-total-col > div:first-child { font-size: 9px !important; }
          .detail-card-base-col > div:last-child { font-size: 13px !important; }
          .detail-card-total-col > div:last-child { font-size: 15px !important; }
          .detail-card-accent { display: none !important; }
        }
      `}</style>

      {/* Header Premium */}
      <header className="app-header">
        <div className="header-title-container">
          <div className="header-icon"><Users size={24} /></div>
          <h1 className="header-title">Gestión de Personal</h1>
        </div>

        {/* Date Navigation in Header */}
        <div className="date-nav">
          <button onClick={() => { if (mes === 0) { setMes(11); setAnio(a => a - 1); } else setMes(m => m - 1); }}
            style={{ border: 'none', background: 'transparent', cursor: 'pointer', padding: 4, display: 'flex', color: '#444746' }}><ChevronLeft size={20} /></button>
          <span className="date-text">{MESES[mes]} {anio}</span>
          <button onClick={() => { if (mes === 11) { setMes(0); setAnio(a => a + 1); } else setMes(m => m + 1); }}
            style={{ border: 'none', background: 'transparent', cursor: 'pointer', padding: 4, display: 'flex', color: '#444746' }}><ChevronRight size={20} /></button>
        </div>


      </header>

      {/* Navigation Tabs */}
      <nav className="main-nav">
        <div className="nav-container">
          {[
            ['nomina', <FileText size={18} />, 'Nómina'],
            ['liquidacion', <img src="/11.png" style={{ width: 18, height: 18 }} />, 'La Ruta 11'],
            ['seguridad', <img src="/camicono.png" style={{ width: 18, height: 18 }} />, 'Cam Seguridad'],
          ].map(([key, icon, label]) => (
            <button key={key} onClick={() => setTab(key)} className={`nav-tab ${tab === key ? 'active' : ''}`}>
              {icon}
              {label}
            </button>
          ))}
        </div>
      </nav>

      <main style={{ maxWidth: 1100, margin: '0 auto', padding: '24px 4px' }}>
        {loading ? (
          <div style={{ textAlign: 'center', padding: 120 }}>
            <Calendar size={48} className="animate-spin" style={{ margin: '0 auto 16px', opacity: 0.1, color: '#1a73e8' }} />
            <div style={{ color: '#70757a', fontSize: 14 }}>Sincronizando nómina...</div>
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 32 }}>
            {tab === 'nomina' && (
              <NominaView
                personal={personal}
                getLiquidacion={getLiquidacion}
                mes={mes}
                anio={anio}
                pagosNomina={pagosNomina}
                presupuestoNomina={presupuestoNomina}
                onReloadPagos={loadData}
                showToast={showToast}
                onEditPersonal={(p) => { setModalPersonal(p); setFormPersonal({ nombre: p.nombre, rol: typeof p.rol === 'string' ? p.rol.split(',') : p.rol, sueldo_base_cajero: p.sueldo_base_cajero || '', sueldo_base_planchero: p.sueldo_base_planchero || '', sueldo_base_admin: p.sueldo_base_admin || '', sueldo_base_seguridad: p.sueldo_base_seguridad || '', activo: p.activo }); }}
              />
            )}

            {tab === 'liquidacion' && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 48 }}>
                <LiquidacionView personal={personal} cajeros={cajeros} plancheros={plancheros} administradores={administradores} getLiquidacion={(p) => getLiquidacion(p, 'ruta11')} colores={COLORES} onAjuste={setModalAjuste} onDeleteAjuste={deleteAjuste} mes={mes} anio={anio} pagosNomina={pagosNomina.ruta11} onReloadPagos={loadData} showToast={showToast} presupuesto={presupuestoNomina.ruta11} onSavePresupuesto={(monto) => savePresupuesto(monto, 'ruta11')} centroCosto="ruta11" />
                <div style={{ background: 'white', borderRadius: 28, padding: 28, border: '1px solid #e3e3e3' }}>
                  <CalendarioView diasEnMes={diasEnMes} primerDiaLunes={primerDiaLunes} turnosPorFecha={turnosNoSeguridad} personal={personal} colores={COLORES} mes={mes} anio={anio} onAddTurno={(dia, fecha) => { setModalTurno({ dia, fecha }); setFormTurno({ personal_id: '', tipo: 'normal', reemplazado_por: '', monto_reemplazo: 20000, pago_por: 'empresa', fecha_fin: fecha }); }} onDeleteTurno={deleteTurno} />
                </div>
              </div>
            )}

            {tab === 'seguridad' && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 48 }}>
                <LiquidacionSeguridad guardias={guardias} getLiquidacion={(p) => getLiquidacion(p, 'seguridad')} colores={COLORES} onAjuste={setModalAjuste} onDeleteAjuste={deleteAjuste} mes={mes} anio={anio} pagosNomina={pagosNomina.seguridad} onReloadPagos={loadData} showToast={showToast} presupuesto={presupuestoNomina.seguridad} onSavePresupuesto={(monto) => savePresupuesto(monto, 'seguridad')} centroCosto="seguridad" />
                <CalendarioSeguridad diasEnMes={diasEnMes} primerDiaLunes={primerDiaLunes} turnosSeguridad={turnosSeguridad} personal={personal} mes={mes} anio={anio} onAddTurno={(params) => { setModalTurno(params); setFormTurno({ personal_id: params.titularId || '', tipo: 'reemplazo_seguridad', reemplazado_por: '', monto_reemplazo: 17966.666, pago_por: 'empresa', fecha_fin: params.fecha }); }} onDeleteTurno={deleteTurno} />
              </div>
            )}
          </div>
        )}
      </main>

      {/* MODALS */}
      {modalTurno && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.6)', backdropFilter: 'blur(4px)', zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}>
          <div style={{ background: 'white', borderRadius: 24, padding: 32, width: '100%', maxWidth: 420, boxShadow: '0 20px 60px rgba(0,0,0,0.2)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
              <h3 style={{ margin: 0, fontSize: 20, fontWeight: 600 }}>{modalTurno.isSeguridad ? 'Reemplazo Seguridad' : 'Agregar Turno'}</h3>
              <button onClick={() => setModalTurno(null)} style={{ border: 'none', background: 'transparent', cursor: 'pointer', color: '#70757a' }}>✕</button>
            </div>

            <div style={{ display: 'flex', gap: 12, marginBottom: 16 }}>
              <div style={{ flex: 1 }}>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 700, color: '#70757a', textTransform: 'uppercase', marginBottom: 6 }}>Desde</label>
                <input type="text" value={modalTurno.fecha} readOnly style={{ width: '100%', padding: '10px 14px', border: '1px solid #e3e3e3', borderRadius: 12, fontSize: 14, background: '#f8f9fa', color: '#70757a', outline: 'none' }} />
              </div>
              <div style={{ flex: 1 }}>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 700, color: '#70757a', textTransform: 'uppercase', marginBottom: 6 }}>Hasta</label>
                <input type="date" value={formTurno.fecha_fin} min={modalTurno.fecha} onChange={e => setFormTurno(f => ({ ...f, fecha_fin: e.target.value }))} style={{ width: '100%', padding: '10px 14px', border: '1px solid #1a73e8', borderRadius: 12, fontSize: 14, outline: 'none' }} />
              </div>
            </div>

            <div style={{ marginBottom: 16 }}>
              <label style={{ display: 'block', fontSize: 12, fontWeight: 700, color: '#70757a', textTransform: 'uppercase', marginBottom: 6 }}>Titular (Ausente)</label>
              <select value={formTurno.personal_id} onChange={e => setFormTurno(f => ({ ...f, personal_id: e.target.value }))}
                style={{ width: '100%', padding: '10px 14px', border: '1px solid #e3e3e3', borderRadius: 12, fontSize: 14, outline: 'none', background: modalTurno?.isSeguridad && formTurno.personal_id ? '#f8f9fa' : 'white' }}
                disabled={modalTurno?.isSeguridad && !!formTurno.personal_id}>
                <option value="">Seleccionar trabajador...</option>
                {personal.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
              </select>
            </div>

            {/* Tipo selector — solo para La Ruta 11 */}
            {!modalTurno?.isSeguridad && (
              <div style={{ marginBottom: 16 }}>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 700, color: '#70757a', textTransform: 'uppercase', marginBottom: 6 }}>Tipo de Turno</label>
                <div style={{ display: 'flex', gap: 8 }}>
                  <button type="button" onClick={() => setFormTurno(f => ({ ...f, tipo: 'normal', reemplazado_por: '' }))}
                    style={{ flex: 1, padding: '10px', borderRadius: 12, border: formTurno.tipo === 'normal' ? '2px solid #1a73e8' : '1px solid #e3e3e3', background: formTurno.tipo === 'normal' ? '#e8f0fe' : 'white', color: formTurno.tipo === 'normal' ? '#1a73e8' : '#444746', fontWeight: 600, fontSize: 13, cursor: 'pointer' }}>
                    📅 Normal
                  </button>
                  <button type="button" onClick={() => setFormTurno(f => ({ ...f, tipo: 'reemplazo' }))}
                    style={{ flex: 1, padding: '10px', borderRadius: 12, border: formTurno.tipo === 'reemplazo' ? '2px solid #ea580c' : '1px solid #e3e3e3', background: formTurno.tipo === 'reemplazo' ? '#fff7ed' : 'white', color: formTurno.tipo === 'reemplazo' ? '#ea580c' : '#444746', fontWeight: 600, fontSize: 13, cursor: 'pointer' }}>
                    ↔ Reemplazo
                  </button>
                </div>
              </div>
            )}

            {formTurno.tipo.includes('reemplazo') && (
              <div style={{ background: '#fef7ed', padding: 16, borderRadius: 16, marginBottom: 24, border: '1px solid #fdba74' }}>
                <div style={{ fontSize: 13, color: '#9a3412', marginBottom: 12 }}>Detalles del Reemplazo</div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                  <div>
                    <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#9a3412', textTransform: 'uppercase', marginBottom: 4 }}>¿Quién lo reemplaza? (Reemplazante)</label>
                    <select value={formTurno.reemplazado_por} onChange={e => setFormTurno(f => ({ ...f, reemplazado_por: e.target.value }))}
                      style={{ width: '100%', padding: '8px 12px', border: '1px solid #fdba74', borderRadius: 10, fontSize: 13, background: 'white' }}>
                      <option value="">Seleccionar...</option>
                      {personal.filter(p => p.id != formTurno.personal_id).map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                    </select>
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#9a3412', textTransform: 'uppercase', marginBottom: 4 }}>Monto del Reemplazo</label>
                    <input type="number" value={formTurno.monto_reemplazo} onChange={e => setFormTurno(f => ({ ...f, monto_reemplazo: e.target.value }))}
                      style={{ width: '100%', padding: '8px 12px', border: '1px solid #fdba74', borderRadius: 10, fontSize: 13, background: 'white', marginBottom: 8 }} />
                    <div style={{ display: 'flex', gap: 6 }}>
                      <button type="button" onClick={() => setFormTurno(f => ({ ...f, monto_reemplazo: 17966.666 }))} style={{ flex: 1, padding: '6px', fontSize: 11, fontWeight: 700, color: '#c2410c', background: '#ffedd5', border: '1px solid #fdba74', borderRadius: 8, cursor: 'pointer' }}>$17.967</button>
                      <button type="button" onClick={() => setFormTurno(f => ({ ...f, monto_reemplazo: 20000 }))} style={{ flex: 1, padding: '6px', fontSize: 11, fontWeight: 700, color: '#c2410c', background: '#ffedd5', border: '1px solid #fdba74', borderRadius: 8, cursor: 'pointer' }}>$20.000</button>
                      <button type="button" onClick={() => setFormTurno(f => ({ ...f, monto_reemplazo: 30000 }))} style={{ flex: 1, padding: '6px', fontSize: 11, fontWeight: 700, color: '#c2410c', background: '#ffedd5', border: '1px solid #fdba74', borderRadius: 8, cursor: 'pointer' }}>$30.000</button>
                    </div>
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#9a3412', textTransform: 'uppercase', marginBottom: 4 }}>¿Quién paga al reemplazante?</label>
                    <div style={{ display: 'flex', gap: 6 }}>
                      <button type="button" onClick={() => setFormTurno(f => ({ ...f, pago_por: 'empresa' }))}
                        style={{ flex: 1, padding: '8px 4px', fontSize: 11, fontWeight: 700, borderRadius: 8, cursor: 'pointer', border: formTurno.pago_por === 'empresa' ? '2px solid #2563eb' : '1px solid #d1d5db', background: formTurno.pago_por === 'empresa' ? '#dbeafe' : 'white', color: formTurno.pago_por === 'empresa' ? '#1d4ed8' : '#6b7280' }}>
                        📅 Fin de mes
                      </button>
                      <button type="button" onClick={() => setFormTurno(f => ({ ...f, pago_por: 'empresa_adelanto' }))}
                        style={{ flex: 1, padding: '8px 4px', fontSize: 11, fontWeight: 700, borderRadius: 8, cursor: 'pointer', border: formTurno.pago_por === 'empresa_adelanto' ? '2px solid #059669' : '1px solid #d1d5db', background: formTurno.pago_por === 'empresa_adelanto' ? '#d1fae5' : 'white', color: formTurno.pago_por === 'empresa_adelanto' ? '#065f46' : '#6b7280' }}>
                        💰 Adelanto
                      </button>
                      <button type="button" onClick={() => setFormTurno(f => ({ ...f, pago_por: 'titular' }))}
                        style={{ flex: 1, padding: '8px 4px', fontSize: 11, fontWeight: 700, borderRadius: 8, cursor: 'pointer', border: formTurno.pago_por === 'titular' ? '2px solid #d97706' : '1px solid #d1d5db', background: formTurno.pago_por === 'titular' ? '#fef3c7' : 'white', color: formTurno.pago_por === 'titular' ? '#92400e' : '#6b7280' }}>
                        🤝 Titular paga
                      </button>
                    </div>
                    <div style={{ fontSize: 10, color: '#78716c', marginTop: 4, fontStyle: 'italic' }}>
                      {formTurno.pago_por === 'empresa' && 'Se suma al reemplazante y se descuenta al titular a fin de mes'}
                      {formTurno.pago_por === 'empresa_adelanto' && 'Ya se pagó al reemplazante. Solo se descuenta al titular a fin de mes'}
                      {formTurno.pago_por === 'titular' && 'El titular pagó directo al reemplazante. Sin efecto en nómina'}
                    </div>
                  </div>
                </div>
              </div>
            )}

            <div style={{ display: 'flex', gap: 12, marginTop: 32 }}>
              <button onClick={() => setModalTurno(null)} style={{ flex: 1, padding: '12px', border: 'none', background: '#f1f3f4', borderRadius: 12, cursor: 'pointer', fontWeight: 600, color: '#444746' }}>Cancelar</button>
              <button onClick={saveTurno} disabled={saving || !formTurno.personal_id || (formTurno.tipo.includes('reemplazo') && !formTurno.reemplazado_por)} style={{ flex: 1, padding: '12px', border: 'none', background: '#1a73e8', borderRadius: 12, cursor: 'pointer', fontWeight: 600, color: 'white', opacity: (saving || !formTurno.personal_id || (formTurno.tipo.includes('reemplazo') && !formTurno.reemplazado_por)) ? 0.6 : 1 }}>
                {saving ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Personal */}
      {modalPersonal && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.6)', backdropFilter: 'blur(4px)', zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}>
          <div style={{ background: 'white', borderRadius: 24, padding: 32, width: '100%', maxWidth: 440, boxShadow: '0 20px 60px rgba(0,0,0,0.2)' }}>
            <h3 style={{ margin: '0 0 24px', fontSize: 20, fontWeight: 600 }}>{modalPersonal === 'new' ? 'Nueva Persona' : 'Editar Ficha'}</h3>
            {/* Form fields... simplified for brief replacement chunk */}
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 700, color: '#70757a', textTransform: 'uppercase', marginBottom: 6 }}>Nombre Completo</label>
                <input type="text" value={formPersonal.nombre} onChange={e => setFormPersonal(f => ({ ...f, nombre: e.target.value }))}
                  style={{ width: '100%', padding: '10px 14px', border: '1px solid #e3e3e3', borderRadius: 12, fontSize: 14 }} />
              </div>
              {/* Roles checkboxes... */}
            </div>
            <div style={{ display: 'flex', gap: 12, marginTop: 40 }}>
              <button onClick={() => setModalPersonal(null)} style={{ flex: 1, padding: '12px', border: 'none', background: '#f1f3f4', borderRadius: 12, cursor: 'pointer', fontWeight: 600 }}>Cancelar</button>
              <button onClick={savePersonal} style={{ flex: 1, padding: '12px', border: 'none', background: '#1a73e8', borderRadius: 12, cursor: 'pointer', fontWeight: 600, color: 'white' }}>Guardar</button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Ajuste */}
      {modalAjuste && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.6)', backdropFilter: 'blur(4px)', zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}>
          <div style={{ background: 'white', borderRadius: 24, padding: 32, width: '100%', maxWidth: 440, boxShadow: '0 20px 60px rgba(0,0,0,0.2)' }}>
            <h3 style={{ margin: '0 0 8px', fontSize: 20, fontWeight: 600 }}>Cargar Ajuste</h3>
            <p style={{ margin: '0 0 24px', fontSize: 13, color: '#64748b' }}>Para {modalAjuste.nombre}</p>

            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 700, color: '#70757a', textTransform: 'uppercase', marginBottom: 6 }}>Motivo / Concepto Rápido</label>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 8 }}>
                  <button type="button" onClick={() => setFormAjuste(f => ({ ...f, concepto: 'Adelanto de sueldo', tipo: '-' }))} style={{ padding: '8px 12px', borderRadius: 20, border: '1px solid #e2e8f0', background: formAjuste.concepto === 'Adelanto de sueldo' ? '#fee2e2' : 'white', color: formAjuste.concepto === 'Adelanto de sueldo' ? '#b91c1c' : '#475569', fontSize: 13, fontWeight: 600, cursor: 'pointer' }}>Adelanto de sueldo</button>
                  <button type="button" onClick={() => setFormAjuste(f => ({ ...f, concepto: 'Descuento por pérdida', tipo: '-' }))} style={{ padding: '8px 12px', borderRadius: 20, border: '1px solid #e2e8f0', background: formAjuste.concepto === 'Descuento por pérdida' ? '#fee2e2' : 'white', color: formAjuste.concepto === 'Descuento por pérdida' ? '#b91c1c' : '#475569', fontSize: 13, fontWeight: 600, cursor: 'pointer' }}>Descuento por pérdida</button>
                  <button type="button" onClick={() => setFormAjuste(f => ({ ...f, concepto: 'Bono extra', tipo: '+' }))} style={{ padding: '8px 12px', borderRadius: 20, border: '1px solid #e2e8f0', background: formAjuste.concepto === 'Bono extra' ? '#dcfce7' : 'white', color: formAjuste.concepto === 'Bono extra' ? '#15803d' : '#475569', fontSize: 13, fontWeight: 600, cursor: 'pointer' }}>Bono extra</button>
                </div>
                <input type="text" value={formAjuste.concepto} onChange={e => setFormAjuste(f => ({ ...f, concepto: e.target.value }))} placeholder="O escribe otro concepto..."
                  style={{ width: '100%', padding: '10px 14px', border: '1px solid #e3e3e3', borderRadius: 12, fontSize: 14 }} />
              </div>

              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 700, color: '#70757a', textTransform: 'uppercase', marginBottom: 6 }}>Dirección del ajuste</label>
                <div style={{ display: 'flex', gap: 8 }}>
                  <button type="button" onClick={() => setFormAjuste(f => ({ ...f, tipo: '-' }))} style={{ flex: 1, padding: '10px', borderRadius: 12, border: formAjuste.tipo === '-' ? '2px solid #ef4444' : '1px solid #e3e3e3', background: formAjuste.tipo === '-' ? '#fef2f2' : 'white', color: formAjuste.tipo === '-' ? '#b91c1c' : '#444746', fontWeight: 700, cursor: 'pointer' }}>➖ Descontar Saldo</button>
                  <button type="button" onClick={() => setFormAjuste(f => ({ ...f, tipo: '+' }))} style={{ flex: 1, padding: '10px', borderRadius: 12, border: formAjuste.tipo === '+' ? '2px solid #10b981' : '1px solid #e3e3e3', background: formAjuste.tipo === '+' ? '#ecfdf5' : 'white', color: formAjuste.tipo === '+' ? '#047857' : '#444746', fontWeight: 700, cursor: 'pointer' }}>➕ Sumar Saldo</button>
                </div>
              </div>

              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 700, color: '#70757a', textTransform: 'uppercase', marginBottom: 6 }}>Cantidad (sin signos)</label>
                <div style={{ position: 'relative' }}>
                  <span style={{ position: 'absolute', left: 14, top: '50%', transform: 'translateY(-50%)', fontWeight: 800, fontSize: 18, color: formAjuste.tipo === '-' ? '#ef4444' : '#10b981' }}>{formAjuste.tipo} $</span>
                  <input type="number" min="0" value={formAjuste.monto} onChange={e => setFormAjuste(f => ({ ...f, monto: Math.abs(parseFloat(e.target.value) || 0) || '' }))} placeholder="0"
                    style={{ width: '100%', padding: '12px 14px 12px 42px', border: '1px solid #e3e3e3', borderRadius: 12, fontSize: 18, fontWeight: 700 }} />
                </div>
                {formAjuste.monto && (
                  <div style={{ fontSize: 12, color: formAjuste.tipo === '-' ? '#ef4444' : '#10b981', marginTop: 4, fontWeight: 600 }}>
                    Se va a {formAjuste.tipo === '-' ? 'descontar' : 'sumar'} ${parseFloat(formAjuste.monto).toLocaleString('es-CL')}
                  </div>
                )}
              </div>

              <div>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 700, color: '#70757a', textTransform: 'uppercase', marginBottom: 6 }}>Notas Adicionales (Opcional)</label>
                <input type="text" value={formAjuste.notas || ''} onChange={e => setFormAjuste(f => ({ ...f, notas: e.target.value }))} placeholder="Detalles extra, motivos..."
                  style={{ width: '100%', padding: '10px 14px', border: '1px solid #e3e3e3', borderRadius: 12, fontSize: 14 }} />
              </div>
            </div>

            <div style={{ display: 'flex', gap: 12, marginTop: 32 }}>
              <button onClick={() => { setModalAjuste(null); setFormAjuste({ monto: '', concepto: '', notas: '', tipo: '-' }); }} style={{ flex: 1, padding: '12px', border: 'none', background: '#f1f3f4', borderRadius: 12, cursor: 'pointer', fontWeight: 600, color: '#444746' }}>Cancelar</button>
              <button onClick={saveAjuste} disabled={saving || !formAjuste.monto || !formAjuste.concepto} style={{ flex: 1, padding: '12px', border: 'none', background: '#1a73e8', borderRadius: 12, cursor: 'pointer', fontWeight: 600, color: 'white', opacity: (saving || !formAjuste.monto || !formAjuste.concepto) ? 0.6 : 1 }}>
                {saving ? 'Guardando...' : 'Guardar Ajuste'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Toast Notification */}
      {toast && (
        <div style={{
          position: 'fixed', bottom: 32, left: '50%', transform: 'translateX(-50%)',
          background: toast.type === 'error' ? '#d93025' : '#202124',
          color: 'white', padding: '12px 24px', borderRadius: 12, fontSize: 14, fontWeight: 500,
          boxShadow: '0 4px 12px rgba(0,0,0,0.3)', zIndex: 2000,
          display: 'flex', alignItems: 'center', gap: 8, animation: 'slideUp 0.3s ease-out'
        }}>
          {toast.type === 'error' ? <AlertCircle size={18} /> : <ShieldCheck size={18} />}
          {toast.msg}
        </div>
      )}
    </div>
  );
}

// --- SUBRECUROS Y COMPONENTES ---

function NominaView({ personal, getLiquidacion, mes, anio, pagosNomina, presupuestoNomina, onReloadPagos, showToast, onEditPersonal }) {
  const [modalEmail, setModalEmail] = useState(null);
  const [copiedGlobal, setCopiedGlobal] = useState(false);

  const allData = personal.filter(p => p.activo == 1).map(p => {
    const lRuta11 = getLiquidacion(p, 'ruta11');
    const lSeguridad = getLiquidacion(p, 'seguridad');

    const pago11 = pagosNomina.ruta11.find(x => x.personal_id == p.id);
    const pagoSeg = pagosNomina.seguridad.find(x => x.personal_id == p.id);

    const lAll = getLiquidacion(p, 'all');

    return {
      persona: p,
      total11: lRuta11.total,
      totalSeg: lSeguridad.total,
      granTotal: lAll.total,
      granCosto: lAll.costoEmpresa,
      granAdelantos: lAll.montoAdelantos,
      pagado11: !!pago11,
      pagadoSeg: !!pagoSeg,
      totalPagado: (pago11 ? parseFloat(pago11.monto) : 0) + (pagoSeg ? parseFloat(pagoSeg.monto) : 0)
    };
  }).sort((a, b) => b.granTotal - a.granTotal);

  function copiarResumenGlobal() {
    const MESES_L = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const emojis = { 'Ricardo': '🤖', 'Andrés': '🧑🏻', 'Andres': '🧑🏻', 'Camila': '👩🏽', 'Neit': '👩🏻', 'Gabriel': '🧑🏿', 'Claudio': '🧓🏽' };

    let md = `🏦 *RESUMEN GLOBAL PAGOS*\n📅 _${MESES_L[mes] ? MESES_L[mes].toUpperCase() : ''} ${anio}_\n━━━━━━━━━━━━\n`;
    let sum = 0;
    const items = allData.filter(item => item.granTotal > 0);
    const montosStr = items.map(item => `$${item.granTotal.toLocaleString('es-CL')}`);
    const maxLen = Math.max(...montosStr.map(s => s.length), 0);

    items.forEach((item, idx) => {
      const nombre = item.persona.nombre;
      const primerNombre = nombre.split(' ')[0];
      const emoji = emojis[nombre] || emojis[primerNombre] || '👤';
      const montoPad = montosStr[idx].padStart(maxLen, ' ');
      md += `- ${emoji} _${nombre.toUpperCase()}:_ \`\`\`${montoPad}\`\`\`\n`;
      sum += item.granTotal;
    });
    md += `━━━━━━━━━━━━\n💰 *Total a Transferir:* \`\`\`$${sum.toLocaleString('es-CL')}\`\`\`\n\n🔗 *DETALLES:* https://caja.laruta11.cl/personal/`;

    navigator.clipboard.writeText(md).then(() => {
      setCopiedGlobal(true);
      setTimeout(() => setCopiedGlobal(false), 2500);
      showToast('Copiado global para WhatsApp');
    });
  }

  const stats = {
    totalAPagar: allData.reduce((s, i) => s + i.granTotal, 0),
    totalPagado: allData.reduce((s, i) => s + i.totalPagado, 0),
    totalCosto: allData.reduce((s, i) => s + i.granCosto, 0),
    totalAdelantos: allData.reduce((s, i) => s + i.granAdelantos, 0),
    presupuesto: (presupuestoNomina.ruta11 || 0) + (presupuestoNomina.seguridad || 0)
  };

  const saldoGlobal = stats.presupuesto - (stats.totalPagado + stats.totalAdelantos);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 24, animation: 'fadeIn 0.4s ease-out' }}>
      {/* Global Header Similar to Ruta 11 */}
      <div style={{ background: 'linear-gradient(135deg, #1e40af, #3b82f6)', borderRadius: 24, padding: '24px 28px', color: 'white', border: '1px solid rgba(255,255,255,0.1)', boxShadow: '0 12px 32px rgba(30,64,175,0.2)' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div style={{ background: 'rgba(255,255,255,0.2)', padding: 10, borderRadius: 14 }}>
              <Users size={24} />
            </div>
            <div>
              <h2 style={{ margin: 0, fontSize: 18, fontWeight: 700, letterSpacing: '-0.3px' }}>Resumen Nómina General</h2>
              <div style={{ fontSize: 13, opacity: 0.8 }}>Febrero 2026 · {allData.length} Trabajadores Activos</div>
            </div>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button onClick={copiarResumenGlobal} style={{ padding: '10px 18px', borderRadius: 12, border: '1px solid rgba(255,255,255,0.3)', background: copiedGlobal ? '#4ade80' : 'rgba(255,255,255,0.15)', color: 'white', fontSize: 13, fontWeight: 700, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 8, transition: 'all 0.2s' }}>
              {copiedGlobal ? <Check size={16} /> : <DollarSign size={16} />} {copiedGlobal ? 'Copiado!' : 'Solo Nombres y Totales'}
            </button>
            <button onClick={() => setModalEmail({ type: 'massive' })} style={{ padding: '10px 18px', borderRadius: 12, border: '1px solid rgba(255,255,255,0.3)', background: 'rgba(255,255,255,0.15)', color: 'white', fontSize: 13, fontWeight: 700, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 8, transition: 'all 0.2s' }}>
              <Mail size={16} /> Notificar Masivo
            </button>
          </div>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: 20 }}>
          <div>
            <div style={{ fontSize: 12, opacity: 0.7, marginBottom: 4 }}>Presupuesto Global</div>
            <div style={{ fontSize: 24, fontWeight: 900 }}>${stats.presupuesto.toLocaleString('es-CL')}</div>
          </div>
          <div>
            <div style={{ fontSize: 12, opacity: 0.7, marginBottom: 4 }}>Pagos realizados</div>
            <div style={{ fontSize: 24, fontWeight: 900 }}>${(stats.totalPagado + stats.totalAdelantos).toLocaleString('es-CL')}</div>
            <div style={{ fontSize: 10, opacity: 0.7 }}>Comprometido: ${stats.totalCosto.toLocaleString('es-CL')}</div>
          </div>
          <div>
            <div style={{ fontSize: 12, opacity: 0.7, marginBottom: 4 }}>{saldoGlobal < 0 ? 'Exceso' : 'Saldo presupuesto'}</div>
            <div style={{ fontSize: 24, fontWeight: 900, color: saldoGlobal < 0 ? '#fca5a5' : '#4ade80' }}>
              ${Math.abs(saldoGlobal).toLocaleString('es-CL')}
            </div>
            <div style={{ fontSize: 10, opacity: 0.7 }}>{saldoGlobal < 0 ? 'Sobre el límite' : 'Disponible'}</div>
          </div>
        </div>
      </div>

      {/* Nomina Cards */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        {allData.map(item => (
          <NominaCard
            key={item.persona.id}
            item={item}
            colorObj={COLORES[item.persona.id] || COLORES[((item.persona.id % 8) + 1)]}
            onNotify={() => setModalEmail({ type: 'individual', persona: item.persona, total: item.granTotal })}
            onEdit={() => onEditPersonal(item.persona)}
          />
        ))}
      </div>



      {modalEmail && <EmailPreviewModal config={modalEmail} onClose={() => setModalEmail(null)} mes={mes} anio={anio} />}
    </div>
  );
}

function StatCard({ title, value, icon, color }) {
  return (
    <div style={{ background: 'white', borderRadius: 20, padding: 24, border: '1px solid #e3e3e3', display: 'flex', alignItems: 'center', gap: 16 }}>
      <div style={{ background: `${color}15`, color: color, padding: 12, borderRadius: 16, display: 'flex' }}>
        {icon}
      </div>
      <div>
        <div style={{ fontSize: 13, color: '#70757a', marginBottom: 4 }}>{title}</div>
        <div style={{ fontSize: 20, fontWeight: 600, color: '#1f1f1f' }}>
          ${Math.round(value).toLocaleString('es-CL')}
        </div>
      </div>
    </div>
  );
}

function NominaCard({ item, colorObj, onNotify, onEdit }) {
  const c = colorObj || { bg: '#1a73e8', text: '#1a73e8', light: '#f8fafd' };

  return (
    <div style={{
      background: 'white',
      borderRadius: 16,
      padding: '16px',
      border: `1px solid ${c.border || '#e3e3e3'}`,
      boxShadow: '0 4px 12px rgba(0,0,0,0.04)',
      display: 'flex',
      flexDirection: 'column',
      gap: 16,
      transition: 'transform 0.2s',
      cursor: 'pointer'
    }} onClick={onEdit}>

      {/* Header Profile + Actions */}
      <div className="nomina-card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, minWidth: 0 }}>
          <div className="nomina-card-avatar" style={{ width: 44, height: 44, borderRadius: '50%', background: c.bg, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 18, fontWeight: 700, color: 'white', flexShrink: 0 }}>
            {item.persona.nombre[0]}
          </div>
          <div style={{ minWidth: 0, flex: 1 }}>
            <div className="nomina-card-name" style={{ fontSize: 16, fontWeight: 700, color: '#1f1f1f', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{item.persona.nombre}</div>
            <div className="nomina-card-rol" style={{ fontSize: 12, color: c.text, fontWeight: 600, textTransform: 'capitalize', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
              {Array.isArray(item.persona.rol) ? item.persona.rol.join(', ') : item.persona.rol}
            </div>
          </div>
        </div>
        <div className="nomina-card-actions" style={{ display: 'flex', gap: 8 }}>
          <button onClick={(e) => { e.stopPropagation(); onNotify(); }} style={{ border: 'none', background: c.light, color: c.text, padding: '8px', borderRadius: 8, cursor: 'pointer', display: 'flex' }} title="Notificar Pago">
            <Mail size={18} />
          </button>
        </div>
      </div>

      {/* Grid for Details */}
      <div className="nomina-card-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(110px, 1fr))', gap: 16, background: '#f8fafd', padding: 12, borderRadius: 12 }}>
        <div>
          <div style={{ fontSize: 10, color: '#70757a', textTransform: 'uppercase', letterSpacing: 0.5 }}>Ruta 11</div>
          <div style={{ fontSize: 14, fontWeight: 700, color: item.pagado11 ? '#1e8e3e' : '#1f1f1f' }}>{item.total11 > 0 ? `$${item.total11.toLocaleString('es-CL')}` : '—'}</div>
        </div>
        <div>
          <div style={{ fontSize: 10, color: '#70757a', textTransform: 'uppercase', letterSpacing: 0.5 }}>Seguridad</div>
          <div style={{ fontSize: 14, fontWeight: 700, color: item.pagadoSeg ? '#1e8e3e' : '#1f1f1f' }}>{item.totalSeg > 0 ? `$${item.totalSeg.toLocaleString('es-CL')}` : '—'}</div>
        </div>
        <div>
          <div style={{ fontSize: 10, color: c.text, textTransform: 'uppercase', fontWeight: 800 }}>A Pagar</div>
          <div style={{ fontSize: 16, fontWeight: 800, color: c.text }}>${item.granTotal.toLocaleString('es-CL')}</div>
        </div>
      </div>
    </div>
  );
}

function EmailPreviewModal({ config, onClose, mes, anio }) {
  const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

  return (
    <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.6)', backdropFilter: 'blur(8px)', zIndex: 2000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}>
      <div style={{ background: '#f8f9fa', borderRadius: 28, width: '100%', maxWidth: 600, maxHeight: '90vh', overflow: 'hidden', display: 'flex', flexDirection: 'column', boxShadow: '0 24px 64px rgba(0,0,0,0.4)' }}>
        <header style={{ padding: '24px 32px', background: 'white', borderBottom: '1px solid #e3e3e3', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <h3 style={{ margin: 0, fontSize: 18, fontWeight: 600 }}>Vista Previa de Notificación</h3>
            <p style={{ margin: '4px 0 0', fontSize: 13, color: '#70757a' }}>{config.type === 'massive' ? 'Envío masivo a todo el personal activo' : `Enviando a ${config.persona.nombre}`}</p>
          </div>
          <button onClick={onClose} style={{ border: 'none', background: 'transparent', cursor: 'pointer', padding: 8 }}>✕</button>
        </header>

        <div style={{ flex: 1, padding: 32, overflowY: 'auto' }}>
          <div style={{ background: 'white', borderRadius: 16, padding: 32, border: '1px solid #e3e3e3', boxShadow: '0 4px 12px rgba(0,0,0,0.05)' }}>
            {/* Email Body Mockup */}
            <div style={{ textAlign: 'center', marginBottom: 32 }}>
              <img src="/11.png" style={{ width: 48, marginBottom: 16 }} />
              <h2 style={{ fontSize: 22, fontWeight: 700, margin: 0 }}>Comprobante de Pago</h2>
              <p style={{ color: '#70757a' }}>Nómina {MESES[mes]} {anio}</p>
            </div>

            <p style={{ fontSize: 16, lineHeight: 1.6 }}>Hola <strong>{config.type === 'massive' ? '[Nombre Trabajador]' : config.persona.nombre}</strong>,</p>
            <p style={{ fontSize: 16, lineHeight: 1.6 }}>Te informamos que tu liquidación correspondiente al mes de <strong>{MESES[mes]}</strong> ha sido procesada con éxito.</p>

            <div style={{ background: '#f8fafd', borderRadius: 16, padding: 24, margin: '24px 0', border: '1px dashed #1a73e8' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 8 }}>
                <span style={{ color: '#70757a' }}>Monto total transferido:</span>
                <span style={{ fontWeight: 700, color: '#1a73e8', fontSize: 18 }}>{config.type === 'massive' ? '$[Monto Total]' : new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(config.total)}</span>
              </div>
              <div style={{ fontSize: 12, color: '#70757a' }}>* El detalle desglosado está disponible en tu panel personal.</div>
            </div>

            <p style={{ fontSize: 14, color: '#70757a', textAlign: 'center', marginTop: 32, borderTop: '1px solid #f1f3f4', paddingTop: 24 }}>
              Gracias por tu compromiso con <strong>La Ruta 11</strong>.<br />
              <em>Este es un correo automático, por favor no respondas.</em>
            </p>
          </div>
        </div>

        <footer style={{ padding: '24px 32px', background: 'white', borderTop: '1px solid #e3e3e3', display: 'flex', justifyContent: 'flex-end', gap: 12 }}>
          <button onClick={onClose} style={{ padding: '12px 24px', border: 'none', background: '#f1f3f4', borderRadius: 14, fontWeight: 600, cursor: 'pointer' }}>Cerrar</button>
          <button style={{ padding: '12px 32px', border: 'none', background: '#1a73e8', color: 'white', borderRadius: 14, fontWeight: 600, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 10 }}>
            <Send size={18} /> {config.type === 'massive' ? 'Enviar a todos' : 'Enviar ahora'}
          </button>
        </footer>
      </div>
    </div>
  );
}


function CalendarioView({ diasEnMes, primerDiaLunes, turnosPorFecha, personal, colores, mes, anio, onAddTurno, onDeleteTurno }) {
  const DIAS_LABEL = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
  const MESES_FULL = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  const celdas = [];
  for (let i = 0; i < primerDiaLunes; i++) celdas.push(null);
  for (let d = 1; d <= diasEnMes; d++) celdas.push(d);

  const activos = personal.filter(p => p.activo == 1 && !p.rol?.includes('seguridad'));
  const hoy = new Date();
  const hoyDia = hoy.getMonth() === mes && hoy.getFullYear() === anio ? hoy.getDate() : -1;

  return (
    <div className="cal-container" style={{ background: '#f8fafd', border: '1px solid #e3e3e3', margin: '-28px' /* Negating parent padding to make it flush */ }}>
      {/* Header Google Calendar Style */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 24, flexWrap: 'wrap', gap: 12 }}>
        <div>
          <h2 style={{ margin: 0, fontSize: 22, fontWeight: 400, color: '#1f1f1f', letterSpacing: '-0.3px', display: 'flex', alignItems: 'center', gap: 8 }}><Store size={22} style={{ color: '#1a73e8' }} /> {MESES_FULL[mes]} {anio}</h2>
          <p style={{ margin: '4px 0 0', color: '#70757a', fontSize: 13, fontWeight: 500 }}>Turnos Fijos y Reemplazos · La Ruta 11</p>
        </div>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {activos.map(p => {
            const c = colores[p.id] || { bg: '#cbd5e1', light: '#f8fafc', border: '#e2e8f0', text: '#475569' };
            return (
              <div key={p.id} style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 14px', borderRadius: 20, background: 'white', border: '1px solid #e3e3e3', boxShadow: '0 1px 2px rgba(0,0,0,0.04)' }}>
                <div style={{ width: 10, height: 10, borderRadius: '50%', background: c.bg }} />
                <span style={{ fontSize: 13, fontWeight: 600, color: '#444746' }}>{p.nombre}</span>
              </div>
            );
          })}
        </div>
      </div>

      <style>{`
        .cal-container { border-radius: 28px; padding: 28px; }
        .month-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
        .grid-cell { background: white; border-radius: 12px; min-height: 100px; padding: 6px; display: flex; flex-direction: column; transition: all 0.2s ease; border: 1px solid #e3e3e380; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .grid-cell.hoy { border: 2px solid #1a73e8; box-shadow: 0 4px 16px rgba(26,115,232,0.12); }
        .grid-cell.empty { background: transparent; border: none; box-shadow: none; min-height: auto; }
        
        .header-day { text-align: center; font-size: 11px; font-weight: 800; color: #70757a; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 0; }
        .day-num { font-size: 13px; font-weight: 700; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: #444746; }
        .day-num.hoy-num { background: #1a73e8; color: white; }
        
        .guard-pill { display: flex; justify-content: center; color: white; border-radius: 8px; padding: 3px 6px; font-size: 10px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 3px; }
        .repl-pill { display: flex; align-items: center; justify-content: space-between; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 3px 6px; font-size: 10px; font-weight: 700; color: #c2410c; margin-bottom: 3px; }
        .repl-btn { background: none; border: none; cursor: pointer; color: #ef4444; font-size: 12px; font-weight: 800; padding: 0; margin-left: 2px; line-height: 1; }
        .normal-btn { background: none; border: none; cursor: pointer; color: inherit; font-size: 12px; font-weight: 800; padding: 0; margin-left: 2px; line-height: 1; }
        .add-btn { font-size: 14px; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: none; background: transparent; cursor: pointer; color: #70757a; font-weight: 700; opacity: 0.5; padding: 0; }

        @media (max-width: 450px) {
          .cal-container { padding: 14px 4px; border-radius: 12px; margin: -28px !important; }
          .month-grid { gap: 2px; }
          .grid-cell { padding: 2px; min-height: 60px; border-radius: 6px; }
          .header-day { font-size: 9px; padding: 4px 0; }
          .day-num { width: 18px; height: 18px; font-size: 10px; }
          .guard-pill { padding: 1.5px 2px; font-size: 8.5px; border-radius: 4px; margin-bottom: 2px; letter-spacing: -0.3px; }
          .repl-pill { padding: 1.5px 2px; font-size: 8.5px; border-radius: 4px; margin-bottom: 2px; letter-spacing: -0.3px; }
          .repl-btn, .normal-btn { font-size: 10px; }
          .add-btn { width: 16px; height: 16px; font-size: 12px; }
          .mobile-short-name { max-width: 100%; display: inline-block; overflow: hidden; text-overflow: ellipsis; }
        }
      `}</style>

      {/* Day headers */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', marginBottom: 8 }}>
        {DIAS_LABEL.map(d => (
          <div key={d} className="header-day">{d}</div>
        ))}
      </div>

      {/* Card grid */}
      <div className="month-grid">
        {celdas.map((dia, i) => {
          const trabajando = dia ? (turnosPorFecha[dia] || []) : [];
          const fecha = dia ? `${anio}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}` : '';
          const esHoy = dia === hoyDia;

          return (
            <div key={i} className={`grid-cell ${dia ? '' : 'empty'} ${esHoy ? 'hoy' : ''}`}>
              {dia && (
                <>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
                    <span className={`day-num ${esHoy ? 'hoy-num' : ''}`}>{dia}</span>
                    <button className="add-btn" onClick={() => onAddTurno(dia, fecha)} title="Agregar Turno">+</button>
                  </div>

                  <div style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
                    {trabajando.map(t => {
                      const p = personal.find(x => x.id == t.personal_id);
                      if (!p) return null;
                      const c = colores[p.id];

                      if (t.tipo.includes('reemplazo')) {
                        const replacer = personal.find(x => x.id == t.reemplazado_por);
                        if (!replacer) return null;
                        return (
                          <div key={t.id} className="repl-pill">
                            <span className="mobile-short-name">
                              {replacer.nombre[0]}↔{p.nombre[0]}
                            </span>
                            <button onClick={() => onDeleteTurno(t.id)} className="repl-btn">×</button>
                          </div>
                        );
                      }

                      // Normal shift pill
                      return (
                        <div key={t.id} className="guard-pill" style={{ background: c?.bg, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                          <span className="mobile-short-name">{p.nombre.slice(0, 4)}.</span>
                          <button onClick={() => onDeleteTurno(t.id)} className="normal-btn">×</button>
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
    let md = `*Liquidación Nómina — ${mesLabel}*\n━━━━━━━━━━━━━━━━━━━━\n`;
    personal.forEach(p => {
      const { diasTrabajados, sueldoBase, ajustesPer, gruposReemplazando, gruposReemplazados, total } = getLiquidacion(p);
      let roles = p.rol;
      if (typeof p.rol === 'string') {
        roles = p.rol.split(',').map(r => r.trim()).join(', ');
      }
      md += `\n*${p.nombre.toUpperCase()}*\n_${roles}_\n▪ *Días:* ${diasTrabajados}\n▪ *Base:* $${sueldoBase.toLocaleString('es-CL')}\n`;
      Object.values(gruposReemplazando).forEach(g => { md += `▪ *Reemplazó a ${g.persona?.nombre ?? '?'}* (días ${g.dias.sort((a, b) => a - b).join(',')}): +$${g.monto.toLocaleString('es-CL')}\n`; });
      Object.values(gruposReemplazados).forEach(g => { md += `▪ *${g.persona?.nombre ?? '?'} cubrió días* ${g.dias.sort((a, b) => a - b).join(',')}: -$${g.monto.toLocaleString('es-CL')}\n`; });
      ajustesPer.forEach(a => {
        const m = parseFloat(a.monto);
        md += `▪ ${a.concepto}: *${m < 0 ? '-' : '+'}$${Math.abs(m).toLocaleString('es-CL')}*\n`;
      });
      md += `> *Total a Pagar:* $${total.toLocaleString('es-CL')}*\n`;
    });
    if (pagosNomina.length > 0) {
      const tp = pagosNomina.reduce((s, p) => s + parseFloat(p.monto), 0);
      md += `\n━━━━━━━━━━━━━━━━━━━━\n*RESUMEN PAGOS REALIZADOS*\n`;
      pagosNomina.forEach(p => { md += `• ${p.nombre}${p.es_externo ? ' (ext)' : ''}: *$${parseFloat(p.monto).toLocaleString('es-CL')}*\n`; });
      md += `\n*Total Pagado:* $${tp.toLocaleString('es-CL')}\n*Presupuesto:* $${presupuesto.toLocaleString('es-CL')}\n`;
    }
    return md;
  }

  function copiarMarkdown() {
    navigator.clipboard.writeText(generarMarkdown()).then(() => { setCopied(true); setTimeout(() => setCopied(false), 2500); });
  }

  function generarResumenPagos() {
    const emojis = { 'Ricardo': '🤖', 'Andrés': '🧑🏻', 'Andres': '🧑🏻', 'Camila': '👩🏽', 'Neit': '👩🏻', 'Gabriel': '🧑🏿', 'Claudio': '🧓🏽' };
    const mesLabel = `${MESES_L[mes]} ${anio}`;
    let md = `🏦 *RESUMEN PAGOS NÓMINA*\n📅 _${mesLabel.toUpperCase()}_\n━━━━━━━━━━━━\n`;
    let sum = 0;
    const items = personal.filter(p => getLiquidacion(p).total > 0);
    const montosStr = items.map(p => `$${getLiquidacion(p).total.toLocaleString('es-CL')}`);
    const maxLen = Math.max(...montosStr.map(s => s.length), 0);

    items.forEach((p, idx) => {
      const { total } = getLiquidacion(p);
      const emoji = emojis[p.nombre] || emojis[p.nombre.split(' ')[0]] || '👤';
      const montoPad = montosStr[idx].padStart(maxLen, ' ');
      md += `- ${emoji} _${p.nombre.toUpperCase()}:_ \`\`\`${montoPad}\`\`\`\n`;
      sum += total;
    });
    md += `━━━━━━━━━━━━\n💰 *Total a Transferir:* \`\`\`$${sum.toLocaleString('es-CL')}\`\`\`\n\n🔗 *DETALLES:* https://caja.laruta11.cl/personal/`;
    return md;
  }

  const [copiedResumen, setCopiedResumen] = useState(false);
  function copiarResumenPagos() {
    navigator.clipboard.writeText(generarResumenPagos()).then(() => { setCopiedResumen(true); setTimeout(() => setCopiedResumen(false), 2500); });
  }

  const totalCalculado = personal.reduce((s, p) => s + getLiquidacion(p).costoEmpresa, 0);
  const totalAdelantos = personal.reduce((s, p) => s + getLiquidacion(p).montoAdelantos, 0);
  const totalPagado = pagosNomina.reduce((s, p) => s + parseFloat(p.monto), 0);
  const saldo = presupuesto - (totalPagado + totalAdelantos);
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
          centro_costo: centroCosto,
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
        <div style={{ fontSize: 13, opacity: 0.7, marginBottom: 12, display: 'flex', alignItems: 'center', gap: 8 }}>
          <DollarSign size={16} /> Centro de Costos — Nómina {MESES_L[mes]} {anio}
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 16 }}>
          <div>
            <div style={{ fontSize: 12, opacity: 0.6 }}>Presupuesto</div>
            {editPresupuesto
              ? <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                <input type='number' value={presupuestoInput} onChange={e => setPresupuestoInput(e.target.value)}
                  style={{ width: 120, padding: '4px 8px', borderRadius: 6, border: 'none', fontSize: 16, fontWeight: 700, color: '#1e293b', background: 'white' }} />
                <button onClick={() => { onSavePresupuesto(parseFloat(presupuestoInput)); setEditPresupuesto(false); }} style={{ padding: '6px', borderRadius: 8, border: 'none', background: '#4ade80', color: '#065f46', cursor: 'pointer', display: 'flex' }}>
                  <Check size={16} />
                </button>
                <button onClick={() => setEditPresupuesto(false)} style={{ padding: '6px', borderRadius: 8, border: 'none', background: 'rgba(255,255,255,0.2)', color: 'white', cursor: 'pointer', display: 'flex' }}>
                  <X size={16} />
                </button>
              </div>
              : <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <div style={{ fontSize: 22, fontWeight: 800 }}>${presupuesto.toLocaleString('es-CL')}</div>
                <button onClick={() => { setPresupuestoInput(presupuesto); setEditPresupuesto(true); }} style={{ padding: '6px', borderRadius: 8, border: 'none', background: 'rgba(255,255,255,0.2)', color: 'white', cursor: 'pointer', display: 'flex' }}>
                  <Edit size={14} />
                </button>
              </div>
            }
          </div>
          <div>
            <div style={{ fontSize: 12, opacity: 0.6 }}>Pagos realizados</div>
            <div style={{ fontSize: 22, fontWeight: 800 }}>${(totalPagado + totalAdelantos).toLocaleString('es-CL')}</div>
            <div style={{ fontSize: 10, opacity: 0.6 }}>Calculado: ${totalCalculado.toLocaleString('es-CL')}</div>
          </div>
          <div>
            <div style={{ fontSize: 12, opacity: 0.6 }}>{saldo < 0 ? 'Exceso' : 'Saldo presupuesto'}</div>
            <div style={{ fontSize: 22, fontWeight: 800, color: saldo < 0 ? '#f87171' : '#4ade80' }}>
              ${Math.abs(saldo).toLocaleString('es-CL')}
            </div>
            <div style={{ fontSize: 10, opacity: 0.6 }}>{saldo < 0 ? 'Sobre el presupuesto' : 'Disponible'}</div>
          </div>
        </div>
        <div style={{ display: 'flex', gap: 12, marginTop: 4 }}>
          <button onClick={copiarMarkdown} style={{ flex: 1, padding: '10px 16px', background: copied ? '#4ade80' : 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.3)', borderRadius: 10, color: 'white', cursor: 'pointer', fontSize: 13, fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
            {copied ? <ShieldCheck size={16} /> : <ImageIcon size={16} />}
            {copied ? 'Copiado!' : 'Detalle Completo'}
          </button>
          <button onClick={copiarResumenPagos} style={{ flex: 1, padding: '10px 16px', background: copiedResumen ? '#fbbf24' : '#f59e0b', border: '1px solid #d97706', borderRadius: 10, color: '#fffbeb', cursor: 'pointer', fontSize: 13, fontWeight: 800, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, boxShadow: '0 4px 12px rgba(245, 158, 11, 0.4)' }}>
            {copiedResumen ? <Check size={16} /> : <DollarSign size={16} />}
            {copiedResumen ? 'Copiado!' : 'Solo Nombres y Totales'}
          </button>
        </div>
      </div>

      {/* Liquidación agrupada por roles */}

      {[['Administradores', administradores], ['Cajeros', cajeros], ['Plancheros', plancheros]].filter(([, grupo]) => grupo.length > 0).map(([titulo, grupo]) => (
        <div key={titulo}>
          <h2 style={{ margin: '0 0 10px', fontSize: 15, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: 1 }}>{titulo}</h2>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {grupo.map(p => {
              const { diasTrabajados, diasReemplazados, ajustesPer, sueldoBase, gruposReemplazados, gruposReemplazando, total } = getLiquidacion(p);
              const c = colores[p.id];
              const abierto = expandidos[p.id] !== false;
              const pagado = pagosNomina.find(pn => pn.personal_id == p.id);
              // Get most specific ruta11 role label
              const roles = typeof p.rol === 'string' ? p.rol.split(',').map(r => r.trim()) : (Array.isArray(p.rol) ? p.rol : []);
              const rolLabel = roles.includes('administrador') ? 'Administrador' : roles.includes('cajero') ? 'Cajero/a' : roles.includes('planchero') ? 'Planchero/a' : '';
              return (
                <div key={p.id} style={{ background: 'white', borderRadius: 16, overflow: 'hidden', boxShadow: '0 4px 20px rgba(0,0,0,0.08)', border: `1px solid ${c?.border || '#e2e8f0'}`, marginBottom: 4 }}>
                  {/* Header row */}
                  <div className="detail-card-header" onClick={() => toggleCard(p.id)} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '16px 20px', cursor: 'pointer', background: c?.light || '#f8fafc', borderBottom: abierto ? `2px solid ${c?.border || '#e2e8f0'}` : 'none', transition: 'all 0.2s' }}>
                    {/* Color accent bar */}
                    <div className="detail-card-accent" style={{ width: 6, height: 40, borderRadius: 3, background: c?.bg || '#cbd5e1', flexShrink: 0 }} />
                    {/* Name + meta */}
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="detail-card-name-section" style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                        <span className="detail-card-name-text" style={{ fontWeight: 800, fontSize: 17, color: '#0f172a', letterSpacing: '-0.3px' }}>{p.nombre}</span>
                        {pagado && <ShieldCheck size={18} style={{ color: '#059669' }} />}
                        <span style={{ fontSize: 11, padding: '3px 10px', borderRadius: 20, background: c?.light || '#f1f5f9', color: c?.text || '#475569', border: `1px solid ${c?.border || '#e2e8f0'}`, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.5px' }}>{rolLabel}</span>
                      </div>
                      <div style={{ fontSize: 13, color: '#64748b', marginTop: 4, fontWeight: 500 }}>{diasTrabajados} días trabajados</div>
                    </div>
                    {/* Salary summary inline */}
                    <div className="detail-card-meta" style={{ display: 'flex', alignItems: 'center', gap: 20, flexShrink: 0 }}>
                      <div className="detail-card-base-col" style={{ textAlign: 'right' }}>
                        <div style={{ fontSize: 10, fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', marginBottom: 2 }}>Base {rolLabel}</div>
                        <div style={{ fontSize: 15, fontWeight: 700, color: '#475569' }}>${sueldoBase.toLocaleString('es-CL')}</div>
                      </div>
                      <div className="detail-card-total-col" style={{ textAlign: 'right' }}>
                        <div style={{ fontSize: 10, fontWeight: 800, color: c?.text || '#64748b', textTransform: 'uppercase', marginBottom: 2 }}>Saldo Final</div>
                        <div style={{ fontSize: 18, fontWeight: 900, color: c?.text || '#1e293b' }}>${total.toLocaleString('es-CL')}</div>
                      </div>
                      <div style={{ marginLeft: 4 }}>
                        {abierto ? <ChevronUp size={24} style={{ color: c?.text || '#94a3b8' }} /> : <ChevronDown size={24} style={{ color: c?.text || '#94a3b8' }} />}
                      </div>
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
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 14px', border: `1px solid ${g.pago_por === 'empresa_adelanto' ? '#a5b4fc' : '#86efac'}`, backgroundColor: g.pago_por === 'empresa_adelanto' ? '#eef2ff' : '#dcfce7', borderRadius: 12, marginTop: 6, marginBottom: 6, boxShadow: '0 1px 2px rgba(0,0,0,0.05)' }}>
                          <span style={{ fontSize: 13, color: g.pago_por === 'empresa_adelanto' ? '#3730a3' : '#14532d', fontWeight: 600 }}>
                            ↔ Reemplazó a {g.persona?.nombre ?? '?'} {g.dias.length} días
                            {g.pago_por === 'empresa_adelanto' && <span style={{ marginLeft: 6, fontSize: 11, padding: '2px 8px', borderRadius: 10, background: '#c7d2fe', color: '#4338ca', fontWeight: 800, border: '1px solid #a5b4fc' }}>✅ YA PAGADO</span>}
                          </span>
                          {g.pago_por === 'empresa_adelanto'
                            ? <span style={{ fontSize: 13, color: '#6366f1', fontWeight: 600 }}>${g.monto.toLocaleString('es-CL')}</span>
                            : <span style={{ fontSize: 13, fontWeight: 800, color: '#166534' }}>+${g.monto.toLocaleString('es-CL')}</span>
                          }
                        </div>
                      ))}
                      {Object.values(gruposReemplazados).map((g, i) => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 14px', border: '1px solid #fca5a5', backgroundColor: '#fee2e2', borderRadius: 12, marginTop: 6, marginBottom: 6, boxShadow: '0 1px 2px rgba(0,0,0,0.05)', gap: 8 }}>
                          <span style={{ fontSize: 13, color: '#7f1d1d', fontWeight: 600 }}>
                            {g.persona?.nombre ?? '?'} cubrió {g.dias.length} días
                            {g.pago_por === 'titular' && <span style={{ marginLeft: 6, fontSize: 11, padding: '2px 8px', borderRadius: 10, background: '#fef3c7', color: '#b45309', fontWeight: 800, border: '1px solid #fcd34d' }}> PAGO DIRECTO</span>}
                            {g.pago_por === 'empresa_adelanto' && <span style={{ marginLeft: 6, fontSize: 11, padding: '2px 8px', borderRadius: 10, background: '#c7d2fe', color: '#4338ca', fontWeight: 800, border: '1px solid #a5b4fc' }}>ADELANTO</span>}
                          </span>
                          {(g.pago_por === 'empresa' || g.pago_por === 'empresa_adelanto')
                            ? <span style={{ fontSize: 13, fontWeight: 800, color: '#991b1b' }}>-${g.monto.toLocaleString('es-CL')}</span>
                            : <span style={{ fontSize: 13, color: '#94a3b8', fontWeight: 600 }}>—</span>
                          }
                        </div>
                      ))}{ajustesPer.map(a => (
                        <div key={a.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '7px 0', borderBottom: '1px solid #f1f5f9', gap: 8 }}>
                          <span style={{ fontSize: 13, color: '#64748b', flex: 1 }}>{a.concepto}</span>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ fontSize: 13, fontWeight: 600, color: parseFloat(a.monto) < 0 ? '#ef4444' : '#10b981' }}>
                              {parseFloat(a.monto) < 0 ? '-' : '+'}${Math.abs(parseFloat(a.monto)).toLocaleString('es-CL')}
                            </span>
                            <button onClick={() => onDeleteAjuste(a.id)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#94a3b8', display: 'flex', padding: 2 }}>
                              <Trash2 size={14} />
                            </button>
                          </div>
                        </div>
                      ))}
                      <div style={{ display: 'flex', justifyContent: 'space-between', padding: '16px 0 12px', borderTop: '2px solid #f1f5f9', marginTop: 12 }}>
                        <span style={{ fontSize: 16, fontWeight: 800, color: '#1e293b' }}>Total Líquido a Pagar</span>
                        <span style={{ fontSize: 20, fontWeight: 900, color: c?.text || '#1e293b' }}>${total.toLocaleString('es-CL')}</span>
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
    let md = `*Liquidación Seguridad — ${mesLabel}*\n━━━━━━━━━━━━━━━━━━━━\n`;
    guardias.forEach(p => {
      const { diasTrabajados, sueldoBase, ajustesPer, gruposReemplazando, gruposReemplazados, total } = getLiquidacion(p);
      let roles = p.rol;
      if (typeof p.rol === 'string') {
        roles = p.rol.split(',').map(r => r.trim()).join(', ');
      }
      md += `\n*${p.nombre.toUpperCase()}*\n_${roles}_\n▪ *Días:* ${diasTrabajados}\n▪ *Base:* $${sueldoBase.toLocaleString('es-CL')}\n`;
      Object.values(gruposReemplazando).forEach(g => { md += `▪ *Reemplazó a ${g.persona?.nombre ?? '?'}* (días ${g.dias.sort((a, b) => a - b).join(',')}): +$${g.monto.toLocaleString('es-CL')}\n`; });
      Object.values(gruposReemplazados).forEach(g => { md += `▪ *${g.persona?.nombre ?? '?'} cubrió días* ${g.dias.sort((a, b) => a - b).join(',')}: -$${g.monto.toLocaleString('es-CL')}\n`; });
      ajustesPer.forEach(a => {
        const m = parseFloat(a.monto);
        md += `▪ ${a.concepto}: *${m < 0 ? '-' : '+'}$${Math.abs(m).toLocaleString('es-CL')}*\n`;
      });
      md += `> *Total a Pagar:* $${total.toLocaleString('es-CL')}*\n`;
    });
    const tp = pagosNomina.filter(pn => guardias.some(g => g.id == pn.personal_id)).reduce((s, p) => s + parseFloat(p.monto), 0);
    if (tp > 0) {
      md += `\n━━━━━━━━━━━━━━\n*RESUMEN PAGOS REALIZADOS*\n`;
      pagosNomina.filter(pn => guardias.some(g => g.id == pn.personal_id)).forEach(p => { md += `• ${p.nombre}${p.es_externo ? ' (ext)' : ''}: *$${parseFloat(p.monto).toLocaleString('es-CL')}*\n`; });
      md += `\n*Total Pagado:* $${tp.toLocaleString('es-CL')}\n*Presupuesto:* $${presupuesto.toLocaleString('es-CL')}\n`;
    }
    return md;
  }

  function copiarMarkdown() {
    navigator.clipboard.writeText(generarMarkdown()).then(() => { setCopied(true); setTimeout(() => setCopied(false), 2500); });
  }

  function generarResumenPagos() {
    const emojis = { 'Ricardo': '🤖', 'Andrés': '🧑🏻', 'Andres': '🧑🏻', 'Camila': '👩🏽', 'Neit': '👩🏻', 'Gabriel': '🧑🏿', 'Claudio': '🧓🏽' };
    const mesLabel = `${MESES_L[mes]} ${anio}`;
    let md = `🏦 *RESUMEN PAGOS SEGURIDAD*\n📅 _${mesLabel.toUpperCase()}_\n━━━━━━━━━━━━\n`;
    let sum = 0;
    const items = guardias.filter(p => getLiquidacion(p).total > 0);
    const montosStr = items.map(p => `$${getLiquidacion(p).total.toLocaleString('es-CL')}`);
    const maxLen = Math.max(...montosStr.map(s => s.length), 0);

    items.forEach((p, idx) => {
      const { total } = getLiquidacion(p);
      const emoji = emojis[p.nombre] || emojis[p.nombre.split(' ')[0]] || '👤';
      const montoPad = montosStr[idx].padStart(maxLen, ' ');
      md += `- ${emoji} _${p.nombre.toUpperCase()}:_ \`\`\`${montoPad}\`\`\`\n`;
      sum += total;
    });
    md += `━━━━━━━━━━━━\n💰 *Total a Transferir:* \`\`\`$${sum.toLocaleString('es-CL')}\`\`\`\n\n🔗 *DETALLES:* https://caja.laruta11.cl/personal/`;
    return md;
  }

  const [copiedResumen, setCopiedResumen] = useState(false);
  function copiarResumenPagos() {
    navigator.clipboard.writeText(generarResumenPagos()).then(() => { setCopiedResumen(true); setTimeout(() => setCopiedResumen(false), 2500); });
  }

  const totalCalculado = guardias.reduce((s, p) => s + getLiquidacion(p).costoEmpresa, 0);
  const totalAdelantos = guardias.reduce((s, p) => s + getLiquidacion(p).montoAdelantos, 0);
  const pagosGuardias = pagosNomina.filter(pn => guardias.some(g => g.id == pn.personal_id));
  const totalPagado = pagosGuardias.reduce((s, p) => s + parseFloat(p.monto), 0);
  const saldo = presupuesto - (totalPagado + totalAdelantos);
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
          centro_costo: centroCosto,
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
        <div style={{ fontSize: 13, opacity: 0.7, marginBottom: 12, display: 'flex', alignItems: 'center', gap: 8 }}>
          <ShieldCheck size={16} /> Centro de Costos — Seguridad {MESES_L[mes]} {anio}
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 16 }}>
          <div>
            <div style={{ fontSize: 12, opacity: 0.6 }}>Presupuesto</div>
            {editPresupuesto
              ? <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                <input type='number' value={presupuestoInput} onChange={e => setPresupuestoInput(e.target.value)}
                  style={{ width: 120, padding: '4px 8px', borderRadius: 6, border: 'none', fontSize: 16, fontWeight: 700, color: '#1e293b', background: 'white' }} />
                <button onClick={() => { onSavePresupuesto(parseFloat(presupuestoInput)); setEditPresupuesto(false); }} style={{ padding: '6px', borderRadius: 8, border: 'none', background: '#4ade80', color: '#065f46', cursor: 'pointer', display: 'flex' }}>
                  <Check size={16} />
                </button>
                <button onClick={() => setEditPresupuesto(false)} style={{ padding: '6px', borderRadius: 8, border: 'none', background: 'rgba(255,255,255,0.2)', color: 'white', cursor: 'pointer', display: 'flex' }}>
                  <X size={16} />
                </button>
              </div>
              : <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <div style={{ fontSize: 22, fontWeight: 800 }}>${presupuesto.toLocaleString('es-CL')}</div>
                <button onClick={() => { setPresupuestoInput(presupuesto); setEditPresupuesto(true); }} style={{ padding: '6px', borderRadius: 8, border: 'none', background: 'rgba(255,255,255,0.2)', color: 'white', cursor: 'pointer', display: 'flex' }}>
                  <Edit size={14} />
                </button>
              </div>
            }
          </div>
          <div>
            <div style={{ fontSize: 12, opacity: 0.6 }}>Pagos realizados</div>
            <div style={{ fontSize: 22, fontWeight: 800 }}>${(totalPagado + totalAdelantos).toLocaleString('es-CL')}</div>
            <div style={{ fontSize: 10, opacity: 0.6 }}>Calculado: ${totalCalculado.toLocaleString('es-CL')}</div>
          </div>
          <div>
            <div style={{ fontSize: 12, opacity: 0.6 }}>{saldo < 0 ? 'Exceso' : 'Saldo presupuesto'}</div>
            <div style={{ fontSize: 22, fontWeight: 800, color: saldo < 0 ? '#f87171' : '#4ade80' }}>
              ${Math.abs(saldo).toLocaleString('es-CL')}
            </div>
            <div style={{ fontSize: 10, opacity: 0.6 }}>{saldo < 0 ? 'Sobre el presupuesto' : 'Disponible'}</div>
          </div>
        </div>
        <button onClick={copiarMarkdown} style={{ marginTop: 4, padding: '8px 16px', background: copied ? '#4ade80' : 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.3)', borderRadius: 8, color: 'white', cursor: 'pointer', fontSize: 13, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 8 }}>
          {copied ? <ShieldCheck size={16} /> : <ImageIcon size={16} />}
          {copied ? 'Copiado!' : 'Copiar para WhatsApp'}
        </button>
      </div>

      {mes === 1 && (
        <div style={{ background: '#fffbeb', border: '1px solid #fde68a', borderRadius: 12, padding: '12px 16px', marginBottom: 16, display: 'flex', alignItems: 'flex-start', gap: 10 }}>
          <span style={{ fontSize: 16, flexShrink: 0 }}>⚖️</span>
          <div style={{ fontSize: 11, color: '#92400e', lineHeight: 1.5 }}>
            <strong>Nota legal — Febrero:</strong> El mes laboral es siempre de 30 días, sin importar que febrero tenga 28 (o 29 en bisiesto). El empleador paga los 30 días completos y el valor diario se calcula dividiendo por 30.
            <br />
            <span style={{ opacity: 0.7 }}>Art. 42 y 55, Código del Trabajo · </span>
            <a href="https://www.dt.gob.cl/legislacion/1624/w3-propertyvalue-145761.html" target="_blank" rel="noopener noreferrer" style={{ color: '#b45309', fontWeight: 600, textDecoration: 'underline', opacity: 0.8 }}>Dirección del Trabajo</a>
          </div>
        </div>
      )}

      {[['Guardias', guardias]].map(([titulo, grupo]) => (
        <div key={titulo}>
          <h2 style={{ margin: '0 0 10px', fontSize: 15, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: 1 }}>{titulo}</h2>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {grupo.map(p => {
              const { diasTrabajados, diasReemplazados, ajustesPer, sueldoBase, gruposReemplazados, gruposReemplazando, total } = getLiquidacion(p);
              const c = colores[p.id] || { bg: '#e0e7ff', border: '#c7d2fe', light: '#eef2ff', text: '#3730a3' };
              const abierto = expandidos[p.id] !== false;
              return (
                <div key={p.id} style={{ background: 'white', borderRadius: 16, overflow: 'hidden', boxShadow: '0 4px 20px rgba(0,0,0,0.08)', border: `1px solid ${c.border}`, marginBottom: 4 }}>
                  <div className="detail-card-header" onClick={() => toggleCard(p.id)} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '16px 20px', cursor: 'pointer', background: c.light, borderBottom: abierto ? `2px solid ${c.border}` : 'none', transition: 'all 0.2s' }}>
                    <div className="detail-card-accent" style={{ width: 6, height: 40, borderRadius: 3, background: c.bg, flexShrink: 0 }} />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="detail-card-name-section" style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                        <span className="detail-card-name-text" style={{ fontWeight: 800, fontSize: 17, color: '#0f172a', letterSpacing: '-0.3px' }}>{p.nombre}</span>
                        {pagosNomina.find(pn => pn.personal_id == p.id) && <ShieldCheck size={18} style={{ color: '#059669' }} />}
                        <span style={{ fontSize: 11, padding: '3px 10px', borderRadius: 20, background: c.light, color: c.text, border: `1px solid ${c.border}`, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.5px' }}>Seguridad</span>
                      </div>
                      <div style={{ fontSize: 13, color: '#64748b', marginTop: 4, fontWeight: 500 }}>{diasReemplazados > 0 ? `${diasTrabajados} de 30` : '30'} días trabajados</div>
                    </div>
                    <div className="detail-card-meta" style={{ display: 'flex', alignItems: 'center', gap: 20, flexShrink: 0 }}>
                      <div className="detail-card-base-col" style={{ textAlign: 'right' }}>
                        <div style={{ fontSize: 10, fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', marginBottom: 2 }}>Base Seguridad</div>
                        <div style={{ fontSize: 15, fontWeight: 700, color: '#475569' }}>${sueldoBase.toLocaleString('es-CL')}</div>
                      </div>
                      <div className="detail-card-total-col" style={{ textAlign: 'right' }}>
                        <div style={{ fontSize: 10, fontWeight: 800, color: c.text, textTransform: 'uppercase', marginBottom: 2 }}>Saldo Final</div>
                        <div style={{ fontSize: 18, fontWeight: 900, color: c.text }}>${total.toLocaleString('es-CL')}</div>
                      </div>
                      <div style={{ marginLeft: 4 }}>
                        {abierto ? <ChevronUp size={24} style={{ color: c.text }} /> : <ChevronDown size={24} style={{ color: c.text }} />}
                      </div>
                    </div>
                  </div>
                  {abierto && (
                    <div style={{ padding: '4px 16px 14px' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #f1f5f9' }}>
                        <span style={{ fontSize: 13, color: '#64748b' }}>Sueldo base (Seguridad)</span>
                        <span style={{ fontSize: 13, fontWeight: 600 }}>${sueldoBase.toLocaleString('es-CL')}</span>
                      </div>
                      {Object.values(gruposReemplazando).map((g, i) => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 14px', border: `1px solid ${g.pago_por === 'empresa_adelanto' ? '#a5b4fc' : '#86efac'}`, backgroundColor: g.pago_por === 'empresa_adelanto' ? '#eef2ff' : '#dcfce7', borderRadius: 12, marginTop: 6, marginBottom: 6, boxShadow: '0 1px 2px rgba(0,0,0,0.05)' }}>
                          <span style={{ fontSize: 13, color: g.pago_por === 'empresa_adelanto' ? '#3730a3' : '#14532d', fontWeight: 600 }}>
                            ↔ Reemplazó a {g.persona?.nombre ?? '?'} {g.dias.length} días
                            {g.pago_por === 'empresa_adelanto' && <span style={{ marginLeft: 6, fontSize: 11, padding: '2px 8px', borderRadius: 10, background: '#c7d2fe', color: '#4338ca', fontWeight: 800, border: '1px solid #a5b4fc' }}>✅ YA PAGADO</span>}
                          </span>
                          {g.pago_por === 'empresa_adelanto'
                            ? <span style={{ fontSize: 13, color: '#6366f1', fontWeight: 600 }}>${g.monto.toLocaleString('es-CL')}</span>
                            : <span style={{ fontSize: 13, fontWeight: 800, color: '#166534' }}>+${g.monto.toLocaleString('es-CL')}</span>
                          }
                        </div>
                      ))}
                      {Object.values(gruposReemplazados).map((g, i) => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 14px', border: '1px solid #fca5a5', backgroundColor: '#fee2e2', borderRadius: 12, marginTop: 6, marginBottom: 6, boxShadow: '0 1px 2px rgba(0,0,0,0.05)', gap: 8 }}>
                          <span style={{ fontSize: 13, color: '#7f1d1d', fontWeight: 600 }}>
                            {g.persona?.nombre ?? '?'} cubrió {g.dias.length} días
                            {g.pago_por === 'titular' && <span style={{ marginLeft: 6, fontSize: 11, padding: '2px 8px', borderRadius: 10, background: '#fef3c7', color: '#b45309', fontWeight: 800, border: '1px solid #fcd34d' }}> PAGO DIRECTO</span>}
                            {g.pago_por === 'empresa_adelanto' && <span style={{ marginLeft: 6, fontSize: 11, padding: '2px 8px', borderRadius: 10, background: '#c7d2fe', color: '#4338ca', fontWeight: 800, border: '1px solid #a5b4fc' }}>ADELANTO</span>}
                          </span>
                          {(g.pago_por === 'empresa' || g.pago_por === 'empresa_adelanto')
                            ? <span style={{ fontSize: 13, fontWeight: 800, color: '#991b1b' }}>-${g.monto.toLocaleString('es-CL')}</span>
                            : <span style={{ fontSize: 13, color: '#94a3b8', fontWeight: 600 }}>—</span>
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
                            <button onClick={() => onDeleteAjuste(a.id)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#94a3b8', display: 'flex', padding: 2 }}>
                              <Trash2 size={14} />
                            </button>
                          </div>
                        </div>
                      ))}
                      <div style={{ display: 'flex', justifyContent: 'space-between', padding: '16px 0 12px', borderTop: '2px solid #f1f5f9', marginTop: 12 }}>
                        <span style={{ fontSize: 16, fontWeight: 800, color: '#1e293b' }}>Total Líquido a Pagar</span>
                        <span style={{ fontSize: 20, fontWeight: 900, color: c.text }}>${total.toLocaleString('es-CL')}</span>
                      </div>
                      <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                        <button onClick={() => onAjuste(p)} style={{ flex: 1, padding: '9px', border: `1px dashed ${c.border}`, borderRadius: 8, background: c.light, color: c.text, cursor: 'pointer', fontSize: 13, fontWeight: 600 }}>+ Ajuste</button>
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
        <h2 style={{ margin: 0, fontSize: 18, fontWeight: 700, color: '#1e293b', display: 'flex', alignItems: 'center', gap: 10 }}>
          <Users size={20} style={{ color: '#1a73e8' }} /> Equipo La Ruta 11
        </h2>
        <button onClick={onAddPersonal} style={{ padding: '10px 18px', border: 'none', borderRadius: 10, background: '#1a73e8', color: 'white', cursor: 'pointer', fontWeight: 600, fontSize: 14, display: 'flex', alignItems: 'center', gap: 8 }}>
          <Plus size={18} /> Agregar persona
        </button>
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
                  <button onClick={() => onEditPersonal(p)} style={{ padding: '8px', border: '1px solid #e2e8f0', borderRadius: 8, background: 'white', cursor: 'pointer', color: '#1a73e8', display: 'inline-flex' }}>
                    <Edit size={16} />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}


function MobileScheduleView({ diasEnMes, turnosSeguridad, personal, mes, anio, onAddTurno, onDeleteTurno, seguridadColors }) {
  const DIAS_CORTO = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
  const days = Array.from({ length: diasEnMes }, (_, i) => i + 1);

  // Get all security guards
  const guardias = personal.filter(p =>
    (typeof p.rol === 'string' ? p.rol.includes('seguridad') : Array.isArray(p.rol) ? p.rol.includes('seguridad') : false) && p.activo == 1
  );

  return (
    <div style={{ display: 'flex', flexDirection: 'column' }}>
      {days.map(dia => {
        const trabajando = turnosSeguridad[dia] || [];
        const date = new Date(anio, mes, dia);
        const dayLabel = DIAS_CORTO[date.getDay()];
        const fecha = `${anio}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const esHoy = new Date().toDateString() === date.toDateString();
        const esFinde = date.getDay() === 0 || date.getDay() === 6;

        // Who has a dynamic (4x4) shift today?
        const dynamicShifts = trabajando.filter(t => t.is_dynamic);
        const dynamicIds = new Set(dynamicShifts.map(t => String(t.personal_id)));

        // Manual replacement shifts
        const reemplazos = trabajando.filter(t => !t.is_dynamic && t.tipo.includes('reemplazo'));

        return (
          <div key={dia} style={{
            display: 'flex', alignItems: 'stretch',
            borderBottom: '1px solid #eef2ff',
            background: esHoy ? '#eef0ff' : (esFinde ? '#fafaff' : 'white'),
          }}>
            {/* Date column */}
            <div style={{
              width: 48, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
              padding: '10px 4px', borderRight: '1px solid #eef2ff', flexShrink: 0,
            }}>
              <div style={{ fontSize: 10, fontWeight: 700, color: esHoy ? '#4f46e5' : '#94a3b8', textTransform: 'uppercase', letterSpacing: 0.5 }}>{dayLabel}</div>
              <div style={{
                fontSize: 17, fontWeight: 800, color: esHoy ? 'white' : '#312e81',
                background: esHoy ? '#4f46e5' : 'transparent',
                width: 30, height: 30, display: 'flex', alignItems: 'center', justifyContent: 'center',
                borderRadius: '50%', marginTop: 1,
              }}>{dia}</div>
            </div>

            {/* Content column */}
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', padding: '6px 10px', gap: 4, justifyContent: 'center' }}>
              {/* Show each guard's status */}
              {guardias.map(g => {
                const c = seguridadColors[g.nombre] || seguridadColors['default'];
                const enGuardia = dynamicIds.has(String(g.id));
                return (
                  <div key={g.id} style={{
                    display: 'flex', alignItems: 'center', gap: 8,
                    padding: '4px 10px', borderRadius: 6,
                    background: enGuardia ? c.bg : '#f8fafc',
                    borderLeft: `3px solid ${enGuardia ? c.line : '#e2e8f0'}`,
                    opacity: enGuardia ? 1 : 0.6,
                  }}>
                    <span style={{
                      fontSize: 13, fontWeight: 700,
                      color: enGuardia ? c.text : '#94a3b8',
                      flex: 1,
                    }}>{g.nombre}</span>
                    <span style={{
                      fontSize: 10, fontWeight: 700, padding: '2px 6px', borderRadius: 4,
                      background: enGuardia ? '#dcfce7' : '#f1f5f9',
                      color: enGuardia ? '#15803d' : '#94a3b8',
                      textTransform: 'uppercase', letterSpacing: 0.3,
                    }}>{enGuardia ? 'Guardia' : 'Descanso'}</span>
                  </div>
                );
              })}

              {/* Replacement shifts */}
              {reemplazos.map(t => {
                const replacer = personal.find(x => x.id == t.personal_id);
                const titular = personal.find(x => x.id == t.reemplazado_por);
                if (!replacer) return null;
                return (
                  <div key={t.id} style={{
                    display: 'flex', alignItems: 'center', gap: 6,
                    padding: '4px 10px', borderRadius: 6,
                    background: '#fff7ed', borderLeft: '3px solid #f97316',
                  }}>
                    <span style={{ fontSize: 12, fontWeight: 700, color: '#c2410c', flex: 1 }}>
                      {replacer.nombre} ↔ {titular ? titular.nombre : '?'}
                    </span>
                    <button onClick={() => onDeleteTurno(t.id)} style={{
                      background: 'none', border: 'none', cursor: 'pointer', color: '#ef4444',
                      fontSize: 14, fontWeight: 800, padding: 0, lineHeight: 1,
                    }}>×</button>
                  </div>
                );
              })}
            </div>

            {/* Add button */}
            <div style={{ display: 'flex', alignItems: 'center', padding: '0 8px', flexShrink: 0 }}>
              <button onClick={() => {
                const titularObj = trabajando.find(t => t.is_dynamic);
                const titularId = titularObj ? titularObj.personal_id : '';
                onAddTurno({ dia, fecha, isSeguridad: true, titularId });
              }} style={{
                width: 24, height: 24, borderRadius: 6, border: '1px solid #e0e7ff',
                background: '#f5f7ff', color: '#4f46e5', fontSize: 16, fontWeight: 800,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                cursor: 'pointer',
              }}>+</button>
            </div>
          </div>
        );
      })}
    </div>
  );
}

function CalendarioSeguridad({ diasEnMes, primerDiaLunes, turnosSeguridad, personal, mes, anio, onAddTurno, onDeleteTurno }) {
  const DIAS_LABEL = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
  const MESES_FULL = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  const celdas = [];
  for (let i = 0; i < primerDiaLunes; i++) celdas.push(null);
  for (let d = 1; d <= diasEnMes; d++) celdas.push(d);

  const seguridadColors = {
    'Ricardo': { bg: '#dbeafe', border: '#93c5fd', text: '#1e40af', line: '#3b82f6' },
    'Claudio': { bg: '#ede9fe', border: '#c4b5fd', text: '#5b21b6', line: '#8b5cf6' },
    'default': { bg: '#fef9c3', border: '#fde047', text: '#a16207', line: '#eab308' }
  };

  const guardias = personal.filter(p =>
    (typeof p.rol === 'string' ? p.rol.includes('seguridad') : Array.isArray(p.rol) ? p.rol.includes('seguridad') : false) && p.activo == 1
  );
  const hoy = new Date();
  const hoyDia = hoy.getMonth() === mes && hoy.getFullYear() === anio ? hoy.getDate() : -1;

  return (
    <div className="cal-container" style={{ background: '#f8fafd', border: '1px solid #e3e3e3' }}>
      {/* Header Google Calendar Style */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 24, flexWrap: 'wrap', gap: 12 }}>
        <div>
          <h2 style={{ margin: 0, fontSize: 22, fontWeight: 400, color: '#1f1f1f', letterSpacing: '-0.3px', display: 'flex', alignItems: 'center', gap: 8 }}><Shield size={22} style={{ color: '#8b5cf6' }} /> {MESES_FULL[mes]} {anio}</h2>
          <p style={{ margin: '4px 0 0', color: '#70757a', fontSize: 13, fontWeight: 500 }}>Turnos 4×4 · Club de Yates</p>
        </div>
        <div style={{ display: 'flex', gap: 8 }}>
          {guardias.map(p => {
            const c = seguridadColors[p.nombre] || seguridadColors['default'];
            return (
              <div key={p.id} style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 14px', borderRadius: 20, background: 'white', border: '1px solid #e3e3e3', boxShadow: '0 1px 2px rgba(0,0,0,0.04)' }}>
                <div style={{ width: 10, height: 10, borderRadius: '50%', background: c.line }} />
                <span style={{ fontSize: 13, fontWeight: 600, color: '#444746' }}>{p.nombre}</span>
              </div>
            );
          })}
        </div>
      </div>

      {/* CSS injected to handle responsive text truncation and sizing flawlessly */}
      <style>{`
        .cal-container { border-radius: 24px; padding: 28px; }
        .month-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
        .grid-cell { background: white; border-radius: 12px; min-height: 100px; padding: 6px; display: flex; flex-direction: column; transition: all 0.2s ease; border: 1px solid #e3e3e380; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .grid-cell.hoy { border: 2px solid #1a73e8; box-shadow: 0 4px 16px rgba(26,115,232,0.12); }
        .grid-cell.empty { background: transparent; border: none; box-shadow: none; min-height: auto; }
        
        .header-day { text-align: center; font-size: 11px; font-weight: 800; color: #70757a; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 0; }
        .day-num { font-size: 13px; font-weight: 700; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: #444746; }
        .day-num.hoy-num { background: #1a73e8; color: white; }
        
        .guard-pill { display: flex; justify-content: center; color: white; border-radius: 8px; padding: 3px 6px; font-size: 10px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 3px; }
        .repl-pill { display: flex; align-items: center; justify-content: space-between; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 3px 6px; font-size: 10px; font-weight: 700; color: #c2410c; margin-bottom: 3px; }
        .repl-btn { background: none; border: none; cursor: pointer; color: #ef4444; font-size: 12px; font-weight: 800; padding: 0; margin-left: 2px; line-height: 1; }
        .add-btn { font-size: 14px; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: none; background: transparent; cursor: pointer; color: #70757a; font-weight: 700; opacity: 0.5; padding: 0; }

        @media (max-width: 450px) {
          .cal-container { padding: 14px 4px; border-radius: 12px; }
          .month-grid { gap: 2px; }
          .grid-cell { padding: 2px; min-height: 60px; border-radius: 6px; }
          .header-day { font-size: 9px; padding: 4px 0; }
          .day-num { width: 18px; height: 18px; font-size: 10px; }
          .guard-pill { padding: 1.5px 2px; font-size: 8.5px; border-radius: 4px; margin-bottom: 2px; letter-spacing: -0.3px; }
          .repl-pill { padding: 1.5px 2px; font-size: 8.5px; border-radius: 4px; margin-bottom: 2px; letter-spacing: -0.3px; }
          .repl-btn { font-size: 10px; }
          .add-btn { width: 16px; height: 16px; font-size: 12px; }
          .mobile-short-name { max-width: 100%; display: inline-block; overflow: hidden; text-overflow: ellipsis; }
        }
      `}</style>

      {/* Day headers */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', marginBottom: 8 }}>
        {DIAS_LABEL.map(d => (
          <div key={d} className="header-day">{d}</div>
        ))}
      </div>

      {/* Card grid */}
      <div className="month-grid">
        {celdas.map((dia, i) => {
          const trabajando = dia ? (turnosSeguridad[dia] || []) : [];
          const fecha = dia ? `${anio}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}` : '';
          const esHoy = dia === hoyDia;
          const dynamicIds = new Set(trabajando.filter(t => t.is_dynamic).map(t => String(t.personal_id)));
          const reemplazos = trabajando.filter(t => !t.is_dynamic && t.tipo.includes('reemplazo'));

          return (
            <div key={i} className={`grid-cell ${dia ? '' : 'empty'} ${esHoy ? 'hoy' : ''}`}>
              {dia && (
                <>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 }}>
                    <span className={`day-num ${esHoy ? 'hoy-num' : ''}`}>{dia}</span>
                    <button className="add-btn" onClick={() => {
                      const titularObj = trabajando.find(t => t.is_dynamic);
                      const titularId = titularObj ? titularObj.personal_id : '';
                      onAddTurno({ dia, fecha, isSeguridad: true, titularId });
                    }} title="Agregar Reemplazo">+</button>
                  </div>

                  <div style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
                    {/* Guard pills - only show who's on duty */}
                    {guardias.map(g => {
                      const c = seguridadColors[g.nombre] || seguridadColors['default'];
                      const enGuardia = dynamicIds.has(String(g.id));
                      if (!enGuardia) return null;
                      // Display full name on desktop, first 3 letters on extreme mobile width via media queries (handled visually via container limit if needed, or by explicit slicing)
                      return (
                        <div key={g.id} className="guard-pill" style={{ background: c.line }}>
                          <span className="mobile-short-name">{g.nombre.slice(0, 3)}.</span>
                        </div>
                      );
                    })}

                    {/* Replacement pills — personal_id=titular, reemplazado_por=replacer */}
                    {reemplazos.map(t => {
                      const titular = personal.find(x => x.id == t.personal_id);
                      const replacer = personal.find(x => x.id == t.reemplazado_por);
                      if (!replacer) return null;
                      return (
                        <div key={t.id} className="repl-pill">
                          <span className="mobile-short-name">
                            {replacer.nombre[0]}↔{titular ? titular.nombre[0] : '?'}
                          </span>
                          <button onClick={() => onDeleteTurno(t.id)} className="repl-btn">×</button>
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
  );
}
