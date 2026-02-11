-- Expandir palabras clave para reducir exigencia

-- 1. Agregar más palabras a categorías existentes
UPDATE job_keywords SET words = JSON_ARRAY(
    "mi plan", "iniciativa", "responsabilidad", "organizo", "organizar", 
    "planifico", "planificar", "decido", "decidir", "resuelvo", "resolver",
    "propongo", "proponer", "actúo", "actuar", "tomo decisiones", "liderazgo",
    "independiente", "autónomo", "proactivo", "eficiente", "eficacia"
) WHERE category = 'autonomia';

UPDATE job_keywords SET words = JSON_ARRAY(
    "comunico", "comunicar", "aviso", "avisar", "digo", "decir", "hablo", "hablar",
    "explico", "explicar", "informo", "informar", "pregunto", "preguntar",
    "escucho", "escuchar", "dialogo", "dialogar", "converso", "conversar",
    "transmito", "transmitir", "expreso", "expresar", "claro", "clara"
) WHERE category = 'comunicacion' AND position = 'both';

UPDATE job_keywords SET words = JSON_ARRAY(
    "compañera", "compañero", "juntos", "nosotros", "equipo", "grupo",
    "colaboro", "colaborar", "apoyo", "apoyar", "ayudo", "ayudar",
    "coopero", "cooperar", "trabajo en equipo", "unidos", "unidas",
    "coordinamos", "coordinación", "sincronizado", "armonía"
) WHERE category = 'equipo' AND position = 'both';

UPDATE job_keywords SET words = JSON_ARRAY(
    "limpieza", "limpiar", "desinfectar", "higiene", "sanitizar",
    "lavo", "lavar", "limpio", "desinfecto", "sanitizo",
    "orden", "ordenado", "ordenada", "pulcro", "pulcra",
    "cuidado", "cuidadoso", "meticuloso", "aseado", "aseada"
) WHERE category = 'higiene' AND position = 'both';

UPDATE job_keywords SET words = JSON_ARRAY(
    "calidad", "sabor", "presentación", "consistencia", "excelencia",
    "bueno", "buena", "mejor", "óptimo", "óptima", "perfecto", "perfecta",
    "cuidado", "detalle", "detalles", "esmero", "dedicación",
    "estándar", "estándares", "nivel", "superior"
) WHERE category = 'calidad' AND position = 'both';

UPDATE job_keywords SET words = JSON_ARRAY(
    "presión", "rápido", "eficiente", "calma", "calmado", "calmada",
    "tranquilo", "tranquila", "sereno", "serena", "control", "controlo",
    "manejo", "gestiono", "organizado", "organizada", "fluido", "fluida",
    "ritmo", "velocidad", "agilidad", "destreza"
) WHERE category = 'presion' AND position = 'both';

UPDATE job_keywords SET words = JSON_ARRAY(
    "idea", "ideas", "mejora", "mejorar", "mejoramos", "propongo", "proponer",
    "sugiero", "sugerir", "innovo", "innovar", "creo", "crear",
    "desarrollo", "desarrollar", "optimizo", "optimizar", "cambio", "cambios",
    "solución", "soluciones", "alternativa", "alternativas"
) WHERE category = 'proactividad' AND position = 'both';

-- 2. Agregar palabras específicas para maestro sanguchero
UPDATE job_keywords SET words = JSON_ARRAY(
    "punto de cocción", "temperatura", "sazón", "textura", "dorado", "dorar",
    "cocino", "cocinar", "preparo", "preparar", "condimento", "condimentos",
    "sal", "pimienta", "especias", "sabor", "sabroso", "sabrosa",
    "técnica", "técnicas", "habilidad", "experiencia", "destreza"
) WHERE category = 'calidad' AND position = 'maestro_sanguchero';

UPDATE job_keywords SET words = JSON_ARRAY(
    "tabla de cortar", "contaminación cruzada", "temperatura", "refrigeración",
    "manipulación", "manipular", "seguridad", "seguro", "segura",
    "protocolo", "protocolos", "norma", "normas", "cuidado", "precaución",
    "limpio", "limpia", "desinfectado", "desinfectada", "sanitario"
) WHERE category = 'higiene' AND position = 'maestro_sanguchero';

-- 3. Agregar palabras específicas para cajero
UPDATE job_keywords SET words = JSON_ARRAY(
    "explico", "informo", "atención", "servicio", "cliente", "clientes",
    "amable", "cordial", "sonrío", "sonreír", "saludo", "saludar",
    "ayudo", "ayudar", "resuelvo", "resolver", "paciencia", "paciente",
    "educado", "educada", "respetuoso", "respetuosa", "gentil"
) WHERE category = 'comunicacion' AND position = 'cajero';

-- 4. Consulta para analizar scoring actual
SELECT 
    position,
    category,
    label,
    weight,
    JSON_LENGTH(words) as cantidad_palabras,
    (weight * JSON_LENGTH(words)) as puntos_maximos_categoria
FROM job_keywords 
ORDER BY position, weight DESC;

-- 5. Calcular puntos máximos totales por posición
SELECT 
    CASE 
        WHEN position = 'both' THEN 'Ambas posiciones'
        WHEN position = 'maestro_sanguchero' THEN 'Solo Maestro'
        WHEN position = 'cajero' THEN 'Solo Cajero'
    END as aplica_a,
    SUM(weight * JSON_LENGTH(words)) as puntos_maximos_teoricos
FROM job_keywords 
GROUP BY position;

-- 6. Puntos máximos para maestro sanguchero (both + maestro_sanguchero)
SELECT 
    'Maestro Sanguchero Total' as posicion,
    SUM(weight * JSON_LENGTH(words)) as puntos_maximos_teoricos
FROM job_keywords 
WHERE position IN ('both', 'maestro_sanguchero');

-- 7. Puntos máximos para cajero (both + cajero)
SELECT 
    'Cajero Total' as posicion,
    SUM(weight * JSON_LENGTH(words)) as puntos_maximos_teoricos
FROM job_keywords 
WHERE position IN ('both', 'cajero');