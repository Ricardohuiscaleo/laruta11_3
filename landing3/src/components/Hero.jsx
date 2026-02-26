import { useState, useEffect } from 'react';
import { Heart, Calculator, X, Calendar, Users, MapPin, Clock } from 'lucide-react';

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
      // Delay mínimo para imagen ultra comprimida
      setTimeout(() => setImageLoaded(true), 200);
    };
    img.src = 'https://laruta11-images.s3.amazonaws.com/menu/1755574768_tomahawk-full-ig-portrait-1080-1350-2.png';
  }, []);

  return (
    <section
      id="inicio"
      className="relative min-h-screen flex items-center pt-20 overflow-hidden"
      style={{
        backgroundImage: `linear-gradient(135deg, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.4)), url('https://laruta11-images.s3.amazonaws.com/menu/1755574768_tomahawk-full-ig-portrait-1080-1350-2.png')`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        backgroundAttachment: 'fixed'
      }}
    >
      {/* Overlay de transición */}
      <div className={`absolute inset-0 bg-black transition-opacity duration-[2000ms] ease-out ${imageLoaded ? 'opacity-0' : 'opacity-100'
        }`}></div>

      {/* Overlay animado adicional */}
      <div className="absolute inset-0 bg-gradient-to-r from-black/30 via-transparent to-black/20 animate-pulse"></div>

      <div className="container mx-auto px-6 relative z-10">
        <div className="flex justify-center items-center">
          <div className="text-ruta-white text-center max-w-4xl">

            <div className="mb-4">
              <span className="inline-block bg-yellow-400 text-ruta-black px-4 py-2 rounded-full text-sm font-bold uppercase tracking-wide">
                Food Trucks Gourmet
              </span>
            </div>
            <h1 className="text-4xl sm:text-5xl md:text-7xl font-bold mb-4 md:mb-6 leading-tight">
              Arica Tiene <span className="text-yellow-400">Sabor</span>
            </h1>
            <p className="text-lg sm:text-xl md:text-2xl mb-3 md:mb-4 opacity-90 font-light">
              Experiencia gastronómica premium
            </p>
            <p className="text-sm sm:text-base md:text-lg mb-6 md:mb-8 opacity-75 px-4 sm:px-0">
              Los mejores food trucks de Arica • Ingredientes frescos • Sabores únicos
            </p>


            <div className="flex flex-row gap-2 sm:gap-4 justify-center px-2 sm:px-0">
              <div className="relative group">
                <a href="https://app.laruta11.cl" target="_blank" className="flex-1 sm:w-auto bg-yellow-400 text-ruta-black px-3 sm:px-8 py-3 sm:py-4 rounded-full font-bold text-sm sm:text-lg border-2 border-transparent hover:bg-black/70 hover:backdrop-blur-sm hover:text-white hover:border-yellow-400 transition-all transform hover:scale-105 shadow-lg flex items-center gap-1 sm:gap-2 justify-center no-underline">
                  <Heart className="w-4 h-4 sm:w-5 sm:h-5 text-red-500 fill-red-500" />
                  Ver Nuestro Menú
                </a>
                <div className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-black text-white text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">
                  Descubre nuestras especialidades
                </div>
              </div>
              <div className="relative group">
                <button
                  onClick={openModal}
                  className="flex-1 sm:w-auto bg-white/[0.01] backdrop-blur-md border-2 border-yellow-400 text-yellow-400 px-3 sm:px-8 py-3 sm:py-4 rounded-full font-semibold text-sm sm:text-lg hover:bg-yellow-400 hover:text-ruta-black transition-all flex items-center gap-1 sm:gap-2 justify-center cursor-pointer"
                >
                  <Calculator className="w-4 h-4 sm:w-5 sm:h-5" />
                  Cotizar Servicios
                </button>
                <div className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-black text-white text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">
                  Solicitar cotización personalizada
                </div>
              </div>
            </div>


          </div>
        </div>
      </div>

      {/* Modern Quotation Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-2 sm:p-4">
          <div className="bg-white rounded-2xl sm:rounded-3xl max-w-2xl w-full max-h-[95vh] sm:max-h-[90vh] overflow-y-auto shadow-2xl">
            {/* Header */}
            <div className="sticky top-0 bg-gradient-to-r from-ruta-red to-ruta-orange text-white p-4 sm:p-6 rounded-t-2xl sm:rounded-t-3xl">
              <div className="flex justify-between items-center">
                <div className="flex items-center gap-3">
                  <img
                    src="https://laruta11-images.s3.amazonaws.com/menu/1755571382_test.jpg"
                    alt="La Ruta11 Logo"
                    className="w-8 h-8 sm:w-10 sm:h-10 rounded-full object-cover border-2 border-white/30"
                  />
                  <div>
                    <h3 className="text-lg sm:text-2xl font-bold">🍴 Cotizar Servicio</h3>
                    <p className="text-white/90 text-xs sm:text-sm">Cuéntanos sobre tu evento</p>
                  </div>
                </div>
                <button
                  onClick={closeModal}
                  className="text-white/80 hover:text-white transition-colors p-2 hover:bg-white/10 rounded-full"
                >
                  <X className="w-5 h-5 sm:w-6 sm:h-6" />
                </button>
              </div>
            </div>

            {/* Form */}
            <div className="p-4 sm:p-6 space-y-4 sm:space-y-6">
              {/* Personal Info */}
              <div>
                <label className="block text-gray-700 font-semibold mb-2 text-sm sm:text-base">👤 Nombre completo</label>
                <input
                  type="text"
                  name="name"
                  value={formData.name}
                  onChange={handleInputChange}
                  placeholder="Tu nombre"
                  className="w-full p-3 sm:p-4 border-2 border-gray-200 rounded-xl focus:border-ruta-red focus:outline-none transition-colors text-sm sm:text-base"
                />
              </div>

              {/* Event Details */}
              <div className="grid sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-gray-700 font-semibold mb-2 sm:mb-3 text-sm sm:text-base">🎉 Tipo de evento</label>
                  <div className="relative">
                    <select
                      name="eventType"
                      value={formData.eventType}
                      onChange={handleInputChange}
                      className="w-full p-3 sm:p-4 bg-gradient-to-r from-white to-gray-50 border-2 border-gray-200 rounded-xl sm:rounded-2xl focus:border-ruta-red focus:from-red-50 focus:to-orange-50 focus:outline-none transition-all duration-300 shadow-sm hover:shadow-md appearance-none cursor-pointer font-medium text-sm sm:text-base"
                    >
                      <option value="">✨ Seleccionar tipo de evento</option>
                      <option value="Matrimonio">💒 Matrimonio</option>
                      <option value="Cumpleaños">🎂 Cumpleaños</option>
                      <option value="Evento Corporativo">🏢 Evento Corporativo</option>
                      <option value="Graduación">🎓 Graduación</option>
                      <option value="Fiesta Privada">🎊 Fiesta Privada</option>
                      <option value="Otro">🎯 Otro</option>
                    </select>
                    <div className="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                      <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                      </svg>
                    </div>
                  </div>
                </div>
                <div>
                  <label className="block text-gray-700 font-semibold mb-2 sm:mb-3 text-sm sm:text-base">📅 Fecha del evento</label>
                  <div className="grid grid-cols-3 gap-2 sm:gap-3">
                    <div className="relative">
                      <select
                        name="eventDay"
                        value={formData.eventDay}
                        onChange={handleInputChange}
                        className="w-full p-2 sm:p-4 bg-gradient-to-br from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-xl sm:rounded-2xl focus:border-blue-500 focus:from-blue-100 focus:to-indigo-100 focus:outline-none transition-all duration-300 shadow-sm hover:shadow-md appearance-none cursor-pointer font-medium text-center text-xs sm:text-base"
                      >
                        <option value="">Día</option>
                        {Array.from({ length: 31 }, (_, i) => i + 1).map(day => (
                          <option key={day} value={day}>{day}</option>
                        ))}
                      </select>
                      <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg className="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                        </svg>
                      </div>
                    </div>
                    <div className="relative">
                      <select
                        name="eventMonth"
                        value={formData.eventMonth}
                        onChange={handleInputChange}
                        className="w-full p-2 sm:p-4 bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl sm:rounded-2xl focus:border-green-500 focus:from-green-100 focus:to-emerald-100 focus:outline-none transition-all duration-300 shadow-sm hover:shadow-md appearance-none cursor-pointer font-medium text-center text-xs sm:text-base"
                      >
                        <option value="">Mes</option>
                        <option value="1">Enero</option>
                        <option value="2">Febrero</option>
                        <option value="3">Marzo</option>
                        <option value="4">Abril</option>
                        <option value="5">Mayo</option>
                        <option value="6">Junio</option>
                        <option value="7">Julio</option>
                        <option value="8">Agosto</option>
                        <option value="9">Septiembre</option>
                        <option value="10">Octubre</option>
                        <option value="11">Noviembre</option>
                        <option value="12">Diciembre</option>
                      </select>
                      <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg className="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                        </svg>
                      </div>
                    </div>
                    <div className="relative">
                      <select
                        name="eventYear"
                        value={formData.eventYear}
                        onChange={handleInputChange}
                        className="w-full p-2 sm:p-4 bg-gradient-to-br from-purple-50 to-pink-50 border-2 border-purple-200 rounded-xl sm:rounded-2xl focus:border-purple-500 focus:from-purple-100 focus:to-pink-100 focus:outline-none transition-all duration-300 shadow-sm hover:shadow-md appearance-none cursor-pointer font-medium text-center text-xs sm:text-base"
                      >
                        <option value="">Año</option>
                        {Array.from({ length: 3 }, (_, i) => new Date().getFullYear() + i).map(year => (
                          <option key={year} value={year}>{year}</option>
                        ))}
                      </select>
                      <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg className="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                        </svg>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div className="grid sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-gray-700 font-semibold mb-2 sm:mb-3 text-sm sm:text-base">👥 Número de invitados</label>
                  <div className="relative">
                    <select
                      name="guestCount"
                      value={formData.guestCount}
                      onChange={handleInputChange}
                      className="w-full p-3 sm:p-4 bg-gradient-to-r from-amber-50 to-yellow-50 border-2 border-amber-200 rounded-xl sm:rounded-2xl focus:border-amber-500 focus:from-amber-100 focus:to-yellow-100 focus:outline-none transition-all duration-300 shadow-sm hover:shadow-md appearance-none cursor-pointer font-medium text-sm sm:text-base"
                    >
                      <option value="">👥 Seleccionar cantidad de invitados</option>
                      <option value="10-25">🥂 10-25 personas (Íntimo)</option>
                      <option value="26-50">🍽️ 26-50 personas (Mediano)</option>
                      <option value="51-100">🎉 51-100 personas (Grande)</option>
                      <option value="101-200">🎊 101-200 personas (Muy Grande)</option>
                      <option value="200+">🏟️ Más de 200 personas (Masivo)</option>
                    </select>
                    <div className="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                      <svg className="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                      </svg>
                    </div>
                  </div>
                </div>
                <div>
                  <label className="block text-gray-700 font-semibold mb-2 sm:mb-3 text-sm sm:text-base">⏰ Duración estimada</label>
                  <div className="relative">
                    <select
                      name="duration"
                      value={formData.duration}
                      onChange={handleInputChange}
                      className="w-full p-3 sm:p-4 bg-gradient-to-r from-teal-50 to-cyan-50 border-2 border-teal-200 rounded-xl sm:rounded-2xl focus:border-teal-500 focus:from-teal-100 focus:to-cyan-100 focus:outline-none transition-all duration-300 shadow-sm hover:shadow-md appearance-none cursor-pointer font-medium text-sm sm:text-base"
                    >
                      <option value="">⏰ Seleccionar duración del evento</option>
                      <option value="2-3 horas">⚡ 2-3 horas (Express)</option>
                      <option value="4-5 horas">🕐 4-5 horas (Estándar)</option>
                      <option value="6-8 horas">🌅 6-8 horas (Extendido)</option>
                      <option value="Todo el día">🌞 Todo el día (Completo)</option>
                    </select>
                    <div className="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                      <svg className="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                      </svg>
                    </div>
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-gray-700 font-semibold mb-2 text-sm sm:text-base">📍 Ubicación del evento</label>
                <input
                  type="text"
                  name="location"
                  value={formData.location}
                  onChange={handleInputChange}
                  placeholder="Dirección o lugar del evento"
                  className="w-full p-3 sm:p-4 border-2 border-gray-200 rounded-xl focus:border-ruta-red focus:outline-none transition-colors text-sm sm:text-base"
                />
              </div>

              <div>
                <label className="block text-gray-700 font-semibold mb-2 text-sm sm:text-base">💬 Información adicional</label>
                <textarea
                  name="additionalInfo"
                  value={formData.additionalInfo}
                  onChange={handleInputChange}
                  placeholder="Cuéntanos más detalles sobre tu evento, preferencias de menú, etc."
                  className="w-full p-3 sm:p-4 border-2 border-gray-200 rounded-xl resize-none h-20 sm:h-24 focus:border-ruta-red focus:outline-none transition-colors text-sm sm:text-base"
                />
              </div>
            </div>

            {/* Footer */}
            <div className="p-4 sm:p-6 bg-gray-50 rounded-b-2xl sm:rounded-b-3xl">
              <div className="flex flex-col sm:flex-row gap-3">
                <button
                  onClick={closeModal}
                  className="flex-1 bg-gray-200 text-gray-700 py-3 sm:py-4 px-4 sm:px-6 rounded-xl font-semibold hover:bg-gray-300 transition-colors text-sm sm:text-base"
                >
                  Cancelar
                </button>
                <button
                  onClick={sendWhatsApp}
                  disabled={!formData.name || !formData.eventType}
                  className="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white py-3 sm:py-4 px-4 sm:px-6 rounded-xl font-semibold hover:from-green-600 hover:to-green-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 text-sm sm:text-base"
                >
                  <span>📱</span>
                  <span className="hidden xs:inline">Enviar por WhatsApp</span>
                  <span className="xs:hidden">WhatsApp</span>
                </button>
              </div>
              <p className="text-xs text-gray-500 text-center mt-3">
                Al enviar, serás redirigido a WhatsApp con tu cotización estructurada
              </p>
            </div>
          </div>
        </div>
      )}
    </section>
  );
}