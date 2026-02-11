import React, { useState } from 'react';
import { Star, X, ExternalLink } from 'lucide-react';
import { createPortal } from 'react-dom';

const GoogleReviewButton = ({ className = '' }) => {
  const [showModal, setShowModal] = useState(false);

  const modalContent = showModal ? (
    <div className="fixed inset-0 bg-black/80 backdrop-blur-sm z-[9999] flex items-center justify-center p-4" onClick={() => setShowModal(false)}>
      <div className="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden" onClick={(e) => e.stopPropagation()}>
        
        {/* Header */}
        <div className="bg-gradient-to-r from-blue-600 to-blue-700 p-6 relative">
          <button onClick={() => setShowModal(false)} className="absolute top-4 right-4 text-white/80 hover:text-white">
            <X size={24} />
          </button>
          <div className="flex items-center gap-3">
            <div className="bg-white/20 p-3 rounded-full">
              <Star size={24} className="text-yellow-300" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-white">¡Ayúdanos en Google!</h2>
              <p className="text-blue-100 text-sm">Tu opinión es muy valiosa</p>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="p-6">
          <div className="text-center mb-6">
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
              <div className="flex justify-center mb-2">
                <div className="flex gap-1">
                  {[...Array(5)].map((_, i) => (
                    <Star key={i} size={24} className="text-yellow-400 fill-current" />
                  ))}
                </div>
              </div>
              <p className="text-sm text-gray-700 font-medium">
                ¿Te gustó nuestro servicio? ¡Compártelo en Google!
              </p>
            </div>
            
            <div className="space-y-3 text-sm text-gray-600 text-left">
              <div className="flex items-start gap-2">
                <span className="text-green-500 font-bold">✓</span>
                <span>Ayudas a otros clientes a encontrarnos</span>
              </div>
              <div className="flex items-start gap-2">
                <span className="text-green-500 font-bold">✓</span>
                <span>Nos motivas a seguir mejorando</span>
              </div>
              <div className="flex items-start gap-2">
                <span className="text-green-500 font-bold">✓</span>
                <span>Solo toma 30 segundos</span>
              </div>
            </div>
          </div>

          <div className="space-y-3">
            <a
              href="https://g.page/r/CWcB3w3uXjdaEAE/review"
              target="_blank"
              rel="noopener noreferrer"
              onClick={() => setShowModal(false)}
              className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              <Star size={18} className="text-yellow-300" />
              Calificar en Google
              <ExternalLink size={16} />
            </a>
            
            <button
              onClick={() => setShowModal(false)}
              className="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors"
            >
              Tal vez más tarde
            </button>
          </div>
          
          <p className="text-xs text-gray-500 text-center mt-4">
            Se abrirá en una nueva pestaña
          </p>
        </div>
      </div>
    </div>
  ) : null;

  return (
    <>
      <button
        onClick={() => setShowModal(true)}
        className={`text-gray-600 hover:text-blue-600 transition-colors flex items-center gap-1 ${className}`}
        title="Recomendar en Google"
      >
        <Star size={16} className="text-yellow-400 fill-current" />
        <span className="text-xs">Recomendar</span>
      </button>

      {/* Renderizar modal usando portal para que aparezca fuera del header */}
      {typeof document !== 'undefined' && modalContent && createPortal(modalContent, document.body)}
    </>
  );
};

export default GoogleReviewButton;