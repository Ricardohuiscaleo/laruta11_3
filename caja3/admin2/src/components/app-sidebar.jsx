import * as React from "react"
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
    Files,
} from "lucide-react"

import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from "@/components/ui/sidebar"

const data = {
    navMain: [
        {
            title: "Administración",
            items: [
                {
                    title: "Dashboard",
                    url: "#",
                    icon: LayoutDashboard,
                    isActive: true,
                },
                {
                    title: "Control Ventas",
                    url: "#",
                    icon: TrendingUp,
                },
                {
                    title: "Plan de Compras",
                    url: "#",
                    icon: CalendarDays,
                },
                {
                    title: "Pagos",
                    url: "#",
                    icon: CreditCard,
                },
            ],
        },
        {
            title: "Gestión",
            items: [
                {
                    title: "Productos",
                    url: "#",
                    icon: Package,
                },
                {
                    title: "Ingredientes",
                    url: "#",
                    icon: BookOpen,
                },
                {
                    title: "Mermas",
                    url: "#",
                    icon: Trash2,
                },
            ],
        },
        {
            title: "Plataforma",
            items: [
                {
                    title: "Usuarios",
                    url: "#",
                    icon: Users,
                },
                {
                    title: "Food Trucks",
                    url: "#",
                    icon: Truck,
                },
                {
                    title: "Reportes",
                    url: "#",
                    icon: Files,
                },
            ],
        },
    ],
}

export function AppSidebar({ ...props }) {
    return (
        <Sidebar {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <a href="#">
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                    <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png" className="w-6 h-6 rounded-sm" alt="Logo" />
                                </div>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="font-semibold text-lg">La Ruta 11</span>
                                    <span className="truncate text-xs text-muted-foreground">Admin 2.0</span>
                                </div>
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                {data.navMain.map((group) => (
                    <SidebarGroup key={group.title}>
                        <SidebarGroupLabel>{group.title}</SidebarGroupLabel>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {group.items.map((item) => (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton asChild isActive={item.isActive} tooltip={item.title}>
                                            <a href={item.url}>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </a>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                ))}
            </SidebarContent>
            <SidebarRail />
        </Sidebar>
    )
}
