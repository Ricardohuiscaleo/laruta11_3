import React, { useState } from 'react';
import { X, Gift, Truck, Zap, Star, Mail, Lock, User, Phone, CheckCircle, AlertCircle, Eye, EyeOff } from 'lucide-react';

const AuthModal = ({ isOpen, onClose, onLoginSuccess }) => {
  if (!isOpen) return null;

  const [mode, setMode] = useState('login'); // 'login' | 'register'
  const [formData, setFormData] = useState({
    nombre: '',
    email: '',
    password: '',
    confirmPassword: '',
    telefono: ''
  });
  const [loading, setLoading] = useState(false);
  const [feedback, setFeedback] = useState({ type: '', message: '' });
  const [showPassword, setShowPassword] = useState(false);

  const benefits = [
    { icon: Star, text: '1% de cashback en cada compra', color: 'text-green-400' },
    { icon: Gift, text: 'Ofertas y promociones exclusivas', color: 'text-yellow-400' },
    { icon: Truck, text: 'Historial completo de tus pedidos', color: 'text-blue-400' },
    { icon: Zap, text: 'Checkout rápido con datos guardados', color: 'text-orange-400' }
  ];

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (mode === 'register' && formData.password !== formData.confirmPassword) {
      setFeedback({ type: 'error', message: 'Las contraseñas no coinciden' });
      return;
    }
    
    setLoading(true);
    setFeedback({ type: '', message: '' });

    try {
      const endpoint = mode === 'register' 
        ? '/api/auth/register_manual.php' 
        : '/api/auth/login_manual.php';

      const formDataToSend = new FormData();
      Object.keys(formData).forEach(key => {
        if (formData[key]) formDataToSend.append(key, formData[key]);
      });

      const response = await fetch(endpoint, {
        method: 'POST',
        body: formDataToSend
      });

      const result = await response.json();

      if (result.success) {
        console.log('🔍 [DEBUG] === LOGIN MANUAL EXITOSO ===');
        console.log('🔍 [DEBUG] Modo:', mode);
        console.log('🔍 [DEBUG] Usuario recibido:', result.user ? result.user.nombre : 'NULL');
        
        setFeedback({ 
          type: 'success', 
          message: result.message || (mode === 'register' ? '¡Registro exitoso!' : '¡Bienvenido de vuelta!')
        });
        
        // Guardar usuario en localStorage para persistencia
        if (result.user) {
          try {
            const userString = JSON.stringify(result.user);
            console.log('🔍 [DEBUG] Guardando en localStorage (primeros 100 chars):', userString.substring(0, 100));
            localStorage.setItem('ruta11_user', userString);
            console.log('✅ [DEBUG] localStorage.setItem ejecutado');
            
            // Verificar que se guardó
            const verificacion = localStorage.getItem('ruta11_user');
            console.log('🔍 [DEBUG] Verificación guardado:', verificacion ? 'CONFIRMADO' : 'FALLÓ');
            if (verificacion) {
              console.log('🔍 [DEBUG] Contenido verificado (primeros 100 chars):', verificacion.substring(0, 100));
            }
          } catch (error) {
            console.warn('⚠️ [DEBUG] No se pudo guardar en localStorage:', error);
          }
        } else {
          console.warn('⚠️ [DEBUG] result.user es NULL, no se puede guardar');
        }
        
        setTimeout(() => {
          if (onLoginSuccess) onLoginSuccess(result.user);
          onClose();
        }, 1500);
      } else {
        setFeedback({ type: 'error', message: result.error || 'Error al procesar solicitud' });
      }
    } catch (error) {
      setFeedback({ type: 'error', message: 'Error de conexión. Intenta nuevamente.' });
    } finally {
      setLoading(false);
    }
  };

  const [googleLoading, setGoogleLoading] = useState(false);

  const handleGoogleLogin = () => {
    console.log('🔍 [DEBUG] === INICIANDO LOGIN GOOGLE ===');
    setGoogleLoading(true);
    console.log('🔍 [DEBUG] Redirigiendo a Google OAuth...');
    window.location.href = '/api/auth/google/login.php';
  };

  return (
    <div className="fixed inset-0 bg-black/90 backdrop-blur-sm z-50 flex items-center justify-center p-4" onClick={onClose}>
      <div className="bg-slate-900 w-full max-w-md max-h-[90vh] rounded-2xl shadow-2xl overflow-hidden animate-slide-up flex flex-col" onClick={(e) => e.stopPropagation()}>
        
        {/* Header */}
        <div className="bg-gradient-to-r from-orange-600 to-red-600 p-6 relative">
          <button onClick={onClose} className="absolute top-4 right-4 text-white/80 hover:text-white">
            <X size={24} />
          </button>
          <div className="flex items-center gap-3 mb-2">
            <img src="/icon.ico" alt="La Ruta 11" className="w-12 h-12" />
            <div>
              <h2 className="text-2xl font-black text-white">LA RUTA 11</h2>
              <p className="text-orange-100 text-sm">Tu cuenta, tus beneficios</p>
            </div>
          </div>
        </div>

        {/* Benefits Section */}
        <div className="bg-slate-800 p-4 border-b border-slate-700">
          <h3 className="text-white font-bold text-sm mb-3 flex items-center gap-2">
            <Star size={16} className="text-yellow-400" />
            ¿Por qué crear una cuenta?
          </h3>
          <div className="grid grid-cols-2 gap-2">
            {benefits.map((benefit, idx) => (
              <div key={idx} className="flex items-start gap-2 text-xs">
                <benefit.icon size={14} className={`${benefit.color} mt-0.5 shrink-0`} />
                <span className="text-slate-300 leading-tight">{benefit.text}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Tabs */}
        <div className="flex bg-slate-800 border-b border-slate-700">
          <button
            onClick={() => setMode('login')}
            className={`flex-1 py-3 text-sm font-bold transition-colors ${
              mode === 'login' 
                ? 'bg-slate-900 text-orange-500 border-b-2 border-orange-500' 
                : 'text-slate-400 hover:text-white'
            }`}
          >
            Iniciar Sesión
          </button>
          <button
            onClick={() => setMode('register')}
            className={`flex-1 py-3 text-sm font-bold transition-colors ${
              mode === 'register' 
                ? 'bg-slate-900 text-orange-500 border-b-2 border-orange-500' 
                : 'text-slate-400 hover:text-white'
            }`}
          >
            Registrarse
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4 overflow-y-auto">
          {mode === 'register' && (
            <div>
              <label className="block text-slate-300 text-sm font-medium mb-2">
                <User size={14} className="inline mr-1" />
                Nombre Completo
              </label>
              <input
                type="text"
                required
                value={formData.nombre}
                onChange={(e) => setFormData({ ...formData, nombre: e.target.value })}
                className="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                placeholder="Juan Pérez"
              />
            </div>
          )}

          <div>
            <label className="block text-slate-300 text-sm font-medium mb-2">
              <Mail size={14} className="inline mr-1" />
              Email
            </label>
            <input
              type="email"
              required
              value={formData.email}
              onChange={(e) => setFormData({ ...formData, email: e.target.value })}
              className="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
              placeholder="tu@email.com"
            />
          </div>

          <div>
            <label className="block text-slate-300 text-sm font-medium mb-2">
              <Lock size={14} className="inline mr-1" />
              Contraseña
            </label>
            <div className="relative">
              <input
                type={showPassword ? "text" : "password"}
                required
                value={formData.password}
                onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                className="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2.5 pr-20 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                placeholder="Mínimo 6 caracteres"
                minLength={6}
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-12 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors"
              >
                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
              <span className={`absolute right-4 top-1/2 -translate-y-1/2 text-xs font-mono transition-colors ${
                formData.password.length >= 6 ? 'text-green-400' : 'text-slate-500'
              }`}>
                {formData.password.length}/6
              </span>
            </div>
          </div>

          {mode === 'register' && (
            <>
              <div>
                <label className="block text-slate-300 text-sm font-medium mb-2">
                  <Lock size={14} className="inline mr-1" />
                  Confirmar Contraseña
                </label>
                <input
                  type="password"
                  required
                  value={formData.confirmPassword}
                  onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })}
                  className="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                  placeholder="Repite tu contraseña"
                />
              </div>
              <div>
                <label className="block text-slate-300 text-sm font-medium mb-2">
                  <Phone size={14} className="inline mr-1" />
                  Teléfono (opcional)
                </label>
                <input
                  type="tel"
                  value={formData.telefono}
                  onChange={(e) => setFormData({ ...formData, telefono: e.target.value })}
                  className="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                  placeholder="+56 9 1234 5678"
                />
              </div>
            </>
          )}

          {/* Feedback Message */}
          {feedback.message && (
            <div className={`flex items-center gap-2 p-3 rounded-lg text-sm ${
              feedback.type === 'success' 
                ? 'bg-green-900/30 border border-green-700 text-green-400' 
                : 'bg-red-900/30 border border-red-700 text-red-400'
            }`}>
              {feedback.type === 'success' ? <CheckCircle size={18} /> : <AlertCircle size={18} />}
              <span>{feedback.message}</span>
            </div>
          )}

          <button
            type="submit"
            disabled={loading}
            className={`w-full bg-white text-black font-bold py-3 rounded-lg transition-all shadow-lg relative overflow-hidden ${
              loading 
                ? 'cursor-not-allowed' 
                : 'hover:bg-gray-800 hover:text-white hover:border-2 hover:border-gray-400 hover:shadow-2xl hover:scale-105'
            }`}
          >
            {loading && (
              <div className="absolute inset-0 bg-yellow-400 animate-fill rounded-lg z-0"></div>
            )}
            <span className="relative z-10">
            {loading ? 'Procesando...' : mode === 'register' ? 'Crear Cuenta' : 'Iniciar Sesión'}
            </span>
          </button>

          <div className="relative">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-slate-700"></div>
            </div>
            <div className="relative flex justify-center text-xs">
              <span className="bg-slate-900 px-2 text-yellow-400 font-semibold">o continúa rápido y simple 😎👇</span>
            </div>
          </div>

          <button
            type="button"
            onClick={handleGoogleLogin}
            disabled={googleLoading}
            className={`w-full bg-white text-black font-semibold py-3 rounded-lg transition-all flex items-center justify-center gap-2 relative overflow-hidden ${
              googleLoading 
                ? 'cursor-not-allowed' 
                : 'hover:bg-gray-800 hover:text-white hover:border-2 hover:border-gray-400'
            }`}
          >
            {googleLoading && (
              <div className="absolute inset-0 bg-yellow-400 animate-fill rounded-lg z-0"></div>
            )}
            <svg className="w-5 h-5 relative z-10" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            <span className="relative z-10">Google</span>
          </button>
        </form>

      </div>
    </div>
  );
};

export default AuthModal;

// Fill animation CSS
const style = document.createElement('style');
style.textContent = `
  @keyframes fill {
    0% { clip-path: inset(0 100% 0 0); }
    100% { clip-path: inset(0 0 0 0); }
  }
  .animate-fill {
    animation: fill 2s ease-in-out infinite;
  }
`;
if (typeof document !== 'undefined' && !document.querySelector('style[data-fill]')) {
  style.setAttribute('data-fill', 'true');
  document.head.appendChild(style);
}
