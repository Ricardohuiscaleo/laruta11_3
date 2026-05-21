import React, { useRef, useEffect, useState } from 'react';

const VIDEO_URL = '/api/loading-opt.mp4';
const POSTER_URL = '/api/loading-poster.jpg';

const LoadingScreen = ({ onComplete }) => {
  const videoRef = useRef(null);
  const [ready, setReady] = useState(false);
  const hasCompleted = useRef(false);

  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;

    const done = () => {
      if (hasCompleted.current) return;
      hasCompleted.current = true;
      const el = video.parentElement;
      if (el && el.style) {
        el.style.transition = 'opacity 0.5s ease-out';
        el.style.opacity = '0';
      }
      setTimeout(onComplete, 500);
    };

    const onCanPlay = () => {
      setReady(true);
      video.play().catch(() => {});
    };

    if (video.readyState >= 3) {
      onCanPlay();
    } else {
      video.addEventListener('canplaythrough', onCanPlay, { once: true });
      video.addEventListener('loadeddata', onCanPlay, { once: true });
    }

    video.addEventListener('error', done, { once: true });

    const timeout = setTimeout(done, 6000);
    video.addEventListener('click', done);
    window.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); done(); } });

    return () => { clearTimeout(timeout); if (!hasCompleted.current) done(); };
  }, [onComplete]);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black">
      <video
        ref={videoRef}
        muted
        loop
        playsInline
        poster={POSTER_URL}
        preload="auto"
        className={`w-full h-full object-cover transition-opacity duration-500 ${ready ? 'opacity-100' : 'opacity-0'}`}
      >
        <source src={VIDEO_URL} type="video/mp4" />
      </video>
      {!ready && (
        <div className="absolute inset-0 flex items-center justify-center bg-black">
          <img src={POSTER_URL} alt="" className="w-full h-full object-cover" />
          <div className="absolute bottom-12 left-1/2 -translate-x-1/2">
            <div className="w-6 h-6 border-2 border-white/40 border-t-white rounded-full animate-spin" />
          </div>
        </div>
      )}
    </div>
  );
};

export default LoadingScreen;
