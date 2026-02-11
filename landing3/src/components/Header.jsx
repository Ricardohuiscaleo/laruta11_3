import { useState, useEffect } from 'react';
import { Smartphone } from 'lucide-react';

export default function Header() {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isScrolled, setIsScrolled] = useState(false);
  const [isVisible, setIsVisible] = useState(true);
  const [lastScrollY, setLastScrollY] = useState(0);

  useEffect(() => {
    const handleScroll = () => {
      const currentScrollY = window.scrollY;
      
      setIsScrolled(currentScrollY > 50);
      
      // Close mobile menu on scroll
      if (isMenuOpen) {
        setIsMenuOpen(false);
      }
      
      if (currentScrollY < lastScrollY || currentScrollY < 100) {
        setIsVisible(true);
      } else if (currentScrollY > lastScrollY && currentScrollY > 100) {
        setIsVisible(false);
      }
      
      setLastScrollY(currentScrollY);
    };
    
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, [lastScrollY, isMenuOpen]);

  return (
    <header className={`fixed w-full z-50 transition-all duration-300 px-4 ${
      isVisible ? 'top-2' : '-top-20'
    }`}>
      <div className={`rounded-2xl md:rounded-full transition-all duration-300 ${
        isScrolled 
          ? 'bg-black/40 backdrop-blur-md shadow-2xl py-2' 
          : 'bg-transparent py-3'
      }`}>
        <nav className="container mx-auto px-6 py-2">
        <div className="flex justify-between items-center">
          <div className="flex items-center space-x-3">
            <img 
              src="https://laruta11-images.s3.amazonaws.com/menu/1755571382_test.jpg" 
              alt="La Ruta11 Logo" 
              className="w-8 h-8 rounded-full object-cover"
            />
            <div className="text-xl font-bold text-ruta-white">
              La Ruta<span className="text-yellow-400">11</span>
            </div>
          </div>
          
          <div className="hidden md:flex items-center space-x-6">
            <a href="#inicio" className="text-ruta-white hover:text-yellow-400 transition-colors">Inicio</a>
            <a href="#servicios" className="text-ruta-white hover:text-yellow-400 transition-colors">Servicios</a>
            <a href="#nosotros" className="text-ruta-white hover:text-yellow-400 transition-colors">Nosotros</a>
            <a href="#menu" className="text-ruta-white hover:text-yellow-400 transition-colors">Menú</a>
            <a href="#ubicacion" className="text-ruta-white hover:text-yellow-400 transition-colors">Ubicación</a>
            <a href="#contacto" className="text-ruta-white hover:text-yellow-400 transition-colors">Contacto</a>
            <a href="https://app.laruta11.cl" target="_blank" className="relative overflow-hidden bg-yellow-400 text-ruta-black px-4 py-2 rounded-full font-semibold text-sm hover:bg-yellow-300 transition-all flex items-center gap-2 group no-underline">
              <div className="absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-700 bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
              <Smartphone className="w-4 h-4 relative z-10" />
              <span className="relative z-10">Ir a App</span>
            </a>
          </div>

          <div className="flex items-center gap-3 md:hidden">
            <a href="https://app.laruta11.cl" target="_blank" className="relative overflow-hidden bg-yellow-400 text-ruta-black px-3 py-2 rounded-full font-semibold text-xs flex items-center gap-1 group no-underline">
              <div className="absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-700 bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
              <Smartphone className="w-3 h-3 relative z-10" />
              <span className="relative z-10">App</span>
            </a>
            <button 
              className="text-ruta-white"
              onClick={() => setIsMenuOpen(!isMenuOpen)}
            >
              ☰
            </button>
          </div>
        </div>

        {isMenuOpen && (
          <div className="md:hidden mt-4 space-y-2">
            <a href="#inicio" className="block text-ruta-white hover:text-yellow-400 py-2">Inicio</a>
            <a href="#servicios" className="block text-ruta-white hover:text-yellow-400 py-2">Servicios</a>
            <a href="#nosotros" className="block text-ruta-white hover:text-yellow-400 py-2">Nosotros</a>
            <a href="#menu" className="block text-ruta-white hover:text-yellow-400 py-2">Menú</a>
            <a href="#ubicacion" className="block text-ruta-white hover:text-yellow-400 py-2">Ubicación</a>
            <a href="#contacto" className="block text-ruta-white hover:text-yellow-400 py-2">Contacto</a>
          </div>
        )}
        </nav>
      </div>
    </header>
  );
}