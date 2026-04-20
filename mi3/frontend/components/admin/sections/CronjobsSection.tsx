'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { Loader2, Clock, CheckCircle2, XCircle, Timer } from 'lucide-react';

interface CronjobStat {
  command: string;
  name: string;
  total_runs: number;
  successes: number;
  failures: number;
  success_rate: number;
  avg_duration: number;
  last_run: string | null;
  last_status: string | null;
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'ahora';
  if (mins < 60) return `hace ${mins}m`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `hace ${hrs}h`;
  return `hace ${Math.floor(hrs / 24)}d`;
}

export default function CronjobsSection() {
  const [cronjobs, setCronjobs] = useState<CronjobStat[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiFetch<{ success: boolean; data: CronjobStat[] }>('/admin/cronjobs')
      .then(res => { if (res.data) setCronjobs(res.data); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Clock className="h-6 w-6 text-amber-700" />
        <h1 className="hidden md:block text-2xl font-bold text-gray-900">Cronjobs</h1>
      </div>

      {cronjobs.length === 0 ? (
        <p className="text-sm text-gray-400">Sin datos de cronjobs aún</p>
      ) : (
        <div className="space-y-3">
          {cronjobs.map((job, i) => (
            <div key={i} className="rounded-xl border bg-white px-5 py-4 shadow-sm">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3 min-w-0">
                  {job.last_status === 'success' ? (
                    <CheckCircle2 className="h-5 w-5 text-green-500 shrink-0" />
                  ) : job.last_status === 'failed' ? (
                    <XCircle className="h-5 w-5 text-red-500 shrink-0" />
                  ) : (
                    <Timer className="h-5 w-5 text-gray-400 shrink-0" />
                  )}
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-gray-900 truncate">{job.name}</p>
                    <p className="text-xs text-gray-500 font-mono">{job.command}</p>
                  </div>
                </div>
                {job.last_run && (
                  <p className="text-xs text-gray-400 shrink-0">{timeAgo(job.last_run)}</p>
                )}
              </div>
              <div className="flex items-center gap-4 mt-3 ml-8">
                <div className="flex items-center gap-1">
                  <span className="text-xs font-semibold text-gray-700">{job.total_runs}</span>
                  <span className="text-xs text-gray-400">total</span>
                </div>
                <div className="flex items-center gap-1">
                  <span className="text-xs font-semibold text-green-600">{job.successes}</span>
                  <span className="text-xs text-gray-400">ok</span>
                </div>
                {job.failures > 0 && (
                  <div className="flex items-center gap-1">
                    <span className="text-xs font-semibold text-red-600">{job.failures}</span>
                    <span className="text-xs text-gray-400">fallos</span>
                  </div>
                )}
                <div className={`text-xs font-bold px-2 py-0.5 rounded-full ${
                  job.success_rate >= 95 ? 'bg-green-100 text-green-700' :
                  job.success_rate >= 80 ? 'bg-yellow-100 text-yellow-700' :
                  'bg-red-100 text-red-700'
                }`}>
                  {job.success_rate}%
                </div>
                {job.avg_duration > 0 && (
                  <span className="text-xs text-gray-400">~{job.avg_duration}s</span>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
