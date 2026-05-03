import { useState, useEffect } from 'react';
import { Menu, X, Smartphone } from 'lucide-react';

export default function Header() {
  const [isOpen, setIsOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => { document.body.style.overflow = ''; };
  }, [isOpen]);

  const navLinks = [
    { label: 'Inicio', href: '#inicio' },
    { label: 'Servicios', href: '#servicios' },
    { label: 'Nosotros', href: '#nosotros' },
    { label: 'Menú', href: '#menu' },
    { label: 'Ubicación', href: '#contacto' },
  ];

  const handleNavClick = (e, href) => {
    e.preventDefault();
    setIsOpen(false);
    const el = document.querySelector(href);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth' });
    }
  };

  return (
    <header
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
        scrolled
          ? 'bg-white/95 backdrop-blur-xl border-b border-gray-100 shadow-sm'
          : 'bg-white/80 backdrop-blur-md'
      }`}
    >
      <nav className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16 sm:h-20">
          {/* Logo */}
          <a
            href="#inicio"
            onClick={(e) => handleNavClick(e, '#inicio')}
            className="flex items-center gap-2.5 group no-underline"
          >
            <img
              src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png"
              alt="La Ruta 11"
              className="h-9 sm:h-11 w-auto"
              draggable={false}
            />
            <span className="text-base sm:text-lg font-extrabold tracking-tight text-ruta-black uppercase">
              La Ruta <span className="text-ruta-red">11</span>
            </span>
          </a>

          {/* Desktop Nav */}
          <div className="hidden lg:flex items-center gap-1">
            {navLinks.map((link) => (
              <a
                key={link.label}
                href={link.href}
                onClick={(e) => handleNavClick(e, link.href)}
                className="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-ruta-red transition-colors duration-200 rounded-lg hover:bg-gray-50 no-underline"
              >
                {link.label}
              </a>
            ))}
          </div>

          {/* CTA + Mobile Toggle */}
          <div className="flex items-center gap-3">
            {/* CTA Button — always visible */}
            <a
              href="https://app.laruta11.cl"
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-2 bg-ruta-red hover:bg-red-700 text-white px-4 sm:px-6 py-2.5 rounded-full font-bold text-xs sm:text-sm uppercase tracking-wide transition-all duration-200 hover:shadow-lg hover:scale-105 no-underline"
            >
              <Smartphone className="w-4 h-4" />
              <span className="hidden sm:inline">Pedir Ahora</span>
              <span className="sm:hidden">Pedir</span>
            </a>

            {/* Mobile Menu Button */}
            <button
              onClick={() => setIsOpen(!isOpen)}
              className="lg:hidden flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 border border-gray-200 text-ruta-black hover:bg-gray-200 transition-colors"
              aria-label={isOpen ? 'Cerrar menú' : 'Abrir menú'}
              aria-expanded={isOpen}
            >
              {isOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
            </button>
          </div>
        </div>
      </nav>

      {/* Mobile Menu — Full screen overlay */}
      {isOpen && (
        <div className="lg:hidden fixed inset-0 z-[9999]">
          {/* Solid white background covering everything */}
          <div className="absolute inset-0 bg-white" />
          
          {/* Header area with close button */}
          <div className="relative z-10 flex items-center justify-between h-16 sm:h-20 px-4 sm:px-6 border-b border-gray-100">
            <a href="#inicio" onClick={(e) => handleNavClick(e, '#inicio')} className="flex items-center gap-2.5 no-underline">
              <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png" alt="La Ruta 11" className="h-9 sm:h-11 w-auto" draggable={false} />
              <span className="text-base sm:text-lg font-extrabold tracking-tight text-ruta-black uppercase">
                La Ruta <span className="text-ruta-red">11</span>
              </span>
            </a>
            <button
              onClick={() => setIsOpen(false)}
              className="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 border border-gray-200 text-ruta-black"
              aria-label="Cerrar menú"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Menu Content */}
          <div className="relative z-10 h-[calc(100vh-4rem)] sm:h-[calc(100vh-5rem)] flex flex-col px-6 pt-8 pb-12 bg-white overflow-y-auto">
            <div className="flex flex-col gap-1">
              {navLinks.map((link) => (
                <a
                  key={link.label}
                  href={link.href}
                  onClick={(e) => handleNavClick(e, link.href)}
                  className="text-2xl font-bold text-ruta-black hover:text-ruta-red py-3 border-b border-gray-100 no-underline"
                >
                  {link.label}
                </a>
              ))}
            </div>

            {/* Mobile CTA */}
            <div className="mt-auto pt-8">
              <a
                href="https://app.laruta11.cl"
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center justify-center gap-3 w-full bg-ruta-red hover:bg-red-700 text-white py-4 rounded-2xl font-bold text-lg no-underline"
                onClick={() => setIsOpen(false)}
              >
                <Smartphone className="w-5 h-5" />
                Pedir Ahora
              </a>
              <div className="flex items-center justify-center gap-6 mt-6">
                <a href="https://www.instagram.com/laruta11foodtruck/" target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-ruta-red text-sm font-medium no-underline">Instagram</a>
                <span className="text-gray-200">•</span>
                <a href="https://wa.me/56922504275" target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-ruta-green text-sm font-medium no-underline">WhatsApp</a>
              </div>
            </div>
          </div>
        </div>
      )}
    </header>
  );
}
