'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/hooks/useAuth';
import { useRiderGPS } from '@/hooks/useRiderGPS';
import RiderDashboard from '@/components/rider/RiderDashboard';

export default function RiderPage() {
  const { user, loading } = useAuth();
  const router = useRouter();
  const { position, isActive, error, toggleDeliveryMode } = useRiderGPS();

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.replace('/login');
      return;
    }
    // Redirect non-riders to their dashboard
    if (!user.rol.includes('rider')) {
      router.replace('/dashboard');
    }
  }, [user, loading, router]);

  if (loading || !user || !user.rol.includes('rider')) {
    return null;
  }

  return (
    <RiderDashboard
      position={position}
      isActive={isActive}
      gpsError={error}
      toggleDeliveryMode={toggleDeliveryMode}
    />
  );
}
