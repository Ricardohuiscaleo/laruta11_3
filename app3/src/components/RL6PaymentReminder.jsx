import React, { useState, useEffect } from 'react';
import { X, CreditCard, Clock } from 'lucide-react';

const RL6PaymentReminder = ({ user }) => {
  const [showReminder, setShowReminder] = useState(false);
  const [creditInfo, setCreditInfo] = useState(null);
  const [timeRemaining, setTimeRemaining] = useState('');

  useEffect(() => {
    console.log('🎖️ [RL6Reminder] Verificando condiciones...', {
      hasUser: !!user,
      es_militar_rl6: user?.es_militar_rl6,
      credito_aprobado: user?.credito_aprobado
    });
    
    // Solo mostrar para militares RL6
    if (!user || (user.es_militar_rl6 != 1)) {
      console.log('🎖️ [RL6Reminder] Usuario no es militar RL6');
      return;
    }

    // Verificar si es día 18, 19, 20 o 21
    const today = new Date();
    const dayOfMonth = today.getDate();
    console.log('🎖️ [RL6Reminder] Día del mes:', dayOfMonth);
    
    if (![18, 19, 20, 21].includes(dayOfMonth)) {
      console.log('🎖️ [RL6Reminder] No es día de recordatorio (18-21)');
      return;
    }

    // Verificar si ya se mostró hoy
    const lastShown = localStorage.getItem('rl6_reminder_shown');
    const todayStr = today.toDateString();
    console.log('🎖️ [RL6Reminder] Último mostrado:', lastShown, 'Hoy:', todayStr);
    if (lastShown === todayStr) {
      console.log('🎖️ [RL6Reminder] Ya se mostró hoy');
      return;
    }

    console.log('🎖️ [RL6Reminder] ✅ Todas las condiciones cumplidas, obteniendo crédito...');
    // Obtener info de crédito
    fetchCreditInfo();
  }, [user]);

  const fetchCreditInfo = async () => {
    try {
      console.log('🎖️ [RL6Reminder] Obteniendo info de crédito...');
      const response = await fetch('/api/rl6/get_credit.php', {
        credentials: 'include'
      });
      const data = await response.json();
      console.log('🎖️ [RL6Reminder] Respuesta crédito:', data);
      
      if (data.success) {
        setCreditInfo(data);
        calculateTimeRemaining();
        console.log('🎖️ [RL6Reminder] ✅ Mostrando popup en 2 segundos...');
        // Mostrar después de 2 segundos para que no moleste al cargar
        setTimeout(() => setShowReminder(true), 2000);
      }
    } catch (error) {
      console.error('🎖️ [RL6Reminder] ❌ Error fetching credit info:', error);
    }
  };

  const calculateTimeRemaining = () => {
    const now = new Date();
    const deadline = new Date(now.getFullYear(), now.getMonth(), 21, 23, 59, 59);
    
    const diff = deadline - now;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    
    if (days > 0) {
      setTimeRemaining(`${days} día${days > 1 ? 's' : ''} y ${hours} hora${hours > 1 ? 's' : ''}`);
    } else {
      setTimeRemaining(`${hours} hora${hours > 1 ? 's' : ''}`);
    }
  };

  const handleClose = () => {
    setShowReminder(false);
    localStorage.setItem('rl6_reminder_shown', new Date().toDateString());
  };

  const handlePayNow = () => {
    window.location.href = '/pagar-credito';
  };

  if (!showReminder || !creditInfo) return null;

  const hasDebt = parseFloat(creditInfo.credito_usado) > 0;
  const availableCredit = parseFloat(creditInfo.limite_credito) - parseFloat(creditInfo.credito_usado);

  return (
    <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black bg-opacity-50 animate-fade-in">
      <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden animate-slide-up">
        {/* Header */}
        <div className="bg-gradient-to-r from-blue-600 to-blue-800 p-6 relative">
          <button
            onClick={handleClose}
            className="absolute top-4 right-4 text-white hover:bg-white hover:bg-opacity-20 rounded-full p-1 transition-all"
          >
            <X size={24} />
          </button>
          
          <div className="flex items-center gap-3 mb-2">
            <div className="bg-white bg-opacity-20 p-3 rounded-full">
              <CreditCard size={28} className="text-white" />
            </div>
            <div>
              <h2 className="text-2xl font-bold text-white">Crédito RL6</h2>
              <p className="text-blue-100 text-sm">Recordatorio de Pago</p>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="p-6">
          {hasDebt ? (
            <>
              <div className="bg-red-50 border-l-4 border-red-500 p-4 mb-4 rounded">
                <p className="text-red-800 font-semibold mb-2">
                  ⚠️ Te recordamos que debes pagar tu crédito
                </p>
                <div className="flex items-center gap-2 text-red-700">
                  <Clock size={18} />
                  <span className="text-sm">Vence en: <strong>{timeRemaining}</strong></span>
                </div>
              </div>

              <div className="bg-gray-50 rounded-lg p-4 mb-4">
                <div className="flex justify-between items-center mb-2">
                  <span className="text-gray-600">Saldo pendiente:</span>
                  <span className="text-2xl font-bold text-red-600">
                    ${parseFloat(creditInfo.credito_usado).toLocaleString('es-CL')}
                  </span>
                </div>
              </div>

              <button
                onClick={handlePayNow}
                className="w-full bg-gradient-to-r from-blue-600 to-blue-800 text-white py-4 rounded-xl font-bold text-lg hover:from-blue-700 hover:to-blue-900 transition-all shadow-lg hover:shadow-xl active:scale-95"
              >
                💳 Pagar Ahora
              </button>
            </>
          ) : (
            <>
              <div className="bg-green-50 border-l-4 border-green-500 p-4 mb-4 rounded">
                <p className="text-green-800 font-semibold mb-2">
                  ✅ ¡Hola! Tienes crédito disponible
                </p>
                <div className="flex items-center gap-2 text-green-700">
                  <Clock size={18} />
                  <span className="text-sm">Compra hoy, paga antes de: <strong>{timeRemaining}</strong></span>
                </div>
              </div>

              <div className="bg-gray-50 rounded-lg p-4 mb-4">
                <div className="flex justify-between items-center mb-2">
                  <span className="text-gray-600">Cupo disponible:</span>
                  <span className="text-2xl font-bold text-green-600">
                    ${availableCredit.toLocaleString('es-CL')}
                  </span>
                </div>
              </div>

              <button
                onClick={handlePayNow}
                className="w-full bg-gradient-to-r from-green-600 to-green-800 text-white py-4 rounded-xl font-bold text-lg hover:from-green-700 hover:to-green-900 transition-all shadow-lg hover:shadow-xl active:scale-95"
              >
                📊 Ver Historial
              </button>
            </>
          )}

          <button
            onClick={handleClose}
            className="w-full mt-3 text-gray-500 hover:text-gray-700 py-2 text-sm font-medium"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  );
};

export default RL6PaymentReminder;
