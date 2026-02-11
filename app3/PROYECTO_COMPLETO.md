# 🍔 La Ruta 11 - Sistema Integral de Gestión de Restaurante

## 📋 Índice
1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Aplicaciones Principales](#aplicaciones-principales)
4. [Base de Datos](#base-de-datos)
5. [APIs y Endpoints](#apis-y-endpoints)
6. [Características Avanzadas](#características-avanzadas)
7. [Interfaz de Usuario](#interfaz-de-usuario)
8. [Sistema de Recompensas](#sistema-de-recompensas)
9. [Control de Calidad](#control-de-calidad)
10. [Sistema de Concurso](#sistema-de-concurso)
11. [Plan de Combos](#plan-de-combos)
12. [Deployment y Hosting](#deployment-y-hosting)
13. [Estructura de Archivos](#estructura-de-archivos)

---

## 🎯 Resumen Ejecutivo

**La Ruta 11** es una aplicación web progresiva (PWA) completa para gestión de restaurante que integra:

- **App Cliente**: Experiencia móvil moderna con carrito inteligente
- **Sistema de Caja**: POS optimizado para operaciones rápidas
- **Panel Admin**: Dashboard completo con analytics y gestión
- **Módulos Especializados**: Control de calidad, concursos, recompensas

### Tecnologías Principales
- **Frontend**: Astro + React/JSX + Tailwind CSS
- **Backend**: PHP + MySQL
- **Hosting**: Hostinger
- **PWA**: Service Workers, offline-ready, instalable

---

## 🏗️ Arquitectura del Sistema

### Stack Tecnológico

#### Frontend
```
Astro Framework
├── React/JSX Components
├── Tailwind CSS
├── PWA Features
├── Service Workers
└── Responsive Design
```

#### Backend
```
PHP APIs
├── MySQL Database
├── RESTful Endpoints
├── Session Management
├── File Upload (AWS S3)
└── Security Layers
```

### Configuración de Base de Datos
- **Base de Datos**: `u958525313_app`
- **Usuario**: `u958525313_app`
- **Servidor**: localhost (Hostinger)
- **Conexión**: PDO con manejo de errores

---

## 📱 Aplicaciones Principales

### 1. App Cliente (`/`)

#### Características Principales
- **Menú Interactivo**: Tarjetas de productos con imágenes
- **Carrito Inteligente**: Gestión de cantidades y totales
- **Sistema Social**: Likes, reviews, compartir productos
- **Búsqueda Avanzada**: Filtros por categoría y texto
- **Geolocalización**: Delivery automático por zona
- **Recompensas**: Sistema de puntos, sellos y cashback
- **Perfil de Usuario**: Historial, estadísticas, configuración

#### Componentes Clave
```jsx
// Componente principal de la app
MenuApp.jsx
├── ProductCard - Tarjeta de producto interactiva
├── CartModal - Modal del carrito de compras
├── CheckoutApp - Proceso de checkout optimizado
├── ProfileModalModern - Perfil de usuario avanzado
├── SearchModal - Búsqueda inteligente
├── NotificationSystem - Sistema de notificaciones
└── FloatingHeart - Animación de likes
```

#### Estados Principales
```javascript
const [activeCategory, setActiveCategory] = useState('hamburguesas');
const [cart, setCart] = useState([]);
const [user, setUser] = useState(null);
const [userLocation, setUserLocation] = useState(null);
const [notifications, setNotifications] = useState([]);
const [likedProducts, setLikedProducts] = useState(new Set());
```

### 2. Sistema de Caja (`/caja`)

#### Funcionalidades
- **Interfaz POS**: Optimizada para pantallas táctiles
- **Gestión de Pedidos**: Creación y seguimiento en tiempo real
- **Múltiples Pagos**: Efectivo, tarjeta, transferencia
- **Impresión**: Tickets y comprobantes
- **Control de Stock**: Verificación automática

#### Características Técnicas
- Diseño responsive para tablets
- Shortcuts de teclado para operación rápida
- Integración con impresoras térmicas
- Sincronización en tiempo real con inventario

### 3. Panel Administrativo (`/admin`)

#### Dashboard Principal
- **KPIs en Tiempo Real**: Ventas, productos, calidad
- **Gráficos Interactivos**: Tendencias y análisis
- **Alertas**: Stock bajo, problemas de calidad
- **Accesos Rápidos**: Módulos principales

#### Módulos Administrativos

##### Gestión de Productos (`/admin/products`)
- CRUD completo de productos
- Gestión de categorías
- Control de precios e inventario
- Subida de imágenes optimizada

##### Gestión de Ingredientes (`/admin/ingredients`)
- Catálogo de ingredientes
- Control de stock por ingrediente
- Costos y proveedores
- Alertas de reposición

##### Proyecciones Financieras (`/admin/projections`)
- Análisis de ventas históricas
- Proyecciones automáticas
- Escenarios optimista/pesimista
- Reportes exportables

---

## 🗄️ Base de Datos

### Tablas Principales

#### Productos y Menú
```sql
-- Productos principales
CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category_id INT,
    image_url VARCHAR(500),
    active TINYINT(1) DEFAULT 1,
    likes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categorías
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    active TINYINT(1) DEFAULT 1
);

-- Ingredientes
CREATE TABLE ingredientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    unit VARCHAR(50),
    cost_per_unit DECIMAL(10,2),
    stock_quantity DECIMAL(10,2) DEFAULT 0,
    min_stock DECIMAL(10,2) DEFAULT 0
);
```

#### Sistema de Usuarios
```sql
-- Usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    direccion TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo TINYINT(1) DEFAULT 1
);

-- Órdenes
CREATE TABLE tuu_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered') DEFAULT 'pending',
    delivery_type ENUM('pickup', 'delivery') DEFAULT 'pickup',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id)
);
```

#### Sistema de Recompensas
```sql
-- Wallet de usuario
CREATE TABLE user_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    balance DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id)
);

-- Cupones de usuario
CREATE TABLE user_coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    coupon_type ENUM('delivery_free', 'papas_bebida') NOT NULL,
    status ENUM('available', 'used') DEFAULT 'available',
    stamps_used INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id)
);
```

#### Control de Calidad
```sql
-- Preguntas de calidad
CREATE TABLE quality_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('planchero', 'cajero') NOT NULL,
    question TEXT NOT NULL,
    requires_photo TINYINT(1) DEFAULT 0,
    order_index INT NOT NULL,
    active TINYINT(1) DEFAULT 1
);

-- Checklists de calidad
CREATE TABLE quality_checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('planchero', 'cajero') NOT NULL,
    checklist_date DATE NOT NULL,
    responses JSON NOT NULL,
    score_percentage DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_date (role, checklist_date)
);
```

---

## 🔌 APIs y Endpoints

### Estructura de APIs
```
api/
├── productos/          # Gestión de productos
├── usuarios/           # Gestión de usuarios
├── ventas/            # Procesamiento de ventas
├── inventario/        # Control de inventario
├── calidad/           # Control de calidad
├── recompensas/       # Sistema de recompensas
├── concurso/          # Sistema de torneos
└── config/            # Configuración del sistema
```

### APIs de Productos
```php
// Obtener productos con filtros
GET api/get_productos.php
- Parámetros: category_id, active, search
- Respuesta: Array de productos con imágenes

// Crear/actualizar producto
POST api/save_producto.php
- Body: {name, description, price, category_id, image}
- Respuesta: {success, product_id}

// Eliminar producto
DELETE api/delete_producto.php
- Parámetros: product_id
- Respuesta: {success, message}
```

### APIs de Ventas
```php
// Registrar venta
POST api/registrar_venta.php
- Body: {user_id, items[], total, payment_method}
- Respuesta: {success, order_id, order_number}

// Obtener ventas
GET api/get_ventas.php
- Parámetros: date_from, date_to, user_id
- Respuesta: Array de ventas con detalles

// Procesar inventario
POST api/process_sale_inventory.php
- Body: {order_items[]}
- Respuesta: {success, inventory_updated}
```

### APIs de Recompensas
```php
// Obtener saldo de wallet
GET api/get_wallet_balance.php
- Parámetros: user_id
- Respuesta: {balance, transactions[]}

// Crear cupón
POST api/create_coupon.php
- Body: {user_id, coupon_type, stamps_used}
- Respuesta: {success, coupon_id}

// Usar cupón
POST api/use_coupon.php
- Body: {coupon_id, order_id}
- Respuesta: {success, discount_applied}
```

### APIs de Control de Calidad
```php
// Obtener preguntas por rol
GET api/get_questions.php
- Parámetros: role (planchero|cajero)
- Respuesta: Array de preguntas organizadas

// Guardar checklist
POST api/save_checklist.php
- Body: {role, responses[], photos[]}
- Respuesta: {success, score_percentage}

// Obtener score de calidad
GET api/get_quality_score.php
- Parámetros: date_from, date_to
- Respuesta: {average_score, by_role[]}
```

---

## 🌟 Características Avanzadas

### Sistema de Cache Busting
```javascript
// Implementación en frontend
const API_BASE = 'https://tudominio.com/api';
const timestamp = Date.now();

const fetchWithCacheBust = async (endpoint, options = {}) => {
  const url = `${API_BASE}/${endpoint}?_t=${timestamp}&_r=${Math.random()}`;
  const response = await fetch(url, {
    ...options,
    headers: {
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      'Expires': '0',
      ...options.headers
    }
  });
  return response.json();
};
```

### Sistema de Notificaciones
```javascript
// Componente de notificaciones
const NotificationSystem = ({ notifications, onMarkAsRead }) => {
  return (
    <div className="notification-container">
      {notifications.map(notification => (
        <NotificationItem 
          key={notification.id}
          notification={notification}
          onMarkAsRead={onMarkAsRead}
        />
      ))}
    </div>
  );
};
```

### Geolocalización y Delivery
```javascript
// Sistema de geolocalización
const useGeolocation = () => {
  const [location, setLocation] = useState(null);
  const [permission, setPermission] = useState('prompt');

  useEffect(() => {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          setLocation({
            lat: position.coords.latitude,
            lng: position.coords.longitude
          });
          setPermission('granted');
        },
        (error) => {
          setPermission('denied');
        }
      );
    }
  }, []);

  return { location, permission };
};
```

---

## 🎁 Sistema de Recompensas

### Estructura de Niveles
```javascript
const REWARD_LEVELS = {
  BRONZE: {
    name: 'Bronze',
    icon: '🥉',
    stamps_required: 6,
    cashback_amount: 6000,
    stamp_value: 1000
  },
  SILVER: {
    name: 'Silver',
    icon: '🥈',
    stamps_required: 6,
    cashback_amount: 12000,
    stamp_value: 2000
  },
  GOLD: {
    name: 'Gold',
    icon: '🥇',
    stamps_required: 6,
    cashback_amount: 18000,
    stamp_value: 3000
  }
};
```

### Cálculo de Puntos y Sellos
```javascript
// Lógica de cálculo
const calculateRewards = (totalSpent) => {
  const points = Math.floor(totalSpent / 10); // $10 = 1 punto
  const stamps = Math.floor(points / 1000);   // 1000 puntos = 1 sello
  
  return { points, stamps };
};

// Determinar nivel actual
const getCurrentLevel = (totalStamps) => {
  if (totalStamps >= 18) return 'GOLD';
  if (totalStamps >= 12) return 'SILVER';
  if (totalStamps >= 6) return 'BRONZE';
  return 'NONE';
};
```

### Cupones Disponibles
```javascript
const AVAILABLE_COUPONS = {
  DELIVERY_FREE: {
    name: 'Delivery Gratis',
    stamps_required: 2,
    description: 'Envío gratuito en tu próximo pedido'
  },
  PAPAS_BEBIDA: {
    name: 'Papas + Bebida Gratis',
    stamps_required: 4,
    description: 'Papas medianas y bebida de tu elección'
  }
};
```

---

## 🎯 Control de Calidad

### Estructura de Preguntas

#### Maestro Planchero (14 preguntas)
```javascript
const PLANCHERO_QUESTIONS = [
  // Pre-Servicio
  {
    section: 'Pre-Servicio',
    questions: [
      'Plancha limpia y desinfectada',
      'Ingredientes frescos verificados',
      'Utensilios limpios y organizados',
      'Uniforme completo y limpio'
    ]
  },
  // Durante Servicio
  {
    section: 'Durante Servicio',
    questions: [
      'Temperatura de cocción adecuada',
      'Tiempos de preparación respetados',
      'Presentación de productos correcta',
      'Higiene personal mantenida',
      'Área de trabajo ordenada'
    ]
  },
  // Post-Servicio
  {
    section: 'Post-Servicio',
    questions: [
      'Plancha limpia al finalizar',
      'Ingredientes almacenados correctamente',
      'Área de trabajo desinfectada',
      'Utensilios lavados y guardados',
      'Registro de temperaturas completo'
    ]
  }
];
```

#### Cajero (6 preguntas)
```javascript
const CAJERO_QUESTIONS = [
  // Pre-Servicio
  {
    section: 'Pre-Servicio',
    questions: [
      'Caja registradora funcionando',
      'Área de atención limpia y ordenada'
    ]
  },
  // Durante Servicio
  {
    section: 'Durante Servicio',
    questions: [
      'Atención cordial al cliente',
      'Órdenes tomadas correctamente',
      'Pagos procesados sin errores'
    ]
  },
  // Post-Servicio
  {
    section: 'Post-Servicio',
    questions: [
      'Cierre de caja correcto'
    ]
  }
];
```

### Componente de Checklist
```jsx
const QualityChecklist = ({ role, questions, onSubmit }) => {
  const [responses, setResponses] = useState({});
  const [photos, setPhotos] = useState({});

  const handleResponse = (questionId, response, observation = '') => {
    setResponses(prev => ({
      ...prev,
      [questionId]: { response, observation }
    }));
  };

  const calculateScore = () => {
    const totalQuestions = questions.length;
    const passedQuestions = Object.values(responses)
      .filter(r => r.response === 'yes').length;
    
    return Math.round((passedQuestions / totalQuestions) * 100);
  };

  return (
    <div className="quality-checklist">
      {questions.map((question, index) => (
        <QuestionItem
          key={index}
          question={question}
          onResponse={(response, observation) => 
            handleResponse(index, response, observation)
          }
          onPhotoUpload={(photo) => 
            setPhotos(prev => ({ ...prev, [index]: photo }))
          }
        />
      ))}
      <div className="score-display">
        Score: {calculateScore()}%
      </div>
    </div>
  );
};
```

---

## 🏆 Sistema de Concurso

### Estructura del Torneo
```javascript
const TOURNAMENT_STRUCTURE = {
  participants: 8,
  stages: [
    { name: 'Cuartos de Final', matches: 4, participants: 8 },
    { name: 'Semifinales', matches: 2, participants: 4 },
    { name: 'Final', matches: 1, participants: 2 },
    { name: 'Campeón', matches: 0, participants: 1 }
  ]
};
```

### Panel de Administración
```jsx
const TournamentAdmin = () => {
  const [tournament, setTournament] = useState(null);
  const [currentStage, setCurrentStage] = useState(0);

  const advanceToNextStage = async () => {
    const response = await fetch('/api/update_concurso_state.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        action: 'advance_stage',
        current_stage: currentStage 
      })
    });
    
    if (response.ok) {
      setCurrentStage(prev => prev + 1);
      loadTournamentData();
    }
  };

  return (
    <div className="tournament-admin">
      <div className="admin-controls">
        <button onClick={startTournament}>▶️ Iniciar Torneo</button>
        <button onClick={resetTournament}>🔄 Reiniciar</button>
        <button onClick={advanceToNextStage}>➡️ Siguiente Etapa</button>
      </div>
      
      <TournamentBracket 
        tournament={tournament}
        isAdmin={true}
        onMatchUpdate={handleMatchUpdate}
      />
    </div>
  );
};
```

### Vista EN VIVO
```jsx
const TournamentLive = () => {
  const [tournament, setTournament] = useState(null);

  useEffect(() => {
    const interval = setInterval(async () => {
      const response = await fetch('/api/get_concurso_live.php');
      const data = await response.json();
      setTournament(data);
    }, 1000); // Actualización cada segundo

    return () => clearInterval(interval);
  }, []);

  return (
    <div className="tournament-live">
      <div className="live-indicator">🔴 EN VIVO</div>
      <TournamentBracket 
        tournament={tournament}
        isLive={true}
      />
    </div>
  );
};
```

---

## 🍔 Plan de Combos (Próxima Implementación)

### Estructura de Base de Datos
```sql
-- Tabla principal de combos
CREATE TABLE combos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(500),
    category_id INT DEFAULT 8,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Productos que componen cada combo
CREATE TABLE combo_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    is_selectable TINYINT(1) DEFAULT 0,
    selection_group VARCHAR(50),
    FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES productos(id)
);

-- Opciones seleccionables para grupos
CREATE TABLE combo_selections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    selection_group VARCHAR(50) NOT NULL,
    product_id INT NOT NULL,
    additional_price DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE
);
```

### APIs para Combos
```php
// Obtener combos con productos
GET api/get_combos.php
- Respuesta: Array de combos con productos incluidos

// Guardar combo
POST api/save_combo.php
- Body: {name, description, price, items[], selections[]}
- Respuesta: {success, combo_id}

// Procesar venta de combo
POST api/process_combo_sale.php
- Body: {combo_id, selections[], quantity}
- Respuesta: {success, inventory_updated}
```

### Componente de Selección de Combo
```jsx
const ComboSelector = ({ combo, onAddToCart }) => {
  const [selections, setSelections] = useState({});

  const handleSelection = (group, productId) => {
    setSelections(prev => ({
      ...prev,
      [group]: productId
    }));
  };

  const addComboToCart = () => {
    const comboItem = {
      id: combo.id,
      name: combo.name,
      price: combo.price,
      type: 'combo',
      selections: selections,
      items: combo.items
    };
    
    onAddToCart(comboItem);
  };

  return (
    <div className="combo-selector">
      <h3>{combo.name}</h3>
      
      {/* Productos fijos */}
      <div className="fixed-items">
        {combo.items.filter(item => !item.is_selectable).map(item => (
          <div key={item.id} className="fixed-item">
            {item.name} x{item.quantity}
          </div>
        ))}
      </div>

      {/* Productos seleccionables */}
      <div className="selectable-items">
        {combo.selection_groups.map(group => (
          <SelectionGroup
            key={group.name}
            group={group}
            onSelection={(productId) => handleSelection(group.name, productId)}
          />
        ))}
      </div>

      <button onClick={addComboToCart}>
        Agregar Combo - ${combo.price.toLocaleString()}
      </button>
    </div>
  );
};
```

---

## 🎨 Interfaz de Usuario

### Componentes Principales

#### ProductCard
```jsx
const ProductCard = ({ product, onAddToCart, onLike, onShare }) => {
  const [showFloatingHeart, setShowFloatingHeart] = useState(false);
  const [heartPosition, setHeartPosition] = useState({ x: 0, y: 0 });

  const handleLike = (e) => {
    const rect = e.currentTarget.getBoundingClientRect();
    setHeartPosition({
      x: rect.left + rect.width / 2,
      y: rect.top + rect.height / 2
    });
    setShowFloatingHeart(true);
    onLike(product.id);
  };

  return (
    <div className="product-card">
      <div className="product-image">
        <img src={product.image} alt={product.name} />
        <FloatingHeart 
          show={showFloatingHeart}
          startPosition={heartPosition}
          onAnimationEnd={() => setShowFloatingHeart(false)}
        />
      </div>
      
      <div className="product-content">
        <h3>{product.name}</h3>
        <p>{product.description}</p>
        
        <div className="product-actions">
          <button onClick={handleLike}>
            <Heart /> {product.likes}
          </button>
          <button onClick={() => onShare(product)}>
            <Share2 />
          </button>
          <span className="price">
            ${product.price.toLocaleString()}
          </span>
        </div>
        
        <button onClick={() => onAddToCart(product)}>
          Agregar al Carrito
        </button>
      </div>
    </div>
  );
};
```

#### CheckoutApp
```jsx
const CheckoutApp = ({ cart, user, onOrderCreate }) => {
  const [customerInfo, setCustomerInfo] = useState({
    name: user?.nombre || '',
    phone: user?.telefono || '',
    email: user?.email || '',
    address: '',
    deliveryType: 'pickup'
  });
  
  const [availableRewards, setAvailableRewards] = useState([]);
  const [appliedRewards, setAppliedRewards] = useState([]);

  const calculateTotal = () => {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const rewardDiscount = appliedRewards.reduce((sum, reward) => sum + reward.discount, 0);
    return subtotal - rewardDiscount;
  };

  const handleApplyReward = (reward) => {
    setAppliedRewards(prev => [...prev, reward]);
    // Crear cupón en backend
    createCoupon(user.id, reward.type, reward.stamps_required);
  };

  return (
    <div className="checkout-app">
      <div className="order-summary">
        {cart.map(item => (
          <OrderItem key={item.id} item={item} />
        ))}
      </div>

      <div className="rewards-section">
        <h3>Recompensas Disponibles</h3>
        {availableRewards.map(reward => (
          <RewardItem 
            key={reward.id}
            reward={reward}
            onApply={() => handleApplyReward(reward)}
          />
        ))}
      </div>

      <div className="customer-info">
        <CustomerForm 
          info={customerInfo}
          onChange={setCustomerInfo}
        />
      </div>

      <div className="total-section">
        <div className="total">Total: ${calculateTotal().toLocaleString()}</div>
        <button onClick={() => onOrderCreate(customerInfo, appliedRewards)}>
          Confirmar Pedido
        </button>
      </div>
    </div>
  );
};
```

### Animaciones y Efectos

#### FloatingHeart
```jsx
const FloatingHeart = ({ show, startPosition, onAnimationEnd }) => {
  if (!show) return null;

  return (
    <div 
      className="floating-heart"
      style={{
        position: 'fixed',
        left: startPosition.x,
        top: startPosition.y,
        zIndex: 9999,
        pointerEvents: 'none',
        animation: 'floatUp 1s ease-out forwards'
      }}
      onAnimationEnd={onAnimationEnd}
    >
      ❤️
    </div>
  );
};
```

#### CSS Animations
```css
@keyframes floatUp {
  0% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
  50% {
    opacity: 1;
    transform: translateY(-30px) scale(1.2);
  }
  100% {
    opacity: 0;
    transform: translateY(-60px) scale(0.8);
  }
}

@keyframes pulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
}

.product-card:hover {
  animation: pulse 0.3s ease-in-out;
}
```

---

## 🚀 Deployment y Hosting

### Configuración de Hostinger

#### Estructura de Archivos en Servidor
```
public_html/
├── index.html              # App principal
├── admin/                  # Panel administrativo
├── caja/                   # Sistema de caja
├── api/                    # APIs PHP
├── assets/                 # CSS, JS, imágenes
├── _astro/                 # Assets compilados
└── .htaccess              # Configuración Apache
```

#### Configuración .htaccess
```apache
RewriteEngine On

# Redireccionar a HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Manejo de rutas SPA
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]

# Headers de caché
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
</IfModule>
```

### Proceso de Deployment

#### 1. Build del Proyecto
```bash
# Instalar dependencias
npm install

# Build para producción
npm run build

# Verificar build
ls -la dist/
```

#### 2. Subida a Hostinger
```bash
# Via FTP/SFTP
scp -r dist/* usuario@servidor:/public_html/

# O usando cPanel File Manager
# Subir carpeta dist completa
```

#### 3. Configuración de Base de Datos
```php
// config.php en servidor
<?php
$host = 'localhost';
$dbname = 'u958525313_app';
$username = 'u958525313_app';
$password = 'wEzho0-hujzoz-cevzin';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
```

### Características PWA

#### Service Worker
```javascript
// sw.js
const CACHE_NAME = 'ruta11-v1';
const urlsToCache = [
  '/',
  '/admin',
  '/caja',
  '/assets/css/main.css',
  '/assets/js/main.js',
  '/images/icon-192.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        return response || fetch(event.request);
      })
  );
});
```

#### Manifest.json
```json
{
  "name": "La Ruta 11",
  "short_name": "Ruta11",
  "description": "Sistema de gestión de restaurante",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#000000",
  "icons": [
    {
      "src": "/images/icon-192.png",
      "sizes": "192x192",
      "type": "image/png"
    },
    {
      "src": "/images/icon-512.png",
      "sizes": "512x512",
      "type": "image/png"
    }
  ]
}
```

---

## 📁 Estructura de Archivos

### Frontend (Astro + React)
```
src/
├── components/
│   ├── MenuApp.jsx           # Componente principal de la app
│   ├── ProductCard.jsx       # Tarjeta de producto
│   ├── CartModal.jsx         # Modal del carrito
│   ├── CheckoutApp.jsx       # Proceso de checkout
│   ├── ProfileModalModern.jsx # Perfil de usuario
│   ├── SearchModal.jsx       # Modal de búsqueda
│   ├── NotificationSystem.jsx # Sistema de notificaciones
│   ├── FloatingHeart.jsx     # Animación de likes
│   └── QualityChecklist.jsx  # Checklist de calidad
├── pages/
│   ├── index.astro           # App principal
│   ├── admin/
│   │   ├── index.astro       # Dashboard admin
│   │   ├── products.astro    # Gestión de productos
│   │   ├── ingredients.astro # Gestión de ingredientes
│   │   ├── calidad.astro     # Control de calidad
│   │   └── projections.astro # Proyecciones financieras
│   ├── caja/
│   │   └── index.astro       # Sistema de caja
│   └── concurso/
│       ├── admin.astro       # Admin del torneo
│       └── live.astro        # Vista en vivo
├── styles/
│   └── global.css            # Estilos globales
└── utils/
    ├── api.js                # Funciones de API
    ├── auth.js               # Autenticación
    └── helpers.js            # Funciones auxiliares
```

### Backend (PHP)
```
api/
├── config.php               # Configuración de BD
├── productos/
│   ├── get_productos.php    # Obtener productos
│   ├── save_producto.php    # Guardar producto
│   └── delete_producto.php  # Eliminar producto
├── usuarios/
│   ├── login.php            # Autenticación
│   ├── register.php         # Registro
│   └── get_profile.php      # Perfil de usuario
├── ventas/
│   ├── registrar_venta.php  # Registrar venta
│   ├── get_ventas.php       # Obtener ventas
│   └── process_sale_inventory.php # Procesar inventario
├── recompensas/
│   ├── get_wallet_balance.php # Saldo de wallet
│   ├── create_coupon.php    # Crear cupón
│   └── use_coupon.php       # Usar cupón
├── calidad/
│   ├── get_questions.php    # Preguntas de calidad
│   ├── save_checklist.php   # Guardar checklist
│   └── get_quality_score.php # Score de calidad
├── concurso/
│   ├── get_concurso_live.php # Estado del torneo
│   └── update_concurso_state.php # Actualizar torneo
└── setup/
    ├── setup_tables.php     # Crear tablas
    └── setup_combo_tables.php # Tablas de combos
```

### Assets y Recursos
```
public/
├── images/
│   ├── icon.png             # Logo de la app
│   ├── Completo-italiano.png # Imagen de producto
│   ├── completo-talquino.png # Imagen de producto
│   ├── salchi-papas.png     # Imagen de producto
│   └── papas-ruta11.png     # Imagen de producto
├── icons/
│   ├── icon-192.png         # Icono PWA 192x192
│   └── icon-512.png         # Icono PWA 512x512
└── manifest.json            # Manifest PWA
```

---

## 📊 Métricas y Analytics

### KPIs Principales
- **Ventas Diarias**: Total de ingresos por día
- **Productos Más Vendidos**: Top 10 productos
- **Usuarios Activos**: Usuarios únicos por período
- **Conversión de Carrito**: % de carritos que se convierten en venta
- **Tiempo de Sesión**: Promedio de tiempo en la app
- **Score de Calidad**: Promedio de checklists de calidad
- **Satisfacción del Cliente**: Basado en reviews y ratings

### Dashboard de Analytics
```jsx
const AnalyticsDashboard = () => {
  const [kpis, setKpis] = useState({});
  const [salesChart, setSalesChart] = useState([]);
  const [topProducts, setTopProducts] = useState([]);

  useEffect(() => {
    loadDashboardData();
  }, []);

  const loadDashboardData = async () => {
    const response = await fetch('/api/get_dashboard_kpis.php');
    const data = await response.json();
    
    setKpis(data.kpis);
    setSalesChart(data.sales_chart);
    setTopProducts(data.top_products);
  };

  return (
    <div className="analytics-dashboard">
      <div className="kpi-cards">
        <KPICard title="Ventas Hoy" value={kpis.daily_sales} />
        <KPICard title="Usuarios Activos" value={kpis.active_users} />
        <KPICard title="Calidad Promedio" value={`${kpis.quality_score}%`} />
        <KPICard title="Conversión" value={`${kpis.conversion_rate}%`} />
      </div>
      
      <div className="charts">
        <SalesChart data={salesChart} />
        <TopProductsChart data={topProducts} />
      </div>
    </div>
  );
};
```

---

## 🔧 Configuración y Mantenimiento

### Variables de Entorno
```javascript
// config.js
export const CONFIG = {
  API_BASE_URL: 'https://tudominio.com/api',
  AWS_S3_BUCKET: 'ruta11-images',
  GOOGLE_MAPS_API_KEY: 'tu-api-key',
  STRIPE_PUBLIC_KEY: 'pk_test_...',
  ENVIRONMENT: 'production'
};
```

### Backup y Seguridad
```sql
-- Script de backup diario
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u usuario -p u958525313_app > backup_$DATE.sql
aws s3 cp backup_$DATE.sql s3://ruta11-backups/
```

### Monitoreo de Errores
```javascript
// Error tracking
window.addEventListener('error', (event) => {
  const errorData = {
    message: event.message,
    filename: event.filename,
    lineno: event.lineno,
    colno: event.colno,
    stack: event.error?.stack,
    timestamp: new Date().toISOString(),
    user_agent: navigator.userAgent,
    url: window.location.href
  };

  fetch('/api/log_error.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(errorData)
  });
});
```

---

## 🚀 Roadmap y Próximas Funcionalidades

### Fase 1: Sistema de Combos (En Desarrollo)
- [ ] Crear tablas de base de datos para combos
- [ ] Desarrollar APIs de gestión de combos
- [ ] Implementar interfaz admin para combos
- [ ] Integrar selector de combos en APP/CAJA
- [ ] Sistema de inventario para combos

### Fase 2: Mejoras de UX
- [ ] Push notifications nativas
- [ ] Modo offline completo
- [ ] Integración con Google Maps
- [ ] Sistema de reviews mejorado
- [ ] Chat en vivo con soporte

### Fase 3: Analytics Avanzados
- [ ] Dashboard de métricas en tiempo real
- [ ] Reportes automáticos por email
- [ ] Predicciones de demanda con IA
- [ ] Análisis de comportamiento de usuarios
- [ ] Integración con Google Analytics

### Fase 4: Expansión
- [ ] Multi-restaurante
- [ ] Sistema de franquicias
- [ ] App móvil nativa (React Native)
- [ ] Integración con delivery partners
- [ ] Sistema de loyalty program avanzado

---

## 📞 Soporte y Documentación

### Contacto Técnico
- **Desarrollador**: Ricardo Huisca
- **Email**: [email de contacto]
- **Repositorio**: [URL del repositorio]

### Documentación Adicional
- `README.md` - Instrucciones de instalación
- `API_DOCS.md` - Documentación de APIs
- `DEPLOYMENT.md` - Guía de deployment
- `TROUBLESHOOTING.md` - Solución de problemas

### Recursos Útiles
- [Astro Documentation](https://docs.astro.build/)
- [React Documentation](https://reactjs.org/docs/)
- [Tailwind CSS](https://tailwindcss.com/docs)
- [PHP Manual](https://www.php.net/manual/)
- [MySQL Documentation](https://dev.mysql.com/doc/)

---

**La Ruta 11** - Sistema Integral de Gestión de Restaurante
*Versión 2.0 - Diciembre 2024*