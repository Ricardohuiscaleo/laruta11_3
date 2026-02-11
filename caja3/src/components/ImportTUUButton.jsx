import React, { useState } from 'react';

const ImportTUUButton = () => {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);

  const importTUUReports = async () => {
    setLoading(true);
    setResult(null);
    
    try {
      const response = await fetch('/api/import_tuu_reports.php');
      const data = await response.json();
      setResult(data);
    } catch (error) {
      setResult({ success: false, error: error.message });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="p-4 bg-white rounded-lg shadow">
      <button
        onClick={importTUUReports}
        disabled={loading}
        className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
      >
        {loading ? 'Importando...' : 'Importar Reportes TUU'}
      </button>
      
      {result && (
        <div className={`mt-4 p-3 rounded ${result.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
          {result.success 
            ? `✓ Importados: ${result.imported} de ${result.total_transactions} transacciones`
            : `✗ Error: ${result.error}`
          }
        </div>
      )}
    </div>
  );
};

export default ImportTUUButton;