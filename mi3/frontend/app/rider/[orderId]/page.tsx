import type { Metadata } from 'next';
import PublicRiderView from '@/components/rider/PublicRiderView';

export const metadata: Metadata = {
  title: 'La Ruta 11 — Rider',
  description: 'Seguimiento de delivery en tiempo real',
  icons: { icon: '/11.png', apple: '/11.png' },
  openGraph: {
    title: 'La Ruta 11 — Delivery Rider',
    description: 'Seguimiento de delivery en tiempo real',
    images: [{ url: '/11.png', width: 192, height: 192 }],
  },
};

export default function PublicRiderPage({ params }: { params: { orderId: string } }) {
  return <PublicRiderView orderId={params.orderId} />;
}
