'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  Home, User, Calendar, Receipt, CreditCard,
  ClipboardCheck, ArrowLeftRight, Bell,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const links = [
  { href: '/dashboard', label: 'Inicio', icon: Home },
  { href: '/dashboard/perfil', label: 'Perfil', icon: User },
  { href: '/dashboard/turnos', label: 'Turnos', icon: Calendar },
  { href: '/dashboard/liquidacion', label: 'Liquidación', icon: Receipt },
  { href: '/dashboard/credito', label: 'Crédito R11', icon: CreditCard },
  { href: '/dashboard/asistencia', label: 'Asistencia', icon: ClipboardCheck },
  { href: '/dashboard/cambios', label: 'Cambios', icon: ArrowLeftRight },
  { href: '/dashboard/notificaciones', label: 'Notificaciones', icon: Bell },
];

export default function WorkerSidebar() {
  const pathname = usePathname();

  return (
    <aside className="hidden md:flex md:flex-col w-64 bg-white border-r">
      <div className="flex items-center border-b px-4 py-4">
        <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-work.png" alt="La Ruta 11 Work" className="h-8 w-auto" />
      </div>
      <nav className="mt-2 space-y-1 px-2">
        {links.map(({ href, label, icon: Icon }) => {
          const active = pathname === href;
          return (
            <Link
              key={href}
              href={href}
              className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                active
                  ? 'bg-amber-50 text-amber-700'
                  : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
              )}
            >
              <Icon className="h-5 w-5" />
              {label}
            </Link>
          );
        })}
      </nav>
    </aside>
  );
}
