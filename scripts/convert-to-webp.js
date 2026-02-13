/**
 * Script para convertir imágenes de S3 a formato WebP
 * 
 * Uso: node scripts/convert-to-webp.js
 */

const sharp = require('sharp');
const axios = require('axios');
const { S3Client, PutObjectCommand } = require('@aws-sdk/client-s3');
const mysql = require('mysql2/promise');

const config = {
  db: {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'laruta11'
  },
  s3: {
    region: process.env.AWS_REGION || 'us-east-1',
    bucket: process.env.S3_BUCKET || 'laruta11-images',
    accessKeyId: process.env.AWS_ACCESS_KEY_ID,
    secretAccessKey: process.env.AWS_SECRET_ACCESS_KEY
  }
};

const s3Client = new S3Client({
  region: config.s3.region,
  credentials: {
    accessKeyId: config.s3.accessKeyId,
    secretAccessKey: config.s3.secretAccessKey
  }
});

async function getImageUrlsFromDB() {
  const connection = await mysql.createConnection(config.db);
  const [rows] = await connection.execute(`
    SELECT id, image_url 
    FROM products 
    WHERE image_url IS NOT NULL 
      AND image_url LIKE '%laruta11-images.s3.amazonaws.com%'
      AND image_url NOT LIKE '%.webp'
  `);
  await connection.end();
  return rows;
}

async function processImage(product) {
  try {
    console.log(`\n[${product.id}] Procesando...`);
    
    const response = await axios.get(product.image_url, { responseType: 'arraybuffer' });
    const imageBuffer = Buffer.from(response.data);
    const originalSize = imageBuffer.length;
    
    const webpBuffer = await sharp(imageBuffer).webp({ quality: 85 }).toBuffer();
    const webpSize = webpBuffer.length;
    
    const urlParts = new URL(product.image_url);
    const originalKey = urlParts.pathname.substring(1);
    const newKey = originalKey.replace(/\.(jpg|jpeg|png)$/i, '.webp');
    
    await s3Client.send(new PutObjectCommand({
      Bucket: config.s3.bucket,
      Key: newKey,
      Body: webpBuffer,
      ContentType: 'image/webp',
      CacheControl: 'max-age=31536000'
    }));
    
    const newUrl = `https://${config.s3.bucket}.s3.amazonaws.com/${newKey}`;
    
    const connection = await mysql.createConnection(config.db);
    await connection.execute('UPDATE products SET image_url = ? WHERE id = ?', [newUrl, product.id]);
    await connection.end();
    
    const savings = ((1 - webpSize / originalSize) * 100).toFixed(1);
    console.log(`✅ ${(originalSize/1024).toFixed(1)}KB → ${(webpSize/1024).toFixed(1)}KB (${savings}% ahorro)`);
    
    return { success: true, originalSize, webpSize };
  } catch (error) {
    console.error(`❌ Error: ${error.message}`);
    return { success: false };
  }
}

async function main() {
  console.log('🚀 Convirtiendo imágenes a WebP...\n');
  
  const products = await getImageUrlsFromDB();
  console.log(`📋 ${products.length} imágenes encontradas\n`);
  
  const results = [];
  for (const product of products) {
    const result = await processImage(product);
    results.push(result);
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  
  const successful = results.filter(r => r.success);
  if (successful.length > 0) {
    const totalOriginal = successful.reduce((sum, r) => sum + r.originalSize, 0);
    const totalWebP = successful.reduce((sum, r) => sum + r.webpSize, 0);
    const totalSavings = ((1 - totalWebP / totalOriginal) * 100).toFixed(1);
    console.log(`\n✨ ${successful.length} imágenes convertidas`);
    console.log(`💰 Ahorro total: ${totalSavings}% (${((totalOriginal - totalWebP) / 1024 / 1024).toFixed(2)} MB)`);
  }
}

main().catch(console.error);
