import AdminSidebar from '@/components/layouts/AdminSidebar';

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen">
      <AdminSidebar />
      <main className="flex-1 p-4 pt-16 md:p-6 md:pt-6">{children}</main>
    </div>
  );
}
