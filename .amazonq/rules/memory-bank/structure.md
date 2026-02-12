# La Ruta 11 - Project Structure

## Monorepo Organization

```
laruta11_3/
├── landing3/          # Landing page (laruta11.cl)
├── app3/              # Customer web app (app.laruta11.cl)
├── caja3/             # POS system (caja.laruta11.cl)
└── .amazonq/rules/    # Project documentation
```

## Application Architecture

### Landing3 - Public Website
**Domain**: `laruta11.cl` | **Port**: 4321

```
landing3/
├── src/
│   ├── components/    # Astro/React components
│   ├── layouts/       # Page layouts
│   └── pages/         # Route pages
├── api/               # PHP backend endpoints
├── vendor/            # PHP dependencies (Composer)
├── .env               # Environment variables
└── config.php         # PHP configuration
```

**Purpose**: Public-facing brand website with AWS S3 media integration

---

### App3 - Customer Application
**Domain**: `app.laruta11.cl` | **Port**: 4322

```
app3/
├── src/
│   ├── components/    # React components for UI
│   ├── pages/         # Astro pages (routes)
│   ├── layouts/       # Page layouts
│   ├── hooks/         # React custom hooks
│   ├── icons/         # Icon components
│   ├── utils/         # Utility functions
│   └── mock/          # Mock data for development
├── api/               # PHP backend (300+ endpoints)
│   ├── admin/         # Admin management
│   ├── auth/          # Google OAuth, Gmail OAuth
│   ├── orders/        # Order processing
│   ├── users/         # User management
│   ├── tuu/           # TUU payment integration
│   ├── tuu-pagos-online/  # Online payments
│   ├── coupons/       # Discount codes
│   ├── notifications/ # Push notifications
│   ├── tracker/       # Order tracking
│   ├── jobs/          # Job applications
│   ├── rl6/           # Military discount system
│   ├── food_trucks/   # Truck scheduling
│   ├── location/      # Geolocation
│   └── cron/          # Scheduled tasks
├── public/
│   ├── js/            # Client-side JavaScript
│   ├── imagenes/      # Static images
│   ├── config.php     # PHP configuration
│   └── manifest.json  # PWA manifest
├── sql/               # Database schemas
├── game-isolated/     # Embedded games
└── docs/              # Technical documentation
```

**Key Components**:
- `CheckoutApp.jsx`: Shopping cart and checkout flow
- `seed_data.php`: Database initialization
- OAuth integration for Google authentication
- TUU payment gateway integration
- Wallet and cashback system
- Contest and voting system
- Real-time notifications

---

### Caja3 - Point of Sale System
**Domain**: `caja.laruta11.cl` | **Port**: 4323

```
caja3/
├── src/
│   ├── components/    # React components
│   ├── pages/         # Astro pages
│   ├── layouts/       # Page layouts
│   ├── hooks/         # React hooks
│   └── utils/         # Utilities
├── api/               # PHP backend (350+ endpoints)
│   ├── admin/         # Admin functions
│   ├── compras/       # Purchase management
│   ├── orders/        # Order processing
│   ├── auth/          # Authentication
│   ├── tuu/           # Payment processing
│   └── tracker/       # Tracking
├── public/
│   ├── js/
│   │   └── kanban.js  # Order board UI
│   ├── product-edit-modal-loader.js
│   ├── smart-analysis-loader.js
│   └── config.php
├── sql/               # Database schemas
└── docs/              # System documentation
```

**Key Features**:
- Cash register management (open/close shifts)
- Inventory and recipe management
- Product cost calculation
- Sales analytics and reporting
- Smart purchase recommendations
- Financial projections
- Multi-payment method support
- Shift-based reporting

---

## Architectural Patterns

### Frontend Architecture
- **Framework**: Astro with React islands
- **Styling**: TailwindCSS
- **State Management**: React hooks and context
- **PWA**: Service workers, offline support, manifest
- **Icons**: Lucide React, React Icons

### Backend Architecture
- **Language**: PHP (procedural style)
- **Database**: MySQL
- **API Pattern**: RESTful endpoints returning JSON
- **Authentication**: Session-based + Google OAuth
- **File Structure**: One file per endpoint

### Database Design
- **Shared Database**: `laruta11` (MySQL)
- **Key Tables**:
  - `usuarios`: User accounts
  - `productos`: Product catalog
  - `ingredientes`: Ingredient inventory
  - `recetas`: Product recipes
  - `ventas`: Sales transactions
  - `tuu_orders`: Online orders
  - `caja_movimientos`: Cash register movements
  - `wallet_transactions`: Cashback system
  - `concurso_participantes`: Contest entries

### Integration Points
- **AWS S3**: Image storage and retrieval
- **TUU Payment Gateway**: Online and POS payments
- **Google OAuth**: User authentication
- **Gmail API**: Email notifications
- **Gemini AI**: Analytics and insights
- **Unsplash API**: Background images
- **Google Maps API**: Location services
- **Google Calendar API**: Scheduling

### Deployment Architecture
- **Platform**: EasyPanel (Docker-based)
- **Containers**: 3 separate containers (landing, app, caja)
- **Database**: Shared MySQL container
- **Reverse Proxy**: Domain routing
- **SSL**: Managed by EasyPanel

## Core Component Relationships

```
Customer (app3) → Orders → Database ← POS (caja3)
                     ↓
                TUU Payment
                     ↓
              Inventory Update
                     ↓
            Financial Analytics
```

### Data Flow
1. **Order Creation**: Customer/Cashier creates order
2. **Payment Processing**: TUU gateway or cash/card
3. **Inventory Deduction**: Automatic stock reduction based on recipes
4. **Notification**: Customer receives order status updates
5. **Analytics**: Sales data aggregated for reporting
6. **Cashback**: Loyalty points credited to wallet
