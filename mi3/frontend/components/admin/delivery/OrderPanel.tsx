'use client';

import { useState } from 'react';
import { MapPin, User, Clock, Copy, QrCode, Check, Phone, Navigation, DollarSign } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import type { DeliveryOrder, DeliveryRider } from '@/hooks/useDeliveryTracking';

const STATUS_LABELS: Record<string, string> = {
  sent_to_kitchen: 'Enviado',
  preparing: 'Preparando',
  ready: 'Listo',
  out_for_delivery: 'En camino',
  delivered: 'Entregado',
};

const STATUS_COLORS: Record<string, string> = {
  sent_to_kitchen: 'bg-gray-100 text-gray-700',
  preparing: 'bg-yellow-100 text-yellow-700',
  ready: 'bg-green-100 text-green-700',
  out_for_delivery: 'bg-blue-100 text-blue-700',
  delivered: 'bg-purple-100 text-purple-700',
};

const fmt = (n: number) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(n);

const FILTER_OPTIONS = [
  { value: 'all', label: 'Todos' },
  { value: 'preparing', label: 'Preparando' },
  { value: 'ready', label: 'Listo' },
  { value: 'out_for_delivery', label: 'En camino' },
];

interface OrderPanelProps {
  orders: DeliveryOrder[];
  riders: DeliveryRider[];
  onAssignRider: (orderId: number, riderId: number) => void;
  onUpdateStatus: (orderId: number, status: string) => void;
}

export default function OrderPanel({ orders, riders, onAssignRider, onUpdateStatus }: OrderPanelProps) {
  const [statusFilter, setStatusFilter] = useState('all');
  const [riderFilter, setRiderFilter] = useState('all');
  const [selectedOrder, setSelectedOrder] = useState<number | null>(null);
  const [assigningRider, setAssigningRider] = useState<number | null>(null);
  const [showQR, setShowQR] = useState<number | null>(null);
  const [copiedId, setCopiedId] = useState<number | null>(null);

  const copyRiderUrl = async (orderId: number, url: string) => {
    try {
      await navigator.clipboard.writeText(url);
      setCopiedId(orderId);
      setTimeout(() => setCopiedId(null), 2000);
    } catch { /* fallback: noop */ }
  };

  const filtered = orders.filter((o) => {
    if (statusFilter !== 'all' && o.order_status !== statusFilter) return false;
    if (riderFilter !== 'all' && String(o.rider_id) !== riderFilter) return false;
    return true;
  });

  const availableRiders = riders.filter((r) => r.last_lat !== null);

  const handleAssign = async (orderId: number, riderId: number) => {
    setAssigningRider(riderId);
    try {
      await onAssignRider(orderId, riderId);
      setSelectedOrder(null);
    } finally {
      setAssigningRider(null);
    }
  };

  return (
    <div className="flex flex-col h-full bg-white rounded-xl border shadow-sm overflow-hidden">
      <div className="p-4 border-b space-y-3">
        <h2 className="font-semibold text-gray-900">Pedidos activos</h2>

        {/* Filters */}
        <div className="flex flex-wrap gap-1">
          {FILTER_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              onClick={() => setStatusFilter(opt.value)}
              className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                statusFilter === opt.value
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>

        <select
          value={riderFilter}
          onChange={(e) => setRiderFilter(e.target.value)}
          className="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
        >
          <option value="all">Todos los riders</option>
          {riders.map((r) => (
            <option key={r.id} value={String(r.id)}>
              {r.nombre}
            </option>
          ))}
        </select>
      </div>

      <div className="flex-1 overflow-y-auto divide-y">
        {filtered.length === 0 ? (
          <p className="p-6 text-center text-sm text-gray-400">Sin pedidos</p>
        ) : (
          filtered.map((order) => {
            const isSelected = selectedOrder === order.id;
            const statusLabel = STATUS_LABELS[order.order_status] ?? order.order_status;
            const statusColor = STATUS_COLORS[order.order_status] ?? 'bg-gray-100 text-gray-700';

            return (
              <div key={order.id} className="p-4 hover:bg-gray-50 transition-colors">
                <button
                  className="w-full text-left"
                  onClick={() => setSelectedOrder(isSelected ? null : order.id)}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="text-sm font-semibold text-gray-900 truncate">
                        #{order.order_number}
                      </p>
                      <p className="text-xs text-gray-500 truncate">{order.customer_name}</p>
                    </div>
                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${statusColor}`}>
                      {statusLabel}
                    </span>
                  </div>

                  <div className="mt-2 flex items-center gap-1 text-xs text-gray-500">
                    <MapPin className="h-3 w-3 shrink-0" />
                    <span className="truncate">{order.delivery_address}</span>
                  </div>

                  {order.rider_nombre && (
                    <div className="mt-1 flex items-center gap-1 text-xs text-gray-500">
                      <User className="h-3 w-3 shrink-0" />
                      <span>{order.rider_nombre}</span>
                    </div>
                  )}

                  {order.estimated_delivery_time && (
                    <div className="mt-1 flex items-center gap-1 text-xs text-gray-500">
                      <Clock className="h-3 w-3 shrink-0" />
                      <span>
                        {new Date(order.estimated_delivery_time).toLocaleTimeString('es-CL', {
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </span>
                    </div>
                  )}

                  {/* Distance & duration */}
                  {(order.delivery_distance_km != null || order.delivery_duration_min != null) && (
                    <div className="mt-1 flex items-center gap-2 text-xs text-gray-500">
                      {order.delivery_distance_km != null && (
                        <span className="flex items-center gap-0.5">
                          <Navigation className="h-3 w-3 shrink-0" />
                          {order.delivery_distance_km} km
                        </span>
                      )}
                      {order.delivery_duration_min != null && (
                        <span className="flex items-center gap-0.5">
                          <Clock className="h-3 w-3 shrink-0" />
                          {order.delivery_duration_min} min
                        </span>
                      )}
                    </div>
                  )}

                  {/* Total & payment */}
                  {order.product_price != null && (
                    <div className="mt-1 flex items-center gap-1 text-xs">
                      <DollarSign className="h-3 w-3 shrink-0 text-green-600" />
                      <span className="font-semibold text-green-700">{fmt(order.product_price)}</span>
                      {order.payment_method && (
                        <span className="text-gray-400 ml-1">
                          ({order.payment_method === 'cash' ? 'Efectivo' : order.payment_method === 'card' ? 'Tarjeta' : order.payment_method === 'transfer' ? 'Transferencia' : order.payment_method})
                        </span>
                      )}
                    </div>
                  )}
                </button>

                {/* Phone call button */}
                {isSelected && order.customer_phone && (
                  <a
                    href={`tel:${order.customer_phone}`}
                    className="mt-2 flex items-center gap-1.5 rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-100 transition-colors"
                    aria-label={`Llamar a ${order.customer_name}`}
                  >
                    <Phone className="h-3 w-3" />
                    Llamar cliente
                  </a>
                )}

                {/* Rider URL actions */}
                {isSelected && order.rider_url && (
                  <div className="mt-3 flex items-center gap-2">
                    <button
                      onClick={() => copyRiderUrl(order.id, order.rider_url!)}
                      className="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 transition-colors"
                      aria-label="Copiar link del rider"
                    >
                      {copiedId === order.id ? <Check className="h-3 w-3 text-green-600" /> : <Copy className="h-3 w-3" />}
                      {copiedId === order.id ? 'Copiado' : 'Link rider'}
                    </button>
                    <button
                      onClick={() => setShowQR(showQR === order.id ? null : order.id)}
                      className={`flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors ${
                        showQR === order.id
                          ? 'border-blue-300 bg-blue-50 text-blue-700'
                          : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100'
                      }`}
                      aria-label="Mostrar código QR"
                    >
                      <QrCode className="h-3 w-3" />
                      QR
                    </button>
                  </div>
                )}

                {/* QR Code */}
                {showQR === order.id && order.rider_url && (
                  <div className="mt-2 flex justify-center rounded-lg bg-white border p-3">
                    <QRCodeSVG value={order.rider_url} size={160} />
                  </div>
                )}

                {/* Rider assignment selector */}
                {isSelected && !order.rider_id && (
                  <div className="mt-3 space-y-2">
                    <p className="text-xs font-medium text-gray-700">Asignar rider:</p>
                    {availableRiders.length === 0 ? (
                      <p className="text-xs text-gray-400">Sin riders disponibles</p>
                    ) : (
                      <div className="space-y-1">
                        {availableRiders.map((rider) => (
                          <button
                            key={rider.id}
                            onClick={() => handleAssign(order.id, rider.id)}
                            disabled={assigningRider !== null}
                            className="w-full rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-left text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50 transition-colors"
                          >
                            {assigningRider === rider.id ? 'Asignando...' : rider.nombre}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                )}
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}
