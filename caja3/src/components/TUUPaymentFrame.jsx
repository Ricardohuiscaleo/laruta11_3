import React, { useState, useEffect } from 'react';
import { X, CreditCard, Shield, ArrowLeft } from 'lucide-react';

const TUUPaymentFrame = ({ paymentUrl, onSuccess, onCancel, onError }) => {
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    // Escuchar mensajes del iframe
    const handleMessage = (event) => {
      // Verificar origen por seguridad
      if (!event.origin.includes('haulmer.com') && !event.origin.includes('tuu.cl')) {
        return;
      }

      const data = event.data;
      
      if (data.type === 'payment_completed') {
        onSuccess(data);
      } else if (data.type === 'payment_cancelled') {
        onCancel();
      } else if (data.type === 'payment_error') {
        onError(data.error);
      } else if (data.type === 'iframe_loaded') {
        setIsLoading(false);
      }
    };

    window.addEventListener('message', handleMessage);
    return () => window.removeEventListener('message', handleMessage);
  }, [onSuccess, onCancel, onError]);

  const handleIframeLoad = () => {
    setIsLoading(false);
  };

  const handleIframeError = () => {
    setError('Error cargando la pasarela de pago');
    setIsLoading(false);
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in">
      <div className="bg-white w-full h-full flex flex-col animate-slide-up overflow-hidden">
        
        {/* Header */}
        <div className="bg-white border-b px-4 py-3 flex items-center justify-between shadow-sm">
          <div className="flex items-center gap-3">
            <button 
              onClick={onCancel}
              className="p-2 hover:bg-gray-100 rounded-full transition-colors"
            >
              <ArrowLeft size={20} className="text-gray-600" />
            </button>
            <div className="flex items-center gap-2">
              <Shield className="text-green-500" size={20} />
              <span className="font-semibold text-gray-800">Pago Seguro</span>
            </div>
          </div>
          
          <div className="flex items-center gap-2 text-sm text-gray-600">
            <CreditCard size={16} />
            <span>TUU • Webpay</span>
          </div>
        </div>

        {/* Loading State */}
        {isLoading && (
          <div className="flex-1 flex items-center justify-center bg-gray-50">
            <div className="text-center">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500 mx-auto mb-4"></div>
              <p className="text-gray-600">Cargando pasarela de pago...</p>
              <p className="text-sm text-gray-500 mt-2">Conexión segura con TUU</p>
            </div>
          </div>
        )}

        {/* Error State */}
        {error && (
          <div className="flex-1 flex items-center justify-center bg-gray-50">
            <div className="text-center max-w-md mx-auto px-4">
              <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <X className="text-red-500" size={24} />
              </div>
              <h3 className="text-lg font-semibold text-gray-800 mb-2">Error de Conexión</h3>
              <p className="text-gray-600 mb-4">{error}</p>
              <button 
                onClick={onCancel}
                className="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors"
              >
                Volver
              </button>
            </div>
          </div>
        )}

        {/* Payment Iframe */}
        {paymentUrl && !error && (
          <iframe
            src={paymentUrl}
            className={`flex-1 w-full border-0 ${isLoading ? 'hidden' : 'block'}`}
            onLoad={handleIframeLoad}
            onError={handleIframeError}
            sandbox="allow-same-origin allow-scripts allow-forms allow-top-navigation"
            title="Pasarela de Pago TUU"
          />
        )}

        {/* Security Footer */}
        <div className="bg-gray-50 border-t px-4 py-2">
          <div className="flex items-center justify-center gap-2 text-xs text-gray-500">
            <Shield size={12} />
            <span>Conexión segura SSL • Protegido por TUU</span>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TUUPaymentFrame;