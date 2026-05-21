import React, { useEffect } from 'react';

const LoadingScreen = ({ onComplete }) => {
  useEffect(() => {
    const video = document.getElementById('splash-video');
    const splash = document.getElementById('splash-screen');
    if (!splash) return;

    let done = false;
    const complete = () => {
      if (done) return;
      done = true;
      splash.style.transition = 'opacity 0.4s ease-out';
      splash.style.opacity = '0';
      if (video) video.pause();
      setTimeout(() => {
        splash.style.display = 'none';
        onComplete();
      }, 400);
    };

    // When video ends, complete loading
    if (video) video.addEventListener('ended', complete, { once: true });

    splash.addEventListener('click', complete);
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); complete(); }
    });

    // Backup: complete after 15s if video never ends
    const timeout = setTimeout(complete, 15000);

    return () => {
      clearTimeout(timeout);
      splash.removeEventListener('click', complete);
      if (video) video.removeEventListener('ended', complete);
      if (!done) complete();
    };
  }, [onComplete]);

  return null;
};

export default LoadingScreen;
