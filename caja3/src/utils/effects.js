// Función de vibración para PWA
export const vibrate = (pattern = 100) => {
  if ('vibrate' in navigator) {
    navigator.vibrate(pattern);
  }
};

// Función de sonido de notificación
export const playNotificationSound = () => {
  try {
    const audio = new Audio('/notificacion.mp3');
    audio.volume = 0.5;
    audio.play().catch(() => {});
  } catch (e) {}
};

// Función de confeti
export const createConfetti = () => {
  const colors = ['#f43f5e', '#eab308', '#22c55e', '#3b82f6', '#a855f7', '#f97316'];
  const confettiCount = 50;
  
  for (let i = 0; i < confettiCount; i++) {
    const confetti = document.createElement('div');
    confetti.style.cssText = `
      position: fixed;
      width: 10px;
      height: 10px;
      background: ${colors[Math.floor(Math.random() * colors.length)]};
      left: ${Math.random() * 100}vw;
      top: -10px;
      z-index: 9999;
      pointer-events: none;
      border-radius: 50%;
      animation: confetti-fall ${2 + Math.random() * 3}s linear forwards;
    `;
    
    document.body.appendChild(confetti);
    
    setTimeout(() => {
      if (confetti.parentNode) {
        confetti.parentNode.removeChild(confetti);
      }
    }, 5000);
  }
};