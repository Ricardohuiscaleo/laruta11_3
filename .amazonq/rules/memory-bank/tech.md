# La Ruta 11 - Technology Stack

## Programming Languages

### Frontend
- **JavaScript/JSX**: React components, client-side logic
- **Astro**: Static site generation and routing
- **HTML/CSS**: Markup and styling
- **TailwindCSS**: Utility-first CSS framework

### Backend
- **PHP**: Server-side API endpoints (procedural style)
- **SQL**: Database queries and schema definitions

## Framework Versions

### Landing3
```json
{
  "astro": "^4.0.0",
  "react": "^18.0.0",
  "react-dom": "^18.0.0",
  "@astrojs/react": "^3.0.0",
  "@astrojs/tailwind": "^5.0.0",
  "tailwindcss": "^3.0.0",
  "lucide-react": "^0.540.0",
  "react-icons": "^5.5.0"
}
```

### App3
```json
{
  "astro": "^4.11.5",
  "react": "^18.3.1",
  "react-dom": "^18.3.1",
  "@astrojs/react": "^3.6.0",
  "@astrojs/node": "^8.3.4",
  "@astrojs/tailwind": "^6.0.2",
  "tailwindcss": "^3.4.17",
  "lucide-react": "^0.400.0",
  "react-icons": "^5.5.0",
  "recharts": "^3.1.2"
}
```

### Caja3
```json
{
  "astro": "^4.11.5",
  "react": "^18.3.1",
  "react-dom": "^18.3.1",
  "@astrojs/react": "^3.6.0",
  "@astrojs/tailwind": "^6.0.2",
  "tailwindcss": "^3.4.17",
  "lucide-react": "^0.400.0",
  "react-icons": "^5.5.0",
  "recharts": "^3.1.2",
  "tesseract.js": "^7.0.0"
}
```

## Database

### MySQL
- **Version**: Compatible with MySQL 5.7+
- **Database Name**: `laruta11`
- **Connection**: PDO with prepared statements
- **Host**: `websites_mysql-laruta11` (Docker container)
- **Credentials**: Stored in `.env` files

### Key Tables
- `usuarios`, `productos`, `ingredientes`, `recetas`
- `ventas`, `tuu_orders`, `caja_movimientos`
- `wallet_transactions`, `concurso_participantes`
- `notifications`, `reviews`, `combos`

## External Services & APIs

### Payment Processing
- **TUU Payment Gateway**
  - API Key authentication
  - Online and POS transactions
  - Environment: Production
  - Device Serial: 6010B232541610747

### Authentication
- **Google OAuth**
  - Multiple client IDs for different apps
  - Scopes: profile, email, calendar
  - Redirect URIs configured per app

### Email & Communication
- **Gmail API**
  - OAuth authentication
  - Sender: saboresdelaruta11@gmail.com
  - Order confirmations and notifications

### Cloud Storage
- **AWS S3**
  - Bucket: `laruta11-images`
  - Region: `us-east-1`
  - Access via AWS SDK for PHP

### AI & Analytics
- **Google Gemini API**
  - AI-powered business analytics
  - Smart recommendations

### Media & Content
- **Unsplash API**
  - Dynamic background images
  - Access key authentication

### Location Services
- **Google Maps API**
  - Geolocation and mapping
  - Delivery address validation

## Development Tools

### Build System
```bash
# Development
npm run dev          # Start dev server

# Production
npm run build        # Build for production
npm run preview      # Preview production build
```

### Package Management
- **npm**: JavaScript dependencies
- **Composer**: PHP dependencies (landing3 only)

### Ports
- Landing3: `4321`
- App3: `4322`
- Caja3: `4323`

## Deployment

### Platform
- **EasyPanel**: Docker-based hosting
- **Containers**: 3 separate apps + MySQL
- **SSL**: Automatic certificate management

### Build Configuration
```dockerfile
# Each app uses similar Dockerfile
FROM node:18-alpine
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build
CMD ["npm", "run", "preview"]
```

### Environment Variables
Each app requires `.env` file with:
- Database credentials
- API keys (TUU, Google, AWS, Gemini, Unsplash)
- OAuth client IDs and secrets
- Admin credentials

## Development Commands

### Installation
```bash
cd landing3 && npm install
cd ../app3 && npm install
cd ../caja3 && npm install
```

### Running Locally
```bash
# Landing
cd landing3 && npm run dev

# App
cd app3 && npm run dev

# Caja
cd caja3 && npm run dev
```

### Building
```bash
# Each app
npm run build
npm run preview
```

## PHP Backend

### Configuration
- **Config Files**: `config.php`, `load-env.php`
- **Database Connection**: `db_connect.php`
- **S3 Manager**: `S3Manager.php`
- **Session Management**: PHP sessions + cookies

### API Structure
- One PHP file per endpoint
- JSON responses
- CORS headers configured
- Error handling with try-catch
- Prepared statements for SQL

### Common Patterns
```php
// Database connection
require_once 'db_connect.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// JSON response
echo json_encode(['success' => true, 'data' => $result]);
```

## Security

### Authentication
- Session-based for admin
- Google OAuth for customers
- Token-based for API endpoints
- Password hashing for admin accounts

### Data Protection
- `.env` files excluded from git
- Prepared statements prevent SQL injection
- CORS configured per endpoint
- Sensitive credentials in environment variables

### File Security
- `.htaccess` for Apache configuration
- API directory protected
- Admin endpoints require authentication
