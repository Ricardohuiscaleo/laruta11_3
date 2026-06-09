<?php
$config = require_once __DIR__ . '/config.php';

$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$productId) {
    header('Location: /');
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']}",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.description, p.price, p.image_url, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header('Location: /');
        exit;
    }
} catch (Exception $e) {
    header('Location: /');
    exit;
}

$productUrl = "https://app.laruta11.cl/?product={$product['id']}";
$productName = htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8');
$productDesc = htmlspecialchars(
    mb_substr($product['description'] ?? 'Producto de La Ruta 11', 0, 160),
    ENT_QUOTES, 'UTF-8'
);
$productPrice = number_format($product['price'], 0, ',', '.');

$imageUrl = '';
if (!empty($product['image_url'])) {
    $img = $product['image_url'];
    $imageUrl = str_starts_with($img, 'http') ? $img : "https://app.laruta11.cl/{$img}";
}

$category = htmlspecialchars($product['category_name'] ?? 'Producto', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= $productName ?> - La Ruta 11</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= $productDesc ?>">

<meta property="og:title" content="<?= $productName ?> - $<?= $productPrice ?>">
<meta property="og:description" content="<?= $productDesc ?>">
<meta property="og:url" content="<?= $productUrl ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="La Ruta 11">
<meta property="og:locale" content="es_CL">
<?php if ($imageUrl): ?>
<meta property="og:image" content="<?= $imageUrl ?>">
<meta property="og:image:width" content="800">
<meta property="og:image:height" content="800">
<?php endif; ?>

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $productName ?>">
<meta name="twitter:description" content="<?= $productDesc ?>">
<?php if ($imageUrl): ?>
<meta name="twitter:image" content="<?= $imageUrl ?>">
<?php endif; ?>

</head>
<body style="margin:0;background:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh">
<div style="text-align:center;padding:20px">
<img src="<?= $imageUrl ?: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🍔</text></svg>' ?>" alt="<?= $productName ?>" style="width:280px;height:280px;object-fit:cover;border-radius:16px;margin-bottom:16px">
<h1 style="font-size:22px;margin:0 0 4px;color:#1a1a1a"><?= $productName ?></h1>
<p style="font-size:16px;margin:0 0 8px;color:#666"><?= $productDesc ?></p>
<p style="font-size:20px;font-weight:bold;margin:0 0 20px;color:#ea580c">$<?= $productPrice ?></p>
<a href="<?= $productUrl ?>" style="display:inline-block;background:#ea580c;color:#fff;padding:14px 32px;border-radius:12px;text-decoration:none;font-size:16px;font-weight:600">Ver en La Ruta 11</a>
</div>
<script>setTimeout(function(){window.location.replace("<?= $productUrl ?>")},1500)</script>
</body>
</html>
