export default function Location() {
  return (
    <section id="ubicacion" className="py-20 bg-ruta-white">
      <div className="container mx-auto px-6">
        <h2 className="text-4xl font-bold text-center mb-16 text-ruta-black">
          Ubicación <span className="text-ruta-red">Pronto</span>
        </h2>
        
        <div className="grid md:grid-cols-2 gap-12 items-center">
          <div className="bg-gradient-to-br from-ruta-red to-ruta-orange rounded-2xl p-8 text-ruta-white">
            <h3 className="text-2xl font-bold mb-6">Horarios de Servicio</h3>
            <div className="space-y-4">
              <div className="flex justify-between">
                <span className="font-semibold">Lunes - Jueves:</span>
                <span>7:00 PM - 1:00 AM</span>
              </div>
              <div className="flex justify-between">
                <span className="font-semibold">Viernes - Sábado:</span>
                <span>7:00 PM - 6:00 AM</span>
              </div>
              <div className="flex justify-between">
                <span className="font-semibold">Domingo:</span>
                <span>Cerrado</span>
              </div>
            </div>
            <div className="w-full mt-8 bg-ruta-white/20 text-ruta-white px-6 py-3 rounded-full font-semibold text-center">
              🚧 Próximamente
            </div>
          </div>
          
          <div className="bg-ruta-light-brown rounded-2xl p-12 text-center text-ruta-white">
            <div className="text-8xl mb-4">🚧</div>
            <h3 className="text-2xl font-bold mb-4">Próximamente</h3>
            <p className="text-lg opacity-90">Estamos preparando algo increíble</p>
          </div>
        </div>
      </div>
    </section>
  );
}