/**
 * Utilidades puras para requisitos de fotos de despacho delivery.
 * Todas las funciones son puras (sin side effects) y exportadas como named exports.
 */

// ─── Generador de Requisitos ─────────────────────────────────

/**
 * Retorna la lista de fotos requeridas según el tipo de delivery.
 * - 'delivery' → 2 fotos obligatorias (productos, bolsa)
 * - Cualquier otro valor → array vacío
 *
 * @param {string} deliveryType - 'delivery' | 'pickup' | 'cuartel' | etc.
 * @returns {Array<{id: string, label: string, required: boolean}>}
 */
export function generatePhotoRequirements(deliveryType) {
  if (deliveryType === 'delivery') {
    return [
      { id: 'productos', label: '📸 Foto de productos', required: true },
      { id: 'bolsa', label: '🛍️ Foto en bolsa sellada', required: true },
    ];
  }
  return [];
}

// ─── Estado del Botón de Despacho ────────────────────────────

/**
 * Determina el estado del botón de despacho basado en completitud de fotos.
 * La verificación IA NO afecta el estado — solo importa que las fotos estén subidas.
 *
 * @param {Array<{id: string}>} photoReqs - Requisitos de fotos (de generatePhotoRequirements)
 * @param {Object} uploadedPhotos - Mapa {requirementId: url} de fotos subidas
 * @returns {{enabled: boolean, text: string, className: string, isDelivery: boolean}}
 */
export function getButtonState(photoReqs, uploadedPhotos) {
  if (!photoReqs || photoReqs.length === 0) {
    return { enabled: true, text: '✅ ENTREGAR', className: 'bg-green-600 text-white', isDelivery: false };
  }

  const allUploaded = photoReqs.every((req) => uploadedPhotos && uploadedPhotos[req.id]);

  if (allUploaded) {
    return { enabled: true, text: '📦 DESPACHAR A DELIVERY', className: 'bg-green-600 text-white', isDelivery: true };
  }

  return { enabled: false, text: '📷 FALTAN FOTOS', className: 'bg-gray-400 text-gray-200', isDelivery: true };
}

// ─── Indicador de Progreso ───────────────────────────────────

/**
 * Formatea el indicador de progreso de fotos subidas.
 *
 * @param {number} uploaded - Cantidad de fotos subidas
 * @param {number} total - Total de fotos requeridas
 * @returns {string} Ej: "1/2 fotos"
 */
export function formatPhotoProgress(uploaded, total) {
  return `${uploaded}/${total} fotos`;
}
