import React, { useState, useEffect } from 'react';
import { X } from 'lucide-react';

const ProfileModal = ({ 
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
  currentSessionTime, 
  userOrders = [], 
  userStats = null, 
  showAllOrders = false, 
  setShowAllOrders = () => {}, 
  loadUserOrders 
}) => {
  // Verificaciones de seguridad
  if (!isOpen || !user) return null;
  
  // Verificar que las funciones existen
  const safeSetHasProfileChanges = setHasProfileChanges || (() => {});
  const safeSetIsSaveChangesModalOpen = setIsSaveChangesModalOpen || (() => {});
  const safeSetIsLogoutModalOpen = setIsLogoutModalOpen || (() => {});
  const safeSetIsDeleteAccountModalOpen = setIsDeleteAccountModalOpen || (() => {});
  const safeRequestLocation = requestLocation || (() => {});
  
  const [formData, setFormData] = useState({
    telefono: '',
    instagram: '',
    fechaNacimiento: '',
    genero: '',
    direccion: ''
  });
  const [saveButtonState, setSaveButtonState] = useState('idle'); // idle, saving, saved
  
  // Cargar datos frescos del perfil al abrir modal
  useEffect(() => {
    if (isOpen && user) {
      const loadProfileData = async () => {
        try {
          const response = await fetch('/api/auth/get_profile.php');
          const result = await response.json();
          
          if (result.success) {
            const userData = result.user;
            setFormData({
              telefono: userData.telefono || '',
              instagram: userData.instagram || '',
              fechaNacimiento: userData.fecha_nacimiento || '',
              genero: userData.genero || '',
              direccion: userData.direccion || ''
            });
            
            // Actualizar usuario en el estado principal
            if (setUser) {
              setUser(userData);
            }
          }
        } catch (error) {
          console.error('Error cargando perfil:', error);
        }
      };
      
      loadProfileData();
      safeSetHasProfileChanges(false);
      setSaveButtonState('idle');
    }
  }, [isOpen, user?.id]);
  
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
      saveFormData.append('fecha_nacimiento', formData.fechaNacimiento);
      saveFormData.append('genero', formData.genero);
      saveFormData.append('direccion', formData.direccion);
      
      const response = await fetch('/api/auth/update_profile.php', {
        method: 'POST',
        body: saveFormData
      });
      
      const result = await response.json();
      if (result.success) {
        console.log('Perfil actualizado exitosamente');
        safeSetHasProfileChanges(false);
        setSaveButtonState('saved');
      } else {
        console.error('Error guardando perfil:', result.error);
        alert('Error al guardar: ' + result.error);
        setSaveButtonState('idle');
      }
    } catch (error) {
      console.error('Error guardando cambios:', error);
      alert('Error de conexión al guardar cambios');
      setSaveButtonState('idle');
    }
  };
  
  const handleDiscardChanges = () => {
    safeSetHasProfileChanges(false);
    safeSetIsSaveChangesModalOpen(false);
    onClose();
  };

  const handleLogout = () => {
    window.location.href = '/api/auth/logout.php';
  };
  
  const handleDeleteAccount = () => {
    // Implementar lógica de eliminación de cuenta
    fetch('/api/auth/delete_account.php', { method: 'POST' })
      .then(() => {
        window.location.href = '/api/auth/logout.php';
      })
      .catch(error => console.error('Error eliminando cuenta:', error));
  };
  
  // Cargar ubicación guardada del usuario
  useEffect(() => {
    if (user && !userLocation && setUserLocation && setLocationPermission) {
      fetch('/api/location/get_location.php')
        .then(response => response.json())
        .then(data => {
          if (data.latitud && data.longitud) {
            setUserLocation({
              latitude: parseFloat(data.latitud),
              longitude: parseFloat(data.longitud),
              address: data.direccion_actual || `${data.latitud}, ${data.longitud}`
            });
            setLocationPermission('granted');
          }
        })
        .catch(error => console.error('Error cargando ubicación:', error));
    }
  }, [user, userLocation, setUserLocation, setLocationPermission]);
  
  // Cargar pedidos cuando se abre el modal
  useEffect(() => {
    if (isOpen && user && loadUserOrders) {
      loadUserOrders();
    }
  }, [isOpen, user, loadUserOrders]);
  
  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex justify-center items-center animate-fade-in" onClick={onClose}>
      <div className="bg-white w-full h-full flex flex-col animate-slide-up overflow-hidden" onClick={(e) => e.stopPropagation()}>
        <div className="border-b flex justify-between items-center" style={{padding: 'clamp(16px, 4vw, 24px)'}}>
          <h2 className="font-bold text-gray-800" style={{fontSize: 'clamp(18px, 5vw, 24px)'}}>Mi Perfil</h2>
          <button onClick={handleClose} className="p-1 text-gray-400 hover:text-gray-600"><X size={24} /></button>
        </div>
        
        <div className="flex-grow overflow-y-auto" style={{padding: 'clamp(16px, 4vw, 24px)'}}>
          <div className="flex items-center gap-3 sm:gap-4 mb-6 sm:mb-8">
            <img src={user.foto_perfil} alt={user.nombre} className="rounded-full" style={{width: 'clamp(60px, 15vw, 80px)', height: 'clamp(60px, 15vw, 80px)'}} />
            <div>
              <h3 className="font-bold text-gray-800" style={{fontSize: 'clamp(16px, 4.5vw, 20px)'}}>{user.nombre}</h3>
              <p className="text-gray-600" style={{fontSize: 'clamp(12px, 3vw, 16px)'}}>{user.email}</p>
            </div>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-4">
              <h4 className="font-bold text-gray-800" style={{fontSize: 'clamp(14px, 3.5vw, 16px)'}}>Información Personal</h4>
              <div className="space-y-3">
                <input 
                  type="tel" 
                  placeholder="Teléfono (+56 9 1234 5678)" 
                  value={formData.telefono}
                  onChange={(e) => handleInputChange('telefono', e.target.value)}
                  className="w-full border rounded-lg" 
                  style={{padding: 'clamp(8px, 2vw, 12px)', fontSize: 'clamp(12px, 3vw, 14px)'}} 
                />
                <input 
                  type="text" 
                  placeholder="Instagram (@usuario)" 
                  value={formData.instagram}
                  onChange={(e) => handleInputChange('instagram', e.target.value)}
                  className="w-full border rounded-lg" 
                  style={{padding: 'clamp(8px, 2vw, 12px)', fontSize: 'clamp(12px, 3vw, 14px)'}} 
                />
                <div className="space-y-2">
                  <label className="text-sm font-medium text-gray-700">Fecha de nacimiento</label>
                  <div className="grid grid-cols-3 gap-2">
                    <select 
                      value={formData.fechaNacimiento ? new Date(formData.fechaNacimiento).getDate() : ''}
                      onChange={(e) => {
                        const day = e.target.value;
                        const month = formData.fechaNacimiento ? new Date(formData.fechaNacimiento).getMonth() + 1 : '';
                        const year = formData.fechaNacimiento ? new Date(formData.fechaNacimiento).getFullYear() : '';
                        if (day && month && year) {
                          handleInputChange('fechaNacimiento', `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`);
                        }
                      }}
                      className="border rounded-lg" 
                      style={{padding: 'clamp(6px, 1.5vw, 8px)', fontSize: 'clamp(11px, 2.5vw, 13px)'}}
                    >
                      <option value="">Día</option>
                      {Array.from({length: 31}, (_, i) => i + 1).map(day => (
                        <option key={day} value={day}>{day}</option>
                      ))}
                    </select>
                    <select 
                      value={formData.fechaNacimiento ? new Date(formData.fechaNacimiento).getMonth() + 1 : ''}
                      onChange={(e) => {
                        const month = e.target.value;
                        const day = formData.fechaNacimiento ? new Date(formData.fechaNacimiento).getDate() : '';
                        const year = formData.fechaNacimiento ? new Date(formData.fechaNacimiento).getFullYear() : '';
                        if (day && month && year) {
                          handleInputChange('fechaNacimiento', `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`);
                        }
                      }}
                      className="border rounded-lg" 
                      style={{padding: 'clamp(6px, 1.5vw, 8px)', fontSize: 'clamp(11px, 2.5vw, 13px)'}}
                    >
                      <option value="">Mes</option>
                      {['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'].map((month, i) => (
                        <option key={i + 1} value={i + 1}>{month}</option>
                      ))}
                    </select>
                    <select 
                      value={formData.fechaNacimiento ? new Date(formData.fechaNacimiento).getFullYear() : ''}
                      onChange={(e) => {
                        const year = e.target.value;
                        const day = formData.fechaNacimiento ? new Date(formData.fechaNacimiento).getDate() : '';
                        const month = formData.fechaNacimiento ? new Date(formData.fechaNacimiento).getMonth() + 1 : '';
                        if (day && month && year) {
                          handleInputChange('fechaNacimiento', `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`);
                        }
                      }}
                      className="border rounded-lg" 
                      style={{padding: 'clamp(6px, 1.5vw, 8px)', fontSize: 'clamp(11px, 2.5vw, 13px)'}}
                    >
                      <option value="">Año</option>
                      {Array.from({length: 80}, (_, i) => new Date().getFullYear() - i).map(year => (
                        <option key={year} value={year}>{year}</option>
                      ))}
                    </select>
                  </div>
                </div>
                <select 
                  value={formData.genero}
                  onChange={(e) => handleInputChange('genero', e.target.value)}
                  className="w-full border rounded-lg" 
                  style={{padding: 'clamp(8px, 2vw, 12px)', fontSize: 'clamp(12px, 3vw, 14px)'}}
                >
                  <option value="">¿Cómo te identificas?</option>
                  <option value="masculino">Masculino</option>
                  <option value="femenino">Femenino</option>
                  <option value="otro">Otro</option>
                  <option value="no_decir">Prefiero no decir</option>
                </select>
              </div>
            </div>
            
            <div className="space-y-4">
              <h4 className="font-bold text-gray-800">Mi dirección</h4>
              <textarea 
                placeholder="Ingresa tu dirección" 
                value={formData.direccion || ''}
                onChange={(e) => handleInputChange('direccion', e.target.value)}
                className="w-full border rounded-lg resize-none" 
                style={{padding: 'clamp(8px, 2vw, 12px)', fontSize: 'clamp(12px, 3vw, 14px)', minHeight: '80px'}} 
                rows="3"
              />
              <button 
                onClick={handleSaveChanges}
                disabled={saveButtonState === 'saving'}
                className={`w-full py-3 rounded-lg font-medium transition-colors ${
                  saveButtonState === 'saving' 
                    ? 'bg-orange-500 text-white cursor-not-allowed' 
                    : saveButtonState === 'saved'
                    ? 'bg-blue-500 text-white'
                    : 'bg-green-500 text-white hover:bg-green-600'
                }`}
              >
                {saveButtonState === 'saving' 
                  ? 'Guardando...' 
                  : saveButtonState === 'saved'
                  ? 'Actualizado'
                  : 'Guardar Cambios'
                }
              </button>
            </div>
          </div>
          
          <div className="mt-6 p-4 bg-gray-50 rounded-lg">
            <h4 className="font-bold text-gray-800 mb-3">Ubicación Actual</h4>
            {userLocation ? (
              <div className="space-y-3">
                <div className="text-sm text-gray-700">
                  <p>📍 {userLocation.address || 'Ubicación detectada'}</p>
                </div>
                <button 
                  onClick={safeRequestLocation}
                  className="text-xs text-orange-500 hover:text-orange-600"
                >
                  Actualizar ubicación
                </button>
              </div>
            ) : (
              <div className="text-center py-4">
                <p className="text-sm text-gray-600 mb-3">No se ha detectado tu ubicación</p>
                <button 
                  onClick={safeRequestLocation}
                  className="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 text-sm"
                  disabled={locationPermission === 'requesting'}
                >
                  {locationPermission === 'requesting' ? 'Detectando...' : 'Activar Ubicación'}
                </button>
              </div>
            )}
          </div>
          
          <div className="mt-8 space-y-4">
            <div className="flex justify-between items-center">
              <h4 className="font-bold text-gray-800">Mis Pedidos</h4>
              <button 
                onClick={() => setShowAllOrders(!showAllOrders)}
                className="text-orange-500 text-sm font-medium hover:text-orange-600"
              >
                {showAllOrders ? 'Ver menos' : 'Ver todos'}
              </button>
            </div>
            
            {/* Lista de pedidos (1 por fila) */}
            <div className="space-y-3">
              {userOrders.length === 0 ? (
                <div className="text-center py-8 text-gray-500">
                  <div className="text-4xl mb-2">🍽️</div>
                  <p>Aún no tienes pedidos</p>
                  <p className="text-sm">¡Haz tu primer pedido!</p>
                </div>
              ) : (
                userOrders.slice(0, showAllOrders ? userOrders.length : 3).map((order, index) => (
                  <div key={index} className="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition-colors">
                    <div className="flex justify-between items-start mb-2">
                      <div className="flex items-center gap-2">
                        <span className="text-lg">🍔</span>
                        <div>
                          <h5 className="font-semibold text-gray-800 text-sm">
                            Pedido {order.order_reference}
                          </h5>
                          <p className="text-xs text-gray-500">
                            {new Date(order.created_at).toLocaleDateString('es-CL', {
                              day: 'numeric',
                              month: 'short',
                              year: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit'
                            })}
                          </p>
                        </div>
                      </div>
                      <span className="text-xs px-2 py-1 rounded-full font-medium" 
                            style={{
                              backgroundColor: order.status === 'completed' ? '#dcfce7' : 
                                              order.status === 'pending' ? '#fef3c7' : '#fee2e2',
                              color: order.status === 'completed' ? '#166534' : 
                                     order.status === 'pending' ? '#92400e' : '#991b1b'
                            }}>
                        {order.status_display}
                      </span>
                    </div>
                    
                    <div className="flex justify-between items-center">
                      <div className="flex items-center gap-4">
                        <span className="font-bold text-green-600">
                          ${parseInt(order.amount).toLocaleString()}
                        </span>
                        <span className="text-xs text-gray-500">
                          {order.payment_method === 'webpay' ? '💳 Webpay' : '💰 Efectivo'}
                        </span>
                      </div>
                      
                      <div className="flex gap-2">
                        <button 
                          onClick={() => {
                            alert(`Pedido: ${order.order_reference}\\nProductos: ${order.product_name}\\nTotal: $${parseInt(order.amount).toLocaleString()}\\nEstado: ${order.status_display}\\nFecha: ${new Date(order.created_at).toLocaleDateString('es-CL')}`);
                          }}
                          className="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200 transition-colors"
                        >
                          Ver Detalle
                        </button>
                        {order.status === 'completed' && (
                          <button 
                            onClick={() => {
                              if (confirm('¿Repetir este pedido? Se agregará al carrito.')) {
                                alert('Funcionalidad de repetir pedido en desarrollo');
                              }
                            }}
                            className="text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded hover:bg-orange-200 transition-colors"
                          >
                            Repetir
                          </button>
                        )}
                      </div>
                    </div>
                    
                    {order.customer_phone && (
                      <div className="mt-2 pt-2 border-t border-gray-200">
                        <p className="text-xs text-gray-500">
                          📍 Entrega: {order.customer_phone}
                        </p>
                      </div>
                    )}
                  </div>
                ))
              )}
            </div>
            
            {/* Estadísticas rápidas */}
            {userStats && userStats.total_orders > 0 && (
              <div className="bg-orange-50 rounded-lg p-4 mt-4">
                <h5 className="font-semibold text-gray-800 mb-2">📊 Resumen</h5>
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <p className="text-gray-600">Total pedidos</p>
                    <p className="font-bold text-orange-600">{userStats.total_orders}</p>
                  </div>
                  <div>
                    <p className="text-gray-600">Total gastado</p>
                    <p className="font-bold text-green-600">
                      ${parseInt(userStats.total_spent || 0).toLocaleString()}
                    </p>
                  </div>
                </div>
              </div>
            )}
          </div>
          
          <div className="mt-8 space-y-4">
            <h4 className="font-bold text-gray-800">Oportunidades Laborales</h4>
            <div className="grid grid-cols-1 gap-3">
              <button 
                onClick={() => window.open('/jobs/maestro-sanguchero', '_blank')}
                className="p-4 bg-amber-50 border border-amber-200 rounded-lg text-left hover:bg-amber-100 transition-colors"
              >
                <div className="flex items-center gap-3">
                  <span className="text-2xl">👨🍳</span>
                  <div>
                    <h5 className="font-semibold text-amber-700">Postular como Maestro/a Sanguchero/a</h5>
                    <p className="text-sm text-amber-600">Únete a nuestro equipo de cocina</p>
                  </div>
                </div>
              </button>
              <button 
                onClick={() => window.open('/jobs/cajero', '_blank')}
                className="p-4 bg-blue-50 border border-blue-200 rounded-lg text-left hover:bg-blue-100 transition-colors"
              >
                <div className="flex items-center gap-3">
                  <span className="text-2xl">💼</span>
                  <div>
                    <h5 className="font-semibold text-blue-700">Postular como Cajero/a</h5>
                    <p className="text-sm text-blue-600">Sé la cara amable de La Ruta 11</p>
                  </div>
                </div>
              </button>
            </div>
          </div>
          
          <div className="mt-8 space-y-4">
            <h4 className="font-bold text-gray-800" style={{fontSize: 'clamp(14px, 3.5vw, 16px)'}}>Seguridad de la Cuenta</h4>
            <div className="grid grid-cols-1 gap-3">
              <button 
                onClick={() => safeSetIsLogoutModalOpen(true)}
                className="p-4 bg-orange-50 border border-orange-200 rounded-lg text-left hover:bg-orange-100 transition-colors"
              >
                <div className="flex items-center gap-3">
                  <span className="text-2xl">🚪</span>
                  <div>
                    <h5 className="font-semibold text-orange-700">Cerrar Sesión</h5>
                    <p className="text-sm text-orange-600">Salir de tu cuenta actual</p>
                  </div>
                </div>
              </button>
              <button 
                onClick={() => safeSetIsDeleteAccountModalOpen(true)}
                className="p-4 bg-red-50 border border-red-200 rounded-lg text-left hover:bg-red-100 transition-colors"
              >
                <div className="flex items-center gap-3">
                  <span className="text-2xl">⚠️</span>
                  <div>
                    <h5 className="font-semibold text-red-700">Eliminar Cuenta</h5>
                    <p className="text-sm text-red-600">Eliminar permanentemente tu cuenta</p>
                  </div>
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ProfileModal;