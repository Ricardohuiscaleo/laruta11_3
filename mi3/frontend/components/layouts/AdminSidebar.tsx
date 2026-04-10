'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  Home, Users, Calendar, Receipt, SlidersHorizontal,
  CreditCard, ArrowLeftRight, X, Menu,
} from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

const links = [
  { href: '/admin', label: 'Inicio', icon: Home },
  { href: '/admin/personal', label: 'Personal', icon: Users },
  { href: '/admin/turnos', label: 'Turnos', icon: Calendar },
  { href: '/admin/nomina', label: 'Nómina', icon: Receipt },
  { href: '/admin/ajustes', label: 'Ajustes', icon: SlidersHorizontal },
  { href: '/admin/creditos', label: 'Créditos R11', icon: CreditCard },
  { href: '/admin/cambios', label: 'Cambios', icon: ArrowLeftRight },
];

export default function AdminSidebar() {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="fixed left-4 top-4 z-40 rounded-lg bg-amber-700 p-2 text-white shadow-lg md:hidden"
        aria-label="Abrir menú"
      >
        <Menu className="h-5 w-5" />
      </button>

      {open && (
        <div className="fixed inset-0 z-40 bg-black/40 md:hidden" onClick={() => setOpen(false)} />
      )}

      <aside className={cn(
        'fixed inset-y-0 left-0 z-50 w-64 bg-amber-800 text-white shadow-xl transition-transform md:translate-x-0 md:static md:shadow-none',
        open ? 'translate-x-0' : '-translate-x-full'
      )}>
        <div className="flex items-center justify-between border-b border-amber-700 px-4 py-4">
          <h2 className="text-lg font-bold">mi3 Admin</h2>
          <button onClick={() => setOpen(false)} className="md:hidden" aria-label="Cerrar menú">
            <X className="h-5 w-5 text-amber-200" />
          </button>
        </div>
        <nav className="mt-2 space-y-1 px-2">
          {links.map(({ href, label, icon: Icon }) => {
            const active = pathname === href;
            return (
              <Link
                key={href}
                href={href}
                onClick={() => setOpen(false)}
                className={cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                  active
                    ? 'bg-amber-700 text-white'
                    : 'text-amber-100 hover:bg-amber-700/50'
                )}
              >
                <Icon className="h-5 w-5" />
                {label}
              </Link>
            );
          })}
        </nav>
      </aside>
    </>
  );
}
