import WorkerSidebar from '@/components/layouts/WorkerSidebar';
import MobileNavLayout from '@/components/mobile/MobileNavLayout';

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      {/* Mobile: header + content + bottom nav */}
      <MobileNavLayout>{children}</MobileNavLayout>

      {/* Desktop: sidebar + content */}
      <div className="hidden md:flex min-h-screen">
        <WorkerSidebar />
        <main className="flex-1 p-6">{children}</main>
      </div>
    </>
  );
}
