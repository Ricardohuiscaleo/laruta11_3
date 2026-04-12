import {
  Home, User, Calendar, Receipt, CreditCard, Wallet,
  ClipboardCheck, ArrowLeftRight, Bell, Users, Repeat2,
  SlidersHorizontal, Clock,
  type LucideIcon,
} from 'lucide-react';

export interface NavItem {
  href: string;
  label: string;
  icon: LucideIcon;
  /** Optional key used to attach dynamic badges (e.g. pending-loan indicator). */
  badgeKey?: string;
}

// ── Worker Navigation ──

export const primaryNavItems: NavItem[] = [
  { href: '/dashboard', label: 'Inicio', icon: Home },
  { href: '/dashboard/turnos', label: 'Turnos', icon: Calendar },
  { href: '/dashboard/liquidacion', label: 'Sueldo', icon: Receipt },
  { href: '/dashboard/prestamos', label: 'Adelantos', icon: Wallet, badgeKey: 'prestamo-pendiente' },
];

export const secondaryNavItems: NavItem[] = [
  { href: '/dashboard/perfil', label: 'Perfil', icon: User },
  { href: '/dashboard/credito', label: 'Crédito', icon: CreditCard },
  { href: '/dashboard/checklist', label: 'Checklist', icon: ClipboardCheck, badgeKey: 'checklist-pendiente' },
  { href: '/dashboard/reemplazos', label: 'Reemplazos', icon: Repeat2 },
  { href: '/dashboard/asistencia', label: 'Asistencia', icon: ClipboardCheck },
  { href: '/dashboard/cambios', label: 'Cambios', icon: ArrowLeftRight },
  { href: '/dashboard/notificaciones', label: 'Notificaciones', icon: Bell },
];

export const allNavItems: NavItem[] = [...primaryNavItems, ...secondaryNavItems];

// ── Admin Navigation ──

export const adminPrimaryNavItems: NavItem[] = [
  { href: '/admin', label: 'Inicio', icon: Home },
  { href: '/admin/personal', label: 'Personal', icon: Users },
  { href: '/admin/turnos', label: 'Turnos', icon: Calendar },
  { href: '/admin/nomina', label: 'Nómina', icon: Receipt },
];

export const adminSecondaryNavItems: NavItem[] = [
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
