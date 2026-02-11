import { useEffect } from 'react';

export default function ChecklistsListener({ onChecklistsUpdate }) {
  useEffect(() => {
    const loadChecklists = async () => {
      try {
        const now = new Date();
        const hours = now.getHours();
        const minutes = now.getMinutes();
        const currentTime = hours * 60 + minutes;
        
        const shouldLoadApertura = currentTime >= 1020 && currentTime < 1140;
        const shouldLoadCierre = currentTime >= 30 && currentTime < 105;
        
        if (!shouldLoadApertura && !shouldLoadCierre) {
          if (onChecklistsUpdate) onChecklistsUpdate(0);
          return;
        }
        
        const types = [];
        if (shouldLoadApertura) types.push('apertura');
        if (shouldLoadCierre) types.push('cierre');
        
        const results = [];
        for (const type of types) {
          const res = await fetch(`/api/checklist.php?action=get_active&type=${type}&date=${now.toISOString().split('T')[0]}`);
          const data = await res.json();
          if (data.success && data.checklist) {
            results.push(data.checklist);
          }
        }
        
        const activeCount = results.filter(c => c.status !== 'completed' && c.status !== 'missed').length;
        if (onChecklistsUpdate) onChecklistsUpdate(activeCount);
      } catch (error) {
        console.error('Error loading checklists:', error);
      }
    };

    loadChecklists();
    const interval = setInterval(loadChecklists, 5000);
    return () => clearInterval(interval);
  }, [onChecklistsUpdate]);

  return null;
}
