import React, { useState, useEffect } from 'react';
import { X, Calendar, Clock } from 'lucide-react';
import { getNextAvailableSlots } from '../utils/businessHours.js';

const ScheduleOrderModal = ({ isOpen, onClose, onSchedule }) => {
  const [selectedSlot, setSelectedSlot] = useState('');
  const [slots, setSlots] = useState([]);

  useEffect(() => {
    if (isOpen) {
      const availableSlots = getNextAvailableSlots();
      setSlots(availableSlots);
      setSelectedSlot('');
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleSchedule = () => {
    if (!selectedSlot) {
      alert('Por favor selecciona un horario');
      return;
    }
    
    const slot = slots.find(s => s.value === selectedSlot);
    onSchedule(slot);
    onClose();
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-[60] flex justify-center items-center animate-fade-in" onClick={onClose}>
      <div className="bg-white w-full max-w-md mx-4 rounded-2xl flex flex-col animate-slide-up max-h-[80vh]" onClick={(e) => e.stopPropagation()}>
        <div className="border-b flex justify-between items-center p-4">
          <div className="flex items-center gap-2">
            <Calendar className="text-orange-500" size={24} />
            <h2 className="text-xl font-bold text-gray-800">Programar Pedido</h2>
          </div>
          <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600">
            <X size={24} />
          </button>
        </div>

        <div className="flex-grow overflow-y-auto p-4">
          <div className="bg-orange-50 border border-orange-200 rounded-lg p-3 mb-4">
            <p className="text-sm text-orange-800">
              <Clock className="inline mr-1" size={16} />
              Estamos fuera de horario. Programa tu pedido para recibirlo en el horario que prefieras.
            </p>
          </div>

          <label className="block text-sm font-medium text-gray-700 mb-2">
            Selecciona fecha y hora de entrega *
          </label>
          
          <select
            value={selectedSlot}
            onChange={(e) => setSelectedSlot(e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 mb-4"
          >
            <option value="">Seleccionar horario...</option>
            {slots.map(slot => (
              <option key={slot.value} value={slot.value}>
                {slot.display}
              </option>
            ))}
          </select>

          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
            <p className="text-xs text-blue-800">
              ℹ️ Tu pedido será preparado para el horario seleccionado. Recibirás una confirmación por WhatsApp.
            </p>
          </div>
        </div>

        <div className="border-t p-4 space-y-2">
          <button
            onClick={handleSchedule}
            disabled={!selectedSlot}
            className="w-full bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white font-bold py-3 px-4 rounded-lg transition-colors"
          >
            Confirmar Programación
          </button>
          <button
            onClick={onClose}
            className="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>
  );
};

export default ScheduleOrderModal;
