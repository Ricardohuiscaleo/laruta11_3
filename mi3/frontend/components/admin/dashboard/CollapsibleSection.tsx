'use client';

import { useState } from 'react';
import { ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

interface CollapsibleSectionProps {
  title: string;
  summary: React.ReactNode;
  children: React.ReactNode;
  defaultOpen?: boolean;
  accentColor?: string;
  icon?: React.ReactNode;
}

export default function CollapsibleSection({
  title,
  summary,
  children,
  defaultOpen = false,
  accentColor = 'border-gray-300',
  icon,
}: CollapsibleSectionProps) {
  const [open, setOpen] = useState(defaultOpen);

  return (
    <div className={cn('rounded-xl border bg-white shadow-sm overflow-hidden border-l-4', accentColor)}>
      <button
        type="button"
        onClick={() => setOpen(v => !v)}
        className="flex items-center justify-between w-full px-4 py-3 text-left min-h-[48px] hover:bg-gray-50 transition-colors"
        aria-expanded={open}
      >
        <div className="flex items-center gap-2 min-w-0">
          {icon}
          <span className="text-sm font-semibold text-gray-900">{title}</span>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          {!open && <div className="text-xs text-gray-500 hidden sm:block">{summary}</div>}
          <ChevronRight
            className={cn(
              'h-4 w-4 text-gray-400 transition-transform duration-200',
              open && 'rotate-90',
            )}
          />
        </div>
      </button>
      {open && <div className="border-t">{children}</div>}
    </div>
  );
}
