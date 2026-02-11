import { useState } from 'react';

export default function TuuPayment({ cartItems, total, user }) {
  const [loading, setLoading] = useState(false);

  const handleTuuPayment = async () => {
    if (!user?.id) {
      alert('Debes iniciar sesión para pagar');
      return;
    }

    setLoading(true);
    
    try {
      // 1. Crear transacción TUU
      const orderRef = `R11-${Date.now()}`;
      
      const response = await fetch('/api/tuu-pagos-online/save_transaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: user.id,
          order_reference: orderRef,
          amount: total,
          payment_method: 'webpay',
          status: 'pending',
          customer_name: user.nombre,
          customer_email: user.email,
          customer_phone: user.telefono || '',
          tuu_transaction_id: `TUU-${Date.now()}`
        })
      });

      const result = await response.json();
      
      if (result.success) {
        // 2. Redirigir a TUU/Webpay
        window.location.href = `https://tuu.cl/pay?ref=${orderRef}&amount=${total}`;
      } else {
        alert('Error iniciando pago: ' + result.error);
      }
    } catch (error) {
      alert('Error: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <button 
      onClick={handleTuuPayment}
      disabled={loading || cartItems.length === 0}
      className="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold disabled:opacity-50"
    >
      {loading ? 'Procesando...' : `Pagar $${total.toLocaleString()} con TUU`}
    </button>
  );
}