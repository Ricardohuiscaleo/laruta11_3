import { Bell, X, ExternalLink } from 'lucide-react';
import { useState, useEffect } from 'react';

export default function ChecklistNotification({ checklist, onClose }) {
  const [shouldShow, setShouldShow] = useState(false);

  useEffect(() => {
    const checkTime = () => {
      const now = new Date();
      const hours = now.getHours();
      const minutes = now.getMinutes();
      const currentTime = hours * 60 + minutes;

      if (checklist.type === 'apertura') {
        // Mostrar desde las 17:00 (1020 min) hasta las 19:00 (1140 min)
        setShouldShow(currentTime >= 1020 && currentTime < 1140);
      } else if (checklist.type === 'cierre') {
        // Mostrar desde las 00:30 (30 min) hasta las 01:45 (105 min)
        setShouldShow(currentTime >= 30 && currentTime < 105);
      }
    };

    checkTime();
    const interval = setInterval(checkTime, 60000); // Check cada minuto
    return () => clearInterval(interval);
  }, [checklist.type]);

  if (!shouldShow) return null;

  return (
    <div className="fixed top-4 right-4 z-50 max-w-sm animate-slide-in">
      <div className="bg-white rounded-xl shadow-2xl border-2 border-orange-500 overflow-hidden">
        <div className="bg-orange-500 text-white px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Bell size={20} className="animate-bounce" />
            <span className="font-bold">Checklist Disponible</span>
          </div>
          <button onClick={onClose} className="hover:bg-orange-600 rounded p-1">
            <X size={20} />
          </button>
        </div>
        
        <div className="p-4">
          <div className="text-lg font-bold mb-2">
            {checklist.type === 'apertura' ? '☀️ Checklist Apertura' : '🌙 Checklist Cierre'}
          </div>
          <p className="text-gray-600 text-sm mb-4">
            Tienes 1 hora para completar el checklist ({checklist.scheduled_time.substring(0, 5)} - {checklist.type === 'apertura' ? '19:00' : '01:45'})
          </p>
          
          <div className="flex gap-2">
            <button
              onClick={() => window.location.href = `/checklist?tab=${checklist.type}`}
              className="flex-1 bg-orange-500 text-white py-2 rounded-lg font-semibold flex items-center justify-center gap-2 hover:bg-orange-600 transition-colors"
            >
              <ExternalLink size={16} />
              Ver Checklist
            </button>
            <button
              onClick={onClose}
              className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200 transition-colors"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
