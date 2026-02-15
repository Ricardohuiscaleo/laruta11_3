import React, { useState, useEffect } from 'react';

const LoadingScreen = ({ onComplete }) => {
  const [progress, setProgress] = useState(0);
  const [currentText, setCurrentText] = useState('Iniciando...');

  useEffect(() => {
    const loadResources = async () => {
      // 1. Inicializar
      setCurrentText('Iniciando aplicación...');
      setProgress(5);

      // 2. Cargar imágenes dinámicamente desde la base de datos
      setCurrentText('Cargando recursos...');
      
      let allImages = [];
      try {
        const response = await fetch('/api/get_menu_products.php?v=' + Date.now());
        const data = await response.json();
        
        if (data.success && data.menuData) {
          // Extraer todas las URLs de imágenes del menú
          Object.values(data.menuData).forEach(category => {
            if (Array.isArray(category)) {
              category.forEach(product => {
                if (product.image && !allImages.includes(product.image)) {
                  allImages.push(product.image);
                }
              });
            } else {
              Object.values(category).forEach(subcategory => {
                if (Array.isArray(subcategory)) {
                  subcategory.forEach(product => {
                    if (product.image && !allImages.includes(product.image)) {
                      allImages.push(product.image);
                    }
                  });
                }
              });
            }
          });
        }
      } catch (error) {
        console.error('Error loading menu images:', error);
      }
      
      // Si no se pudieron cargar imágenes, usar logo como fallback
      if (allImages.length === 0) {
        allImages = ['https://laruta11-images.s3.amazonaws.com/menu/logo.png'];
      }
      
      let loadedCount = 0;
      const totalImages = allImages.length;
      
      const imagePromises = allImages.map((src, index) => {
        return new Promise((resolve) => {
          const img = new Image();
          img.onload = async () => {
            try {
              // Garantizar que está 100% decodificada para visualización
              await img.decode();
              loadedCount++;
              const imageProgress = Math.floor((loadedCount / totalImages) * 80);
              setProgress(5 + imageProgress);
              setCurrentText(`Cargando recursos... ${loadedCount}/${totalImages}`);
              resolve();
            } catch {
              // Si decode() falla, usar onload normal
              loadedCount++;
              const imageProgress = Math.floor((loadedCount / totalImages) * 80);
              setProgress(5 + imageProgress);
              resolve();
            }
          };
          img.onerror = () => {
            loadedCount++;
            const imageProgress = Math.floor((loadedCount / totalImages) * 80);
            setProgress(5 + imageProgress);
            resolve();
          };
          img.src = src;
        });
      });
      
      await Promise.all(imagePromises);
      setProgress(85);

      // 3. Verificar conectividad
      setCurrentText('Conectando servicios...');
      await new Promise(resolve => setTimeout(resolve, 400));
      setProgress(95);

      // 4. Finalizar
      setCurrentText('¡Todo listo!');
      setProgress(100);
      
      setTimeout(onComplete, 300);
    };

    loadResources();
  }, [onComplete]);

  return (
    <div className="fixed inset-0 bg-gradient-to-br from-orange-400 via-red-500 to-pink-500 flex items-center justify-center z-50">
      {/* Animated background circles */}
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute -top-40 -left-40 w-80 h-80 bg-white bg-opacity-10 rounded-full animate-pulse"></div>
        <div className="absolute -bottom-40 -right-40 w-96 h-96 bg-white bg-opacity-5 rounded-full animate-bounce"></div>
        <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-white bg-opacity-5 rounded-full animate-ping"></div>
      </div>

      <div className="text-center z-10 px-8">
        {/* Logo */}
        <div className="mb-8">
          <img 
            src="https://laruta11-images.s3.amazonaws.com/menu/logo.png" 
            alt="La Ruta 11" 
            className="w-24 h-24 mx-auto animate-bounce object-contain"
          />
        </div>

        {/* Title */}
        <h1 className="text-4xl font-bold text-white mb-2 drop-shadow-lg">
          La Ruta 11
        </h1>
        <p className="text-white text-opacity-90 mb-8 text-lg">
          Paga online, recoge en local o pide delivery.
        </p>

        {/* Progress Bar */}
        <div className="w-64 mx-auto mb-6">
          <div className="bg-white bg-opacity-20 rounded-full h-3 overflow-hidden backdrop-blur-sm">
            <div 
              className="h-full bg-gradient-to-r from-white to-yellow-200 rounded-full transition-all duration-300 ease-out shadow-lg"
              style={{ width: `${progress}%` }}
            ></div>
          </div>
          <p className="text-white text-opacity-80 mt-3 text-sm font-medium">
            {progress}%
          </p>
        </div>

        {/* Loading Text */}
        <p className="text-white text-opacity-90 text-lg font-medium animate-pulse">
          {currentText}
        </p>
        
        {/* Loading indicator */}
        {progress < 85 && (
          <div className="mt-4">
            <div className="flex justify-center">
              <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div>
            </div>
          </div>
        )}


      </div>


    </div>
  );
};

export default LoadingScreen;