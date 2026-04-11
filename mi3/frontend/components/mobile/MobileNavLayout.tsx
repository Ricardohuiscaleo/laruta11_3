import MobileHeader from './MobileHeader';
import MobileBottomNav from './MobileBottomNav';

interface MobileNavLayoutProps {
  children: React.ReactNode;
  variant?: 'worker' | 'admin';
}

export default function MobileNavLayout({ children, variant = 'worker' }: MobileNavLayoutProps) {
  return (
    <div className="md:hidden">
      <MobileHeader variant={variant} />
      <div className="pt-14 pb-20">{children}</div>
      <MobileBottomNav variant={variant} />
    </div>
  );
}
