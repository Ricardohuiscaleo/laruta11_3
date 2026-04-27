/**
 * Utilidades puras para el módulo de Merma inline.
 * Todas las funciones son puras (sin side effects) y exportadas como named exports.
 */

// ─── Constantes ───────────────────────────────────────────────

export const MERMA_REASONS = [
  { value: 'Prueba/Producto nuevo', emoji: '🧪', label: 'Prueba/Producto nuevo' },
  { value: 'Podrido', emoji: '🤮', label: 'Podrido' },
  { value: 'Vencido', emoji: '⏰', label: 'Vencido' },
  { value: 'Quemado', emoji: '🔥', label: 'Quemado' },
  { value: 'Dañado', emoji: '💥', label: 'Dañado' },
  { value: 'Caído/Derramado', emoji: '🫗', label: 'Caído/Derramado' },
  { value: 'Mal estado', emoji: '🤢', label: 'Mal estado' },
  { value: 'Contaminado', emoji: '🐛', label: 'Contaminado' },
  { value: 'Mal refrigerado', emoji: '❄️', label: 'Mal refrigerado' },
  { value: 'Devolución cliente', emoji: '🔄', label: 'Devolución cliente' },
  { value: 'Capacitación', emoji: '🎓', label: 'Capacitación' },
  { value: 'Otro', emoji: '❓', label: 'Otro' },
];

// ─── Indicadores de Stock ─────────────────────────────────────

/**
 * Retorna el color del indicador de stock según las reglas del diseño:
 * - Si minStockLevel es 0 o null → 'green'
 * - Si currentStock > 2 * minStockLevel → 'green'
 * - Si minStockLevel <= currentStock <= 2 * minStockLevel → 'yellow'
 * - Si currentStock < minStockLevel → 'red'
 */
export function getStockColor(currentStock, minStockLevel) {
  if (!minStockLevel || minStockLevel === 0) return 'green';
  if (currentStock > 2 * minStockLevel) return 'green';
  if (currentStock < minStockLevel) return 'red';
  return 'yellow'; // minStockLevel <= currentStock <= 2 * minStockLevel
}

/**
 * Cuenta ingredientes en estado crítico (current_stock < min_stock_level),
 * excluyendo aquellos con min_stock_level = 0 o null.
 */
export function countCriticalIngredients(ingredients) {
  if (!Array.isArray(ingredients)) return 0;
  return ingredients.filter(
    (i) => i.min_stock_level && i.min_stock_level > 0 && i.current_stock < i.min_stock_level
  ).length;
}

// ─── Cálculos de Costo ───────────────────────────────────────

/**
 * Calcula el subtotal de un item de merma: cantidad × costoUnitario.
 */
export function calculateMermaSubtotal(cantidad, costoUnitario) {
  return cantidad * costoUnitario;
}

/**
 * Calcula el total acumulado de merma: suma de todos los item.subtotal.
 */
export function calculateMermaTotal(items) {
  if (!Array.isArray(items) || items.length === 0) return 0;
  return items.reduce((sum, item) => sum + (item.subtotal || 0), 0);
}

// ─── Validaciones ─────────────────────────────────────────────

/**
 * Valida la cantidad de merma contra el stock actual y nivel mínimo.
 * Retorna { blocked: boolean, alertCritical: boolean }
 * - blocked = true si quantity > currentStock
 * - alertCritical = true si (currentStock - quantity) < minStockLevel
 */
export function validateMermaQuantity(quantity, currentStock, minStockLevel) {
  return {
    blocked: quantity > currentStock,
    alertCritical: (currentStock - quantity) < minStockLevel,
  };
}

/**
 * Valida si se puede enviar la merma: items.length > 0 AND reason no vacío.
 */
export function canSubmitMerma(items, reason) {
  return Array.isArray(items) && items.length > 0 && typeof reason === 'string' && reason.trim().length > 0;
}

// ─── Búsqueda Fuzzy ──────────────────────────────────────────

/**
 * Fuzzy match reutilizado de MermasApp.jsx.
 * Asigna puntaje basado en coincidencia secuencial de caracteres,
 * con bonus para coincidencias al inicio de palabra.
 * Retorna score > 0 si hay match, 0 si no.
 */
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

/**
 * Filtra items usando fuzzyMatch, ordena por score descendente, limita a maxResults.
 * Cada item debe tener una propiedad `name`.
 */
export function filterAndSortItems(items, searchTerm, maxResults = 10) {
  if (!searchTerm || !searchTerm.trim() || !Array.isArray(items)) return [];
  return items
    .map((item) => ({ ...item, score: fuzzyMatch(item.name, searchTerm) }))
    .filter((item) => item.score > 0)
    .sort((a, b) => b.score - a.score)
    .slice(0, maxResults);
}

// ─── Agrupación ──────────────────────────────────────────────

/**
 * Agrupa ingredientes por su campo `category`.
 * Retorna un objeto { categoryName: [ingredients] }.
 */
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

/**
 * Suma los costos de mermas cuya fecha created_at coincide con targetDate (comparando YYYY-MM-DD).
 * targetDate puede ser un string 'YYYY-MM-DD' o un Date.
 */
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

/**
 * Formatea un string de fecha a formato chileno dd/mm/yyyy.
 */
export function formatDateChilean(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  if (isNaN(date.getTime())) return '';
  const dd = String(date.getDate()).padStart(2, '0');
  const mm = String(date.getMonth() + 1).padStart(2, '0');
  const yyyy = date.getFullYear();
  return `${dd}/${mm}/${yyyy}`;
}
