import { useState, useEffect, useRef } from 'react';

export default function OrderNotifications({ userId, audioEnabled, onNotificationsUpdate }) {
  const [notifications, setNotifications] = useState([]);
  const [showFloating, setShowFloating] = useState(false);
  const [newNotifications, setNewNotifications] = useState([]);
  const previousNotificationCountRef = useRef(0);

  const playTestSound = async () => {
    try {
      console.log('🔊 Intentando reproducir sonido pedido...');
      console.log('Audio habilitado:', audioEnabled);
      
      const audio = new Audio('/pedido.mp3');
      audio.volume = 1.0;
      
      const playPromise = audio.play();
      if (playPromise !== undefined) {
        playPromise.then(() => {
          console.log('✅ Sonido pedido.mp3 reproducido exitosamente');
        }).catch(error => {
          console.error('❌ Error reproduciendo sonido:', error.name, error.message);
          if (!audioEnabled) {
            console.log('👆 Haz click para habilitar audio');
          }
        });
      }
    } catch (error) {
      console.error('Error en playTestSound:', error);
    }
  };



  useEffect(() => {
    // Solicitar permisos de notificación al iniciar
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission();
    }
    
    if (!userId) return;

    const checkNotifications = async () => {
      try {
        const response = await fetch(`/api/get_order_notifications.php?user_id=${userId}&t=${Date.now()}`, {
          headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
          }
        });
        const data = await response.json();
        
        if (data.success) {
          const freshNotifications = data.notifications || [];
          const unreadCount = data.unread_count || 0;
          
          console.log('Debug notificaciones:', freshNotifications.length);
          console.log('Previous count:', previousNotificationCountRef.current, 'New count:', freshNotifications.length);
          
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
          
          // Reproducir sonido si hay MÁS notificaciones que antes (y no es la primera carga)
          if (freshNotifications.length > previousNotificationCountRef.current && previousNotificationCountRef.current > 0) {
            console.log('🔔 NUEVA NOTIFICACIÓN! Reproduciendo sonido...');
            playTestSound();
            
            // Mostrar notificaciones flotantes
            const reallyNew = freshNotifications.slice(0, 3);
            setNewNotifications(reallyNew);
            setShowFloating(true);
            
            // Mostrar notificación del sistema (PWA)
            reallyNew.forEach(notif => {
              if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(`${notif.order_number} - La Ruta 11`, {
                  body: notif.message,
                  icon: '/icon.png',
                  badge: '/icon.png',
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
          
          // Actualizar el contador DESPUÉS de verificar
          if (previousNotificationCountRef.current === 0 && freshNotifications.length > 0) {
            console.log('📊 Inicializando contador en:', freshNotifications.length);
          }
          previousNotificationCountRef.current = freshNotifications.length;
        }
      } catch (error) {
        console.error('Error checking notifications:', error);
      }
    };

    const interval = setInterval(checkNotifications, 10000);
    checkNotifications();

    return () => clearInterval(interval);
  }, [userId, audioEnabled]);
  
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



  if (!showFloating || newNotifications.length === 0) return null;

  return (
    <div className="fixed top-4 right-4 z-50 space-y-2">
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