import {
  Home, User, Calendar, Receipt, CreditCard,
  ClipboardCheck, ArrowLeftRight, Bell,
  type LucideIcon,
} from 'lucide-react';

export interface NavItem {
  href: string;
  label: string;
  icon: LucideIcon;
}

/** Items que aparecen en el bottom nav (máximo 4 + "Más") */
export const primaryNavItems: NavItem[] = [
  { href: '/dashboard', label: 'Inicio', icon: Home },
  { href: '/dashboard/turnos', label: 'Turnos', icon: Calendar },
  { href: '/dashboard/liquidacion', label: 'Sueldo', icon: Receipt },
  { href: '/dashboard/credito', label: 'Crédito', icon: CreditCard },
];

/** Items que aparecen en el sheet "Más" */
export const secondaryNavItems: NavItem[] = [
  { href: '/dashboard/perfil', label: 'Perfil', icon: User },
  { href: '/dashboard/asistencia', label: 'Asistencia', icon: ClipboardCheck },
  { href: '/dashboard/cambios', label: 'Cambios', icon: ArrowLeftRight },
  { href: '/dashboard/notificaciones', label: 'Notificaciones', icon: Bell },
];

/** Todos los items (para el sidebar desktop y mapeo de títulos) */
export const allNavItems: NavItem[] = [...primaryNavItems, ...secondaryNavItems];

/** Mapeo ruta → título para el header */
export function getPageTitle(pathname: string): string {
  const item = allNavItems.find(i => i.href === pathname);
  return item?.label ?? 'mi3';
}

/**
 * Determina si un item de navegación está activo.
 * Coincidencia exacta para /dashboard, prefijo para sub-rutas.
 */
export function isNavItemActive(pathname: string, itemHref: string): boolean {
  if (itemHref === '/dashboard') {
    return pathname === '/dashboard';
  }
  return pathname.startsWith(itemHref);
}
