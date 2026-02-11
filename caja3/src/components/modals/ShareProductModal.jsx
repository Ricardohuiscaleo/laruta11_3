import React, { useState, useEffect } from 'react';
import { X, Share2, Instagram, QrCode } from 'lucide-react';

const ShareProductModal = ({ isOpen, onClose, product }) => {
  const [qrCode, setQrCode] = useState('');
  const [showQRModal, setShowQRModal] = useState(false);

  
  useEffect(() => {
    if (isOpen && product) {
      // Generar QR code usando API gratuita
      const productUrl = `${window.location.origin}?product=${product.id}`;
      const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(productUrl)}`;
      setQrCode(qrUrl);
    }
  }, [isOpen, product]);

  if (!isOpen || !product) return null;

  const productUrl = `${window.location.origin}?product=${product.id}`;
  // Determinar género según categoría
  const getGender = (product) => {
    const category = product.category_name?.toLowerCase() || '';
    const name = product.name?.toLowerCase() || '';
    
    // Femeninas
    if (category.includes('hamburguesa') || name.includes('hamburguesa') || 
        category.includes('papa') || name.includes('papa') || name.includes('empanada')) {
      return 'esta deliciosa';
    }
    
    // Masculinos por defecto (completo, sandwich, tomahawk, etc.)
    return 'este delicioso';
  };
  
  const shareText = `*Mira ${getGender(product)} ${product.name} de La Ruta 11!*\n\n${product.description}\n\n*Solo $${product.price.toLocaleString('es-CL')}*\n\nPídelo aquí:`;

  const handleWhatsApp = () => {
    const message = `${shareText} ${productUrl}`;
    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
  };



  const handleInstagram = () => {
    // Instagram no permite compartir links directamente, copiamos al portapapeles
    navigator.clipboard.writeText(`${shareText} ${productUrl}`);
    alert('📋 Texto copiado! Pégalo en tu historia de Instagram');
  };



  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-end animate-fade-in" onClick={onClose}>
      <div className="bg-white w-full mx-1 rounded-t-2xl flex flex-col animate-slide-up max-h-[80vh]" onClick={(e) => e.stopPropagation()}>
        <div className="border-b flex justify-between items-center p-4">
          <h2 className="font-bold text-gray-800 flex items-center gap-2 text-lg">
            <Share2 size={20} className="text-orange-500" />
            Compartir Producto
          </h2>
          <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600">
            <X size={20} />
          </button>
        </div>
        
        <div className="flex-grow overflow-y-auto p-4">
          {/* Producto Preview */}
          <div className="flex items-center gap-3 mb-6 p-3 bg-gray-50 rounded-lg">
            {product.image ? (
              <img src={product.image} alt={product.name} className="w-16 h-16 object-cover rounded-md" />
            ) : (
              <div className="w-16 h-16 bg-gray-200 rounded-md"></div>
            )}
            <div className="flex-1">
              <h3 className="font-semibold text-gray-800">{product.name}</h3>
              <p className="text-sm text-gray-600 line-clamp-2">{product.description}</p>
              <p className="text-orange-500 font-bold">${product.price.toLocaleString('es-CL')}</p>
            </div>
          </div>

          {/* Opciones de Compartir */}
          <div className="grid grid-cols-3 gap-3 mb-6">
            <button
              onClick={handleWhatsApp}
              className="flex flex-col items-center gap-2 p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors"
            >
              <div className="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/>
                </svg>
              </div>
              <p className="font-semibold text-gray-800 text-center">WhatsApp</p>
            </button>

            <button
              onClick={handleInstagram}
              className="flex flex-col items-center gap-2 p-4 bg-gradient-to-r from-purple-50 to-pink-50 hover:from-purple-100 hover:to-pink-100 rounded-lg transition-colors"
            >
              <div className="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                <Instagram size={24} className="text-white" />
              </div>
              <p className="font-semibold text-gray-800 text-center">Instagram</p>
            </button>

            <button
              onClick={() => setShowQRModal(true)}
              className="flex flex-col items-center gap-2 p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
            >
              {qrCode ? (
                <img src={qrCode} alt="QR Code" className="w-12 h-12 rounded" />
              ) : (
                <div className="w-12 h-12 bg-gray-500 rounded-full flex items-center justify-center">
                  <QrCode size={24} className="text-white" />
                </div>
              )}
              <p className="font-semibold text-gray-800 text-center">QR Code</p>
            </button>
          </div>


        </div>
      </div>
      
      {/* Modal QR */}
      {showQRModal && qrCode && (
        <div className="fixed inset-0 bg-black bg-opacity-80 z-60 flex items-center justify-center animate-fade-in" onClick={() => setShowQRModal(false)}>
          <div className="bg-white rounded-2xl p-6 max-w-sm mx-4" onClick={(e) => e.stopPropagation()}>
            <div className="text-center">
              <h3 className="font-bold text-gray-800 mb-4">Escanea el código QR</h3>
              <img src={qrCode} alt="QR Code" className="w-48 h-48 mx-auto mb-4" />
              <p className="text-sm text-gray-600 mb-4">Escanea para ver el producto</p>
              <button 
                onClick={() => setShowQRModal(false)}
                className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg"
              >
                Cerrar
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ShareProductModal;