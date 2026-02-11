import React from 'react';

export default function SwipeToggle({ isActive, onChange, disabled = false, label = '' }) {
  return (
    <div className="flex flex-col items-center gap-4">
      {label && <p className="text-sm text-gray-600">{label}</p>}
      
      <div className="flex items-center justify-center gap-4 sm:gap-6">
        <button
          onClick={() => !disabled && !isActive && onChange(false)}
          className={`text-lg font-semibold transition-colors cursor-pointer ${
            !isActive ? 'text-red-600' : 'text-gray-400'
          }`}
        >
          Cerrado
        </button>

        <button
          onClick={() => !disabled && onChange(!isActive)}
          disabled={disabled}
          className={`relative inline-flex h-16 w-32 items-center rounded-full transition-colors duration-300 focus:outline-none focus:ring-4 focus:ring-offset-2 ${
            isActive 
              ? 'bg-green-500 focus:ring-green-300' 
              : 'bg-gray-300 focus:ring-gray-200'
          } ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:shadow-lg'}`}
        >
          <span
            className={`inline-block h-12 w-12 rounded-full bg-white shadow-lg transition-all duration-300 ${
              isActive ? 'translate-x-16' : 'translate-x-2'
            }`}
          />
        </button>

        <button
          onClick={() => !disabled && isActive && onChange(true)}
          className={`text-lg font-semibold transition-colors cursor-pointer ${
            isActive ? 'text-green-600' : 'text-gray-400'
          }`}
        >
          Abierto
        </button>
      </div>

      <div className={`mt-4 p-4 rounded-lg text-center font-semibold text-lg ${
        isActive
          ? 'bg-green-100 text-green-800'
          : 'bg-red-100 text-red-800'
      }`}>
        {!isActive 
          ? '❌ Local CERRADO - No se reciben pedidos'
          : '✅ Local ABIERTO - Recibiendo pedidos'
        }
      </div>
    </div>
  );
}
