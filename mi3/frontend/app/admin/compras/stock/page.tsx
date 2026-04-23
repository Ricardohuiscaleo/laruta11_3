'use client';

import StockDashboard from '@/components/admin/compras/StockDashboard';
import AjusteMasivo from '@/components/admin/compras/AjusteMasivo';
import ConsumiblesPanel from '@/components/admin/compras/ConsumiblesPanel';
import AuditoriaPanel from '@/components/admin/compras/AuditoriaPanel';

export default function StockPage() {
  return (
    <div className="space-y-6">
      <StockDashboard />
      <ConsumiblesPanel />
      <AuditoriaPanel />
      <AjusteMasivo />
    </div>
  );
}
