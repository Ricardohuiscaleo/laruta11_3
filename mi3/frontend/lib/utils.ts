/**
 * Formato CLP: $450.000
 */
export function formatCLP(amount: number): string {
  const rounded = Math.round(amount);
  return '$' + rounded.toLocaleString('es-CL');
}

/**
 * Formato fecha en español: "5 de abril de 2026"
 */
export function formatDateES(dateStr: string): string {
  const date = new Date(dateStr + 'T12:00:00');
  return date.toLocaleDateString('es-CL', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

/**
 * Formato mes: "Abril 2026"
 */
export function formatMonthES(mes: string): string {
  const [year, month] = mes.split('-');
  const date = new Date(Number(year), Number(month) - 1, 1);
  const name = date.toLocaleDateString('es-CL', { month: 'long' });
  return name.charAt(0).toUpperCase() + name.slice(1) + ' ' + year;
}

/**
 * cn() — merge classnames (simple version)
 */
export function cn(...classes: (string | undefined | false | null)[]): string {
  return classes.filter(Boolean).join(' ');
}
