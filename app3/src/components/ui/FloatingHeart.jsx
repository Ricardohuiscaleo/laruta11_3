import React from 'react';
import { Heart } from 'lucide-react';

const FloatingHeart = ({ show, onAnimationEnd, startPosition }) => {
  if (!show) return null;
  
  return (
    <div 
      className="fixed pointer-events-none z-50"
      style={{
        left: startPosition?.x || '50%',
        top: startPosition?.y || '50%',
        transform: 'translate(-50%, -50%)'
      }}
      onAnimationEnd={onAnimationEnd}
    >
      <Heart 
        size={40} 
        className="text-red-500 fill-current animate-heart-float"
      />
    </div>
  );
};

export default FloatingHeart;