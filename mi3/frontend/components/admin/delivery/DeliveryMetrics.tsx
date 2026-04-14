'use client';

import { Wifi, WifiOff, Package, Users, Truck } from 'lucide-react';
import type { DeliveryMetrics as DeliveryMetricsType } from '@/hooks/useDeliveryTracking';

interface DeliveryMetricsProps {
  metrics: DeliveryMetricsType;
  isConnected: boolean;
}

export default function DeliveryMetrics({ metrics, isConnected }: DeliveryMetricsProps) {
  const cards = [
    {
      label: 'Pedidos activos',
      value: metrics.totalActive,
      icon: Package,
      bg: 'bg-blue-50',
      color: 'text-blue-600',
    },
    {
      label: 'Riders disponibles',
      value: metrics.availableRiders,
      icon: Users,
      bg: 'bg-green-50',
      color: 'text-green-600',
    },
    {
      label: 'Riders en ruta',
      value: metrics.ridersOnRoute,
      icon: Truck,
      bg: 'bg-amber-50',
      color: 'text-amber-600',
    },
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
