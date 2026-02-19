# La Ruta 11 - Technology Stack

## Programming Languages

### Frontend
- **JavaScript/JSX** - React components, interactive features
- **Astro** - SSR framework, page routing, islands architecture
- **HTML/CSS** - Markup and styling
- **TailwindCSS** - Utility-first CSS framework

### Backend
- **PHP** - Server-side API endpoints, business logic
- **SQL** - Database queries and migrations

### Configuration
- **JSON** - Package manifests, configuration files
- **Markdown** - Documentation

## Framework Versions

### app3 (Customer App)
```json
{
  "astro": "^4.11.5",
  "react": "^18.3.1",
  "react-dom": "^18.3.1",
  "@astrojs/react": "^3.6.0",
  "@astrojs/node": "^8.3.4",
  "@astrojs/tailwind": "^6.0.2",
  "tailwindcss": "^3.4.17"
}
```

### caja3 (Cashier POS)
```json
{
  "astro": "^4.11.5",
  "react": "^18.3.1",
  "react-dom": "^18.3.1",
  "@astrojs/react": "^3.6.0",
  "@astrojs/tailwind": "^6.0.2",
  "tailwindcss": "^3.4.17",
  "tesseract.js": "^7.0.0"
}
```

### landing3 (Landing Page)
```json
{
  "astro": "^4.0.0",
  "react": "^18.0.0",
  "react-dom": "^18.0.0",
  "@astrojs/react": "^3.0.0",
  "@astrojs/tailwind": "^5.0.0",
  "tailwindcss": "^3.0.0"
}
```

## Key Dependencies

### UI Libraries
- **lucide-react** (^0.400.0 / ^0.540.0) - Icon library
- **react-icons** (^5.5.0) - Additional icon sets
- **recharts** (^3.1.2) - Charts and data visualization (app3, caja3)

### Backend Libraries (PHP)
- **AWS SDK for PHP** - S3 image storage integration
- **Composer** - PHP dependency management (landing3)
- **vlucas/phpdotenv** - Environment variable management
- **Gmail API** - Email sending via OAuth (tokens stored in MySQL)

### Email Infrastructure
- **Gmail API OAuth**: Tokens stored in `gmail_tokens` table (not filesystem)
- **Auto-refresh**: GitHub Actions workflow renews tokens every 30 minutes
- **Sender**: "La Ruta 11 <saboresdelaruta11@gmail.com>" with name in From header
- **Templates**: Mobile-first HTML with inline styles, table-based layout

### Special Features
- **tesseract.js** (^7.0.0) - OCR for receipt scanning (caja3 only)

## Database

### Technology
- **MySQL** - Relational database
- **Version**: Compatible with MySQL 5.7+

### Connection
- PHP MySQLi extension
- Connection pooling via `db_connect.php`
- Shared database between app3 and caja3

### Key Tables (40+ tables)
- productos, categorias, ingredientes, recetas
- ventas, ventas_items, ventas_customizations
- usuarios, cashiers
- combos, combo_items, combo_groups
- caja_movimientos, caja_historial
- compras, mermas
- tuu_orders, tuu_pagos_online
- notifications, reviews, visits
- gmail_tokens (OAuth tokens for email sending)
- rl6_credit_transactions (credit system transactions)

## External Services

### AWS Services
- **S3** - Image storage and CDN
  - Bucket: laruta11-images
  - Public read access for menu images
  - PHP SDK for uploads

### Payment Gateway
- **TUU.cl** - Chilean payment processor
  - REST API integration
  - Webhook callbacks for payment confirmation
  - Support for online payments and transfers

### Authentication
- **Google OAuth 2.0** - Customer authentication
  - OAuth flow for login/signup
  - Profile data retrieval
  - Session management via PHP

### Media
- **YouTube API** - Live streaming integration (contests)
- **Unsplash API** - Background images

## Build System

### Package Manager
- **npm** - Node.js package management
- **Composer** - PHP package management (landing3)

### Build Commands
```bash
# Development
npm run dev        # Start dev server with hot reload
npm run start      # Alias for dev

# Production
npm run build      # Build for production
npm run preview    # Preview production build
```

### Build Output
- **Astro**: Static files + server endpoints
- **Output directory**: `dist/`
- **SSR mode**: Node.js adapter for dynamic routes

## Development Tools

### Code Quality
- **ESLint** - JavaScript linting (implicit via Astro)
- **Prettier** - Code formatting (implicit)

### Version Control
- **Git** - Source control
- **GitHub** - Repository hosting

### Deployment
- **EasyPanel** - Hosting platform
- **Docker** - Containerization (Dockerfile present)
- **Shell scripts** - Deployment automation (deploy.sh)

## Environment Configuration

### Required Environment Variables

**Database**:
```
DB_HOST=localhost
DB_USER=username
DB_PASS=password
DB_NAME=database_name
```

**AWS S3**:
```
AWS_ACCESS_KEY_ID=key
AWS_SECRET_ACCESS_KEY=secret
AWS_REGION=us-east-1
AWS_BUCKET=laruta11-images
```

**TUU Payment Gateway**:
```
TUU_API_KEY=key
TUU_SECRET=secret
TUU_WEBHOOK_URL=url
```

**Google OAuth**:
```
GOOGLE_CLIENT_ID=id
GOOGLE_CLIENT_SECRET=secret
GOOGLE_REDIRECT_URI=uri
```

## Performance Optimizations

### Frontend
- Astro Islands architecture (partial hydration)
- Image optimization via S3 CDN
- Service Worker for offline caching
- Lazy loading for images and components

### Backend
- Database connection pooling
- JSON response caching where applicable
- Indexed database queries
- Optimized SQL queries with JOINs

### Assets
- TailwindCSS purging for minimal CSS
- WebP image format conversion (scripts/convert-to-webp.js)
- Minified JavaScript in production

## Browser Compatibility

### Target Browsers
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest, with specific fixes documented)
- Mobile browsers (iOS Safari, Chrome Mobile)

### PWA Features
- Service Worker API
- Web App Manifest
- Push Notifications API
- LocalStorage API
- Geolocation API

## Development Workflow

### Local Development
1. Install dependencies: `npm install`
2. Configure `.env` file
3. Start dev server: `npm run dev`
4. Access at `localhost:4321/4322/4323`

### Database Management
- **Beekeeper Studio** - Preferred SQL client
- SQL scripts executed manually (not PHP migrations)
- Schema changes via SQL files in `sql/` directories

### API Development
- PHP files in `api/` directories
- Direct file execution (no routing framework)
- JSON responses with CORS headers
- Error handling with try-catch blocks

## Testing

### Manual Testing
- Browser DevTools for frontend debugging
- PHP error logs for backend issues
- Database query testing in Beekeeper Studio

### Test Files
- `test_*.php` - API endpoint tests
- `debug_*.php` - Debugging utilities
- `test-*.html` - Frontend component tests

## Documentation

### Inline Documentation
- PHP comments for complex logic
- JSDoc comments for JavaScript functions
- SQL comments in migration files

### External Documentation
- README.md files in each app
- Markdown docs in `docs/` (caja3)
- API documentation in README_*.md files
