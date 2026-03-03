import React from 'react';
import { cn } from "@/lib/utils";
import {
    LayoutDashboard,
    TrendingUp,
    CalendarDays,
    CreditCard,
    Package,
    BookOpen,
    Trash2,
    Users,
    Target,
    TestTube,
    Truck,
    FileText,
    Bot,
    CheckSquare,
    Key,
    BarChart3,
    Star,
    Layers,
    Files
} from 'lucide-react';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from "@/components/ui/sidebar"

const navItems = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { id: 'sales-analytics', label: 'Control Ventas', icon: TrendingUp },
    { id: 'purchase-plan', label: 'Plan de Compras', icon: CalendarDays },
    { id: 'payments', label: 'Pagos', icon: CreditCard },
    { id: 'products', label: 'Productos', icon: Package },
    { id: 'ingredients', label: 'Ingredientes', icon: BookOpen },
    { id: 'mermas', label: 'Mermas', icon: Trash2, href: '/admin/mermas' },
    { id: 'users', label: 'Usuarios', icon: Users },
    { id: 'militares-rl6', label: 'Militares RL6', icon: Target },
    { id: 'trucks', label: 'Food Trucks', icon: Truck, href: '/admin/food-trucks' },
    { id: 'technical-report', label: 'Informe Técnico', icon: FileText },
    { id: 'robots', label: 'Robots', icon: Bot },
    { id: 'calidad', label: 'Control Calidad', icon: CheckSquare },
    { id: 'keys', label: 'Keys', icon: Key, href: '/admin/keys' },
    { id: 'concurso', label: 'Concurso Stats', icon: BarChart3 },
    { id: 'concurso-admin', label: 'Concurso Admin', icon: Star },
    { id: 'combos', label: 'Gestión Combos', icon: Layers },
    { id: 'reportes', label: 'Reportes', icon: Files, href: '/admin/reportes' },
];

export function AdminSidebar({ activeView, onViewChange }) {
    const { setOpenMobile } = useSidebar()

    return (
        <Sidebar>
            <SidebarHeader className="border-b p-4">
                <div className="flex items-center gap-3">
                    <img
                        src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png"
                        className="w-8 h-8 rounded-md"
                        alt="Logo"
                    />
                    <span className="font-bold text-lg tracking-tight">La Ruta 11</span>
                </div>
            </SidebarHeader>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel>Administración</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {navItems.map((item) => {
                                const Icon = item.icon;
                                const isActive = activeView === item.id;

                                return (
                                    <SidebarMenuItem key={item.id}>
                                        <SidebarMenuButton
                                            isActive={isActive}
                                            onClick={() => {
                                                if (item.href) {
                                                    window.location.href = item.href;
                                                } else if (item.action) {
                                                    item.action();
                                                    setOpenMobile(false);
                                                } else {
                                                    onViewChange(item.id);
                                                    setOpenMobile(false);
                                                }
                                            }}
                                            tooltip={item.label}
                                        >
                                            <Icon className="w-5 h-5" />
                                            <span>{item.label}</span>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>
            <SidebarFooter className="border-t p-4">
                {/* User section could go here if needed, but currently handled in DashboardShell */}
            </SidebarFooter>
        </Sidebar>
    );
}
