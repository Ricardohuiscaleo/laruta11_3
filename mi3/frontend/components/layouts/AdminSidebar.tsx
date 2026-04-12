'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  Home, Users, Calendar, Receipt, SlidersHorizontal,
  CreditCard, ArrowLeftRight, LogOut, Clock,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { logout } from '@/lib/auth';
import ViewSwitcher from '@/components/ViewSwitcher';

const links = [
  { href: '/admin', label: 'Inicio', icon: Home },
  { href: '/admin/personal', label: 'Personal', icon: Users },
  { href: '/admin/turnos', label: 'Turnos', icon: Calendar },
  { href: '/admin/nomina', label: 'Nómina', icon: Receipt },
  { href: '/admin/ajustes', label: 'Ajustes', icon: SlidersHorizontal },
  { href: '/admin/creditos', label: 'Créditos R11', icon: CreditCard },
  { href: '/admin/cambios', label: 'Cambios', icon: ArrowLeftRight },
  { href: '/admin/cronjobs', label: 'Cronjobs', icon: Clock },
];

export default function AdminSidebar() {
  const pathname = usePathname();

  return (
    <aside className="hidden md:flex md:flex-col w-64 bg-red-600 text-white">
      <div className="flex items-center border-b border-red-500 px-4 py-4">
        <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-work.png" alt="La Ruta 11 Work" className="h-8 w-auto" />
      </div>
      <nav className="mt-2 flex-1 space-y-1 px-2">
        {links.map(({ href, label, icon: Icon }) => {
          const active = pathname === href;
          return (
            <Link key={href} href={href}
              className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                active ? 'bg-red-500 text-white' : 'text-red-100 hover:bg-red-500/50'
              )}>
              <Icon className="h-5 w-5" />
              {label}
            </Link>
          );
        })}
      </nav>
      <div className="border-t border-red-500 px-2 py-3 space-y-1">
        <ViewSwitcher className="text-red-100 hover:bg-red-500/50 hover:text-white" />
        <button onClick={logout}
          className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-200 transition-colors hover:bg-red-500/50 hover:text-white">
          <LogOut className="h-5 w-5" />
          Cerrar sesión
        </button>
      </div>
    </aside>
  );
}
