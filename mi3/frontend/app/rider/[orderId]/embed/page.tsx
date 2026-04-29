import type { Metadata } from 'next';
import RiderMapEmbed from '@/components/rider/RiderMapEmbed';

export const metadata: Metadata = { title: 'Rider Map' };

export default function RiderEmbedPage({ params }: { params: { orderId: string } }) {
  return <RiderMapEmbed orderId={params.orderId} />;
}
