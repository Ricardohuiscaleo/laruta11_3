import React, { useState, useEffect } from 'react';
import { X } from 'lucide-react';

const SecurityModal = ({ isOpen, onClose, type, onConfirm }) => {
    const [mathAnswer, setMathAnswer] = useState('');
    const [mathProblem, setMathProblem] = useState({ a: 0, b: 0, answer: 0 });
    const [error, setError] = useState('');
    
    useEffect(() => {
        if (isOpen) {
            // Generar suma simple (unidad + decena ≤ 9)
            const a = Math.floor(Math.random() * 5) + 1; // 1-5
            const b = Math.floor(Math.random() * (10 - a)) + 1; // 1-(9-a)
            const answer = a + b;
            setMathProblem({ a, b, answer });
            setMathAnswer('');
            setError('');
        }
    }, [isOpen]);
    
    const handleSubmit = (e) => {
        if (e && e.preventDefault) {
            e.preventDefault();
        }
        if (parseInt(mathAnswer) === mathProblem.answer) {
            onConfirm();
            onClose();
        } else {
            setError('Respuesta incorrecta. Inténtalo de nuevo.');
            setMathAnswer('');
        }
    };
    
    if (!isOpen) return null;
    
    const config = {
        logout: {
            title: 'Cerrar Sesión',
            icon: '🚪',
            description: 'Se cerrará tu sesión actual. Perderás el carrito y tendrás que iniciar sesión nuevamente.',
            action: 'Cerrar Sesión',
            color: 'bg-orange-500 hover:bg-orange-600'
        },
        delete: {
            title: 'Eliminar Cuenta',
            icon: '⚠️',
            description: 'Se eliminará permanentemente tu cuenta, historial de pedidos, favoritos y toda tu información. Esta acción NO se puede deshacer.',
            action: 'Eliminar Cuenta',
            color: 'bg-red-500 hover:bg-red-600'
        }
    };
    
    const currentConfig = config[type];
    
    return (
        <div className="fixed inset-0 bg-black bg-opacity-70 z-[70] flex justify-center items-center animate-fade-in" onClick={onClose}>
            <div className="bg-white w-full max-w-md mx-4 rounded-2xl flex flex-col animate-slide-up" onClick={(e) => e.stopPropagation()}>
                <div className="p-6 text-center">
                    <div className="text-4xl mb-4">{currentConfig.icon}</div>
                    <h2 className="text-xl font-bold text-gray-800 mb-3">{currentConfig.title}</h2>
                    <p className="text-sm text-gray-600 mb-6 leading-relaxed">{currentConfig.description}</p>
                    
                    <div className="bg-gray-50 p-4 rounded-lg mb-4">
                        <p className="text-sm font-medium text-gray-700 mb-3">Para continuar, resuelve esta suma:</p>
                        <div className="text-2xl font-bold text-gray-800 mb-3">
                            {mathProblem.a} + {mathProblem.b} = ?
                        </div>
                        <form onSubmit={handleSubmit}>
                            <input
                                type="number"
                                value={mathAnswer}
                                onChange={(e) => setMathAnswer(e.target.value)}
                                className="w-20 p-2 border rounded-lg text-center text-lg font-bold"
                                placeholder="?"
                                min="1"
                                max="9"
                                required
                            />
                            {error && <p className="text-red-500 text-xs mt-2">{error}</p>}
                        </form>
                    </div>
                    
                    <div className="flex gap-3">
                        <button 
                            onClick={onClose}
                            className="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button 
                            onClick={(e) => {
                                e.preventDefault();
                                if (parseInt(mathAnswer) === mathProblem.answer) {
                                    onConfirm();
                                    onClose();
                                } else {
                                    setError('Respuesta incorrecta. Inténtalo de nuevo.');
                                    setMathAnswer('');
                                }
                            }}
                            className={`flex-1 px-4 py-2 text-white rounded-lg transition-colors ${currentConfig.color}`}
                            disabled={!mathAnswer}
                        >
                            {currentConfig.action}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default SecurityModal;