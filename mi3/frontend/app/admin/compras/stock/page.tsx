'use client';

import StockDashboard from '@/components/admin/compras/StockDashboard';
import AjusteMasivo from '@/components/admin/compras/AjusteMasivo';

export default function StockPage() {
  return (
    <div className="space-y-6">
      <StockDashboard />
      <AjusteMasivo />
    </div>
  );
}
