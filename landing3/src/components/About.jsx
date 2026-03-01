export default function About() {
  return (
    <section id="nosotros" className="py-32 relative overflow-hidden bg-ruta-black">
      {/* Background Decor */}
      <div className="absolute top-0 right-0 w-1/2 h-full bg-ruta-yellow/5 rounded-full blur-[150px] translate-x-1/2"></div>

      <div className="container mx-auto px-6 relative z-10">
        <div className="grid lg:grid-cols-2 gap-20 items-center">

          {/* Visual Column */}
          <div className="relative order-2 lg:order-1">
            <div className="relative z-10 rounded-[3rem] overflow-hidden border border-white/10 shadow-2xl">
              <img
                src="https://laruta11-images.s3.amazonaws.com/menu/1755574768_tomahawk-full-ig-portrait-1080-1350-2.png"
                alt="Nuestro Food Truck"
                className="w-full h-auto object-cover scale-105 hover:scale-100 transition-transform duration-1000"
              />
            </div>
            {/* Animated Badge */}
            <div className="absolute -bottom-10 -right-10 bg-ruta-yellow p-8 rounded-[2rem] shadow-2xl z-20 hidden md:block transition-transform duration-500 hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(250,204,21,0.3)]">
              <div className="text-ruta-black font-extrabold text-4xl leading-none">100%</div>
              <div className="text-ruta-black/60 font-bold uppercase tracking-tighter text-xs">Artesanal</div>
            </div>

            {/* Outline Text */}
            <div className="absolute -top-16 -left-16 text-[10rem] font-black text-white/[0.03] select-none pointer-events-none hidden xl:block">
              EST.2024
            </div>
          </div>

          {/* Text Column */}
          <div className="order-1 lg:order-2">
            <div className="flex items-center gap-3 mb-6">
              <span className="h-px w-8 bg-ruta-yellow"></span>
              <span className="text-ruta-yellow font-bold uppercase tracking-widest text-xs">Nuestra Esencia</span>
            </div>

            <h2 className="text-4xl md:text-6xl font-extrabold text-white tracking-tighter mb-8">
              Pasión por el <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-ruta-yellow to-ruta-orange">Sabor Real</span>
            </h2>

            <div className="space-y-6 text-ruta-white/70 text-lg font-light leading-relaxed">
              <p>
                Somos La Ruta 11, food trucks especializados en completos, hamburguesas y churrascos artesanales. Nos dedicamos a ofrecer la mejor experiencia gastronómica con ingredientes frescos y sabores únicos.
              </p>
            </div>

            <div className="grid md:grid-cols-2 gap-8 mt-12">
              <div className="p-6 rounded-2xl bg-white/5 border border-white/5">
                <h4 className="font-bold text-white mb-2 tracking-tight">Misión Premium</h4>
                <p className="text-sm text-ruta-white/40 leading-relaxed">Transformar lo cotidiano en extraordinario con estándares de chef internacional.</p>
              </div>
              <div className="p-6 rounded-2xl bg-white/5 border border-white/5">
                <h4 className="font-bold text-white mb-2 tracking-tight">Visión de Ruta</h4>
                <p className="text-sm text-ruta-white/40 leading-relaxed">Ser el referente indiscutido de food trucks de lujo en todo el país.</p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>
  );
}