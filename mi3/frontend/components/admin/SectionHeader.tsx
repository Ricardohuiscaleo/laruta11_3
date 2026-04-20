'use client';

import { type ReactNode, type ComponentType } from 'react';
import { cn } from '@/lib/utils';

export interface TabDef {
  key: string;
  label: string;
  icon?: ComponentType<{ className?: string }>;
}

interface SectionHeaderProps {
  /** Título de la sección */
  title: string;
  /** Versión opcional (badge gris) */
  version?: string;
  /** Tabs de navegación */
  tabs?: TabDef[];
  /** Tab activa */
  activeTab?: string;
  /** Callback al cambiar tab */
  onTabChange?: (key: string) => void;
  /** Contenido extra a la derecha del título (budget, botones, etc.) */
  trailing?: ReactNode;
  /** Color de acento para la tab activa. Default: 'red' */
  accent?: 'red' | 'amber' | 'blue' | 'green' | 'purple';
  /** Si el header debe ser sticky. Default: true */
  sticky?: boolean;
}

const accentStyles: Record<string, string> = {
  red: 'bg-red-500 text-white shadow-sm',
  amber: 'bg-amber-500 text-white shadow-sm',
  blue: 'bg-blue-500 text-white shadow-sm',
  green: 'bg-green-500 text-white shadow-sm',
  purple: 'bg-purple-500 text-white shadow-sm',
};

export default function SectionHeader({
  title,
  version,
  tabs,
  activeTab,
  onTabChange,
  trailing,
  accent = 'red',
  sticky = true,
}: SectionHeaderProps) {
  return (
    <div
      className={cn(
        'bg-white/95 backdrop-blur-sm border-b pb-2',
        '-mx-3 px-3 sm:-mx-4 sm:px-4 lg:-mx-6 lg:px-6',
        'md:-mt-6 md:pt-6', // Remove mobile -mt-3 pt-3 so it doesn't clip into mobile pt-14
        sticky && 'sticky md:top-0 top-14 z-20', // mobile sticks below the 56px red header
      )}
    >
      {/* Row 1: Title + trailing - hide on mobile since AdminShell already shows the red mobile header */}
      <div className="hidden md:flex items-center justify-between gap-3 mb-2 min-h-[36px]">
        <h1 className="text-xl font-bold text-gray-900 shrink-0">
          {title}
          {version && (
            <span className="ml-1.5 text-xs text-gray-400 font-normal">{version}</span>
          )}
        </h1>
        {trailing && (
          <div className="min-w-0 overflow-hidden">{trailing}</div>
        )}
      </div>

      {/* Row 2: Tabs — scroll horizontal en móvil, wrap en desktop */}
      {tabs && tabs.length > 0 && (
        <nav
          className={cn(
            'flex items-center gap-1 rounded-lg bg-gray-100 p-1',
            'overflow-x-auto scrollbar-hide',
            '-mx-1 px-1',
          )}
          role="tablist"
          aria-label={`Secciones de ${title}`}
          style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
        >
          {tabs.map(({ key, label, icon: Icon }) => (
            <button
              key={key}
              role="tab"
              aria-selected={activeTab === key}
              onClick={() => onTabChange?.(key)}
              className={cn(
                'flex items-center gap-1.5 whitespace-nowrap rounded-lg text-sm font-medium transition-colors',
                'min-h-[44px] shrink-0',
                Icon ? 'px-2.5 sm:px-3 py-2' : 'px-3 py-2',
                activeTab === key
                  ? accentStyles[accent]
                  : 'text-gray-600 hover:bg-white hover:text-gray-900',
              )}
            >
              {Icon && <Icon className="h-4 w-4 shrink-0" />}
              {/* Móvil (<640px): solo icono. sm+: icono + label */}
              <span className={cn(Icon ? 'hidden sm:inline' : '')}>{label}</span>
              {Icon && <span className="sm:hidden sr-only">{label}</span>}
            </button>
          ))}
        </nav>
      )}
    </div>
  );
}
