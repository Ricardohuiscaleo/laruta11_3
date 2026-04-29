import type { Metadata } from 'next';
import PublicRiderView from '@/components/rider/PublicRiderView';

export const metadata: Metadata = {
  title: 'La Ruta 11 — Delivery',
  description: 'Tomar pedido de delivery — La Ruta 11',
  icons: { icon: '/11.png', apple: '/11.png' },
  openGraph: {
    title: 'La Ruta 11 — Delivery',
    description: 'Tomar pedido de delivery — seguimiento en tiempo real',
    siteName: 'La Ruta 11 Delivery',
    images: [{ url: 'https://mi.laruta11.cl/11.png', width: 192, height: 192 }],
  },
};

export default function PublicRiderPage({ params }: { params: { orderId: string } }) {
  return <PublicRiderView orderId={params.orderId} />;
}
