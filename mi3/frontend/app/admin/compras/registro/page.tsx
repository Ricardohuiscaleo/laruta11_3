'use client';

import { useState } from 'react';
import { FileText, Upload } from 'lucide-react';
import RegistroCompra from '@/components/admin/compras/RegistroCompra';
import SubidaMasiva from '@/components/admin/compras/SubidaMasiva';

export default function RegistroPage() {
  const [mode, setMode] = useState<'single' | 'masiva'>('single');

  return (
    <div className="space-y-3">
      {/* Mode toggle */}
      <div className="flex gap-1 rounded-lg bg-gray-100 p-1">
        <button onClick={() => setMode('single')}
          className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
            mode === 'single' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'
          }`}>
          <FileText className="h-4 w-4" /> Una compra
        </button>
        <button onClick={() => setMode('masiva')}
          className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
            mode === 'masiva' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'
          }`}>
          <Upload className="h-4 w-4" /> Subida masiva
        </button>
      </div>

      {mode === 'single' ? <RegistroCompra /> : <SubidaMasiva />}
    </div>
  );
}
