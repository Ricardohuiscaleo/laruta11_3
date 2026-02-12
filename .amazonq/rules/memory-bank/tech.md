# La Ruta 11 - Technology Stack

## Programming Languages

### JavaScript/JSX
- **Version**: ES6+ modules
- **Usage**: Frontend components, React applications, utility scripts
- **Files**: `.jsx`, `.js`, `.mjs`

### PHP
- **Version**: 7.4+
- **Usage**: Backend API endpoints, database operations, business logic
- **Files**: `.php`

### SQL
- **Dialect**: MySQL
- **Usage**: Database schemas, migrations, queries
- **Files**: `.sql`

## Frontend Technologies

### Astro
- **Version**: ^4.11.5 (app3/caja3), ^4.0.0 (landing3)
- **Purpose**: Static site generation and SSR framework
- **Configuration**: `astro.config.mjs`
- **Commands**:
  - `npm run dev` - Development server
  - `npm run build` - Production build
  - `npm run preview` - Preview production build

### React
- **Version**: ^18.3.1 (app3/caja3), ^18.0.0 (landing3)
- **Purpose**: Interactive UI components
- **Key Libraries**:
  - `react-dom`: ^18.3.1 - DOM rendering
  - `react-icons`: ^5.5.0 - Icon library (GiHamburger, GiHotDog, etc.)
  - `lucide-react`: ^0.400.0 (app3/caja3), ^0.540.0 (landing3) - Modern icons

### TailwindCSS
- **Version**: ^3.4.17 (app3/caja3), ^3.0.0 (landing3)
- **Purpose**: Utility-first CSS framework
- **Configuration**: `tailwind.config.mjs`
- **Integration**: `@astrojs/tailwind` package

### Additional Frontend Libraries
- **recharts**: ^3.1.2 - Data visualization and charts
- **tesseract.js**: ^7.0.0 (caja3 only) - OCR for receipt scanning

## Backend Technologies

### PHP Stack
- **Core**: Native PHP for API endpoints
- **Database**: MySQLi extension for database connections
- **File Structure**: RESTful API endpoints in `/api` directories

### Composer Dependencies (landing3)
- **aws/aws-sdk-php**: AWS S3 integration
- **vlucas/phpdotenv**: Environment variable management
- **guzzlehttp/guzzle**: HTTP client for API requests

## Database

### MySQL
- **Purpose**: Primary data store
- **Connection**: MySQLi with `db_connect.php`
- **Key Tables**:
  - `productos` - Product catalog
  - `ingredientes` - Ingredient inventory
  - `recetas` - Product recipes
  - `ventas` - Sales transactions
  - `tuu_orders` - Online payments
  - `usuarios` - User accounts
  - `combos` - Combo products
  - `food_trucks` - Truck locations
  - `cash_register` - POS shifts

## External Services

### AWS S3
- **Purpose**: Image and media storage
- **Integration**: PHP SDK via Composer
- **Manager**: `S3Manager.php` class
- **Usage**: Product images, gallery uploads

### TUU.cl Payment Gateway
- **Purpose**: Online payment processing
- **Integration**: REST API with callbacks
- **Endpoints**: `/api/tuu/` and `/api/tuu-pagos-online/`
- **Features**: Payment creation, callback handling, order synchronization

### Google OAuth
- **Purpose**: User authentication
- **Integration**: OAuth 2.0 flow
- **Endpoints**: `/api/auth/`
- **Usage**: Customer login in app3

### Geolocation Services
- **Purpose**: Delivery fee calculation, truck location
- **Endpoints**: `/api/location/geocode.php`
- **Usage**: Distance-based pricing

## Build System

### Node.js & NPM
- **Package Manager**: npm
- **Scripts**:
  - `dev` / `start`: Development server
  - `build`: Production build
  - `preview`: Preview production build
  - `astro`: Astro CLI

### Build Configuration
- **Astro Config**: `astro.config.mjs`
  - React integration: `@astrojs/react`
  - Tailwind integration: `@astrojs/tailwind`
  - Node adapter: `@astrojs/node` (app3 only)

## Development Tools

### Environment Management
- **Files**: `.env`, `.env.example`
- **Loader**: `load-env.php` for PHP
- **Variables**: Database credentials, API keys, domain configs

### Version Control
- **System**: Git
- **Ignore**: `.gitignore` excludes `.env`, `config.php`, `node_modules/`, `dist/`

### Deployment
- **Platform**: EasyPanel
- **Method**: Docker containers
- **Dockerfile**: Available in app3 and landing3
- **Scripts**: `deploy.sh` for automation

## Development Commands

### App3 (Customer App)
```bash
cd app3
npm install          # Install dependencies
npm run dev          # Start dev server on port 4322
npm run build        # Build for production
npm run preview      # Preview production build
```

### Caja3 (POS System)
```bash
cd caja3
npm install          # Install dependencies
npm run dev          # Start dev server on port 4323
npm run build        # Build for production
npm run preview      # Preview production build
```

### Landing3 (Landing Page)
```bash
cd landing3
npm install          # Install dependencies
composer install     # Install PHP dependencies
npm run dev          # Start dev server on port 4321
npm run build        # Build for production
npm run preview      # Preview production build
```

## API Endpoints Structure

### Common Patterns
- **GET requests**: Retrieve data (products, orders, analytics)
- **POST requests**: Create/update data (orders, payments, inventory)
- **Response format**: JSON with status codes
- **Error handling**: Consistent error messages

### Key API Categories
- `/api/orders/` - Order management
- `/api/products.php` - Product CRUD
- `/api/get_ingredientes.php` - Inventory data
- `/api/tuu/` - Payment processing
- `/api/users/` - User management
- `/api/notifications/` - Real-time updates
- `/api/food_trucks/` - Truck operations
- `/api/admin/` - Administrative functions

## Performance Optimizations
- Static site generation with Astro
- Image optimization via AWS S3
- Lazy loading for components
- API response caching
- Database query optimization
- Minified production builds
