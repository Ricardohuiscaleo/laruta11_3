import { useState, useEffect } from 'react';
import { Heart, Calculator, X, Calendar, Users, MapPin, Clock, ChevronRight, Play } from 'lucide-react';

export default function Hero() {
  const [imageLoaded, setImageLoaded] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    eventType: '',
    eventDay: new Date().getDate().toString(),
    eventMonth: (new Date().getMonth() + 1).toString(),
    eventYear: new Date().getFullYear().toString(),
    guestCount: '',
    location: '',
    duration: '',
    additionalInfo: ''
  });

  const openModal = () => setIsModalOpen(true);
  const closeModal = () => {
    setIsModalOpen(false);
    setFormData({
      name: '',
      eventType: '',
      eventDay: new Date().getDate().toString(),
      eventMonth: (new Date().getMonth() + 1).toString(),
      eventYear: new Date().getFullYear().toString(),
      guestCount: '',
      location: '',
      duration: '',
      additionalInfo: ''
    });
  };

  const handleInputChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  const sendWhatsApp = () => {
    const whatsappNumber = '56922504275';
    const months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const eventDate = formData.eventDay && formData.eventMonth && formData.eventYear
      ? `${formData.eventDay} de ${months[parseInt(formData.eventMonth)]} de ${formData.eventYear}`
      : 'Por definir';

    const message = `> 🎉 *COTIZACIÓN LA RUTA 11*

*👤 Cliente:* ${formData.name}

*📋 Detalles del evento:*
- *Tipo:* ${formData.eventType}
- *Fecha:* ${eventDate}
- *Invitados:* ${formData.guestCount}
- *Ubicación:* ${formData.location}
- *Duración:* ${formData.duration}

*📝 Información adicional:*
> ${formData.additionalInfo || 'Sin información adicional'}

_¡Gracias por contactar La Ruta 11!_ 🍔`;

    const encodedMessage = encodeURIComponent(message);
    const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodedMessage}`;
    window.open(whatsappUrl, '_blank');
    closeModal();
  };

  useEffect(() => {
    const img = new Image();
    img.onload = () => {
      setImageLoaded(true);
    };
    img.src = 'https://laruta11-images.s3.amazonaws.com/menu/1755574768_tomahawk-full-ig-portrait-1080-1350-2.png';
  }, []);

  return (
    <section
      id="inicio"
      className="relative min-h-[100vh] flex items-center pt-28 pb-20 overflow-hidden bg-ruta-dark"
    >
      {/* Dynamic Background */}
      <div className="absolute inset-0 z-0">
        <div
          className={`absolute inset-0 bg-cover bg-center bg-no-repeat transition-all duration-[2000ms] ease-in-out scale-110 ${imageLoaded ? 'opacity-40 scale-100 blur-none' : 'opacity-0 scale-125 blur-xl'}`}
          style={{
            backgroundImage: `url('https://laruta11-images.s3.amazonaws.com/menu/1755574768_tomahawk-full-ig-portrait-1080-1350-2.png')`,
          }}
        />

        {/* Gradients */}
        <div className="absolute inset-0 bg-gradient-to-b from-ruta-dark/80 via-ruta-dark/40 to-ruta-dark"></div>
        <div className="absolute inset-0 bg-gradient-to-r from-ruta-dark via-transparent to-transparent"></div>
      </div>

      <div className="container mx-auto px-6 relative z-10">
        <div className="grid lg:grid-cols-12 gap-12 items-center">

          {/* Main Content */}
          <div className="lg:col-span-7 xl:col-span-8">
            <div className={`transition-all duration-1000 delay-300 transform ${imageLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'}`}>
              <div className="flex items-center gap-3 mb-6">
                <span className="h-[2px] w-12 bg-ruta-yellow"></span>
                <span className="text-ruta-yellow font-bold uppercase tracking-[0.3em] text-xs sm:text-sm">
                  Premium Food Truck Experience
                </span>
              </div>

              <h1 className="text-5xl md:text-7xl lg:text-8xl font-extrabold leading-[1.1] mb-8 tracking-tighter uppercase">
                Sabor <br />
                <span className="text-transparent bg-clip-text bg-gradient-to-r from-ruta-yellow via-ruta-orange to-ruta-red">Artesanal,</span><br />
                Tecnología <span className="text-ruta-yellow italic">Urbana</span>
              </h1>

              <p className="text-lg md:text-xl text-ruta-white/80 max-w-2xl mb-10 leading-relaxed font-light">
                La mejor comida callejera gourmet de Arica. Expertos en brasas y sabores únicos, ahora con pedidos en vivo y juegos retro directo en nuestra App.
              </p>

              <div className="flex flex-col sm:flex-row gap-4">
                <a
                  href="https://app.laruta11.cl"
                  target="_blank"
                  className="group relative px-8 py-4 bg-ruta-yellow text-ruta-black rounded-full font-bold text-lg transition-all duration-300 hover:pr-12 hover:shadow-[0_0_30px_rgba(250,204,21,0.4)] flex items-center justify-center no-underline"
                >
                  Ver Menú Completo
                  <ChevronRight className="absolute right-4 w-5 h-5 opacity-0 group-hover:opacity-100 transition-all duration-300" />
                </a>

                <button
                  onClick={openModal}
                  className="group px-8 py-4 bg-white/5 backdrop-blur-md border border-white/10 text-white rounded-full font-semibold text-lg hover:bg-white/10 transition-all duration-300 flex items-center justify-center gap-3"
                >
                  <Calculator className="w-5 h-5 text-ruta-yellow transition-transform duration-500 group-hover:rotate-12" />
                  Cotizar Evento
                </button>
              </div>

              {/* Stats/Proof */}
              <div className="grid grid-cols-3 gap-8 mt-16 pt-10 border-t border-white/5 max-w-xl">
                <div>
                  <div className="text-3xl font-bold text-ruta-white mb-1">2k+</div>
                  <div className="text-xs uppercase tracking-widest text-ruta-white/40">Clientes Felices</div>
                </div>
                <div>
                  <div className="text-3xl font-bold text-ruta-white mb-1">11+</div>
                  <div className="text-xs uppercase tracking-widest text-ruta-white/40">Platos de Autor</div>
                </div>
                <div>
                  <div className="text-3xl font-bold text-ruta-white mb-1">4.9/5</div>
                  <div className="text-xs uppercase tracking-widest text-ruta-white/40">Valoración</div>
                </div>
              </div>
            </div>
          </div>

          {/* Floating Card Design (Visual Element) */}
          <div className="hidden lg:block lg:col-span-5 xl:col-span-4">
            <div className={`transition-all duration-[1500ms] delay-500 transform ${imageLoaded ? 'translate-x-0 opacity-100 scale-100' : 'translate-x-20 opacity-0 scale-95'}`}>
              <div className="relative p-2 rounded-[2.5rem] bg-gradient-to-br from-white/10 to-transparent border border-white/10 shadow-2xl backdrop-blur-sm overflow-hidden">
                <img
                  src="https://laruta11-images.s3.amazonaws.com/menu/1755574768_tomahawk-full-ig-portrait-1080-1350-2.png"
                  alt="Our Signature Dish"
                  className="w-full h-auto rounded-[2.2rem] object-cover transition-transform duration-700 hover:scale-105"
                />

                {/* Floating Tags */}
                <div className="absolute top-6 right-6 px-4 py-2 bg-ruta-dark/60 backdrop-blur-md rounded-xl border border-white/20 text-xs font-bold uppercase tracking-widest">
                  🔥 Live Fire Grill
                </div>

                <div className="absolute bottom-6 left-6 right-6 p-6 bg-ruta-dark/80 backdrop-blur-xl rounded-3xl border border-white/10 shadow-2xl">
                  <div className="flex justify-between items-center mb-2">
                    <h4 className="font-bold text-lg tracking-tight">Tomahawk Premium</h4>
                    <span className="text-ruta-yellow font-bold text-lg">$18.990</span>
                  </div>
                  <p className="text-sm text-ruta-white/60 mb-4 font-light leading-relaxed">
                    Nuestro corte estrella, asado a la perfección con sal de mar y hierbas ahumadas.
                  </p>
                  <div className="flex items-center gap-1">
                    {[1, 2, 3, 4, 5].map(i => <Heart key={i} className="w-3 h-3 text-ruta-red fill-current" />)}
                    <span className="ml-2 text-[10px] uppercase tracking-widest text-ruta-white/40 font-bold">580+ Recomiendan</span>
                  </div>
                </div>
              </div>

              {/* Secondary Element */}
              <div className="absolute -bottom-6 -left-12 p-5 bg-ruta-yellow rounded-3xl shadow-2xl animate-bounce duration-[3000ms]">
                <Play className="w-6 h-6 text-ruta-black fill-current" />
              </div>
            </div>
          </div>

        </div>
      </div>

      {/* Improved Quote Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-ruta-dark/95 backdrop-blur-md flex items-center justify-center z-[100] p-4">
          <div className="bg-ruta-black rounded-[2rem] max-w-2xl w-full max-h-[90vh] overflow-y-auto border border-white/10 shadow-2xl">
            {/* Header */}
            <div className="sticky top-0 z-10 bg-gradient-to-r from-ruta-red/20 to-ruta-orange/20 border-b border-white/10 p-6 md:p-8 backdrop-blur-xl">
              <div className="flex justify-between items-center">
                <div>
                  <h3 className="text-2xl font-extrabold tracking-tight mb-1">🍴 Reserva la Experiencia</h3>
                  <p className="text-ruta-white/60 text-sm">Llevamos todo el sabor del food truck a tu evento.</p>
                </div>
                <button
                  onClick={closeModal}
                  className="w-10 h-10 flex items-center justify-center bg-white/5 hover:bg-white/10 rounded-full transition-colors border border-white/10"
                >
                  <X className="w-5 h-5 text-ruta-white" />
                </button>
              </div>
            </div>

            {/* Form Content */}
            <div className="p-6 md:p-8 space-y-8">
              <div className="grid md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <label className="text-xs uppercase tracking-widest text-ruta-white/40 font-bold px-1">Customer Name</label>
                  <input
                    type="text"
                    name="name"
                    value={formData.name}
                    onChange={handleInputChange}
                    placeholder="E.g. John Doe"
                    className="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-4 focus:border-ruta-yellow focus:ring-1 focus:ring-ruta-yellow focus:outline-none transition-all placeholder:text-white/20"
                  />
                </div>

                <div className="space-y-2">
                  <label className="text-xs uppercase tracking-widest text-ruta-white/40 font-bold px-1">Event Type</label>
                  <select
                    name="eventType"
                    value={formData.eventType}
                    onChange={handleInputChange}
                    className="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-4 focus:border-ruta-yellow focus:outline-none appearance-none transition-all text-white/80"
                  >
                    <option value="" className="bg-ruta-black">Select an option...</option>
                    <option value="Matrimonio" className="bg-ruta-black">Matrimonio</option>
                    <option value="Cumpleaños" className="bg-ruta-black">Cumpleaños</option>
                    <option value="Corporativo" className="bg-ruta-black">Corporativo</option>
                    <option value="Graduación" className="bg-ruta-black">Graduación</option>
                    <option value="Otro" className="bg-ruta-black">Otro</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-3 gap-6">
                <div className="space-y-2">
                  <label className="text-xs uppercase tracking-widest text-ruta-white/40 font-bold px-1">Guests</label>
                  <input
                    type="number"
                    name="guestCount"
                    value={formData.guestCount}
                    onChange={handleInputChange}
                    placeholder="0"
                    className="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-4 focus:border-ruta-yellow focus:outline-none"
                  />
                </div>
                <div className="col-span-2 space-y-2">
                  <label className="text-xs uppercase tracking-widest text-ruta-white/40 font-bold px-1">Location</label>
                  <input
                    type="text"
                    name="location"
                    value={formData.location}
                    onChange={handleInputChange}
                    placeholder="City, Hall, or Address"
                    className="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-4 focus:border-ruta-yellow focus:outline-none"
                  />
                </div>
              </div>

              <div className="space-y-4 pt-4">
                <div className="flex gap-4 pt-4">
                  <button
                    onClick={closeModal}
                    className="flex-1 px-6 py-4 rounded-2xl border border-white/10 text-white font-bold hover:bg-white/5 transition-all"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={sendWhatsApp}
                    className="flex-2 bg-gradient-to-r from-ruta-yellow to-ruta-orange hover:from-ruta-yellow/90 hover:to-ruta-orange/90 text-ruta-black px-8 py-5 rounded-2xl font-bold text-lg shadow-2xl transition-all active:scale-95 flex items-center justify-center gap-3"
                  >
                    Enviar Cotización
                    <Play className="w-4 h-4 fill-current rotate-12" />
                  </button>
                </div>
                <p className="text-[10px] text-center text-ruta-white/20 uppercase tracking-widest">
                  Conexión segura vía WhatsApp Real-time Sync
                </p>
              </div>
            </div>
          </div>
        </div>
      )}
    </section>
  );
}