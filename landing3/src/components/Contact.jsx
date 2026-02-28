import { Phone, Mail, Instagram, Facebook, MessageCircle, ArrowUpRight } from 'lucide-react';

export default function Contact() {
  const socialLinks = [
    { name: 'WhatsApp', icon: MessageCircle, href: 'https://wa.me/56922504275', color: 'bg-green-500', handle: '+56 9 2250 4275' },
    { name: 'Instagram', icon: Instagram, href: 'https://www.instagram.com/la_ruta_11/', color: 'bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600', handle: '@la_ruta_11' },
    { name: 'Facebook', icon: Facebook, href: 'https://www.facebook.com/laruta11', color: 'bg-blue-600', handle: 'La Ruta11' },
    { name: 'Email', icon: Mail, href: 'mailto:hola@laruta11.cl', color: 'bg-ruta-red', handle: 'hola@laruta11.cl' }
  ];

  return (
    <section id="contacto" className="py-32 bg-ruta-black relative border-t border-white/5">
      <div className="container mx-auto px-6">

        <div className="text-center mb-24">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-ruta-yellow/20 bg-ruta-yellow/5 text-ruta-yellow text-[10px] uppercase tracking-[0.3em] font-bold mb-6">
            Social & Feedback
          </div>
          <h2 className="text-4xl md:text-6xl font-extrabold text-white tracking-tighter mb-6 italic">
            Mantente <span className="text-transparent bg-clip-text bg-gradient-to-r from-ruta-yellow to-ruta-orange">Conectado</span>
          </h2>
          <p className="text-lg text-ruta-white/40 max-w-xl mx-auto font-light">
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
              className="group relative p-10 rounded-[2.5rem] bg-ruta-dark border border-white/5 transition-all duration-500 hover:-translate-y-2 hover:border-white/20 no-underline"
            >
              <div className={`w-14 h-14 rounded-2xl ${social.color} flex items-center justify-center mb-8 shadow-2xl transition-transform duration-500 group-hover:scale-110 group-hover:rotate-6`}>
                <social.icon className="w-7 h-7 text-white" />
              </div>

              <h3 className="text-xl font-bold text-white mb-2">{social.name}</h3>
              <p className="text-sm text-ruta-white/40 font-medium tracking-tight mb-8">{social.handle}</p>

              <div className="flex items-center gap-2 text-ruta-white/20 group-hover:text-ruta-yellow transition-colors font-bold text-xs uppercase tracking-[0.2em]">
                Seguir <ArrowUpRight className="w-4 h-4" />
              </div>

              {/* Corner Accent */}
              <div className="absolute top-6 right-6 opacity-0 group-hover:opacity-100 transition-opacity">
                <div className={`w-2 h-2 rounded-full ${social.color} blur-[2px]`}></div>
              </div>
            </a>
          ))}
        </div>

        {/* Footer Bottom */}
        <div className="mt-32 pt-16 border-t border-white/5">
          <div className="flex flex-col md:flex-row justify-between items-center gap-10">

            <div className="flex items-center gap-4">
              <img
                src="https://laruta11-images.s3.amazonaws.com/menu/1755571382_test.jpg"
                alt="Logo"
                className="w-12 h-12 rounded-full object-cover grayscale opacity-50"
              />
              <div>
                <div className="text-lg font-black text-white px-1">LA RUTA<span className="text-ruta-yellow">11</span></div>
                <p className="text-[10px] text-ruta-white/30 uppercase tracking-[0.2em] px-1 font-bold">Arica • Chile • Premium Food Trucks</p>
              </div>
            </div>

            <div className="text-center md:text-right space-y-4">
              <div className="flex items-center justify-center md:justify-end gap-8 text-[10px] font-bold uppercase tracking-widest text-ruta-white/30">
                <a href="https://agenterag.com/politica-de-privacidad/" className="hover:text-ruta-yellow transition-colors">Privacidad</a>
                <a href="https://agenterag.com/terminos-y-condiciones/" className="hover:text-ruta-yellow transition-colors">Términos</a>
              </div>
              <p className="text-[10px] text-ruta-white/20 uppercase tracking-widest">
                &copy; 2025 La Ruta 11 Food Trucks. Todo el sabor, sin los límites.
              </p>
              <div className="pt-2">
                <a href="https://agenterag.com" target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/5 text-[10px] font-bold text-ruta-white/40 hover:text-ruta-yellow transition-all uppercase tracking-[0.2em] no-underline">
                  <span className="w-1.5 h-1.5 rounded-full bg-ruta-yellow animate-pulse"></span>
                  Crafted by agenterag.com
                </a>
              </div>
            </div>

          </div>
        </div>

      </div>
    </section>
  );
}