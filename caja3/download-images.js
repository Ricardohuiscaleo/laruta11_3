const fs = require('fs');
const https = require('https');
const path = require('path');

// Productos que necesitan imágenes de Unsplash
const products = [
  { id: 1, name: 'Churrasco Vacuno', query: 'ciabatta beef sandwich' },
  { id: 104, name: 'Churrasco Queso (Vacuno)', query: 'ciabatta steak cheese sandwich' },
  { id: 3, name: 'Churrasco Pollo', query: 'ciabatta chicken sandwich' },
  { id: 105, name: 'Churrasco Queso (Pollo)', query: 'sandwich de pollo avocado' },
  { id: 4, name: 'Churrasco Vegetariano', query: 'veggie burger' },
  { id: 107, name: 'Completo Talquino Premium', query: 'gourmet hot dog' },
  { id: 12, name: 'Papas Fritas', query: 'french fries' },
  { id: 109, name: 'Papas Provenzal', query: 'fries with herbs' },
  { id: 6, name: 'Jugo de Frutilla', query: 'strawberry juice' },
  { id: 7, name: 'Jugo de Melón Tuna', query: 'melon juice' },
  { id: 8, name: 'Coca-Cola', query: 'coca-cola can' },
  { id: 9, name: 'Sprite', query: 'sprite can' },
  { id: 201, name: 'Mayonesa de Ajo', query: 'garlic mayonnaise' },
  { id: 202, name: 'Mayonesa de Aceituna', query: 'olive mayonnaise' },
  { id: 203, name: 'Mayonesa de Albahaca', query: 'basil mayonnaise' },
  { id: 401, name: 'Hamburguesa Clásica', query: 'classic hamburger' },
  { id: 402, name: 'Hamburguesa con Queso', query: 'cheeseburger' },
  { id: 403, name: 'Hamburguesa Doble', query: 'double burger' },
  { id: 404, name: 'Hamburguesa BBQ', query: 'bbq burger' },
  { id: 405, name: 'Hamburguesa Ruta 11', query: 'signature burger' }
];

const UNSPLASH_ACCESS_KEY = 'BanqJYPHGfqqCACbct84AzuSYYJ3mGxme_O5j-rA8as';

// Crear directorio de descarga
const downloadDir = './downloaded-images';
if (!fs.existsSync(downloadDir)) {
  fs.mkdirSync(downloadDir);
}

// Función para descargar imagen
const downloadImage = (url, filename) => {
  return new Promise((resolve, reject) => {
    const file = fs.createWriteStream(path.join(downloadDir, filename));
    
    https.get(url, (response) => {
      response.pipe(file);
      
      file.on('finish', () => {
        file.close();
        console.log(`✅ Descargado: ${filename}`);
        resolve();
      });
      
      file.on('error', (err) => {
        fs.unlink(path.join(downloadDir, filename), () => {});
        reject(err);
      });
    }).on('error', (err) => {
      reject(err);
    });
  });
};

// Función para obtener URL de Unsplash
const getUnsplashImage = async (query) => {
  return new Promise((resolve, reject) => {
    const url = `https://api.unsplash.com/search/photos?query=${encodeURIComponent(query)}&per_page=1&client_id=${UNSPLASH_ACCESS_KEY}`;
    
    https.get(url, (res) => {
      let data = '';
      
      res.on('data', (chunk) => {
        data += chunk;
      });
      
      res.on('end', () => {
        try {
          const result = JSON.parse(data);
          if (result.results && result.results[0]) {
            resolve(result.results[0].urls.regular);
          } else {
            reject(new Error('No image found'));
          }
        } catch (error) {
          reject(error);
        }
      });
    }).on('error', (err) => {
      reject(err);
    });
  });
};

// Función principal
const downloadAllImages = async () => {
  console.log('🚀 Iniciando descarga de imágenes...\n');
  
  const results = [];
  
  for (const product of products) {
    try {
      console.log(`🔍 Buscando imagen para: ${product.name}`);
      
      const imageUrl = await getUnsplashImage(product.query);
      const filename = `${product.id}-${product.name.toLowerCase().replace(/[^a-z0-9]/g, '-')}.jpg`;
      
      await downloadImage(imageUrl, filename);
      
      results.push({
        id: product.id,
        name: product.name,
        filename: filename,
        originalUrl: imageUrl,
        awsPath: `/images/${filename}` // Ruta sugerida para AWS
      });
      
      // Pausa para no saturar la API
      await new Promise(resolve => setTimeout(resolve, 1000));
      
    } catch (error) {
      console.log(`❌ Error con ${product.name}: ${error.message}`);
      results.push({
        id: product.id,
        name: product.name,
        error: error.message
      });
    }
  }
  
  // Guardar mapeo de imágenes
  fs.writeFileSync(
    path.join(downloadDir, 'image-mapping.json'),
    JSON.stringify(results, null, 2)
  );
  
  console.log('\n✅ Descarga completada!');
  console.log(`📁 Imágenes guardadas en: ${downloadDir}`);
  console.log(`📋 Mapeo guardado en: ${downloadDir}/image-mapping.json`);
  
  // Mostrar resumen
  const successful = results.filter(r => !r.error).length;
  const failed = results.filter(r => r.error).length;
  
  console.log(`\n📊 Resumen:`);
  console.log(`   ✅ Exitosas: ${successful}`);
  console.log(`   ❌ Fallidas: ${failed}`);
  
  if (successful > 0) {
    console.log(`\n🚀 Próximos pasos:`);
    console.log(`   1. Sube las imágenes a tu bucket AWS S3`);
    console.log(`   2. Actualiza las URLs en tu código con las de AWS`);
    console.log(`   3. Usa el archivo image-mapping.json como referencia`);
  }
};

// Ejecutar
downloadAllImages().catch(console.error);