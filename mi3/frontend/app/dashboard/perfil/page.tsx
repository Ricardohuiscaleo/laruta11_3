'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP } from '@/lib/utils';
import { User, CreditCard, Shield, Loader2 } from 'lucide-react';
import type { ApiResponse } from '@/types';

interface ProfileData {
  nombre: string;
  email: string;
  telefono: string;
  rut: string;
  rol: string[];
  foto_perfil: string | null;
  fecha_registro: string;
  sueldos_base: Record<string, number>;
  credito_r11: {
    activo: boolean;
    aprobado: boolean;
    bloqueado: boolean;
    limite: number;
    usado: number;
    disponible: number;
  } | null;
}

export default function PerfilPage() {
  const [profile, setProfile] = useState<ProfileData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    apiFetch<ApiResponse<ProfileData>>('/worker/profile')
      .then(res => { if (res.data) setProfile(res.data); })
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;
  if (!profile) return null;

  const roleLabels: Record<string, string> = { cajero: 'Cajero/a', planchero: 'Planchero/a', seguridad: 'Seguridad', administrador: 'Admin', 'dueño': 'Dueño/a' };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Mi Perfil</h1>

      <div className="rounded-xl border bg-white p-5 shadow-sm">
        <div className="flex items-center gap-4">
          {profile.foto_perfil ? (
            <img src={profile.foto_perfil} alt="" className="h-16 w-16 rounded-full object-cover" />
          ) : (
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
              <User className="h-8 w-8 text-amber-600" />
            </div>
          )}
          <div>
            <h2 className="text-lg font-bold">{profile.nombre}</h2>
            <div className="flex flex-wrap gap-1.5 mt-1">
              {profile.rol.map(r => (
                <span key={r} className="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                  {roleLabels[r] || r}
                </span>
              ))}
            </div>
          </div>
        </div>

        <dl className="mt-5 grid gap-3 sm:grid-cols-2">
          <div><dt className="text-xs text-gray-500">Email</dt><dd className="text-sm font-medium">{profile.email}</dd></div>
          <div><dt className="text-xs text-gray-500">Teléfono</dt><dd className="text-sm font-medium">{profile.telefono || '—'}</dd></div>
          <div><dt className="text-xs text-gray-500">RUT</dt><dd className="text-sm font-medium">{profile.rut || '—'}</dd></div>
          <div><dt className="text-xs text-gray-500">Registro</dt><dd className="text-sm font-medium">{profile.fecha_registro || '—'}</dd></div>
        </dl>
      </div>

      {/* Sueldos base */}
      <div className="rounded-xl border bg-white p-5 shadow-sm">
        <h3 className="flex items-center gap-2 font-semibold text-gray-900">
          <Shield className="h-4 w-4 text-amber-600" /> Sueldos Base
        </h3>
        <div className="mt-3 grid gap-2 sm:grid-cols-2">
          {Object.entries(profile.sueldos_base).filter(([, v]) => v > 0).map(([role, amount]) => (
            <div key={role} className="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
              <span className="text-sm capitalize">{role}</span>
              <span className="font-semibold text-sm">{formatCLP(amount)}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Credit R11 */}
      {profile.credito_r11 && profile.credito_r11.activo && (
        <div className="rounded-xl border bg-white p-5 shadow-sm">
          <h3 className="flex items-center gap-2 font-semibold text-gray-900">
            <CreditCard className="h-4 w-4 text-amber-600" /> Crédito R11
          </h3>
          {profile.credito_r11.bloqueado && (
            <div className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-600">
              ⚠️ Tu crédito está bloqueado
            </div>
          )}
          <div className="mt-3 grid grid-cols-3 gap-3 text-center">
            <div className="rounded-lg bg-gray-50 p-3">
              <p className="text-xs text-gray-500">Límite</p>
              <p className="text-sm font-bold">{formatCLP(profile.credito_r11.limite)}</p>
            </div>
            <div className="rounded-lg bg-gray-50 p-3">
              <p className="text-xs text-gray-500">Usado</p>
              <p className="text-sm font-bold">{formatCLP(profile.credito_r11.usado)}</p>
            </div>
            <div className="rounded-lg bg-green-50 p-3">
              <p className="text-xs text-gray-500">Disponible</p>
              <p className="text-sm font-bold text-green-600">{formatCLP(profile.credito_r11.disponible)}</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
