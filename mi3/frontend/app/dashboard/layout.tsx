import WorkerSidebar from '@/components/layouts/WorkerSidebar';
import MobileNavLayout from '@/components/mobile/MobileNavLayout';
import PushNotificationInit from '@/components/PushNotificationInit';

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <PushNotificationInit />
      <MobileNavLayout variant="worker">{children}</MobileNavLayout>
      <div className="hidden md:flex min-h-screen">
        <WorkerSidebar />
        <main className="flex-1 p-6">{children}</main>
      </div>
    </>
  );
}
