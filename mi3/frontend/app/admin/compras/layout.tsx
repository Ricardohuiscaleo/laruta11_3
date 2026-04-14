'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { Plus, FileText, Package, TrendingUp, BarChart3, Wifi, WifiOff } from 'lucide-react';
import { ComprasProvider } from '@/contexts/ComprasContext';
import { useEffect, useState, useCallback, useRef } from 'react';
import { getEcho } from '@/lib/echo';

const tabs = [
  { href: '/admin/compras/registro', label: 'Registro', icon: Plus },
  { href: '/admin/compras/historial', label: 'Historial', icon: FileText },
  { href: '/admin/compras/stock', label: 'Stock', icon: Package },
  { href: '/admin/compras/proyeccion', label: 'Proyección', icon: TrendingUp },
  { href: '/admin/compras/kpis', label: 'KPIs', icon: BarChart3 },
];

interface CompraEvent {
  compra_id: number;
  proveedor: string;
  monto_total: number;
  items_count: number;
  timestamp: string;
}

export default function ComprasLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [wsConnected, setWsConnected] = useState(false);
  const [toast, setToast] = useState<CompraEvent | null>(null);
  const toastTimer = useRef<ReturnType<typeof setTimeout>>();
  const reconnectAttempt = useRef(0);
  const reconnectTimer = useRef<ReturnType<typeof setTimeout>>();

  const showToast = useCallback((event: CompraEvent) => {
    setToast(event);
    if (toastTimer.current) clearTimeout(toastTimer.current);
    toastTimer.current = setTimeout(() => setToast(null), 5000);
  }, []);

  const refreshData = useCallback(() => {
    // Trigger a soft refresh of the current page data
    router.refresh();
  }, [router]);

  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;

    const connectChannel = () => {
      try {
        const channel = echo.channel('compras');

        channel.listen('.compra.registrada', (event: CompraEvent) => {
          showToast(event);
          refreshData();
        });

        setWsConnected(true);
        reconnectAttempt.current = 0;
      } catch {
        setWsConnected(false);
        scheduleReconnect();
      }
    };

    const scheduleReconnect = () => {
      const delay = Math.min(1000 * Math.pow(2, reconnectAttempt.current), 30000);
      reconnectAttempt.current++;
      reconnectTimer.current = setTimeout(() => {
        connectChannel();
      }, delay);
    };

    // Monitor connection state via the underlying connector
    const connector = (echo as any).connector;
    if (connector?.pusher) {
      const pusher = connector.pusher;
      pusher.connection.bind('connected', () => {
        setWsConnected(true);
        reconnectAttempt.current = 0;
      });
      pusher.connection.bind('disconnected', () => {
        setWsConnected(false);
        scheduleReconnect();
      });
      pusher.connection.bind('error', () => {
        setWsConnected(false);
        scheduleReconnect();
      });
    }

    connectChannel();

    return () => {
      echo.leave('compras');
      if (toastTimer.current) clearTimeout(toastTimer.current);
      if (reconnectTimer.current) clearTimeout(reconnectTimer.current);
    };
  }, [showToast, refreshData]);

  const formatCLP = (n: number) => '$' + Math.round(n).toLocaleString('es-CL');

  return (
    <ComprasProvider>
    <div className="flex flex-col h-full">
      <nav className="flex items-center gap-1 overflow-x-auto border-b bg-white px-2 py-1.5 md:px-4">
        <div className="flex flex-1 gap-1">
          {tabs.map(({ href, label, icon: Icon }) => {
            const active = pathname.startsWith(href);
            return (
              <Link
                key={href}
                href={href}
                className={`flex items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                  active
                    ? 'bg-mi3-500 text-white'
                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                }`}
              >
                <Icon className="h-4 w-4" />
                <span>{label}</span>
              </Link>
            );
          })}
        </div>
        {/* WebSocket connection indicator */}
        <div className="flex items-center gap-1 px-2" title={wsConnected ? 'Conectado en tiempo real' : 'Desconectado'}>
          {wsConnected ? (
            <Wifi className="h-4 w-4 text-green-500" />
          ) : (
            <WifiOff className="h-4 w-4 text-red-400" />
          )}
          <span className={`h-2 w-2 rounded-full ${wsConnected ? 'bg-green-500' : 'bg-red-400'}`} />
        </div>
      </nav>

      <div className="flex-1 p-3 md:p-4">{children}</div>

      {/* Toast notification for new compras */}
      {toast && (
        <div className="fixed bottom-4 right-4 z-50 animate-in slide-in-from-bottom-4 fade-in duration-300">
          <div className="rounded-lg border border-green-200 bg-white p-4 shadow-lg max-w-sm">
            <div className="flex items-start gap-3">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-green-100">
                <Plus className="h-4 w-4 text-green-600" />
              </div>
              <div className="flex-1">
                <p className="text-sm font-medium text-gray-900">Nueva compra registrada</p>
                <p className="text-sm text-gray-500">
                  {toast.proveedor} — {formatCLP(toast.monto_total)} ({toast.items_count} ítems)
                </p>
              </div>
              <button onClick={() => setToast(null)} className="text-gray-400 hover:text-gray-600">
                ×
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
    </ComprasProvider>
  );
}
