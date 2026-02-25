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

// --- Motor de Síntesis de Audio (AI-Generated) ---

// Obtener o crear el AudioContext de forma segura
const getAudioContext = () => {
  if (!audioContext) {
    audioContext = new (window.AudioContext || window.webkitAudioContext)();
  }
  return audioContext;
};

// Función maestra para sintetizar tonos
const synthesizeTone = (freq, duration, type = 'sine', volume = 0.1, sweepFreq = null) => {
  try {
    const ctx = getAudioContext();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    osc.type = type;
    osc.frequency.setValueAtTime(freq, ctx.currentTime);

    if (sweepFreq) {
      osc.frequency.exponentialRampToValueAtTime(sweepFreq, ctx.currentTime + duration);
    }

    gain.gain.setValueAtTime(volume, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);

    osc.connect(gain);
    gain.connect(ctx.destination);

    osc.start();
    osc.stop(ctx.currentTime + duration);
  } catch (e) {
    console.warn('Audio synthesis failed:', e);
  }
};

// Sonido al agregar al carrito (blip cristalino)
export const playAddSound = () => {
  synthesizeTone(800, 0.1, 'sine', 0.2, 1200);
};

// Sonido al quitar del carrito (thud descendente)
export const playRemoveSound = () => {
  synthesizeTone(400, 0.15, 'sawtooth', 0.1, 150);
};

// Sonido de éxito/pago (arpegio triunfal)
export const playSuccessSound = () => {
  const ctx = getAudioContext();
  const notes = [523.25, 659.25, 783.99, 1046.50]; // C5, E5, G5, C6

  notes.forEach((freq, i) => {
    setTimeout(() => {
      synthesizeTone(freq, 0.3, 'sine', 0.15 - (i * 0.02));
    }, i * 80);
  });
};

// Alias para mantener compatibilidad si se invocan con otros nombres
export const playCajaSound = () => {
  synthesizeTone(600, 0.1, 'sine', 0.15, 900);
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