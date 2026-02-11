import { useState } from 'react';

const useDoubleTap = (onDoubleTap, delay = 300) => {
  const [lastTap, setLastTap] = useState(0);
  
  const handleTap = (e) => {
    const now = Date.now();
    if (now - lastTap < delay) {
      e.preventDefault();
      onDoubleTap(e);
    }
    setLastTap(now);
  };
  
  return handleTap;
};

export default useDoubleTap;