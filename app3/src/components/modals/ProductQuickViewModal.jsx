import React from 'react';
import { X, PlusCircle, MinusCircle, Heart, MessageSquare, Share2 } from 'lucide-react';

const ProductQuickViewModal = ({ product, isOpen, onClose, onAddToCart, onRemoveFromCart, quantity, isLiked, onLike, onOpenReviews, onShare }) => {
  if (!isOpen || !product) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in p-4" onClick={onClose}>
      <div className="bg-white w-full max-w-md rounded-2xl shadow-2xl animate-slide-up overflow-hidden flex flex-col max-h-[90vh]" onClick={(e) => e.stopPropagation()}>
        {/* Header fijo */}
        <div className="flex justify-between items-center p-4 border-b flex-shrink-0">
          <h2 className="text-lg font-bold text-gray-800">{product.name}</h2>
          <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600">
            <X size={24} />
          </button>
        </div>

        {/* Body con scroll */}
        <div className="flex-1 overflow-y-auto">
          {/* Imagen 4:5 */}
          <div className="aspect-[4/5] w-full bg-gray-100">
            {product.image ? (
              <img src={product.image} alt={product.name} className="w-full h-full object-cover" />
            ) : (
              <div className="w-full h-full flex items-center justify-center text-gray-400">
                Sin imagen
              </div>
            )}
          </div>

          {/* Descripción */}
          <div className="p-6">
            <p className="text-gray-600 text-sm leading-relaxed mb-4">{product.description}</p>
            
            {/* Stats y precio */}
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-3">
                <button 
                  onClick={(e) => {
                    e.stopPropagation();
                    onLike(product.id);
                  }} 
                  className="flex items-center gap-1 cursor-pointer transition-colors"
                >
                  <Heart size={16} className={`${isLiked ? 'text-red-500 fill-red-500' : 'text-red-500'}`} />
                  <span className="font-semibold text-gray-600 text-sm">{product.likes}</span>
                </button>
                
                <button 
                  onClick={(e) => {
                    e.stopPropagation();
                    onOpenReviews(product);
                  }}
                  className="flex items-center gap-1 cursor-pointer transition-colors"
                >
                  <MessageSquare size={16} className="text-gray-600" />
                  <span className="font-semibold text-gray-600 text-sm">{product.reviews?.count || 0}</span>
                </button>
                
                <button 
                  onClick={(e) => {
                    e.stopPropagation();
                    onShare(product);
                  }}
                  className="cursor-pointer transition-colors"
                  title="Compartir producto"
                >
                  <Share2 size={16} className="text-gray-600" />
                </button>
              </div>
              
              <span className="text-2xl font-bold text-green-600">
                ${product.price.toLocaleString('es-CL')}
              </span>
            </div>
          </div>
        </div>

        {/* Footer fijo con botón */}
        <div className="p-4 border-t flex-shrink-0">
          <div className="flex items-center gap-2">
            {quantity > 0 && (
              <>
                <button
                  onClick={() => onRemoveFromCart(product.id)}
                  className="bg-red-500 text-white hover:bg-red-600 transition-colors flex items-center justify-center w-12 h-12 rounded-lg"
                >
                  <MinusCircle size={24} />
                </button>
                <div className="bg-gray-100 text-gray-900 px-4 flex items-center justify-center h-12 rounded-lg min-w-[48px]">
                  <span className="font-bold text-lg">{quantity}</span>
                </div>
              </>
            )}
            <button 
              onClick={() => onAddToCart(product)} 
              className={`flex-1 font-bold transition-all duration-200 flex items-center justify-center gap-2 h-12 rounded-lg ${
                quantity > 0 
                  ? 'bg-yellow-500 text-black hover:bg-yellow-600' 
                  : 'bg-green-500 hover:bg-green-600 text-white'
              }`}
            >
              <PlusCircle size={20} />
              <span className="text-base">{quantity > 0 ? 'Agregar más' : 'Agregar al carrito'}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ProductQuickViewModal;
