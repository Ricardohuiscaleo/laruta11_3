import { redirect } from 'next/navigation';

export default function Home() {
  // Server component — redirect to login by default.
  // Client-side auth check happens in middleware.ts
  redirect('/login');
}
