import AdminShell from '@/components/admin/AdminShell';

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  // AdminShell handles everything: sidebar, mobile nav, content area, URL sync.
  // The children (Next.js page routes) are ignored — AdminShell renders sections
  // directly via React.lazy(). Page files remain as thin wrappers for direct URL
  // access and SEO, but AdminShell parses the URL on mount to set the right section.
  return <AdminShell />;
}
