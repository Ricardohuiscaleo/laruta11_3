import MobileHeader from './MobileHeader';
import MobileBottomNav from './MobileBottomNav';
import type { NavItem } from '@/lib/navigation';

interface MobileNavLayoutProps {
  children: React.ReactNode;
  primary?: NavItem[];
  secondary?: NavItem[];
  notificationsEndpoint?: string;
}

export default function MobileNavLayout({ children, primary, secondary, notificationsEndpoint }: MobileNavLayoutProps) {
  return (
    <div className="md:hidden">
      <MobileHeader notificationsEndpoint={notificationsEndpoint} />
      <div className="pt-14 pb-20">{children}</div>
      <MobileBottomNav primary={primary} secondary={secondary} />
    </div>
  );
}
