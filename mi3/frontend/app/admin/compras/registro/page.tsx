'use client';

import { useState, useCallback, useRef, useEffect } from 'react';
import { Upload, X, Loader2, Check, AlertTriangle, Trash2, ChevronDown, ChevronUp, FileText, Search, Sparkles, Package } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP } from '@/lib/compras-utils';
import { useCompras } from '@/contexts/ComprasContext';
import ExtractionPipeline from '@/components/admin/compras/ExtractionPipeline';
import ReconciliationQuestions from '@/components/admin/compras/ReconciliationQuestions';
import type { ReconciliationQuestion } from '@/components/admin/compras/ExtractionPipeline';
import type { ExtractionResult, Kpi, ItemSugerencia, RegistroGroup, RegistroItem, RegistroImage } from '@/types/compras';

// Types imported from @/types/compras: RegistroImage, RegistroItem, RegistroGroup

type CompraItem = RegistroItem;
type CompraGroup = RegistroGroup;
type UploadedImage = RegistroImage;

const METODOS_PAGO = [
  { value: 'cash', label: 'Efectivo' },
  { value: 'transfer', label: 'Transferencia' },
  { value: 'card', label: 'Tarjeta' },
  { value: 'credit', label: 'Crédito' },
];

// --- Item search mini-component (inline) ---
function InlineItemSearch({ onSelect }: { onSelect: (item: { id: number; name: string; unit: string; type: 'ingredient' | 'product'; cost_per_unit: number | null }) => void }) {
  const [q, setQ] = useState('');
  const [results, setResults] = useState<any[]>([]);
  const [open, setOpen] = useState(false);
  const timer = useRef<NodeJS.Timeout | null>(null);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const h = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false); };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, []);

  const search = async (val: string) => {
    if (val.length < 2) { setResults([]); setOpen(false); return; }
    try {
      const data = await comprasApi.get<any[]>(`/compras/items?q=${encodeURIComponent(val)}`);
      setResults(data);
      setOpen(true);
    } catch { setResults([]); }
  };

  return (
    <div ref={ref} className="relative">
      <div className="relative">
        <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
        <input type="text" value={q} placeholder="Buscar ingrediente..."
          onChange={e => { setQ(e.target.value); if (timer.current) clearTimeout(timer.current); timer.current = setTimeout(() => search(e.target.value), 300); }}
          className="w-full rounded-lg border border-gray-300 py-1.5 pl-8 pr-3 text-base" />
      </div>
      {open && results.length > 0 && (
        <div className="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-lg border bg-white shadow-lg">
          {results.map((r: any) => (
            <button key={`${r.type}-${r.id}`} onClick={() => { onSelect(r); setQ(''); setOpen(false); }}
              className="flex w-full items-center justify-between px-3 py-1.5 text-left text-sm hover:bg-gray-50">
              <span className="font-medium">{r.name}</span>
              <span className="text-xs text-gray-400">{r.current_stock} {r.unit}</span>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

// --- Proveedor search with autocomplete ---
function ProveedorSearch({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const [suggestions, setSuggestions] = useState<string[]>([]);
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const h = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false); };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, []);

  const search = async (val: string) => {
    if (val.length < 1) { setSuggestions([]); setOpen(false); return; }
    try {
      const data = await comprasApi.get<{ success: boolean; data: string[] }>(`/compras/proveedores?q=${encodeURIComponent(val)}`);
      const list = Array.isArray(data) ? data : (data.data || []);
      setSuggestions(list.filter((s: string) => s.toLowerCase().includes(val.toLowerCase())));
      setOpen(list.length > 0);
    } catch { setSuggestions([]); }
  };

  return (
    <div ref={ref} className="relative">
      <input type="text" value={value} placeholder="Buscar proveedor..."
        onChange={e => { onChange(e.target.value); search(e.target.value); }}
        onFocus={() => { if (value) search(value); }}
        className="w-full rounded border px-2 py-1.5 text-base" />
      {open && suggestions.length > 0 && (
        <div className="absolute z-20 mt-1 max-h-40 w-full overflow-auto rounded-lg border bg-white shadow-lg">
          {suggestions.map(s => (
            <button key={s} onClick={() => { onChange(s); setOpen(false); }}
              className="w-full px-3 py-1.5 text-left text-sm hover:bg-gray-50 font-medium">{s}</button>
          ))}
        </div>
      )}
    </div>
  );
}

// --- Main page component ---
export default function RegistroPage() {
  const { registroGroups: groups, registroSubmitted: submitted, setRegistroGroups: setGroups, setRegistroSubmitted: setSubmitted, kpis: ctxKpis, refreshAll } = useCompras();
  const [uploading, setUploading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [saldo, setSaldo] = useState<number | null>(ctxKpis?.saldo_disponible ?? null);
  const inputRef = useRef<HTMLInputElement>(null);
  const [previewImg, setPreviewImg] = useState<string | null>(null);
  const [pipelineTempKey, setPipelineTempKey] = useState<string | null>(null);
  const [pipelineTempUrl, setPipelineTempUrl] = useState<string | null>(null);
  const [uploadProgress, setUploadProgress] = useState<string | null>(null);
  const [reconciliationQuestions, setReconciliationQuestions] = useState<ReconciliationQuestion[]>([]);
  const [reconciliationLoading, setReconciliationLoading] = useState(false);

  // Sync saldo from context
  useEffect(() => {
    if (ctxKpis) setSaldo(ctxKpis.saldo_disponible);
  }, [ctxKpis]);

  // Compress image using canvas (max 1200px wide, JPEG quality 0.7)
  const compressImage = useCallback((file: File): Promise<File> => {
    return new Promise((resolve) => {
      // Skip non-image or already small files
      if (file.size < 500_000) { resolve(file); return; }

      const img = new Image();
      const url = URL.createObjectURL(file);
      img.onload = () => {
        URL.revokeObjectURL(url);
        const maxW = 1200;
        const scale = img.width > maxW ? maxW / img.width : 1;
        const w = Math.round(img.width * scale);
        const h = Math.round(img.height * scale);
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        if (!ctx) { resolve(file); return; }
        ctx.drawImage(img, 0, 0, w, h);
        canvas.toBlob(
          (blob) => {
            if (!blob || blob.size >= file.size) { resolve(file); return; }
            resolve(new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' }));
          },
          'image/jpeg',
          0.7
        );
      };
      img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }, []);

  // Upload + extract + group
  const processFiles = useCallback(async (files: FileList | File[]) => {
    console.log('[Compras] processFiles called, files:', files.length);
    const imageFiles = Array.from(files).filter(f => {
      const byType = f.type.startsWith('image/');
      const byExt = /\.(jpe?g|png|gif|webp|heic|heif|bmp|tiff?)$/i.test(f.name);
      console.log('[Compras] file:', f.name, 'type:', f.type, 'size:', f.size, 'byType:', byType, 'byExt:', byExt);
      return byType || byExt;
    });
    if (imageFiles.length === 0) { console.warn('[Compras] No image files after filter'); return; }

    // Single photo: upload then show pipeline visual SSE
    if (imageFiles.length === 1) {
      setUploading(true);
      try {
        console.log('[Compras] compressing image...');
        const compressed = await compressImage(imageFiles[0]);
        console.log('[Compras] compressed:', compressed.name, compressed.size, compressed.type);
        const fd = new FormData();
        fd.append('image', compressed);
        console.log('[Compras] uploading to /compras/upload-temp...');
        const res = await comprasApi.upload<{ tempKey: string; tempUrl: string }>('/compras/upload-temp', fd);
        console.log('[Compras] upload OK, tempKey:', res.tempKey);
        setPipelineTempKey(res.tempKey);
        setPipelineTempUrl(res.tempUrl);
      } catch (err) {
        console.error('[Compras] upload error:', err);
        // Show error to user instead of silently failing
        const newGroups = [...groups.filter((_, i) => !submitted.includes(i))];
        newGroups.push({
          proveedor: '', fecha_compra: new Date().toISOString().split('T')[0],
          metodo_pago: 'cash', tipo_compra: 'ingredientes', notas: '',
          images: [{ tempKey: '', tempUrl: '', status: 'error', error: 'Error al subir imagen. Intenta de nuevo.' }],
          items: [], expanded: true,
        });
        setGroups(newGroups);
        setSubmitted([]);
      }
      setUploading(false);
      return;
    }

    // Multiple photos: use sync endpoint with progress
    setUploading(true);
    const newGroups: CompraGroup[] = [...groups.filter((_, i) => !submitted.includes(i))];

    for (let fi = 0; fi < imageFiles.length; fi++) {
      const file = imageFiles[fi];
      setUploadProgress(`Procesando ${fi + 1}/${imageFiles.length}...`);

      let tempKey = '', tempUrl = '';
      try {
        const compressed = await compressImage(file);
        const fd = new FormData();
        fd.append('image', compressed);
        const res = await comprasApi.upload<{ tempKey: string; tempUrl: string }>('/compras/upload-temp', fd);
        tempKey = res.tempKey;
        tempUrl = res.tempUrl;
      } catch { continue; }

      const img: UploadedImage = { tempKey, tempUrl, status: 'extracting' };

      // Extract with IA + get sugerencias
      try {
        const res = await comprasApi.post<{
          success: boolean;
          data?: ExtractionResult;
          confianza?: any;
          sugerencias?: { proveedor: any; items: ItemSugerencia[] };
          error?: string;
        }>('/compras/extract', { temp_key: tempKey });

        if (res.success && res.data) {
          img.status = 'extracted';
          img.extraction = { ...res.data, confianza: res.confianza ?? res.data.confianza, sugerencias: res.sugerencias };
          img.sugerencias = res.sugerencias;
        } else {
          img.status = 'error';
          img.error = res.error || 'No se pudo extraer';
        }
      } catch {
        img.status = 'error';
        img.error = 'Error de conexión';
      }

      // Build items with DB match
      const extractedItems = img.extraction?.items || [];
      const sugItems = img.sugerencias?.items || [];
      const items: CompraItem[] = extractedItems.map((item, idx) => {
        const sug = sugItems[idx];
        const matched = sug?.pre_selected && sug?.match;
        if (matched && sug.match) {
          const m = sug.match;
          const isIng = sug.match_type === 'ingredient';
          return {
            ingrediente_id: isIng ? m.id : null,
            product_id: !isIng ? m.id : null,
            item_type: (sug.match_type || 'ingredient') as 'ingredient' | 'product',
            nombre: m.name,
            cantidad: item.cantidad || 0,
            unidad: m.unit || item.unidad || 'unidad',
            precio_unitario: item.precio_unitario || 0,
            subtotal: item.subtotal || (item.cantidad || 0) * (item.precio_unitario || 0),
            empaque_detalle: item.empaque_detalle || null,
            notas_descuento: item.notas_descuento || null,
            categoria_sugerida: item.categoria_sugerida || null,
            match_score: sug.score,
            match_name: m.name,
          };
        }
        return {
          ingrediente_id: null, product_id: null, item_type: 'ingredient' as const,
          nombre: item.nombre || '', cantidad: item.cantidad || 0,
          unidad: item.unidad || 'unidad', precio_unitario: item.precio_unitario || 0,
          subtotal: item.subtotal || (item.cantidad || 0) * (item.precio_unitario || 0),
          empaque_detalle: item.empaque_detalle || null,
          notas_descuento: item.notas_descuento || null,
          categoria_sugerida: item.categoria_sugerida || null,
          match_score: sug?.score, match_name: sug?.match?.name,
        };
      });

      // Determine proveedor
      const provSug = img.sugerencias?.proveedor;
      const proveedor = provSug?.nombre_original || img.extraction?.proveedor || 'Proveedor desconocido';
      const metodoPago = img.extraction?.metodo_pago || 'cash';
      const tipoCompra = img.extraction?.tipo_compra || 'ingredientes';

      // Group by proveedor
      const existing = newGroups.find(g => g.proveedor.toLowerCase() === proveedor.toLowerCase());
      if (existing) {
        existing.images.push(img);
        existing.items.push(...items);
      } else {
        newGroups.push({
          proveedor, fecha_compra: img.extraction?.fecha || new Date().toISOString().split('T')[0],
          metodo_pago: metodoPago, tipo_compra: tipoCompra, notas: '',
          images: [img], items, expanded: true,
        });
      }
    }

    setGroups(newGroups);
    setSubmitted([]);
    setUploading(false);
    setUploadProgress(null);
  }, [groups, submitted]);

  // Handle pipeline SSE result (single photo)
  const handlePipelineResult = useCallback((data: ExtractionResult, sugerencias?: ExtractionResult['sugerencias']) => {
    const tempKey = pipelineTempKey!;
    const tempUrl = pipelineTempUrl!;
    const img: UploadedImage = {
      tempKey, tempUrl, status: 'extracted',
      extraction: { ...data, confianza: data.confianza, sugerencias: sugerencias ?? data.sugerencias },
      sugerencias: sugerencias ?? data.sugerencias,
    };

    const extractedItems = data.items || [];
    const sugItems = (sugerencias?.items || data.sugerencias?.items || []) as ItemSugerencia[];
    const items: CompraItem[] = extractedItems.map((item, idx) => {
      const sug = sugItems[idx];
      const matched = sug?.pre_selected && sug?.match;
      if (matched && sug.match) {
        const m = sug.match;
        const isIng = sug.match_type === 'ingredient';
        return {
          ingrediente_id: isIng ? m.id : null,
          product_id: !isIng ? m.id : null,
          item_type: (sug.match_type || 'ingredient') as 'ingredient' | 'product',
          nombre: m.name, cantidad: item.cantidad || 0,
          unidad: m.unit || item.unidad || 'unidad',
          precio_unitario: item.precio_unitario || 0,
          subtotal: item.subtotal || (item.cantidad || 0) * (item.precio_unitario || 0),
          empaque_detalle: item.empaque_detalle || null,
          notas_descuento: item.notas_descuento || null,
          categoria_sugerida: item.categoria_sugerida || null,
          match_score: sug.score, match_name: m.name,
        };
      }
      return {
        ingrediente_id: null, product_id: null, item_type: 'ingredient' as const,
        nombre: item.nombre || '', cantidad: item.cantidad || 0,
        unidad: item.unidad || 'unidad', precio_unitario: item.precio_unitario || 0,
        subtotal: item.subtotal || (item.cantidad || 0) * (item.precio_unitario || 0),
        empaque_detalle: item.empaque_detalle || null,
        notas_descuento: item.notas_descuento || null,
        categoria_sugerida: item.categoria_sugerida || null,
        match_score: sug?.score, match_name: sug?.match?.name,
      };
    });

    const provSug = sugerencias?.proveedor || data.sugerencias?.proveedor;
    const proveedor = (provSug as any)?.nombre_original || data.proveedor || 'Proveedor desconocido';
    const metodoPago = data.metodo_pago || 'cash';
    const tipoCompra = data.tipo_compra || 'ingredientes';

    const newGroups = [...groups.filter((_, i) => !submitted.includes(i))];
    newGroups.push({
      proveedor, fecha_compra: data.fecha || new Date().toISOString().split('T')[0],
      metodo_pago: metodoPago, tipo_compra: tipoCompra, notas: '',
      images: [img], items, expanded: true,
    });
    setGroups(newGroups);
    setSubmitted([]);
    setPipelineTempKey(null);
    setPipelineTempUrl(null);
  }, [pipelineTempKey, pipelineTempUrl, groups, submitted]);

  const handlePipelineError = useCallback(() => {
    if (pipelineTempKey && pipelineTempUrl) {
      const img: UploadedImage = { tempKey: pipelineTempKey, tempUrl: pipelineTempUrl, status: 'error', error: 'Error de extracción' };
      const newGroups = [...groups.filter((_, i) => !submitted.includes(i))];
      newGroups.push({
        proveedor: '', fecha_compra: new Date().toISOString().split('T')[0],
        metodo_pago: 'cash', tipo_compra: 'ingredientes', notas: '',
        images: [img], items: [], expanded: true,
      });
      setGroups(newGroups);
      setSubmitted([]);
    }
    setPipelineTempKey(null);
    setPipelineTempUrl(null);
  }, [pipelineTempKey, pipelineTempUrl, groups, submitted]);

  // Handle reconciliation questions from multi-agent pipeline
  const handleReconciliationNeeded = useCallback((questions: ReconciliationQuestion[]) => {
    setReconciliationQuestions(questions);
  }, []);

  // Submit reconciliation responses to backend and apply corrections
  const handleReconciliationSubmit = useCallback(async (responses: Record<string, string | number>) => {
    if (!pipelineTempKey) return;
    setReconciliationLoading(true);
    try {
      const res = await comprasApi.post<{
        success: boolean;
        correcciones: Record<string, unknown>;
      }>('/compras/reconciliar', {
        temp_key: pipelineTempKey,
        respuestas: responses,
      });
      if (res.success && res.correcciones) {
        // Apply corrections to the last group added
        setGroups(prev => {
          const updated = [...prev];
          const lastIdx = updated.length - 1;
          if (lastIdx < 0) return prev;
          const group = { ...updated[lastIdx] };
          const corr = res.correcciones;
          if (corr.proveedor) group.proveedor = corr.proveedor as string;
          if (corr.fecha_compra) group.fecha_compra = corr.fecha_compra as string;
          if (corr.metodo_pago) group.metodo_pago = corr.metodo_pago as string;
          if (corr.items && Array.isArray(corr.items)) {
            group.items = (corr.items as CompraItem[]).map(item => ({
              ...item,
              subtotal: item.subtotal || (item.cantidad || 0) * (item.precio_unitario || 0),
            }));
          }
          updated[lastIdx] = group;
          return updated;
        });
      }
    } catch {
      // silently fail — user can still edit manually
    } finally {
      setReconciliationLoading(false);
      setReconciliationQuestions([]);
    }
  }, [pipelineTempKey]);

  // Add manual group (no photo)
  const addManualGroup = () => {
    setGroups(prev => [...prev, {
      proveedor: '', fecha_compra: new Date().toISOString().split('T')[0],
      metodo_pago: 'cash', tipo_compra: 'ingredientes', notas: '',
      images: [], items: [], expanded: true,
    }]);
  };

  // Add item from search to a group
  const addItemToGroup = (gi: number, item: { id: number; name: string; unit: string; type: 'ingredient' | 'product'; cost_per_unit: number | null }) => {
    setGroups(prev => prev.map((g, i) => {
      if (i !== gi) return g;
      const newItem: CompraItem = {
        ingrediente_id: item.type === 'ingredient' ? item.id : null,
        product_id: item.type === 'product' ? item.id : null,
        item_type: item.type, nombre: item.name, cantidad: 1,
        unidad: item.unit, precio_unitario: item.cost_per_unit ?? 0,
        subtotal: item.cost_per_unit ?? 0, match_score: 100, match_name: item.name,
      };
      return { ...g, items: [...g.items, newItem] };
    }));
  };

  const updateGroup = (idx: number, updates: Partial<CompraGroup>) => {
    setGroups(prev => prev.map((g, i) => i === idx ? { ...g, ...updates } : g));
  };

  const updateItem = (gi: number, ii: number, field: string, value: any) => {
    setGroups(prev => prev.map((g, gIdx) => {
      if (gIdx !== gi) return g;
      const items = [...g.items];
      items[ii] = { ...items[ii], [field]: value };
      if (field === 'cantidad' || field === 'precio_unitario') {
        items[ii].subtotal = (Number(items[ii].cantidad) || 0) * (Number(items[ii].precio_unitario) || 0);
      }
      return { ...g, items };
    }));
  };

  const removeItem = (gi: number, ii: number) => {
    setGroups(prev => prev.map((g, i) => i !== gi ? g : { ...g, items: g.items.filter((_, j) => j !== ii) }));
  };

  const removeGroup = (idx: number) => {
    setGroups(prev => prev.filter((_, i) => i !== idx));
  };

  const groupTotal = (g: CompraGroup) => g.items.reduce((s, i) => s + (i.subtotal || 0), 0);

  const submitGroup = async (idx: number) => {
    const group = groups[idx];
    if (!group.proveedor || group.items.length === 0) return;
    setSubmitting(true);
    try {
      await comprasApi.post('/compras', {
        proveedor: group.proveedor,
        fecha_compra: group.fecha_compra,
        tipo_compra: group.tipo_compra,
        metodo_pago: group.metodo_pago,
        monto_total: groupTotal(group),
        notas: group.notas || (group.images.length > 0 ? `${group.images.length} foto(s)` : ''),
        items: group.items.map(i => ({
          nombre_item: i.nombre, cantidad: i.cantidad, unidad: i.unidad,
          precio_unitario: i.precio_unitario, subtotal: i.subtotal,
          item_type: i.item_type, ingrediente_id: i.ingrediente_id, product_id: i.product_id,
        })),
        temp_keys: group.images.map(i => i.tempKey),
        usuario: 'Admin',
      });
      setSubmitted(prev => [...prev, idx]);
      refreshAll();
    } catch { alert('Error al registrar compra'); }
    setSubmitting(false);
  };

  const submitAll = async () => {
    for (let i = 0; i < groups.length; i++) {
      if (!submitted.includes(i) && groups[i].items.length > 0 && groups[i].proveedor) await submitGroup(i);
    }
  };

  const pendingGroups = groups.filter((_, i) => !submitted.includes(i));
  const grandTotal = pendingGroups.reduce((s, g) => s + groupTotal(g), 0);

  const isProveedorNeto = (prov: string) => {
    const lower = prov.toLowerCase();
    return ['vanni', 'arauco'].some(n => lower.includes(n));
  };

  return (
    <div className="space-y-4">
      {/* Saldo */}
      {saldo !== null && (
        <div className="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2 text-sm">
          <span className="text-gray-500">Saldo disponible</span>
          <span className={`font-bold ${saldo < 0 ? 'text-red-600' : 'text-green-700'}`}>{formatearPesosCLP(saldo)}</span>
        </div>
      )}

      {/* Drop zone — always visible */}
      <label
        htmlFor="compras-file-input"
        onDragOver={e => e.preventDefault()}
        onDrop={e => { e.preventDefault(); if (e.dataTransfer.files.length) processFiles(e.dataTransfer.files); }}
        className="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 p-6 text-center hover:border-mi3-400 hover:bg-mi3-50/30 transition-colors"
      >
        {uploading ? (
          <><Loader2 className="h-6 w-6 animate-spin text-mi3-500" /><p className="text-sm text-mi3-600">{uploadProgress || 'Subiendo foto...'}</p></>
        ) : (
          <><Upload className="h-6 w-6 text-gray-400" />
          <p className="text-sm text-gray-600">Sube 1 o más fotos de boletas/facturas</p>
          <p className="text-xs text-gray-400">La IA extrae datos y agrupa por proveedor</p></>
        )}
        <input id="compras-file-input" ref={inputRef} type="file" accept="image/*" multiple className="hidden"
          onChange={e => {
            console.log('[Compras] onChange fired, files:', e.target.files?.length);
            const files = e.target.files;
            if (files && files.length > 0) {
              // Copy files to array BEFORE resetting input (FileList is live, reset empties it)
              const fileArray = Array.from(files);
              e.target.value = '';
              console.log('[Compras] scheduling processFiles, copied', fileArray.length, 'files');
              setTimeout(() => processFiles(fileArray), 0);
            } else {
              console.warn('[Compras] onChange: no files selected');
            }
          }} />
      </label>

      {/* Pipeline visual SSE (single photo) */}
      {(() => { console.log('[Compras] render check: pipelineTempKey=', pipelineTempKey); return null; })()}
      {pipelineTempKey && (
        <div className="space-y-3">
          {pipelineTempUrl && (
            <div className="flex items-center gap-3 rounded-lg bg-gray-50 p-2">
              <img src={pipelineTempUrl} alt="" className="h-16 w-16 rounded-lg object-cover border" />
              <p className="text-sm text-gray-600">Analizando imagen...</p>
            </div>
          )}
          <ExtractionPipeline
            tempKey={pipelineTempKey}
            onResult={handlePipelineResult}
            onError={handlePipelineError}
            onReconciliationNeeded={handleReconciliationNeeded}
          />
          {reconciliationQuestions.length > 0 && (
            <ReconciliationQuestions
              questions={reconciliationQuestions}
              onSubmit={handleReconciliationSubmit}
              loading={reconciliationLoading}
            />
          )}
        </div>
      )}

      {/* Manual entry button */}
      {groups.length === 0 && (
        <button onClick={addManualGroup}
          className="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 py-2.5 text-sm text-gray-600 hover:bg-gray-50">
          <FileText className="h-4 w-4" /> Ingresar manualmente sin foto
        </button>
      )}

      {/* Groups */}
      {groups.map((group, gi) => {
        const isSubmitted = submitted.includes(gi);
        const total = groupTotal(group);
        return (
          <div key={gi} className={`rounded-xl border shadow-sm ${isSubmitted ? 'bg-green-50 border-green-200' : 'bg-white'}`}>
            {/* Header */}
            <div className="flex items-center gap-2 px-4 py-3 border-b cursor-pointer" onClick={() => updateGroup(gi, { expanded: !group.expanded })}>
              {group.expanded ? <ChevronUp className="h-4 w-4 text-gray-400" /> : <ChevronDown className="h-4 w-4 text-gray-400" />}
              {isSubmitted && <Check className="h-4 w-4 text-green-600" />}
              <span className="text-sm font-semibold text-gray-900 flex-1">{group.proveedor || 'Sin proveedor'}</span>
              <span className="text-sm font-bold">{formatearPesosCLP(total)}</span>
              <span className="text-xs text-gray-400">{group.items.length} items</span>
              {!isSubmitted && (
                <button onClick={e => { e.stopPropagation(); removeGroup(gi); }} className="text-red-400 hover:text-red-600">
                  <Trash2 className="h-4 w-4" />
                </button>
              )}
            </div>

            {/* Body */}
            {group.expanded && !isSubmitted && (
              <div className="p-4 space-y-3">
                {/* Thumbnails — click to preview */}
                {group.images.length > 0 && (
                  <div className="space-y-2">
                    <div className="flex gap-2 overflow-x-auto">
                      {group.images.map((img, ii) => (
                        <button key={ii} onClick={() => setPreviewImg(img.tempUrl)}
                          className="relative h-14 w-14 flex-shrink-0 rounded-lg border overflow-hidden hover:ring-2 hover:ring-mi3-400">
                          <img src={img.tempUrl} alt="" className="h-full w-full object-cover" />
                          {img.status === 'extracting' && <div className="absolute inset-0 flex items-center justify-center bg-black/40"><Loader2 className="h-3 w-3 animate-spin text-white" /></div>}
                          {img.status === 'error' && <div className="absolute inset-0 flex items-center justify-center bg-red-500/40"><X className="h-3 w-3 text-white" /></div>}
                          {img.status === 'extracted' && <div className="absolute bottom-0 right-0 rounded-tl bg-green-500 p-0.5"><Check className="h-2.5 w-2.5 text-white" /></div>}
                        </button>
                      ))}
                    </div>
                    {/* IA feedback */}
                    {group.images.some(img => img.extraction?.notas_ia) && (
                      <div className="rounded-lg bg-blue-50 border border-blue-200 px-3 py-2">
                        <p className="text-xs text-blue-700">
                          💡 {group.images.filter(img => img.extraction?.notas_ia).map(img => img.extraction?.notas_ia).join(' · ')}
                        </p>
                      </div>
                    )}
                    {group.images.some(img => img.status === 'error') && (
                      <div className="rounded-lg bg-red-50 border border-red-200 px-3 py-2">
                        <p className="text-xs text-red-700">
                          ⚠️ {group.images.filter(img => img.error).map(img => img.error).join(' · ')} — Puedes ingresar los datos manualmente
                        </p>
                      </div>
                    )}
                  </div>
                )}

                {/* Fields row */}
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                  <div>
                    <label className="text-xs text-gray-500">Proveedor</label>
                    <ProveedorSearch value={group.proveedor} onChange={v => updateGroup(gi, { proveedor: v })} />
                  </div>
                  <div>
                    <label className="text-xs text-gray-500">Fecha</label>
                    <input type="date" value={group.fecha_compra} onChange={e => updateGroup(gi, { fecha_compra: e.target.value })}
                      className="w-full rounded border px-2 py-1.5 text-base" />
                  </div>
                  <div>
                    <label className="text-xs text-gray-500">Pago</label>
                    <select value={group.metodo_pago} onChange={e => updateGroup(gi, { metodo_pago: e.target.value })}
                      className="w-full rounded border px-2 py-1.5 text-base">
                      {METODOS_PAGO.map(m => <option key={m.value} value={m.value}>{m.label}</option>)}
                    </select>
                  </div>
                  <div>
                    <label className="text-xs text-gray-500">Notas</label>
                    <input type="text" value={group.notas} onChange={e => updateGroup(gi, { notas: e.target.value })}
                      className="w-full rounded border px-2 py-1.5 text-base" placeholder="Opcional" />
                  </div>
                </div>

                {/* Search to add items */}
                <InlineItemSearch onSelect={item => addItemToGroup(gi, item)} />

                {/* Items */}
                {group.items.length > 0 && (
                  <div className="space-y-1">
                    {group.items.map((item, ii) => (
                      <div key={ii} className="rounded-lg border bg-gray-50 px-3 py-2 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                          <input type="text" value={item.nombre} onChange={e => updateItem(gi, ii, 'nombre', e.target.value)}
                            className="min-w-[100px] flex-1 rounded border px-2 py-1 text-[16px] font-medium" />
                          <input type="number" value={item.cantidad || ''} step="any" placeholder="Cant."
                            onChange={e => updateItem(gi, ii, 'cantidad', e.target.value === '' ? 0 : parseFloat(e.target.value))}
                            onFocus={e => { if (e.target.value === '0') e.target.value = ''; }}
                            className="w-16 rounded border px-2 py-1 text-[16px] text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                          <select value={item.unidad} onChange={e => updateItem(gi, ii, 'unidad', e.target.value)}
                            className="w-20 rounded border px-1 py-1 text-xs text-gray-600">
                            <option value="kg">kg</option>
                            <option value="unidad">unidad</option>
                            <option value="litro">litro</option>
                            <option value="g">g</option>
                            <option value="ml">ml</option>
                          </select>
                          <div className="relative w-24">
                            <span className="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[14px]">$</span>
                            <input type="number" value={item.precio_unitario || ''} step="any" placeholder="Precio"
                              onChange={e => updateItem(gi, ii, 'precio_unitario', e.target.value === '' ? 0 : parseFloat(e.target.value))}
                              onFocus={e => { if (e.target.value === '0') e.target.value = ''; }}
                              className="w-full rounded border pl-5 pr-2 py-1 text-[16px] text-right [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                          </div>
                          <div className="w-20 text-right">
                            <span className="text-[10px] text-gray-400 block leading-none">Total</span>
                            <span className="text-xs font-medium">{formatearPesosCLP(item.subtotal)}</span>
                          </div>
                          <button onClick={() => removeItem(gi, ii)} className="text-red-400 hover:text-red-600"><X className="h-3.5 w-3.5" /></button>
                        </div>
                        {/* Match + empaque info */}
                        <div className="flex flex-wrap gap-2 mt-1">
                          {item.empaque_detalle && <span className="text-xs text-blue-600">📦 {item.empaque_detalle}</span>}
                          {item.notas_descuento && <span className="text-xs text-orange-600">🏷️ {item.notas_descuento}</span>}
                          {item.ingrediente_id && (
                            <span className="text-xs text-green-600">
                              ✅ {item.match_name} ({Math.round(item.match_score || 0)}%)
                              {(item.match_score || 0) === 100 && !item.product_id && (
                                <span className="ml-1 inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold text-green-700">🆕 Nuevo</span>
                              )}
                            </span>
                          )}
                          {!item.ingrediente_id && !item.product_id && item.match_name && (item.match_score || 0) >= 75 && (
                            <span className="text-xs text-amber-600">⚠️ {item.match_name} ({Math.round(item.match_score || 0)}%)</span>
                          )}
                          {!item.ingrediente_id && !item.product_id && (!(item.match_score) || (item.match_score || 0) < 75) && (
                            <>
                              {item.match_name && <span className="text-xs text-gray-400">🤔 {item.match_name} ({Math.round(item.match_score || 0)}%) — no parece correcto</span>}
                              <button
                                type="button"
                                onClick={async () => {
                                try {
                                  const res = await comprasApi.post<{ success: boolean; ingrediente: { id: number; name: string; unit: string; cost_per_unit: number } }>('/compras/ingrediente', {
                                    name: item.nombre,
                                    category: item.categoria_sugerida || null,
                                    unit: item.unidad,
                                    cost_per_unit: item.precio_unitario || 0,
                                    supplier: group.proveedor || null,
                                  });
                                  if (res.success && res.ingrediente) {
                                    updateItem(gi, ii, 'ingrediente_id', res.ingrediente.id);
                                    updateItem(gi, ii, 'match_name', res.ingrediente.name);
                                    updateItem(gi, ii, 'match_score', 100);
                                    updateItem(gi, ii, 'item_type', 'ingredient');
                                  }
                                } catch { /* silently fail, user can retry */ }
                              }}
                              className="inline-flex items-center gap-1 rounded-md bg-blue-50 border border-blue-200 px-2 py-0.5 text-xs text-blue-700 hover:bg-blue-100 transition-colors"
                            >
                              <Sparkles className="h-3 w-3" />
                              Crear &quot;{item.nombre}&quot; &gt; {
                                { ingredientes: 'ingredientes', insumos: 'insumos', equipamiento: 'equipamiento', otros: 'otros' }[group.tipo_compra as string] || 'insumos'
                              }
                              {item.categoria_sugerida && <span className="text-blue-500"> &gt; {item.categoria_sugerida}</span>}
                            </button>
                            </>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {/* Submit group */}
                {isProveedorNeto(group.proveedor) && total > 0 && (
                  <div className="flex items-center justify-between rounded-lg bg-blue-50 border border-blue-200 px-3 py-2 text-sm">
                    <span className="text-blue-700">Neto: {formatearPesosCLP(total)}</span>
                    <span className="font-bold text-blue-900">Con IVA: {formatearPesosCLP(Math.round(total * 1.19))}</span>
                  </div>
                )}
                <button onClick={() => submitGroup(gi)} disabled={submitting || !group.proveedor || group.items.length === 0}
                  className="w-full rounded-lg bg-mi3-500 py-2.5 text-sm font-medium text-white hover:bg-mi3-600 disabled:opacity-50">
                  {submitting ? 'Registrando...' : `Registrar — ${formatearPesosCLP(isProveedorNeto(group.proveedor) ? Math.round(total * 1.19) : total)}`}
                </button>
              </div>
            )}

            {isSubmitted && (
              <div className="px-4 py-3 text-sm text-green-700 flex items-center gap-2">
                <Check className="h-4 w-4" /> Compra registrada
              </div>
            )}
          </div>
        );
      })}

      {/* Submit all + add more */}
      {groups.length > 0 && (
        <div className="space-y-2">
          <button onClick={addManualGroup}
            className="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 py-2 text-sm text-gray-500 hover:bg-gray-50">
            + Agregar compra manual
          </button>
          {pendingGroups.length > 1 && (
            <button onClick={submitAll} disabled={submitting}
              className="w-full rounded-xl bg-green-600 py-3 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50">
              {submitting ? 'Registrando...' : `Registrar todas (${pendingGroups.length}) — ${formatearPesosCLP(grandTotal)}`}
            </button>
          )}
          {saldo !== null && grandTotal > saldo && (
            <div className="flex items-center gap-2 rounded-lg bg-amber-50 p-2 text-sm text-amber-700">
              <AlertTriangle className="h-4 w-4 flex-shrink-0" /> El total supera el saldo disponible
            </div>
          )}
        </div>
      )}

      {/* Photo preview modal */}
      {previewImg && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" onClick={() => setPreviewImg(null)}>
          <div className="relative max-h-[90vh] max-w-[90vw]">
            <img src={previewImg} alt="Preview" className="max-h-[85vh] max-w-full rounded-lg object-contain" />
            <button onClick={() => setPreviewImg(null)}
              className="absolute -top-3 -right-3 rounded-full bg-white p-1.5 shadow-lg hover:bg-gray-100">
              <X className="h-5 w-5 text-gray-700" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
