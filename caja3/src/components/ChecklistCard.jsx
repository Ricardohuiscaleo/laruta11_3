import { Clock, PlayCircle } from 'lucide-react';

export default function ChecklistCard({ checklist, onStart }) {
  const handleTrashDone = () => {
    const today = new Date().toISOString().split('T')[0];
    localStorage.setItem(`trash_done_${today}`, 'true');
    window.location.reload();
  };

  const formatTime = (minutes) => {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${h}:${m.toString().padStart(2, '0')}`;
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'active': return 'border-orange-500 bg-orange-50';
      case 'completed': return 'border-green-500 bg-green-50';
      case 'missed': return 'border-red-500 bg-red-50';
      default: return 'border-yellow-500 bg-yellow-50';
    }
  };

  // Renderizado especial para recordatorio de basura
  if (checklist.type === 'trash') {
    return (
      <div className="border-2 rounded-xl p-4 border-orange-500 bg-orange-50">
        <div className="flex items-center gap-2 mb-3">
          <span className="text-2xl">🗑️</span>
          <div className="flex-1">
            <div className="font-bold text-lg">
              {checklist.title}
            </div>
            <div className="text-sm text-gray-600">
              {checklist.description}
            </div>
          </div>
        </div>
        <button
          onClick={handleTrashDone}
          className="w-full bg-green-500 text-white py-3 rounded-lg font-bold hover:bg-green-600 transition-colors"
        >
          ✅ Listo
        </button>
      </div>
    );
  }

  return (
    <div className={`border-2 rounded-xl p-4 ${getStatusColor(checklist.status)}`}>
      <div className="flex items-center gap-2 mb-3">
        <span className="text-2xl">📋</span>
        <div className="flex-1">
          <div className="font-bold text-lg">
            {checklist.type === 'apertura' ? '☀️ CHECKLIST APERTURA' : '🌙 CHECKLIST CIERRE'}
          </div>
          <div className="text-sm text-gray-600">
            ⏰ {checklist.scheduled_time.substring(0, 5)} - {checklist.type === 'apertura' ? '19:00' : '01:45'}
          </div>
        </div>
      </div>

      {checklist.status !== 'completed' && checklist.status !== 'missed' && (
        <div className="mb-3 flex items-center gap-2 text-sm">
          <Clock size={16} className="text-gray-600" />
          <span className="font-semibold">
            ⏳ {formatTime(checklist.time_remaining_minutes)} restantes
          </span>
        </div>
      )}

      <div className="mb-3">
        <div className="text-sm text-gray-600 mb-1">
          Progreso: {checklist.completed_items}/{checklist.total_items}
        </div>
        <div className="w-full bg-gray-200 rounded-full h-2">
          <div
            className="bg-green-500 h-2 rounded-full transition-all"
            style={{ width: `${checklist.completion_percentage}%` }}
          />
        </div>
      </div>

      {checklist.status === 'pending' || checklist.status === 'active' ? (
        <button
          onClick={() => window.location.href = `/checklist?tab=${checklist.type}`}
          className="w-full bg-orange-500 text-white py-3 rounded-lg font-bold flex items-center justify-center gap-2 hover:bg-orange-600 transition-colors"
        >
          <PlayCircle size={20} />
          {checklist.status === 'pending' ? 'Iniciar Checklist' : 'Continuar Checklist'}
        </button>
      ) : (
        <div className={`text-center py-3 rounded-lg font-bold ${
          checklist.status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
        }`}>
          {checklist.status === 'completed' ? '✅ Completado' : '❌ No Realizado'}
        </div>
      )}
    </div>
  );
}
