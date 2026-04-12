/**
 * Calcula el precio unitario neto (sin IVA) a partir del precio total con IVA.
 * IVA Chile = 19%
 */
export function calcularIVA(precioTotal: number, cantidad: number): number {
  return Math.round(precioTotal / 1.19 / cantidad);
}

/**
 * Formatea un monto en pesos chilenos: "$15.990"
 */
export function formatearPesosCLP(monto: number): string {
  return '$' + monto.toLocaleString('es-CL');
}

/**
 * Formatea una fecha ISO en formato legible es-CL: "15 abr 2025"
 */
export function formatearFecha(fecha: string): string {
  return new Date(fecha).toLocaleDateString('es-CL', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}
