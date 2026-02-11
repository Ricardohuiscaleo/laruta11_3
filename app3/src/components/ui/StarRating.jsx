import React from 'react';
import { Star } from 'lucide-react';

const StarRating = ({ rating, setRating }) => (
  <div className="flex space-x-1">
    {[1, 2, 3, 4, 5].map((star) => (
      <Star
        key={star}
        className={`cursor-pointer transition-colors ${
          star <= rating ? 'text-yellow-400 fill-current' : 'text-gray-300'
        }`}
        onClick={() => setRating && setRating(star)}
      />
    ))}
  </div>
);

export default StarRating;