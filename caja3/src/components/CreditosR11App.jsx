import React, { useState, useEffect } from 'react';

export default function CreditosR11App() {
  const [beneficiarios, setBeneficiarios] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const [modal, setModal] = useState(null); // 'register' | 'approve' | 'payment' | null
  const [selectedUser, setSelectedUser] = useState(null);
  const [processing, setProcessing] = useState(false);
  const [formData, setFormData] = useState({});
  const [message, setMessage] = useState(null);

  useEffect(() => { loadBeneficiarios(); }, [filter]);

  const loadBeneficiarios = async () => {
    try {
      const url = filter === 'all'
        ? `/api/get_creditos_r11.php?t=${Date.now()}`
        : `/api/get_creditos_r11.php?status=${filter}&t=${Date.now()}`;
      const res = await fetch(url);
      const data = await res.json();
      if (data.success) {
        setBeneficiarios(data.data || []);
      }
    } catch (e) {
      console.error('Error cargando beneficiarios R11:', e);
    } finally {
      setLoading(false);
    }
  };

  const showMessage = (text, type = 'success') => {
    setMessage({ text, type });
    setTimeout(() => setMessage(null), 4000);
  };

  const fmt = (n) => Math.round(parseFloat(n) || 0).toLocaleString('es-CL');

  // --- Actions ---

  const handleRegister = async () => {
    setProcessing(true);
    try {
      const body = { ...formData };
      if (body.auto_approve) {
        body.limite_credito_r11 = parseFloat(body.limite_credito_r11) || 0;
      }
      const res = await fetch('/api/register_credito_r11.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const data = await res.json();
      if (data.success) {
        showMessage('Beneficiario registrado correctamente');
        setModal(null);
        setFormData({});
        loadBeneficiarios();
      } else {
        showMessage(data.error || 'Error al registrar', 'error');
      }
    } catch (e) {
      showMessage('Error de conexión', 'error');
    } finally {
      setProcessing(false);
    }
  };

  const handleApprove = async () => {
    if (!selectedUser) return;
    setProcessing(true);
    try {
      const fd = new FormData();
      fd.append('usuario_id', selectedUser.id);
      fd.append('action', 'approve');
      fd.append('limite_credito', formData.limite_credito_r11 || '30000');
      const res = await fetch('/api/approve_credito_r11.php', {
        method: 'POST',
        body: fd
      });
      const data = await res.json();
      if (data.success) {
        showMessage('Crédito aprobado');
        setModal(null);
        setSelectedUser(null);
        loadBeneficiarios();
      } else {
        showMessage(data.error || 'Error al aprobar', 'error');
      }
    } catch (e) {
      showMessage('Error de conexión', 'error');
    } finally {
      setProcessing(false);
    }
  };

  const handleReject = async (userId) => {
    if (!confirm('¿Rechazar este crédito R11?')) return;
    setProcessing(true);
    try {
      const fd = new FormData();
      fd.append('usuario_id', userId);
      fd.append('action', 'reject');
      const res = await fetch('/api/approve_credito_r11.php', {
        method: 'POST',
        body: fd
      });
      const data = await res.json();
      if (data.success) {
        showMessage('Crédito rechazado');
        loadBeneficiarios();
      } else {
        showMessage(data.error || 'Error al rechazar', 'error');
      }
    } catch (e) {
      showMessage('Error de conexión', 'error');
    } finally {
      setProcessing(false);
    }
  };

  const handleManualPayment = async () => {
    if (!selectedUser || !formData.amount) return;
    setProcessing(true);
    try {
      const res = await fetch('/api/r11/process_manual_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: selectedUser.id,
          amount: parseFloat(formData.amount),
          method: formData.payment_method || 'cash',
          notes: formData.notes || ''
        })
      });
      const data = await res.json();
      if (data.success) {
        showMessage('Pago manual procesado');
        setModal(null);
        setSelectedUser(null);
        setFormData({});
        loadBeneficiarios();
      } else {
        showMessage(data.error || 'Error al procesar pago', 'error');
      }
    } catch (e) {
      showMessage('Error de conexión', 'error');
    } finally {
      setProcessing(false);
    }
  };

  // --- Render ---
  return (
    <div className="max-w-6xl mx-auto p-4">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <button onClick={() => window.location.href = '/'} className="text-gray-500 hover:text-gray-700 text-2xl">←</button>
          <h1 className="text-2xl font-bold text-amber-700">🏷️ Créditos R11</h1>
          <span className="text-sm text-amber-600 bg-amber-50 px-2 py-1 rounded border border-amber-200">
            {beneficiarios.length} beneficiarios
          </span>
        </div>
        <button
          onClick={() => { setModal('register'); setFormData({}); }}
          className="bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 px-4 rounded-lg text-sm flex items-center gap-2"
        >
          + Nuevo Beneficiario
        </button>
      </div>

      {/* Toast */}
      {message && (
        <div className={`fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white font-medium ${message.type === 'error' ? 'bg-red-500' : 'bg-green-500'}`}>
          {message.text}
        </div>
      )}

      {/* Filters */}
      <div className="flex gap-2 mb-4">
        {['all', 'pending', 'approved'].map(f => (
          <button
            key={f}
            onClick={() => { setFilter(f); setLoading(true); }}
            className={`px-4 py-2 rounded-lg text-sm font-semibold transition-colors ${
              filter === f
                ? 'bg-amber-500 text-white'
                : 'bg-white text-gray-600 border border-gray-200 hover:bg-amber-50'
            }`}
          >
            {f === 'all' ? 'Todos' : f === 'pending' ? 'Pendientes' : 'Aprobados'}
          </button>
        ))}
      </div>

      {/* Content */}
      {loading ? (
        <div className="text-center py-12 text-gray-500">Cargando...</div>
      ) : beneficiarios.length === 0 ? (
        <div className="text-center py-12 text-gray-400">No hay beneficiarios R11</div>
      ) : (
        <div className="bg-white rounded-xl shadow-sm border border-amber-200 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-amber-50 border-b border-amber-200">
                  {['Nombre', 'Relación', 'Límite', 'Usado', 'Disponible', 'Estado', 'Acciones'].map(col => (
                    <th key={col} className="px-4 py-3 text-left text-xs font-bold text-amber-700 uppercase tracking-wider">{col}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {beneficiarios.map(b => {
                  const limite = parseFloat(b.limite_credito_r11) || 0;
                  const usado = parseFloat(b.credito_r11_usado) || 0;
                  const disponible = limite - usado;
                  const aprobado = parseInt(b.credito_r11_aprobado) === 1;
                  const bloqueado = parseInt(b.credito_r11_bloqueado) === 1;

                  return (
                    <tr key={b.id} className="hover:bg-amber-50/30">
                      <td className="px-4 py-3">
                        <div className="font-medium text-gray-800">{b.name || b.nombre || 'Sin nombre'}</div>
                        <div className="text-xs text-gray-400">{b.email || ''}</div>
                        {b.phone && <div className="text-xs text-gray-400">{b.phone}</div>}
                      </td>
                      <td className="px-4 py-3 text-gray-600">{b.relacion_r11 || '-'}</td>
                      <td className="px-4 py-3 font-semibold text-gray-700">${fmt(limite)}</td>
                      <td className={`px-4 py-3 font-semibold ${usado > 0 ? 'text-red-600' : 'text-gray-400'}`}>${fmt(usado)}</td>
                      <td className={`px-4 py-3 font-semibold ${disponible > 0 ? 'text-green-600' : 'text-gray-400'}`}>${fmt(disponible)}</td>
                      <td className="px-4 py-3">
                        {bloqueado
                          ? <span className="px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">🔒 Bloqueado</span>
                          : aprobado
                            ? <span className="px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">✅ Aprobado</span>
                            : <span className="px-2 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-700">⏳ Pendiente</span>
                        }
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex gap-1 flex-wrap">
                          {!aprobado && (
                            <button
                              onClick={() => { setSelectedUser(b); setFormData({ limite_credito_r11: '30000' }); setModal('approve'); }}
                              className="bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded text-xs font-bold"
                            >✅ Aprobar</button>
                          )}
                          {!aprobado && (
                            <button
                              onClick={() => handleReject(b.id)}
                              className="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs font-bold"
                            >❌ Rechazar</button>
                          )}
                          {aprobado && (
                            <button
                              onClick={() => handleReject(b.id)}
                              className="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded text-xs font-bold"
                            >🔄 Revocar</button>
                          )}
                          {aprobado && usado > 0 && (
                            <button
                              onClick={() => { setSelectedUser(b); setFormData({ amount: String(usado) }); setModal('payment'); }}
                              className="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs font-bold"
                            >💰 Pago Manual</button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Register Modal */}
      {modal === 'register' && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onClick={() => setModal(null)}>
          <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-amber-700 mb-4">Registrar Beneficiario R11</h3>
            <div className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">ID Usuario (si existe)</label>
                <input
                  type="number"
                  placeholder="Dejar vacío para crear nuevo"
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                  value={formData.user_id || ''}
                  onChange={e => setFormData({ ...formData, user_id: e.target.value ? parseInt(e.target.value) : undefined })}
                />
              </div>
              {!formData.user_id && (
                <>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Nombre *</label>
                    <input
                      type="text"
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                      value={formData.nombre || ''}
                      onChange={e => setFormData({ ...formData, nombre: e.target.value })}
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Teléfono</label>
                    <input
                      type="text"
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                      value={formData.telefono || ''}
                      onChange={e => setFormData({ ...formData, telefono: e.target.value })}
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Email</label>
                    <input
                      type="email"
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                      value={formData.email || ''}
                      onChange={e => setFormData({ ...formData, email: e.target.value })}
                    />
                  </div>
                </>
              )}
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">Relación *</label>
                <select
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                  value={formData.relacion_r11 || ''}
                  onChange={e => setFormData({ ...formData, relacion_r11: e.target.value })}
                >
                  <option value="">Seleccionar...</option>
                  <option value="trabajador">Trabajador</option>
                  <option value="familiar">Familiar</option>
                  <option value="confianza">Persona de confianza</option>
                </select>
              </div>
              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="auto_approve"
                  checked={formData.auto_approve || false}
                  onChange={e => setFormData({ ...formData, auto_approve: e.target.checked })}
                  className="accent-amber-500"
                />
                <label htmlFor="auto_approve" className="text-sm text-gray-700">Auto-aprobar con límite</label>
              </div>
              {formData.auto_approve && (
                <div>
                  <label className="block text-xs font-semibold text-gray-600 mb-1">Límite de crédito</label>
                  <select
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-transparent"
                    value={formData.limite_credito_r11 || '30000'}
                    onChange={e => setFormData({ ...formData, limite_credito_r11: e.target.value })}
                  >
                    <option value="20000">$20.000</option>
                    <option value="30000">$30.000</option>
                    <option value="50000">$50.000</option>
                    <option value="100000">$100.000</option>
                  </select>
                </div>
              )}
            </div>
            <div className="flex gap-2 mt-6">
              <button onClick={() => setModal(null)} className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 rounded-lg text-sm">Cancelar</button>
              <button
                onClick={handleRegister}
                disabled={processing || !formData.relacion_r11}
                className="flex-1 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 text-white font-bold py-2 rounded-lg text-sm"
              >
                {processing ? 'Registrando...' : 'Registrar'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Approve Modal */}
      {modal === 'approve' && selectedUser && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onClick={() => { setModal(null); setSelectedUser(null); }}>
          <div className="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-green-700 mb-2">Aprobar Crédito R11</h3>
            <p className="text-sm text-gray-600 mb-4">
              {selectedUser.name || selectedUser.nombre} — {selectedUser.relacion_r11 || 'Sin relación'}
            </p>
            <div>
              <label className="block text-xs font-semibold text-gray-600 mb-1">Límite de crédito</label>
              <select
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-400 focus:border-transparent"
                value={formData.limite_credito_r11 || '30000'}
                onChange={e => setFormData({ ...formData, limite_credito_r11: e.target.value })}
              >
                <option value="20000">$20.000</option>
                <option value="30000">$30.000</option>
                <option value="50000">$50.000</option>
                <option value="100000">$100.000</option>
              </select>
            </div>
            <div className="flex gap-2 mt-6">
              <button onClick={() => { setModal(null); setSelectedUser(null); }} className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 rounded-lg text-sm">Cancelar</button>
              <button
                onClick={handleApprove}
                disabled={processing}
                className="flex-1 bg-green-500 hover:bg-green-600 disabled:bg-gray-300 text-white font-bold py-2 rounded-lg text-sm"
              >
                {processing ? 'Aprobando...' : 'Aprobar'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Manual Payment Modal */}
      {modal === 'payment' && selectedUser && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onClick={() => { setModal(null); setSelectedUser(null); setFormData({}); }}>
          <div className="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-blue-700 mb-2">Pago Manual R11</h3>
            <p className="text-sm text-gray-600 mb-1">{selectedUser.name || selectedUser.nombre}</p>
            <p className="text-xs text-red-500 font-semibold mb-4">
              Deuda: ${fmt(selectedUser.credito_r11_usado)}
            </p>
            <div className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">Monto a pagar</label>
                <input
                  type="number"
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                  value={formData.amount || ''}
                  onChange={e => setFormData({ ...formData, amount: e.target.value })}
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">Método</label>
                <select
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                  value={formData.payment_method || 'cash'}
                  onChange={e => setFormData({ ...formData, payment_method: e.target.value })}
                >
                  <option value="cash">Efectivo</option>
                  <option value="transfer">Transferencia</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">Notas (opcional)</label>
                <input
                  type="text"
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                  value={formData.notes || ''}
                  onChange={e => setFormData({ ...formData, notes: e.target.value })}
                />
              </div>
            </div>
            <div className="flex gap-2 mt-6">
              <button onClick={() => { setModal(null); setSelectedUser(null); setFormData({}); }} className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 rounded-lg text-sm">Cancelar</button>
              <button
                onClick={handleManualPayment}
                disabled={processing || !formData.amount || parseFloat(formData.amount) <= 0}
                className="flex-1 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-300 text-white font-bold py-2 rounded-lg text-sm"
              >
                {processing ? 'Procesando...' : 'Procesar Pago'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
