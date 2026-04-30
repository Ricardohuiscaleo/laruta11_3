/**
 * Utilidades puras para el módulo de Merma Smart.
 * Todas las funciones son puras (sin side effects) y exportadas como named exports.
 */

// ─── Constantes ───────────────────────────────────────────────

export const MERMA_REASONS = [
  { value: 'Podrido', emoji: '🤮', label: 'Podrido' },
  { value: 'Vencido', emoji: '⏰', label: 'Vencido' },
  { value: 'Quemado', emoji: '🔥', label: 'Quemado' },
  { value: 'Dañado', emoji: '💥', label: 'Dañado' },
  { value: 'Caído/Derramado', emoji: '🫗', label: 'Caído/Derramado' },
  { value: 'Mal estado', emoji: '🤢', label: 'Mal estado' },
  { value: 'Contaminado', emoji: '🐛', label: 'Contaminado' },
  { value: 'Mal refrigerado', emoji: '❄️', label: 'Mal refrigerado' },
  { value: 'Devolución cliente', emoji: '🔄', label: 'Devolución' },
  { value: 'Prueba/Producto nuevo', emoji: '🧪', label: 'Prueba' },
  { value: 'Capacitación', emoji: '🎓', label: 'Capacitación' },
  { value: 'Otro', emoji: '❓', label: 'Otro' },
];

// ─── Smart Merma: Detección de tipo de input ──────────────────

/**
 * Determina cómo se debe mermar un ingrediente:
 * - 'natural': tiene peso_por_unidad → preguntar "¿Cuántos tomates?" (enteros)
 * - 'unidad': unit='unidad' → preguntar "¿Cuántos?" (enteros)
 * - 'peso': unit='kg'/'g'/'L'/'ml' sin peso_por_unidad → preguntar en unidad base (decimal)
 */
export function getMermaInputType(ingredient) {
  const ppu = parseFloat(ingredient.peso_por_unidad);
  if (ppu > 0) return 'natural';
  if (ingredient.unit === 'unidad') return 'unidad';
  return 'peso';
}

/**
 * Genera la pregunta smart para el input de cantidad.
 * Ej: "¿Cuántos tomates?" / "¿Cuántos panes?" / "¿Cuántos kg?"
 */
export function getSmartQuestion(ingredient) {
  const type = getMermaInputType(ingredient);
  const nombre = ingredient.nombre_unidad_natural || ingredient.name.toLowerCase();
  if (type === 'natural') return `¿Cuántos ${nombre}s?`;
  if (type === 'unidad') return `¿Cuántos?`;
  return `¿Cuántos ${ingredient.unit || 'kg'}?`;
}

/**
 * Genera el placeholder del input según el tipo.
 */
export function getSmartPlaceholder(ingredient) {
  const type = getMermaInputType(ingredient);
  if (type === 'natural') return 'Ej: 3';
  if (type === 'unidad') return 'Ej: 5';
  return 'Ej: 0.5';
}

/**
 * Convierte la cantidad ingresada a la unidad base del ingrediente.
 * - natural: cantidad × peso_por_unidad
 * - unidad/peso: cantidad directa
 */
export function convertToBaseUnit(cantidad, ingredient) {
  const type = getMermaInputType(ingredient);
  if (type === 'natural') {
    const ppu = parseFloat(ingredient.peso_por_unidad) || 0;
    return Math.round(cantidad * ppu * 1000) / 1000; // 3 decimales
  }
  return cantidad;
}

/**
 * Calcula el costo de la merma considerando la conversión smart.
 */
export function calculateSmartCost(cantidad, ingredient) {
  const baseQty = convertToBaseUnit(cantidad, ingredient);
  const costPerUnit = parseFloat(ingredient.cost_per_unit) || 0;
  return baseQty * costPerUnit;
}

/**
 * Genera texto descriptivo de la conversión para el resumen.
 * Ej: "3 tomates = 0.450 kg" / "5 unidades" / "0.5 kg"
 */
export function getConversionText(cantidad, ingredient) {
  const type = getMermaInputType(ingredient);
  const baseQty = convertToBaseUnit(cantidad, ingredient);
  const nombre = ingredient.nombre_unidad_natural || 'unidad';
  const unit = ingredient.unit || 'kg';

  if (type === 'natural') {
    const plural = cantidad !== 1 ? 's' : '';
    return `${cantidad} ${nombre}${plural} = ${baseQty.toFixed(3)} ${unit}`;
  }
  if (type === 'unidad') {
    return `${cantidad} unidad${cantidad !== 1 ? 'es' : ''}`;
  }
  return `${cantidad} ${unit}`;
}

/**
 * Calcula cuántas unidades naturales equivale el stock actual.
 * Solo aplica si tiene peso_por_unidad.
 * Retorna null si no aplica.
 */
export function stockInNaturalUnits(ingredient) {
  const ppu = parseFloat(ingredient.peso_por_unidad);
  if (!ppu || ppu <= 0) return null;
  const stock = parseFloat(ingredient.current_stock) || 0;
  return Math.floor(stock / ppu);
}

/**
 * Valida la cantidad ingresada contra el stock.
 * Retorna { blocked, alertCritical, stockBaseQty }
 */
export function validateSmartQuantity(cantidad, ingredient) {
  const baseQty = convertToBaseUnit(cantidad, ingredient);
  const stock = parseFloat(ingredient.current_stock) || 0;
  const min = parseFloat(ingredient.min_stock_level) || 0;
  return {
    blocked: baseQty > stock,
    alertCritical: (stock - baseQty) < min && !((stock - baseQty) < 0),
    stockBaseQty: baseQty,
  };
}

// ─── Indicadores de Stock ─────────────────────────────────────

/**
 * Retorna el color del indicador de stock según las reglas del diseño.
 */
export function getStockColor(currentStock, minStockLevel) {
  if (!minStockLevel || minStockLevel === 0) return 'green';
  if (currentStock > 2 * minStockLevel) return 'green';
  if (currentStock < minStockLevel) return 'red';
  return 'yellow';
}

export function countCriticalIngredients(ingredients) {
  if (!Array.isArray(ingredients)) return 0;
  return ingredients.filter(
    (i) => i.min_stock_level && i.min_stock_level > 0 && i.current_stock < i.min_stock_level
  ).length;
}

// ─── Cálculos de Costo ───────────────────────────────────────

export function calculateMermaSubtotal(cantidad, costoUnitario) {
  return cantidad * costoUnitario;
}

export function calculateMermaTotal(items) {
  if (!Array.isArray(items) || items.length === 0) return 0;
  return items.reduce((sum, item) => sum + (item.subtotal || 0), 0);
}

// ─── Validaciones ─────────────────────────────────────────────

export function validateMermaQuantity(quantity, currentStock, minStockLevel) {
  return {
    blocked: quantity > currentStock,
    alertCritical: (currentStock - quantity) < minStockLevel,
  };
}

export function canSubmitMerma(items, reason) {
  return Array.isArray(items) && items.length > 0 && typeof reason === 'string' && reason.trim().length > 0;
}

// ─── Búsqueda Fuzzy ──────────────────────────────────────────

export function fuzzyMatch(str, pattern) {
  const strLower = str.toLowerCase();
  const patternLower = pattern.toLowerCase();
  let patternIdx = 0;
  let score = 0;

  for (let i = 0; i < strLower.length && patternIdx < patternLower.length; i++) {
    if (strLower[i] === patternLower[patternIdx]) {
      score += (patternIdx === 0 || strLower[i - 1] === ' ') ? 2 : 1;
      patternIdx++;
    }
  }
  return patternIdx === patternLower.length ? score : 0;
}

export function filterAndSortItems(items, searchTerm, maxResults = 10) {
  if (!searchTerm || !searchTerm.trim() || !Array.isArray(items)) return [];
  return items
    .map((item) => ({ ...item, score: fuzzyMatch(item.name, searchTerm) }))
    .filter((item) => item.score > 0)
    .sort((a, b) => b.score - a.score)
    .slice(0, maxResults);
}

// ─── Agrupación ──────────────────────────────────────────────

export function groupByCategory(ingredients) {
  if (!Array.isArray(ingredients)) return {};
  return ingredients.reduce((groups, ingredient) => {
    const cat = ingredient.category || 'Sin categoría';
    if (!groups[cat]) groups[cat] = [];
    groups[cat].push(ingredient);
    return groups;
  }, {});
}

// ─── Historial y Fechas ──────────────────────────────────────

export function getDailyMermaTotal(mermas, targetDate) {
  if (!Array.isArray(mermas) || !targetDate) return 0;
  const target = typeof targetDate === 'string' ? targetDate : targetDate.toISOString().split('T')[0];
  return mermas
    .filter((m) => {
      if (!m.created_at) return false;
      const mermaDate = m.created_at.split('T')[0].split(' ')[0];
      return mermaDate === target;
    })
    .reduce((sum, m) => sum + (parseFloat(m.cost) || 0), 0);
}

export function formatDateChilean(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return '';
  const dd = String(date.getDate()).padStart(2, '0');
  const mm = String(date.getMonth() + 1).padStart(2, '0');
  const yyyy = date.getFullYear();
  return `${dd}/${mm}/${yyyy}`;
}
