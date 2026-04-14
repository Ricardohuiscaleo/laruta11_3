import WorkerSidebar from '@/components/layouts/WorkerSidebar';
import MobileNavLayout from '@/components/mobile/MobileNavLayout';
import PushNotificationInit from '@/components/PushNotificationInit';
import TokenFromUrl from '@/components/TokenFromUrl';
import { Suspense } from 'react';

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <Suspense fallback={null}><TokenFromUrl /></Suspense>
      <PushNotificationInit />
      <MobileNavLayout variant="worker">{children}</MobileNavLayout>
      <div className="hidden md:flex min-h-screen">
        <WorkerSidebar />
        <main className="flex-1 p-6">{children}</main>
      </div>
    </>
  );
}
