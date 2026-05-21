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
      if (video) {
        video.pause();
        video.removeAttribute('src');
        video.load();
      }
      splash.style.transition = 'opacity 0.5s ease-out';
      splash.style.opacity = '0';
      setTimeout(() => {
        splash.style.display = 'none';
        onComplete();
      }, 500);
    };

    if (!video) {
      const t = setTimeout(complete, 2000);
      return () => { clearTimeout(t); if (!done) complete(); };
    }

    video.addEventListener('ended', complete, { once: true });
    video.addEventListener('error', complete, { once: true });
    video.addEventListener('timeupdate', () => {
      if (video.ended || video.currentTime >= video.duration - 0.3) complete();
    });

    const timeout = setTimeout(complete, 30000);

    return () => {
      clearTimeout(timeout);
      if (!done) {
        video.removeEventListener('ended', complete);
        video.removeEventListener('error', complete);
        complete();
      }
    };
  }, [onComplete]);

  return null;
};

export default LoadingScreen;
