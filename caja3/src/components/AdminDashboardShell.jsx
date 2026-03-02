import React, { useState, useEffect } from 'react';
import { AdminHeader } from './layout/AdminHeader';
import { AdminSidebar } from './layout/AdminSidebar';

export default function AdminDashboardShell({ children, cajaUser: initialUser }) {
    const [activeView, setActiveView] = useState('dashboard');
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);
    const [cajaUser] = useState(initialUser);

    // Sync with index.astro views if needed or handle navigation here
    const handleViewChange = (viewId) => {
        setActiveView(viewId);
        // In index.astro we have showView(id)
        if (window.showView) {
            window.showView(viewId);
        }
    };

    const getTitle = () => {
        const titles = {
            'dashboard': 'Dashboard',
            'sales-analytics': 'Control de Ventas',
            'purchase-plan': 'Plan de Compras',
            'payments': 'Pagos',
            'products': 'Gestión de Productos',
            'ingredients': 'Ingredientes',
            'users': 'Usuarios',
            'militares-rl6': 'Militares RL6',
            'test': 'Prueba de APIs',
            'technical-report': 'Informe Técnico',
            'robots': 'Gestión de Robots',
            'calidad': 'Control de Calidad',
            'concurso': 'Estadísticas Concurso',
            'concurso-admin': 'Administración Concurso',
            'combos': 'Gestión de Combos',
            'reportes': 'Reportes Detallados'
        };
        return titles[activeView] || 'La Ruta 11';
    };

    return (
        <div className="flex min-h-screen">
            <AdminSidebar
                activeView={activeView}
                onViewChange={handleViewChange}
                isOpen={isSidebarOpen}
                onClose={() => setIsSidebarOpen(false)}
            />

            <div className="flex flex-col flex-1 min-w-0">
                <AdminHeader
                    title={getTitle()}
                    onMenuClick={() => setIsSidebarOpen(true)}
                    cajaUser={cajaUser}
                />

                <main className="flex-1 overflow-y-auto p-4 lg:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
