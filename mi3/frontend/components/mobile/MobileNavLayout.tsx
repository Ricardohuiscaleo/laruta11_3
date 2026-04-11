import MobileHeader from './MobileHeader';
import MobileBottomNav from './MobileBottomNav';

interface MobileNavLayoutProps {
  children: React.ReactNode;
}

export default function MobileNavLayout({ children }: MobileNavLayoutProps) {
  return (
    <div className="md:hidden">
      <MobileHeader />
      <div className="pt-14 pb-20">{children}</div>
      <MobileBottomNav />
    </div>
  );
}
