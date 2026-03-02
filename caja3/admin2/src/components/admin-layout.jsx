import { SidebarProvider, SidebarInset, SidebarTrigger } from "./ui/sidebar"
import { AppSidebar } from "./app-sidebar"
import { Separator } from "./ui/separator"

export function AdminLayout({ children, title = "Dashboard" }) {
    return (
        <SidebarProvider>
            <AppSidebar />
            <SidebarInset>
                <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 border-b px-4">
                    <div className="flex items-center gap-2">
                        <SidebarTrigger className="-ml-1" />
                        <Separator orientation="vertical" className="mr-2 h-4" />
                        <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                    </div>
                </header>
                <main className="flex flex-1 flex-col gap-4 p-4 lg:p-6 bg-slate-50/50">
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
    )
}
