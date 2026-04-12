'use client';

import { useState } from 'react';
import { DollarSign, MapPin, Package, Check, HelpCircle } from 'lucide-react';

interface Pregunta {
  tipo: 'precio' | 'proveedor' | 'identificar';
  item_index?: number;
  pregunta: string;
  placeholder?: string;
  ultimo_precio?: number | null;
  opciones?: Record<string, number>;
  sugerencias?: Record<string, string>;
}

interface ItemResuelto {
  nombre_detectado: string;
  nombre_ingrediente?: string;
  ingrediente_id?: number;
  cantidad_real?: number;
  unidad_real?: string;
  equivalencia?: {
    nombre_ingrediente: string;
    cantidad_por_unidad: number;
    unidad_visual: string;
    unidad_real: string;
    ultimo_precio: number | null;
  };
}

interface AsistenteCompraProps {
  items: ItemResuelto[];
  preguntas: Pregunta[];
  proveedorDetectado: string | null;
  notasIa: string | null;
  onComplete: (data: {
    items: Array<{
      nombre: string;
      ingrediente_id: number | null;
      cantidad: number;
      unidad: string;
      precio_unitario: number;
      subtotal: number;
    }>;
    proveedor: string;
  }) => void;
}

export default function AsistenteCompra({ items, preguntas, proveedorDetectado, notasIa, onComplete }: AsistenteCompraProps) {
  const [respuestas, setRespuestas] = useState<Record<string, string>>({});
  const [currentStep, setCurrentStep] = useState(0);

  const preguntasFiltradas = preguntas.filter(p => {
    if (p.tipo === 'proveedor' && proveedorDetectado) return false;
    return true;
  });

  const currentPregunta = preguntasFiltradas[currentStep];
  const isLastStep = currentStep >= preguntasFiltradas.length - 1;
  const allAnswered = preguntasFiltradas.every((_, i) => respuestas[`q${i}`]);

  const handleResponder = (value: string) => {
    setRespuestas(prev => ({ ...prev, [`q${currentStep}`]: value }));
    if (!isLastStep) {
      setCurrentStep(prev => prev + 1);
    }
  };

  const handleConfirmar = () => {
    const itemsFinales = items.map((item, idx) => {
      const equiv = item.equivalencia;
      let cantidad = item.cantidad_real || 0;
      let unidad = item.unidad_real || 'kg';
      let precioUnitario = 0;
      let subtotal = 0;

      // Find price answer for this item
      const precioQ = preguntasFiltradas.findIndex(p => p.tipo === 'precio' && p.item_index === idx);
      if (precioQ >= 0 && respuestas[`q${precioQ}`]) {
        const precioTotal = parseFloat(respuestas[`q${precioQ}`]);
        subtotal = precioTotal;
        precioUnitario = cantidad > 0 ? Math.round(precioTotal / cantidad) : precioTotal;
      } else if (equiv?.ultimo_precio && cantidad > 0) {
        subtotal = equiv.ultimo_precio;
        precioUnitario = Math.round(equiv.ultimo_precio / cantidad);
      }

      return {
        nombre: item.nombre_ingrediente || item.nombre_detectado,
        ingrediente_id: item.ingrediente_id || null,
        cantidad,
        unidad,
        precio_unitario: precioUnitario,
        subtotal,
      };
    });

    // Find proveedor answer
    const provQ = preguntasFiltradas.findIndex(p => p.tipo === 'proveedor');
    const proveedor = provQ >= 0 ? respuestas[`q${provQ}`] || '' : proveedorDetectado || '';

    onComplete({ items: itemsFinales, proveedor });
  };

  const formatCLP = (n: number) => '$' + Math.round(n).toLocaleString('es-CL');

  return (
    <div className="rounded-xl border border-mi3-200 bg-white p-4 space-y-4">
      {/* Items detectados */}
      <div className="space-y-2">
        <h4 className="text-sm font-semibold text-gray-700 flex items-center gap-2">
          <Package className="h-4 w-4" /> Productos detectados
        </h4>
        {items.map((item, i) => (
          <div key={i} className="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm">
            <div>
              <span className="font-medium">{item.nombre_ingrediente || item.nombre_detectado}</span>
              {item.equivalencia && (
                <span className="ml-2 text-xs text-gray-500">
                  ({item.equivalencia.cantidad_por_unidad} {item.equivalencia.unidad_real} por {item.equivalencia.unidad_visual})
                </span>
              )}
            </div>
            {item.cantidad_real && (
              <span className="text-gray-600">{item.cantidad_real} {item.unidad_real}</span>
            )}
          </div>
        ))}
      </div>

      {/* Notas IA */}
      {notasIa && (
        <p className="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">💡 {notasIa}</p>
      )}

      {/* Pregunta actual */}
      {currentPregunta && !allAnswered && (
        <div className="rounded-lg border-2 border-mi3-300 bg-mi3-50 p-4">
          <div className="flex items-center gap-2 mb-3">
            {currentPregunta.tipo === 'precio' && <DollarSign className="h-5 w-5 text-mi3-600" />}
            {currentPregunta.tipo === 'proveedor' && <MapPin className="h-5 w-5 text-mi3-600" />}
            {currentPregunta.tipo === 'identificar' && <HelpCircle className="h-5 w-5 text-mi3-600" />}
            <span className="text-sm font-semibold text-gray-800">{currentPregunta.pregunta}</span>
          </div>

          {/* Precio input */}
          {currentPregunta.tipo === 'precio' && (
            <div className="space-y-2">
              {currentPregunta.ultimo_precio && (
                <button
                  onClick={() => handleResponder(String(currentPregunta.ultimo_precio))}
                  className="w-full rounded-lg border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-700 hover:bg-green-100 text-left"
                >
                  Usar último precio: {formatCLP(currentPregunta.ultimo_precio)}
                </button>
              )}
              <div className="flex gap-2">
                <input
                  type="number"
                  placeholder={currentPregunta.placeholder || 'Precio...'}
                  className="flex-1 rounded-lg border px-3 py-2 text-sm"
                  onKeyDown={e => {
                    if (e.key === 'Enter') {
                      handleResponder((e.target as HTMLInputElement).value);
                    }
                  }}
                />
                <button
                  onClick={e => {
                    const input = (e.target as HTMLElement).parentElement?.querySelector('input');
                    if (input?.value) handleResponder(input.value);
                  }}
                  className="rounded-lg bg-mi3-500 px-4 py-2 text-sm font-medium text-white hover:bg-mi3-600"
                >
                  OK
                </button>
              </div>
            </div>
          )}

          {/* Proveedor options */}
          {currentPregunta.tipo === 'proveedor' && currentPregunta.opciones && (
            <div className="space-y-1">
              {Object.entries(currentPregunta.opciones)
                .sort(([, a], [, b]) => b - a)
                .map(([nombre, freq]) => (
                  <button
                    key={nombre}
                    onClick={() => handleResponder(nombre)}
                    className="flex w-full items-center justify-between rounded-lg border bg-white px-3 py-2 text-sm hover:bg-gray-50"
                  >
                    <span className="font-medium">{nombre}</span>
                    <span className="text-xs text-gray-400">{freq}x</span>
                  </button>
                ))}
              <input
                type="text"
                placeholder="Otro proveedor..."
                className="w-full rounded-lg border px-3 py-2 text-sm mt-1"
                onKeyDown={e => {
                  if (e.key === 'Enter' && (e.target as HTMLInputElement).value) {
                    handleResponder((e.target as HTMLInputElement).value);
                  }
                }}
              />
            </div>
          )}

          {/* Identify product */}
          {currentPregunta.tipo === 'identificar' && currentPregunta.sugerencias && (
            <div className="space-y-1">
              {Object.entries(currentPregunta.sugerencias).map(([id, nombre]) => (
                <button
                  key={id}
                  onClick={() => handleResponder(nombre)}
                  className="flex w-full items-center rounded-lg border bg-white px-3 py-2 text-sm hover:bg-gray-50"
                >
                  {nombre}
                </button>
              ))}
            </div>
          )}

          {/* Step indicator */}
          <div className="flex items-center justify-between mt-3 text-xs text-gray-400">
            <span>Pregunta {currentStep + 1} de {preguntasFiltradas.length}</span>
            {currentStep > 0 && (
              <button onClick={() => setCurrentStep(prev => prev - 1)} className="text-mi3-600 hover:underline">
                ← Anterior
              </button>
            )}
          </div>
        </div>
      )}

      {/* Confirmar */}
      {allAnswered && (
        <button
          onClick={handleConfirmar}
          className="flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 py-3 text-sm font-semibold text-white hover:bg-green-700"
        >
          <Check className="h-4 w-4" /> Confirmar y registrar compra
        </button>
      )}
    </div>
  );
}
