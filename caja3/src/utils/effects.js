// Sistema de audio para notificaciones
let audioContext = null;
let audioUnlocked = false;
let comandaAudio = null;

// Inicializar audio context (necesario para iOS)
export const initAudio = () => {
  if (audioUnlocked) return true;

  try {
    // Crear instancia de audio
    comandaAudio = new Audio('/comanda.mp3');
    comandaAudio.volume = 1.0;
    comandaAudio.load();

    // Intentar reproducir silenciosamente para desbloquear
    const playPromise = comandaAudio.play();
    if (playPromise !== undefined) {
      playPromise.then(() => {
        comandaAudio.pause();
        comandaAudio.currentTime = 0;
        audioUnlocked = true;
        console.log('✅ Audio desbloqueado');
      }).catch(() => {
        console.log('⚠️ Audio aún bloqueado, se desbloqueará con interacción');
      });
    }
    return true;
  } catch (e) {
    console.error('Error inicializando audio:', e);
    return false;
  }
};

// Reproducir sonido de comanda
export const playComandaSound = () => {
  try {
    if (!comandaAudio) {
      comandaAudio = new Audio('/comanda.mp3');
      comandaAudio.volume = 1.0;
    }

    comandaAudio.currentTime = 0;
    const playPromise = comandaAudio.play();

    if (playPromise !== undefined) {
      playPromise.then(() => {
        audioUnlocked = true;
      }).catch(error => {
        console.log('⚠️ No se pudo reproducir audio:', error.message);
      });
    }
  } catch (e) {
    console.error('Error reproduciendo audio:', e);
  }
};

// --- Nuevos Sonidos con Pre-carga ---
const addSound = typeof Audio !== 'undefined' ? new Audio('/blip.mp3') : null;
const removeSound = typeof Audio !== 'undefined' ? new Audio('/damage.mp3') : null;
const successSound = typeof Audio !== 'undefined' ? new Audio('/gg.mp3') : null;

// Configurar volúmenes iniciales
if (addSound) addSound.volume = 0.4;
if (removeSound) removeSound.volume = 0.3;
if (successSound) successSound.volume = 0.6;

// Sonido al agregar al carrito (blip)
export const playAddSound = () => {
  try {
    if (addSound) {
      addSound.currentTime = 0;
      addSound.play().catch(() => { });
    }
  } catch (e) { }
};

// Sonido al quitar del carrito (damage/blip invertido)
export const playRemoveSound = () => {
  try {
    if (removeSound) {
      removeSound.currentTime = 0;
      removeSound.play().catch(() => { });
    }
  } catch (e) { }
};

// Sonido de éxito/pago (gg)
export const playSuccessSound = () => {
  try {
    if (successSound) {
      successSound.currentTime = 0;
      successSound.play().catch(() => { });
    }
  } catch (e) { }
};

// Función de vibración para PWA
export const vibrate = (pattern = 100) => {
  if ('vibrate' in navigator) {
    navigator.vibrate(pattern);
  }
};

// Función de sonido de notificación (legacy)
export const playNotificationSound = () => {
  playSuccessSound(); // Usar el nuevo sonido de éxito para notificaciones de pago
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