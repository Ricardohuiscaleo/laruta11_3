import React from 'react';

const SaveChangesModal = ({ isOpen, onClose, onSave, onDiscard }) => {
    if (!isOpen) return null;
    
    return (
        <div className="fixed inset-0 bg-black bg-opacity-60 z-[70] flex justify-center items-center animate-fade-in" onClick={onDiscard}>
            <div className="bg-white w-full max-w-md mx-4 rounded-2xl flex flex-col animate-slide-up" onClick={(e) => e.stopPropagation()}>
                <div className="p-6 text-center">
                    <div className="text-4xl mb-4">💾</div>
                    <h2 className="text-xl font-bold text-gray-800 mb-3">¿Guardar cambios?</h2>
                    <p className="text-sm text-gray-600 mb-6">Actualizaste tu perfil. ¿Deseas guardar los cambios realizados?</p>
                    
                    <div className="flex gap-3">
                        <button 
                            onClick={onDiscard}
                            className="bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors font-medium"
                            style={{padding: 'clamp(8px, 2vw, 12px)', fontSize: 'clamp(12px, 3vw, 14px)', flex: '1'}}
                        >
                            NO
                        </button>
                        <button 
                            onClick={() => {
                                onSave();
                                window.location.reload();
                            }}
                            className="bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-medium"
                            style={{padding: 'clamp(8px, 2vw, 12px)', fontSize: 'clamp(12px, 3vw, 14px)', flex: '4'}}
                        >
                            SÍ, GUARDAR CAMBIOS
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default SaveChangesModal;