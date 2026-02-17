<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;

$config = require_once __DIR__ . '/../config.php';

$s3 = new S3Client([
    'version' => 'latest',
    'region'  => $config['aws_region'],
    'credentials' => [
        'key'    => $config['aws_access_key_id'],
        'secret' => $config['aws_secret_access_key'],
    ],
]);

$localFile = '/tmp/logo-optimized.png';
$bucket = 'laruta11-images';
$key = 'menu/logo-optimized.png';

try {
    $result = $s3->putObject([
        'Bucket' => $bucket,
        'Key'    => $key,
        'SourceFile' => $localFile,
        'ContentType' => 'image/png',
    ]);
    
    echo "✅ Logo optimizado subido exitosamente!\n";
    echo "URL: " . $result['ObjectURL'] . "\n";
    echo "Tamaño: 243KB con transparencia\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
