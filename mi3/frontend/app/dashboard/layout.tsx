import WorkerSidebar from '@/components/layouts/WorkerSidebar';

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen">
      <WorkerSidebar />
      <main className="flex-1 p-4 pt-16 md:p-6 md:pt-6">{children}</main>
    </div>
  );
}
