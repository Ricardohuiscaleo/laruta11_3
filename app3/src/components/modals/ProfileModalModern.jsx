import React, { useState, useEffect } from 'react';
import { 
  X, Stamp, Gift, Truck, Utensils, Clock, QrCode, ChevronRight, 
  Star, Zap, Info, User, MapPin, Phone, Instagram, Calendar,
  LogOut, Trash2, Briefcase, Wallet, TrendingUp, ArrowDownCircle, ShoppingBag,
  CheckCircle2, Package, CreditCard, Banknote, DollarSign, RefreshCw
} from 'lucide-react';
import AddressAutocomplete from '../AddressAutocomplete.jsx';

const Card = ({ children, className = "" }) => (
  <div className={`bg-slate-800 rounded-xl border border-slate-700 shadow-lg overflow-hidden ${className}`}>
    {children}
  </div>
);

const ProfileModalModern = ({ 
  isOpen, 
  onClose, 
  user, 
  setUser, 
  userLocation, 
  locationPermission, 
  requestLocation, 
  setUserLocation, 
  setLocationPermission, 
  hasProfileChanges, 
  setHasProfileChanges, 
  setIsSaveChangesModalOpen, 
  setIsLogoutModalOpen, 
  setIsDeleteAccountModalOpen, 
  userOrders = [], 
  userStats = null, 
  showAllOrders = false, 
  setShowAllOrders = () => {}, 
  loadUserOrders 
}) => {
  if (!isOpen || !user) return null;
  
  const safeSetHasProfileChanges = setHasProfileChanges || (() => {});
  const safeSetIsSaveChangesModalOpen = setIsSaveChangesModalOpen || (() => {});
  const safeSetIsLogoutModalOpen = setIsLogoutModalOpen || (() => {});
  const safeSetIsDeleteAccountModalOpen = setIsDeleteAccountModalOpen || (() => {});
  const safeRequestLocation = requestLocation || (() => {});
  
  const [activeTab, setActiveTab] = useState('wallet');
  const [showCelebration, setShowCelebration] = useState(false);
  const [showQR, setShowQR] = useState(false);
  const [walletData, setWalletData] = useState(null);
  const [loadingWallet, setLoadingWallet] = useState(false);
  const [expandedOrders, setExpandedOrders] = useState({});
  const [rl6Credit, setRl6Credit] = useState(null);
  const [loadingRL6, setLoadingRL6] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  
  // Verificar si es militar RL6 aprobado
  const isMilitarRL6 = (user?.es_militar_rl6 == 1 || user?.es_militar_rl6 === '1') && 
                       (user?.credito_aprobado == 1 || user?.credito_aprobado === '1');
  
  const [passport, setPassport] = useState({
    hamburguesas: false,
    churrascos: false,
    completos: false
  });
  
  const [formData, setFormData] = useState({
    telefono: '',
    instagram: '',
    fechaNacimiento: '',
    genero: '',
    direccion: ''
  });
  const [saveButtonState, setSaveButtonState] = useState('idle');
  const [showHeader, setShowHeader] = useState(false);
  const [showBody, setShowBody] = useState(false);
  
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
      setActiveTab('wallet');
      setTimeout(() => setShowHeader(true), 50);
      setTimeout(() => setShowBody(true), 250);
      return () => {
        document.body.style.overflow = '';
      };
    } else {
      setShowHeader(false);
      setShowBody(false);
    }
  }, [isOpen]);
  
  useEffect(() => {
    if (isOpen && user) {
      setFormData({
        telefono: user.telefono || '',
        instagram: user.instagram || '',
        fechaNacimiento: user.fecha_nacimiento || '',
        genero: user.genero || '',
        direccion: user.direccion || user.direccion_actual || ''
      });
      safeSetHasProfileChanges(false);
      setSaveButtonState('idle');
    }
  }, [isOpen, user?.id]);
  
  useEffect(() => {
    if (isOpen && user && loadUserOrders) {
      loadUserOrders();
    }
  }, [isOpen, user, loadUserOrders]);
  
  useEffect(() => {
    if (isOpen && user && activeTab === 'wallet') {
      loadWalletData();
    }
    if (isOpen && user && activeTab === 'rl6' && isMilitarRL6) {
      loadRL6Credit();
    }
  }, [isOpen, user, activeTab]);
  
  const loadWalletData = async () => {
    setLoadingWallet(true);
    try {
      const response = await fetch(`/api/get_wallet_balance.php?user_id=${user.id}&t=${Date.now()}`);
      const data = await response.json();
      if (data.success) {
        setWalletData({
          ...data.wallet,
          transactions: data.transactions
        });
      }
    } catch (error) {
      console.error('Error loading wallet:', error);
    } finally {
      setLoadingWallet(false);
    }
  };
  
  const loadRL6Credit = async () => {
    setLoadingRL6(true);
    try {
      const response = await fetch(`/api/rl6/get_credit.php?user_id=${user.id}&t=${Date.now()}`);
      const data = await response.json();
      if (data.success) {
        setRl6Credit(data);
      }
    } catch (error) {
      console.error('Error loading RL6 credit:', error);
    } finally {
      setLoadingRL6(false);
    }
  };
  
  const handleRefreshProfile = async () => {
    setRefreshing(true);
    try {
      const response = await fetch('/api/auth/get_profile.php');
      const data = await response.json();
      if (data.success) {
        setUser(data.user);
        await loadWalletData();
        if (isMilitarRL6) {
          await loadRL6Credit();
        }
      }
    } catch (error) {
      console.error('Error refreshing profile:', error);
    } finally {
      setRefreshing(false);
    }
  };
  
  const handleInputChange = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    safeSetHasProfileChanges(true);
    setSaveButtonState('idle');
  };
  
  const handleClose = () => {
    if (hasProfileChanges) {
      safeSetIsSaveChangesModalOpen(true);
    } else {
      onClose();
    }
  };
  
  const handleSaveChanges = async () => {
    setSaveButtonState('saving');
    
    try {
      const saveFormData = new FormData();
      saveFormData.append('telefono', formData.telefono);
      saveFormData.append('instagram', formData.instagram);
      // Filtrar fecha_nacimiento inválida
      const fechaNac = formData.fecha_nacimiento || formData.fechaNacimiento;
      if (fechaNac && fechaNac !== '0000-00-00') {
        saveFormData.append('fecha_nacimiento', fechaNac);
      }
      saveFormData.append('genero', formData.genero);
      saveFormData.append('direccion', formData.direccion);
      
      const response = await fetch('/api/users/update_profile.php', {
        method: 'POST',
        body: saveFormData
      });
      
      const result = await response.json();
      if (result.success) {
        safeSetHasProfileChanges(false);
        setSaveButtonState('saved');
      } else {
        alert('Error al guardar: ' + (result.error || 'Error del servidor: ' + JSON.stringify(result)));
        setSaveButtonState('idle');
      }
    } catch (error) {
      alert('Error de conexión al guardar cambios');
      setSaveButtonState('idle');
    }
  };
  
  const triggerCelebration = () => {
    setShowCelebration(true);
    setTimeout(() => setShowCelebration(false), 3000);
  };

  return (
    <>
      <div 
        className={`fixed inset-0 bg-black transition-opacity duration-300 z-50 ${
          isOpen ? 'opacity-50' : 'opacity-0 pointer-events-none'
        }`}
        onClick={handleClose}
      />
      <div 
        className={`fixed top-0 right-0 h-full w-full bg-slate-900 shadow-2xl z-50 transform transition-transform duration-300 ease-out ${
          isOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        <div className="flex flex-col h-full">
          {/* Header */}
          <header className="bg-gradient-to-r from-orange-600 to-red-600 p-4 pt-8 pb-6 shadow-lg relative z-10">
            <div className="flex justify-between items-center">
              <div className="flex items-center gap-3">
                <img 
                  src="/icon.ico" 
                  alt="La Ruta 11" 
                  className="w-10 h-10"
                />
                <div>
                  <h1 className="text-2xl font-black italic tracking-wider text-white">LA RUTA 11</h1>
                  <p className="text-orange-100 text-xs font-medium opacity-90">MI PERFIL</p>
                </div>
              </div>
              <div className="flex gap-2">
                <button 
                  onClick={handleRefreshProfile} 
                  disabled={refreshing}
                  className="bg-white/20 p-2 rounded-full backdrop-blur-sm border border-white/30 hover:bg-white/30 transition-colors disabled:opacity-50"
                  title="Actualizar datos"
                >
                  <RefreshCw size={20} className={`text-white ${refreshing ? 'animate-spin' : ''}`} />
                </button>
                <button onClick={handleClose} className="bg-white/20 p-2 rounded-full backdrop-blur-sm border border-white/30 hover:bg-white/30 transition-colors">
                  <X size={24} className="text-white" />
                </button>
              </div>
            </div>
            
            <div className="mt-4 flex items-center gap-2 text-sm bg-black/20 p-2.5 rounded-lg backdrop-blur-md">
              <img src={user.foto_perfil} alt={user.nombre} className="h-10 w-10 rounded-full border-2 border-white/30 flex-shrink-0" />
              <div className="flex-1 min-w-0">
                <p className="font-bold text-white text-sm truncate">{user.nombre}</p>
                <p className="text-[10px] text-orange-200 truncate">{user.email}</p>
                <div className="flex items-center gap-2 mt-0.5">
                  <p className="text-[10px] text-green-300 font-semibold">💰 ${walletData?.balance ? parseInt(walletData.balance).toLocaleString('es-CL') : '0'}</p>
                  <p className="text-[10px] text-slate-300 flex items-center gap-0.5">
                    <Truck size={10} />
                    {userOrders.filter(order => order.order_status !== 'cancelled').length}
                  </p>
                </div>
              </div>
            </div>
          </header>

          {/* Navegación de Tabs */}
          <div className="px-4 mt-4">
            <div className="flex p-1 bg-slate-800 rounded-xl">
              <button 
                onClick={() => setActiveTab('profile')}
                className={`flex-1 py-3 text-sm sm:text-base font-extrabold rounded-lg transition-all flex items-center justify-center gap-1.5 sm:gap-2 ${activeTab === 'profile' ? 'bg-orange-500 text-white shadow-md' : 'text-slate-400 hover:text-white'}`}
              >
                <User size={18} />
                <span>Perfil</span>
              </button>

              <button 
                onClick={() => setActiveTab('wallet')}
                className={`flex-1 py-3 text-sm sm:text-base font-extrabold rounded-lg transition-all flex items-center justify-center gap-1.5 sm:gap-2 ${activeTab === 'wallet' ? 'bg-orange-500 text-white shadow-md' : 'text-slate-400 hover:text-white'}`}
              >
                <Wallet size={18} />
                <span>Cashback</span>
              </button>
              
              {isMilitarRL6 && (
                <button 
                  onClick={() => setActiveTab('rl6')}
                  className={`flex-1 py-3 text-sm sm:text-base font-extrabold rounded-lg transition-all flex items-center justify-center gap-1.5 sm:gap-2 ${activeTab === 'rl6' ? 'bg-orange-500 text-white shadow-md' : 'text-slate-400 hover:text-white'}`}
                >
                  <CreditCard size={18} />
                  <span>Crédito</span>
                </button>
              )}
              
              <button 
                onClick={() => setActiveTab('orders')}
                className={`flex-1 py-3 text-sm sm:text-base font-extrabold rounded-lg transition-all flex items-center justify-center gap-1.5 sm:gap-2 ${activeTab === 'orders' ? 'bg-orange-500 text-white shadow-md' : 'text-slate-400 hover:text-white'}`}
              >
                <Truck size={18} />
                <span>Pedidos</span>
              </button>
            </div>
          </div>

          {/* CONTENIDO PRINCIPAL */}
          <main className="flex-1 p-4 space-y-4 overflow-y-auto pb-6">

          {/* TAB: PERFIL */}
          {activeTab === 'profile' && (
            <div className="space-y-4 animate-fade-in">
              <Card className="p-4">
                <h3 className="text-white font-bold mb-3 flex items-center gap-2">
                  <Phone size={18} className="text-orange-500" />
                  Información Personal
                </h3>
                <div className="space-y-3">
                  <input 
                    type="tel" 
                    placeholder="Teléfono (+56 9 1234 5678)" 
                    value={formData.telefono}
                    onChange={(e) => handleInputChange('telefono', e.target.value)}
                    className="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-orange-500"
                  />
                  <input 
                    type="text" 
                    placeholder="Instagram (@usuario)" 
                    value={formData.instagram}
                    onChange={(e) => handleInputChange('instagram', e.target.value)}
                    className="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-orange-500"
                  />
                  <select 
                    value={formData.genero}
                    onChange={(e) => handleInputChange('genero', e.target.value)}
                    className="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
                  >
                    <option value="">¿Cómo te identificas?</option>
                    <option value="masculino">Masculino</option>
                    <option value="femenino">Femenino</option>
                    <option value="otro">Otro</option>
                    <option value="no_decir">Prefiero no decir</option>
                  </select>
                </div>
              </Card>

              <Card className="p-4">
                <h3 className="text-white font-bold mb-3 flex items-center gap-2">
                  <Calendar size={18} className="text-orange-500" />
                  Fecha de Nacimiento
                </h3>
                <input 
                  type="date"
                  value={formData.fecha_nacimiento && formData.fecha_nacimiento !== '0000-00-00' ? formData.fecha_nacimiento : ''}
                  onChange={(e) => handleInputChange('fecha_nacimiento', e.target.value)}
                  className="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
                />
              </Card>

              <Card className="p-4">
                <h3 className="text-white font-bold mb-3 flex items-center gap-2">
                  <MapPin size={18} className="text-orange-500" />
                  Mi Dirección
                </h3>
                <AddressAutocomplete
                  value={formData.direccion || ''}
                  onChange={(value) => handleInputChange('direccion', value)}
                  placeholder="Ingresa tu dirección"
                  className="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 pl-10 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-orange-500"
                />
                <button 
                  onClick={handleSaveChanges}
                  disabled={saveButtonState === 'saving'}
                  className={`w-full mt-3 py-3 rounded-lg font-bold transition-colors ${
                    saveButtonState === 'saving' 
                      ? 'bg-orange-500 text-white cursor-not-allowed' 
                      : saveButtonState === 'saved'
                      ? 'bg-green-500 text-white'
                      : 'bg-orange-500 text-white hover:bg-orange-600'
                  }`}
                >
                  {saveButtonState === 'saving' 
                    ? 'Guardando...' 
                    : saveButtonState === 'saved'
                    ? '✓ Actualizado'
                    : 'Guardar Cambios'
                  }
                </button>
              </Card>


              <Card className="p-4">
                <h3 className="text-white font-bold mb-3">Seguridad</h3>
                <div className="space-y-2">
                  <button 
                    onClick={() => safeSetIsLogoutModalOpen(true)}
                    className="w-full p-3 bg-orange-900/30 border border-orange-700 rounded-lg hover:bg-orange-900/50 transition-colors flex items-center gap-3"
                  >
                    <LogOut size={20} className="text-orange-400" />
                    <span className="text-orange-300 font-medium">Cerrar Sesión</span>
                  </button>
                  <button 
                    onClick={() => safeSetIsDeleteAccountModalOpen(true)}
                    className="w-full p-3 bg-red-900/30 border border-red-700 rounded-lg hover:bg-red-900/50 transition-colors flex items-center gap-3"
                  >
                    <Trash2 size={20} className="text-red-400" />
                    <span className="text-red-300 font-medium">Eliminar Cuenta</span>
                  </button>
                </div>
              </Card>
            </div>
          )}



          {/* TAB: SALDO */}
          {activeTab === 'wallet' && (
            <div className="space-y-4 animate-fade-in">
              <div className="text-center mb-2">
                <h2 className="text-xl font-bold text-white">Mi Saldo</h2>
                <p className="text-slate-400 text-sm">Dinero en cuenta para tus compras</p>
              </div>

              {loadingWallet ? (
                <Card className="p-8 text-center">
                  <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500 mx-auto"></div>
                  <p className="text-slate-400 mt-4">Cargando...</p>
                </Card>
              ) : walletData ? (
                <>
                  {/* Balance Principal */}
                  <Card className={`p-6 ${
                    walletData.balance > 0 
                      ? 'bg-gradient-to-br from-green-900/30 to-green-800/30 border-green-600' 
                      : 'bg-gradient-to-br from-slate-800 to-slate-700 border-slate-600'
                  }`}>
                    <div className="text-center">
                      <p className="text-slate-300 text-sm mb-2">Saldo Disponible</p>
                      <h3 className={`text-5xl font-black ${
                        walletData.balance > 0 ? 'text-green-400' : 'text-slate-400'
                      }`}>
                        ${parseInt(walletData.balance || 0).toLocaleString('es-CL')}
                      </h3>
                      {walletData.balance === 0 ? (
                        <p className="text-slate-400 text-xs mt-2">Completa niveles para ganar cashback</p>
                      ) : (
                        <p className="text-slate-400 text-xs mt-2">Úsalo en tu próxima compra</p>
                      )}
                    </div>
                  </Card>

                  {/* Estadísticas */}
                  <div className="grid grid-cols-2 gap-3">
                    <Card className="p-4">
                      <div className="flex items-center gap-2 mb-2">
                        <TrendingUp size={18} className="text-green-400" />
                        <span className="text-slate-400 text-xs">Total Ganado</span>
                      </div>
                      <p className="text-green-400 font-bold text-xl">
                        ${parseInt(walletData.total_earned || 0).toLocaleString('es-CL')}
                      </p>
                    </Card>
                    <Card className="p-4">
                      <div className="flex items-center gap-2 mb-2">
                        <ArrowDownCircle size={18} className="text-orange-400" />
                        <span className="text-slate-400 text-xs">Total Usado</span>
                      </div>
                      <p className="text-orange-400 font-bold text-xl">
                        ${parseInt(walletData.total_used || 0).toLocaleString('es-CL')}
                      </p>
                    </Card>
                  </div>

                  {/* Historial de Transacciones */}
                  <div>
                    <h3 className="text-white font-bold text-sm px-2 mb-3">Historial</h3>
                    {walletData.transactions && walletData.transactions.length > 0 ? (
                      <div className="space-y-2">
                        {walletData.transactions.map((tx, index) => (
                          <Card key={index} className="p-3">
                            <div className="flex justify-between items-start">
                              <div className="flex-1">
                                <p className="text-white text-sm font-medium">{tx.description}</p>
                                <p className="text-slate-500 text-xs">
                                  {new Date(tx.created_at).toLocaleDateString('es-CL', {
                                    day: 'numeric',
                                    month: 'short',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                  })}
                                </p>
                              </div>
                              <span className={`font-bold text-sm ${
                                tx.type === 'earned' ? 'text-green-400' : 'text-orange-400'
                              }`}>
                                {tx.type === 'earned' ? '+' : '-'}${parseInt(tx.amount).toLocaleString('es-CL')}
                              </span>
                            </div>
                          </Card>
                        ))}
                      </div>
                    ) : (
                      <Card className="p-6 text-center">
                        <div className="text-3xl mb-2">💰</div>
                        <p className="text-slate-400 text-sm">Sin transacciones aún</p>
                        <p className="text-slate-500 text-xs mt-2">Aquí verás tus movimientos de saldo</p>
                      </Card>
                    )}
                  </div>

                  {/* Info */}
                  <Card className="p-4 bg-blue-900/20 border-blue-700">
                    <div className="flex gap-3">
                      <Info size={20} className="text-blue-400 flex-shrink-0 mt-0.5" />
                      <div>
                        <p className="text-blue-300 text-xs font-medium mb-2">¿Cómo funciona?</p>
                        <ul className="text-slate-400 text-xs space-y-1">
                          <li>✓ Ganas 1% de cashback en cada compra</li>
                          <li>✓ Se acumula automáticamente en tu saldo</li>
                          <li>✓ Úsalo cuando tengas $500 o más</li>
                          <li>✓ Aplica solo a productos (no delivery)</li>
                        </ul>
                      </div>
                    </div>
                  </Card>
                </>
              ) : (
                <Card className="p-8 text-center">
                  <div className="text-4xl mb-2">💳</div>
                  <p className="text-slate-400">Error al cargar saldo</p>
                </Card>
              )}
            </div>
          )}

          {/* TAB: CRÉDITO RL6 */}
          {activeTab === 'rl6' && isMilitarRL6 && (
            <div className="space-y-4 animate-fade-in">
              <div className="text-center mb-2">
                <h2 className="text-xl font-bold text-white">Crédito RL6</h2>
                <p className="text-slate-400 text-sm">🎖️ Crédito exclusivo militar</p>
              </div>

              {loadingRL6 ? (
                <Card className="p-8 text-center">
                  <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500 mx-auto"></div>
                  <p className="text-slate-400 mt-4">Cargando...</p>
                </Card>
              ) : rl6Credit ? (
                <>
                  {/* Crédito Disponible */}
                  <Card className="p-6 bg-gradient-to-br from-amber-900/30 to-amber-800/30 border-amber-600">
                    <div className="text-center">
                      <p className="text-slate-300 text-sm mb-2">Crédito Disponible</p>
                      <h3 className="text-5xl font-black text-amber-400">
                        ${parseInt(rl6Credit.credit.credito_disponible || 0).toLocaleString('es-CL')}
                      </h3>
                      <p className="text-slate-400 text-xs mt-2">Paga el 21 de cada mes</p>
                    </div>
                  </Card>

                  {/* Info Militar */}
                  <Card className="p-4 bg-slate-800/50">
                    <div className="space-y-2 text-sm">
                      <div className="flex justify-between">
                        <span className="text-slate-400">Grado:</span>
                        <span className="text-white font-bold">{rl6Credit.credit.grado_militar}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-slate-400">Unidad:</span>
                        <span className="text-white font-bold">{rl6Credit.credit.unidad_trabajo}</span>
                      </div>
                    </div>
                  </Card>

                  {/* Estadísticas */}
                  <div className="grid grid-cols-2 gap-3">
                    <Card className="p-4">
                      <div className="flex items-center gap-2 mb-2">
                        <CreditCard size={18} className="text-amber-400" />
                        <span className="text-slate-400 text-xs">Límite Total</span>
                      </div>
                      <p className="text-amber-400 font-bold text-xl">
                        ${parseInt(rl6Credit.credit.limite_credito || 0).toLocaleString('es-CL')}
                      </p>
                    </Card>
                    <Card className="p-4">
                      <div className="flex items-center gap-2 mb-2">
                        <ArrowDownCircle size={18} className="text-red-400" />
                        <span className="text-slate-400 text-xs">Usado</span>
                      </div>
                      <p className="text-red-400 font-bold text-xl">
                        ${parseInt(rl6Credit.credit.credito_usado || 0).toLocaleString('es-CL')}
                      </p>
                    </Card>
                  </div>

                  {/* Historial de Transacciones */}
                  <div>
                    <h3 className="text-white font-bold text-sm px-2 mb-3">Historial de Uso</h3>
                    {rl6Credit.transactions && rl6Credit.transactions.length > 0 ? (
                      <div className="space-y-2">
                        {rl6Credit.transactions.map((tx, index) => (
                          <Card key={index} className="p-3">
                            <div className="flex justify-between items-start">
                              <div className="flex-1">
                                <p className="text-white text-sm font-medium">{tx.description}</p>
                                <p className="text-slate-500 text-xs">
                                  {new Date(tx.created_at).toLocaleDateString('es-CL', {
                                    day: 'numeric',
                                    month: 'short',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                  })}
                                </p>
                              </div>
                              <span className={`font-bold text-sm ${
                                tx.type === 'refund' ? 'text-green-400' : 'text-red-400'
                              }`}>
                                {tx.type === 'refund' ? '+' : '-'}${parseInt(tx.amount).toLocaleString('es-CL')}
                              </span>
                            </div>
                          </Card>
                        ))}
                      </div>
                    ) : (
                      <Card className="p-6 text-center">
                        <div className="text-3xl mb-2">🎖️</div>
                        <p className="text-slate-400 text-sm">Sin transacciones aún</p>
                        <p className="text-slate-500 text-xs mt-2">Usa tu crédito en tu próxima compra</p>
                      </Card>
                    )}
                  </div>

                  {/* Info */}
                  <Card className="p-4 bg-amber-900/20 border-amber-700">
                    <div className="flex gap-3">
                      <Info size={20} className="text-amber-400 flex-shrink-0 mt-0.5" />
                      <div>
                        <p className="text-amber-300 text-xs font-medium mb-2">¿Cómo funciona?</p>
                        <ul className="text-slate-400 text-xs space-y-1">
                          <li>✓ Compra ahora, paga el 21 de cada mes</li>
                          <li>✓ Usa tu crédito en cualquier compra</li>
                          <li>✓ Sin intereses ni comisiones</li>
                          <li>✓ Exclusivo para militares RL6</li>
                        </ul>
                      </div>
                    </div>
                  </Card>
                </>
              ) : (
                <Card className="p-8 text-center">
                  <div className="text-4xl mb-2">🎖️</div>
                  <p className="text-slate-400">Error al cargar crédito</p>
                </Card>
              )}
            </div>
          )}

          {/* TAB: PEDIDOS */}
          {activeTab === 'orders' && (
            <div className="space-y-4 animate-fade-in">
              <div className="flex justify-between items-center">
                <h2 className="text-xl font-bold text-white">Mis Pedidos</h2>
                <button 
                  onClick={() => setShowAllOrders(!showAllOrders)}
                  className="text-orange-500 text-sm font-medium hover:text-orange-400"
                >
                  {showAllOrders ? 'Ver menos' : 'Ver todos'}
                </button>
              </div>
              
              {userOrders.length === 0 ? (
                <Card className="p-8 text-center">
                  <div className="text-4xl mb-2">🍽️</div>
                  <p className="text-slate-400">Aún no tienes pedidos</p>
                  <p className="text-sm text-slate-500">¡Haz tu primer pedido!</p>
                </Card>
              ) : (
                <div className="space-y-3">
                  {userOrders.filter(order => order.order_status !== 'cancelled').slice(0, showAllOrders ? userOrders.length : 5).map((order, index) => {
                    const utcDate = order.created_at.replace(' ', 'T') + 'Z';
                    const chileDate = new Date(utcDate);
                    const formattedDate = chileDate.toLocaleDateString('es-CL', {
                      timeZone: 'America/Santiago',
                      day: 'numeric',
                      month: 'short'
                    }) + ', ' + chileDate.toLocaleTimeString('es-CL', {
                      timeZone: 'America/Santiago',
                      hour: '2-digit',
                      minute: '2-digit',
                      hour12: true
                    });
                    
                    const isExpanded = expandedOrders[index];
                    const isPaid = order.payment_status === 'paid';
                    const statusDisplay = !isPaid ? 'Procesando' : order.status_display;
                    
                    return (
                    <Card key={index} className="p-4 hover:border-orange-500/50 transition-all">
                      <div className="flex justify-between items-start mb-3">
                        <div className="flex items-center gap-3">
                          <div className={`p-2 rounded-lg ${
                            order.status === 'completed' ? 'bg-green-500/20' : 
                            order.status === 'pending' ? 'bg-yellow-500/20' : 
                            'bg-slate-700'
                          }`}>
                            {order.status === 'completed' ? (
                              <CheckCircle2 size={20} className="text-green-400" />
                            ) : order.status === 'pending' ? (
                              <Package size={20} className="text-yellow-400" />
                            ) : (
                              <ShoppingBag size={20} className="text-orange-500" />
                            )}
                          </div>
                          <div>
                            <h5 className="font-bold text-white text-sm">
                              Pedido #{order.order_reference}
                            </h5>
                            <p className="text-xs text-slate-400 flex items-center gap-1">
                              <Clock size={12} />
                              {formattedDate}
                            </p>
                          </div>
                        </div>
                        <span className={`text-xs px-3 py-1.5 rounded-full font-bold flex items-center gap-1 ${
                          !isPaid ? 'bg-yellow-500 text-black' :
                          order.status === 'completed' || order.status_display === 'Entregado' ? 'bg-green-500 text-white' : 
                          order.status === 'pending' ? 'bg-orange-500 text-white' : 
                          'bg-red-500 text-white'
                        }`}>
                          {(order.status === 'completed' || order.status_display === 'Entregado') && <CheckCircle2 size={12} />}
                          {statusDisplay}
                        </span>
                      </div>
                      
                      {/* Items del pedido */}
                      {order.items && order.items.length > 0 && (
                        <div className="mb-2">
                          <button
                            onClick={() => setExpandedOrders(prev => ({...prev, [index]: !prev[index]}))}
                            className="text-xs text-orange-400 hover:text-orange-300 flex items-center gap-1"
                          >
                            {isExpanded ? '▼' : '▶'} Ver productos ({order.items.length})
                          </button>
                          
                          {isExpanded && (
                            <div className="mt-2 space-y-2 bg-slate-900/50 p-2 rounded">
                              {order.items.map((item, itemIdx) => {
                                const comboData = item.combo_data;
                                let includesText = [];
                                
                                if (comboData) {
                                  if (comboData.fixed_items) {
                                    comboData.fixed_items.forEach(f => {
                                      includesText.push(typeof f === 'string' ? f : (f.product_name || f.name));
                                    });
                                  }
                                  if (comboData.selections) {
                                    Object.values(comboData.selections).forEach(sel => {
                                      if (Array.isArray(sel)) {
                                        sel.forEach(s => includesText.push(s.name || s.product_name));
                                      } else if (sel && typeof sel === 'object') {
                                        includesText.push(sel.name || sel.product_name);
                                      }
                                    });
                                  }
                                }
                                
                                return (
                                  <div key={itemIdx} className="text-xs text-slate-300">
                                    <div className="flex justify-between">
                                      <span>{item.quantity}x {item.product_name}</span>
                                      <span className="text-green-400">${parseInt(item.product_price * item.quantity).toLocaleString('es-CL')}</span>
                                    </div>
                                    {includesText.length > 0 && (
                                      <div className="text-[10px] text-slate-500 ml-4 mt-1">
                                        Incluye: {includesText.join(', ')}
                                      </div>
                                    )}
                                  </div>
                                );
                              })}
                            </div>
                          )}
                        </div>
                      )}
                      
                      <div className="flex justify-between items-center pt-3 border-t border-slate-700">
                        <div className="flex flex-col gap-1">
                          <span className={`font-bold text-lg ${
                            isPaid ? 'text-green-400' : 'text-yellow-400'
                          }`}>
                            ${parseInt(order.amount).toLocaleString('es-CL')}
                          </span>
                          {order.delivery_type === 'delivery' && order.delivery_fee > 0 && (
                            <span className="text-xs text-slate-400">
                              Incluye delivery: ${parseInt(order.delivery_fee).toLocaleString('es-CL')}
                            </span>
                          )}
                          <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold w-fit ${
                            isPaid ? 'bg-green-500/20 text-green-400' : 'bg-orange-500/20 text-orange-400'
                          }`}>
                            {isPaid ? 'Pagado' : 'Pendiente de pago'}
                          </span>
                        </div>
                        <div className="flex items-center gap-1.5 text-xs bg-slate-700/50 px-2.5 py-1.5 rounded-lg">
                          {order.payment_method === 'webpay' ? (
                            <><CreditCard size={14} className="text-blue-400" /><span className="text-slate-300 font-medium">Webpay</span></>
                          ) : order.payment_method === 'transfer' ? (
                            <><Banknote size={14} className="text-purple-400" /><span className="text-slate-300 font-medium">Transferencia</span></>
                          ) : order.payment_method === 'card' ? (
                            <><CreditCard size={14} className="text-blue-400" /><span className="text-slate-300 font-medium">Tarjeta</span></>
                          ) : (
                            <><DollarSign size={14} className="text-green-400" /><span className="text-slate-300 font-medium">Efectivo</span></>
                          )}
                        </div>
                      </div>
                    </Card>
                    );
                  })}
                </div>
              )}
              

            </div>
          )}

        </main>

        {/* OVERLAY CELEBRACIÓN */}
        {showCelebration && (
          <div className="absolute inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm animate-fade-in pointer-events-none">
            <div className="text-center transform animate-bounce-in">
              <div className="text-6xl mb-4">🎉</div>
              <h2 className="text-3xl font-black text-yellow-400 drop-shadow-lg">¡FELICIDADES!</h2>
              <p className="text-white font-bold text-lg mt-2">Premio Desbloqueado</p>
            </div>
          </div>
        )}

        {/* MODAL QR */}
        {showQR && (
          <div className="absolute inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-md animate-fade-in p-4">
             <div className="bg-white w-full max-w-sm rounded-3xl p-8 text-center relative">
                <button onClick={() => setShowQR(false)} className="absolute top-4 right-4 text-slate-400 hover:text-black">
                  <X size={24} />
                </button>
                <h3 className="text-2xl font-black text-slate-900 mb-2">CÓDIGO DE CANJE</h3>
                <p className="text-slate-500 text-sm mb-6">Muestra esto en caja para validar tu premio</p>
                
                <div className="bg-slate-100 p-6 rounded-xl inline-block mx-auto border-4 border-slate-900">
                  <QrCode size={160} className="text-slate-900" />
                </div>
                
                <p className="mt-6 text-xs font-mono text-slate-400">REF: REWARD-{user.id}-{Date.now()}</p>
                <div className="mt-4 flex items-center justify-center gap-2 text-xs text-green-600 font-bold bg-green-50 p-2 rounded">
                  <Clock size={14} />
                  Válido por 7 días
                </div>
             </div>
          </div>
        )}

        </div>
      </div>
    </>
  );
};

export default ProfileModalModern;
