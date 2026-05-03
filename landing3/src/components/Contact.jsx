import { Mail, Instagram, Facebook, MessageCircle, ArrowUpRight } from 'lucide-react';

export default function Contact() {
  const socialLinks = [
    { name: 'WhatsApp', icon: MessageCircle, href: 'https://wa.me/56922504275', color: 'bg-green-500', handle: '+56 9 2250 4275' },
    { name: 'Instagram', icon: Instagram, href: 'https://www.instagram.com/la_ruta_11/', color: 'bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600', handle: '@la_ruta_11' },
    { name: 'Facebook', icon: Facebook, href: 'https://www.facebook.com/laruta11', color: 'bg-blue-600', handle: 'La Ruta11' },
    { name: 'Email', icon: Mail, href: 'mailto:hola@laruta11.cl', color: 'bg-ruta-red', handle: 'hola@laruta11.cl' }
  ];

  return (
    <section id="contacto" className="py-32 bg-white relative border-t border-gray-100">
      <div className="container mx-auto px-6">

        <div className="text-center mb-24">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-ruta-orange/20 bg-ruta-orange/5 text-ruta-orange text-[10px] uppercase tracking-[0.3em] font-bold mb-6">
            Redes y Feedback
          </div>
          <h2 className="text-4xl md:text-6xl font-extrabold text-ruta-black tracking-tighter mb-6">
            Mantente <span className="text-transparent bg-clip-text bg-gradient-to-r from-ruta-red to-ruta-orange">Conectado</span>
          </h2>
          <p className="text-lg text-gray-500 max-w-xl mx-auto font-light">
            Únete a nuestra comunidad en redes sociales para enterarte de nuestras ubicaciones diarias y promociones exclusivas.
          </p>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-7xl mx-auto">
          {socialLinks.map((social, index) => (
            <a
              key={index}
              href={social.href}
              target="_blank"
              rel="noopener noreferrer"
              className="group relative p-10 rounded-[2.5rem] bg-ruta-gray border border-gray-100 transition-all duration-500 hover:-translate-y-2 hover:shadow-lg hover:border-gray-200 no-underline"
            >
              <div className={`w-14 h-14 rounded-2xl ${social.color} flex items-center justify-center mb-8 shadow-lg transition-transform duration-500 group-hover:scale-110 group-hover:rotate-6`}>
                <social.icon className="w-7 h-7 text-white" />
              </div>

              <h3 className="text-xl font-bold text-ruta-black mb-2">{social.name}</h3>
              <p className="text-sm text-gray-400 font-medium tracking-tight mb-8">{social.handle}</p>

              <div className="flex items-center gap-2 text-gray-300 group-hover:text-ruta-red transition-colors font-bold text-xs uppercase tracking-[0.2em]">
                Seguir <ArrowUpRight className="w-4 h-4" />
              </div>
            </a>
          ))}
        </div>

        {/* Footer Bottom */}
        <div className="mt-32 pt-16 border-t border-gray-100">
          <div className="flex flex-col md:flex-row justify-between items-center gap-10">

            <div className="flex items-center gap-4">
              <img
                src="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png"
                alt="La Ruta 11 Logo"
                className="w-12 h-12 object-contain"
              />
              <div>
                <div className="text-lg font-black text-ruta-black px-1">LA RUTA <span className="text-ruta-red">11</span></div>
                <p className="text-[10px] text-gray-400 uppercase tracking-[0.2em] px-1 font-bold">Arica • Chile • Premium Food Trucks</p>
              </div>
            </div>

            <div className="text-center md:text-right space-y-4">
              <div className="flex items-center justify-center md:justify-end gap-8 text-[10px] font-bold uppercase tracking-widest text-gray-400">
                <a href="https://digitalizatodo.cl/politica-de-privacidad/" className="hover:text-ruta-red transition-colors no-underline">Privacidad</a>
                <a href="https://digitalizatodo.cl/terminos-y-condiciones/" className="hover:text-ruta-red transition-colors no-underline">Términos</a>
              </div>
              <p className="text-[10px] text-gray-300 uppercase tracking-widest">
                &copy; 2026 La Ruta 11 Food Trucks. Todo el sabor, sin los límites.
              </p>
              <div className="pt-2">
                <a href="https://digitalizatodo.cl" target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-ruta-gray border border-gray-200 text-[10px] font-bold text-gray-400 hover:text-ruta-red transition-all uppercase tracking-[0.2em] no-underline">
                  <span className="w-1.5 h-1.5 rounded-full bg-ruta-red animate-pulse"></span>
                  Administrado por digitalizatodo.cl
                </a>
              </div>
            </div>

          </div>
        </div>

      </div>
    </section>
  );
}
