import AdminSidebar from '@/components/layouts/AdminSidebar';
import MobileNavLayout from '@/components/mobile/MobileNavLayout';
import PushNotificationInit from '@/components/PushNotificationInit';
import TokenFromUrl from '@/components/TokenFromUrl';
import { Suspense } from 'react';

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <Suspense fallback={null}><TokenFromUrl /></Suspense>
      <PushNotificationInit />
      <MobileNavLayout variant="admin">{children}</MobileNavLayout>
      <div className="hidden md:flex min-h-screen">
        <AdminSidebar />
        <main className="flex-1 p-6">{children}</main>
      </div>
    </>
  );
}
