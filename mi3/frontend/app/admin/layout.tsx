import AdminSidebar from '@/components/layouts/AdminSidebar';
import MobileNavLayout from '@/components/mobile/MobileNavLayout';

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <MobileNavLayout variant="admin">{children}</MobileNavLayout>
      <div className="hidden md:flex min-h-screen">
        <AdminSidebar />
        <main className="flex-1 p-6">{children}</main>
      </div>
    </>
  );
}
