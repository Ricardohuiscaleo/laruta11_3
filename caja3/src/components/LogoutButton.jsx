import React from 'react';

const LogoutButton = () => {
  const handleLogout = () => {
    localStorage.removeItem('caja_session');
    window.location.href = '/login';
  };

  return (
    <button 
      onClick={handleLogout}
      style={{
        position: 'fixed',
        top: '20px',
        right: '20px',
        background: '#e74c3c',
        color: 'white',
        border: 'none',
        borderRadius: '8px',
        padding: '10px 15px',
        fontSize: '14px',
        cursor: 'pointer',
        zIndex: 1000,
        fontWeight: '600'
      }}
    >
      Cerrar Sesión
    </button>
  );
};

export default LogoutButton;