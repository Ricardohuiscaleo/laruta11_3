'use client';

import { useState, useRef, useCallback } from 'react';
import { cn } from '@/lib/utils';
import { Image, Upload, Loader2 } from 'lucide-react';

interface ImageQuickDropProps {
  imageUrl: string | null;
  productName: string;
  onUpload: (file: File) => Promise<void>;
  size?: number;
}

export default function ImageQuickDrop({ imageUrl, productName, onUpload, size = 36 }: ImageQuickDropProps) {
  const [dragOver, setDragOver] = useState(false);
  const [uploading, setUploading] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const handleFile = useCallback(async (file: File) => {
    if (!file.type.match(/^image\/(jpeg|png|webp)$/)) return;
    if (file.size > 5 * 1024 * 1024) return;
    setUploading(true);
    try {
      await onUpload(file);
    } finally {
      setUploading(false);
    }
  }, [onUpload]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragOver(false);
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
  }, [handleFile]);

  return (
    <div className="relative flex-shrink-0">
      <div
        onDragOver={e => { e.preventDefault(); e.stopPropagation(); setDragOver(true); }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleDrop}
        onClick={() => inputRef.current?.click()}
        className={cn(
          'flex items-center justify-center rounded-md border-2 overflow-hidden cursor-pointer transition-colors',
          dragOver ? 'border-red-400 bg-red-50' : 'border-transparent hover:border-gray-300 bg-gray-100',
        )}
        style={{ width: size, height: size }}
        role="button"
        tabIndex={0}
        onKeyDown={e => e.key === 'Enter' && inputRef.current?.click()}
        aria-label={`Cambiar imagen de ${productName}`}
        title="Arrastra o click para cambiar imagen"
      >
        {uploading ? (
          <Loader2 className="w-4 h-4 animate-spin text-red-500" />
        ) : imageUrl ? (
          <img
            src={imageUrl}
            alt={productName}
            className="h-full w-full object-cover"
            onError={e => { (e.target as HTMLImageElement).style.display = 'none'; }}
          />
        ) : (
          <Image className="w-4 h-4 text-gray-400" />
        )}
      </div>
      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        onChange={e => { const f = e.target.files?.[0]; if (f) handleFile(f); e.target.value = ''; }}
        className="hidden"
        aria-label="Seleccionar imagen"
      />
    </div>
  );
}
