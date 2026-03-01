import { useState, useEffect } from 'react';
import { Smartphone, Menu as MenuIcon, X } from 'lucide-react';

export default function Header() {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isScrolled, setIsScrolled] = useState(false);
  const [isVisible, setIsVisible] = useState(true);
  const [lastScrollY, setLastScrollY] = useState(0);

  useEffect(() => {
    const handleScroll = () => {
      const currentScrollY = window.scrollY;

      setIsScrolled(currentScrollY > 50);

      if (currentScrollY < lastScrollY || currentScrollY < 100) {
        setIsVisible(true);
      } else if (currentScrollY > lastScrollY && currentScrollY > 100) {
        setIsVisible(false);
      }

      setLastScrollY(currentScrollY);
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, [lastScrollY]);

  const navLinks = [
    { name: 'Inicio', href: '#inicio' },
    { name: 'Servicios', href: '#servicios' },
    { name: 'Nosotros', href: '#nosotros' },
    { name: 'Menú', href: '#menu' },
    { name: 'Contacto', href: '#contacto' },
  ];

  return (
    <header className={`fixed w-full z-[100] transition-all duration-500 transform ${isVisible ? 'translate-y-0' : '-translate-y-full'
      } pt-4 md:pt-6 px-4 md:px-6`}>
      <div className={`container mx-auto max-w-7xl transition-all duration-500 rounded-[2rem] border border-white/5 ${isScrolled
        ? 'bg-ruta-black/60 backdrop-blur-xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] py-3'
        : 'bg-transparent py-4'
        }`}>
        <nav className="flex justify-between items-center px-6 md:px-10">
          {/* Logo Section */}
          <div className="flex items-center gap-3 group cursor-pointer">
            <div className="relative">
              <div className="absolute inset-0 bg-ruta-yellow blur-md opacity-0 group-hover:opacity-40 transition-opacity"></div>
              <img
                src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png"
                alt="La Ruta 11 Logo"
                className="relative w-8 h-8 md:w-10 md:h-10 object-contain drop-shadow-[0_0_10px_rgba(250,204,21,0.2)]"
              />
            </div>
            <div className="text-lg md:text-xl font-extrabold tracking-tighter text-ruta-white uppercase">
              La Ruta <span className="text-ruta-yellow">11</span>
            </div>
          </div>

          {/* Desktop Nav */}
          <div className="hidden lg:flex items-center space-x-10">
            {navLinks.map((link) => (
              <a
                key={link.name}
                href={link.href}
                className="text-xs font-bold uppercase tracking-[0.2em] text-ruta-white/60 hover:text-ruta-yellow transition-colors relative group py-2"
              >
                {link.name}
                <span className="absolute bottom-0 left-0 w-0 h-[1px] bg-ruta-yellow transition-all duration-300 group-hover:w-full"></span>
              </a>
            ))}

            <a
              href="https://app.laruta11.cl"
              target="_blank"
              className="bg-ruta-yellow text-ruta-black px-6 py-2.5 rounded-full font-bold text-xs uppercase tracking-widest hover:bg-white hover:text-ruta-black transition-all transform hover:scale-105 shadow-lg flex items-center gap-2 no-underline"
            >
              <Smartphone className="w-3.5 h-3.5" />
              Pedir Ahora
            </a>
          </div>

          {/* Mobile Right */}
          <div className="flex items-center gap-4 lg:hidden">
            <a
              href="https://app.laruta11.cl"
              target="_blank"
              className="bg-ruta-yellow text-ruta-black p-2.5 rounded-full font-bold transition-all shadow-lg no-underline"
            >
              <Smartphone className="w-4 h-4" />
            </a>
            <button
              className="text-ruta-white p-2"
              onClick={() => setIsMenuOpen(!isMenuOpen)}
            >
              {isMenuOpen ? <X className="w-6 h-6" /> : <MenuIcon className="w-6 h-6" />}
            </button>
          </div>
        </nav>
      </div>

      {/* Mobile Menu Overlay */}
      <div className={`lg:hidden fixed inset-0 z-[-1] bg-ruta-dark transition-all duration-500 ${isMenuOpen ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-full'
        }`}>
        <div className="flex flex-col items-center justify-center h-full space-y-8 p-6">
          {navLinks.map((link) => (
            <a
              key={link.name}
              href={link.href}
              className="text-3xl font-extrabold text-ruta-white hover:text-ruta-yellow transition-colors tracking-tighter"
              onClick={() => setIsMenuOpen(false)}
            >
              {link.name}
            </a>
          ))}
          <a
            href="https://app.laruta11.cl"
            target="_blank"
            className="w-full bg-ruta-yellow text-ruta-black py-5 rounded-[2rem] font-bold text-center text-lg shadow-xl no-underline"
            onClick={() => setIsMenuOpen(false)}
          >
            Ir a la App
          </a>
        </div>
      </div>
    </header>
  );
}