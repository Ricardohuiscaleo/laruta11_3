import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'mi3 — La Ruta 11',
  description: 'Portal de autoservicio RRHH para trabajadores de La Ruta 11',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="es">
      <body className="min-h-screen bg-gray-50 text-gray-900 antialiased">
        {children}
      </body>
    </html>
  );
}
