import { useState, useEffect } from 'react';
import { CheckCircle, Circle, Camera, ArrowLeft, CheckSquare, Timer, ChevronDown, ChevronUp, X } from 'lucide-react';

const API_URL = '/api/checklist.php';

export default function ChecklistApp() {
  const [activeTab, setActiveTab] = useState('apertura');
  const [checklist, setChecklist] = useState(null);
  const [loading, setLoading] = useState(true);
  const [timeRemaining, setTimeRemaining] = useState(0);
  const [currentDate, setCurrentDate] = useState(new Date());
  const [itemNotes, setItemNotes] = useState({});

  const getTurnoInfo = () => {
    const today = new Date(currentDate);
    const tomorrow = new Date(currentDate);
    tomorrow.setDate(tomorrow.getDate() + 1);
    return {
      today: today.getDate(),
      tomorrow: tomorrow.getDate(),
      month: today.toLocaleDateString('es-CL', { month: 'long' }),
      year: today.getFullYear()
    };
  };

  const navigateDate = (days) => {
    const newDate = new Date(currentDate);
    newDate.setDate(newDate.getDate() + days);
    setCurrentDate(newDate);
  };

  useEffect(() => {
    loadChecklist();
    const interval = setInterval(loadChecklist, 5000);
    return () => clearInterval(interval);
  }, [activeTab, currentDate]);

  useEffect(() => {
    if (!checklist) return;
    const timer = setInterval(() => {
      setTimeRemaining(prev => Math.max(0, prev - 1));
    }, 60000);
    return () => clearInterval(timer);
  }, [checklist]);

  const loadChecklist = async () => {
    try {
      const dateParam = currentDate.toISOString().split('T')[0];
      const res = await fetch(`${API_URL}?action=get_active&type=${activeTab}&date=${dateParam}&t=${Date.now()}`);
      const data = await res.json();
      if (data.success) {
        // Verificar que el checklist cargado sea del tipo correcto
        if (data.checklist.type === activeTab) {
          setChecklist(data.checklist);
          setTimeRemaining(data.checklist.time_remaining_minutes);
        } else {
          // Si el tipo no coincide, mostrar error
          setChecklist(null);
        }
      } else {
        setChecklist(null);
      }
    } catch (error) {
      console.error('Error loading checklist:', error);
      setChecklist(null);
    } finally {
      setLoading(false);
    }
  };



  const toggleItem = async (itemId, currentStatus, notes = null) => {
    try {
      const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'update_item',
          item_id: itemId,
          is_completed: !currentStatus,
          notes: notes || itemNotes[itemId] || null
        })
      });
      const data = await res.json();
      if (data.success) loadChecklist();
    } catch (error) {
      console.error('Error updating item:', error);
    }
  };

  const compressImage = (file) => {
    return new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
          const canvas = document.createElement('canvas');
          const MAX_WIDTH = 800;
          const scale = MAX_WIDTH / img.width;
          canvas.width = MAX_WIDTH;
          canvas.height = img.height * scale;
          
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          
          canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.8);
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });
  };

  const uploadPhoto = async (itemId, file) => {
    try {
      const compressed = await compressImage(file);
      const formData = new FormData();
      formData.append('action', 'upload_photo');
      formData.append('item_id', itemId);
      formData.append('photo', compressed, 'photo.jpg');

      const res = await fetch(API_URL, {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (data.success) {
        await toggleItem(itemId, false);
        loadChecklist();
      }
    } catch (error) {
      console.error('Error uploading photo:', error);
    }
  };

  const completeChecklist = async () => {
    if (!confirm('¿Completar checklist?')) return;
    try {
      const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'complete',
          checklist_id: checklist.id
        })
      });
      const data = await res.json();
      if (data.success) {
        alert('✅ Checklist completado');
        loadChecklist();
      }
    } catch (error) {
      console.error('Error completing checklist:', error);
    }
  };

  const formatDateTime = (utcDateStr) => {
    if (!utcDateStr) return '';
    const date = new Date(utcDateStr + 'Z'); // Z indica UTC
    return date.toLocaleString('es-CL', { 
      timeZone: 'America/Santiago',
      year: 'numeric',
      month: '2-digit', 
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  };

  const formatTime = (minutes) => {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}:${m.toString().padStart(2, '0')}`;
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-xl">Cargando...</div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 pb-20">
      {/* Header */}
      <div className="bg-white shadow-sm sticky top-0 z-10">
        <div className="max-w-4xl mx-auto px-4 py-3">
          <div className="flex items-center gap-3">
            <button onClick={() => window.history.back()} className="p-1 hover:bg-gray-100 rounded-lg">
              <ArrowLeft size={20} />
            </button>
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-1">
                <h1 className="text-lg font-bold flex items-center gap-2">
                  <CheckSquare size={20} className="text-orange-500" />
                  Checklist
                </h1>
                {checklist && checklist.status !== 'completed' && (
                  <span className="text-sm text-gray-600">
                    {checklist.completed_items}/{checklist.total_items}
                  </span>
                )}
              </div>
              {checklist && checklist.status !== 'completed' && (
                <div className="w-full bg-gray-200 rounded-full h-2 mb-1">
                  <div 
                    className="bg-green-500 h-2 rounded-full transition-all duration-300"
                    style={{ width: `${checklist.completion_percentage}%` }}
                  />
                </div>
              )}
              <div className="flex items-center gap-3 text-xs text-gray-600">
                <span className="flex items-center gap-1">
                  <Timer size={14} />
                  {checklist?.scheduled_time?.substring(0, 5)}
                </span>
                {checklist && checklist.status !== 'completed' && timeRemaining > 0 && (
                  <span className="flex items-center gap-1">
                    ⏱️ {formatTime(timeRemaining)} restante
                  </span>
                )}
                {checklist && (
                  <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${
                    checklist.status === 'completed' ? 'bg-green-100 text-green-700' :
                    checklist.status === 'active' ? 'bg-green-100 text-green-700' :
                    checklist.status === 'missed' ? 'bg-red-100 text-red-700' :
                    'bg-yellow-100 text-yellow-700'
                  }`}>
                    {checklist.status === 'completed' && '✅ Completado'}
                    {checklist.status === 'missed' && '❌ Perdido'}
                    {checklist.status === 'pending' && '🟡 Pendiente'}
                    {checklist.status === 'active' && '🟢 Activo'}
                  </span>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="bg-white border-b sticky top-[60px] z-10">
        <div className="max-w-4xl mx-auto px-4">
          <div className="flex gap-2">
            <button
              onClick={() => setActiveTab('apertura')}
              className={`flex-1 py-2 text-sm font-semibold border-b-2 transition-colors ${
                activeTab === 'apertura' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500'
              }`}
            >
              ☀️ 18:00
            </button>
            <button
              onClick={() => setActiveTab('cierre')}
              className={`flex-1 py-2 text-sm font-semibold border-b-2 transition-colors ${
                activeTab === 'cierre' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'
              }`}
            >
              🌙 00:45
            </button>
            <button
              onClick={() => setActiveTab('historial')}
              className={`flex-1 py-2 text-sm font-semibold border-b-2 transition-colors ${
                activeTab === 'historial' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500'
              }`}
            >
              📅 Historial
            </button>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="max-w-4xl mx-auto px-4 py-6">
        {activeTab !== 'historial' && !checklist && (
          <div className="flex flex-col items-center justify-center py-16 text-gray-500">
            <div className="text-6xl mb-4">{activeTab === 'apertura' ? '☀️' : '🌙'}</div>
            <p className="text-lg font-semibold">No hay checklist disponible</p>
            <p className="text-sm mt-2">para {activeTab === 'apertura' ? 'apertura (18:00)' : 'cierre (00:45)'}</p>
          </div>
        )}
        {activeTab !== 'historial' && checklist && (
          <>
            {checklist.status === 'completed' ? (
              <div className="flex flex-col items-center justify-center py-16">
                <CheckCircle size={80} className="text-green-500 mb-4" />
                <h2 className="text-2xl font-bold text-gray-800 mb-2">
                  {activeTab === 'apertura' ? '☀️ Apertura' : '🌙 Cierre'} Completado
                </h2>
                <p className="text-gray-600">Realizado el {formatDateTime(checklist.completed_at)}</p>
                <p className="text-sm text-gray-500 mt-2">
                  {checklist.completed_items}/{checklist.total_items} tareas • {Math.round(checklist.completion_percentage)}% completado
                </p>
              </div>
            ) : (
              <>

                <div className="space-y-3">
                  {checklist.items?.map((item) => (
                    <div key={item.id} className="bg-white rounded-xl shadow-sm p-4">
                      <div className="flex items-start gap-3">
                        <button
                          onClick={() => toggleItem(item.id, item.is_completed)}
                          className="flex-shrink-0 mt-1"
                          disabled={checklist.status === 'completed'}
                        >
                          {item.is_completed ? (
                            <CheckCircle size={28} className="text-green-500" />
                          ) : (
                            <Circle size={28} className="text-gray-300" />
                          )}
                        </button>
                        <div className="flex-1">
                          <p className={`font-medium ${item.is_completed ? 'line-through text-gray-400' : 'text-gray-800'}`}>
                            {item.description}
                          </p>
                          {checklist.status !== 'completed' && (
                            <textarea
                              placeholder="Notas opcionales..."
                              className="w-full mt-2 p-2 text-sm border rounded-lg resize-none"
                              rows="2"
                              value={itemNotes[item.id] || item.notes || ''}
                              onChange={(e) => setItemNotes({...itemNotes, [item.id]: e.target.value})}
                              onBlur={() => item.is_completed && toggleItem(item.id, true)}
                            />
                          )}
                          {item.notes && checklist.status === 'completed' && (
                            <p className="text-sm text-gray-600 mt-1 italic">📝 {item.notes}</p>
                          )}
                          {!!item.requires_photo && (
                            <div className="mt-2">
                              {item.photo_url ? (
                                <div className="relative">
                                  <img src={item.photo_url} alt="Evidencia" className="w-full h-40 object-cover rounded-lg" />
                                  {checklist.status !== 'completed' && (
                                    <button
                                      onClick={() => {
                                        if (confirm('¿Eliminar foto?')) {
                                          fetch(API_URL, {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ action: 'delete_photo', item_id: item.id })
                                          }).then(() => {
                                            toggleItem(item.id, true);
                                            loadChecklist();
                                          });
                                        }
                                      }}
                                      className="absolute top-2 right-2 bg-red-500 text-white p-2 rounded-lg hover:bg-red-600"
                                    >
                                      ❌
                                    </button>
                                  )}
                                </div>
                              ) : (
                                <label className="flex items-center gap-2 bg-blue-50 text-blue-600 px-4 py-2 rounded-lg cursor-pointer hover:bg-blue-100 transition-colors">
                                  <Camera size={20} />
                                  <span className="font-semibold">Subir Foto</span>
                                  <input
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    className="hidden"
                                    onChange={(e) => e.target.files[0] && uploadPhoto(item.id, e.target.files[0])}
                                    disabled={checklist.status === 'completed'}
                                  />
                                </label>
                              )}
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Complete Button */}
                {checklist.status !== 'completed' && (
                  <button
                    onClick={completeChecklist}
                    className="w-full bg-blue-500 text-white py-3 rounded-lg font-semibold mt-6 hover:bg-blue-600"
                  >
                    Completar Checklist
                  </button>
                )}
              </>
            )}
          </>
        )}

        {/* Historial Tab */}
        {activeTab === 'historial' && <HistorialTab />}
      </div>


    </div>
  );
}

function HistorialTab() {
  const [history, setHistory] = useState([]);
  const [loading, setLoading] = useState(true);
  const [expanded, setExpanded] = useState({});
  const [fullscreenImage, setFullscreenImage] = useState(null);

  useEffect(() => {
    loadHistory();
  }, []);

  const loadHistory = async () => {
    try {
      const res = await fetch(`${API_URL}?action=get_history`);
      const data = await res.json();
      if (data.success) {
        const grouped = {};
        const aperturas = data.checklists.filter(c => c.type === 'apertura');
        const cierres = data.checklists.filter(c => c.type === 'cierre');
        
        // Agrupar por apertura
        aperturas.forEach(apertura => {
          const key = apertura.scheduled_date;
          grouped[key] = { apertura };
          
          const nextDay = new Date(apertura.scheduled_date + 'T00:00:00');
          nextDay.setDate(nextDay.getDate() + 1);
          const nextDayStr = nextDay.toISOString().split('T')[0];
          
          const cierre = cierres.find(c => c.scheduled_date === nextDayStr);
          if (cierre) grouped[key].cierre = cierre;
        });
        
        // Agregar cierres sin apertura
        cierres.forEach(cierre => {
          const prevDay = new Date(cierre.scheduled_date + 'T00:00:00');
          prevDay.setDate(prevDay.getDate() - 1);
          const prevDayStr = prevDay.toISOString().split('T')[0];
          
          if (!grouped[prevDayStr]) {
            grouped[prevDayStr] = { cierre };
          }
        });
        
        setHistory(Object.entries(grouped).sort((a, b) => b[0].localeCompare(a[0])));
      }
    } catch (error) {
      console.error('Error loading history:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadChecklistItems = async (checklistId) => {
    try {
      const res = await fetch(`${API_URL}?action=get_checklist_items&checklist_id=${checklistId}`);
      const data = await res.json();
      return data.success ? data.items : [];
    } catch (error) {
      console.error('Error loading items:', error);
      return [];
    }
  };

  const toggleExpand = async (key, type) => {
    const expandKey = `${key}-${type}`;
    if (expanded[expandKey]) {
      setExpanded({...expanded, [expandKey]: null});
    } else {
      const checklist = history.find(([k]) => k === key)?.[1]?.[type];
      if (checklist) {
        const items = await loadChecklistItems(checklist.id);
        setExpanded({...expanded, [expandKey]: items});
      }
    }
  };

  const formatTurno = (date) => {
    const d = new Date(date + 'T00:00:00');
    const next = new Date(d);
    next.setDate(next.getDate() + 1);
    return `${d.getDate()}/${d.getMonth() + 1} al ${next.getDate()}/${next.getMonth() + 1}`;
  };

  if (loading) return <div className="text-center py-8">Cargando historial...</div>;

  return (
    <>
      <div className="space-y-3">
        {history.map(([date, checklists]) => (
          <div key={date} className="bg-white rounded-xl shadow-sm p-4">
            <div className="font-bold text-gray-800 mb-3">Turno del {formatTurno(date)}</div>
            <div className="space-y-2">
              {checklists.apertura && (
                <div className="border rounded-lg overflow-hidden">
                  <button
                    onClick={() => toggleExpand(date, 'apertura')}
                    className="w-full flex items-center justify-between p-3 bg-gray-50 hover:bg-gray-100 transition-colors"
                  >
                    <div className="flex items-center gap-2">
                      <span className="text-xl">☀️</span>
                      <div className="text-left">
                        <div className="font-semibold text-sm">Apertura • {checklists.apertura.scheduled_time.substring(0, 5)}</div>
                        <div className="text-xs text-gray-500">Progreso: {checklists.apertura.completed_items}/{checklists.apertura.total_items} ({Math.round(checklists.apertura.completion_percentage)}%)</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className={`px-2 py-1 rounded-full text-xs font-semibold ${
                        checklists.apertura.status === 'completed' ? 'bg-green-100 text-green-700' :
                        checklists.apertura.status === 'active' ? 'bg-green-100 text-green-700' :
                        checklists.apertura.status === 'missed' ? 'bg-red-100 text-red-700' :
                        'bg-yellow-100 text-yellow-700'
                      }`}>
                        {checklists.apertura.status === 'completed' && '✅ Completado'}
                        {checklists.apertura.status === 'missed' && '❌ No Realizado'}
                        {checklists.apertura.status === 'pending' && '🟡 Pendiente'}
                        {checklists.apertura.status === 'active' && '🟢 Activo'}
                      </div>
                      {expanded[`${date}-apertura`] ? <ChevronUp size={20} /> : <ChevronDown size={20} />}
                    </div>
                  </button>
                  {expanded[`${date}-apertura`] && (
                    <div className="p-3 space-y-2 bg-white">
                      {expanded[`${date}-apertura`].map(item => (
                        <div key={item.id} className="text-sm">
                          <div className="flex items-start gap-2">
                            {item.is_completed ? <CheckCircle size={16} className="text-green-500 mt-0.5" /> : <Circle size={16} className="text-gray-300 mt-0.5" />}
                            <span className={item.is_completed ? 'line-through text-gray-400' : ''}>{item.description}</span>
                          </div>
                          {item.notes && <p className="text-xs text-gray-600 ml-6 italic">📝 {item.notes}</p>}
                          {item.photo_url && (
                            <img 
                              src={item.photo_url} 
                              alt="Evidencia" 
                              className="ml-6 mt-1 w-24 h-24 object-cover rounded cursor-pointer hover:opacity-80"
                              onClick={() => setFullscreenImage(item.photo_url)}
                            />
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
              {checklists.cierre && (
                <div className="border rounded-lg overflow-hidden">
                  <button
                    onClick={() => toggleExpand(date, 'cierre')}
                    className="w-full flex items-center justify-between p-3 bg-gray-50 hover:bg-gray-100 transition-colors"
                  >
                    <div className="flex items-center gap-2">
                      <span className="text-xl">🌙</span>
                      <div className="text-left">
                        <div className="font-semibold text-sm">Cierre • {checklists.cierre.scheduled_time.substring(0, 5)}</div>
                        <div className="text-xs text-gray-500">Progreso: {checklists.cierre.completed_items}/{checklists.cierre.total_items} ({Math.round(checklists.cierre.completion_percentage)}%)</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className={`px-2 py-1 rounded-full text-xs font-semibold ${
                        checklists.cierre.status === 'completed' ? 'bg-green-100 text-green-700' :
                        checklists.cierre.status === 'active' ? 'bg-green-100 text-green-700' :
                        checklists.cierre.status === 'missed' ? 'bg-red-100 text-red-700' :
                        'bg-yellow-100 text-yellow-700'
                      }`}>
                        {checklists.cierre.status === 'completed' && '✅ Completado'}
                        {checklists.cierre.status === 'missed' && '❌ No Realizado'}
                        {checklists.cierre.status === 'pending' && '🟡 Pendiente'}
                        {checklists.cierre.status === 'active' && '🟢 Activo'}
                      </div>
                      {expanded[`${date}-cierre`] ? <ChevronUp size={20} /> : <ChevronDown size={20} />}
                    </div>
                  </button>
                  {expanded[`${date}-cierre`] && (
                    <div className="p-3 space-y-2 bg-white">
                      {expanded[`${date}-cierre`].map(item => (
                        <div key={item.id} className="text-sm">
                          <div className="flex items-start gap-2">
                            {item.is_completed ? <CheckCircle size={16} className="text-green-500 mt-0.5" /> : <Circle size={16} className="text-gray-300 mt-0.5" />}
                            <span className={item.is_completed ? 'line-through text-gray-400' : ''}>{item.description}</span>
                          </div>
                          {item.notes && <p className="text-xs text-gray-600 ml-6 italic">📝 {item.notes}</p>}
                          {item.photo_url && (
                            <img 
                              src={item.photo_url} 
                              alt="Evidencia" 
                              className="ml-6 mt-1 w-24 h-24 object-cover rounded cursor-pointer hover:opacity-80"
                              onClick={() => setFullscreenImage(item.photo_url)}
                            />
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* Modal pantalla completa */}
      {fullscreenImage && (
        <div 
          className="fixed inset-0 bg-black bg-opacity-90 z-50 flex items-center justify-center p-4"
          onClick={() => setFullscreenImage(null)}
        >
          <button 
            className="absolute top-4 right-4 text-white p-2 hover:bg-white/20 rounded-full"
            onClick={() => setFullscreenImage(null)}
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
    </>
  );
}
