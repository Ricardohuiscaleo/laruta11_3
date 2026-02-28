import { Smartphone, ChevronRight, Star } from 'lucide-react';
import { useState, useEffect } from 'react';

export default function Menu() {
  const [products, setProducts] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchMenu = async () => {
      try {
        const response = await fetch('https://app.laruta11.cl/api/get_menu_products.php');
        const data = await response.json();

        if (data.success && data.menuData) {
          // Flatten the nested menu structure to get a pool of products
          let allProducts = [];
          Object.values(data.menuData).forEach(subs => {
            Object.values(subs).forEach(prods => {
              allProducts = [...allProducts, ...prods];
            });
          });

          // Filter out inactive products and get a diverse selection of high-quality items
          // E.g., items with images and good reviews
          const featuredProducts = allProducts
            .filter(p => p.active === 1 && p.image && p.image !== 'https://laruta11-images.s3.amazonaws.com/menu/default-product.jpg')
            .sort((a, b) => b.likes - a.likes || b.reviews.average - a.reviews.average) // Prioritize liked/highly rated
            .slice(0, 6); // Take top 6 for the landing page grid

          setProducts(featuredProducts);
        }
      } catch (error) {
        console.error('Error fetching menu:', error);
      } finally {
        setIsLoading(false);
      }
    };

    fetchMenu();
  }, []);

  const formatPrice = (price) => {
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(price);
  };

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

        {isLoading ? (
          <div className="flex justify-center items-center py-20">
            <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-ruta-yellow"></div>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 lg:gap-10">
            {products.map((item, index) => (
              <div
                key={item.id || index}
                className="group relative h-[450px] rounded-[2.5rem] overflow-hidden bg-ruta-black border border-white/5 transition-all duration-500 hover:border-ruta-yellow/30 hover:shadow-[0_20px_50px_rgba(0,0,0,0.5)]"
              >
                {/* Image Background */}
                <div className="absolute inset-0 z-0 transition-transform duration-700 group-hover:scale-110">
                  <img src={item.image} alt={item.name} className="w-full h-full object-cover opacity-60 group-hover:opacity-80 transition-opacity" />
                  <div className="absolute inset-0 bg-gradient-to-t from-ruta-black via-ruta-black/80 to-transparent"></div>
                </div>

                {/* Content */}
                <div className="absolute inset-0 p-8 flex flex-col justify-end z-10">
                  {/* Rating / Likes Badge */}
                  {(item.reviews.average > 0 || item.likes > 0) && (
                    <div className="absolute top-6 right-6 flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-black/50 backdrop-blur-md border border-white/10">
                      <Star className="w-3.5 h-3.5 text-ruta-yellow fill-ruta-yellow" />
                      <span className="text-white text-xs font-bold">{item.reviews.average > 0 ? item.reviews.average.toFixed(1) : item.likes}</span>
                    </div>
                  )}

                  <div className="transform transition-all duration-500 translate-y-4 group-hover:translate-y-0">
                    <h3 className="text-2xl font-bold text-white mb-2 group-hover:text-ruta-yellow transition-colors line-clamp-2">
                      {item.name}
                    </h3>

                    <p className="text-ruta-yellow font-black text-xl mb-4">
                      {formatPrice(item.price)}
                    </p>

                    <p className="text-ruta-white/70 text-sm leading-relaxed mb-6 opacity-0 group-hover:opacity-100 transition-opacity duration-500 line-clamp-3">
                      {item.description}
                    </p>

                    <a
                      href="https://app.laruta11.cl"
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 text-white text-sm font-bold uppercase tracking-widest cursor-pointer group/link hover:text-ruta-yellow transition-colors"
                    >
                      <span>Pedir Ahora</span>
                      <ChevronRight className="w-4 h-4 transition-transform group-hover/link:translate-x-1" />
                    </a>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Modernized CTA */}
        <div className="mt-24 text-center">
          <div className="inline-block p-[1px] rounded-full bg-gradient-to-r from-ruta-yellow via-ruta-orange to-ruta-red">
            <a
              href="https://app.laruta11.cl"
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-3 bg-ruta-dark px-10 py-5 rounded-full font-bold text-lg text-white hover:bg-transparent transition-all duration-300 no-underline"
            >
              <Smartphone className="w-5 h-5 text-ruta-yellow" />
              Ver Menú Completo
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