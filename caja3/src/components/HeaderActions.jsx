import React, { useState, useEffect, useCallback } from 'react';
import { BellIcon } from './icons/BellIcon.jsx';
import { UserIcon } from './icons/UserIcon.jsx';
import { SearchIcon } from './icons/SearchIcon.jsx';
import NotificationsModal from './modals/NotificationsModal.jsx';

const HeaderActions = ({ initialUsername = 'Admin' }) => {
    const [unreadCount, setUnreadCount] = useState(0);
    const [notifications, setNotifications] = useState([]);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [username, setUsername] = useState(initialUsername);

    const fetchNotifications = useCallback(async () => {
        try {
            // Usar el mismo endpoint que OrderNotifications.jsx
            // En caja3 admin, usualmente el user_id es 1 o viene de la sesión
            const response = await fetch(`/api/get_order_notifications.php?user_id=1&t=${Date.now()}`);
            const data = await response.json();
            if (data.success) {
                setNotifications(data.notifications || []);
                setUnreadCount(data.unread_count || 0);
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    }, []);

    useEffect(() => {
        fetchNotifications();
        const interval = setInterval(fetchNotifications, 10000);
        return () => clearInterval(interval);
    }, [fetchNotifications]);

    const handleMarkAllRead = async () => {
        try {
            const response = await fetch('/api/mark_notifications_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: 1 })
            });
            const data = await response.json();
            if (data.success) {
                setUnreadCount(0);
                setNotifications(prev => prev.map(n => ({ ...n, is_read: 1 })));
            }
        } catch (error) {
            console.error('Error marking notifications as read:', error);
        }
    };

    const handleLogout = () => {
        if (window.confirm('¿Estás seguro que deseas salir?')) {
            window.location.href = '/api/logout.php';
        }
    };

    return (
        <div className="flex items-center gap-4">
            {/* Botones de navegación de mes (Contextuales) */}
            <div id="header-actions" className="flex items-center gap-2">
                <div id="month-year-display" style={{ display: 'none' }} className="text-sm font-semibold text-gray-900 px-3 py-1.5 bg-gray-100 rounded-lg"></div>
                <button
                    id="prev-month-btn"
                    style={{ display: 'none' }}
                    className="px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors border border-gray-200"
                    onClick={() => window.loadPreviousMonth?.()}
                >
                    ⏪ Mes Anterior
                </button>
                <button
                    id="current-month-btn"
                    style={{ display: 'none' }}
                    className="px-3 py-1.5 text-xs font-medium bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-colors shadow-sm"
                    onClick={() => window.loadCurrentMonth?.()}
                >
                    📅 Mes Actual
                </button>
            </div>

            {/* Búsqueda */}
            <button
                onClick={() => {
                    const searchInput = document.querySelector('.search input');
                    if (searchInput) {
                        searchInput.focus();
                        // Scroll to search if needed
                        searchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }}
                className="text-gray-500 hover:text-orange-500 transition-colors p-2 rounded-full hover:bg-orange-50"
                title="Buscar"
            >
                <SearchIcon size={20} />
            </button>

            {/* Campana de Notificaciones */}
            <div className="relative">
                <button
                    onClick={() => setIsModalOpen(true)}
                    className="text-gray-500 hover:text-orange-500 transition-colors p-2 rounded-full hover:bg-orange-50 relative"
                    title="Notificaciones"
                >
                    <BellIcon size={20} />
                    {unreadCount > 0 && (
                        <span className="absolute top-1.5 right-1.5 bg-red-500 text-white text-[10px] font-bold rounded-full h-4 w-4 flex items-center justify-center border-2 border-white animate-pulse">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                </button>
            </div>

            {/* Perfil de Usuario */}
            <div className="flex items-center gap-2 pl-2 border-l border-gray-200">
                <span className="text-sm font-medium text-gray-700 hidden sm:block">{username}</span>
                <button
                    onClick={handleLogout}
                    className="text-gray-500 hover:text-red-500 transition-colors p-2 rounded-full hover:bg-red-50"
                    title="Cerrar Sesión"
                >
                    <UserIcon size={20} />
                </button>
            </div>

            {/* Modal de Notificaciones */}
            <NotificationsModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                notifications={notifications}
                onMarkAllRead={handleMarkAllRead}
            />
        </div>
    );
};

export default HeaderActions;
