import React, { useState, useEffect } from 'react';
import { AlertTriangle, CheckCircle, Info, X, Bell } from 'lucide-react';

const RobotAlerts = () => {
  const [alerts, setAlerts] = useState([]);
  const [showAlerts, setShowAlerts] = useState(false);
  const [unreadCount, setUnreadCount] = useState(0);

  useEffect(() => {
    loadAlerts();
    // Actualizar cada 30 segundos
    const interval = setInterval(loadAlerts, 30000);
    return () => clearInterval(interval);
  }, []);

  const loadAlerts = async () => {
    try {
      const response = await fetch('/api/robot_notifications.php?action=get_alerts');
      const data = await response.json();
      if (data.success) {
        setAlerts(data.alerts);
        setUnreadCount(data.total_unread);
      }
    } catch (error) {
      console.error('Error loading robot alerts:', error);
    }
  };

  const markAsRead = async (alertId) => {
    try {
      const formData = new FormData();
      formData.append('alert_id', alertId);
      await fetch('/api/robot_notifications.php?action=mark_read', {
        method: 'POST',
        body: formData
      });
      loadAlerts();
    } catch (error) {
      console.error('Error marking alert as read:', error);
    }
  };

  const getAlertIcon = (type, priority) => {
    if (priority === 'high') return <AlertTriangle className="text-red-500" size={20} />;
    if (type === 'robot_failure') return <AlertTriangle className="text-orange-500" size={20} />;
    return <Info className="text-blue-500" size={20} />;
  };

  const getAlertColor = (priority) => {
    switch (priority) {
      case 'high': return 'border-red-500 bg-red-50';
      case 'medium': return 'border-orange-500 bg-orange-50';
      default: return 'border-blue-500 bg-blue-50';
    }
  };

  return (
    <div className="relative">
      {/* Botón de notificaciones */}
      <button
        onClick={() => setShowAlerts(!showAlerts)}
        className="relative p-2 text-gray-600 hover:text-orange-500 transition-colors"
        title="Robot Alerts"
      >
        <Bell size={20} />
        {unreadCount > 0 && (
          <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
            {unreadCount}
          </span>
        )}
      </button>

      {/* Panel de alertas */}
      {showAlerts && (
        <div className="absolute right-0 top-full mt-2 w-80 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-96 overflow-y-auto">
          <div className="p-3 border-b border-gray-200 flex justify-between items-center">
            <h3 className="font-semibold text-gray-800">🤖 Robot Alerts</h3>
            <button
              onClick={() => setShowAlerts(false)}
              className="text-gray-400 hover:text-gray-600"
            >
              <X size={16} />
            </button>
          </div>

          <div className="max-h-80 overflow-y-auto">
            {alerts.length === 0 ? (
              <div className="p-4 text-center text-gray-500">
                <CheckCircle className="mx-auto mb-2 text-green-500" size={24} />
                <p>No hay alertas</p>
                <p className="text-sm">El robot está funcionando correctamente</p>
              </div>
            ) : (
              alerts.map((alert, index) => (
                <div
                  key={index}
                  className={`p-3 border-l-4 ${getAlertColor(alert.priority)} border-b border-gray-100 last:border-b-0`}
                >
                  <div className="flex items-start gap-3">
                    {getAlertIcon(alert.type, alert.priority)}
                    <div className="flex-1">
                      <h4 className="font-medium text-gray-800 text-sm">
                        {alert.title}
                      </h4>
                      <p className="text-gray-600 text-xs mt-1">
                        {alert.message}
                      </p>
                      <div className="flex justify-between items-center mt-2">
                        <span className="text-xs text-gray-500">
                          {new Date(alert.created_at).toLocaleString()}
                        </span>
                        {!alert.read_status && alert.id && (
                          <button
                            onClick={() => markAsRead(alert.id)}
                            className="text-xs text-blue-500 hover:text-blue-700"
                          >
                            Marcar leído
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>

          <div className="p-3 border-t border-gray-200 bg-gray-50">
            <div className="flex justify-between items-center text-xs text-gray-600">
              <span>🟢 Robot Status: Activo</span>
              <span>Último test: {new Date().toLocaleTimeString()}</span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default RobotAlerts;