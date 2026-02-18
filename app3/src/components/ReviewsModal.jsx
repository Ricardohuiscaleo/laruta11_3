import React, { useState, useEffect } from 'react';

const ReviewsModal = ({ product, isOpen, onClose }) => {
  const [reviews, setReviews] = useState([]);
  const [stats, setStats] = useState(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [newReview, setNewReview] = useState({
    customer_name: '',
    rating: 5,
    comment: ''
  });
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (isOpen && product) {
      loadReviews();
    }
  }, [isOpen, product]);

  const loadReviews = async () => {
    try {
      const response = await fetch(`/api/get_reviews.php?product_id=${product.id}&t=${Date.now()}`);
      const data = await response.json();
      if (data.success) {
        setReviews(data.reviews);
        setStats(data.stats);
      } else {
        console.error('API Error:', data.error);
      }
    } catch (error) {
      console.error('Error loading reviews:', error);
    }
  };

  const handleSubmitReview = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await fetch('/api/add_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          product_id: product.id,
          ...newReview
        })
      });

      const data = await response.json();
      
      if (data.success) {
        setNewReview({ customer_name: '', rating: 5, comment: '' });
        setShowAddForm(false);
        loadReviews(); // Recargar reseñas
        alert('¡Gracias por tu reseña!');
      } else {
        console.error('Review submission error:', data.error);
        alert(data.error || 'Error al enviar reseña');
      }
    } catch (error) {
      console.error('Network error:', error);
      alert('Error de conexión al enviar reseña');
    } finally {
      setLoading(false);
    }
  };

  const renderStars = (rating, interactive = false, onRate = null) => {
    return [...Array(5)].map((_, i) => (
      <span
        key={i}
        className={`text-2xl ${interactive ? 'cursor-pointer hover:scale-110' : ''} ${
          i < rating ? 'text-yellow-400' : 'text-gray-300'
        }`}
        onClick={interactive ? () => onRate(i + 1) : undefined}
      >
        ★
      </span>
    ));
  };

  if (!isOpen) {
    return null;
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[70] p-4">
      <div className="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div className="p-6">
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-2xl font-bold">Reseñas - {product?.name}</h2>
            <button
              onClick={onClose}
              className="text-gray-500 hover:text-gray-700 text-2xl"
            >
              ×
            </button>
          </div>

          {stats && (
            <div className="mb-6 p-4 bg-gray-50 rounded-lg">
              <div className="flex items-center gap-4 mb-2">
                <div className="flex items-center">
                  {renderStars(Math.round(stats.average))}
                  <span className="ml-2 text-lg font-semibold">{stats.average}</span>
                </div>
                <span className="text-gray-600">({stats.total} reseñas)</span>
              </div>
              
              <div className="space-y-1">
                {[5, 4, 3, 2, 1].map(star => (
                  <div key={star} className="flex items-center gap-2 text-sm">
                    <span>{star}★</span>
                    <div className="flex-1 bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-yellow-400 h-2 rounded-full"
                        style={{
                          width: stats.total > 0 ? `${(stats.distribution[star] / stats.total) * 100}%` : '0%'
                        }}
                      ></div>
                    </div>
                    <span className="text-gray-600">({stats.distribution[star]})</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          <div className="flex gap-2 mb-4">
            <button
              onClick={() => setShowAddForm(!showAddForm)}
              className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700"
            >
              {showAddForm ? 'Cancelar' : 'Escribir Reseña'}
            </button>
          </div>

          {showAddForm && (
            <form onSubmit={handleSubmitReview} className="mb-6 p-4 border rounded-lg">
              <div className="mb-4">
                <label className="block text-sm font-medium mb-2">Tu nombre</label>
                <input
                  type="text"
                  value={newReview.customer_name}
                  onChange={(e) => setNewReview({...newReview, customer_name: e.target.value})}
                  className="w-full p-2 border rounded-lg"
                  required
                  maxLength="100"
                />
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium mb-2">Calificación</label>
                <div className="flex gap-1">
                  {renderStars(newReview.rating, true, (rating) => 
                    setNewReview({...newReview, rating})
                  )}
                </div>
              </div>

              <div className="mb-4">
                <label className="block text-sm font-medium mb-2">Comentario (opcional)</label>
                <textarea
                  value={newReview.comment}
                  onChange={(e) => setNewReview({...newReview, comment: e.target.value})}
                  className="w-full p-2 border rounded-lg"
                  rows="3"
                  maxLength="500"
                />
              </div>

              <button
                type="submit"
                disabled={loading}
                className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 disabled:opacity-50"
              >
                {loading ? 'Enviando...' : 'Enviar Reseña'}
              </button>
            </form>
          )}

          <div className="space-y-4">
            {reviews.length === 0 ? (
              <p className="text-gray-500 text-center py-8">
                Aún no hay reseñas. ¡Sé el primero!
              </p>
            ) : (
              reviews.map((review) => (
                <div key={review.id} className="border-b pb-4">
                  <div className="flex items-center gap-2 mb-2">
                    <span className="font-semibold">{review.customer_name}</span>
                    <div className="flex">{renderStars(review.rating)}</div>
                    <span className="text-gray-500 text-sm">
                      {new Date(review.created_at).toLocaleDateString()}
                    </span>
                  </div>
                  {review.comment && (
                    <p className="text-gray-700">{review.comment}</p>
                  )}
                </div>
              ))
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default ReviewsModal;