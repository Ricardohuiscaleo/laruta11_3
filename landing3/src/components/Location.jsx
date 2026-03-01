import { MapPin, Clock, Calendar } from 'lucide-react';

export default function Location() {
  const currentHour = new Date().getHours();
  const isOpen = currentHour >= 18 || currentHour < 3;

  return (
    <section id="ubicacion" className="py-32 bg-ruta-dark relative overflow-hidden">
      {/* Background Decor */}
      <div className="absolute top-1/2 left-0 w-full h-[1px] bg-gradient-to-r from-transparent via-white/5 to-transparent"></div>

      <div className="container mx-auto px-6 relative z-10">
        <div className="grid lg:grid-cols-12 gap-16 items-start">

          {/* Info Column */}
          <div className="lg:col-span-5">
            <div className="flex items-center gap-3 mb-6">
              <span className="h-px w-8 bg-ruta-yellow"></span>
              <span className="text-ruta-yellow font-bold uppercase tracking-widest text-xs">Encuéntranos</span>
            </div>

            <h2 className="text-4xl md:text-6xl font-extrabold text-white tracking-tighter mb-8">
              Donde la <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-ruta-yellow to-ruta-orange">Ruta nos Lleve</span>
            </h2>

            <div className="p-10 rounded-[2.5rem] bg-ruta-black border border-white/5 shadow-2xl space-y-10">

              {/* Status Badge */}
              <div className="flex items-center gap-4">
                <div className={`w-3 h-3 rounded-full animate-pulse ${isOpen ? 'bg-green-500 shadow-[0_0_15px_rgba(34,197,94,0.5)]' : 'bg-ruta-red shadow-[0_0_15px_rgba(185,28,28,0.5)]'}`}></div>
                <span className="text-lg font-bold uppercase tracking-widest">{isOpen ? 'Abierto Ahora' : 'Cerrado Ahora'}</span>
              </div>

              <div className="space-y-6">
                <div className="flex items-start gap-4">
                  <Clock className="w-6 h-6 text-ruta-yellow mt-1" />
                  <div>
                    <h4 className="font-bold text-white mb-2">Horarios de Atención</h4>
                    <div className="space-y-2 text-sm text-ruta-white/50">
                      <p className="flex justify-between w-full border-b border-white/5 pb-2"><span>Lunes - Jueves</span> <span className="text-white">18:00 - 00:30</span></p>
                      <p className="flex justify-between w-full border-b border-white/5 pb-2"><span>Viernes - Sábado</span> <span className="text-white">18:00 - 03:00</span></p>
                      <p className="flex justify-between w-full"><span>Domingo</span> <span className="text-white">18:00 - 00:00</span></p>
                    </div>
                  </div>
                </div>

                <div className="flex items-start gap-4">
                  <MapPin className="w-6 h-6 text-ruta-yellow mt-1" />
                  <div>
                    <h4 className="font-bold text-white mb-2">Ubicación Actual</h4>
                    <p className="text-sm text-ruta-white/50 font-light">Actualizamos nuestra ubicación dinámicamente. Visítanos en nuestra ubicación principal en Arica.</p>
                  </div>
                </div>
              </div>

              <a
                href="https://maps.app.goo.gl/8RM68ErBdwgl3pkUE"
                target="_blank"
                className="block w-full text-center py-5 bg-white/5 border border-white/10 rounded-2xl font-bold text-ruta-white hover:bg-ruta-yellow hover:text-ruta-black transition-all no-underline"
              >
                Abrir en Google Maps
              </a>
            </div>
          </div>

          {/* Visual Column (Real Map) */}
          <div className="lg:col-span-7 relative h-full min-h-[500px]">
            <div className="absolute inset-0 rounded-[3rem] overflow-hidden border border-white/10 bg-ruta-black shadow-2xl">
              <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15137.082940291351!2d-70.30726709437847!3d-18.471391954398037!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x915aa9be349b5ac7%3A0x5a375eee0ddf0167!2sLa%20Ruta%2011!5e0!3m2!1ses!2scl!4v1766369155665!5m2!1ses!2scl"
                width="100%"
                height="100%"
                style={{ border: 0 }}
                allowFullScreen=""
                loading="lazy"
                referrerPolicy="no-referrer-when-downgrade"
                title="Ubicación La Ruta 11"
                className="grayscale opacity-80 hover:grayscale-0 transition-all duration-700"
              ></iframe>

              {/* Gradient Overlay for better UI integration */}
              <div className="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-ruta-dark to-transparent pointer-events-none"></div>
            </div>

            {/* Dynamic Card */}
            <div className="absolute bottom-8 left-8 right-8 p-6 bg-ruta-black/80 backdrop-blur-xl border border-white/10 rounded-3xl shadow-2xl">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 rounded-xl bg-ruta-yellow flex items-center justify-center text-ruta-black">
                  <Calendar className="w-6 h-6" />
                </div>
                <div>
                  <h5 className="font-bold text-white text-sm">Reserva para Eventos</h5>
                  <p className="text-xs text-ruta-white/50">Disponibilidad para todo el año en Arica.</p>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>
  );
}