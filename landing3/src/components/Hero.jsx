import { useState, useEffect } from 'react';
import { Calculator, X, ChevronRight, Play, Star, MapPin } from 'lucide-react';

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
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const sendWhatsApp = () => {
    const whatsappNumber = '56922504275';
    const months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const eventDate = formData.eventDay && formData.eventMonth && formData.eventYear
      ? `${formData.eventDay} de ${months[parseInt(formData.eventMonth)]} de ${formData.eventYear}`
      : 'Por definir';

    const message = `> 🎉 *COTIZACIÓN LA RUTA 11*\n\n*👤 Cliente:* ${formData.name}\n\n*📋 Detalles del evento:*\n- *Tipo:* ${formData.eventType}\n- *Fecha:* ${eventDate}\n- *Invitados:* ${formData.guestCount}\n- *Ubicación:* ${formData.location}\n- *Duración:* ${formData.duration}\n\n*📝 Información adicional:*\n> ${formData.additionalInfo || 'Sin información adicional'}\n\n_¡Gracias por contactar La Ruta 11!_ 🍔`;

    const encodedMessage = encodeURIComponent(message);
    window.open(`https://wa.me/${whatsappNumber}?text=${encodedMessage}`, '_blank');
    closeModal();
  };

  const [googleData, setGoogleData] = useState({ rating: '4.9', count: '30+' });

  useEffect(() => {
    const fetchRating = async () => {
      try {
        const response = await fetch('https://app.laruta11.cl/api/get_google_reviews.php');
        const result = await response.json();
        if (result.success) {
          setGoogleData({ rating: result.rating.toString(), count: result.total_ratings.toString() });
        }
      } catch (error) { /* fallback values */ }
    };
    fetchRating();
  }, []);

  useEffect(() => {
    const img = new Image();
    img.onload = () => setImageLoaded(true);
    img.src = 'https://laruta11-images.s3.amazonaws.com/products/68d1fd67a7e42_WhatsApp%2520Image%25202025-09-22%2520at%252022.51.41.webp';
  }, []);

  return (
    <section id="inicio" className="relative min-h-screen flex items-center pt-24 pb-16 overflow-hidden bg-ruta-gray">
      {/* Subtle background pattern */}
      <div className="absolute inset-0 opacity-[0.03]" style={{ backgroundImage: 'radial-gradient(circle at 1px 1px, #000 1px, transparent 0)', backgroundSize: '40px 40px' }} />

      <div className="container mx-auto px-4 sm:px-6 relative z-10">
        <div className="grid lg:grid-cols-2 gap-8 lg:gap-16 items-center">

          {/* Content */}
          <div className={`transition-all duration-1000 delay-200 ${imageLoaded ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'}`}>
            {/* Badge */}
            <div className="inline-flex items-center gap-2 bg-white border border-gray-200 rounded-full px-4 py-2 mb-6 shadow-sm">
              <MapPin className="w-4 h-4 text-ruta-red" />
              <span className="text-sm font-semibold text-gray-700">Arica, Chile</span>
              <span className="w-2 h-2 bg-ruta-green rounded-full animate-pulse" />
            </div>

            <h1 className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold leading-[1.05] mb-6 tracking-tight">
              Sabor{' '}
              <span className="text-ruta-red">Artesanal</span>
              <br />
              <span className="text-ruta-orange">en tu mesa</span>
            </h1>

            <p className="text-lg sm:text-xl text-gray-600 max-w-lg mb-8 leading-relaxed">
              Hamburguesas premium, completos y churrascos con procesos 100% artesanales. La mejor experiencia gourmet urbana de Arica.
            </p>

            {/* CTA Buttons */}
            <div className="flex flex-col sm:flex-row gap-3 mb-10">
              <a
                href="https://app.laruta11.cl"
                target="_blank"
                rel="noopener noreferrer"
                className="group flex items-center justify-center gap-2 px-8 py-4 bg-ruta-red hover:bg-red-700 text-white rounded-2xl font-bold text-base sm:text-lg transition-all duration-200 hover:shadow-xl hover:scale-[1.02] no-underline"
              >
                Ver Menú y Pedir
                <ChevronRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </a>

              <button
                onClick={openModal}
                className="flex items-center justify-center gap-2 px-8 py-4 bg-white border-2 border-gray-200 text-ruta-black rounded-2xl font-bold text-base sm:text-lg hover:border-ruta-orange hover:text-ruta-orange transition-all duration-200"
              >
                <Calculator className="w-5 h-5" />
                Cotizar Evento
              </button>
            </div>

            {/* Stats */}
            <div className="flex flex-wrap items-center gap-6 sm:gap-8">
              <div className="flex items-center gap-2">
                <div className="flex">
                  {[1,2,3,4,5].map(i => <Star key={i} className="w-4 h-4 text-ruta-yellow fill-current" />)}
                </div>
                <span className="text-sm font-bold text-ruta-black">{googleData.rating}</span>
                <span className="text-sm text-gray-400">({googleData.count} reseñas)</span>
              </div>
              <div className="h-6 w-px bg-gray-200 hidden sm:block" />
              <div className="text-sm text-gray-500">
                <span className="font-bold text-ruta-black">2.000+</span> clientes felices
              </div>
            </div>
          </div>

          {/* Image */}
          <div className={`transition-all duration-[1200ms] delay-400 ${imageLoaded ? 'translate-y-0 opacity-100 scale-100' : 'translate-y-8 opacity-0 scale-95'}`}>
            <div className="relative">
              {/* Main image card */}
              <div className="relative rounded-3xl overflow-hidden shadow-2xl border border-gray-100 bg-white">
                <img
                  src="https://laruta11-images.s3.amazonaws.com/products/68d1fd67a7e42_WhatsApp%2520Image%25202025-09-22%2520at%252022.51.41.webp"
                  alt="Hamburguesa premium La Ruta 11"
                  className="w-full aspect-[4/5] sm:aspect-square object-cover"
                />
                {/* Overlay tag */}
                <div className="absolute top-4 left-4 bg-white/90 backdrop-blur-sm rounded-full px-4 py-2 shadow-lg border border-gray-100">
                  <span className="text-xs font-bold text-ruta-red uppercase tracking-wide">⭐ Favorita de Arica</span>
                </div>
                {/* Bottom info */}
                <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent p-6">
                  <h3 className="text-white font-bold text-lg">Doble Mixta (580g)</h3>
                  <p className="text-white/80 text-sm">400g carne premium + pollo filete + doble cheddar</p>
                </div>
              </div>

              {/* Floating accent */}
              <div className="absolute -bottom-4 -right-4 w-24 h-24 bg-ruta-yellow rounded-2xl -z-10 rotate-6" />
              <div className="absolute -top-4 -left-4 w-16 h-16 bg-ruta-red/10 rounded-2xl -z-10 -rotate-6" />
            </div>
          </div>
        </div>
      </div>

      {/* Quote Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
          <div className="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100">
            {/* Header */}
            <div className="sticky top-0 z-10 bg-white border-b border-gray-100 p-6 md:p-8 rounded-t-3xl">
              <div className="flex justify-between items-center">
                <div>
                  <h3 className="text-2xl font-extrabold text-ruta-black mb-1">🍴 Cotiza tu Evento</h3>
                  <p className="text-gray-500 text-sm">Llevamos el food truck a tu celebración.</p>
                </div>
                <button
                  onClick={closeModal}
                  className="w-10 h-10 flex items-center justify-center bg-gray-100 hover:bg-gray-200 rounded-full transition-colors"
                >
                  <X className="w-5 h-5 text-gray-600" />
                </button>
              </div>
            </div>

            {/* Form */}
            <div className="p-6 md:p-8 space-y-6">
              <div className="grid md:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="text-xs uppercase tracking-widest text-gray-400 font-bold">Nombre</label>
                  <input
                    type="text"
                    name="name"
                    value={formData.name}
                    onChange={handleInputChange}
                    placeholder="Tu nombre"
                    className="w-full bg-ruta-gray border border-gray-200 rounded-xl px-4 py-3 focus:border-ruta-red focus:ring-1 focus:ring-ruta-red focus:outline-none transition-all text-ruta-black"
                  />
                </div>
                <div className="space-y-1.5">
                  <label className="text-xs uppercase tracking-widest text-gray-400 font-bold">Tipo de Evento</label>
                  <select
                    name="eventType"
                    value={formData.eventType}
                    onChange={handleInputChange}
                    className="w-full bg-ruta-gray border border-gray-200 rounded-xl px-4 py-3 focus:border-ruta-red focus:outline-none text-ruta-black"
                  >
                    <option value="">Seleccionar...</option>
                    <option value="Matrimonio">Matrimonio</option>
                    <option value="Cumpleaños">Cumpleaños</option>
                    <option value="Corporativo">Corporativo</option>
                    <option value="Graduación">Graduación</option>
                    <option value="Otro">Otro</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-3 gap-4">
                <div className="space-y-1.5">
                  <label className="text-xs uppercase tracking-widest text-gray-400 font-bold">Invitados</label>
                  <input
                    type="number"
                    name="guestCount"
                    value={formData.guestCount}
                    onChange={handleInputChange}
                    placeholder="0"
                    className="w-full bg-ruta-gray border border-gray-200 rounded-xl px-4 py-3 focus:border-ruta-red focus:outline-none text-ruta-black"
                  />
                </div>
                <div className="col-span-2 space-y-1.5">
                  <label className="text-xs uppercase tracking-widest text-gray-400 font-bold">Ubicación</label>
                  <input
                    type="text"
                    name="location"
                    value={formData.location}
                    onChange={handleInputChange}
                    placeholder="Ciudad o dirección"
                    className="w-full bg-ruta-gray border border-gray-200 rounded-xl px-4 py-3 focus:border-ruta-red focus:outline-none text-ruta-black"
                  />
                </div>
              </div>

              <div className="flex gap-3 pt-4">
                <button
                  onClick={closeModal}
                  className="flex-1 px-6 py-3.5 rounded-xl border border-gray-200 text-gray-600 font-bold hover:bg-gray-50 transition-all"
                >
                  Cancelar
                </button>
                <button
                  onClick={sendWhatsApp}
                  className="flex-[2] bg-ruta-green hover:bg-green-700 text-white px-8 py-3.5 rounded-xl font-bold text-base shadow-lg transition-all active:scale-95 flex items-center justify-center gap-2"
                >
                  Enviar por WhatsApp
                  <Play className="w-4 h-4 fill-current" />
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </section>
  );
}
