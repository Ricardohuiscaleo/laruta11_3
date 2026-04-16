import { useState, useEffect, useCallback } from 'react';
import { CheckCircle, Circle, Camera, ArrowLeft, CheckSquare, DollarSign, Loader2, X } from 'lucide-react';

const API_BASE = 'https://api-mi3.laruta11.cl/api/v1/public/checklists';

function detectDefaultTab() {
  const hour = new Date().getHours();
  return hour >= 21 || hour < 4 ? 'cierre' : 'apertura';
}

export default function ChecklistApp() {
  const [activeTab, setActiveTab] = useState(detectDefaultTab);
  const [checklists, setChecklists] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [actionLoading, setActionLoading] = useState({});
  const [cashInputs, setCashInputs] = useState({});
  const [fullscreenImage, setFullscreenImage] = useState(null);

  const checklist = checklists.find(c => c.type === activeTab) || null;

  const completedCount = checklist?.items?.filter(i => i.is_completed).length ?? 0;
  const totalCount = checklist?.items?.length ?? 0;
  const percentage = totalCount > 0 ? Math.round((completedCount / totalCount) * 100) : 0;

  const fetchChecklists = useCallback(async () => {
    try {
      setError(null);
      const res = await fetch(`${API_BASE}/today?rol=cajero`);
      if (!res.ok) throw new Error(`Error ${res.status}`);
      const data = await res.json();
      if (data.success) {
        setChecklists(data.data);
      } else {
        setError('No se pudieron cargar los checklists');
      }
    } catch (err) {
      setError('Error de conexión con mi3');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchChecklists();
    const interval = setInterval(fetchChecklists, 15000);
    return () => clearInterval(interval);
  }, [fetchChecklists]);

  const setItemLoading = (itemId, isLoading) => {
    setActionLoading(prev => ({ ...prev, [itemId]: isLoading }));
  };

  const handleCompleteItem = async (itemId) => {
    if (!checklist) return;
    setItemLoading(itemId, true);
    try {
      const res = await fetch(`${API_BASE}/${checklist.id}/items/${itemId}/complete`, { method: 'POST' });
      if (res.ok) await fetchChecklists();
    } catch { /* silent */ } finally {
      setItemLoading(itemId, false);
    }
  };

  const handleUploadPhoto = async (itemId, file) => {
    if (!checklist) return;
    setItemLoading(itemId, true);
    try {
      const compressed = await compressImage(file);
      const formData = new FormData();
      formData.append('photo', compressed, 'photo.jpg');

      const res = await fetch(`${API_BASE}/${checklist.id}/items/${itemId}/photo`, {
        method: 'POST',
        body: formData,
      });
      if (res.ok) await fetchChecklists();
    } catch { /* silent */ } finally {
      setItemLoading(itemId, false);
    }
  };

  const handleVerifyCash = async (itemId) => {
    if (!checklist) return;
    const amount = parseFloat(cashInputs[itemId]);
    if (isNaN(amount) || amount < 0) return;
    setItemLoading(itemId, true);
    try {
      const res = await fetch(`${API_BASE}/${checklist.id}/items/${itemId}/verify-cash`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ actual_amount: amount }),
      });
      if (res.ok) await fetchChecklists();
    } catch { /* silent */ } finally {
      setItemLoading(itemId, false);
    }
  };

  const handleCompleteChecklist = async () => {
    if (!checklist || !confirm('¿Completar checklist?')) return;
    setActionLoading(prev => ({ ...prev, complete: true }));
    try {
      const res = await fetch(`${API_BASE}/${checklist.id}/complete`, { method: 'POST' });
      const data = await res.json();
      if (data.success) {
        alert('✅ Checklist completado');
        await fetchChecklists();
      } else {
        alert(data.error || 'Error al completar');
      }
    } catch {
      alert('Error de conexión');
    } finally {
      setActionLoading(prev => ({ ...prev, complete: false }));
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <Loader2 size={32} className="animate-spin text-orange-500" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 p-4 text-center">
        <p className="text-red-500 font-semibold mb-4">{error}</p>
        <button
          onClick={() => { setLoading(true); fetchChecklists(); }}
          className="bg-orange-500 text-white px-6 py-2 rounded-lg font-bold"
        >
          Reintentar
        </button>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 pb-24">
      {/* Header */}
      <div className="bg-white shadow-sm sticky top-0 z-10 px-4 py-3">
        <div className="flex items-center gap-3">
          <button
            onClick={() => window.history.back()}
            className="p-2 hover:bg-gray-100 rounded-full"
            aria-label="Volver"
          >
            <ArrowLeft size={22} className="text-gray-700" />
          </button>
          <div className="flex-1">
            <div className="flex items-center gap-2 mb-1">
              <h1 className="text-lg font-bold flex items-center gap-2">
                <CheckSquare size={20} className="text-orange-500" />
                Checklist
              </h1>
              {checklist && checklist.status !== 'completed' && (
                <span className="text-sm text-gray-600">{completedCount}/{totalCount}</span>
              )}
            </div>
            {checklist && checklist.status !== 'completed' && (
              <div className="w-full bg-gray-100 rounded-full h-2" role="progressbar" aria-valuenow={percentage} aria-valuemin={0} aria-valuemax={100}>
                <div
                  className="bg-green-500 h-2 rounded-full transition-all duration-500"
                  style={{ width: `${percentage}%` }}
                />
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="bg-white border-b sticky top-[60px] z-10">
        <div className="flex" role="tablist" aria-label="Tipo de checklist">
          <button
            role="tab"
            aria-selected={activeTab === 'apertura'}
            onClick={() => setActiveTab('apertura')}
            className={`flex-1 py-3 text-sm font-bold border-b-2 transition-colors ${
              activeTab === 'apertura' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500'
            }`}
          >
            ☀️ Apertura
          </button>
          <button
            role="tab"
            aria-selected={activeTab === 'cierre'}
            onClick={() => setActiveTab('cierre')}
            className={`flex-1 py-3 text-sm font-bold border-b-2 transition-colors ${
              activeTab === 'cierre' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'
            }`}
          >
            🌙 Cierre
          </button>
        </div>
      </div>

      {/* Content */}
      <div className="max-w-lg mx-auto px-4 py-6" role="tabpanel">
        {!checklist ? (
          <div className="flex flex-col items-center justify-center py-16 text-gray-500">
            <div className="text-6xl mb-4">{activeTab === 'apertura' ? '☀️' : '🌙'}</div>
            <p className="text-lg font-semibold">No hay checklist disponible</p>
            <p className="text-sm mt-2">para {activeTab === 'apertura' ? 'apertura' : 'cierre'}</p>
          </div>
        ) : checklist.status === 'completed' ? (
          <div className="flex flex-col items-center justify-center py-16">
            <CheckCircle size={80} className="text-green-500 mb-4" />
            <h2 className="text-2xl font-bold text-gray-800 mb-2">
              {activeTab === 'apertura' ? '☀️ Apertura' : '🌙 Cierre'} Completado
            </h2>
            <p className="text-sm text-gray-500 mt-2">
              {totalCount}/{totalCount} tareas • 100%
            </p>
          </div>
        ) : (
          <div className="space-y-3">
            {checklist.items?.map((item) => (
              <ChecklistItemCard
                key={item.id}
                item={item}
                isLoading={!!actionLoading[item.id]}
                cashValue={cashInputs[item.id] || ''}
                onCashChange={(val) => setCashInputs(prev => ({ ...prev, [item.id]: val }))}
                onToggle={() => handleCompleteItem(item.id)}
                onUploadPhoto={(file) => handleUploadPhoto(item.id, file)}
                onVerifyCash={() => handleVerifyCash(item.id)}
                onImageClick={setFullscreenImage}
              />
            ))}

            <button
              onClick={handleCompleteChecklist}
              disabled={!!actionLoading.complete}
              className="w-full bg-orange-500 text-white py-4 rounded-xl font-black uppercase tracking-widest mt-8 hover:bg-orange-600 shadow-xl active:scale-[0.98] transition-all disabled:opacity-50"
            >
              {actionLoading.complete ? 'Completando...' : 'Completar Checklist'}
            </button>
          </div>
        )}
      </div>

      {/* Fullscreen image modal */}
      {fullscreenImage && (
        <div
          className="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4"
          onClick={() => setFullscreenImage(null)}
          role="dialog"
          aria-label="Imagen ampliada"
        >
          <button
            className="absolute top-4 right-4 text-white p-2 hover:bg-white/20 rounded-full"
            onClick={() => setFullscreenImage(null)}
            aria-label="Cerrar imagen"
          >
            <X size={32} />
          </button>
          <img
            src={fullscreenImage}
            alt="Evidencia"
            className="max-w-full max-h-full object-contain"
            onClick={(e) => e.stopPropagation()}
          />
        </div>
      )}
    </div>
  );
}

function ChecklistItemCard({ item, isLoading, cashValue, onCashChange, onToggle, onUploadPhoto, onVerifyCash, onImageClick }) {
  const isCash = item.item_type === 'cash_verification';
  const isPhoto = !!item.requires_photo;

  return (
    <div className="bg-white rounded-2xl shadow-sm p-4 border border-gray-100">
      <div className="flex items-start gap-3">
        {/* Checkbox / status */}
        {!isCash && (
          <button
            onClick={onToggle}
            disabled={isLoading || (isPhoto && !item.photo_url && !item.is_completed)}
            className="flex-shrink-0 mt-1 disabled:opacity-40"
            aria-label={item.is_completed ? 'Desmarcar tarea' : 'Completar tarea'}
          >
            {isLoading ? (
              <Loader2 size={28} className="animate-spin text-orange-400" />
            ) : item.is_completed ? (
              <CheckCircle size={28} className="text-green-500" />
            ) : (
              <Circle size={28} className="text-gray-300" />
            )}
          </button>
        )}

        {isCash && (
          <div className="flex-shrink-0 mt-1">
            {item.is_completed ? (
              <CheckCircle size={28} className="text-green-500" />
            ) : (
              <DollarSign size={28} className="text-yellow-500" />
            )}
          </div>
        )}

        <div className="flex-1 min-w-0">
          <p className={`text-sm font-bold ${item.is_completed ? 'line-through text-gray-400' : 'text-gray-800'}`}>
            {item.description}
          </p>

          {/* Cash verification UI */}
          {isCash && !item.is_completed && (
            <div className="mt-3 space-y-2">
              {item.cash_expected != null && (
                <p className="text-xs text-gray-500">
                  Monto esperado: <span className="font-bold text-gray-700">${Number(item.cash_expected).toLocaleString('es-CL')}</span>
                </p>
              )}
              <div className="flex gap-2">
                <input
                  type="number"
                  inputMode="numeric"
                  placeholder="Monto real"
                  value={cashValue}
                  onChange={(e) => onCashChange(e.target.value)}
                  className="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent outline-none"
                  aria-label="Monto real en caja"
                />
                <button
                  onClick={onVerifyCash}
                  disabled={isLoading || !cashValue}
                  className="bg-yellow-500 text-white px-4 py-2 rounded-lg text-sm font-bold disabled:opacity-50"
                >
                  {isLoading ? '...' : 'Verificar'}
                </button>
              </div>
            </div>
          )}

          {isCash && item.is_completed && item.cash_result && (
            <p className={`text-xs mt-1 font-semibold ${item.cash_result === 'ok' ? 'text-green-600' : 'text-red-600'}`}>
              {item.cash_result === 'ok' ? '✅ Caja cuadrada' : `⚠️ Diferencia: $${Number(item.cash_difference || 0).toLocaleString('es-CL')}`}
            </p>
          )}

          {/* Photo upload UI */}
          {isPhoto && !isCash && (
            <div className="mt-3">
              {item.photo_url ? (
                <div className="relative rounded-xl overflow-hidden shadow-md">
                  <img
                    src={item.photo_url}
                    alt="Evidencia fotográfica"
                    className="w-full h-40 object-cover cursor-pointer"
                    onClick={() => onImageClick(item.photo_url)}
                  />
                  {item.ai_score != null && (
                    <div className={`absolute bottom-0 left-0 right-0 px-3 py-1.5 text-xs font-bold ${
                      item.ai_score >= 7 ? 'bg-green-600/90' : item.ai_score >= 4 ? 'bg-yellow-500/90' : 'bg-red-500/90'
                    } text-white`}>
                      IA: {item.ai_score}/10 — {item.ai_observations || ''}
                    </div>
                  )}
                </div>
              ) : (
                <label className="flex items-center justify-center gap-2 bg-orange-50 text-orange-600 px-4 py-3 rounded-xl cursor-pointer hover:bg-orange-100 transition-all border border-orange-100">
                  {isLoading ? (
                    <Loader2 size={20} className="animate-spin" />
                  ) : (
                    <Camera size={20} />
                  )}
                  <span className="font-bold text-sm">{isLoading ? 'Subiendo...' : 'Subir Foto'}</span>
                  <input
                    type="file"
                    accept="image/*"
                    capture="environment"
                    className="hidden"
                    onChange={(e) => e.target.files[0] && onUploadPhoto(e.target.files[0])}
                    disabled={isLoading}
                  />
                </label>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function compressImage(file) {
  return new Promise((resolve) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement('canvas');
        const MAX_WIDTH = 800;
        const scale = Math.min(1, MAX_WIDTH / img.width);
        canvas.width = img.width * scale;
        canvas.height = img.height * scale;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.8);
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
}
