import React from 'react';
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
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
    { id: 'test', label: 'Test APIs', icon: TestTube },
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

export function AdminSidebar({ activeView, onViewChange, isOpen, onClose }) {
    return (
        <>
            <div
                className={cn(
                    "fixed inset-0 bg-black/50 z-40 lg:hidden transition-opacity",
                    isOpen ? "opacity-100 visible" : "opacity-0 invisible"
                )}
                onClick={onClose}
            />

            <aside className={cn(
                "fixed lg:static inset-y-0 left-0 w-72 bg-white border-r z-50 transition-transform lg:translate-x-0",
                isOpen ? "translate-x-0" : "-translate-x-full"
            )}>
                <div className="flex flex-col h-full">
                    <div className="p-6 border-b">
                        <div className="flex items-center gap-3">
                            <img
                                src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png"
                                className="w-8 h-8 rounded-md"
                                alt="Logo"
                            />
                            <span className="font-bold text-lg tracking-tight">La Ruta 11</span>
                        </div>
                    </div>

                    <nav className="flex-1 overflow-y-auto p-4 space-y-1 scrollbar-thin">
                        {navItems.map((item) => {
                            const Icon = item.icon;
                            const isActive = activeView === item.id;

                            return (
                                <Button
                                    key={item.id}
                                    variant={isActive ? "default" : "ghost"}
                                    className={cn(
                                        "w-full justify-start gap-3 px-4 py-6 text-sm font-medium transition-all",
                                        !isActive && "text-muted-foreground hover:text-foreground hover:bg-muted/50"
                                    )}
                                    onClick={() => {
                                        if (item.href) {
                                            window.location.href = item.href;
                                        } else {
                                            onViewChange(item.id);
                                            onClose?.();
                                        }
                                    }}
                                >
                                    <Icon className={cn("w-5 h-5", isActive ? "text-white" : "text-muted-foreground")} />
                                    {item.label}
                                </Button>
                            );
                        })}
                    </nav>
                </div>
            </aside>
        </>
    );
}
