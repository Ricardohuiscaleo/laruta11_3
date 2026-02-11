// Horarios de atención en hora de Chile (America/Santiago)
export const BUSINESS_HOURS = {
  1: { open: '18:00', close: '00:30', name: 'Lunes' },      // Lunes
  2: { open: '18:00', close: '00:30', name: 'Martes' },     // Martes
  3: { open: '18:00', close: '00:30', name: 'Miércoles' },  // Miércoles
  4: { open: '18:00', close: '00:30', name: 'Jueves' },     // Jueves
  5: { open: '18:00', close: '02:30', name: 'Viernes' },    // Viernes
  6: { open: '18:00', close: '02:30', name: 'Sábado' },     // Sábado
  0: { open: '18:00', close: '00:00', name: 'Domingo' }     // Domingo
};

export const isWithinBusinessHours = () => {
  const now = new Date();
  const chileTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/Santiago' }));
  const day = chileTime.getDay();
  const hours = chileTime.getHours();
  const minutes = chileTime.getMinutes();
  const currentTime = hours * 60 + minutes;

  const schedule = BUSINESS_HOURS[day];
  if (!schedule) return false;

  const [openH, openM] = schedule.open.split(':').map(Number);
  const [closeH, closeM] = schedule.close.split(':').map(Number);
  
  const openTime = openH * 60 + openM;
  let closeTime = closeH * 60 + closeM;
  
  // Si cierra después de medianoche
  if (closeH < openH) {
    closeTime += 24 * 60;
    if (hours < openH) {
      return currentTime + 24 * 60 <= closeTime;
    }
  }
  
  return currentTime >= openTime && currentTime <= closeTime;
};

export const getNextAvailableSlots = () => {
  const now = new Date();
  const chileTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/Santiago' }));
  const slots = [];
  
  for (let dayOffset = 0; dayOffset < 7; dayOffset++) {
    const targetDate = new Date(chileTime);
    targetDate.setDate(targetDate.getDate() + dayOffset);
    const day = targetDate.getDay();
    const schedule = BUSINESS_HOURS[day];
    
    if (!schedule) continue;
    
    const [openH, openM] = schedule.open.split(':').map(Number);
    const [closeH, closeM] = schedule.close.split(':').map(Number);
    
    let startHour = openH;
    let endHour = closeH;
    
    // Si es hoy, empezar desde la hora actual + 1 hora
    if (dayOffset === 0) {
      const currentHour = chileTime.getHours();
      startHour = Math.max(openH, currentHour + 1);
    }
    
    // Ajustar si cierra después de medianoche
    if (closeH < openH) {
      endHour += 24;
    }
    
    // Generar slots de 1 hora
    for (let hour = startHour; hour < endHour; hour++) {
      const displayHour = hour >= 24 ? hour - 24 : hour;
      const nextHour = displayHour + 1 >= 24 ? displayHour + 1 - 24 : displayHour + 1;
      
      const dateStr = targetDate.toLocaleDateString('es-CL', { 
        weekday: 'short', 
        day: 'numeric', 
        month: 'short' 
      });
      
      slots.push({
        value: `${targetDate.toISOString().split('T')[0]}_${displayHour.toString().padStart(2, '0')}:00`,
        display: `${dateStr} - ${displayHour.toString().padStart(2, '0')}:00 a ${nextHour.toString().padStart(2, '0')}:00`,
        date: targetDate.toISOString().split('T')[0],
        time: `${displayHour.toString().padStart(2, '0')}:00`
      });
    }
  }
  
  return slots;
};

export const getBusinessStatus = () => {
  const isOpen = isWithinBusinessHours();
  const now = new Date();
  const chileTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/Santiago' }));
  const day = chileTime.getDay();
  const schedule = BUSINESS_HOURS[day];
  
  return {
    isOpen,
    currentDay: schedule?.name || 'Desconocido',
    openTime: schedule?.open || '',
    closeTime: schedule?.close || '',
    message: isOpen 
      ? `Abierto hasta las ${schedule?.close}` 
      : `Cerrado - Abre ${schedule?.name} a las ${schedule?.open}`
  };
};
