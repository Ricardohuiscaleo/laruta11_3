import React, { useRef, useEffect } from 'react';
import { X } from 'lucide-react';

const SwipeableModal = ({ isOpen, onClose, title, children, className = '' }) => {
    const modalRef = useRef(null);
    const headerRef = useRef(null);

    useEffect(() => {
        if (!isOpen) return;
        
        const modal = modalRef.current;
        const header = headerRef.current;
        if (!modal || !header) return;
        
        let startY = 0;
        let isDragging = false;
        
        const handleStart = (clientY) => {
            isDragging = true;
            startY = clientY;
            modal.classList.add('dragging');
        };
        
        const handleMove = (clientY) => {
            if (!isDragging) return;
            const diffY = clientY - startY;
            if (diffY > 0) {
                modal.style.transform = `translateY(${diffY}px)`;
            }
        };
        
        const handleEnd = (clientY) => {
            if (!isDragging) return;
            isDragging = false;
            const diffY = clientY - startY;
            modal.classList.remove('dragging');
            
            if (diffY > 80) {
                onClose();
            } else {
                modal.style.transform = 'translateY(0)';
            }
        };
        
        const onTouchStart = (e) => handleStart(e.touches[0].clientY);
        const onTouchMove = (e) => { e.preventDefault(); handleMove(e.touches[0].clientY); };
        const onTouchEnd = (e) => handleEnd(e.changedTouches[0].clientY);
        
        const onMouseDown = (e) => handleStart(e.clientY);
        const onMouseMove = (e) => { if (isDragging) handleMove(e.clientY); };
        const onMouseUp = (e) => { if (isDragging) handleEnd(e.clientY); };
        
        header.addEventListener('touchstart', onTouchStart, { passive: false });
        header.addEventListener('touchmove', onTouchMove, { passive: false });
        header.addEventListener('touchend', onTouchEnd);
        header.addEventListener('mousedown', onMouseDown);
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
        
        return () => {
            header.removeEventListener('touchstart', onTouchStart);
            header.removeEventListener('touchmove', onTouchMove);
            header.removeEventListener('touchend', onTouchEnd);
            header.removeEventListener('mousedown', onMouseDown);
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };
    }, [isOpen, onClose]);
    
    if (!isOpen) return null;
    
    return (
        <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-end animate-fade-in" onClick={onClose}>
            <div 
                ref={modalRef}
                className={`bg-white w-full max-w-2xl max-h-[75vh] rounded-t-2xl flex flex-col transition-transform duration-300 ease-out ${className}`}
                onClick={(e) => e.stopPropagation()}
                style={{transform: 'translateY(0)'}}
            >
                <div 
                    ref={headerRef}
                    className="bg-gradient-to-r from-orange-500 to-red-600 text-white flex justify-between items-center sticky top-0 z-10 rounded-t-2xl cursor-grab active:cursor-grabbing"
                    style={{padding: 'clamp(16px, 4vw, 20px)'}}
                >
                    <div className="absolute top-2 left-1/2 transform -translate-x-1/2 w-12 h-1 bg-white/40 rounded-full"></div>
                    <h2 className="font-black" style={{fontSize: 'clamp(18px, 4.5vw, 22px)'}}>{title}</h2>
                    <button onClick={onClose} className="p-1 hover:bg-white/20 rounded-full transition-colors">
                        <X size={28} />
                    </button>
                </div>
                
                <div className="flex-grow overflow-y-auto">
                    {children}
                </div>
            </div>
        </div>
    );
};

export default SwipeableModal;

// CSS para la clase dragging
const style = document.createElement('style');
style.textContent = `
    .dragging {
        transition: none !important;
    }
`;
if (typeof document !== 'undefined' && !document.querySelector('style[data-swipeable-modal]')) {
    style.setAttribute('data-swipeable-modal', 'true');
    document.head.appendChild(style);
}