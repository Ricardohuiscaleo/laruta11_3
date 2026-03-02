import React from 'react';
import { Menu } from 'lucide-react';
import { Button } from "@/components/ui/button";
import HeaderActions from '../HeaderActions';

export function AdminHeader({ title, onMenuClick, cajaUser }) {
    return (
        <header className="sticky top-0 z-30 flex items-center justify-between h-16 px-4 bg-white border-b lg:px-8">
            <div className="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    className="lg:hidden"
                    onClick={onMenuClick}
                >
                    <Menu className="w-6 h-6" />
                </Button>
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
