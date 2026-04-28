import PublicRiderView from '@/components/rider/PublicRiderView';

export default function PublicRiderPage({ params }: { params: { orderId: string } }) {
  return <PublicRiderView orderId={params.orderId} />;
}
