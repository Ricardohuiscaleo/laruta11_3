# La Ruta 11 - Key Insights

## Database Structure
- **Production Database**: MySQL with different schema than local development
- **Category IDs (Production)**:
  - Hamburguesas: category_id=3, subcategory_id=6
  - Hamburguesas 100g: category_id=3, subcategory_id=5
  - Churrascos: category_id=2
  - Completos: category_id=4
  - Papas: category_id=12, subcategory_id=57
  - Pizzas: category_id=5, subcategory_id=60
  - Bebidas: category_id=5, subcategory_ids=[11,10,27,28] (Bebidas, Jugos, Café, Té)
  - Combos: category_id=8

## Development Workflow
- User executes SQL scripts in Beekeeper Studio (not local PHP)
- Prefer SQL scripts over PHP migration scripts
- Minimal code changes preferred
- Avoid breaking existing functionality

## System Architecture
- 100% database-driven menu system
- No hardcoded category definitions in frontend
- API parses JSON on server side (no JSON.parse in frontend)
- Dynamic category filtering based on `is_active` flag
