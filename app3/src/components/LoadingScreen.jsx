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
      splash.style.transition = 'opacity 0.5s ease-out';
      splash.style.opacity = '0';
      if (video) video.pause();
      setTimeout(() => {
        splash.style.display = 'none';
        onComplete();
      }, 500);
    };

    if (video) {
      if (video.ended || video.currentTime >= video.duration) {
        complete();
        return;
      }
      video.addEventListener('ended', complete, { once: true });
    }

    const timeout = setTimeout(complete, 30000);

    return () => {
      clearTimeout(timeout);
      if (video) video.removeEventListener('ended', complete);
      if (!done) complete();
    };
  }, [onComplete]);

  return null;
};

export default LoadingScreen;
