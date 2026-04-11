'use client';

import { usePathname } from 'next/navigation';
import { ArrowRightLeft } from 'lucide-react';
import Link from 'next/link';
import { useEffect, useState } from 'react';

function getCookie(name: string): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
  return match ? decodeURIComponent(match[2]) : null;
}

export default function ViewSwitcher({ className = '' }: { className?: string }) {
  const pathname = usePathname();
  const [isAdmin, setIsAdmin] = useState(false);

  useEffect(() => {
    setIsAdmin(getCookie('mi3_role') === 'admin');
  }, []);

  if (!isAdmin) return null;

  const isOnAdmin = pathname.startsWith('/admin');
  const target = isOnAdmin ? '/dashboard' : '/admin';
  const label = isOnAdmin ? 'Vista Trabajador' : 'Vista Admin';

  return (
    <Link href={target}
      className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${className}`}>
      <ArrowRightLeft className="h-5 w-5" />
      {label}
    </Link>
  );
}
