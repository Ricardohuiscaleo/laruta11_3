'use client';

import { useState, useRef, useCallback } from 'react';
import { Upload, X, Image as ImageIcon, ZoomIn, Sparkles, Loader2 } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import ExtractionPreview, { ExtractionError } from './ExtractionPreview';
import type { ExtractionResult } from '@/types/compras';

interface TempImage {
  tempKey: string;
  tempUrl: string;
  file?: File;
}

interface ImageUploaderProps {
  images: TempImage[];
  onChange: (images: TempImage[]) => void;
  onExtractionResult?: (data: ExtractionResult) => void;
}

export default function ImageUploader({ images, onChange, onExtractionResult }: ImageUploaderProps) {
  const [uploading, setUploading] = useState(false);
  const [extracting, setExtracting] = useState(false);
  const [extractionResult, setExtractionResult] = useState<ExtractionResult | null>(null);
  const [extractionError, setExtractionError] = useState(false);
  const [preview, setPreview] = useState<string | null>(null);
  const [dragOver, setDragOver] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const uploadFiles = useCallback(async (files: FileList | File[]) => {
    setUploading(true);
    const newImages: TempImage[] = [];
    for (const file of Array.from(files)) {
      try {
        const fd = new FormData();
        fd.append('image', file);
        const res = await comprasApi.upload<{ tempUrl: string; tempKey: string }>('/compras/upload-temp', fd);
        newImages.push({ tempKey: res.tempKey, tempUrl: res.tempUrl, file });
      } catch {
        // skip failed uploads
      }
    }
    onChange([...images, ...newImages]);
    setUploading(false);
  }, [images, onChange]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
    if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files);
  }, [uploadFiles]);

  const removeImage = (idx: number) => {
    onChange(images.filter((_, i) => i !== idx));
  };

  const handleExtract = useCallback(async () => {
    if (images.length === 0) return;
    setExtracting(true);
    setExtractionError(false);
    setExtractionResult(null);
    try {
      const res = await comprasApi.post<{
        success: boolean;
        data?: ExtractionResult;
        confianza?: ExtractionResult['confianza'];
        sugerencias?: ExtractionResult['sugerencias'];
        error?: string;
      }>('/compras/extract', { temp_key: images[0].tempKey });
      if (res.success && res.data) {
        const result: ExtractionResult = {
          ...res.data,
          confianza: res.confianza ?? res.data.confianza,
          sugerencias: res.sugerencias ?? res.data.sugerencias,
        };
        setExtractionResult(result);
      } else {
        setExtractionError(true);
      }
    } catch {
      setExtractionError(true);
    }
    setExtracting(false);
  }, [images]);

  const handleUseData = useCallback((data: ExtractionResult) => {
    onExtractionResult?.(data);
    setExtractionResult(null);
  }, [onExtractionResult]);

  return (
    <div>
      <div
        onDragOver={e => { e.preventDefault(); setDragOver(true); }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleDrop}
        onClick={() => inputRef.current?.click()}
        className={`flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed p-4 text-center transition-colors ${
          dragOver ? 'border-mi3-500 bg-mi3-50' : 'border-gray-300 hover:border-gray-400'
        }`}
      >
        <Upload className="h-6 w-6 text-gray-400" />
        <p className="text-sm text-gray-500">
          {uploading ? 'Subiendo...' : 'Arrastra imágenes o haz clic'}
        </p>
        <input
          ref={inputRef}
          type="file"
          accept="image/*"
          multiple
          className="hidden"
          onChange={e => e.target.files && uploadFiles(e.target.files)}
        />
      </div>

      {images.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-2">
          {images.map((img, idx) => (
            <div key={img.tempKey} className="group relative h-20 w-20 overflow-hidden rounded-lg border">
              <img src={img.tempUrl} alt="" className="h-full w-full object-cover" />
              <div className="absolute inset-0 flex items-center justify-center gap-1 bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                <button onClick={(e) => { e.stopPropagation(); setPreview(img.tempUrl); }} className="rounded-full bg-white/80 p-1">
                  <ZoomIn className="h-3.5 w-3.5 text-gray-700" />
                </button>
                <button onClick={(e) => { e.stopPropagation(); removeImage(idx); }} className="rounded-full bg-white/80 p-1">
                  <X className="h-3.5 w-3.5 text-red-600" />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Full-size preview modal */}
      {preview && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" onClick={() => setPreview(null)}>
          <div className="relative max-h-[90vh] max-w-[90vw]">
            <button onClick={() => setPreview(null)} className="absolute -right-2 -top-2 rounded-full bg-white p-1 shadow">
              <X className="h-5 w-5" />
            </button>
            <img src={preview} alt="" className="max-h-[85vh] rounded-lg object-contain" />
          </div>
        </div>
      )}

      {/* Extract button */}
      {images.length > 0 && onExtractionResult && !extractionResult && (
        <button
          onClick={handleExtract}
          disabled={extracting}
          className="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border border-mi3-300 bg-mi3-50 px-3 py-2 text-sm font-medium text-mi3-700 hover:bg-mi3-100 transition-colors disabled:opacity-50"
        >
          {extracting ? (
            <><Loader2 className="h-4 w-4 animate-spin" /> Extrayendo datos...</>
          ) : (
            <><Sparkles className="h-4 w-4" /> Extraer datos de la boleta</>
          )}
        </button>
      )}

      {/* Extraction result */}
      {extractionResult && (
        <div className="mt-3">
          <ExtractionPreview result={extractionResult} onUseData={handleUseData} />
        </div>
      )}

      {/* Extraction error */}
      {extractionError && (
        <div className="mt-3">
          <ExtractionError onManual={() => setExtractionError(false)} />
        </div>
      )}
    </div>
  );
}
