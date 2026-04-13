import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'La Ruta 11 — Work',
  description: 'Portal de control y gestión para colaboradores y administración de La Ruta 11',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="es">
      <head>
        <link rel="manifest" href="/manifest.json" />
        <meta name="theme-color" content="#ef4444" />
        <link rel="apple-touch-icon" href="https://laruta11-images.s3.amazonaws.com/menu/logo-work.png" />
      </head>
      <body className="min-h-screen bg-gray-50 text-gray-900 antialiased">
        {children}
      </body>
    </html>
  );
}
