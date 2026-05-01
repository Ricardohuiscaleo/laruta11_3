'use client';

import React, { useState, useEffect, useCallback, useRef, lazy, Suspense, Component, type ReactNode, type ComponentType } from 'react';
import { Loader2, RefreshCw } from 'lucide-react';
import { cn } from '@/lib/utils';
import PushNotificationInit from '@/components/PushNotificationInit';
import TokenFromUrl from '@/components/TokenFromUrl';
import AdminSidebarSPA from '@/components/admin/AdminSidebarSPA';
import MobileBottomNavSPA from '@/components/admin/MobileBottomNavSPA';
import { useAdminRealtime } from '@/hooks/useAdminRealtime';
import { useAuth } from '@/hooks/useAuth';

/* ─── Section type ─── */

export type SectionKey =
  | 'inicio' | 'personal' | 'turnos' | 'nomina' | 'ajustes'
  | 'creditos' | 'cambios' | 'cronjobs' | 'delivery' | 'delivery-config'
  | 'notificaciones' | 'adelantos' | 'compras' | 'checklists'
  | 'recetas' | 'capital' | 'ventas';

/* ─── Header config that sections can provide ─── */

export interface TabDef {
  key: string;
  label: string;
  icon?: ComponentType<{ className?: string }>;
}

export interface SectionHeaderConfig {
  tabs?: TabDef[];
  activeTab?: string;
  onTabChange?: (key: string) => void;
  trailing?: ReactNode;
  accent?: 'red' | 'amber' | 'blue' | 'green' | 'purple';
  version?: string;
}

/* ─── Section title mapping ─── */

const SECTION_TITLES: Record<SectionKey, string> = {
  inicio: 'Panel Admin',
  personal: 'Usuarios',
  turnos: 'Turnos',
  nomina: 'Nómina',
  ajustes: 'Ajustes',
  creditos: 'Créditos',
  cambios: 'Cambios',
  cronjobs: 'Cronjobs',
  delivery: 'Delivery',
  'delivery-config': 'Config Delivery',
  notificaciones: 'Alertas',
  adelantos: 'Adelantos',
  compras: 'Compras',
  checklists: 'Checklists',
  recetas: 'Recetas',
  capital: 'Capital de Trabajo',
  ventas: 'Ventas',
};

/* ─── Accent styles for tabs ─── */

const accentStyles: Record<string, string> = {
  red: 'bg-red-500 text-white shadow-sm',
  amber: 'bg-amber-500 text-white shadow-sm',
  blue: 'bg-blue-500 text-white shadow-sm',
  green: 'bg-green-500 text-white shadow-sm',
  purple: 'bg-purple-500 text-white shadow-sm',
};

/* ─── Lazy-loaded section registry ─── */

const sectionImports: Record<SectionKey, React.LazyExoticComponent<React.ComponentType<any>>> = {
  inicio: lazy(() => import('@/components/admin/sections/DashboardSection')),
  personal: lazy(() => import('@/components/admin/sections/PersonalSection')),
  turnos: lazy(() => import('@/components/admin/sections/TurnosSection')),
  nomina: lazy(() => import('@/components/admin/sections/NominaSection')),
  ajustes: lazy(() => import('@/components/admin/sections/AjustesSection')),
  creditos: lazy(() => import('@/components/admin/sections/CreditosSection')),
  cambios: lazy(() => import('@/components/admin/sections/CambiosSection')),
  cronjobs: lazy(() => import('@/components/admin/sections/CronjobsSection')),
  delivery: lazy(() => import('@/components/admin/sections/DeliverySection')),
  'delivery-config': lazy(() => import('@/components/admin/sections/DeliveryConfigSection')),
  notificaciones: lazy(() => import('@/components/admin/sections/NotificacionesSection')),
  adelantos: lazy(() => import('@/components/admin/sections/AdelantosSection')),
  compras: lazy(() => import('@/components/admin/sections/ComprasSection')),
  checklists: lazy(() => import('@/components/admin/sections/ChecklistsSection')),
  recetas: lazy(() => import('@/components/admin/sections/RecetasSection')),
  capital: lazy(() => import('@/components/admin/sections/CapitalTrabajoSection')),
  ventas: lazy(() => import('@/components/admin/sections/VentasSection')),
};

/* ─── URL ↔ Section mapping ─── */

function sectionFromPath(pathname: string): SectionKey {
  const segment = pathname.replace(/^\/admin\/?/, '').split('/')[0] || 'inicio';
  if (segment in sectionImports) return segment as SectionKey;
  return 'inicio';
}

function pathFromSection(section: SectionKey): string {
  return section === 'inicio' ? '/admin' : `/admin/${section}`;
}

/* ─── Section Error Boundary ─── */

interface SectionErrorBoundaryProps {
  sectionKey: string;
  children: ReactNode;
}

interface SectionErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
}

class SectionErrorBoundary extends Component<SectionErrorBoundaryProps, SectionErrorBoundaryState> {
  constructor(props: SectionErrorBoundaryProps) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: Error): SectionErrorBoundaryState {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo) {
    console.error(`[SectionError:${this.props.sectionKey}]`, error, info.componentStack);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="flex flex-col items-center justify-center py-16 px-4 text-center" role="alert">
          <div className="h-12 w-12 rounded-full bg-red-100 flex items-center justify-center mb-3">
            <span className="text-xl" aria-hidden="true">⚠️</span>
          </div>
          <p className="text-sm font-medium text-gray-900 mb-1">Error en esta sección</p>
          <p className="text-xs text-gray-500 mb-4 max-w-xs">
            {this.state.error?.message || 'Ocurrió un error inesperado'}
          </p>
          <button
            type="button"
            onClick={() => this.setState({ hasError: false, error: null })}
            className="inline-flex items-center gap-1.5 px-3 py-2 bg-red-500 text-white text-xs font-medium rounded-lg hover:bg-red-600 transition-colors"
          >
            <RefreshCw className="h-3 w-3" />
            Reintentar
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}

/* ─── Skeleton fallback ─── */

function SectionSkeleton() {
  return (
    <div className="flex items-center justify-center py-20" role="status" aria-label="Cargando sección">
      <Loader2 className="h-8 w-8 animate-spin text-red-500" />
    </div>
  );
}

/* ─── Unified Header ─── */

function UnifiedHeader({
  title,
  config,
}: {
  title: string;
  config: SectionHeaderConfig;
}) {
  const { tabs, activeTab, onTabChange, trailing, accent = 'red', version } = config;
  const hasTabs = tabs && tabs.length > 0;

  return (
    <div
      className={cn(
        'bg-white/95 backdrop-blur-sm border-b',
        hasTabs ? 'pb-1 md:pb-2' : 'pb-2 md:pb-3',
        '-mx-3 px-3 sm:-mx-4 sm:px-4 md:-mx-6 md:px-6',
        '-mt-6 pt-6',
        'sticky top-0 z-20',
      )}
    >
      {/* Desktop: Title + trailing */}
      <div className="flex items-center justify-between gap-3 mb-2 min-h-[36px]">
        <h1 className="text-xl font-bold text-gray-900 shrink-0">
          {title}
          {version && (
            <span className="ml-1.5 text-xs text-gray-400 font-normal">{version}</span>
          )}
        </h1>
        {trailing && <div className="min-w-0 overflow-hidden">{trailing}</div>}
      </div>
      {/* Tabs row */}
      {hasTabs && (
        <nav
          className="flex items-center gap-1 rounded-lg bg-gray-100 p-1 overflow-x-auto -mx-1 px-1"
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
              <span className={cn(
                Icon && activeTab !== key ? 'hidden sm:inline' : '',
              )}>{label}</span>
              {Icon && activeTab !== key && <span className="sm:hidden sr-only">{label}</span>}
            </button>
          ))}
        </nav>
      )}
    </div>
  );
}


/* ─── Main Shell ─── */

export default function AdminShell() {
  const [activeSection, setActiveSection] = useState<SectionKey>('inicio');
  const [loadedSections, setLoadedSections] = useState<Set<SectionKey>>(new Set());
  const [sectionParams, setSectionParams] = useState<Record<string, any>>({});
  const initialized = useRef(false);

  // Header config from active section
  const [headerConfigs, setHeaderConfigs] = useState<Record<string, SectionHeaderConfig>>({});
  const activeHeaderConfig = headerConfigs[activeSection] || {};

  // Clear stale header config when switching sections (sections without tabs won't call onHeaderConfig)
  const prevSection = useRef(activeSection);
  useEffect(() => {
    if (prevSection.current !== activeSection) {
      prevSection.current = activeSection;
      // If the new section hasn't registered a config yet, clear it
      if (!headerConfigs[activeSection]) {
        setHeaderConfigs(prev => {
          const next = { ...prev };
          delete next[activeSection];
          return next;
        });
      }
    }
  }, [activeSection, headerConfigs]);

  // Callback for sections to register their header config
  const setHeaderConfig = useCallback((section: string, config: SectionHeaderConfig) => {
    setHeaderConfigs(prev => {
      const existing = prev[section];
      // Only skip update if primitive fields match — trailing and onTabChange are
      // always new references (JSX / useCallback) so we exclude them from comparison.
      // This prevents infinite re-render loops (React Error #185).
      if (existing &&
        existing.tabs === config.tabs &&
        existing.activeTab === config.activeTab &&
        existing.accent === config.accent &&
        existing.version === config.version
      ) return prev;
      return { ...prev, [section]: config };
    });
  }, []);

  // Realtime: connect to admin WebSocket channel for badges
  const { user } = useAuth();
  const { badges, clearBadge, onEvent } = useAdminRealtime(user?.is_admin ? user.personal_id : null);

  // Auto-refresh: when a realtime event arrives for the active section, notify via ref
  // NOTE: We do NOT use key-based re-mounting (refreshCounters) because it causes
  // infinite loops — re-mount → re-subscribe to WebSocket → event fires → re-mount.
  // Instead, sections handle their own refresh via WebSocket listeners.
  useEffect(() => {
    onEvent((event) => {
      // Badges are already handled by useAdminRealtime.
      // Sections that need live refresh (e.g., DashboardSection) subscribe
      // to their own WebSocket channels directly.
    });
  }, [onEvent]);

  // Initialize from URL on mount
  useEffect(() => {
    if (initialized.current) return;
    initialized.current = true;
    const initial = sectionFromPath(window.location.pathname);
    setActiveSection(initial);
    setLoadedSections(new Set([initial]));
  }, []);

  // Ensure active section is always in loadedSections
  useEffect(() => {
    setLoadedSections(prev => {
      if (prev.has(activeSection)) return prev;
      const next = new Set(prev);
      next.add(activeSection);
      return next;
    });
  }, [activeSection]);

  // URL sync: pushState on section change (skip initial mount)
  const isFirstRender = useRef(true);
  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }
    const newPath = pathFromSection(activeSection);
    if (window.location.pathname !== newPath) {
      window.history.pushState({ section: activeSection }, '', newPath);
    }
  }, [activeSection]);

  // Listen to popstate for browser back/forward
  useEffect(() => {
    function onPopState(e: PopStateEvent) {
      const section = e.state?.section || sectionFromPath(window.location.pathname);
      setActiveSection(section);
    }
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  // Navigation function for cross-section navigation
  const onNavigate = useCallback((section: string, params?: any) => {
    const key = section as SectionKey;
    if (!(key in sectionImports)) return;
    if (params) {
      setSectionParams(prev => ({ ...prev, [key]: params }));
    }
    setActiveSection(key);
    clearBadge(key);
  }, [clearBadge]);

  const onSectionChange = useCallback((section: string) => {
    onNavigate(section);
  }, [onNavigate]);

  // Render props for sections
  const getSectionProps = (key: SectionKey): Record<string, any> => {
    const props: Record<string, any> = {
      onHeaderConfig: (config: SectionHeaderConfig) => setHeaderConfig(key, config),
    };
    if (key === 'notificaciones' || key === 'adelantos') {
      props.onNavigate = onNavigate;
    }
    if (key === 'adelantos' && sectionParams.adelantos?.highlightId) {
      props.highlightId = sectionParams.adelantos.highlightId;
    }
    return props;
  };

  const title = SECTION_TITLES[activeSection] || 'Admin';
  const showUnifiedHeader = activeHeaderConfig.tabs || activeHeaderConfig.trailing;

  return (
    <div className="min-h-screen bg-gray-50">
      <Suspense fallback={null}><TokenFromUrl /></Suspense>
      <PushNotificationInit />

      {/* Desktop: sidebar */}
      <div className="hidden md:block fixed top-0 left-0 h-screen z-30">
        <AdminSidebarSPA
          activeSection={activeSection}
          onSectionChange={onSectionChange}
          badges={badges}
        />
      </div>

      {/* Mobile: unified header (red bar + optional tabs/trailing) */}
      <header className="md:hidden fixed top-0 left-0 right-0 z-40 bg-red-500 shadow-sm pt-[env(safe-area-inset-top)]">
        {/* Row 1: Logo + Title */}
        <div className="flex items-center justify-between h-14 px-4">
          <div className="flex items-center gap-2">
            <img src="/R11WORK.png" alt="La Ruta 11" className="h-8 w-8 rounded-lg" />
          </div>
          <span className="text-sm font-semibold text-white">{title}</span>
          <div className="w-8" />
        </div>
        {/* Row 2: Trailing + Tabs (if section provides them) */}
        {showUnifiedHeader && (
          <div className="bg-white/95 backdrop-blur-sm border-b px-3 pb-1 pt-1">
            {activeHeaderConfig.trailing && (
              <div className="mb-1 overflow-hidden">{activeHeaderConfig.trailing}</div>
            )}
            {activeHeaderConfig.tabs && activeHeaderConfig.tabs.length > 0 && (
              <nav
                className="flex items-center gap-1 rounded-lg bg-gray-100 p-1 overflow-x-auto"
                role="tablist"
                aria-label={`Secciones de ${title}`}
                style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
              >
                {activeHeaderConfig.tabs.map(({ key, label, icon: Icon }) => (
                  <button
                    key={key}
                    role="tab"
                    aria-selected={activeHeaderConfig.activeTab === key}
                    onClick={() => activeHeaderConfig.onTabChange?.(key)}
                    className={cn(
                      'flex items-center gap-1.5 whitespace-nowrap rounded-lg text-sm font-medium transition-colors',
                      'min-h-[44px] shrink-0',
                      Icon ? 'px-2.5 sm:px-3 py-2' : 'px-3 py-2',
                      activeHeaderConfig.activeTab === key
                        ? accentStyles[activeHeaderConfig.accent || 'red']
                        : 'text-gray-600 hover:bg-white hover:text-gray-900',
                    )}
                  >
                    {Icon && <Icon className="h-4 w-4 shrink-0" />}
                    <span className={cn(
                      Icon && activeHeaderConfig.activeTab !== key ? 'hidden sm:inline' : '',
                    )}>{label}</span>
                    {Icon && activeHeaderConfig.activeTab !== key && <span className="sm:hidden sr-only">{label}</span>}
                  </button>
                ))}
              </nav>
            )}
          </div>
        )}
      </header>

      {/* Content */}
      <main className={cn(
        'min-h-screen pb-20 px-4 sm:px-5 md:pt-0 md:pb-0 md:pl-60 md:pr-8 md:py-6 overflow-y-auto',
        showUnifiedHeader ? 'pt-[7.5rem]' : 'pt-14',
      )}>
        {/* Desktop: unified header (title + trailing + tabs) */}
        {showUnifiedHeader && (
          <div className="hidden md:block">
            <UnifiedHeader title={title} config={activeHeaderConfig} />
          </div>
        )}

        {Array.from(loadedSections).map(key => {
          const Component = sectionImports[key];
          return (
            <div key={key} className={activeSection === key ? 'block' : 'hidden'}>
              <SectionErrorBoundary sectionKey={key}>
                <Suspense fallback={<SectionSkeleton />}>
                  <Component {...getSectionProps(key)} />
                </Suspense>
              </SectionErrorBoundary>
            </div>
          );
        })}
      </main>

      {/* Mobile: bottom nav */}
      <div className="md:hidden">
        <MobileBottomNavSPA
          activeSection={activeSection}
          onSectionChange={onSectionChange}
          badges={badges}
        />
      </div>
    </div>
  );
}
