import { MapPin, Clock, Calendar } from 'lucide-react';

export default function Location() {
  const currentHour = new Date().getHours();
  const isOpen = currentHour >= 19 || currentHour < 1; // 7 PM to 1 AM

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

            <h2 className="text-4xl md:text-6xl font-extrabold text-white tracking-tighter mb-8 italic">
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
                    <h4 className="font-bold text-white mb-2">Horarios de Servicio</h4>
                    <div className="space-y-2 text-sm text-ruta-white/50">
                      <p className="flex justify-between w-full border-b border-white/5 pb-2"><span>Lunes - Jueves</span> <span className="text-white">19:00 - 01:00</span></p>
                      <p className="flex justify-between w-full border-b border-white/5 pb-2"><span>Viernes - Sábado</span> <span className="text-white">19:00 - 06:00</span></p>
                      <p className="flex justify-between w-full"><span>Domingo</span> <span className="text-ruta-red font-bold">CERRADO</span></p>
                    </div>
                  </div>
                </div>

                <div className="flex items-start gap-4">
                  <MapPin className="w-6 h-6 text-ruta-yellow mt-1" />
                  <div>
                    <h4 className="font-bold text-white mb-2">Ubicación Actual</h4>
                    <p className="text-sm text-ruta-white/50 font-light">Actualizamos nuestra ubicación diariamente en nuestras redes sociales y App.</p>
                  </div>
                </div>
              </div>

              <a
                href="https://app.laruta11.cl"
                target="_blank"
                className="block w-full text-center py-5 bg-white/5 border border-white/10 rounded-2xl font-bold text-ruta-white hover:bg-ruta-yellow hover:text-ruta-black transition-all no-underline"
              >
                Ver Mapa en Tiempo Real
              </a>
            </div>
          </div>

          {/* Visual Column (Placeholder for Map/Truck Photo) */}
          <div className="lg:col-span-7 relative h-full min-h-[500px]">
            <div className="absolute inset-0 rounded-[3rem] overflow-hidden border border-white/10 bg-ruta-black">
              {/* Simulated Map / High-end Photo */}
              <div className="absolute inset-0 opacity-40 bg-[url('https://laruta11-images.s3.amazonaws.com/menu/1755631926_WhatsApp%20Image%202025-07-06%20at%2015.34.52.jpeg')] bg-cover bg-center grayscale contrast-125"></div>
              <div className="absolute inset-0 bg-gradient-to-tr from-ruta-dark via-transparent to-ruta-dark/20"></div>

              {/* Floating Map Pin Effect */}
              <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                <div className="relative">
                  <div className="absolute inset-0 bg-ruta-yellow blur-xl opacity-60 animate-ping"></div>
                  <div className="relative bg-ruta-yellow p-4 rounded-2xl border-4 border-ruta-dark shadow-2xl">
                    <MapPin className="w-8 h-8 text-ruta-black fill-current" />
                  </div>
                </div>
              </div>
            </div>

            {/* Dynamic Card */}
            <div className="absolute bottom-8 left-8 right-8 p-6 bg-ruta-black/80 backdrop-blur-xl border border-white/10 rounded-3xl shadow-2xl">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 rounded-xl bg-ruta-yellow flex items-center justify-center text-ruta-black">
                  <Calendar className="w-6 h-6" />
                </div>
                <div>
                  <h5 className="font-bold text-white text-sm">Reserva para Eventos</h5>
                  <p className="text-xs text-ruta-white/50">Disponibilidad para todo el año 2025.</p>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>
  );
}