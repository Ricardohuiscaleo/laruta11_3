import { ActivityIcon } from './icons/ActivityIcon';
import { X, ChevronRight, Coins, BadgePercent } from 'lucide-react';
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
      icon: ActivityIcon,
      title: "Catering & Eventos",
      description: "Llevamos la experiencia del food truck a tu fiesta, matrimonio o evento corporativo con propuestas a medida.",
      accent: "text-ruta-red"
    },
    {
      icon: Coins,
      title: "Cashback Real",
      description: "Acumula dinero en cada pedido realizado a través de nuestra App para usarlo como descuento en tu siguiente bocado.",
      accent: "text-ruta-yellow"
    },
    {
      icon: BadgePercent,
      title: "Ahorra un 45%",
      description: "Al pedir directo por nuestra App, ahorras hasta un 45% comparado con PedidosYa o UberEats. Más comida, menos comisiones.",
      accent: "text-blue-500"
    }
  ];

  return (
    <section id="servicios" className="py-32 bg-ruta-gray">
      <div className="container mx-auto px-6">
        <div className="text-center mb-24">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-ruta-orange/20 bg-ruta-orange/5 text-ruta-orange text-[10px] uppercase tracking-[0.3em] font-bold mb-6">
            <span className="relative flex h-2 w-2">
              <span className="relative inline-flex rounded-full h-2 w-2 bg-ruta-orange/80"></span>
            </span>
            Nuestras Soluciones
          </div>
          <h2 className="text-4xl md:text-6xl font-extrabold text-ruta-black tracking-tighter mb-6">
            Experiencia <span className="text-transparent bg-clip-text bg-gradient-to-r from-ruta-red to-ruta-orange">Sin Límites</span>
          </h2>
          <p className="text-lg text-gray-500 max-w-2xl mx-auto font-light leading-relaxed">
            Más que comida, ofrecemos una infraestructura gastronómica completa para cualquier ocasión.
          </p>
        </div>

        <div className="grid md:grid-cols-3 gap-8">
          {services.map((service, index) => (
            <div
              key={index}
              className="group p-10 rounded-[2rem] bg-white border border-gray-100 shadow-sm transition-all duration-500 hover:shadow-lg hover:-translate-y-2"
            >
              <div className={`w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center mb-8 border border-gray-100 ${service.accent}`}>
                <service.icon size={32} />
              </div>
              <h3 className="text-2xl font-bold text-ruta-black mb-4">{service.title}</h3>
              <p className="text-gray-500 text-sm leading-relaxed mb-6">
                {service.description}
              </p>
              <div className="flex items-center gap-2 text-ruta-orange text-xs font-bold uppercase tracking-widest cursor-pointer group-hover:gap-4 transition-all" onClick={openModal}>
                Consultar <ChevronRight className="w-4 h-4" />
              </div>
            </div>
          ))}
        </div>

        {/* Dynamic CTA */}
        <div className="mt-16 p-1 rounded-[2.5rem] bg-gradient-to-r from-ruta-red/20 via-gray-100 to-ruta-orange/20">
          <div className="bg-white rounded-[2.4rem] p-10 md:p-16 flex flex-col md:flex-row items-center justify-between gap-10 shadow-sm">
            <div className="text-center md:text-left">
              <h3 className="text-3xl font-bold text-ruta-black mb-2">¿Tienes un evento especial?</h3>
              <p className="text-gray-500 font-light">Diseñamos una propuesta gastronómica a tu medida.</p>
            </div>
            <button
              onClick={openModal}
              className="bg-ruta-red text-white px-10 py-5 rounded-full font-bold text-lg hover:scale-105 transition-all shadow-[0_10px_30px_rgba(220,38,38,0.2)] whitespace-nowrap"
            >
              Cotizar Ahora
            </button>
          </div>
        </div>
      </div>

      {/* Reusable Premium Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-white/95 backdrop-blur-md flex items-center justify-center z-[150] p-4">
          <div className="bg-white rounded-[2rem] max-w-md w-full p-8 border border-gray-200 shadow-2xl">
            <div className="flex justify-between items-center mb-8">
              <h3 className="text-2xl font-extrabold tracking-tight text-ruta-black">Mensaje Directo</h3>
              <button onClick={closeModal} className="p-2 hover:bg-gray-50 rounded-full transition-colors">
                <X className="w-6 h-6 text-gray-400" />
              </button>
            </div>

            <textarea
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder="Hola, me gustaría saber más sobre..."
              className="w-full bg-gray-50 border border-gray-200 rounded-2xl p-6 h-40 focus:border-ruta-orange focus:outline-none text-ruta-black transition-all resize-none mb-8"
            />

            <div className="flex gap-4">
              <button
                onClick={closeModal}
                className="flex-1 px-6 py-4 rounded-xl border border-gray-200 text-ruta-black font-bold hover:bg-gray-50 transition-all"
              >
                Cerrar
              </button>
              <button
                onClick={sendWhatsApp}
                className="flex-1 px-6 py-4 rounded-xl bg-ruta-red text-white font-bold hover:shadow-[0_0_20px_rgba(220,38,38,0.3)] transition-all"
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
