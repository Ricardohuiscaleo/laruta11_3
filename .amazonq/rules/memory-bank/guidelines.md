# La Ruta 11 - Development Guidelines

## Code Quality Standards

### Naming Conventions
- **Variables**: camelCase for JavaScript/React (`cartTotal`, `customerInfo`, `isProcessingOrder`)
- **Functions**: camelCase for JavaScript (`handleDrop`, `loadKanbanData`, `renderCard`)
- **PHP Functions**: snake_case (`executeQuery`, `get_flag_emoji`)
- **Database Tables**: snake_case (`tuu_orders`, `wallet_transactions`, `proyecciones_financieras`)
- **Database Columns**: snake_case (`user_id`, `payment_status`, `created_at`)
- **React Components**: PascalCase (`CheckoutApp`, `SmartAnalysis`, `ScheduleOrderModal`)
- **CSS Classes**: kebab-case with utility classes (`kanban-column`, `bg-white`, `text-gray-900`)

### File Organization
- **One endpoint per file**: Each PHP API endpoint is a separate file (e.g., `get_productos.php`, `create_order.php`)
- **Component files**: React components in `.jsx` files under `src/components/`
- **Utility files**: Helper functions in `src/utils/`
- **API directory structure**: Organized by feature (`/api/auth/`, `/api/orders/`, `/api/tuu/`)

### Code Formatting
- **Indentation**: 2 spaces for JavaScript/JSX, 4 spaces for PHP
- **String quotes**: Single quotes for JavaScript, double quotes for PHP
- **Line length**: No strict limit, but keep readable (typically under 120 characters)
- **Semicolons**: Used in JavaScript
- **Trailing commas**: Used in JavaScript arrays and objects

### Documentation Standards
- **Inline comments**: Used sparingly, only for complex logic
- **Function comments**: Minimal, code should be self-documenting
- **TODO comments**: Used for pending work
- **Debug logs**: `console.log()` for frontend, error_log() for backend

## Semantic Patterns

### React Component Patterns

#### State Management
```javascript
// useState for local component state
const [cart, setCart] = useState([]);
const [user, setUser] = useState(null);
const [loading, setLoading] = useState(false);

// useEffect for side effects and data loading
useEffect(() => {
  fetch('/api/endpoint.php')
    .then(response => response.json())
    .then(data => setData(data))
    .catch(error => console.error('Error:', error));
}, [dependency]);
```

#### Conditional Rendering
```javascript
// Ternary for simple conditions
{user ? <UserProfile /> : <LoginButton />}

// Logical AND for single condition
{isLoading && <LoadingSpinner />}

// IIFE for complex logic
{(() => {
  if (condition1) return <Component1 />;
  if (condition2) return <Component2 />;
  return <DefaultComponent />;
})()}
```

#### Event Handlers
```javascript
// Inline arrow functions for simple handlers
onClick={() => setShowModal(true)}

// Named functions for complex logic
const handleSubmit = async () => {
  setLoading(true);
  try {
    const response = await fetch('/api/endpoint.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await response.json();
    if (result.success) {
      // Handle success
    }
  } catch (error) {
    console.error('Error:', error);
  } finally {
    setLoading(false);
  }
};
```

### PHP API Patterns

#### Standard API Response Structure
```php
// Success response
echo json_encode([
    'success' => true,
    'data' => $result,
    'message' => 'Operation completed successfully'
]);

// Error response
echo json_encode([
    'success' => false,
    'error' => 'Error message',
    'details' => $additionalInfo
]);
```

#### Database Connection Pattern
```php
// Include config
require_once __DIR__ . '/../config.php';

// Check connection
if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Set charset
mysqli_set_charset($conn, 'utf8');
```

#### CORS Headers
```php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
```

#### Prepared Statements
```php
// Always use prepared statements for SQL queries
$stmt = $conn->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();
```

### JavaScript Patterns

#### Async/Await for API Calls
```javascript
// Preferred pattern for API calls
const loadData = async () => {
  try {
    const response = await fetch('/api/endpoint.php');
    const data = await response.json();
    if (data.success) {
      setData(data.data);
    } else {
      console.error('Error:', data.error);
    }
  } catch (error) {
    console.error('Error:', error);
  }
};
```

#### LocalStorage for Cart Management
```javascript
// Save to localStorage
localStorage.setItem('ruta11_cart', JSON.stringify(cart));

// Load from localStorage
const savedCart = localStorage.getItem('ruta11_cart');
if (savedCart) {
  setCart(JSON.parse(savedCart));
}

// Remove from localStorage
localStorage.removeItem('ruta11_cart');
```

#### Dynamic Styling with Inline Styles
```javascript
// Conditional inline styles
style={{
  background: isActive ? '#059669' : '#e5e5e5',
  color: isActive ? 'white' : '#666',
  width: `${percentage}%`
}}
```

### Modal Patterns

#### Modal Creation (Vanilla JS)
```javascript
// Create overlay
const overlay = document.createElement('div');
overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999';

// Create modal
const modal = document.createElement('div');
modal.style.cssText = 'background:white;border-radius:12px;max-width:600px';
modal.innerHTML = '...content...';

// Append and handle close
overlay.appendChild(modal);
document.body.appendChild(overlay);
overlay.onclick = (e) => { if (e.target === overlay) overlay.remove(); };
```

#### Modal Pattern (React)
```javascript
{showModal && (
  <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center"
       onClick={() => setShowModal(false)}>
    <div className="bg-white rounded-2xl max-w-md"
         onClick={(e) => e.stopPropagation()}>
      {/* Modal content */}
    </div>
  </div>
)}
```

## Internal API Usage Patterns

### Authentication Check
```javascript
// Check user session
fetch('/api/auth/check_session.php')
  .then(response => response.json())
  .then(data => {
    if (data.authenticated) {
      setUser(data.user);
    }
  });
```

### Order Creation
```javascript
// Create order with full details
const orderData = {
  amount: cartTotal,
  subtotal: cartSubtotal,
  customer_name: customerInfo.name,
  customer_phone: customerInfo.phone,
  user_id: user?.id || null,
  cart_items: cart,
  delivery_fee: deliveryFee,
  delivery_type: customerInfo.deliveryType,
  payment_method: 'cash',
  cashback_used: cashbackAmount
};

const response = await fetch('/api/create_order.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(orderData)
});
```

### Product Management
```javascript
// Load products
fetch('/api/get_productos.php')
  .then(response => response.json())
  .then(products => {
    if (Array.isArray(products)) {
      setProducts(products);
    }
  });

// Update product
const formData = new FormData();
formData.append('id', productId);
formData.append('name', productName);
formData.append('price', price);

fetch('/api/update_producto.php', {
  method: 'POST',
  body: formData
});
```

### Image Upload to S3
```javascript
// Upload image
const formData = new FormData();
formData.append('image', file);
formData.append('product_id', productId);

const response = await fetch('/api/upload_image.php', {
  method: 'POST',
  body: formData
});
```

## Frequently Used Code Idioms

### Currency Formatting
```javascript
// Chilean peso formatting
`$${parseInt(amount).toLocaleString('es-CL')}`

// Example: $15.000 (Chilean format with dots)
```

### Date Formatting
```javascript
// Chilean date format
new Date(dateString).toLocaleDateString('es-CL')

// Example: 15/01/2025
```

### Timestamp for Cache Busting
```javascript
// Add timestamp to API calls to prevent caching
fetch(`/api/endpoint.php?t=${Date.now()}`)
```

### Loose Equality for Database Values
```javascript
// Handle string/number inconsistency from database
if (user?.es_militar_rl6 == 1 || user?.es_militar_rl6 === '1')
```

### Array Filtering and Mapping
```javascript
// Filter and map pattern
const activeProducts = products
  .filter(p => p.is_active === 1 || p.active === 1)
  .map(p => ({
    id: p.id,
    name: p.name,
    price: parseFloat(p.price)
  }));
```

### Reduce for Totals
```javascript
// Calculate cart total
const total = cart.reduce((sum, item) => {
  return sum + (item.price * item.quantity);
}, 0);
```

### Conditional Class Names
```javascript
// TailwindCSS conditional classes
className={`px-4 py-2 rounded ${
  isActive 
    ? 'bg-blue-600 text-white' 
    : 'bg-gray-200 text-gray-700'
}`}
```

### Error Handling Pattern
```javascript
// Standard try-catch with user feedback
try {
  const response = await fetch('/api/endpoint.php', {
    method: 'POST',
    body: JSON.stringify(data)
  });
  const result = await response.json();
  
  if (result.success) {
    alert('✅ Operación exitosa');
  } else {
    alert('❌ Error: ' + result.error);
  }
} catch (error) {
  console.error('Error:', error);
  alert('❌ Error de conexión');
}
```

### Loading States
```javascript
// Standard loading pattern
const [isLoading, setIsLoading] = useState(false);

const handleAction = async () => {
  setIsLoading(true);
  try {
    // Perform action
  } finally {
    setIsLoading(false);
  }
};

// Render
<button disabled={isLoading}>
  {isLoading ? 'Procesando...' : 'Confirmar'}
</button>
```

## Popular Annotations and Emojis

### User-Facing Messages
- ✅ Success messages
- ❌ Error messages
- ⚠️ Warning messages
- 💰 Money/pricing related
- 🚚 Delivery related
- 📦 Inventory/stock related
- 🎯 Goals/targets
- 🔥 Promotions/hot items
- ⏰ Time-sensitive
- 📊 Analytics/reports

### Code Comments
```javascript
// PASO 1: Create payment
// PASO 2: Save delivery info
// CRITICAL: Check business hours
// TODO: Implement feature
// FIXME: Bug to fix
// NOTE: Important information
```

## Best Practices

### Security
- Always use prepared statements for SQL queries
- Validate and sanitize user input
- Store sensitive data in `.env` files
- Never commit credentials to git
- Use HTTPS for production
- Implement CORS properly

### Performance
- Cache bust with timestamps when needed
- Minimize API calls with batch operations
- Use localStorage for cart persistence
- Lazy load images
- Debounce search inputs

### User Experience
- Show loading states for async operations
- Provide clear error messages with emojis
- Confirm destructive actions
- Use optimistic UI updates when possible
- Mobile-first responsive design

### Code Organization
- Keep components small and focused
- Extract reusable logic into utilities
- Group related API endpoints in folders
- Use consistent naming across the codebase
- Document complex business logic

### Testing
- Test payment flows thoroughly
- Verify inventory deductions
- Check edge cases (empty cart, out of stock)
- Test on multiple devices and browsers
- Validate form inputs

### Deployment
- Build before deploying
- Test in staging environment
- Backup database before migrations
- Monitor error logs
- Keep dependencies updated
