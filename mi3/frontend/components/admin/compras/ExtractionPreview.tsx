'use client';

import { CheckCircle, AlertTriangle, XCircle, Scale, Package, FileText, Camera } from 'lucide-react';
import type { ExtractionResult } from '@/types/compras';

interface ExtractionPreviewProps {
  result: ExtractionResult;
  onUseData: (data: ExtractionResult) => void;
}

const TIPO_LABELS: Record<string, { label: string; icon: typeof FileText; color: string }> = {
  boleta: { label: 'Boleta', icon: FileText, color: 'text-blue-600 bg-blue-50' },
  factura: { label: 'Factura', icon: FileText, color: 'text-purple-600 bg-purple-50' },
  producto: { label: 'Foto de Producto', icon: Package, color: 'text-green-600 bg-green-50' },
  bascula: { label: 'Báscula / Balanza', icon: Scale, color: 'text-amber-600 bg-amber-50' },
  desconocido: { label: 'Imagen', icon: Camera, color: 'text-gray-600 bg-gray-50' },
};

function ConfidenceBadge({ score }: { score: number }) {
  if (score >= 0.8) {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">
        <CheckCircle className="h-3 w-3" /> {Math.round(score * 100)}%
      </span>
    );
  }
  if (score >= 0.7) {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-700">
        <AlertTriangle className="h-3 w-3" /> {Math.round(score * 100)}%
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-700">
      <AlertTriangle className="h-3 w-3" /> {Math.round(score * 100)}%
    </span>
  );
}

function fieldBorder(score: number): string {
  return score < 0.7 ? 'border-orange-400 bg-orange-50' : 'border-gray-200 bg-white';
}

export default function ExtractionPreview({ result, onUseData }: ExtractionPreviewProps) {
  const { confianza } = result;
  const tipoImagen = (result as any).tipo_imagen || 'desconocido';
  const pesoBascula = (result as any).peso_bascula;
  const unidadBascula = (result as any).unidad_bascula;
  const notasIa = (result as any).notas_ia;
  const tipoInfo = TIPO_LABELS[tipoImagen] || TIPO_LABELS.desconocido;
  const TipoIcon = tipoInfo.icon;

  const formatCLP = (n: number) => '$' + Math.round(n).toLocaleString('es-CL');
  const isDocumento = tipoImagen === 'boleta' || tipoImagen === 'factura';

  return (
    <div className="rounded-lg border border-mi3-200 bg-mi3-50/30 p-4 space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${tipoInfo.color}`}>
            <TipoIcon className="h-3.5 w-3.5" /> {tipoInfo.label}
          </span>
          <h4 className="text-sm font-semibold text-gray-800">Datos extraídos</h4>
        </div>
        <button
          onClick={() => onUseData(result)}
          className="rounded-lg bg-mi3-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-mi3-600 transition-colors"
        >
          Usar datos
        </button>
      </div>

      {/* Scale reading */}
      {tipoImagen === 'bascula' && pesoBascula && (
        <div className="rounded-md border-2 border-amber-300 bg-amber-50 p-3 text-center">
          <Scale className="mx-auto h-6 w-6 text-amber-600 mb-1" />
          <p className="text-2xl font-bold text-amber-800">{pesoBascula} {unidadBascula || 'kg'}</p>
          <p className="text-xs text-amber-600">Peso leído de la báscula</p>
        </div>
      )}

      {/* Proveedor & RUT (for documents) */}
      {(result.proveedor || result.rut_proveedor) && (
        <div className="grid grid-cols-2 gap-3">
          <div className={`rounded-md border p-2 ${fieldBorder(confianza.proveedor)}`}>
            <div className="flex items-center justify-between mb-1">
              <span className="text-xs text-gray-500">Proveedor</span>
              <ConfidenceBadge score={confianza.proveedor} />
            </div>
            <p className="text-sm font-medium">{result.proveedor || '—'}</p>
          </div>
          <div className={`rounded-md border p-2 ${fieldBorder(confianza.rut)}`}>
            <div className="flex items-center justify-between mb-1">
              <span className="text-xs text-gray-500">RUT</span>
              <ConfidenceBadge score={confianza.rut} />
            </div>
            <p className="text-sm font-medium">{result.rut_proveedor || '—'}</p>
          </div>
        </div>
      )}

      {/* Montos (only for boleta/factura) */}
      {isDocumento && (result.monto_total > 0) && (
        <div className="grid grid-cols-3 gap-3">
          <div className={`rounded-md border p-2 ${fieldBorder(confianza.monto_neto)}`}>
            <div className="flex items-center justify-between mb-1">
              <span className="text-xs text-gray-500">Neto</span>
              <ConfidenceBadge score={confianza.monto_neto} />
            </div>
            <p className="text-sm font-medium">{formatCLP(result.monto_neto)}</p>
          </div>
          <div className={`rounded-md border p-2 ${fieldBorder(confianza.iva)}`}>
            <div className="flex items-center justify-between mb-1">
              <span className="text-xs text-gray-500">IVA</span>
              <ConfidenceBadge score={confianza.iva} />
            </div>
            <p className="text-sm font-medium">{formatCLP(result.iva)}</p>
          </div>
          <div className={`rounded-md border p-2 ${fieldBorder(confianza.monto_total)}`}>
            <div className="flex items-center justify-between mb-1">
              <span className="text-xs text-gray-500">Total</span>
              <ConfidenceBadge score={confianza.monto_total} />
            </div>
            <p className="text-sm font-medium">{formatCLP(result.monto_total)}</p>
          </div>
        </div>
      )}

      {/* Items */}
      {result.items.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-medium text-gray-500">
              {tipoImagen === 'producto' ? 'Producto identificado' : `Ítems (${result.items.length})`}
            </span>
            <ConfidenceBadge score={confianza.items} />
          </div>
          <div className="space-y-1">
            {result.items.map((item, i) => (
              <div key={i} className="flex items-center justify-between rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm">
                <span className="flex-1 truncate font-medium">{item.nombre}</span>
                <span className="mx-2 text-gray-500">{item.cantidad} {item.unidad}</span>
                {item.precio_unitario > 0 && (
                  <span className="font-medium text-gray-700">{formatCLP(item.precio_unitario)}</span>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* AI notes */}
      {notasIa && (
        <div className="rounded-md border border-gray-200 bg-gray-50 p-2">
          <p className="text-xs text-gray-500">💡 {notasIa}</p>
        </div>
      )}
    </div>
  );
}

export function ExtractionError({ onManual }: { onManual: () => void }) {
  return (
    <div className="rounded-lg border border-red-200 bg-red-50 p-4">
      <div className="flex items-center gap-2 mb-2">
        <XCircle className="h-5 w-5 text-red-500" />
        <span className="text-sm font-medium text-red-700">Extracción falló</span>
      </div>
      <p className="text-sm text-red-600 mb-3">
        No se pudieron extraer datos de la imagen. Puedes ingresar los datos manualmente.
      </p>
      <button
        onClick={onManual}
        className="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 transition-colors"
      >
        Ingreso manual
      </button>
    </div>
  );
}
