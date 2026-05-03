import { Smartphone, ChevronRight } from 'lucide-react';

const FEATURED_PRODUCTS = [
  {
    id: 196,
    name: 'Pichanga Familiar',
    price: 18980,
    image: 'https://laruta11-images.s3.amazonaws.com/products/68dde52594241_39ca9494-3ce5-4145-8cd5-74c6e8a72727.webp',
    description: 'Lomo vetado, tocino ahumado, filete de pollo, lomito de cerdo, salchicha, tomate y cebolla caramelizada sobre papas rústicas.'
  },
  {
    id: 187,
    name: 'Combo Doble Mixta',
    price: 14180,
    image: 'https://laruta11-images.s3.amazonaws.com/products/69260b0031e58_1.webp',
    description: 'Hamburguesa doble mixta, papa individual y una bebida en lata de 350 ml a elección.'
  },
  {
    id: 11,
    name: 'Hamburguesa Doble Mixta (580g)',
    price: 12280,
    image: 'https://laruta11-images.s3.amazonaws.com/products/68d1fd67a7e42_WhatsApp%2520Image%25202025-09-22%2520at%252022.51.41.webp',
    description: '400g de hamburguesa premium, 180g de filete de pollo, doble queso cheddar fundido, tomate, mayonesa Kraft y cebolla caramelizada.'
  },
  {
    id: 204,
    name: 'Churrasco Italiano',
    price: 5890,
    image: 'https://laruta11-images.s3.amazonaws.com/products/69deeccab0f9d_9d2a8aa8-82a0-4040-8bc3-c8777a2f40f0.jpeg',
    description: 'Clásico churrasco con palta, tomate y mayonesa Kraft en pan frica.'
  },
  {
    id: 280,
    name: 'Lucaso 11',
    price: 6890,
    image: 'https://laruta11-images.s3.amazonaws.com/products/producto_280_1777748819.jpeg',
    description: 'Churrasco, queso, palta y tomate fresco en pan Frica. ¡El clásico que te encanta!'
  },
  {
    id: 194,
    name: 'Completo Tocino Ahumado',
    price: 3780,
    image: 'https://laruta11-images.s3.amazonaws.com/products/68db45ce0fc60_WhatsApp%2520Image%25202025-09-29%2520at%252023.50.28.webp',
    description: 'Salchicha premium, tocino ahumado artesanal, queso mantecoso fundido y salsa especial Crazy Chicken.'
  }
];

export default function Menu() {
  const formatPrice = (price) => {
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(price);
  };

  return (
    <section id="menu" className="py-24 sm:py-32 bg-ruta-gray relative overflow-hidden">
      <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-ruta-orange/20 to-transparent"></div>

      <div className="container mx-auto px-4 sm:px-6 relative z-10">
        <div className="flex flex-col md:flex-row md:items-end justify-between mb-12 sm:mb-20 gap-6">
          <div className="max-w-2xl">
            <div className="flex items-center gap-3 mb-4">
              <span className="h-px w-8 bg-ruta-orange"></span>
              <span className="text-ruta-orange font-bold uppercase tracking-widest text-xs">
                Gastronomía de Vanguardia
              </span>
            </div>
            <h2 className="text-3xl sm:text-4xl md:text-6xl font-extrabold text-ruta-black tracking-tighter leading-none">
              Nuestras <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-ruta-red to-ruta-orange">Especialidades</span>
            </h2>
          </div>
          <p className="text-base sm:text-lg text-gray-500 max-w-sm font-light leading-relaxed">
            Cada plato es una obra de arte culinaria, preparada al momento con los ingredientes más frescos.
          </p>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
          {FEATURED_PRODUCTS.map((item) => (
            <div
              key={item.id}
              className="group relative h-[380px] sm:h-[420px] rounded-3xl overflow-hidden bg-white border border-gray-100 shadow-sm transition-all duration-500 hover:shadow-lg hover:border-ruta-orange/30"
            >
              {/* Image */}
              <div className="absolute inset-0 z-0 transition-transform duration-700 group-hover:scale-105">
                <img
                  src={item.image}
                  alt={item.name}
                  className="w-full h-full object-cover"
                  loading="lazy"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
              </div>

              {/* Price Badge */}
              <div className="absolute top-4 right-4 z-10 bg-white/90 backdrop-blur-sm rounded-full px-3 py-1.5 shadow-sm border border-gray-100">
                <span className="text-sm font-bold text-ruta-red">{formatPrice(item.price)}</span>
              </div>

              {/* Content */}
              <div className="absolute inset-0 p-6 flex flex-col justify-end z-10">
                <h3 className="text-xl sm:text-2xl font-bold text-white mb-2 line-clamp-2">
                  {item.name}
                </h3>
                <p className="text-white/70 text-sm leading-relaxed mb-4 line-clamp-2">
                  {item.description}
                </p>
                <a
                  href="https://app.laruta11.cl"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 text-ruta-yellow text-sm font-bold uppercase tracking-wide no-underline group/link"
                >
                  Pedir <ChevronRight className="w-4 h-4 group-hover/link:translate-x-1 transition-transform" />
                </a>
              </div>
            </div>
          ))}
        </div>

        {/* CTA */}
        <div className="mt-16 sm:mt-24 text-center">
          <a
            href="https://app.laruta11.cl"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-3 bg-ruta-red hover:bg-red-700 text-white px-8 sm:px-10 py-4 sm:py-5 rounded-full font-bold text-base sm:text-lg transition-all duration-200 hover:shadow-xl hover:scale-[1.02] no-underline"
          >
            <Smartphone className="w-5 h-5" />
            Ver Menú Completo
          </a>
          <p className="mt-4 text-gray-400 text-xs font-medium uppercase tracking-[0.2em]">
            Disponibilidad sujeta a stock diario
          </p>
        </div>
      </div>
    </section>
  );
}
