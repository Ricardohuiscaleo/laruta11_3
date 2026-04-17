'use client';

import React, { useState, useEffect, useCallback, useRef, lazy, Suspense } from 'react';
import { Loader2 } from 'lucide-react';
import PushNotificationInit from '@/components/PushNotificationInit';
import TokenFromUrl from '@/components/TokenFromUrl';
import AdminSidebarSPA from '@/components/admin/AdminSidebarSPA';
import MobileBottomNavSPA from '@/components/admin/MobileBottomNavSPA';
import { useAdminRealtime } from '@/hooks/useAdminRealtime';
import { useAuth } from '@/hooks/useAuth';

/* ─── Section type ─── */

export type SectionKey =
  | 'inicio' | 'personal' | 'turnos' | 'nomina' | 'ajustes'
  | 'creditos' | 'cambios' | 'cronjobs' | 'delivery'
  | 'notificaciones' | 'adelantos' | 'compras' | 'checklists'
  | 'recetas';

/* ─── Section title mapping ─── */

const SECTION_TITLES: Record<SectionKey, string> = {
  inicio: 'Panel Admin',
  personal: 'Personal',
  turnos: 'Turnos',
  nomina: 'Nómina',
  ajustes: 'Ajustes',
  creditos: 'Créditos R11',
  cambios: 'Cambios',
  cronjobs: 'Cronjobs',
  delivery: 'Delivery',
  notificaciones: 'Alertas',
  adelantos: 'Adelantos',
  compras: 'Compras',
  checklists: 'Checklists',
  recetas: 'Recetas',
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
  notificaciones: lazy(() => import('@/components/admin/sections/NotificacionesSection')),
  adelantos: lazy(() => import('@/components/admin/sections/AdelantosSection')),
  compras: lazy(() => import('@/components/admin/sections/ComprasSection')),
  checklists: lazy(() => import('@/components/admin/sections/ChecklistsSection')),
  recetas: lazy(() => import('@/components/admin/sections/RecetasSection')),
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

/* ─── Skeleton fallback ─── */

function SectionSkeleton() {
  return (
    <div className="flex items-center justify-center py-20" role="status" aria-label="Cargando sección">
      <Loader2 className="h-8 w-8 animate-spin text-red-500" />
    </div>
  );
}


/* ─── Main Shell ─── */

export default function AdminShell() {
  // Parse initial section from URL (client-side only)
  const [activeSection, setActiveSection] = useState<SectionKey>('inicio');
  const [loadedSections, setLoadedSections] = useState<Set<SectionKey>>(new Set());
  const [sectionParams, setSectionParams] = useState<Record<string, any>>({});
  const initialized = useRef(false);

  // Realtime: connect to admin WebSocket channel for badges
  const { user } = useAuth();
  const { badges, clearBadge, onEvent } = useAdminRealtime(user?.is_admin ? user.personal_id : null);

  // Auto-refresh: when a realtime event arrives for the active section, force re-mount
  const [refreshCounters, setRefreshCounters] = useState<Record<string, number>>({});

  useEffect(() => {
    onEvent((event) => {
      if (event.type === 'admin.data.updated' && event.section === activeSection) {
        setRefreshCounters(prev => ({
          ...prev,
          [event.section]: (prev[event.section] || 0) + 1,
        }));
      }
    });
  }, [activeSection, onEvent]);

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

  // Navigation function for cross-section navigation (used by NotificacionesSection, AdelantosSection)
  const onNavigate = useCallback((section: string, params?: any) => {
    const key = section as SectionKey;
    if (!(key in sectionImports)) return;
    if (params) {
      setSectionParams(prev => ({ ...prev, [key]: params }));
    }
    setActiveSection(key);
    // Clear badge for the section being navigated to
    clearBadge(key);
  }, [clearBadge]);

  // Section change handler for sidebar/bottom nav
  const onSectionChange = useCallback((section: string) => {
    onNavigate(section);
  }, [onNavigate]);

  // Render props for sections that accept them
  const getSectionProps = (key: SectionKey): Record<string, any> => {
    const props: Record<string, any> = {};
    if (key === 'notificaciones' || key === 'adelantos') {
      props.onNavigate = onNavigate;
    }
    if (key === 'adelantos' && sectionParams.adelantos?.highlightId) {
      props.highlightId = sectionParams.adelantos.highlightId;
    }
    return props;
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <Suspense fallback={null}><TokenFromUrl /></Suspense>
      <PushNotificationInit />

      {/* Desktop layout: sidebar + content */}
      <div className="hidden md:flex min-h-screen">
        <AdminSidebarSPA
          activeSection={activeSection}
          onSectionChange={onSectionChange}
          badges={badges}
        />
        <main className="flex-1 p-6">
          {Array.from(loadedSections).map(key => {
            const Component = sectionImports[key];
            return (
              <div key={`${key}-${refreshCounters[key] || 0}`} className={activeSection === key ? 'block' : 'hidden'}>
                <Suspense fallback={<SectionSkeleton />}>
                  <Component {...getSectionProps(key)} />
                </Suspense>
              </div>
            );
          })}
        </main>
      </div>

      {/* Mobile layout: content + bottom nav */}
      <div className="md:hidden">
        <header className="fixed top-0 left-0 right-0 z-40 h-14 bg-red-500 shadow-sm">
          <div className="flex items-center justify-between h-full px-4">
            <div className="flex items-center gap-2">
              <img src="/R11HEADER.jpg" alt="La Ruta 11" className="h-8 w-auto" />
            </div>
            <span className="text-sm font-semibold text-white">{SECTION_TITLES[activeSection] || 'Admin'}</span>
            <div className="w-8" />
          </div>
        </header>
        <div className="pt-14 pb-20 px-3 sm:px-4 scroll-smooth">
          {Array.from(loadedSections).map(key => {
            const Component = sectionImports[key];
            return (
              <div key={`${key}-${refreshCounters[key] || 0}`} className={activeSection === key ? 'block' : 'hidden'}>
                <Suspense fallback={<SectionSkeleton />}>
                  <Component {...getSectionProps(key)} />
                </Suspense>
              </div>
            );
          })}
        </div>
        <MobileBottomNavSPA
          activeSection={activeSection}
          onSectionChange={onSectionChange}
          badges={badges}
        />
      </div>
    </div>
  );
}
