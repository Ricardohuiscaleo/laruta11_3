import React, { useState, useEffect, useCallback } from 'react';
import { DollarSign, Upload, CheckCircle, Clock, FileText, Image, Loader2, Plus } from 'lucide-react';
import { pagosDeliveryApi } from '@/lib/pagos-delivery-api';
import type { DailySettlement } from '@/types/pagos-delivery';

export default function LiquidacionesPanel() {
  const [settlements, setSettlements] = useState<DailySettlement[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState<number | null>(null);
  const [generating, setGenerating] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await pagosDeliveryApi.getSettlements();
      if (res.success) setSettlements(res.settlements || []);
    } catch (e) {
      console.error('Error loading settlements:', e);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const handleUpload = async (id: number, file: File) => {
    setUploading(id);
    try {
      await pagosDeliveryApi.uploadVoucher(id, file);
      await load();
    } catch (e) {
      alert('Error al subir comprobante');
    } finally {
      setUploading(null);
    }
  };

  const handleGenerate = async () => {
    const date = prompt('Fecha (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);
    if (!date) return;
    setGenerating(true);
    try {
      await pagosDeliveryApi.generateSettlement(date);
      await load();
    } catch (e) {
      alert('Error al generar liquidación');
    } finally {
      setGenerating(false);
    }
  };

  const formatCurrency = (n: number) => `$${n.toLocaleString('es-CL')}`;

  if (loading) return <div className="text-center py-8 text-gray-500">Cargando liquidaciones...</div>;

  const pendingCount = settlements.filter(s => s.status === 'pending').length;

  return (
    <div className="space-y-4 max-w-4xl mx-auto">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div className="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-sm font-medium">
            {pendingCount} pendientes
          </div>
          <div className="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-medium">
            {settlements.filter(s => s.status === 'paid').length} pagadas
          </div>
        </div>
        <button
          onClick={handleGenerate}
          disabled={generating}
          className="flex items-center gap-1.5 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        >
          {generating ? <Loader2 className="animate-spin" size={16} /> : <Plus size={16} />}
          Generar Liquidación
        </button>
      </div>

      <div className="grid gap-3">
        {settlements.length === 0 && (
          <div className="text-center py-12 text-gray-400">
            <DollarSign size={48} className="mx-auto mb-2 opacity-30" />
            <p>No hay liquidaciones aún</p>
          </div>
        )}
        {settlements.map(s => (
          <div key={s.id} className="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between gap-4">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1.5">
                  <span className="text-sm font-bold text-gray-900">
                    {new Date(s.settlement_date + 'T12:00:00').toLocaleDateString('es-CL', { day: 'numeric', month: 'long', year: 'numeric' })}
                  </span>
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${s.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}`}>
                    {s.status === 'paid' ? 'Pagado' : 'Pendiente'}
                  </span>
                </div>
                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
                  <span>📦 {s.total_orders_delivered} pedidos</span>
                  <span className="font-bold text-gray-900">{formatCurrency(s.total_delivery_fees)}</span>
                </div>
                {s.settlement_data && s.settlement_data.length > 0 && (
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {s.settlement_data.map((r, i) => (
                      <span key={i} className="inline-flex items-center gap-1 bg-purple-50 text-purple-700 px-2 py-0.5 rounded text-xs">
                        🛵 {r.rider_nombre}: {formatCurrency(r.total_fees)}
                      </span>
                    ))}
                  </div>
                )}
              </div>
              <div className="flex flex-col items-end gap-1.5 flex-shrink-0">
                {s.payment_voucher_url ? (
                  <a
                    href={s.payment_voucher_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800"
                  >
                    <FileText size={14} />
                    Ver comprobante
                  </a>
                ) : (
                  <label className={`flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium cursor-pointer transition-colors ${uploading === s.id ? 'bg-gray-200 text-gray-500' : 'bg-purple-100 text-purple-700 hover:bg-purple-200'}`}>
                    {uploading === s.id ? (
                      <Loader2 size={14} className="animate-spin" />
                    ) : (
                      <Upload size={14} />
                    )}
                    {uploading === s.id ? 'Subiendo...' : 'Subir comprobante'}
                    <input
                      type="file"
                      className="hidden"
                      accept="image/*"
                      disabled={uploading === s.id}
                      onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) handleUpload(s.id, file);
                        e.target.value = '';
                      }}
                    />
                  </label>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
