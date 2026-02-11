import { useState } from 'react';

export default function MultiPOSCheckout({ amount, orderId, description, cartType = 'web' }) {
    const [status, setStatus] = useState('idle');
    const [idempotencyKey, setIdempotencyKey] = useState(null);
    const [message, setMessage] = useState('');
    const [selectedPOS, setSelectedPOS] = useState('pos1');

    const posOptions = [
        { id: 'pos1', name: 'POS Principal', location: 'Mostrador Principal' },
        { id: 'pos2', name: 'POS Secundario', location: 'Caja 2', disabled: true }
    ];

    const initiatePayment = async () => {
        setStatus('loading');
        
        try {
            const response = await fetch('/api/tuu_multi_checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    amount, 
                    orderId, 
                    description,
                    posDevice: selectedPOS,
                    cartType
                })
            });

            const data = await response.json();

            if (data.success) {
                setIdempotencyKey(data.idempotencyKey);
                setMessage(data.message);
                setStatus('waiting');
                startPolling(data.idempotencyKey);
            } else {
                setMessage(data.error);
                setStatus('error');
            }
        } catch (error) {
            setMessage('Error de conexión');
            setStatus('error');
        }
    };

    const startPolling = (id) => {
        const interval = setInterval(async () => {
            try {
                const response = await fetch(`/api/tuu_status_check.php?idempotencyKey=${id}`);
                const data = await response.json();

                if (data.success && data.status !== 'Pending') {
                    clearInterval(interval);
                    setStatus(data.status === 'Completed' ? 'success' : 'failed');
                    setMessage(data.status === 'Completed' ? 'Pago completado exitosamente' : `Pago ${data.status.toLowerCase()}`);
                }
            } catch (error) {
                console.error('Error checking status:', error);
            }
        }, 3000);

        setTimeout(() => clearInterval(interval), 300000);
    };

    return (
        <div className="multi-pos-checkout">
            <h3>Pagar con TUU.cl</h3>
            <p>Monto: ${amount}</p>
            <p>Tipo: {cartType === 'web' ? 'Pedido Web' : `Carrito Físico ${cartType.split('_')[2]}`}</p>
            
            {status === 'idle' && (
                <div>
                    <div className="pos-selector">
                        <label>Seleccionar POS:</label>
                        {posOptions.map(pos => (
                            <div key={pos.id}>
                                <input
                                    type="radio"
                                    id={pos.id}
                                    name="pos"
                                    value={pos.id}
                                    checked={selectedPOS === pos.id}
                                    onChange={(e) => setSelectedPOS(e.target.value)}
                                    disabled={pos.disabled}
                                />
                                <label htmlFor={pos.id}>
                                    {pos.name} - {pos.location}
                                    {pos.disabled && ' (No configurado)'}
                                </label>
                            </div>
                        ))}
                    </div>
                    <button onClick={initiatePayment} className="btn-primary">
                        Iniciar Pago
                    </button>
                </div>
            )}

            {status === 'loading' && <p>Iniciando pago...</p>}
            
            {status === 'waiting' && (
                <div>
                    <p>{message}</p>
                    <p>Verificando estado del pago...</p>
                    <div className="spinner"></div>
                </div>
            )}

            {status === 'success' && (
                <div className="success">
                    <p>✅ {message}</p>
                </div>
            )}

            {status === 'failed' && (
                <div className="error">
                    <p>❌ {message}</p>
                    <button onClick={() => setStatus('idle')}>Reintentar</button>
                </div>
            )}
        </div>
    );
}