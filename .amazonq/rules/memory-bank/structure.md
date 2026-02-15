# La Ruta 11 - Project Structure

## Monorepo Organization

```
laruta11_3/
├── app3/           # Customer web application (app.laruta11.cl)
├── caja3/          # Cashier POS system (caja.laruta11.cl)
├── landing3/       # Marketing landing page (laruta11.cl)
├── scripts/        # Utility scripts (image conversion, etc.)
└── .amazonq/       # Amazon Q AI assistant rules and memory
```

## Application Architecture

### app3/ - Customer Web Application
**Purpose**: Public-facing menu and ordering system for customers

**Key Directories**:
- `src/components/` - React components (MenuApp, CartModal, ProductModal, CheckoutModal, etc.)
- `src/pages/` - Astro pages (index, menu, orders, profile, etc.)
- `api/` - PHP backend endpoints (200+ API files)
  - `auth/` - Authentication (Google OAuth, session management)
  - `orders/` - Order creation, status updates, history
  - `tuu/` - Payment gateway integration
  - `users/` - User profiles, wallet, rewards
  - `tracker/` - Analytics and usage tracking
- `public/` - Static assets (images, sounds, manifest.json, service worker)
- `sql/` - Database migration scripts

**Architecture Pattern**: 
- Frontend: Astro + React (SSR + Islands)
- Backend: PHP REST APIs
- Database: MySQL (shared with caja3)
- Storage: AWS S3 for images
- Auth: Google OAuth + PHP sessions

### caja3/ - Cashier POS System
**Purpose**: Internal system for staff to process orders and manage operations

**Key Directories**:
- `src/components/` - React components (MenuApp, ComprasApp, DashboardApp, InventarioApp, etc.)
- `src/pages/` - Astro pages (index, compras, dashboard, inventario, etc.)
- `api/` - PHP backend endpoints (300+ API files)
  - `compras/` - Purchase management
  - `orders/` - Order processing
  - `tracker/` - QR code generation, analytics
  - `auth/` - Cashier authentication
- `docs/` - Technical documentation (30+ markdown files)
- `public/` - Static assets, health check tools
- `sql/` - Database schemas and migrations

**Architecture Pattern**:
- Frontend: Astro + React (SSR + Islands)
- Backend: PHP REST APIs
- Database: MySQL (shared with app3)
- Features: Cash register, inventory, financial reports, purchase planning

### landing3/ - Landing Page
**Purpose**: Marketing website and business information

**Key Directories**:
- `src/components/` - Astro/React components
- `src/pages/` - Static pages
- `api/` - Minimal PHP endpoints (S3 integration)
- `vendor/` - PHP Composer dependencies (AWS SDK)

**Architecture Pattern**:
- Frontend: Astro (static site generation)
- Backend: Minimal PHP for AWS S3
- Deployment: Static hosting

## Core Components

### Shared Infrastructure
- **Database**: Single MySQL database shared between app3 and caja3
- **Image Storage**: AWS S3 bucket (laruta11-images)
- **Payment Gateway**: TUU.cl API integration
- **Authentication**: 
  - Customers: Google OAuth
  - Cashiers: Username/password with PHP sessions

### Database Schema (Key Tables)
- `productos` - Menu items with categories, prices, recipes
- `categorias` - Product categories and subcategories
- `ingredientes` - Inventory ingredients with stock levels
- `recetas` - Product recipes (ingredient quantities)
- `ventas` - Sales/orders with items and customizations
- `usuarios` - Customer accounts
- `cashiers` - Cashier accounts
- `combos` - Combo definitions with rules
- `caja_movimientos` - Cash register transactions
- `compras` - Purchase orders
- `mermas` - Inventory waste tracking
- `tuu_orders` - Online payment records

### API Architecture
**Pattern**: RESTful PHP endpoints with JSON responses

**Common Structure**:
```php
// Database connection
require_once 'db_connect.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Business logic
$result = performOperation();

// JSON response
echo json_encode(['success' => true, 'data' => $result]);
```

**Key API Groups**:
- CRUD operations (get, create, update, delete)
- Business logic (calculate costs, process inventory)
- Integrations (TUU payments, Google OAuth)
- Analytics (sales reports, projections)

### Frontend Architecture
**Pattern**: Astro Islands with React components

**Component Structure**:
- Large interactive apps as single React components (MenuApp, ComprasApp)
- Smaller reusable components (modals, cards, forms)
- Astro pages for routing and SSR
- TailwindCSS for styling

**State Management**:
- React useState/useEffect for local state
- localStorage for persistence
- API calls for server state

## Deployment Configuration

### Ports
- landing3: 4321
- app3: 4322
- caja3: 4323

### Build Process
```bash
npm run build    # Astro build
npm run preview  # Production preview
```

### Environment Variables
Each app has `.env` file with:
- Database credentials
- AWS S3 credentials
- TUU API keys
- Google OAuth credentials

## Architectural Patterns

### Database-Driven Menu System
- Categories and products stored in database
- No hardcoded category definitions in frontend
- Dynamic filtering based on `is_active` flag
- JSON fields for customizations and extras

### Recipe-Based Inventory
- Products linked to recipes
- Recipes define ingredient quantities
- Automatic stock deduction on sales
- Real-time stock calculations

### Multi-Tenant Design
- Single database, multiple applications
- Shared data models (products, orders, inventory)
- Application-specific features (POS vs customer app)

### Progressive Web App (PWA)
- Service workers for offline capability
- Manifest.json for installability
- Push notifications for order updates
