'use client';

import { useEffect, useState, useCallback, useRef } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, Plus, X, Pencil, ToggleLeft, ToggleRight, User, RotateCw, Search } from 'lucide-react';
import type { Personal, ApiResponse } from '@/types';
import type { SectionHeaderConfig } from '@/components/admin/AdminShell';
import type { CustomerUser } from '@/types/admin';

const SUELDO_BASE_DEFECTO = 300000;
const ROLES_VALIDOS = ['cajero', 'planchero', 'admin', 'seguridad'] as const;
type RolValido = typeof ROLES_VALIDOS[number];

type PersonalTab = 'work' | 'clientes';

interface PersonalSectionProps {
  onHeaderConfig?: (config: SectionHeaderConfig) => void;
}

export default function PersonalSection({ onHeaderConfig }: PersonalSectionProps) {
  /* ─── Tab state ─── */
  const [activeTab, setActiveTab] = useState<PersonalTab>('work');

  /* ─── Work tab state (existing) ─── */
  const [personal, setPersonal] = useState<Personal[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<Personal | null>(null);
  const [form, setForm] = useState({ nombre: '', rol: '', sueldo_base_cajero: 0, sueldo_base_planchero: 0, sueldo_base_admin: 0, sueldo_base_seguridad: 0 });
  const [submitting, setSubmitting] = useState(false);
  const [touchedSueldos, setTouchedSueldos] = useState<Set<string>>(new Set());

  /* ─── Clientes tab state (new) ─── */
  const [clientesData, setClientesData] = useState<CustomerUser[] | null>(null);
  const [clientesLoading, setClientesLoading] = useState(false);
  const [clientesError, setClientesError] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleTabChange = useCallback((key: string) => {
    setActiveTab(key as PersonalTab);
  }, []);

  /* ─── Register tabs via onHeaderConfig ─── */
  useEffect(() => {
    onHeaderConfig?.({
      tabs: [
        { key: 'work', label: 'Ruta 11 Work' },
        { key: 'clientes', label: 'Clientes' },
      ],
      activeTab,
      onTabChange: handleTabChange,
      accent: 'red',
    });
  }, [activeTab, handleTabChange, onHeaderConfig]);

  /* ─── Work tab data fetch ─── */
  const fetchData = useCallback(() => {
    setLoading(true);
    apiFetch<ApiResponse<Personal[]>>('/admin/personal')
      .then(res => setPersonal(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  /* ─── Clientes tab data fetch (lazy load) ─── */
  const fetchClientes = useCallback((search?: string) => {
    setClientesLoading(true);
    setClientesError('');
    const qs = search ? `?search=${encodeURIComponent(search)}` : '';
    apiFetch<{ data: CustomerUser[] }>(`/admin/users/customers${qs}`)
      .then(res => setClientesData(res.data || []))
      .catch(e => setClientesError(e.message))
      .finally(() => setClientesLoading(false));
  }, []);

  // Lazy load clientes on first tab activation
  useEffect(() => {
    if (activeTab === 'clientes' && clientesData === null) {
      fetchClientes();
    }
  }, [activeTab, clientesData, fetchClientes]);

  /* ─── Search with debounce ─── */
  const handleSearchChange = (value: string) => {
    setSearchQuery(value);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      fetchClientes(value);
    }, 300);
  };

  // Cleanup debounce on unmount
  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  /* ─── Work tab handlers (existing logic preserved) ─── */
  const openAdd = () => {
    setEditing(null);
    setForm({ nombre: '', rol: '', sueldo_base_cajero: 0, sueldo_base_planchero: 0, sueldo_base_admin: 0, sueldo_base_seguridad: 0 });
    setTouchedSueldos(new Set());
    setShowModal(true);
  };

  const handleRolChange = (rolValue: string) => {
    const roles = rolValue.split(',').map(r => r.trim().toLowerCase()).filter(Boolean);
    const sueldoUpdates: Record<string, number> = {};

    if (!editing) {
      for (const role of ROLES_VALIDOS) {
        const key = `sueldo_base_${role}`;
        const isRoleSelected = roles.includes(role);
        const wasTouched = touchedSueldos.has(key);

        if (isRoleSelected && !wasTouched) {
          sueldoUpdates[key] = SUELDO_BASE_DEFECTO;
        } else if (!isRoleSelected && !wasTouched) {
          sueldoUpdates[key] = 0;
        }
      }
    }

    setForm(f => ({ ...f, rol: rolValue, ...sueldoUpdates }));
  };

  const handleSueldoChange = (role: RolValido, value: number) => {
    const key = `sueldo_base_${role}`;
    if (!editing) {
      setTouchedSueldos(prev => new Set(prev).add(key));
    }
    setForm(f => ({ ...f, [key]: value }));
  };

  const openEdit = (p: Personal) => {
    setEditing(p);
    setForm({ nombre: p.nombre, rol: p.rol, sueldo_base_cajero: p.sueldo_base_cajero, sueldo_base_planchero: p.sueldo_base_planchero, sueldo_base_admin: p.sueldo_base_admin, sueldo_base_seguridad: p.sueldo_base_seguridad });
    setShowModal(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const payload = { ...form, rol: form.rol.split(',').map(r => r.trim()).filter(Boolean) };
      if (editing) {
        await apiFetch(`/admin/personal/${editing.id}`, { method: 'PUT', body: JSON.stringify(payload) });
      } else {
        await apiFetch('/admin/personal', { method: 'POST', body: JSON.stringify({ ...payload, activo: 1 }) });
      }
      setShowModal(false);
      fetchData();
    } catch (err: any) {
      alert(err.message);
    } finally {
      setSubmitting(false);
    }
  };

  const toggleActive = async (p: Personal) => {
    try {
      await apiFetch(`/admin/personal/${p.id}/toggle`, { method: 'PATCH' });
      fetchData();
    } catch (err: any) { alert(err.message); }
  };

  const rotateFoto = async (id: number, deg: number) => {
    const rotation = deg % 360;
    try {
      await apiFetch(`/admin/personal/${id}/rotate-foto`, {
        method: 'PATCH',
        body: JSON.stringify({ rotation }),
      });
      setPersonal(prev => prev.map(p => p.id === id ? { ...p, foto_rotation: rotation } : p));
    } catch {}
  };

  /* ─── Render Work tab ─── */
  const renderWork = () => {
    if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
    if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

    return (
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="hidden md:block text-2xl font-bold text-gray-900">Personal</h2>
          <button onClick={openAdd} className="flex items-center gap-1 rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700">
            <Plus className="h-4 w-4" /> Agregar
          </button>
        </div>

        <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
          <table className="w-full text-sm">
            <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
              <tr>
                <th className="px-4 py-3">Nombre</th>
                <th className="px-4 py-3">Roles</th>
                <th className="px-4 py-3 hidden sm:table-cell">Sueldos</th>
                <th className="px-4 py-3">Estado</th>
                <th className="px-4 py-3">Acciones</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {personal.map(p => (
                <tr key={p.id}>
                  <td className="px-4 py-3 font-medium">
                    <div className="flex items-center gap-2">
                      {p.foto_url ? (
                        <div className="relative group">
                          <img src={p.foto_url} alt="" className="h-8 w-8 rounded-full object-cover"
                            style={{ transform: `rotate(${p.foto_rotation || 0}deg)` }} />
                          <button onClick={(e) => { e.stopPropagation(); rotateFoto(p.id, (p.foto_rotation || 0) + 90); }}
                            className="absolute -bottom-1 -right-1 hidden group-hover:flex h-4 w-4 items-center justify-center rounded-full bg-white shadow text-gray-500 hover:text-amber-600"
                            title="Rotar foto">
                            <RotateCw className="h-2.5 w-2.5" />
                          </button>
                        </div>
                      ) : (
                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100">
                          <User className="h-4 w-4 text-amber-600" />
                        </div>
                      )}
                      {p.nombre}
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex flex-wrap gap-1">
                      {p.rol.split(',').map(r => r.trim()).filter(Boolean).map(r => (
                        <span key={r} className="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">{r}</span>
                      ))}
                    </div>
                  </td>
                  <td className="px-4 py-3 hidden sm:table-cell text-xs text-gray-500">
                    {[
                      p.sueldo_base_cajero > 0 && `Caj: ${formatCLP(p.sueldo_base_cajero)}`,
                      p.sueldo_base_seguridad > 0 && `Seg: ${formatCLP(p.sueldo_base_seguridad)}`,
                      p.sueldo_base_planchero > 0 && `Pla: ${formatCLP(p.sueldo_base_planchero)}`,
                      p.sueldo_base_admin > 0 && `Adm: ${formatCLP(p.sueldo_base_admin)}`,
                    ].filter(Boolean).join(' · ') || '—'}
                  </td>
                  <td className="px-4 py-3">
                    <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', p.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500')}>
                      {p.activo ? 'Activo' : 'Inactivo'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1">
                      <button onClick={() => openEdit(p)} className="rounded p-1 hover:bg-gray-100" title="Editar"><Pencil className="h-4 w-4 text-gray-500" /></button>
                      <button onClick={() => toggleActive(p)} className="rounded p-1 hover:bg-gray-100" title={p.activo ? 'Desactivar' : 'Activar'}>
                        {p.activo ? <ToggleRight className="h-4 w-4 text-green-500" /> : <ToggleLeft className="h-4 w-4 text-gray-400" />}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  };

  /* ─── Render Clientes tab ─── */
  const renderClientes = () => {
    if (clientesLoading && clientesData === null) {
      return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-red-500" /></div>;
    }
    if (clientesError) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{clientesError}</div>;

    return (
      <div className="space-y-3">
        {/* Search input */}
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" aria-hidden="true" />
          <input
            type="search"
            value={searchQuery}
            onChange={e => handleSearchChange(e.target.value)}
            placeholder="Buscar por nombre o email..."
            aria-label="Buscar clientes"
            className="w-full rounded-lg border bg-white pl-10 pr-4 py-2.5 text-sm placeholder:text-gray-400 focus:border-red-300 focus:outline-none focus:ring-2 focus:ring-red-100"
          />
          {clientesLoading && (
            <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-gray-400" />
          )}
        </div>

        {/* Table */}
        {clientesData && clientesData.length === 0 ? (
          <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
            <p className="text-sm text-gray-500">No se encontraron clientes</p>
          </div>
        ) : (
          <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
                <tr>
                  <th className="px-4 py-3">Nombre</th>
                  <th className="px-4 py-3 hidden sm:table-cell">Email</th>
                  <th className="px-4 py-3 hidden md:table-cell">Teléfono</th>
                  <th className="px-4 py-3">Pedidos</th>
                  <th className="px-4 py-3">Total Gastado</th>
                  <th className="px-4 py-3 hidden sm:table-cell">Último Pedido</th>
                  <th className="px-4 py-3">Estado</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {clientesData?.map(c => (
                  <tr key={c.id}>
                    <td className="px-4 py-3 font-medium">
                      <div>
                        <div>{c.nombre}</div>
                        <div className="sm:hidden text-xs text-gray-400">{c.email}</div>
                      </div>
                    </td>
                    <td className="px-4 py-3 text-gray-600 hidden sm:table-cell">{c.email}</td>
                    <td className="px-4 py-3 text-gray-600 hidden md:table-cell">{c.telefono || '—'}</td>
                    <td className="px-4 py-3 text-gray-700">{c.total_orders}</td>
                    <td className="px-4 py-3 font-medium text-gray-900">{formatCLP(c.total_spent)}</td>
                    <td className="px-4 py-3 text-gray-500 text-xs hidden sm:table-cell">
                      {c.last_order_date
                        ? new Date(c.last_order_date).toLocaleDateString('es-CL')
                        : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <span className={cn(
                        'rounded-full px-2 py-0.5 text-xs font-medium',
                        c.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                      )}>
                        {c.activo ? 'Activo' : 'Inactivo'}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    );
  };

  /* ─── Main render ─── */
  return (
    <div className="space-y-4 pt-4">
      {activeTab === 'work' && renderWork()}
      {activeTab === 'clientes' && renderClientes()}

      {/* Modal for add/edit worker (Work tab only) */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-label={editing ? 'Editar Trabajador' : 'Agregar Trabajador'}>
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-bold">{editing ? 'Editar' : 'Agregar'} Trabajador</h2>
              <button onClick={() => setShowModal(false)} aria-label="Cerrar"><X className="h-5 w-5 text-gray-400" /></button>
            </div>
            <form onSubmit={handleSubmit} className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-gray-700">Nombre</label>
                <input required value={form.nombre} onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))}
                  className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm" />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700">Roles (separados por coma)</label>
                <input required value={form.rol} onChange={e => handleRolChange(e.target.value)}
                  className="mt-1 block w-full rounded-lg border px-3 py-2 text-sm" placeholder="cajero, planchero" />
              </div>
              <div className="grid grid-cols-2 gap-2">
                {ROLES_VALIDOS.map(role => (
                  <div key={role}>
                    <label className="block text-xs text-gray-500 capitalize">Sueldo {role}</label>
                    <input type="number" value={form[`sueldo_base_${role}`]} onChange={e => handleSueldoChange(role, Number(e.target.value))}
                      className="mt-1 block w-full rounded-lg border px-3 py-1.5 text-sm" />
                  </div>
                ))}
              </div>
              <button type="submit" disabled={submitting} className="w-full rounded-lg bg-amber-600 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">
                {submitting ? 'Guardando...' : 'Guardar'}
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
