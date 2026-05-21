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

    splash.addEventListener('click', complete);
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); complete(); }
    });
    const timeout = setTimeout(complete, 8000);

    // If video plays for at least 1.5s, let the app's timer handle completion
    return () => {
      clearTimeout(timeout);
      splash.removeEventListener('click', complete);
      if (!done) complete();
    };
  }, [onComplete]);

  return null;
};

export default LoadingScreen;
