# La Ruta 11 - Project Structure

## Monorepo Organization

```
laruta11_3/
├── landing3/          # Landing page (laruta11.cl)
├── app3/              # Customer web app (app.laruta11.cl)
├── caja3/             # POS system (caja.laruta11.cl)
└── .amazonq/rules/    # Project documentation and rules
```

## Application Architecture

### Landing3 (Public Website)
```
landing3/
├── src/
│   ├── components/    # Astro/React components
│   ├── layouts/       # Page layouts
│   └── pages/         # Route pages
├── api/               # PHP backend endpoints
├── vendor/            # PHP Composer dependencies (AWS SDK)
├── .env               # Environment variables
└── config.php         # PHP configuration
```

**Purpose**: Public-facing marketing site with food truck information
**Tech Stack**: Astro, React, TailwindCSS, PHP, AWS S3
**Port**: 4321

### App3 (Customer Application)
```
app3/
├── src/
│   ├── components/    # React components (modals, UI elements)
│   ├── hooks/         # Custom React hooks
│   ├── utils/         # Utility functions (validation, effects)
│   ├── layouts/       # Page layouts
│   ├── pages/         # Astro route pages
│   └── mock/          # Mock data for development
├── api/               # PHP backend (400+ endpoints)
│   ├── orders/        # Order management
│   ├── users/         # User authentication
│   ├── tuu/           # Payment integration
│   ├── notifications/ # Real-time notifications
│   ├── location/      # Geolocation services
│   ├── food_trucks/   # Truck management
│   └── auth/          # Google OAuth
├── public/
│   ├── imagenes/      # Product images
│   ├── js/            # Client-side scripts
│   └── config.php     # PHP configuration
├── sql/               # Database schemas
└── docs/              # Technical documentation
```

**Purpose**: Customer-facing ordering application with full e-commerce features
**Tech Stack**: Astro, React, TailwindCSS, PHP, MySQL, AWS S3, TUU Payments
**Port**: 4322

### Caja3 (Point of Sale System)
```
caja3/
├── src/
│   ├── components/    # React components (MenuApp, modals, dashboards)
│   │   ├── modals/    # Modal components
│   │   └── ui/        # Reusable UI components
│   ├── hooks/         # Custom React hooks
│   ├── utils/         # Utility functions
│   ├── layouts/       # Page layouts
│   └── pages/         # Astro route pages
├── api/               # PHP backend (500+ endpoints)
│   ├── orders/        # Order processing
│   ├── compras/       # Purchase management
│   ├── admin/         # Admin operations
│   ├── location/      # Geolocation
│   ├── food_trucks/   # Truck schedules
│   └── notifications/ # Order notifications
├── public/
│   ├── imagenes/      # Product images
│   ├── js/            # Client-side scripts
│   └── config.php     # PHP configuration
├── sql/               # Database schemas
└── docs/              # Technical documentation
```

**Purpose**: Internal POS system for staff to manage orders, inventory, and cash operations
**Tech Stack**: Astro, React, TailwindCSS, PHP, MySQL, Tesseract.js (OCR)
**Port**: 4323

## Core Components

### Frontend Components (React)
- **MenuApp.jsx**: Main menu interface with product catalog
- **Modals**: ProductDetail, Profile, Security, SaveChanges, ShareProduct, Combo, PaymentPending
- **UI Components**: FloatingHeart, StarRating, SwipeToggle, NotificationIcon
- **Listeners**: OrdersListener, ChecklistsListener (real-time updates)
- **Integrations**: TUUPaymentIntegration, ReviewsModal, OrderNotifications

### Backend API Structure (PHP)
- **Database Connection**: `db_connect.php` - centralized MySQL connection
- **Configuration**: `config.php` - environment-specific settings
- **S3Manager**: AWS S3 integration for image uploads
- **CRUD Operations**: Products, ingredients, orders, users, combos
- **Business Logic**: Inventory tracking, cost calculations, sales analytics
- **Payment Processing**: TUU integration, transfer confirmations
- **Notifications**: Real-time order updates, admin alerts

### Database Schema
- **productos**: Product catalog with pricing and stock
- **ingredientes**: Ingredient inventory with units and costs
- **recetas**: Product recipes linking products to ingredients
- **ventas**: Sales transactions with payment details
- **tuu_orders**: Online payment records
- **usuarios**: User accounts and authentication
- **combos**: Combo definitions and pricing
- **food_trucks**: Truck locations and schedules
- **notifications**: System notifications
- **cash_register**: POS shift management

## Architectural Patterns

### Multi-Tenant Architecture
- Shared database across all applications
- Domain-based routing (landing, app, caja subdomains)
- Centralized authentication with role-based access

### API-First Design
- RESTful PHP endpoints for all operations
- JSON response format
- CORS enabled for cross-origin requests
- Centralized error handling

### Real-Time Updates
- Polling-based notification system
- OrdersListener and ChecklistsListener components
- Server-sent events for live order updates

### Inventory Management
- Recipe-based ingredient tracking
- Automatic stock deduction on sales
- Cost calculation from ingredient prices
- Purchase recommendations based on projections

### Payment Flow
- Multi-method support (TUU, transfer, cash, credit)
- Callback handling for online payments
- Order status synchronization
- Delivery fee calculation based on geolocation

## Deployment Configuration

### EasyPanel Setup
- Each app deployed as separate service
- Environment variables configured per app
- Build command: `npm run build`
- Start command: `npm run preview`
- Custom ports: 4321 (landing), 4322 (app), 4323 (caja)

### Environment Variables
- Database credentials (MySQL)
- AWS S3 credentials
- TUU payment API keys
- Google OAuth credentials
- Domain configurations
