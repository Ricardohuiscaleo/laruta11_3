import React, { useState, useEffect } from 'react';
import { RefreshCw, Server, Database, Code, Activity, AlertTriangle } from 'lucide-react';
import LiveMetrics from './LiveMetrics.jsx';

const TechnicalReport = () => {
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);
  const [lastUpdate, setLastUpdate] = useState(null);

  const fetchReport = async () => {
    try {
      const response = await fetch('/api/get_technical_report.php');
      const data = await response.json();
      if (data.success) {
        setReport(data.report);
        setLastUpdate(new Date());
      }
    } catch (error) {
      console.error('Error fetching technical report:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReport();
    const interval = setInterval(fetchReport, 30000); // Actualizar cada 30 segundos
    return () => clearInterval(interval);
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <RefreshCw className="animate-spin h-8 w-8 text-blue-500" />
        <span className="ml-2">Generando informe técnico...</span>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Métricas en vivo */}
      <LiveMetrics />
      
      {/* Header */}
      <div className="flex justify-between items-center">
        <h2 className="text-2xl font-bold text-gray-800">Informe Técnico - La Ruta 11</h2>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-500">
            Última actualización: {lastUpdate?.toLocaleTimeString('es-CL')}
          </span>
          <button
            onClick={fetchReport}
            className="flex items-center gap-2 px-3 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
          >
            <RefreshCw className="h-4 w-4" />
            Actualizar
          </button>
        </div>
      </div>

      {/* Métricas principales */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
          <div className="flex items-center">
            <Code className="h-8 w-8 text-blue-500" />
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Total Archivos</p>
              <p className="text-2xl font-bold text-gray-900">{report?.summary?.totalFiles || 0}</p>
            </div>
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
          <div className="flex items-center">
            <Activity className="h-8 w-8 text-green-500" />
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Líneas de Código</p>
              <p className="text-2xl font-bold text-gray-900">{report?.summary?.totalLines?.toLocaleString() || 0}</p>
            </div>
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500">
          <div className="flex items-center">
            <Server className="h-8 w-8 text-purple-500" />
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">APIs PHP</p>
              <p className="text-2xl font-bold text-gray-900">{report?.categories?.['Backend PHP']?.files || 0}</p>
            </div>
          </div>
        </div>

        <div className="bg-white p-6 rounded-lg shadow border-l-4 border-orange-500">
          <div className="flex items-center">
            <Database className="h-8 w-8 text-orange-500" />
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Scripts SQL</p>
              <p className="text-2xl font-bold text-gray-900">{report?.categories?.Database?.files || 0}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Stack tecnológico */}
      <div className="bg-white rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold mb-4">Stack Tecnológico</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {report?.categories && Object.entries(report.categories).map(([category, data]) => (
            data.files > 0 && (
              <div key={category} className="flex justify-between items-center p-3 bg-gray-50 rounded">
                <span className="font-medium">{category}</span>
                <div className="text-right">
                  <div className="text-sm text-gray-600">{data.files} archivos</div>
                  <div className="text-xs text-gray-500">{data.lines?.toLocaleString()} líneas</div>
                </div>
              </div>
            )
          ))}
        </div>
      </div>

      {/* Archivos más grandes */}
      <div className="bg-white rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold mb-4">Archivos Más Grandes</h3>
        <div className="space-y-2">
          {report?.largestFiles?.slice(0, 10).map((file, index) => (
            <div key={index} className="flex justify-between items-center p-2 hover:bg-gray-50 rounded">
              <span className="text-sm font-mono">{file.path}</span>
              <span className="text-sm text-gray-600">{file.lines?.toLocaleString()} líneas</span>
            </div>
          ))}
        </div>
      </div>

      {/* Distribución por directorios */}
      <div className="bg-white rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold mb-4">Distribución por Directorios</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {report?.directories && Object.entries(report.directories)
            .sort((a, b) => b[1].files - a[1].files)
            .slice(0, 8)
            .map(([dir, data]) => (
              <div key={dir} className="flex justify-between items-center p-3 bg-gray-50 rounded">
                <span className="font-medium">/{dir}</span>
                <div className="text-right">
                  <div className="text-sm text-gray-600">{data.files} archivos</div>
                  <div className="text-xs text-gray-500">{data.lines?.toLocaleString()} líneas</div>
                </div>
              </div>
            ))}
        </div>
      </div>

      {/* Estado del sistema */}
      <div className="bg-white rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold mb-4">Estado del Sistema</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="flex items-center p-3 bg-green-50 rounded border border-green-200">
            <div className="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
            <span className="text-green-800">Aplicación Operativa</span>
          </div>
          <div className="flex items-center p-3 bg-green-50 rounded border border-green-200">
            <div className="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
            <span className="text-green-800">API Funcionando</span>
          </div>
          <div className="flex items-center p-3 bg-green-50 rounded border border-green-200">
            <div className="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
            <span className="text-green-800">Base de Datos OK</span>
          </div>
        </div>
      </div>

      {/* Recomendaciones */}
      <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
        <div className="flex items-center mb-3">
          <AlertTriangle className="h-5 w-5 text-yellow-600 mr-2" />
          <h3 className="text-lg font-semibold text-yellow-800">Recomendaciones Técnicas</h3>
        </div>
        <ul className="space-y-2 text-sm text-yellow-700">
          <li>• Considerar refactorizar MenuApp.jsx (3,298 líneas)</li>
          <li>• Implementar tests unitarios para componentes críticos</li>
          <li>• Documentar APIs principales con OpenAPI/Swagger</li>
          <li>• Optimizar queries de base de datos más complejas</li>
          <li>• Implementar CI/CD para deployments automáticos</li>
        </ul>
      </div>
    </div>
  );
};

export default TechnicalReport;