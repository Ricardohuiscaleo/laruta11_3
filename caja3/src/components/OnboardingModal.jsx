import React, { useState } from 'react';
import { MapPin, Bell, Smartphone, CheckCircle, ArrowRight, Camera, Mic, HardDrive } from 'lucide-react';

const OnboardingModal = ({ isOpen, onComplete }) => {
  const [currentStep, setCurrentStep] = useState(0);
  const [permissions, setPermissions] = useState({
    location: 'pending',
    notifications: 'pending',
    camera: 'pending',
    storage: 'pending'
  });

  const steps = [
    {
      title: '¡Bienvenido a La Ruta 11!',
      subtitle: 'Tu menú digital favorito',
      icon: <Smartphone className="w-16 h-16 text-orange-500" />,
      description: 'Descubre nuestros deliciosos completos, hamburguesas y más. Para brindarte la mejor experiencia, necesitamos configurar algunos permisos.',
      action: 'Comenzar'
    },
    {
      title: 'Encuentra Food Trucks Cercanos',
      subtitle: 'Ubicación',
      icon: <MapPin className="w-16 h-16 text-blue-500" />,
      description: 'Permítenos acceder a tu ubicación para mostrarte los food trucks más cercanos y calcular tiempos de entrega.',
      action: 'Permitir Ubicación',
      permission: 'location'
    },
    {
      title: 'Recibe Ofertas Especiales',
      subtitle: 'Notificaciones',
      icon: <Bell className="w-16 h-16 text-green-500" />,
      description: 'Te enviaremos notificaciones sobre promociones, nuevos productos y cuando tu pedido esté listo.',
      action: 'Permitir Notificaciones',
      permission: 'notifications'
    },
    {
      title: 'Comparte tus Fotos',
      subtitle: 'Cámara',
      icon: <Camera className="w-16 h-16 text-purple-500" />,
      description: 'Toma fotos de tus platos favoritos para compartir en redes sociales y ayudar a otros usuarios a decidir.',
      action: 'Permitir Cámara',
      permission: 'camera'
    },
    {
      title: 'Almacenamiento Offline',
      subtitle: 'Datos Locales',
      icon: <HardDrive className="w-16 h-16 text-indigo-500" />,
      description: 'Guarda tu menú favorito y pedidos anteriores para acceso rápido, incluso sin conexión a internet.',
      action: 'Permitir Almacenamiento',
      permission: 'storage'
    },
    {
      title: '¡Todo Listo!',
      subtitle: 'Configuración completa',
      icon: <CheckCircle className="w-16 h-16 text-green-500" />,
      description: 'Ya puedes disfrutar de toda la experiencia de La Ruta 11. ¡Comienza a explorar nuestro menú!',
      action: 'Empezar a Ordenar'
    }
  ];

  const requestLocationPermission = async () => {
    try {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          () => {
            setPermissions(prev => ({ ...prev, location: 'granted' }));
            localStorage.setItem('location_permission', 'granted');
          },
          () => {
            setPermissions(prev => ({ ...prev, location: 'denied' }));
            localStorage.setItem('location_permission', 'denied');
          }
        );
      } else {
        setPermissions(prev => ({ ...prev, location: 'denied' }));
        localStorage.setItem('location_permission', 'denied');
      }
    } catch (error) {
      setPermissions(prev => ({ ...prev, location: 'denied' }));
      localStorage.setItem('location_permission', 'denied');
    }
  };

  const requestNotificationPermission = async () => {
    try {
      if ('Notification' in window) {
        const permission = await Notification.requestPermission();
        setPermissions(prev => ({ ...prev, notifications: permission }));
        localStorage.setItem('notification_permission', permission);
      } else {
        setPermissions(prev => ({ ...prev, notifications: 'denied' }));
        localStorage.setItem('notification_permission', 'denied');
      }
    } catch (error) {
      setPermissions(prev => ({ ...prev, notifications: 'denied' }));
      localStorage.setItem('notification_permission', 'denied');
    }
  };

  const requestCameraPermission = async () => {
    try {
      if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        stream.getTracks().forEach(track => track.stop()); // Detener inmediatamente
        setPermissions(prev => ({ ...prev, camera: 'granted' }));
        localStorage.setItem('camera_permission', 'granted');
      } else {
        setPermissions(prev => ({ ...prev, camera: 'denied' }));
        localStorage.setItem('camera_permission', 'denied');
      }
    } catch (error) {
      setPermissions(prev => ({ ...prev, camera: 'denied' }));
      localStorage.setItem('camera_permission', 'denied');
    }
  };

  const requestStoragePermission = async () => {
    try {
      if ('storage' in navigator && 'persist' in navigator.storage) {
        const granted = await navigator.storage.persist();
        setPermissions(prev => ({ ...prev, storage: granted ? 'granted' : 'denied' }));
        localStorage.setItem('storage_permission', granted ? 'granted' : 'denied');
      } else {
        setPermissions(prev => ({ ...prev, storage: 'granted' })); // Asumir concedido si no es necesario
        localStorage.setItem('storage_permission', 'granted');
      }
    } catch (error) {
      setPermissions(prev => ({ ...prev, storage: 'denied' }));
      localStorage.setItem('storage_permission', 'denied');
    }
  };

  const handleNext = async () => {
    const currentStepData = steps[currentStep];
    
    if (currentStepData.permission === 'location') {
      await requestLocationPermission();
    } else if (currentStepData.permission === 'notifications') {
      await requestNotificationPermission();
    } else if (currentStepData.permission === 'camera') {
      await requestCameraPermission();
    } else if (currentStepData.permission === 'storage') {
      await requestStoragePermission();
    }

    if (currentStep < steps.length - 1) {
      setCurrentStep(currentStep + 1);
    } else {
      localStorage.setItem('onboarding_completed', 'true');
      onComplete();
    }
  };

  const handleSkip = () => {
    const currentStepData = steps[currentStep];
    
    if (currentStepData.permission === 'location') {
      setPermissions(prev => ({ ...prev, location: 'denied' }));
      localStorage.setItem('location_permission', 'denied');
    } else if (currentStepData.permission === 'notifications') {
      setPermissions(prev => ({ ...prev, notifications: 'denied' }));
      localStorage.setItem('notification_permission', 'denied');
    } else if (currentStepData.permission === 'camera') {
      setPermissions(prev => ({ ...prev, camera: 'denied' }));
      localStorage.setItem('camera_permission', 'denied');
    } else if (currentStepData.permission === 'storage') {
      setPermissions(prev => ({ ...prev, storage: 'denied' }));
      localStorage.setItem('storage_permission', 'denied');
    }

    if (currentStep < steps.length - 1) {
      setCurrentStep(currentStep + 1);
    } else {
      localStorage.setItem('onboarding_completed', 'true');
      onComplete();
    }
  };

  if (!isOpen) return null;

  const currentStepData = steps[currentStep];

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl max-w-md w-full p-6 text-center animate-fade-in min-h-[500px] flex flex-col">
        {/* Progress Bar */}
        <div className="flex justify-center mb-6">
          {steps.map((_, index) => (
            <div
              key={index}
              className={`w-2 h-2 rounded-full mx-1 transition-colors ${
                index <= currentStep ? 'bg-orange-500' : 'bg-gray-300'
              }`}
            />
          ))}
        </div>

        {/* Icon */}
        <div className="flex justify-center mb-4">
          {currentStepData.icon}
        </div>

        {/* Content */}
        <h2 className="text-2xl font-bold text-gray-800 mb-2">
          {currentStepData.title}
        </h2>
        <p className="text-orange-500 font-semibold mb-4">
          {currentStepData.subtitle}
        </p>
        <p className="text-gray-600 mb-8 leading-relaxed">
          {currentStepData.description}
        </p>

        {/* Permission Status */}
        {currentStepData.permission && (
          <div className="mb-6">
            {permissions[currentStepData.permission] === 'granted' && (
              <div className="flex items-center justify-center text-green-600">
                <CheckCircle className="w-5 h-5 mr-2" />
                <span>Permiso concedido</span>
              </div>
            )}
            {permissions[currentStepData.permission] === 'denied' && (
              <div className="flex items-center justify-center text-red-600">
                <span>Permiso denegado (puedes cambiarlo después)</span>
              </div>
            )}
          </div>
        )}

        {/* Actions */}
        <div className="mt-auto">
          <button
            onClick={handleNext}
            className="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-6 rounded-xl transition-colors flex items-center justify-center h-12"
          >
            {currentStepData.action}
            {currentStep < steps.length - 1 && <ArrowRight className="w-5 h-5 ml-2" />}
          </button>
        </div>

        {/* Step indicator and Skip button */}
        <div className="flex justify-between items-center mt-4">
          {currentStepData.permission && (
            <button
              onClick={handleSkip}
              className="text-gray-400 hover:text-gray-600 font-medium text-xs transition-colors"
            >
              Saltar por ahora
            </button>
          )}
          <p className="text-xs text-gray-400">
            Paso {currentStep + 1} de {steps.length}
          </p>
        </div>
      </div>
    </div>
  );
};

export default OnboardingModal;