import React from 'react';
import { SidebarTrigger } from "@/components/ui/sidebar"
import HeaderActions from '../HeaderActions';

export function AdminHeader({ title, cajaUser }) {
    return (
        <header className="sticky top-0 z-30 flex items-center justify-between h-16 px-4 bg-white border-b lg:px-6">
            <div className="flex items-center gap-4">
                <SidebarTrigger />
                <h1 className="text-xl font-semibold tracking-tight text-foreground">
                    {title}
                </h1>
            </div>

            <div className="flex items-center gap-4">
                <HeaderActions client:load initialUsername={cajaUser?.fullName || "Admin"} />
            </div>
        </header>
    );
}
