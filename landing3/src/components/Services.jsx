import { Truck, UtensilsCrossed, Smartphone, TrendingUp, ChefHat, Leaf, X } from 'lucide-react';
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
    const encodedMessage = encodeURIComponent(message || 'Hola, me interesa cotizar un servicio de La Ruta11');
    const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodedMessage}`;
    window.open(whatsappUrl, '_blank');
    closeModal();
  };
  const services = [
    {
      icon: Truck,
      title: "Food Trucks Premium",
      description: "Flota moderna de food trucks con cocinas gourmet y estándares de calidad superiores",
      features: ["Cocinas profesionales", "Equipos certificados", "Diseño moderno"],
      gradient: "from-ruta-red to-ruta-orange"
    },
    {
      icon: UtensilsCrossed,
      title: "Catering Eventos",
      description: "Servicio de catering para eventos corporativos, matrimonios y celebraciones",
      features: ["Eventos corporativos", "Matrimonios", "Fiestas privadas"],
      gradient: "from-blue-500 to-purple-600"
    },
    {
      icon: Smartphone,
      title: "Pedidos Online",
      description: "App móvil y plataforma web para pedidos anticipados y seguimiento en tiempo real",
      features: ["App móvil", "Pedidos anticipados", "Seguimiento GPS"],
      gradient: "from-green-500 to-teal-600"
    },
    {
      icon: TrendingUp,
      title: "Asesoría Personalizada",
      description: "Acompañamiento especializado para elegir el mejor menú y servicio para tu evento",
      features: ["Selección de menú", "Planificación de evento", "Recomendaciones personalizadas"],
      gradient: "from-pink-500 to-rose-600"
    },
    {
      icon: ChefHat,
      title: "Chefs Especializados",
      description: "Equipo de chefs con experiencia en gastronomía gourmet y cocina internacional",
      features: ["Chefs certificados", "Cocina internacional", "Recetas exclusivas"],
      gradient: "from-indigo-500 to-blue-600"
    },
    {
      icon: Leaf,
      title: "Ingredientes Premium",
      description: "Selección cuidadosa de ingredientes frescos y proveedores locales certificados",
      features: ["Ingredientes frescos", "Proveedores locales", "Calidad garantizada"],
      gradient: "from-emerald-500 to-green-600"
    }
  ];

  return (
    <section id="servicios" className="py-20 bg-white">
      <div className="container mx-auto px-6">
        {/* Header */}
        <div className="text-center mb-16">
          <span className="inline-block bg-ruta-red text-white px-4 py-2 rounded-full text-sm font-bold uppercase tracking-wide mb-4">
            Nuestros Servicios
          </span>
          <h2 className="text-4xl md:text-5xl font-bold text-ruta-black mb-6">
            Experiencia <span className="text-yellow-400">Completa</span>
          </h2>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            Ofrecemos una gama completa de servicios gastronómicos premium para satisfacer todas tus necesidades culinarias
          </p>
        </div>

        {/* Services Cards */}
        <div className="space-y-8">
          {services.map((service, index) => (
            <div
              key={index}
              className="sticky top-20"
              style={{ zIndex: index + 1 }}
            >
              <div 
                className={`bg-gradient-to-br ${service.gradient} text-white rounded-2xl md:rounded-3xl p-6 md:p-12 shadow-2xl min-h-[300px] md:h-80 flex flex-col justify-center items-center text-center max-w-7xl mx-auto transform transition-transform duration-300 hover:scale-105`}
                style={{
                  transform: `translateY(${index * 10}px) scale(${1 + index * 0.01})`,
                }}
              >
                <div className="mb-6">
                  <service.icon className="w-16 h-16 md:w-20 md:h-20 mx-auto text-yellow-400" />
                </div>
                <h3 className="text-2xl md:text-4xl lg:text-5xl font-bold mb-4 md:mb-6 leading-tight">
                  {service.title}
                </h3>
                <p className="text-lg md:text-xl leading-relaxed opacity-90 max-w-2xl mb-6">
                  {service.description}
                </p>
                
                {/* Features */}
                <div className="flex flex-wrap justify-center gap-2 mb-6">
                  {service.features.map((feature, idx) => (
                    <span key={idx} className="bg-yellow-400 text-ruta-black px-3 py-1 rounded-full text-sm font-semibold">
                      {feature}
                    </span>
                  ))}
                </div>
              </div>
            </div>
          ))}
          
          {/* CTA Card */}
          <div
            className="sticky top-20"
            style={{ zIndex: services.length + 1 }}
          >
            <div 
              className="bg-gradient-to-br from-yellow-400 to-orange-500 text-ruta-black rounded-2xl md:rounded-3xl p-6 md:p-12 shadow-2xl min-h-[300px] md:h-80 flex flex-col justify-center items-center text-center max-w-7xl mx-auto transform transition-transform duration-300 hover:scale-105"
              style={{
                transform: `translateY(${services.length * 10}px) scale(${1 + services.length * 0.01})`,
              }}
            >
              <h3 className="text-2xl md:text-4xl lg:text-5xl font-bold mb-4 md:mb-6 leading-tight">
                ¿Necesitas un servicio personalizado?
              </h3>
              <p className="text-lg md:text-xl leading-relaxed opacity-90 max-w-2xl mb-8">
                Contáctanos para crear una propuesta a medida para tu evento o necesidad específica
              </p>
              <div className="flex flex-col sm:flex-row gap-4 justify-center">
                <button 
                  onClick={openModal}
                  className="bg-ruta-red text-white px-8 py-4 rounded-full font-bold hover:bg-ruta-orange transition-all transform hover:scale-105 cursor-pointer"
                >
                  📞 Cotizar Servicio
                </button>
                <button 
                  onClick={() => window.open('https://wa.me/56922504275', '_blank')}
                  className="border-2 border-ruta-red text-ruta-red px-8 py-4 rounded-full font-semibold hover:bg-ruta-red hover:text-white transition-all transform hover:scale-105 cursor-pointer"
                >
                  💬 Chat WhatsApp
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      {/* WhatsApp Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-2xl font-bold text-gray-800">💬 Enviar Mensaje</h3>
              <button 
                onClick={closeModal}
                className="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer"
              >
                <X className="w-6 h-6" />
              </button>
            </div>
            
            <div className="mb-6">
              <label className="block text-gray-700 font-semibold mb-3">Tu mensaje:</label>
              <textarea
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                placeholder="Hola, me interesa cotizar un servicio de La Ruta11..."
                className="w-full p-4 border-2 border-gray-200 rounded-xl resize-none h-32 focus:border-ruta-red focus:outline-none transition-colors"
              />
            </div>
            
            <div className="flex gap-3">
              <button
                onClick={closeModal}
                className="flex-1 bg-gray-200 text-gray-700 py-3 px-6 rounded-xl font-semibold hover:bg-gray-300 transition-colors cursor-pointer"
              >
                Cancelar
              </button>
              <button
                onClick={sendWhatsApp}
                className="flex-1 bg-green-500 text-white py-3 px-6 rounded-xl font-semibold hover:bg-green-600 transition-colors cursor-pointer"
              >
                📱 Enviar WhatsApp
              </button>
            </div>
          </div>
        </div>
      )}
    </section>
  );
}