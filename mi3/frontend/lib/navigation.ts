import {
  Home, User, Calendar, Receipt, CreditCard, Wallet,
  ClipboardCheck, ArrowLeftRight, Bell, Users, Repeat2,
  SlidersHorizontal, Clock, ShoppingCart, Truck,
  type LucideIcon,
} from 'lucide-react';

export interface NavItem {
  href: string;
  label: string;
  icon: LucideIcon;
  badgeKey?: string;
}

// ── Worker Navigation (3 + Alertas + Más) ──

export const primaryNavItems: NavItem[] = [
  { href: '/dashboard', label: 'Inicio', icon: Home },
  { href: '/dashboard/turnos', label: 'Turnos', icon: Calendar },
  { href: '/dashboard/liquidacion', label: 'Sueldo', icon: Receipt },
  { href: '/dashboard/notificaciones', label: 'Alertas', icon: Bell, badgeKey: 'notificaciones-unread' },
];

export const secondaryNavItems: NavItem[] = [
  { href: '/dashboard/prestamos', label: 'Adelantos', icon: Wallet, badgeKey: 'prestamo-pendiente' },
  { href: '/dashboard/perfil', label: 'Perfil', icon: User },
  { href: '/dashboard/credito', label: 'Crédito', icon: CreditCard },
  { href: '/dashboard/reemplazos', label: 'Reemplazos', icon: Repeat2 },
  { href: '/dashboard/asistencia', label: 'Asistencia', icon: ClipboardCheck },
  { href: '/dashboard/cambios', label: 'Cambios', icon: ArrowLeftRight },
];

export const allNavItems: NavItem[] = [...primaryNavItems, ...secondaryNavItems];

// ── Admin Navigation (3 + Alertas + Más) ──

export const adminPrimaryNavItems: NavItem[] = [
  { href: '/admin', label: 'Inicio', icon: Home },
  { href: '/admin/personal', label: 'Personal', icon: Users },
  { href: '/admin/turnos', label: 'Turnos', icon: Calendar },
  { href: '/admin/notificaciones', label: 'Alertas', icon: Bell, badgeKey: 'notificaciones-unread' },
];

export const adminSecondaryNavItems: NavItem[] = [
  { href: '/admin/delivery', label: 'Delivery', icon: Truck },
  { href: '/admin/adelantos', label: 'Adelantos', icon: Wallet },
  { href: '/admin/compras', label: 'Compras', icon: ShoppingCart },
  { href: '/admin/nomina', label: 'Nómina', icon: Receipt },
  { href: '/admin/checklists', label: 'Checklists', icon: ClipboardCheck },
  { href: '/admin/ajustes', label: 'Ajustes', icon: SlidersHorizontal },
  { href: '/admin/creditos', label: 'Créditos R11', icon: CreditCard },
  { href: '/admin/cambios', label: 'Cambios', icon: ArrowLeftRight },
  { href: '/admin/cronjobs', label: 'Cronjobs', icon: Clock },
];

export const allAdminNavItems: NavItem[] = [...adminPrimaryNavItems, ...adminSecondaryNavItems];

// ── Shared Helpers ──

export function getPageTitle(pathname: string): string {
  const all = [...allNavItems, ...allAdminNavItems];
  const item = all.find(i => i.href === pathname);
  return item?.label ?? 'R11 Work';
}

export function isNavItemActive(pathname: string, itemHref: string): boolean {
  if (itemHref === '/dashboard' || itemHref === '/admin') {
    return pathname === itemHref;
  }
  return pathname.startsWith(itemHref);
}
