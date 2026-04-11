import AdminSidebar from '@/components/layouts/AdminSidebar';
import MobileNavLayout from '@/components/mobile/MobileNavLayout';
import { adminPrimaryNavItems, adminSecondaryNavItems } from '@/lib/navigation';

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      {/* Mobile: header + content + bottom nav */}
      <MobileNavLayout
        primary={adminPrimaryNavItems}
        secondary={adminSecondaryNavItems}
        notificationsEndpoint="/worker/notifications"
      >
        {children}
      </MobileNavLayout>

      {/* Desktop: sidebar + content */}
      <div className="hidden md:flex min-h-screen">
        <AdminSidebar />
        <main className="flex-1 p-6">{children}</main>
      </div>
    </>
  );
}
