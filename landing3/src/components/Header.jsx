import { useState, useEffect } from 'react';
import { Smartphone, Menu as MenuIcon, X } from 'lucide-react';
import StaggeredMenu from './StaggeredMenu';

export default function Header() {
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
    { label: 'Inicio', ariaLabel: 'Ir a Inicio', link: '#inicio' },
    { label: 'Servicios', ariaLabel: 'Ir a Servicios', link: '#servicios' },
    { label: 'Nosotros', ariaLabel: 'Ir a Nosotros', link: '#nosotros' },
    { label: 'Menú', ariaLabel: 'Ir a Menú', link: '#menu' },
    { label: 'Ubicación', ariaLabel: 'Ir a Ubicación', link: '#contacto' },
  ];

  const socialItems = [
    { label: 'Pedido Online', link: 'https://app.laruta11.cl' },
    { label: 'WhatsApp', link: 'https://wa.me/56922504275' }
  ];

  return (
    <>
      <header className={`fixed w-full z-[100] transition-all duration-500 transform ${isVisible ? 'translate-y-0' : '-translate-y-full'
        } pt-4 md:pt-6 px-4 md:px-6`}>
        <div className={`container mx-auto max-w-7xl transition-all duration-500 rounded-[2rem] border border-white/5 ${isScrolled
          ? 'bg-ruta-black/60 backdrop-blur-xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] py-3'
          : 'bg-transparent py-4'
          }`}>
          <nav className="flex justify-between items-center px-6 md:px-10">
            {/* Logo Section */}
            <div className="flex items-center gap-3 group cursor-pointer" onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}>
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
                  key={link.label}
                  href={link.link}
                  className="text-xs font-bold uppercase tracking-[0.2em] text-ruta-white/60 hover:text-ruta-yellow transition-colors relative group py-2"
                >
                  {link.label}
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

            {/* Mobile Nav Button (Empty space for StaggeredMenu trigger) */}
            <div className="lg:hidden">
            </div>
          </nav>
        </div>
      </header>

      {/* Premium Mobile Menu Overlay */}
      <div className="lg:hidden">
        <StaggeredMenu
          isFixed={true}
          position="right"
          items={navLinks}
          socialItems={socialItems}
          colors={['#0a0a0b', '#1a1a1c', '#fac815']}
          accentColor="#fac815"
          logoUrl="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png"
          displayItemNumbering={true}
        />
      </div>
    </>
  );
}