import { useState, useEffect } from 'react';

export default function OrderNotifications({ userId, onNotificationsUpdate }) {
  const [notifications, setNotifications] = useState([]);
  const [showFloating, setShowFloating] = useState(false);
  const [newNotifications, setNewNotifications] = useState([]);

  useEffect(() => {
    // Solicitar permisos de notificación al iniciar
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission();
    }
    
    if (!userId) return;

    const checkNotifications = async () => {
      try {
        const response = await fetch(`/api/get_order_notifications.php?user_id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
          const freshNotifications = data.notifications || [];
          const unreadCount = data.unread_count || 0;
          
          // Actualizar contador en campanita
          if (onNotificationsUpdate) {
            onNotificationsUpdate(freshNotifications, unreadCount);
          }
          
          // Actualizar badge de PWA
          if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
              type: 'UPDATE_BADGE',
              count: unreadCount
            });
          }
          
          // Detectar nuevas notificaciones
          const reallyNew = freshNotifications.filter(notif => 
            notif.is_new && !notifications.some(existing => existing.id === notif.id)
          );
          
          if (reallyNew.length > 0) {
            setNewNotifications(reallyNew.slice(0, 3));
            setShowFloating(true);
            
            // Reproducir sonido de notificación
            try {
              const audio = new Audio('/notificacion.mp3');
              audio.volume = 0.7;
              audio.play().catch(() => {});
            } catch (e) {}
            
            // Mostrar notificación del sistema (PWA)
            reallyNew.forEach(notif => {
              if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(`${notif.order_number} - La Ruta 11`, {
                  body: notif.message,
                  icon: '/icon.ico',
                  badge: '/icon.ico',
                  tag: `order-${notif.order_id}`,
                  requireInteraction: false,
                  silent: false
                });
              }
            });
            
            setTimeout(() => {
              setShowFloating(false);
            }, 5000);
          }
          
          setNotifications(freshNotifications);
        }
      } catch (error) {
        console.error('Error checking notifications:', error);
      }
    };

    const interval = setInterval(checkNotifications, 10000);
    checkNotifications();

    return () => clearInterval(interval);
  }, [userId]);
  
  // Solicitar permisos al montar el componente
  useEffect(() => {
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission();
    }
  }, []);

  const getStatusIcon = (status) => {
    switch (status) {
      case 'sent_to_kitchen': return '👨🍳';
      case 'preparing': return '🔥';
      case 'ready': return '✅';
      case 'delivered': return '🎉';
      default: return '📱';
    }
  };

  // Siempre renderizar pero ocultar con CSS cuando no hay notificaciones
  if (newNotifications.length === 0 && !showFloating) return null;

  return (
    <div className={`fixed top-20 right-4 z-50 space-y-2 transition-all duration-300 ${
      showFloating ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-full pointer-events-none'
    }`}>
      {newNotifications.map((notification, index) => (
        <div
          key={notification.id || index}
          className="bg-white border-l-4 border-orange-500 rounded-lg shadow-lg p-4 max-w-sm animate-slide-in"
          style={{
            animation: `slideIn 0.3s ease-out ${index * 0.1}s both`
          }}
        >
          <div className="flex items-start">
            <div className="text-2xl mr-3">
              {getStatusIcon(notification.status)}
            </div>
            <div className="flex-1">
              <div className="font-semibold text-gray-800 text-sm">
                {notification.order_number}
              </div>
              <div className="text-gray-600 text-sm mt-1">
                {notification.message}
              </div>
              <div className="text-xs text-gray-400 mt-2">
                {new Date(notification.created_at).toLocaleTimeString('es-CL', {
                  hour: '2-digit',
                  minute: '2-digit',
                  timeZone: 'America/Santiago'
                })}
              </div>
            </div>
            <button
              onClick={() => setShowFloating(false)}
              className="text-gray-400 hover:text-gray-600 ml-2"
            >
              ×
            </button>
          </div>
        </div>
      ))}
      
      <style jsx>{`
        @keyframes slideIn {
          from {
            transform: translateX(100%);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
        .animate-slide-in {
          animation: slideIn 0.3s ease-out both;
        }
      `}</style>
    </div>
  );
}