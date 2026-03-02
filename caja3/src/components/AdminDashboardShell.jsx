import React, { useState } from 'react';
import { AdminHeader } from './layout/AdminHeader';
import { AdminSidebar } from './layout/AdminSidebar';
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar"

export default function AdminDashboardShell({ children, cajaUser: initialUser }) {
    const [activeView, setActiveView] = useState('dashboard');
    const [cajaUser] = useState(initialUser);

    const handleViewChange = (viewId) => {
        setActiveView(viewId);
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
        <SidebarProvider>
            <div className="flex min-h-screen w-full">
                <AdminSidebar
                    activeView={activeView}
                    onViewChange={handleViewChange}
                />

                <SidebarInset className="flex flex-col flex-1 min-w-0">
                    <AdminHeader
                        title={getTitle()}
                        cajaUser={cajaUser}
                    />

                    <main className="flex-1 overflow-y-auto p-4 lg:p-8">
                        {children}
                    </main>
                </SidebarInset>
            </div>
        </SidebarProvider>
    );
}
