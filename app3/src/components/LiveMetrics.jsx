import React, { useState, useEffect } from 'react';
import { Activity, TrendingUp, Clock } from 'lucide-react';

const LiveMetrics = () => {
  const [metrics, setMetrics] = useState({
    totalFiles: 0,
    totalLines: 0,
    phpFiles: 0,
    jsxFiles: 0,
    lastUpdate: null
  });

  const fetchMetrics = async () => {
    try {
      const response = await fetch('/api/get_technical_report.php');
      const data = await response.json();
      if (data.success) {
        setMetrics({
          totalFiles: data.report.summary.totalFiles,
          totalLines: data.report.summary.totalLines,
          phpFiles: data.report.categories['Backend PHP'].files,
          jsxFiles: data.report.categories.Frontend.files,
          lastUpdate: new Date()
        });
      }
    } catch (error) {
      console.error('Error fetching metrics:', error);
    }
  };

  useEffect(() => {
    fetchMetrics();
    const interval = setInterval(fetchMetrics, 60000); // Cada minuto
    return () => clearInterval(interval);
  }, []);

  return (
    <div className="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-4 rounded-lg">
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-lg font-semibold flex items-center">
          <Activity className="mr-2 h-5 w-5" />
          Métricas en Vivo
        </h3>
        <div className="flex items-center text-sm opacity-75">
          <Clock className="mr-1 h-4 w-4" />
          {metrics.lastUpdate?.toLocaleTimeString('es-CL')}
        </div>
      </div>
      
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="text-center">
          <div className="text-2xl font-bold">{metrics.totalFiles.toLocaleString()}</div>
          <div className="text-sm opacity-75">Archivos</div>
        </div>
        <div className="text-center">
          <div className="text-2xl font-bold">{metrics.totalLines.toLocaleString()}</div>
          <div className="text-sm opacity-75">Líneas</div>
        </div>
        <div className="text-center">
          <div className="text-2xl font-bold">{metrics.phpFiles}</div>
          <div className="text-sm opacity-75">APIs PHP</div>
        </div>
        <div className="text-center">
          <div className="text-2xl font-bold">{metrics.jsxFiles}</div>
          <div className="text-sm opacity-75">Frontend</div>
        </div>
      </div>
    </div>
  );
};

export default LiveMetrics;