import { MapPin, Clock, Calendar } from 'lucide-react';

export default function Location() {
  const currentHour = new Date().getHours();
  const isOpen = currentHour >= 18 || currentHour < 3;

  return (
    <section id="ubicacion" className="py-32 bg-ruta-gray relative overflow-hidden">
      {/* Background Decor */}
      <div className="absolute top-1/2 left-0 w-full h-[1px] bg-gradient-to-r from-transparent via-gray-200 to-transparent"></div>

      <div className="container mx-auto px-6 relative z-10">
        <div className="grid lg:grid-cols-12 gap-16 items-start">

          {/* Info Column */}
          <div className="lg:col-span-5">
            <div className="flex items-center gap-3 mb-6">
              <span className="h-px w-8 bg-ruta-orange"></span>
              <span className="text-ruta-orange font-bold uppercase tracking-widest text-xs">Encuéntranos</span>
            </div>

            <h2 className="text-4xl md:text-6xl font-extrabold text-ruta-black tracking-tighter mb-8">
              Donde la <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-ruta-red to-ruta-orange">Ruta nos Lleve</span>
            </h2>

            <div className="p-10 rounded-[2.5rem] bg-white border border-gray-100 shadow-lg space-y-10">

              {/* Status Badge */}
              <div className="flex items-center gap-4">
                <div className={`w-3 h-3 rounded-full animate-pulse ${isOpen ? 'bg-green-500 shadow-[0_0_15px_rgba(34,197,94,0.5)]' : 'bg-ruta-red shadow-[0_0_15px_rgba(220,38,38,0.5)]'}`}></div>
                <span className="text-lg font-bold uppercase tracking-widest text-ruta-black">{isOpen ? 'Abierto Ahora' : 'Cerrado Ahora'}</span>
              </div>

              <div className="space-y-6">
                <div className="flex items-start gap-4">
                  <Clock className="w-6 h-6 text-ruta-orange mt-1" />
                  <div>
                    <h4 className="font-bold text-ruta-black mb-2">Horarios de Atención</h4>
                    <div className="space-y-2 text-sm text-gray-500">
                      <p className="flex justify-between w-full border-b border-gray-100 pb-2"><span>Lunes - Jueves</span> <span className="text-ruta-black">18:00 - 00:30</span></p>
                      <p className="flex justify-between w-full border-b border-gray-100 pb-2"><span>Viernes - Sábado</span> <span className="text-ruta-black">18:00 - 03:00</span></p>
                      <p className="flex justify-between w-full"><span>Domingo</span> <span className="text-ruta-black">18:00 - 00:00</span></p>
                    </div>
                  </div>
                </div>

                <div className="flex items-start gap-4">
                  <MapPin className="w-6 h-6 text-ruta-orange mt-1" />
                  <div>
                    <h4 className="font-bold text-ruta-black mb-2">Ubicación Actual</h4>
                    <p className="text-sm text-gray-500 font-light">Actualizamos nuestra ubicación dinámicamente. Visítanos en nuestra ubicación principal en Arica.</p>
                  </div>
                </div>
              </div>

              <a
                href="https://www.google.com/maps/place/?q=place_id:ChIJx1qbNL6pWpERZwHfDe5eN1o"
                target="_blank"
                className="block w-full text-center py-5 bg-ruta-gray border border-gray-200 rounded-2xl font-bold text-ruta-black hover:bg-ruta-red hover:text-white hover:border-ruta-red transition-all no-underline"
              >
                Abrir en Google Maps
              </a>
            </div>
          </div>

          {/* Visual Column (Real Map) */}
          <div className="lg:col-span-7 relative h-full min-h-[500px]">
            <div className="absolute inset-0 rounded-[3rem] overflow-hidden border border-gray-100 bg-white shadow-lg">
              <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15137.082940291351!2d-70.30726709437847!3d-18.471391954398037!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x915aa9be349b5ac7%3A0x5a375eee0ddf0167!2sLa%20Ruta%2011!5e0!3m2!1ses!2scl!4v1766369155665!5m2!1ses!2scl"
                width="100%"
                height="100%"
                style={{ border: 0 }}
                allowFullScreen=""
                loading="lazy"
                referrerPolicy="no-referrer-when-downgrade"
                title="Ubicación La Ruta 11"
                className="hover:grayscale-0 transition-all duration-700"
              ></iframe>

              {/* Gradient Overlay for better UI integration */}
              <div className="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-ruta-gray to-transparent pointer-events-none"></div>
            </div>

            {/* Dynamic Card */}
            <div className="absolute bottom-8 left-8 right-8 p-6 bg-white/90 backdrop-blur-xl border border-gray-100 rounded-3xl shadow-lg">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 rounded-xl bg-ruta-red flex items-center justify-center text-white">
                  <Calendar className="w-6 h-6" />
                </div>
                <div>
                  <h5 className="font-bold text-ruta-black text-sm">Reserva para Eventos</h5>
                  <p className="text-xs text-gray-500">Disponibilidad para todo el año en Arica.</p>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>
  );
}
