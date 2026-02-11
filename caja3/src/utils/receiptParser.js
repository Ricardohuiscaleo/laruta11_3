// 🧠 Parser de Boletas con Patrones Adaptativos

const patterns = {
  vanni: {
    identifier: /76[.,\s]*979[.,\s]*850[-\s]*1|FABRICA DE BANDEJAS|ANGELICA.*UNI|waNzLLA|DELIVERY|TROQUELADA/i,
    total: /TOTAL\s*\$\s*([\d.]+)/i,
    date: /FECHA DE EMISION\s*:\s*(\d{2}\/\d{2}\/\d{4})/i,
    provider: 'VANNI',
    hasIVA: false, // Valores sin IVA, multiplicar por 1.19
    itemsPattern: /(\d{7})\s+([\d,]+(?:\.\d{2})?)\s*\|?\s*([A-Z\[\]]*)?\s*([A-Za-z\s\/\-\d\x]+?)\s+([\d,]+(?:\.\d{2})?)\s+([\d.,]+)/g,
    // Mapeo directo por código de producto
    productCodes: {
      '2340702': 'Bolsa Delivery Baja',
      '2322310': 'Caja Sandwich'
    }
  },
  unimarc: {
    identifier: /UNIMARC/i,
    total: /TOTAL\s*\$?\s*([\d.]+)/i,
    date: /(\d{2}\/\d{2}\/\d{4})/,
    provider: 'UNIMARC'
  },
  lider: {
    identifier: /LIDER|HIPER.*LIDER/i,
    total: /Total.*?([\d.]+)/i,
    date: /(\d{2}-\d{2}-\d{4})/,
    provider: 'LIDER'
  },
  santaisabel: {
    identifier: /SANTA\s*ISABEL/i,
    total: /TOTAL.*?([\d.]+)/i,
    date: /(\d{2}\/\d{2}\/\d{2})/,
    provider: 'SANTA ISABEL'
  },
  tottus: {
    identifier: /TOTTUS/i,
    total: /TOTAL.*?([\d.]+)/i,
    date: /(\d{2}\/\d{2}\/\d{4})/,
    provider: 'TOTTUS'
  },
  // Patrón genérico (fallback)
  generic: {
    total: /(?:TOTAL|Total|total|NETO|Neto).*?([\d.]+)/i,
    date: /(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/,
    provider: 'GENERICO'
  }
};

// Detectar proveedor
function detectProvider(text) {
  for (const [key, pattern] of Object.entries(patterns)) {
    if (pattern.identifier && pattern.identifier.test(text)) {
      return key;
    }
  }
  return 'generic';
}

// Extraer monto total
function extractTotal(text, pattern) {
  const match = text.match(pattern.total);
  if (match) {
    return match[1].replace(/\./g, '').replace(/,/g, '');
  }
  
  // Fallback específico para facturas chilenas
  const totalMatch = text.match(/TOTAL\s*\$?\s*([\d.]+)/i);
  if (totalMatch) {
    return totalMatch[1].replace(/\./g, '');
  }
  
  // Último fallback: buscar el número más grande
  const numbers = text.match(/\d{4,}/g);
  if (numbers) {
    const largest = Math.max(...numbers.map(n => parseInt(n.replace(/\./g, ''))));
    return largest.toString();
  }
  
  return '';
}

// Extraer fecha
function extractDate(text, pattern) {
  const match = text.match(pattern.date);
  if (match) {
    return normalizeDate(match[1]);
  }
  
  // Fallback: fecha de hoy
  return new Date().toISOString().split('T')[0];
}

// Normalizar fecha a formato YYYY-MM-DD
function normalizeDate(dateStr) {
  const parts = dateStr.split(/[\/\-]/);
  if (parts.length === 3) {
    let [day, month, year] = parts;
    
    // Si el año es de 2 dígitos, agregar 20
    if (year.length === 2) {
      year = `20${year}`;
    }
    
    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
  }
  return dateStr;
}

// Calcular confianza del parseo
function calculateConfidence(text, extracted) {
  let score = 0;
  
  // Tiene texto suficiente
  if (text.length > 50) score += 20;
  
  // Detectó un total
  if (extracted.total) score += 40;
  
  // Detectó una fecha
  if (extracted.date && extracted.date !== new Date().toISOString().split('T')[0]) {
    score += 30;
  }
  
  // Detectó proveedor específico
  if (extracted.provider !== 'GENERICO') score += 10;
  
  return Math.min(score, 100);
}

// Normalizar nombres de productos
function normalizeProductName(name, mappings = {}) {
  let normalized = name.trim();
  
  // Aplicar mapeos específicos primero
  for (const [pattern_key, mapped_name] of Object.entries(mappings)) {
    const regex = new RegExp(pattern_key, 'i');
    if (regex.test(normalized)) {
      return mapped_name;
    }
  }
  
  // Normalizaciones generales
  normalized = normalized
    .replace(/waNzLLA/gi, 'BOLSA')
    .replace(/TROQUELADA/gi, '')
    .replace(/\s+/g, ' ')
    .trim();
  
  // Capitalizar primera letra de cada palabra
  normalized = normalized.toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
  
  return normalized;
}

// Extraer items (opcional, para futuro)
function extractItems(text, pattern) {
  const lines = text.split('\n');
  const items = [];
  
  // Patrón específico para tabla de VANNI
  if (pattern && pattern.itemsPattern) {
    const matches = [...text.matchAll(pattern.itemsPattern)];
    for (const match of matches) {
      const [_, codigo, cantidad, unidad, descripcion, precioUnit, total] = match;
      // Usar mapeo por código si existe
      let productName = pattern.productCodes && pattern.productCodes[codigo] 
        ? pattern.productCodes[codigo]
        : descripcion.trim();
      
      // Calcular precio unitario y total con IVA
      const cantidadItem = parseFloat(match[2].replace(/,/g, '.'));
      const precioUnitarioSinIVA = parseFloat(match[5].replace(/,/g, '.'));
      const totalSinIVA = parseFloat(match[6].replace(/\./g, ''));
      
      let precioUnitarioConIVA = precioUnitarioSinIVA;
      let totalConIVA = totalSinIVA;
      
      if (pattern.hasIVA === false) {
        precioUnitarioConIVA = Math.round(precioUnitarioSinIVA * 1.19);
        totalConIVA = Math.round(totalSinIVA * 1.19);
      }
      
      items.push({
        codigo: codigo,
        ingrediente: productName,
        cantidad: cantidadItem,
        unidad: (unidad && unidad !== '[ENVASE') ? unidad : 'unidad',
        precio_unitario: precioUnitarioConIVA,
        total: totalConIVA
      });
    }
    return items;
  }
  
  // Patrón genérico para otras boletas
  for (const line of lines) {
    // Buscar líneas con formato: "Producto $1.990" o "Producto 1990"
    const match = line.match(/(.+?)\s+\$?\s*([\d.]+)$/);
    if (match) {
      const price = parseInt(match[2].replace(/\./g, ''));
      // Solo considerar si el precio es razonable (> 100)
      if (price > 100 && price < 1000000) {
        items.push({
          name: match[1].trim(),
          price: price
        });
      }
    }
  }
  
  return items;
}

// Formatear items como tabla HTML
export function formatReceiptTable(parsedData) {
  if (!parsedData.items || parsedData.items.length === 0) {
    return '<p>No se detectaron items</p>';
  }

  const fmt = (n) => Math.round(n).toLocaleString('es-CL');
  
  let table = `
    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
      <thead>
        <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Código</th>
          <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Ingrediente</th>
          <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151;">Cantidad</th>
          <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151;">Unidad</th>
          <th style="padding: 12px; text-align: right; font-weight: 600; color: #374151;">P. Unitario</th>
          <th style="padding: 12px; text-align: right; font-weight: 600; color: #374151;">Total</th>
        </tr>
      </thead>
      <tbody>
  `;

  parsedData.items.forEach((item, idx) => {
    const bgColor = idx % 2 === 0 ? '#ffffff' : '#f9fafb';
    table += `
      <tr style="background: ${bgColor}; border-bottom: 1px solid #e5e7eb;">
        <td style="padding: 10px; font-family: monospace; font-size: 12px; color: #6b7280;">${item.codigo}</td>
        <td style="padding: 10px; font-weight: 600; color: #374151;">${item.ingrediente}</td>
        <td style="padding: 10px; text-align: center; color: #374151;">${item.cantidad}</td>
        <td style="padding: 10px; text-align: center; color: #6b7280;">${item.unidad}</td>
        <td style="padding: 10px; text-align: right; font-weight: 600; color: #059669;">$${fmt(item.precio_unitario)}</td>
        <td style="padding: 10px; text-align: right; font-weight: 700; color: #10b981;">$${fmt(item.total)}</td>
      </tr>
    `;
  });

  table += `
      </tbody>
      <tfoot>
        <tr style="background: #f0fdf4; border-top: 2px solid #10b981;">
          <td colspan="5" style="padding: 12px; font-weight: 700; color: #059669; text-align: right;">TOTAL:</td>
          <td style="padding: 12px; text-align: right; font-weight: 800; color: #10b981; font-size: 18px;">$${fmt(parsedData.total)}</td>
        </tr>
      </tfoot>
    </table>
  `;
  
  return table;
}

// Formatear items para mostrar
export function formatReceiptItems(parsedData) {
  if (!parsedData.items || parsedData.items.length === 0) {
    return 'No se detectaron items';
  }

  const fmt = (n) => Math.round(n).toLocaleString('es-CL');
  let output = '';

  parsedData.items.forEach(item => {
    // Compatibilidad con ambos formatos
    const nombre = item.ingrediente || item.name;
    const precioUnitario = item.precio_unitario || Math.round(item.price / item.cantidad);
    const total = item.total || item.price;
    
    output += `${nombre}\n`;
    output += `${item.cantidad} ${item.unidad}\n`;
    output += `$${fmt(precioUnitario)}\n`;
    output += `$${fmt(total)}\n\n`;
  });

  output += `TOTAL\n$${fmt(parsedData.total)}`;
  
  return output;
}

// 🎯 Función principal de parseo
export function parseReceipt(rawText) {
  const providerKey = detectProvider(rawText);
  const pattern = patterns[providerKey];
  
  const extracted = {
    provider: pattern.provider,
    total: extractTotal(rawText, pattern),
    date: extractDate(rawText, pattern),
    items: extractItems(rawText, pattern),
    rawText: rawText,
    detectedPattern: providerKey,
    hasIVA: pattern.hasIVA !== false // Por defecto true, excepto VANNI
  };
  
  // Si es VANNI (sin IVA), calcular total con IVA
  if (!extracted.hasIVA && extracted.total) {
    const totalSinIVA = parseInt(extracted.total);
    const totalConIVA = Math.round(totalSinIVA * 1.19);
    extracted.totalSinIVA = totalSinIVA;
    extracted.totalConIVA = totalConIVA;
    extracted.total = totalConIVA.toString(); // Usar total con IVA como principal
  }
  
  extracted.confidence = calculateConfidence(rawText, extracted);
  
  return extracted;
}

// Exportar patrones para edición en UI
export { patterns };
