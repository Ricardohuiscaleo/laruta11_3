import React from 'react';

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    console.error('Error capturado por ErrorBoundary:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="min-h-screen bg-white flex flex-col items-center justify-center p-4">
          <div className="text-center">
            <div className="w-16 h-16 mx-auto mb-4">
              <img src="/icon.ico" alt="La Ruta 11" className="w-full h-full" />
            </div>
            <h1 className="text-xl font-bold text-gray-800 mb-2">¡Ups! Algo salió mal</h1>
            <p className="text-gray-600 mb-4">Estamos trabajando para solucionarlo</p>
            <button 
              onClick={() => window.location.reload()} 
              className="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600"
            >
              Recargar página
            </button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;