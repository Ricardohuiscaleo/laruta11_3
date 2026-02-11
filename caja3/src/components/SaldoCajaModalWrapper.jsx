import { useState, useEffect } from 'react';
import SaldoCajaModal from './modals/SaldoCajaModal.jsx';

export default function SaldoCajaModalWrapper() {
  const [isOpen, setIsOpen] = useState(false);

  useEffect(() => {
    const handleOpen = () => setIsOpen(true);
    window.addEventListener('openSaldoCajaModal', handleOpen);
    return () => window.removeEventListener('openSaldoCajaModal', handleOpen);
  }, []);

  const handleClose = () => {
    setIsOpen(false);
    // Recargar saldo después de cerrar
    if (window.loadSaldoCaja) {
      window.loadSaldoCaja();
    }
  };

  return <SaldoCajaModal isOpen={isOpen} onClose={handleClose} />;
}
