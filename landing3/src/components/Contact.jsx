import { Phone, Mail, Instagram, Facebook, MessageCircle } from 'lucide-react';

export default function Contact() {
  return (
    <section id="contacto" className="py-20 bg-ruta-black rounded-t-3xl">
      <div className="container mx-auto px-6">
        <h2 className="text-4xl font-bold text-center mb-16 text-ruta-white">
          ¡Mantente <span className="text-ruta-orange">Conectado</span>!
        </h2>
        
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto">
          <div className="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 text-center text-white transform hover:scale-105 transition-all duration-300">
            <MessageCircle className="w-12 h-12 mx-auto mb-4" />
            <h3 className="text-lg font-bold mb-2">WhatsApp</h3>
            <p className="text-sm opacity-90 mb-3">Pedidos y consultas</p>
            <a href="https://wa.me/56922504275" className="bg-white text-green-600 px-4 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors">
              +56 9 2250 4275
            </a>
          </div>
          
          <div className="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-center text-white transform hover:scale-105 transition-all duration-300">
            <Mail className="w-12 h-12 mx-auto mb-4" />
            <h3 className="text-lg font-bold mb-2">Email</h3>
            <p className="text-sm opacity-90 mb-3">Contacto general</p>
            <a href="mailto:hola@laruta11.cl" className="bg-white text-blue-600 px-4 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors">
              hola@laruta11.cl
            </a>
          </div>
          
          <div className="bg-gradient-to-br from-pink-500 to-purple-600 rounded-2xl p-6 text-center text-white transform hover:scale-105 transition-all duration-300">
            <Instagram className="w-12 h-12 mx-auto mb-4" />
            <h3 className="text-lg font-bold mb-2">Instagram</h3>
            <p className="text-sm opacity-90 mb-3">Fotos y novedades</p>
            <a href="https://www.instagram.com/la_ruta_11/" target="_blank" rel="noopener noreferrer" className="bg-white text-pink-600 px-4 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors">
              @la_ruta_11
            </a>
          </div>
          
          <div className="bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-6 text-center text-white transform hover:scale-105 transition-all duration-300">
            <Facebook className="w-12 h-12 mx-auto mb-4" />
            <h3 className="text-lg font-bold mb-2">Facebook</h3>
            <p className="text-sm opacity-90 mb-3">Eventos y promociones</p>
            <a href="https://www.facebook.com/laruta11" target="_blank" rel="noopener noreferrer" className="bg-white text-blue-600 px-4 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors">
              La Ruta11
            </a>
          </div>
        </div>
        
        <div className="text-center mt-16 text-ruta-white opacity-75 space-y-2">
          <p>&copy; 2025 La Ruta11 Food Trucks. Todos los derechos reservados.</p>
          <div className="flex flex-col sm:flex-row items-center justify-center gap-2 sm:gap-4 text-xs">
            <a href="https://agenterag.com/politica-de-privacidad/" target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-gray-300 transition-colors">
              Política de Privacidad
            </a>
            <span className="hidden sm:inline text-gray-500">•</span>
            <a href="https://agenterag.com/terminos-y-condiciones/" target="_blank" rel="noopener noreferrer" className="text-gray-400 hover:text-gray-300 transition-colors">
              Términos y Condiciones
            </a>
          </div>
          <p className="text-sm">
            <a href="https://agenterag.com" target="_blank" rel="noopener noreferrer" className="text-yellow-400 hover:text-yellow-300 transition-colors">
              ⚡ Powered by agenterag.com
            </a>
          </p>
        </div>
      </div>
    </section>
  );
}