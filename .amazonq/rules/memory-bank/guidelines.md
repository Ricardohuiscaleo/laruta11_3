# La Ruta 11 - Development Guidelines

## Code Quality Standards

### File Organization
- **Component files**: Single component per file with descriptive names (e.g., `GalagaGame.jsx`, `ApiMonitor.jsx`, `MenuApp.jsx`)
- **API endpoints**: Descriptive PHP filenames indicating functionality (e.g., `get_smart_projection_shifts.php`, `geocode.php`)
- **Utility scripts**: Node.js scripts with clear purpose (e.g., `project-summary.js`, `analyze-project.js`)
- **Directory structure**: Organized by feature/domain (api/orders/, api/location/, src/components/, src/utils/)

### Code Formatting Patterns

#### JavaScript/JSX
- **Indentation**: 2 spaces consistently
- **Imports**: Grouped logically - React first, then external libraries, then internal modules
- **Component structure**: Hooks at top, helper functions in middle, render at bottom
- **Destructuring**: Extensive use for props and state objects
- **Template literals**: Used for string interpolation and multi-line strings
- **Arrow functions**: Preferred for callbacks and functional components
- **Semicolons**: Consistently used at statement ends

#### PHP
- **Opening tags**: `<?php` on first line
- **Headers**: Set early (Content-Type, CORS headers)
- **Config loading**: Flexible path resolution with fallback array
- **Error handling**: Try-catch blocks with JSON error responses
- **Database**: PDO with prepared statements and error mode exceptions
- **Response format**: Consistent JSON structure with `success` boolean and `data`/`error` keys
- **Closing tags**: `?>` at file end

### Naming Conventions

#### JavaScript/JSX
- **Components**: PascalCase (e.g., `GalagaGame`, `ApiMonitor`, `ProductDetailModal`)
- **Functions**: camelCase (e.g., `checkStatus`, `categorizeApis`, `getOverallStats`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `API_CATEGORIES`, `STATUS_CONFIG`)
- **State variables**: Descriptive camelCase (e.g., `gameState`, `lastCheck`, `showTestPanel`)
- **Event handlers**: Prefix with `handle` or `on` (e.g., `handleKeyDown`, `onClick`)

#### PHP
- **Variables**: snake_case (e.g., `$current_hour`, `$sales_by_weekday`, `$total_avg`)
- **Arrays**: snake_case keys (e.g., `['success' => true, 'total_real' => $value]`)
- **Functions**: snake_case (e.g., `file_get_contents`, `json_encode`)
- **Database columns**: snake_case (e.g., `installment_amount`, `delivery_fee`, `created_at`)

#### SQL
- **Tables**: snake_case (e.g., `tuu_orders`, `food_trucks`, `cash_register`)
- **Columns**: snake_case (e.g., `payment_status`, `order_id`, `user_id`)

### Documentation Standards
- **Inline comments**: Used sparingly, explain "why" not "what"
- **Section comments**: Mark major code sections in complex files
- **JSDoc**: Not heavily used, prefer self-documenting code
- **README files**: Markdown format with clear sections and examples
- **API documentation**: Inline comments in PHP explaining business logic

## Semantic Patterns

### React Component Patterns

#### Hooks Usage (Frequency: 5/5 files)
```jsx
// State management with multiple useState calls
const [gameState, setGameState] = useState('playing');
const [score, setScore] = useState(0);
const [loading, setLoading] = useState(true);

// Refs for DOM access and persistent values
const canvasRef = useRef(null);
const gameLoopRef = useRef(null);
const gameObjects = useRef({ player: {}, bullets: [] });

// Effects for lifecycle and side effects
useEffect(() => {
  // Setup logic
  return () => {
    // Cleanup logic
  };
}, [dependencies]);

// Memoized callbacks to prevent re-renders
const handleAction = useCallback(() => {
  // Action logic
}, [dependencies]);
```

#### Component Structure Pattern (Frequency: 5/5 files)
```jsx
const ComponentName = () => {
  // 1. State declarations
  const [state, setState] = useState(initialValue);
  
  // 2. Refs
  const ref = useRef(null);
  
  // 3. Helper functions and callbacks
  const helperFunction = useCallback(() => {
    // Logic
  }, [deps]);
  
  // 4. Effects
  useEffect(() => {
    // Side effects
  }, [deps]);
  
  // 5. Render logic
  return (
    <div>
      {/* JSX */}
    </div>
  );
};

export default ComponentName;
```

#### Conditional Rendering Pattern (Frequency: 5/5 files)
```jsx
// Early return for loading states
if (loading && !lastCheck) {
  return <LoadingSpinner />;
}

// Ternary operators for inline conditions
{gameState === 'gameOver' && (
  <GameOverOverlay />
)}

// Logical AND for conditional display
{showTestPanel && (
  <TestPanel />
)}

// Optional chaining for safe property access
{item.response_time && (
  <ResponseTime time={item.response_time} />
)}
```

### PHP API Patterns

#### Config Loading Pattern (Frequency: 5/5 PHP files)
```php
// Flexible config path resolution
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}
```

#### Database Connection Pattern (Frequency: 5/5 PHP files)
```php
try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
```

#### API Response Pattern (Frequency: 5/5 PHP files)
```php
// Success response
echo json_encode([
    'success' => true,
    'data' => [
        'key' => $value,
        'items' => $array
    ]
]);

// Error response
echo json_encode([
    'success' => false,
    'error' => 'Error message'
]);

// Response with debug info
echo json_encode([
    'success' => true,
    'data' => $mainData,
    'debug' => [
        'timestamp' => $now,
        'query_count' => $count
    ]
]);
```

#### Request Validation Pattern (Frequency: 4/5 PHP files)
```php
// Method validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Parameter validation
$lat = floatval($_POST['lat']);
$lng = floatval($_POST['lng']);

if (!$lat || !$lng) {
    echo json_encode(['error' => 'Coordenadas inválidas']);
    exit();
}

// GET parameter with default
$mode = $_GET['mode'] ?? 'weighted';
```

### Data Processing Patterns

#### Array Manipulation (Frequency: 5/5 files)
```javascript
// Array filtering
const filtered = items.filter(item => item.status === 'active');

// Array mapping
const mapped = items.map(item => ({ ...item, newProp: value }));

// Array reduction
const total = items.reduce((sum, item) => sum + item.value, 0);

// Array sorting
items.sort((a, b) => b.value - a.value);

// Array slicing for pagination
const limited = items.slice(0, 10);
```

```php
// PHP array operations
$filtered = array_filter($items, function($item) {
    return $item['status'] === 'active';
});

$mapped = array_map(function($item) {
    return $item['value'];
}, $items);

$total = array_sum($values);
$keys = array_keys($array);
$values = array_values($array);
```

#### Date/Time Handling (Frequency: 4/5 files)
```php
// Timezone-aware date handling
$now = new DateTime('now', new DateTimeZone('America/Santiago'));
$currentHour = (int)$now->format('G');

// Date manipulation
$shiftToday = clone $now;
if ($currentHour >= 0 && $currentHour < 4) {
    $shiftToday->modify('-1 day');
}

// Date formatting
$dateKey = $date->format('Y-m-d');
$weekday = (int)$date->format('w');
```

```javascript
// JavaScript date handling
const timestamp = new Date().toLocaleString();
const dateKey = date.toISOString().split('T')[0];
```

### Error Handling Patterns

#### Try-Catch Pattern (Frequency: 5/5 files)
```javascript
// Async error handling
try {
  const response = await fetch(url);
  const data = await response.json();
  // Process data
} catch (error) {
  console.error('Error:', error);
  // Handle error
} finally {
  setLoading(false);
}
```

```php
// PHP error handling
try {
    // Database operations
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
```

### Performance Optimization Patterns

#### Memoization and Caching (Frequency: 4/5 files)
```javascript
// useCallback for expensive functions
const expensiveOperation = useCallback(() => {
  // Complex calculation
}, [dependencies]);

// useMemo for computed values
const computedValue = useMemo(() => {
  return expensiveCalculation(data);
}, [data]);

// Refs for values that don't trigger re-renders
const persistentValue = useRef(initialValue);
```

#### Cleanup Pattern (Frequency: 5/5 files)
```javascript
useEffect(() => {
  // Setup
  const interval = setInterval(checkStatus, 30000);
  const listener = window.addEventListener('keydown', handleKeyDown);
  
  // Cleanup
  return () => {
    clearInterval(interval);
    window.removeEventListener('keydown', handleKeyDown);
  };
}, [dependencies]);
```

### UI/UX Patterns

#### Loading States (Frequency: 5/5 files)
```javascript
// Loading indicator
if (loading && !data) {
  return (
    <div className="flex items-center justify-center">
      <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      <span className="ml-3">Cargando...</span>
    </div>
  );
}
```

#### Status Indicators (Frequency: 4/5 files)
```javascript
// Status configuration object
const STATUS_CONFIG = {
  ok: {
    icon: CheckCircle,
    color: 'text-green-600',
    bg: 'bg-green-50',
    label: 'Operativo'
  },
  error: {
    icon: XCircle,
    color: 'text-red-600',
    bg: 'bg-red-50',
    label: 'Error'
  }
};

// Dynamic status rendering
const statusConfig = STATUS_CONFIG[item.status];
<StatusIcon className={statusConfig.color} />
```

#### Responsive Design (Frequency: 5/5 files)
```jsx
// Tailwind responsive classes
<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  {/* Content */}
</div>

// Mobile-first approach
<div className="flex flex-col md:flex-row items-center">
  {/* Content */}
</div>
```

## Internal API Usage

### Fetch API Pattern (Frequency: 5/5 files)
```javascript
// GET request
const response = await fetch('/api/endpoint.php');
const data = await response.json();

// POST request
const response = await fetch('/api/endpoint.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(payload)
});

// Query parameters
const url = `/api/endpoint.php?param=${value}&mode=${mode}`;
const response = await fetch(url);
```

### Database Query Pattern (Frequency: 5/5 PHP files)
```php
// Prepared statement with parameters
$sql = "SELECT * FROM table WHERE status = :status ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['status' => 'active']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Complex aggregation query
$sql = "SELECT 
            o.amount,
            o.delivery_fee,
            o.created_at
        FROM tuu_orders o
        WHERE o.payment_status = 'paid'
        ORDER BY o.created_at ASC";
```

### External API Integration (Frequency: 3/5 files)
```php
// Google Maps API
$url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$config['api_key']}&language=es";
$response = file_get_contents($url);
$data = json_decode($response, true);

// Response validation
if ($data['status'] === 'OK' && !empty($data['results'])) {
    // Process results
}
```

## Code Idioms

### Frequently Used Patterns

#### Null Coalescing (Frequency: 5/5 files)
```php
$mode = $_GET['mode'] ?? 'default';
$amount = (float)($transaction['amount'] ?? 0);
```

```javascript
const value = data?.property ?? defaultValue;
const items = response.data || [];
```

#### Spread Operator (Frequency: 5/5 JS files)
```javascript
// Object spreading
const newState = { ...oldState, updatedProp: value };

// Array spreading
const combined = [...array1, ...array2];
const updated = [newItem, ...prev.slice(0, 9)];
```

#### Destructuring (Frequency: 5/5 JS files)
```javascript
// Object destructuring
const { player, bullets, enemies } = gameObjects.current;
const { success, data, error } = response;

// Array destructuring
const [state, setState] = useState(initial);
const [first, ...rest] = array;
```

#### Template Literals (Frequency: 5/5 files)
```javascript
const message = `Total: ${total.toLocaleString('es-CL')}`;
const url = `/api/endpoint.php?id=${id}&action=${action}`;
```

```php
$sql = "SELECT * FROM {$table} WHERE id = :id";
$dateKey = "$currentYear-$currentMonth-$day";
```

### Common Annotations and Comments

#### Section Markers (Frequency: 4/5 files)
```javascript
// Game objects
// Audio functions
// Input handling
// Render game
```

```php
// Modo de proyección
// Determinar día del turno actual
// Obtener TODO el histórico
// Generar proyección día por día
```

#### Business Logic Comments (Frequency: 5/5 PHP files)
```php
// Aplicar lógica de turnos: 00:00-03:59 pertenece al día anterior
// Si estamos antes de las 17:00, el turno actual aún no ha comenzado
// Saltar transacciones inválidas
// Usar promedio general para días sin datos
```

#### TODO and FIXME (Frequency: 2/5 files)
```javascript
// TODO: Implement error recovery
// FIXME: Handle edge case for timezone
```

## Best Practices Observed

1. **Separation of Concerns**: Clear separation between UI components, business logic, and data access
2. **Error Handling**: Comprehensive try-catch blocks with user-friendly error messages
3. **Type Safety**: Explicit type casting in PHP (`(int)`, `(float)`, `floatval()`)
4. **Security**: Prepared statements for SQL, input validation, CORS headers
5. **Performance**: useCallback/useMemo for optimization, efficient array operations
6. **Maintainability**: Small, focused functions with clear responsibilities
7. **Consistency**: Uniform code style across files, consistent naming conventions
8. **Debugging**: Debug information included in API responses for troubleshooting
9. **Timezone Awareness**: Explicit timezone handling for Chilean time (America/Santiago)
10. **Responsive Design**: Mobile-first approach with Tailwind responsive utilities
