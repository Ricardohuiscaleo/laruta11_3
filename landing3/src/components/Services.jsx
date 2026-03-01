import { Truck, UtensilsCrossed, Smartphone, TrendingUp, ChefHat, Leaf, X, ChevronRight, Gamepad2 } from 'lucide-react';
import { useState } from 'react';

export default function Services() {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [message, setMessage] = useState('');

  const openModal = () => setIsModalOpen(true);
  const closeModal = () => {
    setIsModalOpen(false);
    setMessage('');
  };

  const sendWhatsApp = () => {
    const whatsappNumber = '56922504275';
    const encodedMessage = encodeURIComponent(message || 'Hola, me interesa cotizar un servicio de La Ruta 11');
    const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodedMessage}`;
    window.open(whatsappUrl, '_blank');
    closeModal();
  };

  const services = [
    {
      icon: Truck,
      title: "Catering & Eventos",
      description: "Llevamos la experiencia del food truck a tu fiesta, matrimonio o evento corporativo con propuestas a medida.",
      gradient: "from-ruta-red/20 to-ruta-orange/20",
      accent: "text-ruta-red"
    },
    {
      icon: Gamepad2,
      title: "Juega y Gana",
      description: "Accede a descuentos exclusivos en nuestra App superando récords en Galaga y Pacman mientras esperas tu pedido.",
      gradient: "from-ruta-yellow/10 to-transparent",
      accent: "text-ruta-yellow"
    },
    {
      icon: Smartphone,
      title: "Sáltate la Fila",
      description: "Pide desde tu móvil, paga de forma segura y recibe notificaciones en tiempo real cuando tu pedido esté listo.",
      gradient: "from-blue-900/20 to-transparent",
      accent: "text-blue-400"
    }
  ];

  return (
    <section id="servicios" className="py-32 bg-ruta-dark">
      <div className="container mx-auto px-6">
        <div className="text-center mb-24">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-ruta-yellow/20 bg-ruta-yellow/5 text-ruta-yellow text-[10px] uppercase tracking-[0.3em] font-bold mb-6">
            <span className="relative flex h-2 w-2">
              <span className="relative inline-flex rounded-full h-2 w-2 bg-ruta-yellow/80"></span>
            </span>
            Nuestras Soluciones
          </div>
          <h2 className="text-4xl md:text-6xl font-extrabold text-white tracking-tighter mb-6">
            Experiencia <span className="text-transparent bg-clip-text bg-gradient-to-r from-ruta-yellow to-ruta-orange">Sin Límites</span>
          </h2>
          <p className="text-lg text-ruta-white/60 max-w-2xl mx-auto font-light leading-relaxed">
            Más que comida, ofrecemos una infraestructura gastronómica completa para cualquier ocasión.
          </p>
        </div>

        <div className="grid md:grid-cols-3 gap-8">
          {services.map((service, index) => (
            <div
              key={index}
              className="group p-10 rounded-[2rem] bg-ruta-black border border-white/5 transition-all duration-500 hover:bg-white/5 hover:-translate-y-2"
            >
              <div className={`w-16 h-16 rounded-2xl bg-white/5 flex items-center justify-center mb-8 border border-white/10 ${service.accent}`}>
                <service.icon className="w-8 h-8" />
              </div>
              <h3 className="text-2xl font-bold text-white mb-4">{service.title}</h3>
              <p className="text-ruta-white/50 text-sm leading-relaxed mb-6">
                {service.description}
              </p>
              <div className="flex items-center gap-2 text-ruta-yellow text-xs font-bold uppercase tracking-widest cursor-pointer group-hover:gap-4 transition-all" onClick={openModal}>
                Consultar <ChevronRight className="w-4 h-4" />
              </div>
            </div>
          ))}
        </div>

        {/* Dynamic CTA */}
        <div className="mt-16 p-1 rounded-[2.5rem] bg-gradient-to-r from-ruta-yellow/20 via-white/5 to-ruta-red/20">
          <div className="bg-ruta-black rounded-[2.4rem] p-10 md:p-16 flex flex-col md:flex-row items-center justify-between gap-10">
            <div className="text-center md:text-left">
              <h3 className="text-3xl font-bold text-white mb-2">¿Tienes un evento especial?</h3>
              <p className="text-ruta-white/50 font-light">Diseñamos una propuesta gastronómica a tu medida.</p>
            </div>
            <button
              onClick={openModal}
              className="bg-ruta-yellow text-ruta-black px-10 py-5 rounded-full font-bold text-lg hover:scale-105 transition-all shadow-[0_10px_30px_rgba(250,204,21,0.2)] whitespace-nowrap"
            >
              Cotizar Ahora
            </button>
          </div>
        </div>
      </div>

      {/* Reusable Premium Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-ruta-dark/95 backdrop-blur-md flex items-center justify-center z-[150] p-4">
          <div className="bg-ruta-black rounded-[2rem] max-w-md w-full p-8 border border-white/10 shadow-2xl">
            <div className="flex justify-between items-center mb-8">
              <h3 className="text-2xl font-extrabold tracking-tight text-white">Mensaje Directo</h3>
              <button onClick={closeModal} className="p-2 hover:bg-white/5 rounded-full transition-colors">
                <X className="w-6 h-6 text-white/40" />
              </button>
            </div>

            <textarea
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder="Hola, me gustaría saber más sobre..."
              className="w-full bg-white/5 border border-white/10 rounded-2xl p-6 h-40 focus:border-ruta-yellow focus:outline-none text-white transition-all resize-none mb-8"
            />

            <div className="flex gap-4">
              <button
                onClick={closeModal}
                className="flex-1 px-6 py-4 rounded-xl border border-white/10 text-white font-bold hover:bg-white/5 transition-all"
              >
                Cerrar
              </button>
              <button
                onClick={sendWhatsApp}
                className="flex-1 px-6 py-4 rounded-xl bg-ruta-yellow text-ruta-black font-bold hover:shadow-[0_0_20px_rgba(250,204,21,0.3)] transition-all"
              >
                Enviar
              </button>
            </div>
          </div>
        </div>
      )}
    </section>
  );
}