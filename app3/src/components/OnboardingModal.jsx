import React, { useState } from 'react';
import { Smartphone, CheckCircle, ArrowRight } from 'lucide-react';

const OnboardingModal = ({ isOpen, onComplete }) => {
  const [currentStep, setCurrentStep] = useState(0);

  const steps = [
    {
      title: '¡Bienvenido a La Ruta 11!',
      subtitle: 'Tu menú digital favorito',
      icon: <Smartphone className="w-16 h-16 text-orange-500" />,
      description: 'Descubre nuestros deliciosos completos, hamburguesas y más. ¡Comienza a explorar nuestro menú!',
      action: 'Comenzar'
    },
    {
      title: '¡Todo Listo!',
      subtitle: 'Configuración completa',
      icon: <CheckCircle className="w-16 h-16 text-green-500" />,
      description: 'Ya puedes disfrutar de toda la experiencia de La Ruta 11. ¡Comienza a explorar nuestro menú!',
      action: 'Empezar a Ordenar'
    }
  ];

  const handleNext = () => {
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

        {/* Step indicator */}
        <div className="flex justify-center items-center mt-4">
          <p className="text-xs text-gray-400">
            Paso {currentStep + 1} de {steps.length}
          </p>
        </div>
      </div>
    </div>
  );
};

export default OnboardingModal;