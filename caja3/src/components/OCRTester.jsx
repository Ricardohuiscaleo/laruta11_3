import { useState } from 'react';
import Tesseract from 'tesseract.js';
import { parseReceipt, patterns, formatReceiptTable } from '../utils/receiptParser';
import { Camera, Upload, FileText, CheckCircle, AlertCircle } from 'lucide-react';

export default function OCRTester() {
  const [image, setImage] = useState(null);
  const [scanning, setScanning] = useState(false);
  const [progress, setProgress] = useState(0);
  const [rawText, setRawText] = useState('');
  const [parsed, setParsed] = useState(null);
  const [showRawText, setShowRawText] = useState(false);
  const [showPatterns, setShowPatterns] = useState(false);

  const handleImageUpload = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    // Preview de imagen
    const reader = new FileReader();
    reader.onload = (e) => setImage(e.target.result);
    reader.readAsDataURL(file);

    // Escanear con Tesseract
    setScanning(true);
    setProgress(0);

    try {
      const result = await Tesseract.recognize(file, 'spa', {
        logger: (m) => {
          if (m.status === 'recognizing text') {
            setProgress(Math.round(m.progress * 100));
          }
        }
      });

      const text = result.data.text;
      setRawText(text);
      
      // Parsear con nuestros algoritmos
      const parsedData = parseReceipt(text);
      setParsed(parsedData);
    } catch (error) {
      alert('Error al escanear: ' + error.message);
    } finally {
      setScanning(false);
    }
  };

  const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    alert('✅ Copiado al portapapeles');
  };

  return (
    <div style={{maxWidth: '1200px', margin: '0 auto', padding: '20px'}}>
      {/* Header similar a ComprasApp */}
      <div style={{marginBottom: '24px'}}>
        <h1 style={{fontSize: '28px', fontWeight: '700', marginBottom: '8px', display: 'flex', alignItems: 'center', gap: '12px'}}>
          <Camera size={32} /> Laboratorio OCR de Boletas
        </h1>
        <p style={{color: '#6b7280', fontSize: '14px'}}>
          Sube fotos de tus boletas reales para calibrar los patrones de detección
        </p>
      </div>

      {/* Upload Card - estilo ComprasApp */}
      {!image && (
        <div style={{
          background: 'white',
          padding: '24px',
          borderRadius: '12px',
          boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
          marginBottom: '24px'
        }}>
          <label style={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '60px 20px',
            background: '#f9fafb',
            border: '2px dashed #d1d5db',
            borderRadius: '12px',
            cursor: 'pointer',
            transition: 'all 0.2s'
          }}
          onMouseEnter={(e) => {
            e.currentTarget.style.background = '#f0fdf4';
            e.currentTarget.style.borderColor = '#10b981';
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.background = '#f9fafb';
            e.currentTarget.style.borderColor = '#d1d5db';
          }}>
            <input 
              type="file" 
              accept="image/*" 
              onChange={handleImageUpload}
              style={{display: 'none'}}
            />
            <Upload size={48} style={{color: '#10b981', marginBottom: '16px'}} />
            <div style={{fontSize: '18px', fontWeight: '600', marginBottom: '8px', color: '#374151'}}>
              Click para subir boleta
            </div>
            <div style={{fontSize: '14px', color: '#6b7280'}}>
              JPG, PNG o foto desde tu celular
            </div>
          </label>
        </div>
      )}

      {/* Progress - estilo ComprasApp */}
      {scanning && (
        <div style={{
          background: 'white',
          padding: '24px',
          borderRadius: '12px',
          boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
          marginBottom: '24px'
        }}>
          <div style={{display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '12px'}}>
            <div className="spinner" style={{
              width: '20px',
              height: '20px',
              border: '3px solid #e5e7eb',
              borderTopColor: '#10b981',
              borderRadius: '50%',
              animation: 'spin 0.6s linear infinite'
            }}></div>
            <span style={{fontWeight: '600', fontSize: '16px'}}>🔍 Escaneando... {progress}%</span>
          </div>
          <div style={{
            height: '8px',
            background: '#e5e7eb',
            borderRadius: '4px',
            overflow: 'hidden'
          }}>
            <div style={{
              height: '100%',
              background: '#10b981',
              width: `${progress}%`,
              transition: 'width 0.3s'
            }}></div>
          </div>
        </div>
      )}

      {/* Resultados - Grid estilo ComprasApp */}
      {parsed && (
        <div style={{display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px', marginBottom: '24px'}}>
          {/* Imagen */}
          <div style={{
            background: 'white',
            padding: '24px',
            borderRadius: '12px',
            boxShadow: '0 2px 8px rgba(0,0,0,0.1)'
          }}>
            <h3 style={{marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px'}}>
              <Camera size={20} /> Imagen Original
            </h3>
            <img 
              src={image} 
              alt="Boleta" 
              style={{
                width: '100%',
                borderRadius: '8px',
                border: '2px solid #e5e7eb'
              }}
            />
            <button
              onClick={() => {
                setImage(null);
                setParsed(null);
                setRawText('');
              }}
              style={{
                width: '100%',
                marginTop: '16px',
                padding: '12px',
                background: '#6b7280',
                color: 'white',
                border: 'none',
                borderRadius: '8px',
                cursor: 'pointer',
                fontWeight: '600'
              }}
            >
              🔄 Escanear Otra
            </button>
          </div>

          {/* Datos Extraídos */}
          <div style={{
            background: 'white',
            padding: '24px',
            borderRadius: '12px',
            boxShadow: '0 2px 8px rgba(0,0,0,0.1)'
          }}>
            <h3 style={{marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px'}}>
              {parsed.confidence > 70 ? <CheckCircle size={20} color="#10b981" /> : <AlertCircle size={20} color="#f59e0b" />}
              Datos Extraídos
            </h3>
            
            {/* Badge de Confianza */}
            <div style={{
              padding: '16px',
              background: parsed.confidence > 70 ? '#d1fae5' : '#fef3c7',
              borderRadius: '8px',
              marginBottom: '16px',
              border: `2px solid ${parsed.confidence > 70 ? '#10b981' : '#f59e0b'}`
            }}>
              <div style={{fontSize: '24px', fontWeight: '700', marginBottom: '8px'}}>
                {parsed.confidence}% Confianza
              </div>
              <div style={{fontSize: '14px', color: '#6b7280'}}>
                Patrón: <strong>{parsed.detectedPattern}</strong>
              </div>
            </div>

            {/* Datos */}
            <div style={{
              padding: '16px',
              background: '#f9fafb',
              borderRadius: '8px',
              marginBottom: '16px'
            }}>
              <div style={{marginBottom: '16px'}}>
                <div style={{fontSize: '12px', color: '#6b7280', marginBottom: '4px'}}>
                  Proveedor
                </div>
                <div style={{fontSize: '18px', fontWeight: '600'}}>
                  {parsed.provider}
                </div>
              </div>

              <div style={{marginBottom: '16px'}}>
                <div style={{fontSize: '12px', color: '#6b7280', marginBottom: '4px'}}>
                  Monto Total
                </div>
                <div style={{fontSize: '24px', fontWeight: '700', color: '#10b981'}}>
                  ${parsed.total ? parseInt(parsed.total).toLocaleString('es-CL') : 'No detectado'}
                </div>
                {parsed.totalSinIVA && (
                  <div style={{fontSize: '12px', color: '#6b7280', marginTop: '4px'}}>
                    Sin IVA: ${parseInt(parsed.totalSinIVA).toLocaleString('es-CL')} × 1.19 = ${parseInt(parsed.totalConIVA).toLocaleString('es-CL')}
                  </div>
                )}
              </div>

              <div style={{marginBottom: '16px'}}>
                <div style={{fontSize: '12px', color: '#6b7280', marginBottom: '4px'}}>
                  Fecha
                </div>
                <div style={{fontSize: '18px', fontWeight: '600'}}>
                  {parsed.date}
                </div>
              </div>

              {parsed.items.length > 0 && (
                <div>
                  <div style={{fontSize: '12px', color: '#6b7280', marginBottom: '8px'}}>
                    Items Detectados ({parsed.items.length})
                  </div>
                  <div 
                    style={{maxHeight: '300px', overflowY: 'auto'}}
                    dangerouslySetInnerHTML={{__html: formatReceiptTable(parsed)}}
                  />
                </div>
              )}
            </div>

            <button 
              onClick={() => copyToClipboard(JSON.stringify(parsed, null, 2))}
              style={{
                width: '100%',
                padding: '12px',
                background: '#10b981',
                color: 'white',
                border: 'none',
                borderRadius: '8px',
                fontWeight: '600',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '8px'
              }}
            >
              <FileText size={18} /> Copiar JSON
            </button>
          </div>
        </div>
      )}

      {/* Texto Crudo - Collapsible */}
      {rawText && (
        <div style={{
          background: 'white',
          padding: '24px',
          borderRadius: '12px',
          boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
          marginBottom: '24px'
        }}>
          <button
            onClick={() => setShowRawText(!showRawText)}
            style={{
              width: '100%',
              padding: '12px',
              background: '#f9fafb',
              border: '2px solid #e5e7eb',
              borderRadius: '8px',
              cursor: 'pointer',
              fontWeight: '600',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between'
            }}
          >
            <span style={{display: 'flex', alignItems: 'center', gap: '8px'}}>
              <FileText size={18} /> Texto OCR Completo
            </span>
            <span>{showRawText ? '▲' : '▼'}</span>
          </button>
          {showRawText && (
            <>
              <div style={{
                marginTop: '16px',
                padding: '16px',
                background: '#f9fafb',
                borderRadius: '8px',
                border: '2px solid #e5e7eb',
                maxHeight: '300px',
                overflowY: 'auto',
                fontFamily: 'monospace',
                fontSize: '12px',
                whiteSpace: 'pre-wrap'
              }}>
                {rawText}
              </div>
              <button 
                onClick={() => copyToClipboard(rawText)}
                style={{
                  marginTop: '12px',
                  padding: '10px 16px',
                  background: '#6b7280',
                  color: 'white',
                  border: 'none',
                  borderRadius: '8px',
                  cursor: 'pointer',
                  fontWeight: '600'
                }}
              >
                📋 Copiar Texto
              </button>
            </>
          )}
        </div>
      )}

      {/* Patrones - Collapsible */}
      <div style={{
        background: 'white',
        padding: '24px',
        borderRadius: '12px',
        boxShadow: '0 2px 8px rgba(0,0,0,0.1)'
      }}>
        <button
          onClick={() => setShowPatterns(!showPatterns)}
          style={{
            width: '100%',
            padding: '12px',
            background: '#f9fafb',
            border: '2px solid #e5e7eb',
            borderRadius: '8px',
            cursor: 'pointer',
            fontWeight: '600',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between'
          }}
        >
          <span>⚙️ Patrones Configurados</span>
          <span>{showPatterns ? '▲' : '▼'}</span>
        </button>
        {showPatterns && (
          <div style={{
            marginTop: '16px',
            padding: '16px',
            background: '#f9fafb',
            borderRadius: '8px',
            border: '2px solid #e5e7eb',
            fontFamily: 'monospace',
            fontSize: '12px',
            whiteSpace: 'pre-wrap',
            maxHeight: '400px',
            overflowY: 'auto'
          }}>
            {JSON.stringify(patterns, null, 2)}
          </div>
        )}
      </div>

      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  );
}
