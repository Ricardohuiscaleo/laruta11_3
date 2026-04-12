import { NextResponse } from 'next/server';

const COOLIFY_TOKEN = '3|S52ZUspC6N5G54apjgnKO6sY3VW5OixHlnY9GsMv8dc72ae8';
const APPS: Record<string, string> = {
  'mi3-backend': 'ds24j8jlaf9ov4flk1nq4jek',
  'app3': 'egck4wwcg0ccc4osck4sw8ow',
  'caja3': 'xockcgsc8k000o8osw8o88ko',
};

const COOLIFY_URLS = [
  'http://coolify:8080/api/v1',           // Coolify v4 internal port
  'http://coolify:80/api/v1',
  'http://coolify:8000/api/v1',
  'http://host.docker.internal:8000/api/v1',
  'http://172.17.0.1:8000/api/v1',
  'http://172.18.0.1:8000/api/v1',
  'http://76.13.126.63:8000/api/v1',
];

async function findWorkingUrl(): Promise<{ url: string | null; errors: string[] }> {
  const headers = { Authorization: `Bearer ${COOLIFY_TOKEN}`, Accept: 'application/json' };
  const errors: string[] = [];
  for (const url of COOLIFY_URLS) {
    try {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 2000);
      const res = await fetch(`${url}/version`, { headers, signal: controller.signal });
      clearTimeout(timeout);
      if (res.ok) return { url, errors };
      errors.push(`${url}: HTTP ${res.status}`);
    } catch (e: any) {
      errors.push(`${url}: ${e.cause?.code || e.message || 'unknown'}`);
    }
  }
  return { url: null, errors };
}

export async function GET() {
  const headers = { Authorization: `Bearer ${COOLIFY_TOKEN}`, Accept: 'application/json' };
  const tasks: any[] = [];

  const { url: baseUrl, errors } = await findWorkingUrl();
  if (!baseUrl) {
    return NextResponse.json({ error: 'Cannot reach Coolify API', tried: COOLIFY_URLS, details: errors }, { status: 502 });
  }

  for (const [appName, uuid] of Object.entries(APPS)) {
    try {
      const res = await fetch(`${baseUrl}/applications/${uuid}/scheduled-tasks`, { headers, cache: 'no-store' });
      if (!res.ok) continue;
      const taskList = await res.json();

      for (const task of taskList) {
        let total_runs = 0, failures = 0, last_status: string | null = null, last_run: string | null = null;
        try {
          const execRes = await fetch(`${baseUrl}/applications/${uuid}/scheduled-tasks/${task.uuid}/executions`, { headers, cache: 'no-store' });
          if (execRes.ok) {
            const execs = await execRes.json();
            total_runs = execs.length;
            failures = execs.filter((e: any) => e.status !== 'success').length;
            if (execs[0]) { last_status = execs[0].status; last_run = execs[0].finished_at; }
          }
        } catch {}
        tasks.push({ app: appName, name: task.name, frequency: task.frequency, enabled: task.enabled, last_status, last_run, total_runs, failures });
      }
    } catch {}
  }

  return NextResponse.json(tasks);
}
