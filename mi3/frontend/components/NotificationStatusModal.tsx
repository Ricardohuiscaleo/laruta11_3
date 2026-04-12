'use client';

import { useState } from 'react';
import { Bell, BellOff, BellRing, X, AlertTriangle, CheckCircle2, XCircle, Loader2 } from 'lucide-react';
import { usePushNotifications, type PushStatus } from '@/hooks/usePushNotifications';

const STATUS_CONFIG: Record<PushStatus, {
  icon: typeof Bell;
  color: string;
  label: string;
  description: string;
  canActivate: boolean;
}> = {
  loading: {
    icon: Loader2,
    color: 'text-gray-400',
    label: 'Verificando...',
    description: 'Comprobando el estado de las notificaciones.',
    canActivate: false,
  },
  unsupported: {
    icon: XCircle,
    color: 'text-gray-400',
    label: 'No soportado',
    description: 'Tu navegador no soporta notificaciones push. Prueba con Chrome o Safari.',
    canActivate: false,
  },
  'no-vapid': {
    icon: AlertTriangle,
    color: 'text-yellow-500',
    label: 'No configurado',
    description: 'Las notificaciones push aún no están configuradas en el servidor. Contacta al administrador.',
    canActivate: false,
  },
  denied: {
    icon: BellOff,
    color: 'text-red-500',
    label: 'Bloqueadas',
    description: 'Bloqueaste las notificaciones en tu navegador. Para activarlas, ve a la configuración de tu navegador y permite notificaciones para este sitio.',
    canActivate: false,
  },
  prompt: {
    icon: Bell,
    color: 'text-yellow-500',
    label: 'Inactivas',
    description: 'Las notificaciones no están activadas. Actívalas para recibir alertas de turnos, checklists y adelantos.',
    canActivate: true,
  },
  active: {
    icon: BellRing,
    color: 'text-green-500',
    label: 'Activas',
    description: 'Recibirás notificaciones de turnos, checklists, adelantos y más.',
    canActivate: false,
  },
  inactive: {
    icon: Bell,
    color: 'text-yellow-500',
    label: 'Inactivas',
    description: 'Tienes permiso pero no estás suscrito. Activa las notificaciones para recibir alertas.',
    canActivate: true,
  },
};

/** Small bell indicator for the header — click opens modal */
export function NotificationBellIndicator() {
  const { status } = usePushNotifications();
  const [open, setOpen] = useState(false);

  const config = STATUS_CONFIG[status];
  const isActive = status === 'active';

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="relative p-1 -m-1"
        aria-label="Estado de notificaciones"
      >
        <config.icon className={`w-5 h-5 ${isActive ? 'text-white' : 'text-white/50'}`} />
        {/* Dot indicator */}
        <span
          className={`absolute top-0 right-0 w-2 h-2 rounded-full border border-red-500 ${
            isActive ? 'bg-green-400' : status === 'denied' ? 'bg-red-400' : 'bg-yellow-400'
          }`}
        />
      </button>

      {open && <NotificationModal onClose={() => setOpen(false)} />}
    </>
  );
}

function NotificationModal({ onClose }: { onClose: () => void }) {
  const { status, activate } = usePushNotifications();
  const [activating, setActivating] = useState(false);
  const config = STATUS_CONFIG[status];
  const Icon = config.icon;

  async function handleActivate() {
    setActivating(true);
    await activate();
    setActivating(false);
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center" onClick={onClose}>
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black/40" />

      {/* Modal */}
      <div
        className="relative w-full sm:max-w-sm bg-white rounded-t-2xl sm:rounded-2xl p-6 pb-8 animate-in slide-in-from-bottom duration-200"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Close */}
        <button onClick={onClose} className="absolute top-4 right-4 text-gray-400">
          <X className="w-5 h-5" />
        </button>

        {/* Content */}
        <div className="flex flex-col items-center text-center gap-4">
          <div className={`w-14 h-14 rounded-full flex items-center justify-center ${
            status === 'active' ? 'bg-green-50' : status === 'denied' ? 'bg-red-50' : 'bg-yellow-50'
          }`}>
            <Icon className={`w-7 h-7 ${config.color} ${status === 'loading' ? 'animate-spin' : ''}`} />
          </div>

          <div>
            <h3 className="text-lg font-semibold text-gray-900">
              Notificaciones: {config.label}
            </h3>
            <p className="mt-1 text-sm text-gray-500">{config.description}</p>
          </div>

          {/* Status details */}
          <div className="w-full space-y-2 text-left text-sm">
            <StatusRow label="Service Worker" ok={status !== 'unsupported'} />
            <StatusRow label="Permiso del navegador" ok={status === 'active' || status === 'inactive'} />
            <StatusRow label="Suscripción push" ok={status === 'active'} />
            <StatusRow label="Configuración servidor" ok={status !== 'no-vapid' && status !== 'unsupported'} />
          </div>

          {/* Activate button */}
          {config.canActivate && (
            <button
              onClick={handleActivate}
              disabled={activating}
              className="w-full mt-2 py-3 px-4 bg-red-500 text-white font-medium rounded-xl hover:bg-red-600 disabled:opacity-50 transition-colors"
            >
              {activating ? 'Activando...' : '🔔 Activar Notificaciones'}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

function StatusRow({ label, ok }: { label: string; ok: boolean }) {
  return (
    <div className="flex items-center gap-2 py-1.5 px-3 rounded-lg bg-gray-50">
      {ok ? (
        <CheckCircle2 className="w-4 h-4 text-green-500 shrink-0" />
      ) : (
        <XCircle className="w-4 h-4 text-gray-300 shrink-0" />
      )}
      <span className={ok ? 'text-gray-700' : 'text-gray-400'}>{label}</span>
    </div>
  );
}
