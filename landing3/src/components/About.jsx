export default function About() {
  return (
    <>
      <style jsx>{`
        @keyframes float {
          0%, 100% { transform: translateY(0px) translateX(0px); }
          25% { transform: translateY(-10px) translateX(5px); }
          50% { transform: translateY(-5px) translateX(-3px); }
          75% { transform: translateY(-15px) translateX(8px); }
        }
      `}</style>
      <section id="nosotros" className="py-20 relative overflow-hidden">
        {/* Background Image */}
        <div 
          className="absolute inset-0 bg-cover bg-center bg-no-repeat"
          style={{
            backgroundImage: 'url(https://laruta11-images.s3.amazonaws.com/menu/1755631926_WhatsApp%20Image%202025-07-06%20at%2015.34.52.jpeg)'
          }}
        ></div>
        
        {/* Dark Overlay */}
        <div className="absolute inset-0 bg-gradient-to-br from-black/80 via-ruta-black/85 to-black/80"></div>
        
        {/* Dynamic Top Cut */}
        <div className="absolute top-0 left-0 w-full h-16 bg-white transform -skew-y-2 origin-top-left -translate-y-8"></div>
        
        {/* Background Pattern */}
        <div className="absolute inset-0 opacity-10">
          <div className="absolute top-20 left-10 w-16 h-16 bg-yellow-400/30 rounded-full"></div>
          <div className="absolute top-40 right-20 w-12 h-12 bg-ruta-red/30 rounded-full"></div>
          <div className="absolute bottom-32 left-1/4 w-20 h-20 bg-ruta-orange/30 rounded-full"></div>
          <div className="absolute bottom-20 right-10 w-8 h-8 bg-yellow-400/30 rounded-full"></div>
        </div>
        
        <div className="container mx-auto px-6 relative z-10">
          {/* Header */}
          <div className="text-center mb-16">
            <span className="inline-block bg-yellow-400 text-ruta-black px-4 py-2 rounded-full text-sm font-bold uppercase tracking-wide mb-4">
              Nuestra Historia
            </span>
            <h2 className="text-4xl md:text-5xl font-bold text-white mb-6">
              Conoce <span className="text-yellow-400">La Ruta11</span>
            </h2>
            <p className="text-xl text-gray-300 max-w-3xl mx-auto">
              Más que food trucks, somos una experiencia gastronómica que mereces disfrutar.
            </p>
          </div>
          
          <div className="grid lg:grid-cols-2 gap-16 items-center">
            {/* Content */}
            <div className="space-y-8">
              <div className="bg-white/5 backdrop-blur-sm rounded-2xl p-8 border border-white/10">
                <h3 className="text-2xl font-bold text-yellow-400 mb-4">
                  Nuestra Misión
                </h3>
                <p className="text-gray-300 leading-relaxed">
                  Transformar la experiencia fast food en Arica, ofreciendo platos gourmet 
                  de alta calidad con la comodidad y autenticidad que solo los food trucks pueden brindar.
                </p>
              </div>
              
              <div className="bg-white/5 backdrop-blur-sm rounded-2xl p-8 border border-white/10">
                <h3 className="text-2xl font-bold text-yellow-400 mb-4">
                  Nuestra Visión
                </h3>
                <p className="text-gray-300 leading-relaxed">
                  Ser la red de food trucks líder en el norte de Chile, reconocida por la innovación 
                  culinaria, rapidez, calidad excepcional y el compromiso con la comunidad local.
                </p>
              </div>
              
              <div className="bg-white/5 backdrop-blur-sm rounded-2xl p-8 border border-white/10">
                <h3 className="text-2xl font-bold text-yellow-400 mb-4">
                  Nuestros Valores
                </h3>
                <div className="grid grid-cols-2 gap-4">
                  <div className="flex items-center gap-2">
                    <span className="w-2 h-2 bg-yellow-400 rounded-full"></span>
                    <span className="text-gray-300 text-sm">Calidad Premium</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="w-2 h-2 bg-yellow-400 rounded-full"></span>
                    <span className="text-gray-300 text-sm">Ingredientes Frescos</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="w-2 h-2 bg-yellow-400 rounded-full"></span>
                    <span className="text-gray-300 text-sm">Innovación Constante</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="w-2 h-2 bg-yellow-400 rounded-full"></span>
                    <span className="text-gray-300 text-sm">Compromiso Local</span>
                  </div>
                </div>
              </div>
            </div>
            
            {/* Visual */}
            <div className="flex justify-center">
              <div 
                className="relative perspective-1000 p-24"
                onMouseMove={(e) => {
                  const rect = e.currentTarget.getBoundingClientRect();
                  const x = e.clientX - rect.left - rect.width / 2;
                  const y = e.clientY - rect.top - rect.height / 2;
                  const rotateX = (y / rect.height) * -20;
                  const rotateY = (x / rect.width) * 20;
                  
                  // Logo 3D effect
                  const logo = e.currentTarget.querySelector('.logo-3d');
                  logo.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.05)`;
                  
                  // Reactive particles with random behavior
                  const particles = e.currentTarget.querySelectorAll('.particle');
                  particles.forEach((particle, index) => {
                    const randomIntensity = 0.2 + (index % 3) * 0.4 + Math.sin(index) * 0.2;
                    const randomOffset = (index % 5) * 0.1;
                    const moveX = (x / rect.width) * (25 + index * 3) * randomIntensity;
                    const moveY = (y / rect.height) * (25 + index * 2) * randomIntensity;
                    const distance = Math.sqrt(x*x + y*y);
                    const scale = 1 + (distance / (rect.width * 0.5)) * (1.5 + randomOffset);
                    const rotation = (x + y) * 0.1 * (index % 2 === 0 ? 1 : -1);
                    
                    particle.style.transform = `translate(${moveX}px, ${moveY}px) scale(${Math.min(scale, 3)}) rotate(${rotation}deg)`;
                    particle.style.opacity = Math.min(0.9, 0.4 + (scale - 1) * 0.3);
                  });
                }}
                onMouseLeave={(e) => {
                  const logo = e.currentTarget.querySelector('.logo-3d');
                  logo.style.transform = 'rotateX(0deg) rotateY(0deg) scale(1)';
                  
                  // Reset particles
                  const particles = e.currentTarget.querySelectorAll('.particle');
                  particles.forEach((particle) => {
                    particle.style.transform = 'translate(0px, 0px) scale(1)';
                    particle.style.opacity = '0.6';
                  });
                }}
              >
                {/* Background Particles (behind logo) */}
                <div className="particle absolute w-3 h-3 bg-ruta-red/40 rounded-full transition-all duration-320 ease-out z-0" style={{ top: '-12px', right: '-16px', boxShadow: '0 0 18px rgba(220, 38, 38, 0.3)', animation: 'float 6s ease-in-out infinite' }}></div>
                <div className="particle absolute w-2 h-2 bg-yellow-400/50 rounded-full transition-all duration-480 ease-out z-0" style={{ top: '-32px', right: '32px', boxShadow: '0 0 14px rgba(252, 211, 77, 0.4)', animation: 'float 8s ease-in-out infinite reverse' }}></div>
                <div className="particle absolute w-4 h-4 bg-yellow-400/60 rounded-full transition-all duration-380 ease-out z-0" style={{ bottom: '-16px', left: '-16px', boxShadow: '0 0 20px rgba(252, 211, 77, 0.5)', animation: 'float 7s ease-in-out infinite' }}></div>
                <div className="particle absolute w-3 h-3 bg-ruta-orange/50 rounded-full transition-all duration-420 ease-out z-0" style={{ top: '50%', left: '-32px', boxShadow: '0 0 16px rgba(234, 88, 12, 0.4)', animation: 'float 9s ease-in-out infinite reverse' }}></div>
                <div className="particle absolute w-2 h-2 bg-ruta-red/30 rounded-full transition-all duration-360 ease-out z-0" style={{ bottom: '32px', right: '16px', boxShadow: '0 0 12px rgba(220, 38, 38, 0.2)', animation: 'float 5s ease-in-out infinite' }}></div>
                <div className="particle absolute w-2 h-2 bg-ruta-orange/40 rounded-full transition-all duration-520 ease-out z-0" style={{ top: '32px', left: '-48px', boxShadow: '0 0 14px rgba(234, 88, 12, 0.3)', animation: 'float 10s ease-in-out infinite reverse' }}></div>
                <div className="particle absolute w-3 h-3 bg-yellow-400/45 rounded-full transition-all duration-440 ease-out z-0" style={{ top: '-8px', left: '24px', boxShadow: '0 0 17px rgba(252, 211, 77, 0.45)', animation: 'float 6.5s ease-in-out infinite' }}></div>
                
                {/* Logo */}
                <img 
                  src="https://laruta11-images.s3.amazonaws.com/menu/1755571382_test.jpg" 
                  alt="La Ruta11 Logo" 
                  className="logo-3d w-96 h-96 object-contain drop-shadow-2xl transition-transform duration-200 ease-out relative z-10"
                  style={{ transformStyle: 'preserve-3d' }}
                />
                
                {/* Foreground Particles (in front of logo) */}
                <div className="particle absolute w-2 h-2 bg-ruta-red/80 rounded-full transition-all duration-390 ease-out z-20" style={{ bottom: '-24px', right: '48px', boxShadow: '0 0 13px rgba(220, 38, 38, 0.6)', animation: 'float 4s ease-in-out infinite reverse' }}></div>
                <div className="particle absolute w-4 h-4 bg-ruta-orange/90 rounded-full transition-all duration-460 ease-out z-20" style={{ top: '20%', right: '-24px', boxShadow: '0 0 19px rgba(234, 88, 12, 0.8)', animation: 'float 7.5s ease-in-out infinite' }}></div>
                <div className="particle absolute w-2 h-2 bg-yellow-400/85 rounded-full transition-all duration-340 ease-out z-20" style={{ bottom: '60%', left: '-20px', boxShadow: '0 0 15px rgba(252, 211, 77, 0.7)', animation: 'float 5.5s ease-in-out infinite reverse' }}></div>
                <div className="particle absolute w-3 h-3 bg-ruta-red/95 rounded-full transition-all duration-500 ease-out z-20" style={{ top: '80%', right: '8px', boxShadow: '0 0 18px rgba(220, 38, 38, 0.8)', animation: 'float 8.5s ease-in-out infinite' }}></div>
                <div className="particle absolute w-2 h-2 bg-ruta-orange/75 rounded-full transition-all duration-410 ease-out z-20" style={{ top: '10%', left: '40px', boxShadow: '0 0 12px rgba(234, 88, 12, 0.6)', animation: 'float 6s ease-in-out infinite reverse' }}></div>
                <div className="particle absolute w-3 h-3 bg-yellow-400/90 rounded-full transition-all duration-370 ease-out z-20" style={{ bottom: '10%', left: '20px', boxShadow: '0 0 16px rgba(252, 211, 77, 0.8)', animation: 'float 9.5s ease-in-out infinite' }}></div>
                <div className="particle absolute w-2 h-2 bg-ruta-red/70 rounded-full transition-all duration-450 ease-out z-20" style={{ top: '40%', right: '60px', boxShadow: '0 0 11px rgba(220, 38, 38, 0.5)', animation: 'float 4.5s ease-in-out infinite reverse' }}></div>
                <div className="particle absolute w-4 h-4 bg-ruta-orange/85 rounded-full transition-all duration-330 ease-out z-20" style={{ bottom: '40%', right: '-12px', boxShadow: '0 0 17px rgba(234, 88, 12, 0.7)', animation: 'float 7s ease-in-out infinite' }}></div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}