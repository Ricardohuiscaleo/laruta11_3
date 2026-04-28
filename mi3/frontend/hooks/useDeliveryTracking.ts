'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import { getEcho } from '@/lib/echo';
import { apiFetch } from '@/lib/api';

export interface DeliveryOrder {
  id: number;
  order_number: string;
  order_status: string;
  customer_name: string;
  delivery_address: string;
  delivery_fee: number;
  rider_id: number | null;
  rider_nombre: string | null;
  rider_last_lat: number | null;
  rider_last_lng: number | null;
  estimated_delivery_time: string | null;
  created_at: string;
  rider_url: string | null;
}

export interface DeliveryRider {
  id: number;
  nombre: string;
  foto_url: string | null;
  last_lat: number | null;
  last_lng: number | null;
  last_location_at: string | null;
}

export interface DeliveryMetrics {
  totalActive: number;
  availableRiders: number;
  ridersOnRoute: number;
}

interface RiderLocationPayload {
  rider_id: number;
  nombre: string;
  latitud: number;
  longitud: number;
  timestamp: string;
  pedido_asignado_id: number | null;
}

interface OrderStatusPayload {
  order_id: number;
  order_number: string;
  order_status: string;
  rider_id: number | null;
  estimated_delivery_time: string | null;
  updated_at: string;
}

export function useDeliveryTracking() {
  const [orders, setOrders] = useState<DeliveryOrder[]>([]);
  const [riders, setRiders] = useState<DeliveryRider[]>([]);
  const [isConnected, setIsConnected] = useState(false);

  // Carga inicial
  useEffect(() => {
    let cancelled = false;

    async function load() {
      try {
        const [ordersRes, ridersRes] = await Promise.all([
          apiFetch<{ success: boolean; orders: DeliveryOrder[] }>('/admin/delivery/orders'),
          apiFetch<{ success: boolean; riders: DeliveryRider[] }>('/admin/delivery/riders'),
        ]);
        if (cancelled) return;
        if (ordersRes.orders) setOrders(ordersRes.orders);
        if (ridersRes.riders) setRiders(ridersRes.riders);
      } catch {
        // silently fail
      }
    }

    load();
    return () => { cancelled = true; };
  }, []);

  // Suscripción Echo
  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;

    const channel = echo.private('delivery.monitor');

    const pusher = (echo.connector as any)?.pusher;
    if (pusher) {
      pusher.connection.bind('connected', () => setIsConnected(true));
      pusher.connection.bind('disconnected', () => setIsConnected(false));
      setIsConnected(pusher.connection.state === 'connected');
    }

    channel.listen('.rider.location.updated', (data: RiderLocationPayload) => {
      setRiders(prev =>
        prev.map(r =>
          r.id === data.rider_id
            ? { ...r, last_lat: data.latitud, last_lng: data.longitud }
            : r
        )
      );
      if (data.pedido_asignado_id) {
        setOrders(prev =>
          prev.map(o =>
            o.id === data.pedido_asignado_id
              ? { ...o, rider_last_lat: data.latitud, rider_last_lng: data.longitud }
              : o
          )
        );
      }
    });

    channel.listen('.order.status.updated', (data: OrderStatusPayload) => {
      setOrders(prev =>
        prev.map(o =>
          o.id === data.order_id
            ? {
                ...o,
                order_status: data.order_status,
                rider_id: data.rider_id ?? o.rider_id,
                estimated_delivery_time: data.estimated_delivery_time,
              }
            : o
        )
      );
    });

    return () => {
      echo.leave('delivery.monitor');
    };
  }, []);

  const metrics: DeliveryMetrics = {
    totalActive: orders.filter(o =>
      !['delivered', 'completed', 'cancelled'].includes(o.order_status)
    ).length,
    availableRiders: riders.filter(r => r.last_lat !== null).length,
    ridersOnRoute: orders.filter(o => o.order_status === 'out_for_delivery').length,
  };

  const assignRider = useCallback(async (orderId: number, riderId: number) => {
    await apiFetch(`/admin/delivery/orders/${orderId}/assign-rider`, {
      method: 'POST',
      body: JSON.stringify({ rider_id: riderId }),
    });
  }, []);

  const updateStatus = useCallback(async (orderId: number, status: string) => {
    await apiFetch(`/admin/delivery/orders/${orderId}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status }),
    });
  }, []);

  return { orders, riders, metrics, isConnected, assignRider, updateStatus };
}
