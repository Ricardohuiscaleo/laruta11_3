'use client';

import { Wifi, WifiOff, Package, Users, Truck } from 'lucide-react';
import type { DeliveryMetrics as DeliveryMetricsType } from '@/hooks/useDeliveryTracking';

interface DeliveryMetricsProps {
  metrics: DeliveryMetricsType;
  isConnected: boolean;
  compact?: boolean;
}

export default function DeliveryMetrics({ metrics, isConnected, compact }: DeliveryMetricsProps) {
  const items = [
    { label: 'Pedidos', value: metrics.totalActive, icon: Package, color: 'text-blue-600' },
    { label: 'Disponibles', value: metrics.availableRiders, icon: Users, color: 'text-green-600' },
    { label: 'En ruta', value: metrics.ridersOnRoute, icon: Truck, color: 'text-amber-600' },
    {
      label: isConnected ? 'En línea' : 'Desconectado',
      value: isConnected ? '●' : '○',
      icon: isConnected ? Wifi : WifiOff,
      color: isConnected ? 'text-green-600' : 'text-red-600',
    },
  ];

  // Compact: single horizontal bar for mobile
  if (compact) {
    return (
      <div className="flex items-center justify-around bg-white border-b border-gray-100 px-2 py-2 shrink-0">
        {items.map((item) => {
          const Icon = item.icon;
          return (
            <div key={item.label} className="flex flex-col items-center gap-0.5">
              <div className="flex items-center gap-1">
                <Icon className={`h-3.5 w-3.5 ${item.color}`} />
                <span className={`text-sm font-bold ${item.color}`}>{item.value}</span>
              </div>
              <span className="text-[10px] text-gray-400">{item.label}</span>
            </div>
          );
        })}
      </div>
    );
  }

  // Full: 2x2 grid for desktop
  const cards = [
    { label: 'Pedidos activos', value: metrics.totalActive, icon: Package, bg: 'bg-blue-50', color: 'text-blue-600' },
    { label: 'Riders disponibles', value: metrics.availableRiders, icon: Users, bg: 'bg-green-50', color: 'text-green-600' },
    { label: 'Riders en ruta', value: metrics.ridersOnRoute, icon: Truck, bg: 'bg-amber-50', color: 'text-amber-600' },
    {
      label: 'Conexión',
      value: isConnected ? 'En línea' : 'Desconectado',
      icon: isConnected ? Wifi : WifiOff,
      bg: isConnected ? 'bg-green-50' : 'bg-red-50',
      color: isConnected ? 'text-green-600' : 'text-red-600',
    },
  ];

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
      {cards.map((card) => {
        const Icon = card.icon;
        return (
          <div key={card.label} className={`rounded-xl ${card.bg} p-4`}>
            <div className="flex items-center gap-2 mb-1">
              <Icon className={`h-4 w-4 ${card.color}`} />
              <span className="text-xs font-medium text-gray-500">{card.label}</span>
            </div>
            <p className={`text-xl font-bold ${card.color}`}>{card.value}</p>
          </div>
        );
      })}
    </div>
  );
}
