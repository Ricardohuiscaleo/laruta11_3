#!/bin/bash

echo "=== VERIFICACIÓN DE APIS - CAJA3 ==="
echo ""

# Colores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Contadores
total=0
ok=0
issues=0

echo "📁 Escaneando archivos PHP en /api..."
echo ""

# Función para verificar un archivo
check_file() {
    local file=$1
    total=$((total + 1))
    
    # Verificar si carga config
    if grep -q "config\.php\|config_loader\.php" "$file"; then
        echo -e "${GREEN}✓${NC} $file"
        ok=$((ok + 1))
    else
        echo -e "${RED}✗${NC} $file ${YELLOW}(no carga config)${NC}"
        issues=$((issues + 1))
    fi
}

# Buscar todos los PHP en api/
while IFS= read -r file; do
    check_file "$file"
done < <(find api/ -name "*.php" -type f | sort)

echo ""
echo "=== RESUMEN ==="
echo "Total archivos: $total"
echo -e "${GREEN}OK: $ok${NC}"
echo -e "${RED}Issues: $issues${NC}"
echo ""

if [ $issues -eq 0 ]; then
    echo -e "${GREEN}✓ Todas las APIs cargan config correctamente${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠ Hay $issues archivos que no cargan config${NC}"
    exit 1
fi
