'use client';

import { useState } from 'react';
import { X, CheckCircle2, XCircle, Loader2 } from 'lucide-react';
import { usePushNotifications, type PushStatus } from '@/hooks/usePushNotifications';

const STATUS_CONFIG: Record<PushStatus, {
  label: string;
  description: string;
  canActivate: boolean;
  dotColor: string; // tailwind bg class for the indicator dot
}> = {
  loading: {
    label: 'Verificando...',
    description: 'Comprobando el estado de las notificaciones.',
    canActivate: false,
    dotColor: 'bg-gray-400',
  },
  unsupported: {
    label: 'No soportado',
    description: 'Tu navegador no soporta notificaciones push. Prueba con Chrome o Safari.',
    canActivate: false,
    dotColor: 'bg-gray-400',
  },
  'no-vapid': {
    label: 'No configurado',
    description: 'Las notificaciones push aún no están configuradas en el servidor.',
    canActivate: false,
    dotColor: 'bg-gray-400',
  },
  denied: {
    label: 'Bloqueadas',
    description: 'Bloqueaste las notificaciones en tu navegador. Para activarlas, ve a configuración del navegador y permite notificaciones para este sitio.',
    canActivate: false,
    dotColor: 'bg-gray-400',
  },
  prompt: {
    label: 'Inactivas',
    description: 'Las notificaciones no están activadas. Actívalas para recibir alertas de turnos, checklists y adelantos.',
    canActivate: true,
    dotColor: 'bg-yellow-400',
  },
  active: {
    label: 'Activas',
    description: 'Recibirás notificaciones de turnos, checklists, adelantos y más.',
    canActivate: false,
    dotColor: 'bg-green-400',
  },
  inactive: {
    label: 'Inactivas',
    description: 'Tienes permiso pero no estás suscrito. Activa las notificaciones para recibir alertas.',
    canActivate: true,
    dotColor: 'bg-yellow-400',
  },
};

/** Tag-style indicator: black pill with "Notificaciones" + colored dot */
export function NotificationTagIndicator() {
  const { status } = usePushNotifications();
  const [open, setOpen] = useState(false);
  const config = STATUS_CONFIG[status];

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="flex items-center gap-1.5 bg-black/80 rounded-full px-2.5 py-1"
        aria-label="Estado de notificaciones"
      >
        <span className={`w-2 h-2 rounded-full ${config.dotColor}`} />
        <span className="text-[11px] font-medium text-white leading-none">Notificaciones</span>
      </button>

      {open && <NotificationModal onClose={() => setOpen(false)} />}
    </>
  );
}

function NotificationModal({ onClose }: { onClose: () => void }) {
  const { status, activate } = usePushNotifications();
  const [activating, setActivating] = useState(false);
  const config = STATUS_CONFIG[status];

  async function handleActivate() {
    setActivating(true);
    await activate();
    setActivating(false);
  }

  return (
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center p-4"
      onClick={onClose}
    >
      {/* Backdrop with blur */}
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" />

      {/* Modal */}
      <div
        className="relative w-full max-w-sm bg-white rounded-2xl p-6 shadow-2xl"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Close */}
        <button onClick={onClose} className="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
          <X className="w-5 h-5" />
        </button>

        {/* Content */}
        <div className="flex flex-col items-center text-center gap-4">
          {/* Status dot large */}
          <div className="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center">
            {status === 'loading' ? (
              <Loader2 className="w-6 h-6 text-gray-400 animate-spin" />
            ) : (
              <span className={`w-5 h-5 rounded-full ${config.dotColor}`} />
            )}
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
