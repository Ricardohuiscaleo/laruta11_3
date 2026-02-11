export const validateCheckoutForm = (customerInfo, user) => {
  const name = customerInfo.name || user?.nombre;
  const phone = customerInfo.phone || user?.telefono;
  
  if (!name || !phone) {
    return { isValid: false, message: 'Por favor completa tu nombre y teléfono' };
  }
  
  if (customerInfo.deliveryType === 'pickup' && !customerInfo.pickupTime) {
    return { isValid: false, message: 'Por favor selecciona un horario de retiro' };
  }
  
  if (customerInfo.deliveryType === 'delivery' && !customerInfo.address) {
    return { isValid: false, message: 'Por favor ingresa tu dirección de entrega' };
  }
  
  return { isValid: true };
};

export const getFormDisabledState = (customerInfo, user) => {
  const name = customerInfo.name || user?.nombre;
  const phone = customerInfo.phone || user?.telefono;
  
  return !name || !phone || 
    (customerInfo.deliveryType === 'pickup' && !customerInfo.pickupTime) ||
    (customerInfo.deliveryType === 'delivery' && !customerInfo.address);
};