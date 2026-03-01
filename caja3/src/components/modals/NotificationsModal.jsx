import React from 'react';
import { X } from 'lucide-react';
import { BellIcon } from '../icons/BellIcon.jsx';

const NotificationsModal = ({ isOpen, onClose, notifications, onMarkAllRead }) => {
    const getStatusIcon = (status) => {
        switch (status) {
            case 'sent_to_kitchen': return '👨🍳';
            case 'preparing': return '🔥';
            case 'ready': return '✅';
            case 'delivered': return '🎉';
            default: return '📱';
        }
    };

    if (!isOpen) return null;

    return (
        <>
            <div
                className="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity duration-300 z-[1001]"
                onClick={onClose}
            />
            <div
                className={`fixed top-0 right-0 h-full w-full max-w-sm bg-white z-[1002] shadow-2xl transform transition-transform duration-300 ease-out ${isOpen ? 'translate-x-0' : 'translate-x-full'
                    }`}
            >
                <div className="flex flex-col h-full">
                    <div className="border-b flex justify-between items-center p-4 bg-gradient-to-r from-orange-500 to-orange-600 shadow-md">
                        <h2 className="font-bold text-white flex items-center gap-2 text-lg">
                            <BellIcon size={20} className="text-white" />
                            Notificaciones
                        </h2>
                        <button
                            onClick={onClose}
                            className="p-1.5 text-white hover:bg-white/20 rounded-full transition-colors"
                        >
                            <X size={20} />
                        </button>
                    </div>

                    <div className="flex-1 overflow-y-auto p-4 bg-gray-50">
                        {notifications.length > 0 ? (
                            <div className="space-y-3">
                                {notifications.map((notif, index) => (
                                    <div
                                        key={notif.id || index}
                                        className={`border-l-4 rounded-lg bg-white shadow-sm p-3 transition-all hover:shadow-md ${notif.is_read ? 'border-gray-200' : 'border-orange-500'
                                            }`}
                                    >
                                        <div className="flex items-start gap-3">
                                            <div className="text-xl pt-1">
                                                {getStatusIcon(notif.status)}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="flex justify-between items-start mb-1">
                                                    <h4 className="font-bold text-gray-900 text-sm truncate">
                                                        {notif.order_number}
                                                    </h4>
                                                    <span className="text-gray-400 text-[10px] whitespace-nowrap ml-2">
                                                        {new Date(notif.created_at).toLocaleTimeString('es-CL', {
                                                            hour: '2-digit',
                                                            minute: '2-digit'
                                                        })}
                                                    </span>
                                                </div>
                                                <p className="text-gray-600 text-xs leading-relaxed">
                                                    {notif.message}
                                                </p>
                                                {(notif.product_details || notif.product_name) && (
                                                    <div className="mt-2 pt-2 border-t border-gray-100 flex items-center gap-1.5">
                                                        <span className="text-[10px]">📦</span>
                                                        <p className="text-gray-500 text-[10px] font-medium truncate">
                                                            {notif.product_details || notif.product_name}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="h-full flex flex-col items-center justify-center text-center p-8">
                                <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <BellIcon size={32} className="text-gray-300" />
                                </div>
                                <h3 className="font-semibold text-gray-900 mb-1">No hay notificaciones</h3>
                                <p className="text-sm text-gray-500">Te avisaremos cuando lleguen nuevos pedidos.</p>
                            </div>
                        )}
                    </div>

                    {notifications.length > 0 && (
                        <div className="p-4 bg-white border-t border-gray-100">
                            <button
                                onClick={onMarkAllRead}
                                className="w-full py-2.5 text-sm text-orange-600 hover:bg-orange-50 font-bold rounded-lg transition-colors border border-orange-100"
                            >
                                Marcar todas como leídas
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
};

export default NotificationsModal;
