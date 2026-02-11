import { GlassWater, Smartphone } from 'lucide-react';
import { GiHamburger, GiHotDog, GiFrenchFries, GiMeat, GiSaucepan } from 'react-icons/gi';

export default function Menu() {
  const menuItems = [
    {
      icon: GiMeat,
      title: "Tomahawk Gourmet",
      description: "Preparación gourmet con provoleta fundida",
      gradient: "from-red-500 to-orange-600"
    },
    {
      icon: GiHamburger,
      title: "Churrascos & Hamburguesas", 
      description: "Especialidades de carne premium preparadas al momento",
      gradient: "from-amber-500 to-red-600"
    },
    {
      icon: GiHotDog,
      title: "Completos al Vapor",
      description: "Tradicionales chilenos preparados al vapor",
      gradient: "from-yellow-500 to-orange-500"
    },
    {
      icon: GiFrenchFries,
      title: "Papas Naturales",
      description: "Papas frescas cortadas y fritas al momento",
      gradient: "from-orange-400 to-yellow-500"
    },
    {
      icon: GlassWater,
      title: "Jugos Naturales",
      description: "Frutas frescas exprimidas al momento",
      gradient: "from-green-500 to-emerald-600"
    },
    {
      icon: GiSaucepan,
      title: "Salsas Artesanales",
      description: "Variedades caseras para acompañar tus platos",
      gradient: "from-red-600 to-pink-600"
    }
  ];

  return (
    <section id="menu" className="py-20 bg-gradient-to-br from-ruta-light-brown to-ruta-brown rounded-b-3xl">
      <div className="container mx-auto px-6">
        <div className="text-center mb-16">
          <span className="inline-block bg-yellow-400 text-ruta-black px-4 py-2 rounded-full text-sm font-bold uppercase tracking-wide mb-4">
            Nuestro Menú
          </span>
          <h2 className="text-4xl md:text-5xl font-bold text-white mb-6">
            Nuestras <span className="text-yellow-400">Especialidades</span>
          </h2>
          <p className="text-xl text-white opacity-90 max-w-3xl mx-auto">
            Sabores auténticos preparados con ingredientes frescos y técnicas gourmet
          </p>
        </div>
        
        <div className="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-8 max-w-6xl mx-auto">
          {menuItems.map((item, index) => (
            <div
              key={index}
              className="group relative"
            >
              <div 
                className={`bg-gradient-to-br ${item.gradient} text-white rounded-2xl p-8 shadow-xl transform transition-all duration-300 group-hover:scale-105 group-hover:-translate-y-2 h-64 flex flex-col justify-center items-center text-center relative overflow-hidden`}
              >
                <div className="absolute inset-0 bg-black opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                <item.icon className="w-16 h-16 mb-4 transform group-hover:scale-110 transition-transform duration-300" />
                <h3 className="text-xl font-bold mb-3 relative z-10">
                  {item.title}
                </h3>
                <p className="text-sm opacity-90 relative z-10">
                  {item.description}
                </p>
              </div>
            </div>
          ))}
        </div>
        
        {/* CTA Button */}
        <div className="text-center mt-12">
          <a href="https://app.laruta11.cl" target="_blank" className="inline-flex items-center gap-2 bg-yellow-400 text-ruta-black px-6 py-3 md:px-8 md:py-4 rounded-full font-bold text-base md:text-lg hover:bg-black/70 hover:backdrop-blur-sm hover:text-white hover:border-2 hover:border-yellow-400 transition-all transform hover:scale-105 shadow-lg no-underline">
            <Smartphone className="w-4 h-4 md:w-5 md:h-5" />
            Ir a App
          </a>
        </div>
      </div>
    </section>
  );
}