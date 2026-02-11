import React from 'react';
import { X, Clock, Calendar, MessageCircle } from 'lucide-react';

const ClosedInfoModal = ({ isOpen, onClose, nextOpenDay, nextOpenTime, isActive }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] flex items-center justify-center p-4 animate-fadeIn">
      <div className="bg-white rounded-3xl max-w-md w-full shadow-2xl animate-slideUp overflow-hidden">
        {/* Header */}
        <div className="bg-gradient-to-r from-orange-500 to-red-600 p-8 relative">
          <button 
            onClick={onClose}
            className="absolute top-4 right-4 text-white/80 hover:text-white transition-colors"
          >
            <X size={24} />
          </button>
          
          <div className="text-center text-white">
            <Clock size={48} className="mx-auto mb-3" strokeWidth={2.5} />
            <h2 className="text-2xl font-black mb-2">Próxima Apertura</h2>
            <p className="text-3xl font-black">{nextOpenDay} a las {nextOpenTime}</p>
          </div>
        </div>

        {/* Contenido */}
        <div className="p-6 space-y-4">
          {/* Programa tu pedido */}
          <div className="bg-green-50 rounded-xl p-4 border-2 border-green-200">
            <div className="flex items-start gap-3">
              <Calendar size={24} className="text-green-600 flex-shrink-0 mt-0.5" />
              <div>
                <h3 className="font-bold text-gray-800 mb-1">Programa tu pedido</h3>
                <p className="text-gray-700 text-sm">
                  Haz tu pedido ahora y programa la entrega
                </p>
              </div>
            </div>
          </div>

          {/* Consultas */}
          <a 
            href="https://wa.me/56936227422"
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-3 bg-green-500 hover:bg-green-600 text-white p-4 rounded-xl transition-colors"
          >
            <MessageCircle size={24} className="flex-shrink-0" />
            <div className="flex-1">
              <p className="font-bold">Consultas por WhatsApp</p>
              <p className="text-sm text-green-100">+56 9 3622 7422</p>
            </div>
          </a>

          {/* Botón cerrar */}
          <button
            onClick={onClose}
            className="w-full bg-gradient-to-r from-orange-500 to-red-600 text-white font-bold py-3 rounded-xl hover:shadow-lg transition-all"
          >
            Entendido
          </button>
        </div>
      </div>
    </div>
  );
};

export default ClosedInfoModal;
