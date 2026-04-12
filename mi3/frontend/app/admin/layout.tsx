import AdminSidebar from '@/components/layouts/AdminSidebar';
import MobileNavLayout from '@/components/mobile/MobileNavLayout';
import PushNotificationInit from '@/components/PushNotificationInit';

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <PushNotificationInit />
      <MobileNavLayout variant="admin">{children}</MobileNavLayout>
      <div className="hidden md:flex min-h-screen">
        <AdminSidebar />
        <main className="flex-1 p-6">{children}</main>
      </div>
    </>
  );
}
