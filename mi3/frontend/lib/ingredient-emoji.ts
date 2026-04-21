/**
 * Emoji mapping for ingredients.
 * 1. Exact keyword match (case-insensitive, checked in order)
 * 2. Fallback by category
 */

const KEYWORD_EMOJI: [RegExp, string][] = [
  // Carnes
  [/hamburguesa/i, '🍔'],
  [/pollo|pechuga/i, '🍗'],
  [/cerdo|lomo de cerdo/i, '🐷'],
  [/tocino/i, '🥓'],
  [/longaniza|chorizo/i, '🌭'],
  [/cordero/i, '🐑'],
  [/tomahawk|chuleta|filete|lomo vetado|posta/i, '🥩'],
  [/carne molida/i, '🥩'],
  // Panes
  [/pan completo|pan hamburguesa|pan lomito|pan artesano|pan churrasco|brioche/i, '🍞'],
  [/pre-?pizza/i, '🍕'],
  // Vegetales
  [/palta/i, '🥑'],
  [/tomate/i, '🍅'],
  [/cebolla/i, '🧅'],
  [/ajo/i, '🧄'],
  [/lechuga/i, '🥬'],
  [/papa/i, '🥔'],
  [/champiñón/i, '🍄'],
  [/pimentón/i, '🫑'],
  [/limón/i, '🍋'],
  [/mango/i, '🥭'],
  [/maracuyá|pulpa/i, '🍈'],
  [/pepinillo/i, '🥒'],
  [/cilantro|perejil|ciboulette/i, '🌿'],
  [/chucrut/i, '🥬'],
  [/aji|pebre/i, '🌶️'],
  // Lácteos
  [/queso/i, '🧀'],
  [/leche/i, '🥛'],
  [/crema/i, '🫙'],
  [/mantequilla/i, '🧈'],
  // Salsas
  [/ketchup/i, '🍅'],
  [/mayonesa/i, '🥚'],
  [/mostaza/i, '🟡'],
  [/bbq/i, '🔥'],
  [/soya|ostras|salsa para lomo/i, '🫗'],
  [/relish/i, '🥒'],
  // Condimentos
  [/aceite/i, '🫒'],
  [/sal\b/i, '🧂'],
  [/azúcar|azucar/i, '🍬'],
  [/pimienta/i, '🫚'],
  [/orégano|oregano|comino|merkén|merken/i, '🌿'],
  [/vino/i, '🍷'],
  // Bebidas
  [/agua/i, '💧'],
  [/jugo/i, '🧃'],
  [/score|bebida lata/i, '🥤'],
  [/fruguele/i, '🍬'],
  // Embutidos
  [/montina/i, '🌭'],
  // Gas
  [/gas/i, '🔥'],
  // Packaging
  [/bolsa/i, '🛍️'],
  [/caja/i, '📦'],
  [/envase|tupper/i, '🥡'],
  [/servilleta/i, '🧻'],
  [/papel/i, '📄'],
  [/pocillo|salsero/i, '🥣'],
  [/tapa vaso/i, '🥤'],
  [/bandeja/i, '🍽️'],
  [/rollo bolsa/i, '🛍️'],
  // Limpieza
  [/esponja/i, '🧽'],
  [/lavaloza|magistral/i, '🧴'],
  [/toalla/i, '🧻'],
  [/detergente|ariel/i, '🧺'],
  [/virutilla/i, '🪥'],
  [/pala/i, '🧹'],
  [/paño/i, '🧹'],
  [/anti-?grasa|energi/i, '🧴'],
  // Servicios
  [/aws|vps|servidor/i, '☁️'],
  [/meta ads/i, '📱'],
  [/delivery/i, '🚗'],
];

const CATEGORY_EMOJI: Record<string, string> = {
  'Carnes': '🥩',
  'Vegetales': '🥬',
  'Salsas': '🫗',
  'Condimentos': '🧂',
  'Panes': '🍞',
  'Embutidos': '🌭',
  'Pre-elaborados': '🍕',
  'Lácteos': '🧀',
  'Bebidas': '🥤',
  'Gas': '🔥',
  'Servicios': '☁️',
  'Packaging': '📦',
  'Limpieza': '🧹',
};

/**
 * Returns the best emoji for an ingredient based on name + category.
 */
export function getIngredientEmoji(name: string, category?: string | null): string {
  const lower = name.toLowerCase();
  for (const [pattern, emoji] of KEYWORD_EMOJI) {
    if (pattern.test(lower)) return emoji;
  }
  if (category && CATEGORY_EMOJI[category]) {
    return CATEGORY_EMOJI[category];
  }
  return '🔸';
}

/**
 * Returns name prefixed with emoji: "🥑 Palta Hass"
 */
export function emojiName(name: string, category?: string | null): string {
  return `${getIngredientEmoji(name, category)} ${name}`;
}
