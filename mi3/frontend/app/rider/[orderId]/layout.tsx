import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'La Ruta 11 — Delivery',
  description: 'Tomar pedido de delivery — seguimiento en tiempo real',
  icons: { icon: '/11.png', apple: '/11.png' },
  openGraph: {
    title: 'La Ruta 11 — Delivery',
    description: 'Tomar pedido de delivery — seguimiento en tiempo real',
    siteName: 'La Ruta 11 Delivery',
    images: [{ url: 'https://mi.laruta11.cl/11.png', width: 192, height: 192 }],
  },
};

export default function RiderOrderLayout({ children }: { children: React.ReactNode }) {
  return children;
}
