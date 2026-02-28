import { GlassWater, Smartphone, ChevronRight } from 'lucide-react';
import { GiHamburger, GiHotDog, GiFrenchFries, GiMeat, GiSaucepan } from 'react-icons/gi';

export default function Menu() {
  const menuItems = [
    {
      icon: GiMeat,
      title: "Tomahawk Gourmet",
      description: "Nuestra pieza maestra: corte premium asado a fuego lento con costra de sal de mar y hierbas ahumadas.",
      gradient: "from-red-900/40 to-orange-900/40",
      accent: "text-red-500",
      image: "https://laruta11-images.s3.amazonaws.com/menu/1755574768_tomahawk-full-ig-portrait-1080-1350-2.png"
    },
    {
      icon: GiHamburger,
      title: "Burger Signature",
      description: "Carne premium seleccionada, queso fundido y pan artesanal sellado en mantequilla.",
      gradient: "from-amber-900/40 to-red-900/40",
      accent: "text-amber-500",
      image: "https://laruta11-images.s3.amazonaws.com/menu/1755571382_test.jpg"
    },
    {
      icon: GiHotDog,
      title: "Completos Premium",
      description: "El clásico chileno elevado a nivel gourmet con ingredientes frescos y pan al vapor.",
      gradient: "from-yellow-900/40 to-orange-900/40",
      accent: "text-yellow-500"
    },
    {
      icon: GiFrenchFries,
      title: "Papas Rústicas",
      description: "Corte grueso, doble cocción para máxima crocancia y especias de la casa.",
      gradient: "from-orange-900/40 to-yellow-900/40",
      accent: "text-orange-500"
    },
    {
      icon: GlassWater,
      title: "Mixología Natural",
      description: "Jugos de fruta natural y preparaciones refrescantes del día.",
      gradient: "from-green-900/40 to-emerald-900/40",
      accent: "text-green-500"
    },
    {
      icon: GiSaucepan,
      title: "Salsas de Autor",
      description: "Salsas artesanales preparadas diariamente en nuestro food truck.",
      gradient: "from-red-900/40 to-pink-900/40",
      accent: "text-pink-500"
    }
  ];

  return (
    <section id="menu" className="py-32 bg-ruta-dark relative overflow-hidden">
      {/* Background Decor */}
      <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-ruta-yellow/20 to-transparent"></div>
      <div className="absolute -bottom-24 -left-24 w-96 h-96 bg-ruta-orange/5 rounded-full blur-[120px]"></div>

      <div className="container mx-auto px-6 relative z-10">
        <div className="flex flex-col md:flex-row md:items-end justify-between mb-20 gap-8">
          <div className="max-w-2xl">
            <div className="flex items-center gap-3 mb-4">
              <span className="h-px w-8 bg-ruta-yellow"></span>
              <span className="text-ruta-yellow font-bold uppercase tracking-widest text-xs">
                Gastronomía de Vanguardia
              </span>
            </div>
            <h2 className="text-4xl md:text-6xl font-extrabold text-white tracking-tighter leading-none">
              Nuestras <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-ruta-yellow to-ruta-orange">Especialidades</span>
            </h2>
          </div>
          <p className="text-lg text-ruta-white/60 max-w-sm font-light leading-relaxed">
            Cada plato es una obra de arte culinaria, preparada al momento con los ingredientes más frescos de la región.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 lg:gap-10">
          {menuItems.map((item, index) => (
            <div
              key={index}
              className="group relative h-[450px] rounded-[2.5rem] overflow-hidden bg-ruta-black border border-white/5 transition-all duration-500 hover:border-ruta-yellow/30 hover:shadow-[0_20px_50px_rgba(0,0,0,0.5)]"
            >
              {/* Image Background or Gradient */}
              <div className="absolute inset-0 z-0 transition-transform duration-700 group-hover:scale-110">
                {item.image ? (
                  <>
                    <img src={item.image} alt={item.title} className="w-full h-full object-cover opacity-40 group-hover:opacity-60 transition-opacity" />
                    <div className="absolute inset-0 bg-gradient-to-t from-ruta-black via-ruta-black/60 to-transparent"></div>
                  </>
                ) : (
                  <div className={`w-full h-full bg-gradient-to-br ${item.gradient}`}></div>
                )}
              </div>

              {/* Content */}
              <div className="absolute inset-0 p-8 flex flex-col justify-end z-10">
                <div className={`mb-6 p-4 rounded-2xl bg-white/5 backdrop-blur-md border border-white/10 w-fit transition-transform duration-500 group-hover:-translate-y-2 ${item.accent}`}>
                  <item.icon className="w-8 h-8" />
                </div>

                <h3 className="text-2xl font-bold text-white mb-3 group-hover:text-ruta-yellow transition-colors">
                  {item.title}
                </h3>

                <p className="text-ruta-white/60 text-sm leading-relaxed mb-8 transform transition-all duration-500 translate-y-2 opacity-0 group-hover:translate-y-0 group-hover:opacity-100">
                  {item.description}
                </p>

                <div className="flex items-center gap-2 text-ruta-yellow text-sm font-bold uppercase tracking-widest cursor-pointer group/link">
                  <span>Saber más</span>
                  <ChevronRight className="w-4 h-4 transition-transform group-hover/link:translate-x-1" />
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Modernized CTA */}
        <div className="mt-24 text-center">
          <div className="inline-block p-[1px] rounded-full bg-gradient-to-r from-ruta-yellow via-ruta-orange to-ruta-red">
            <a
              href="https://app.laruta11.cl"
              target="_blank"
              className="flex items-center gap-3 bg-ruta-dark px-10 py-5 rounded-full font-bold text-lg text-white hover:bg-transparent transition-all duration-300 no-underline"
            >
              <Smartphone className="w-5 h-5 text-ruta-yellow" />
              Realizar Pedido Online
            </a>
          </div>
          <p className="mt-6 text-ruta-white/30 text-xs font-medium uppercase tracking-[0.2em]">
            Servicio disponible según disponibilidad de stock diario
          </p>
        </div>
      </div>
    </section>
  );
}