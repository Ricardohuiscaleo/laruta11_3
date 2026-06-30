import { Metadata } from 'next';
import NominaPublicClient from './NominaPublicClient';

const API = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';
const MONTH_NAMES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function fmtMonth(mes: string) {
  const [y, m] = mes.split('-');
  return `${MONTH_NAMES[parseInt(m, 10) - 1]} ${y}`;
}

interface Props {
  params: { token: string };
  searchParams: { worker?: string };
}

export async function generateMetadata({ params, searchParams }: Props): Promise<Metadata> {
  const token = params.token;
  const workerId = searchParams.worker;

  let title = 'Nómina — La Ruta 11';
  let description = 'Revisa el detalle de tu nómina';
  let ogUrl = `https://mi.laruta11.cl/nomina/${token}`;

  try {
    const res = await fetch(`${API}/api/v1/nomina/${token}`, {
      headers: { Accept: 'application/json' },
      next: { revalidate: 60 },
    });
    const d = await res.json();
    if (d.success && d.data && workerId) {
      const pid = parseInt(workerId, 10);
      const allWorkers = [
        ...(d.data.ruta11?.workers ?? []),
        ...((d.data.seguridad?.workers ?? [])),
      ];
      const w = allWorkers.find((w: any) => w.personal_id === pid);
      if (w) {
        const total = Math.round(w.total_a_pagar).toLocaleString('es-CL');
        title = `${w.nombre} — ${fmtMonth(d.mes)} — La Ruta 11`;
        description = `Total a pagar: $${total}`;
        ogUrl = `https://mi.laruta11.cl/nomina/${token}?worker=${workerId}`;
      } else {
        title = `Nómina ${fmtMonth(d.mes)} — La Ruta 11`;
      }
    } else if (d.success && d.data) {
      title = `Nómina ${fmtMonth(d.mes)} — La Ruta 11`;
    }
  } catch {}

  return {
    title,
    description,
    openGraph: {
      title,
      description,
      url: ogUrl,
      type: 'website',
      siteName: 'La Ruta 11',
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
    },
  };
}

export default async function NominaPublicPage(props: Props) {
  let initialData: any = null;
  let initialMes = '';
  let initialCreatedAt = '';
  let initialAprobadoPor: string | null = null;
  let initialAprobadoAt: string | null = null;

  try {
    const res = await fetch(`${API}/api/v1/nomina/${props.params.token}`, {
      headers: { Accept: 'application/json' },
      next: { revalidate: 60 },
    });
    const d = await res.json();
    if (d.success) {
      initialData = d.data;
      initialMes = d.mes;
      initialCreatedAt = d.created_at ?? '';
      initialAprobadoPor = d.aprobado_por ?? null;
      initialAprobadoAt = d.aprobado_at ?? null;
    }
  } catch {}

  return (
    <NominaPublicClient
      params={props.params}
      initialData={initialData}
      initialMes={initialMes}
      initialCreatedAt={initialCreatedAt}
      initialAprobadoPor={initialAprobadoPor}
      initialAprobadoAt={initialAprobadoAt}
    />
  );
}
