import { useState, useEffect } from 'react';
import { ShoppingCart, Package, DollarSign, TrendingUp, Calendar, User, FileText, Plus, Trash2, Edit, Check, X, PlusCircle, Paperclip, Image, Search, Banknote, Building2, CreditCard, Wallet, History, AlertTriangle, ChevronDown } from 'lucide-react';

export default function ComprasApp() {
  const [activeTab, setActiveTab] = useState('registro');
  const [showKpiMenu, setShowKpiMenu] = useState(false);
  const [showTabMenu, setShowTabMenu] = useState(false);
  const [stockTab, setStockTab] = useState('ingredientes');
  const [stockFilter, setStockFilter] = useState('');
  const [stockSort, setStockSort] = useState('criticidad');
  const [proyeccionItems, setProyeccionItems] = useState([]);
  const [compras, setCompras] = useState([]);
  const [ingredientes, setIngredientes] = useState([]);
  const [saldoDisponible, setSaldoDisponible] = useState(0);
  const [resumenFinanciero, setResumenFinanciero] = useState(null);
  const [loading, setLoading] = useState(false);
  const [showSaldoModal, setShowSaldoModal] = useState(false);
  const [historialSaldo, setHistorialSaldo] = useState([]);

  // Form state
  const [formData, setFormData] = useState({
    proveedor: '',
    fecha_compra: new Date().toISOString().split('T')[0],
    tipo_compra: 'ingredientes',
    metodo_pago: 'cash',
    notas: '',
    items: []
  });

  const [currentItem, setCurrentItem] = useState({
    ingrediente_id: '',
    product_id: '',
    item_type: '',
    nombre_item: '',
    cantidad: '',
    unidad: 'kg',
    precio_total: '',
    precio_unitario: '',
    con_iva: true
  });
  const [proyeccionItem, setProyeccionItem] = useState({
    ingrediente_id: '',
    product_id: '',
    item_type: '',
    nombre_item: '',
    cantidad: '',
    unidad: 'kg',
    precio_total: '',
    precio_unitario: ''
  });
  const [searchTerm, setSearchTerm] = useState('');
  const [filteredIngredientes, setFilteredIngredientes] = useState([]);
  const [showNewIngredientModal, setShowNewIngredientModal] = useState(false);
  const [newIngredient, setNewIngredient] = useState({
    name: '',
    category: 'Ingredientes',
    unit: 'kg',
    cost_per_unit: '',
    supplier: ''
  });
  const [isCreatingItem, setIsCreatingItem] = useState(false);
  const [proveedores, setProveedores] = useState([]);
  const [filteredProveedores, setFilteredProveedores] = useState([]);
  const [showProveedorDropdown, setShowProveedorDropdown] = useState(false);
  const [selectedCompras, setSelectedCompras] = useState([]);
  const [showRendicionModal, setShowRendicionModal] = useState(false);
  const [montoTransferencia, setMontoTransferencia] = useState('');
  const [saldoAnterior, setSaldoAnterior] = useState('');
  const [uploadingRespaldo, setUploadingRespaldo] = useState(null);
  const [respaldoFile, setRespaldoFile] = useState(null);
  const [respaldoPreview, setRespaldoPreview] = useState(null);
  const [historialSearchTerm, setHistorialSearchTerm] = useState('');
  const [showComprasSearch, setShowComprasSearch] = useState(false);
  const [comprasSearchTerm, setComprasSearchTerm] = useState('');

  useEffect(() => {
    loadIngredientes();
    loadCompras();
    loadSaldoDisponible();
    loadProveedores();
  }, []);

  const loadIngredientes = async () => {
    try {
      const response = await fetch(`/api/compras/get_items_compra.php?t=${Date.now()}`, {
        cache: 'no-store',
        headers: { 'Cache-Control': 'no-cache' }
      });
      const data = await response.json();
      setIngredientes(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error:', error);
      setIngredientes([]);
    }
  };

  const loadCompras = async () => {
    try {
      const response = await fetch(`/api/compras/get_compras.php?t=${Date.now()}`, {
        cache: 'no-store',
        headers: { 'Cache-Control': 'no-cache' }
      });
      const data = await response.json();
      console.log('Compras cargadas:', data);
      if (data.success) {
        setCompras(data.compras || []);
        console.log('Primera compra:', data.compras[0]);
        console.log('Primera compra items:', data.compras[0]?.items);
        console.log('Items count:', data.compras[0]?.items_count);
      }
    } catch (error) {
      console.error('Error:', error);
    }
  };

  const loadProveedores = async () => {
    try {
      const response = await fetch(`/api/compras/get_proveedores.php?t=${Date.now()}`, {
        cache: 'no-store',
        headers: { 'Cache-Control': 'no-cache' }
      });
      const data = await response.json();
      if (data.success) {
        setProveedores(data.proveedores || []);
      }
    } catch (error) {
      console.error('Error:', error);
    }
  };

  const loadSaldoDisponible = async () => {
    try {
      const response = await fetch(`/api/compras/get_saldo_disponible.php?t=${Date.now()}`, {
        cache: 'no-store',
        headers: { 'Cache-Control': 'no-cache' }
      });
      const data = await response.json();
      if (data.success) {
        const oldSaldo = saldoDisponible;
        const newSaldo = data.saldo_disponible || 0;
        
        // Animación si el saldo cambió
        if (oldSaldo !== newSaldo && oldSaldo !== 0) {
          const saldoCard = document.querySelector('.saldo-card');
          if (saldoCard) {
            saldoCard.style.animation = 'pulse 0.5s ease-in-out';
            setTimeout(() => {
              saldoCard.style.animation = '';
            }, 500);
          }
        }
        
        setSaldoDisponible(newSaldo);
        setResumenFinanciero(data);
      }
    } catch (error) {
      console.error('Error:', error);
    }
  };

  const loadHistorialSaldo = async () => {
    try {
      const response = await fetch(`/api/compras/get_historial_saldo.php?t=${Date.now()}`, {
        cache: 'no-store',
        headers: { 'Cache-Control': 'no-cache' }
      });
      const data = await response.json();
      if (data.success) {
        setHistorialSaldo(data.movimientos || []);
        setHistorialSearchTerm('');
        setShowSaldoModal(true);
      }
    } catch (error) {
      console.error('Error:', error);
    }
  };

  const handleAddItem = async () => {
    const nombre = currentItem.nombre_item || searchTerm.trim();
    if (!nombre || !currentItem.cantidad || !currentItem.precio_unitario) {
      alert('Complete nombre, cantidad y precio');
      return;
    }

    const cantidad = parseFloat(currentItem.cantidad);
    let precio_unitario = parseFloat(currentItem.precio_unitario);
    
    // Si el precio NO tiene IVA, calcularlo
    if (!currentItem.con_iva) {
      precio_unitario = precio_unitario * 1.19;
    }
    
    const subtotal = cantidad * precio_unitario;

    // Si es producto, NO crear ingrediente
    if (currentItem.item_type === 'product') {
      const newItem = { 
        ...currentItem,
        nombre_item: nombre,
        precio_unitario: precio_unitario.toFixed(2),
        subtotal 
      };
      
      setFormData({
        ...formData,
        items: [...formData.items, newItem]
      });

      setCurrentItem({
        ingrediente_id: '',
        product_id: '',
        item_type: '',
        nombre_item: '',
        cantidad: '',
        unidad: 'kg',
        precio_total: '',
        precio_unitario: '',
        con_iva: true
      });
      setSearchTerm('');
      return;
    }

    // Solo crear ingrediente si NO tiene ingrediente_id Y NO es producto
    let ingrediente_id = currentItem.ingrediente_id;
    if (!ingrediente_id) {
      setIsCreatingItem(true);
      try {
        const response = await fetch(`/api/save_ingrediente.php?t=${Date.now()}`, {
          method: 'POST',
          headers: { 
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache'
          },
          cache: 'no-store',
          body: JSON.stringify({
            name: nombre,
            category: 'Ingredientes',
            unit: currentItem.unidad || 'kg',
            cost_per_unit: precio_unitario,
            current_stock: 0,
            min_stock_level: 1,
            is_active: 1
          })
        });
        const data = await response.json();
        if (data.success) {
          ingrediente_id = data.id;
          await loadIngredientes();
        } else {
          alert(`⚠️ No se pudo crear el ingrediente: ${data.error || 'Error desconocido'}`);
          setIsCreatingItem(false);
          return;
        }
      } catch (error) {
        alert(`❌ Error al crear ingrediente: ${error.message}`);
        setIsCreatingItem(false);
        return;
      } finally {
        setIsCreatingItem(false);
      }
    }

    const newItem = { 
      ...currentItem,
      ingrediente_id: ingrediente_id,
      item_type: 'ingredient',
      nombre_item: nombre,
      precio_unitario: precio_unitario.toFixed(2),
      subtotal 
    };
    
    setFormData({
      ...formData,
      items: [...formData.items, newItem]
    });

    setCurrentItem({
      ingrediente_id: '',
      product_id: '',
      item_type: '',
      nombre_item: '',
      cantidad: '',
      unidad: 'kg',
      precio_total: '',
      precio_unitario: '',
      con_iva: true
    });
    setSearchTerm('');
  };

  const handleRemoveItem = (index) => {
    setFormData({
      ...formData,
      items: formData.items.filter((_, i) => i !== index)
    });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (formData.items.length === 0) {
      alert('Agregue al menos un item a la compra');
      return;
    }

    const montoTotal = formData.items.reduce((sum, item) => sum + item.subtotal, 0);

    if (montoTotal > saldoDisponible) {
      alert(`Saldo insuficiente. Disponible: $${fmt(saldoDisponible)}`);
      return;
    }

    setLoading(true);
    try {
      const response = await fetch(`/api/compras/registrar_compra.php?t=${Date.now()}`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Cache-Control': 'no-cache'
        },
        cache: 'no-store',
        body: JSON.stringify({
          ...formData,
          monto_total: montoTotal,
          usuario: localStorage.getItem('userName') || 'Admin'
        })
      });

      const data = await response.json();
      if (data.success) {
        const compraId = data.compra_id;
        
        // Subir respaldo si existe
        if (respaldoFile) {
          const formData = new FormData();
          formData.append('image', respaldoFile);
          formData.append('compra_id', compraId);
          
          try {
            await fetch(`/api/compras/upload_respaldo.php?t=${Date.now()}`, {
              method: 'POST',
              body: formData,
              cache: 'no-store'
            });
          } catch (error) {
            console.error('Error subiendo respaldo:', error);
          }
        }
        
        alert(`✅ Compra registrada\n\n💰 Saldo nuevo: $${fmt(data.saldo_nuevo || 0)}`);
        setFormData({
          proveedor: '',
          fecha_compra: new Date().toISOString().split('T')[0],
          tipo_compra: 'ingredientes',
          metodo_pago: 'cash',
          notas: '',
          items: []
        });
        setRespaldoFile(null);
        setRespaldoPreview(null);
        loadCompras();
        loadSaldoDisponible();
      } else {
        alert('Error: ' + data.error);
      }
    } catch (error) {
      alert('Error al registrar compra');
    } finally {
      setLoading(false);
    }
  };

  const fuzzyMatch = (str, pattern) => {
    const strLower = str.toLowerCase();
    const patternLower = pattern.toLowerCase();
    
    // Exact match at start
    if (strLower.startsWith(patternLower)) return 1000;
    
    // Word boundary match
    const words = strLower.split(/\s+/);
    for (let word of words) {
      if (word.startsWith(patternLower)) return 500;
    }
    
    // Fuzzy match
    let patternIdx = 0;
    let score = 0;
    let consecutiveMatches = 0;
    
    for (let i = 0; i < strLower.length && patternIdx < patternLower.length; i++) {
      if (strLower[i] === patternLower[patternIdx]) {
        consecutiveMatches++;
        score += consecutiveMatches * 2;
        patternIdx++;
      } else {
        consecutiveMatches = 0;
      }
    }
    
    return patternIdx === patternLower.length ? score : 0;
  };

  const handleSearchChange = (e) => {
    const term = e.target.value;
    setSearchTerm(term);
    console.log('Buscando:', term, 'Total ingredientes:', ingredientes.length);
    if (term.trim() === '') {
      setFilteredIngredientes([]);
    } else {
      const filtered = ingredientes
        .map(i => ({ ...i, score: fuzzyMatch(i.name, term) }))
        .filter(i => i.score > 0)
        .sort((a, b) => b.score - a.score)
        .slice(0, 10);
      console.log('Resultados filtrados:', filtered.length);
      setFilteredIngredientes(filtered);
    }
  };

  const handleIngredienteSelect = async (ingrediente, isProyeccion = false) => {
    const baseData = {
      ingrediente_id: ingrediente.type === 'ingredient' ? ingrediente.id : null,
      product_id: ingrediente.type === 'product' ? ingrediente.id : null,
      item_type: ingrediente.type,
      nombre_item: ingrediente.name,
      unidad: ingrediente.unit,
      stock_actual: ingrediente.current_stock,
      precio_unitario: '',
      cantidad: '',
      precio_total: ''
    };

    // Buscar precio histórico (solo para ingredientes)
    if (ingrediente.type === 'ingredient') {
      try {
        const response = await fetch(`/api/compras/get_precio_historico.php?ingrediente_id=${ingrediente.id}&t=${Date.now()}`, {
          cache: 'no-store',
          headers: { 'Cache-Control': 'no-cache' }
        });
        const data = await response.json();
        console.log('💰 Historial precio:', data);
        if (data.success) {
          baseData.precio_unitario = data.precio_unitario;
          baseData.unidad = data.unidad;
          console.log('✅ Precio autocompletado:', data.precio_unitario);
        }
      } catch (error) {
        console.log('❌ Sin historial de precios');
      }
    }

    if (isProyeccion) {
      setProyeccionItem(baseData);
      console.log('📦 Proyección item actualizado:', baseData);
    } else {
      setCurrentItem(baseData);
    }
    setSearchTerm(ingrediente.name);
    setFilteredIngredientes([]);
  };

  const handleCreateIngredient = async () => {
    if (!newIngredient.name || !newIngredient.cost_per_unit) {
      alert('Complete nombre y costo por unidad');
      return;
    }
    try {
      const response = await fetch(`/api/save_ingrediente.php?t=${Date.now()}`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Cache-Control': 'no-cache'
        },
        cache: 'no-store',
        body: JSON.stringify({...newIngredient, current_stock: 0})
      });
      const data = await response.json();
      if (data.success) {
        alert(`Ingrediente ${data.action === 'created' ? 'creado' : 'actualizado'} correctamente`);
        setShowNewIngredientModal(false);
        setNewIngredient({ name: '', category: 'Ingredientes', unit: 'kg', cost_per_unit: '', supplier: '' });
        await loadIngredientes();
      } else {
        alert('Error: ' + data.error);
      }
    } catch (error) {
      alert('Error al crear ingrediente');
    }
  };

  const fmt = (n) => Math.round(n).toLocaleString('es-CL');

  const handleSelectCompra = (compraId) => {
    setSelectedCompras(prev => 
      prev.includes(compraId) ? prev.filter(id => id !== compraId) : [...prev, compraId]
    );
  };

  const handleDeleteCompra = async (compraId) => {
    if (!window.confirm('¿Eliminar esta compra?\n\nEsto revertirá el inventario.')) return;
    
    try {
      const response = await fetch(`/api/compras/delete_compra.php?t=${Date.now()}`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Cache-Control': 'no-cache'
        },
        cache: 'no-store',
        body: JSON.stringify({ compra_id: compraId })
      });
      const data = await response.json();
      if (data.success) {
        alert('✅ Compra eliminada');
        loadCompras();
        loadSaldoDisponible();
      } else {
        alert('❌ Error: ' + data.error);
      }
    } catch (error) {
      alert('❌ Error al eliminar compra');
    }
  };

  const handleUploadRespaldo = async (compraId, event) => {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validar tipo de archivo
    if (!file.type.startsWith('image/')) {
      alert('❌ Por favor selecciona una imagen');
      return;
    }
    
    // Validar tamaño (máx 10MB)
    if (file.size > 10 * 1024 * 1024) {
      alert('❌ La imagen es demasiado grande (máx 10MB)');
      return;
    }
    
    setUploadingRespaldo(compraId);
    const formData = new FormData();
    formData.append('image', file);
    formData.append('compra_id', compraId);
    
    try {
      const response = await fetch(`/api/compras/upload_respaldo.php?t=${Date.now()}`, {
        method: 'POST',
        body: formData,
        cache: 'no-store'
      });
      
      const text = await response.text();
      console.log('Raw response:', text);
      
      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        alert('❌ Error parseando respuesta del servidor:\n\n' + text.substring(0, 500));
        console.error('Parse error:', e, 'Response:', text);
        return;
      }
      
      if (data.success) {
        let msg = '✅ Respaldo subido correctamente';
        if (data.compressed) {
          msg += `\n\n📦 Tamaño original: ${(data.original_size / 1024).toFixed(0)} KB`;
          msg += `\n✅ Comprimido a: ${(data.final_size / 1024).toFixed(0)} KB`;
          msg += `\n📊 Ahorro: ${data.savings}`;
        }
        alert(msg);
        loadCompras();
      } else {
        // Mostrar error completo con debug
        const debugStr = data.debug ? '\n\nDEBUG:\n' + JSON.stringify(data.debug, null, 2) : '';
        alert('❌ Error: ' + data.error + debugStr);
        console.error('Upload error:', data);
      }
    } catch (error) {
      alert('❌ Error al subir respaldo: ' + error.message);
    } finally {
      setUploadingRespaldo(null);
    }
  };

  const generarRendicion = () => {
    const comprasSeleccionadas = compras.filter(c => selectedCompras.includes(c.id));
    const totalGastado = comprasSeleccionadas.reduce((sum, c) => sum + parseFloat(c.monto_total), 0);
    const transferencia = parseFloat(montoTransferencia) || 0;
    const anterior = parseFloat(saldoAnterior) || 0;
    const totalDisponible = anterior + transferencia;
    const saldoFinal = totalDisponible - totalGastado;

    let mensaje = `*📋 RENDICIÓN DE GASTOS*\n`;
    mensaje += `━━━━━━━━━━━━━━━━━━━━\n\n`;
    
    if (anterior !== 0) {
      mensaje += `*💰 Saldo anterior:* ${anterior >= 0 ? '+' : ''}$${fmt(Math.abs(anterior))}\n`;
    }
    if (transferencia > 0) {
      mensaje += `*💳 Transferencia recibida:* $${fmt(transferencia)}\n`;
    }
    if (anterior !== 0 || transferencia > 0) {
      mensaje += `*💵 Total disponible:* $${fmt(totalDisponible)}\n\n`;
    }
    
    mensaje += `*🛒 Compras realizadas:*\n\n`;
    
    comprasSeleccionadas.forEach((compra, idx) => {
      mensaje += `*${idx + 1}. ${compra.proveedor}*\n`;
      mensaje += `   📅 ${new Date(compra.fecha_compra).toLocaleDateString('es-CL')}\n`;
      mensaje += `   💵 $${fmt(compra.monto_total)}\n`;
      if (compra.items && compra.items.length > 0) {
        compra.items.forEach(item => {
          const cantidad = parseFloat(item.cantidad);
          const cantidadFormateada = Number.isInteger(cantidad) ? cantidad : cantidad.toFixed(2);
          mensaje += `   • ${item.nombre_item} (${cantidadFormateada} ${item.unidad})\n`;
        });
      }
      if (compra.imagen_respaldo) {
        mensaje += `   📎 Respaldo: ${compra.imagen_respaldo}\n`;
      }
      mensaje += `\n`;
    });
    
    mensaje += `━━━━━━━━━━━━━━━━━━━━\n`;
    mensaje += `*💳 Total gastado:* $${fmt(totalGastado)}\n`;
    mensaje += `━━━━━━━━━━━━━━━━━━━━\n`;
    mensaje += `*${saldoFinal >= 0 ? '✅ Saldo a favor' : '⚠️ Saldo a devolver'}:* ${saldoFinal >= 0 ? '+' : ''}$${fmt(Math.abs(saldoFinal))}\n`;

    mensaje += `\n🔗 https://caja.laruta11.cl/compras/\n`;
    mensaje += `\n_Generado desde App Compras_`;

    return mensaje;
  };

  const copiarRendicion = () => {
    const mensaje = generarRendicion();
    navigator.clipboard.writeText(mensaje).then(() => {
      alert('✅ Mensaje copiado al portapapeles\n\nPuedes pegarlo en WhatsApp');
      setShowRendicionModal(false);
      setSelectedCompras([]);
      setMontoTransferencia('');
      setSaldoAnterior('');
    });
  };

  const getPaymentLabel = (method) => {
    const labels = {
      'cash': 'Efectivo',
      'transfer': 'Transferencia',
      'card': 'Débito',
      'credit': 'Crédito'
    };
    return labels[method] || method;
  };

  const getPaymentIcon = (method) => {
    const icons = {
      'cash': '💵',
      'transfer': '🏦',
      'card': '💳',
      'credit': '💳'
    };
    return icons[method] || '💰';
  };

  return (
    <div className="compras-container">
      {/* Header Fijo - Compacto con Dropdowns */}
      <div className="compras-header-fixed">
        <div className="header-compact">
          {/* KPIs Dropdown */}
          <div className="dropdown-container">
            <button onClick={() => setShowKpiMenu(!showKpiMenu)} className="dropdown-trigger">
              <span style={{fontSize: '12px', color: '#6b7280', marginRight: '4px'}}>Disponible</span>
              ${fmt(saldoDisponible)} <ChevronDown size={16} />
            </button>
            {showKpiMenu && (
              <div className="dropdown-menu" onClick={() => setShowKpiMenu(false)}>
                <div className="dropdown-item">
                  <span>ENE</span>
                  <strong>${fmt(resumenFinanciero?.ventas_mes_anterior || 0)}</strong>
                </div>
                <div className="dropdown-item">
                  <span>FEB {new Date().getDate()}</span>
                  <strong style={{color: '#10b981'}}>${fmt(resumenFinanciero?.ventas_mes_actual || 0)}</strong>
                </div>
                <div className="dropdown-item">
                  <span>SUELDOS</span>
                  <strong style={{color: '#dc2626'}}>-${fmt(resumenFinanciero?.sueldos || 0)}</strong>
                </div>
                <div className="dropdown-item" style={{borderTop: '2px solid #e5e7eb', paddingTop: '8px', marginTop: '8px'}}>
                  <span>DISPONIBLE</span>
                  <strong style={{color: saldoDisponible < 0 ? '#ef4444' : saldoDisponible < 200000 ? '#f59e0b' : '#10b981', fontSize: '16px'}}>${fmt(saldoDisponible)}</strong>
                </div>
                <button onClick={loadHistorialSaldo} className="dropdown-item-btn">
                  <History size={16} /> Ver Historial
                </button>
              </div>
            )}
          </div>

          {/* Tabs Dropdown */}
          <div className="dropdown-container">
            <button onClick={() => setShowTabMenu(!showTabMenu)} className="dropdown-trigger">
              {activeTab === 'registro' && 'Registrar'}
              {activeTab === 'proyeccion' && 'Proyección'}
              {activeTab === 'stock' && 'Stock'}
              {activeTab === 'historial' && 'Historial'}
              <ChevronDown size={16} />
            </button>
            {showTabMenu && (
              <div className="dropdown-menu" onClick={() => setShowTabMenu(false)}>
                <button onClick={() => setActiveTab('registro')} className="dropdown-item-btn">
                  <Plus size={16} /> Registrar
                </button>
                <button onClick={() => setActiveTab('proyeccion')} className="dropdown-item-btn">
                  <TrendingUp size={16} /> Proyección
                </button>
                <button onClick={() => setActiveTab('stock')} className="dropdown-item-btn">
                  <Package size={16} /> Stock
                </button>
                <button onClick={() => setActiveTab('historial')} className="dropdown-item-btn">
                  <FileText size={16} /> Historial
                </button>
              </div>
            )}
          </div>

          {/* Botón Buscar (solo en Historial) */}
          {activeTab === 'historial' && (
            <button 
              onClick={() => setShowComprasSearch(!showComprasSearch)} 
              className="dropdown-trigger" 
              style={{width: 'auto', padding: '12px', minWidth: '48px'}}
              title="Buscar"
            >
              <Search size={18} />
            </button>
          )}
        </div>
      </div>
      {showComprasSearch && (
        <div style={{
          marginBottom: '16px',
          padding: '12px',
          background: '#f9fafb',
          borderRadius: '8px',
          border: '2px solid #e5e7eb'
        }}>
          <input
            type="text"
            placeholder="Buscar por proveedor, producto, fecha..."
            value={comprasSearchTerm}
            onChange={(e) => setComprasSearchTerm(e.target.value)}
            style={{
              width: '100%',
              padding: '10px',
              border: '2px solid #e5e7eb',
              borderRadius: '8px',
              fontSize: '14px',
              boxSizing: 'border-box'
            }}
          />
        </div>
      )}

      {/* Contenido */}
      {activeTab === 'stock' ? (
        <div className="compra-form">
          <div style={{display: 'flex', gap: '8px', marginBottom: '16px'}}>
            <button
              onClick={() => setStockTab('ingredientes')}
              style={{
                flex: 1,
                padding: '12px',
                border: 'none',
                borderRadius: '8px',
                background: stockTab === 'ingredientes' ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : '#f1f5f9',
                color: stockTab === 'ingredientes' ? 'white' : '#64748b',
                fontWeight: '600',
                cursor: 'pointer',
                transition: 'all 0.2s'
              }}
            >
              Ingredientes
            </button>
            <button
              onClick={() => setStockTab('bebidas')}
              style={{
                flex: 1,
                padding: '12px',
                border: 'none',
                borderRadius: '8px',
                background: stockTab === 'bebidas' ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : '#f1f5f9',
                color: stockTab === 'bebidas' ? 'white' : '#64748b',
                fontWeight: '600',
                cursor: 'pointer',
                transition: 'all 0.2s'
              }}
            >
              Bebidas
            </button>
          </div>

          <div style={{display: 'flex', gap: '8px', marginBottom: '16px'}}>
            <input
              type="text"
              placeholder="Buscar..."
              value={stockFilter}
              onChange={(e) => setStockFilter(e.target.value)}
              style={{
                flex: 1,
                padding: '12px',
                border: '2px solid #e2e8f0',
                borderRadius: '8px',
                fontSize: '14px'
              }}
            />
            <select
              value={stockSort}
              onChange={(e) => setStockSort(e.target.value)}
              style={{
                padding: '12px',
                border: '2px solid #e2e8f0',
                borderRadius: '8px',
                fontSize: '14px',
                background: 'white'
              }}
            >
              <option value="criticidad">🚦 Criticidad</option>
              <option value="nombre">🔤 Nombre</option>
              <option value="stock">📊 Stock</option>
            </select>
          </div>

          <div style={{background: '#f8fafc', borderRadius: '12px', padding: '12px'}}>
            <div style={{display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '4px'}}>
              {ingredientes
                .filter(ing => {
                  if (stockTab === 'bebidas') {
                    // Bebidas: products con category_id=5 y subcategory_id=11
                    return ing.type === 'product' && ing.category_id === 5 && ing.subcategory_id === 11;
                  }
                  // Ingredientes: todos los ingredientes (type='ingredient')
                  return ing.type === 'ingredient';
                })
                .filter(ing => ing.name.toLowerCase().includes(stockFilter.toLowerCase()))
                .sort((a, b) => {
                  if (stockSort === 'criticidad') {
                    const ratioA = a.current_stock / (a.min_stock_level || 1);
                    const ratioB = b.current_stock / (b.min_stock_level || 1);
                    return ratioA - ratioB;
                  }
                  if (stockSort === 'nombre') return a.name.localeCompare(b.name);
                  if (stockSort === 'stock') return a.current_stock - b.current_stock;
                  return 0;
                })
                .map(ing => {
                  const currentStock = parseFloat(ing.current_stock);
                  const minStock = parseFloat(ing.min_stock_level) || 1;
                  const percentage = (currentStock / minStock) * 100;
                  
                  // Sistema semáforo: Verde >= 100%, Amarillo 50-99%, Rojo < 50%
                  const isOk = percentage >= 100;
                  const isLow = percentage >= 50 && percentage < 100;
                  const isCritical = percentage < 50;
                  
                  // Bebidas: enteros, Ingredientes: decimales si necesario
                  const isBebida = stockTab === 'bebidas';
                  const displayStock = isBebida ? Math.round(currentStock) : (Number.isInteger(currentStock) ? currentStock : currentStock.toFixed(1));
                  const displayMin = isBebida ? Math.round(minStock) : (Number.isInteger(minStock) ? minStock : minStock.toFixed(1));
                  
                  const bgColor = isCritical ? '#fee2e2' : isLow ? '#fef3c7' : 'white';
                  const borderColor = isCritical ? '#ef4444' : isLow ? '#f59e0b' : '#10b981';
                  const textColor = isCritical ? '#dc2626' : isLow ? '#d97706' : '#6b7280';
                  const label = isCritical ? '¡Crítico!' : isLow ? 'Bajo' : '';
                  
                  return (
                    <div key={ing.id} style={{
                      padding: '6px 8px',
                      background: bgColor,
                      borderRadius: '4px',
                      borderLeft: `3px solid ${borderColor}`,
                      fontSize: '12px',
                      lineHeight: '1.3'
                    }}>
                      <div style={{fontWeight: '600', fontSize: '12px', marginBottom: '2px'}}>{ing.name}</div>
                      <div style={{color: textColor, fontSize: '11px', fontWeight: (isCritical || isLow) ? '600' : '400'}}>
                        {displayStock} / {displayMin} ({Math.round(percentage)}%) {label}
                      </div>
                    </div>
                  );
                })}
            </div>
          </div>
        </div>
      ) : activeTab === 'proyeccion' ? (
        <div className="compra-form">
          <h3 style={{marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px'}}>
            <TrendingUp size={20} /> Presupuesto de Compra
          </h3>
          
          <div className="item-form">
            <div className="item-form-row">
              <div className="search-container" style={{flex: '3'}}>
                <input
                  type="text"
                  placeholder="Buscar ingrediente..."
                  value={searchTerm}
                  onChange={handleSearchChange}
                />
                {filteredIngredientes.length > 0 && (
                  <div className="search-results">
                    {filteredIngredientes.map(ing => (
                      <div key={ing.id} className="search-result-item" onMouseDown={() => handleIngredienteSelect(ing, true)}>
                        <strong>{ing.name}</strong> <span style={{color: '#999', fontSize: '12px'}}>({ing.unit} - {ing.category})</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              <input
                type="number"
                placeholder="Cantidad"
                value={proyeccionItem.cantidad}
                onChange={(e) => setProyeccionItem({...proyeccionItem, cantidad: e.target.value})}
                step="0.01"
                style={{flex: '1'}}
              />
            </div>

            <div className="item-form-row">
              <select
                value={proyeccionItem.unidad}
                onChange={(e) => setProyeccionItem({...proyeccionItem, unidad: e.target.value})}
                style={{flex: '1'}}
              >
                <option value="kg">kg</option>
                <option value="unidad">unidad</option>
                <option value="litro">litro</option>
                <option value="gramo">gramo</option>
              </select>

              <input
                type="number"
                placeholder="Precio Unit."
                value={proyeccionItem.precio_unitario}
                onChange={(e) => setProyeccionItem({...proyeccionItem, precio_unitario: e.target.value})}
                step="0.01"
                style={{background: proyeccionItem.precio_unitario ? '#f0fdf4' : 'white', flex: '1'}}
              />
            </div>

            {proyeccionItem.cantidad && proyeccionItem.precio_unitario && (
              <div style={{padding: '10px 12px', background: '#f0fdf4', borderRadius: '6px', border: '2px solid #10b981', fontSize: '14px', color: '#059669', fontWeight: '700', textAlign: 'center'}}>
                💰 Total: ${fmt(parseFloat(proyeccionItem.cantidad) * parseFloat(proyeccionItem.precio_unitario))}
              </div>
            )}

            <button type="button" onClick={() => {
              const nombre = proyeccionItem.nombre_item || searchTerm.trim();
              if (!nombre || !proyeccionItem.cantidad || !proyeccionItem.precio_unitario) {
                alert('Complete nombre, cantidad y precio');
                return;
              }

              const cantidad = parseFloat(proyeccionItem.cantidad);
              const precio_unitario = parseFloat(proyeccionItem.precio_unitario);
              const subtotal = cantidad * precio_unitario;

              const newItem = { 
                ...proyeccionItem,
                nombre_item: nombre,
                precio_unitario: precio_unitario.toFixed(2),
                subtotal 
              };
              
              setProyeccionItems([...proyeccionItems, newItem]);
              setProyeccionItem({
                ingrediente_id: '',
                product_id: '',
                item_type: '',
                nombre_item: '',
                cantidad: '',
                unidad: 'kg',
                precio_total: '',
                precio_unitario: ''
              });
              setSearchTerm('');
            }} className="btn-add">
              <Plus size={18} /> Agregar
            </button>
          </div>

          {proyeccionItems.length > 0 && (
            <>
              <div style={{marginTop: '12px', padding: '12px', background: saldoDisponible - proyeccionItems.reduce((sum, item) => sum + item.subtotal, 0) >= 0 ? '#f0fdf4' : '#fef2f2', borderRadius: '8px', border: `2px solid ${saldoDisponible - proyeccionItems.reduce((sum, item) => sum + item.subtotal, 0) >= 0 ? '#10b981' : '#ef4444'}`}}>
                <div style={{display: 'flex', justifyContent: 'space-between', marginBottom: '6px'}}>
                  <span style={{fontSize: '13px', fontWeight: '600'}}>💰 Saldo Disponible:</span>
                  <span style={{fontSize: '14px', fontWeight: '700', color: '#6b7280'}}>${fmt(saldoDisponible)}</span>
                </div>
                <div style={{display: 'flex', justifyContent: 'space-between', marginBottom: '6px'}}>
                  <span style={{fontSize: '13px', fontWeight: '600'}}>🛒 Costo Proyectado:</span>
                  <span style={{fontSize: '14px', fontWeight: '700', color: '#ef4444'}}>-${fmt(proyeccionItems.reduce((sum, item) => sum + item.subtotal, 0))}</span>
                </div>
                <div style={{borderTop: '2px solid #e5e7eb', paddingTop: '6px', marginTop: '6px', display: 'flex', justifyContent: 'space-between'}}>
                  <span style={{fontSize: '14px', fontWeight: '700'}}>💵 Saldo Restante:</span>
                  <span style={{fontSize: '16px', fontWeight: '800', color: saldoDisponible - proyeccionItems.reduce((sum, item) => sum + item.subtotal, 0) >= 0 ? '#10b981' : '#ef4444'}}>
                    ${fmt(saldoDisponible - proyeccionItems.reduce((sum, item) => sum + item.subtotal, 0))}
                  </span>
                </div>
              </div>

              <div className="items-list">
                {proyeccionItems.map((item, index) => (
                  <div key={index} className="item-row">
                    <span>{item.nombre_item}</span>
                    <span>{item.cantidad} {item.unidad}</span>
                    <span>${fmt(item.precio_unitario)}</span>
                    <span className="subtotal">${fmt(item.subtotal)}</span>
                    <button type="button" onClick={() => setProyeccionItems(proyeccionItems.filter((_, i) => i !== index))} className="btn-remove">
                      <Trash2 size={16} />
                    </button>
                  </div>
                ))}
                <div className="total-row">
                  <span>TOTAL PROYECTADO</span>
                  <span className="total-amount">
                    ${fmt(proyeccionItems.reduce((sum, item) => sum + item.subtotal, 0))}
                  </span>
                </div>
              </div>

              <button type="button" onClick={() => {
                if (window.confirm('¿Limpiar proyección?')) {
                  setProyeccionItems([]);
                }
              }} style={{
                width: '100%',
                padding: '12px',
                marginTop: '16px',
                background: '#6b7280',
                color: 'white',
                border: 'none',
                borderRadius: '8px',
                cursor: 'pointer',
                fontWeight: '600',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '8px'
              }}>
                <X size={18} /> Limpiar Proyección
              </button>
            </>
          )}
        </div>
      ) : activeTab === 'registro' ? (
        <form onSubmit={handleSubmit} className="compra-form">
          <h3 style={{marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px'}}>
            <FileText size={20} /> Datos de Compra
          </h3>
          
          {/* Fila Compacta: Proveedor | Fecha | Método Pago */}
          <div className="form-row-compact">
            <div className="form-group-compact" style={{flex: '2', position: 'relative'}}>
              <input
                type="text"
                value={formData.proveedor}
                onChange={(e) => {
                  const value = e.target.value;
                  setFormData({...formData, proveedor: value});
                  if (value.trim()) {
                    const filtered = proveedores
                      .map(p => ({ name: p, score: fuzzyMatch(p, value) }))
                      .filter(p => p.score > 0)
                      .sort((a, b) => b.score - a.score)
                      .slice(0, 5);
                    setFilteredProveedores(filtered);
                    setShowProveedorDropdown(true);
                  } else {
                    setFilteredProveedores([]);
                    setShowProveedorDropdown(false);
                  }
                }}
                onBlur={() => setTimeout(() => setShowProveedorDropdown(false), 200)}
                placeholder="Proveedor"
                required
                style={{height: '48px'}}
              />
              {showProveedorDropdown && filteredProveedores.length > 0 && (
                <div style={{
                  position: 'absolute',
                  top: '100%',
                  left: 0,
                  right: 0,
                  background: 'white',
                  border: '2px solid #e5e7eb',
                  borderTop: 'none',
                  borderRadius: '0 0 8px 8px',
                  maxHeight: '150px',
                  overflowY: 'auto',
                  zIndex: 10,
                  boxShadow: '0 4px 6px rgba(0,0,0,0.1)'
                }}>
                  {filteredProveedores.map((prov, idx) => (
                    <div
                      key={idx}
                      onMouseDown={() => {
                        setFormData({...formData, proveedor: prov.name});
                        setShowProveedorDropdown(false);
                      }}
                      style={{
                        padding: '10px',
                        cursor: 'pointer',
                        borderBottom: '1px solid #f3f4f6'
                      }}
                      onMouseEnter={(e) => e.target.style.background = '#f9fafb'}
                      onMouseLeave={(e) => e.target.style.background = 'white'}
                    >
                      <strong>{prov.name}</strong>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          <div className="form-row-compact">
            <div className="form-group-compact" style={{flex: '1'}}>
              <input
                type="date"
                value={formData.fecha_compra}
                onChange={(e) => setFormData({...formData, fecha_compra: e.target.value})}
                required
                style={{height: '48px'}}
              />
            </div>

            <div className="form-group-compact" style={{flex: '1'}}>
              <select
                value={formData.metodo_pago}
                onChange={(e) => setFormData({...formData, metodo_pago: e.target.value})}
                required
                style={{height: '48px'}}
              >
                <option value="cash">💵 Efectivo</option>
                <option value="transfer">🏦 Transferencia</option>
                <option value="card">💳 Débito</option>
                <option value="credit">💳 Crédito</option>
              </select>
            </div>
          </div>

          <div className="form-group">
            <label>Notas</label>
            <textarea
              value={formData.notas}
              onChange={(e) => setFormData({...formData, notas: e.target.value})}
              placeholder="Observaciones adicionales"
              rows="2"
            />
          </div>

          <div className="form-group">
            <label><Paperclip size={16} /> Respaldo (Boleta/Factura)</label>
            {!respaldoPreview ? (
              <label style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '8px',
                padding: '12px',
                background: '#f0fdf4',
                border: '2px dashed #10b981',
                borderRadius: '8px',
                cursor: 'pointer',
                fontWeight: '600',
                color: '#059669',
                transition: 'all 0.2s'
              }}
              onMouseEnter={(e) => e.currentTarget.style.background = '#dcfce7'}
              onMouseLeave={(e) => e.currentTarget.style.background = '#f0fdf4'}
              >
                <Image size={18} /> Adjuntar Foto de Boleta/Factura
                <input
                  type="file"
                  accept="image/*"
                  style={{display: 'none'}}
                  onChange={(e) => {
                    const file = e.target.files[0];
                    if (file) {
                      // Validar tipo
                      if (!file.type.startsWith('image/')) {
                        alert('❌ Por favor selecciona una imagen');
                        return;
                      }
                      // Validar tamaño (máx 10MB)
                      if (file.size > 10 * 1024 * 1024) {
                        alert('❌ La imagen es demasiado grande (máx 10MB)');
                        return;
                      }
                      setRespaldoFile(file);
                      setRespaldoPreview(URL.createObjectURL(file));
                    }
                  }}
                />
              </label>
            ) : (
              <div style={{marginTop: '8px', position: 'relative', display: 'inline-block'}}>
                <img src={respaldoPreview} style={{maxWidth: '200px', borderRadius: '8px', border: '2px solid #10b981'}} />
                <button
                  type="button"
                  onClick={() => {
                    setRespaldoFile(null);
                    setRespaldoPreview(null);
                  }}
                  style={{position: 'absolute', top: '8px', right: '8px', padding: '6px 10px', background: '#ef4444', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer', fontSize: '12px', fontWeight: '600', boxShadow: '0 2px 4px rgba(0,0,0,0.2)'}}
                >
                  <X size={14} /> Quitar
                </button>
              </div>
            )}
          </div>

          {/* Agregar Items */}
          <div className="items-section">
            <h3><ShoppingCart size={20} /> Items de Compra</h3>
            
            <div className="item-form">
              {/* Fila 1: Buscar ingrediente - ancho completo */}
              <div className="search-container" style={{width: '100%'}}>
                <input
                  type="text"
                  placeholder="Buscar ingrediente..."
                  value={searchTerm}
                  onChange={handleSearchChange}
                  style={{width: '100%'}}
                />
                {filteredIngredientes.length > 0 && (
                  <div className="search-results">
                    {filteredIngredientes.map(ing => (
                      <div key={ing.id} className="search-result-item" onMouseDown={() => handleIngredienteSelect(ing)}>
                        <strong>{ing.name}</strong> <span style={{color: '#999', fontSize: '12px'}}>({ing.unit} - {ing.category})</span>
                      </div>
                    ))}
                    <div className="search-result-item create-new" onMouseDown={() => { setNewIngredient({...newIngredient, name: searchTerm}); setShowNewIngredientModal(true); }}>
                      <PlusCircle size={16} /> Crear "{searchTerm}"
                    </div>
                  </div>
                )}
              </div>

              {/* Fila 2: Cantidad | Unidad | Precio | IVA - misma altura */}
              <div className="item-form-row" style={{alignItems: 'stretch'}}>
                <input
                  type="number"
                  placeholder="Cantidad"
                  value={currentItem.cantidad}
                  onChange={(e) => setCurrentItem({...currentItem, cantidad: e.target.value})}
                  step="0.01"
                  style={{flex: '1', height: '48px'}}
                />

                <select
                  value={currentItem.unidad}
                  onChange={(e) => setCurrentItem({...currentItem, unidad: e.target.value})}
                  style={{flex: '0.8', height: '48px'}}
                >
                  <option value="kg">kg</option>
                  <option value="gramo">gr</option>
                  <option value="litro">lt</option>
                  <option value="unidad">un</option>
                </select>

                <input
                  type="number"
                  placeholder={currentItem.con_iva ? "Precio c/IVA" : "Precio s/IVA"}
                  value={currentItem.precio_unitario}
                  onChange={(e) => setCurrentItem({...currentItem, precio_unitario: e.target.value})}
                  step="0.01"
                  style={{background: currentItem.precio_unitario ? '#f0fdf4' : 'white', flex: '1.5', height: '48px'}}
                />

                <label style={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  gap: '6px',
                  padding: '0 16px',
                  cursor: 'pointer',
                  userSelect: 'none',
                  whiteSpace: 'nowrap',
                  fontSize: '13px',
                  fontWeight: '600',
                  color: currentItem.con_iva ? '#10b981' : '#6b7280',
                  border: '2px solid',
                  borderColor: currentItem.con_iva ? '#10b981' : '#e2e8f0',
                  borderRadius: '10px',
                  background: currentItem.con_iva ? '#f0fdf4' : '#f8fafc',
                  height: '48px',
                  minWidth: '100px'
                }}>
                  <input
                    type="checkbox"
                    checked={currentItem.con_iva}
                    onChange={(e) => setCurrentItem({...currentItem, con_iva: e.target.checked})}
                    style={{width: '18px', height: '18px', cursor: 'pointer'}}
                  />
                  c/IVA
                </label>
              </div>

              {currentItem.cantidad && currentItem.precio_unitario && (
                <div style={{padding: '10px 12px', background: '#f0fdf4', borderRadius: '6px', border: '2px solid #10b981', fontSize: '13px', color: '#059669', fontWeight: '700'}}>
                  <div style={{display: 'flex', justifyContent: 'space-between', marginBottom: '4px'}}>
                    <span>Subtotal {currentItem.con_iva ? '(c/IVA)' : '(s/IVA)'}:</span>
                    <span>${fmt(parseFloat(currentItem.cantidad) * parseFloat(currentItem.precio_unitario))}</span>
                  </div>
                  {!currentItem.con_iva && (
                    <div style={{display: 'flex', justifyContent: 'space-between', paddingTop: '4px', borderTop: '1px solid #10b981'}}>
                      <span>Total c/IVA:</span>
                      <span style={{fontSize: '15px'}}>${fmt(parseFloat(currentItem.cantidad) * parseFloat(currentItem.precio_unitario) * 1.19)}</span>
                    </div>
                  )}
                </div>
              )}

              <button type="button" onClick={handleAddItem} className="btn-add" disabled={isCreatingItem}>
                {isCreatingItem ? (
                  <>
                    <span className="spinner"></span> Creando...
                  </>
                ) : (
                  <>
                    <Plus size={18} /> Agregar
                  </>
                )}
              </button>
            </div>

            {/* Preview de Stock */}
            {(currentItem.ingrediente_id || currentItem.product_id) && currentItem.cantidad && (
              <div style={{marginTop: '8px', padding: '8px', background: '#f0fdf4', borderRadius: '6px', fontSize: '13px', color: '#059669'}}>
                📦 Stock: {currentItem.stock_actual || 0} → {(parseFloat(currentItem.stock_actual || 0) + parseFloat(currentItem.cantidad)).toFixed(2)} {currentItem.unidad}
              </div>
            )}

            {/* Presupuesto en tiempo real */}
            {formData.items.length > 0 && (
              <div style={{marginTop: '12px', padding: '12px', background: saldoDisponible - formData.items.reduce((sum, item) => sum + item.subtotal, 0) >= 0 ? '#f0fdf4' : '#fef2f2', borderRadius: '8px', border: `2px solid ${saldoDisponible - formData.items.reduce((sum, item) => sum + item.subtotal, 0) >= 0 ? '#10b981' : '#ef4444'}`}}>
                <div style={{display: 'flex', justifyContent: 'space-between', marginBottom: '6px'}}>
                  <span style={{fontSize: '13px', fontWeight: '600'}}>💰 Saldo Disponible:</span>
                  <span style={{fontSize: '14px', fontWeight: '700', color: '#6b7280'}}>${fmt(saldoDisponible)}</span>
                </div>
                <div style={{display: 'flex', justifyContent: 'space-between', marginBottom: '6px'}}>
                  <span style={{fontSize: '13px', fontWeight: '600'}}>🛒 Costo Compra:</span>
                  <span style={{fontSize: '14px', fontWeight: '700', color: '#ef4444'}}>-${fmt(formData.items.reduce((sum, item) => sum + item.subtotal, 0))}</span>
                </div>
                <div style={{borderTop: '2px solid #e5e7eb', paddingTop: '6px', marginTop: '6px', display: 'flex', justifyContent: 'space-between'}}>
                  <span style={{fontSize: '14px', fontWeight: '700'}}>💵 Saldo Restante:</span>
                  <span style={{fontSize: '16px', fontWeight: '800', color: saldoDisponible - formData.items.reduce((sum, item) => sum + item.subtotal, 0) >= 0 ? '#10b981' : '#ef4444'}}>
                    ${fmt(saldoDisponible - formData.items.reduce((sum, item) => sum + item.subtotal, 0))}
                  </span>
                </div>
              </div>
            )}

            {/* Lista de Items */}
            {formData.items.length > 0 && (
              <div className="items-list">
                {formData.items.map((item, index) => (
                  <div key={index} className="item-row">
                    <span>{item.nombre_item}</span>
                    <span>{item.cantidad} {item.unidad}</span>
                    <span>${fmt(item.precio_unitario)}</span>
                    <span className="subtotal">${fmt(item.subtotal)}</span>
                    <button type="button" onClick={() => handleRemoveItem(index)} className="btn-remove">
                      <Trash2 size={16} />
                    </button>
                  </div>
                ))}
                <div className="total-row">
                  <span>TOTAL</span>
                  <span className="total-amount">
                    ${fmt(formData.items.reduce((sum, item) => sum + item.subtotal, 0))}
                  </span>
                </div>
              </div>
            )}
          </div>

          <div className="btn-submit">
            <button type="submit" disabled={loading}>
              {loading ? 'Registrando...' : <><Check size={18} /> Registrar Compra</>}
            </button>
          </div>
        </form>
      ) : (
        <div className="historial-container">
          {selectedCompras.length > 0 && (
            <div style={{marginBottom: '16px', padding: '16px', background: '#f0fdf4', borderRadius: '8px', border: '2px solid #10b981', display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
              <span style={{fontWeight: '600', color: '#059669'}}>
                {selectedCompras.length} compra{selectedCompras.length > 1 ? 's' : ''} seleccionada{selectedCompras.length > 1 ? 's' : ''}
              </span>
              <button onClick={() => setShowRendicionModal(true)} style={{
                padding: '10px 20px',
                background: '#10b981',
                color: 'white',
                border: 'none',
                borderRadius: '8px',
                cursor: 'pointer',
                fontWeight: '600',
                display: 'flex',
                alignItems: 'center',
                gap: '8px'
              }}>
                <FileText size={18} /> Rendir Gastos
              </button>
            </div>
          )}
          {compras.length === 0 ? (
            <div className="empty-state">No hay compras registradas</div>
          ) : (
            compras
              .filter(compra => {
                if (!comprasSearchTerm) return true;
                const term = comprasSearchTerm.toLowerCase();
                return (
                  compra.proveedor.toLowerCase().includes(term) ||
                  (compra.items && compra.items.some(item => item.nombre_item.toLowerCase().includes(term))) ||
                  new Date(compra.fecha_compra).toLocaleDateString('es-CL').includes(term)
                );
              })
              .map(compra => (
              <div key={compra.id} className="compra-card" style={{position: 'relative'}}>
                <div style={{position: 'absolute', top: '20px', right: '50px', display: 'flex', gap: '6px'}}>
                  {compra.imagen_respaldo ? (
                    <button
                      onClick={() => window.open(compra.imagen_respaldo, '_blank')}
                      style={{padding: '6px 10px', background: '#3b82f6', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer', fontSize: '11px', fontWeight: '600'}}
                      title="Ver respaldo"
                    >
                      📎 Ver
                    </button>
                  ) : (
                    <label style={{padding: '6px 10px', background: '#10b981', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer', fontSize: '11px', fontWeight: '600'}}>
                      {uploadingRespaldo === compra.id ? '⏳' : '📎 Subir'}
                      <input
                        type="file"
                        accept="image/*"
                        style={{display: 'none'}}
                        onChange={(e) => handleUploadRespaldo(compra.id, e)}
                        disabled={uploadingRespaldo === compra.id}
                      />
                    </label>
                  )}
                  <button
                    onClick={() => handleDeleteCompra(compra.id)}
                    style={{padding: '6px 10px', background: '#ef4444', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer', fontSize: '12px', fontWeight: '600'}}
                    title="Eliminar compra"
                  >
                    <Trash2 size={14} />
                  </button>
                </div>
                <input
                  type="checkbox"
                  checked={selectedCompras.includes(compra.id)}
                  onChange={() => handleSelectCompra(compra.id)}
                  style={{position: 'absolute', top: '20px', right: '20px', width: '20px', height: '20px', cursor: 'pointer'}}
                />
                <div className="compra-header">
                  <div>
                    <h4>{compra.proveedor}</h4>
                    <span className="compra-fecha">{new Date(compra.fecha_compra).toLocaleDateString('es-CL')}</span>
                  </div>
                </div>
                <div className="compra-details">
                  <span className="badge badge-pagado">{getPaymentLabel(compra.metodo_pago || 'cash')}</span>
                  <span className="badge">{compra.usuario || 'Admin'}</span>
                  {compra.items && compra.items.length > 0 && (
                    <span className="badge" style={{background: '#e0e7ff', color: '#4338ca'}}>
                      {compra.items.length} {compra.items.length === 1 ? 'item' : 'items'}
                    </span>
                  )}
                </div>
                {compra.items && compra.items.length > 0 ? (
                  <div className="compra-items">
                    <div className="items-header">
                      <span>Producto</span>
                      <span>Cantidad</span>
                      <span>P. Unit.</span>
                      <span>Subtotal</span>
                      <span>Stock</span>
                    </div>
                    {compra.items.map((item, idx) => {
                      const hasSnapshot = item.stock_antes !== null && item.stock_despues !== null;
                      return (
                        <div key={idx} className="compra-item">
                          <span className="item-nombre">{item.nombre_item}</span>
                          <span className="item-cantidad">{item.cantidad} {item.unidad}</span>
                          <span className="item-precio">${fmt(parseFloat(item.precio_unitario))}</span>
                          <span className="item-subtotal">${fmt(parseFloat(item.subtotal))}</span>
                          <span className="item-stock">
                            {hasSnapshot ? (
                              <span style={{fontSize: '11px'}}>
                                {parseFloat(item.stock_antes)} → <strong style={{color: '#10b981'}}>{parseFloat(item.stock_despues)}</strong>
                              </span>
                            ) : (
                              <span style={{fontSize: '10px', color: '#9ca3af'}}>-</span>
                            )}
                          </span>
                        </div>
                      );
                    })}
                    <div className="compra-item" style={{background: '#f0fdf4', borderTop: '2px solid #10b981', fontWeight: '700'}}>
                      <span style={{color: '#059669'}}>TOTAL</span>
                      <span></span>
                      <span></span>
                      <span style={{color: '#059669', fontSize: '16px', textAlign: 'right'}}>${fmt(compra.monto_total)}</span>
                      <span></span>
                    </div>
                  </div>
                ) : (
                  <div className="compra-sin-detalle">
                    ℹ️ Compra sin detalle de items (registro antiguo)
                  </div>
                )}
                {compra.notas && <div className="compra-notas">📝 {compra.notas}</div>}
              </div>
            ))
          )}
        </div>
      )}

      {/* Modal Historial Saldo */}
      {showSaldoModal && (
        <div className="modal-overlay" onClick={() => setShowSaldoModal(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{maxWidth: '700px'}}>
            <h3 style={{marginBottom: '16px'}}>📊 Historial de Saldo para Compras</h3>
            <input
              type="text"
              placeholder="Buscar en historial..."
              value={historialSearchTerm}
              onChange={(e) => setHistorialSearchTerm(e.target.value)}
              style={{
                width: '100%',
                padding: '10px',
                marginBottom: '16px',
                border: '2px solid #e5e7eb',
                borderRadius: '8px',
                fontSize: '14px',
                boxSizing: 'border-box'
              }}
            />
            <div style={{marginTop: '16px'}}>
              {historialSaldo
                .filter(mov => mov.concepto.toLowerCase().includes(historialSearchTerm.toLowerCase()))
                .map((mov, idx) => (
                <div key={idx} style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  padding: '12px',
                  background: mov.tipo === 'ingreso' ? '#d1fae5' : '#fee2e2',
                  borderRadius: '8px',
                  marginBottom: '8px',
                  borderLeft: `4px solid ${mov.tipo === 'ingreso' ? '#10b981' : '#ef4444'}`
                }}>
                  <div>
                    <div style={{fontWeight: '600', fontSize: '14px'}}>
                      {mov.tipo === 'ingreso' ? '💰' : '💸'} {mov.concepto}
                    </div>
                    <div style={{fontSize: '12px', color: '#6b7280', marginTop: '2px'}}>
                      {new Date(mov.fecha).toLocaleDateString('es-CL')}
                      {mov.dias_transcurridos && ` • ${mov.dias_transcurridos} días`}
                    </div>
                    {mov.ultima_venta && (
                      <div style={{fontSize: '10px', color: '#10b981', marginTop: '2px', fontWeight: '600'}}>
                        ⏱️ Último ingreso: {new Date(mov.ultima_venta).toLocaleString('es-CL', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'})}
                      </div>
                    )}
                  </div>
                  <div style={{textAlign: 'right'}}>
                    <div style={{fontWeight: '700', fontSize: '16px', color: mov.tipo === 'ingreso' ? '#10b981' : '#ef4444'}}>
                      {mov.tipo === 'ingreso' ? '+' : '-'}${fmt(mov.monto)}
                    </div>
                    <div style={{fontSize: '12px', color: '#6b7280', marginTop: '2px'}}>
                      Saldo: ${fmt(mov.saldo_resultante)}
                    </div>
                  </div>
                </div>
              ))}
            </div>
            <button onClick={() => setShowSaldoModal(false)} style={{
              width: '100%',
              padding: '12px',
              marginTop: '16px',
              background: '#6b7280',
              color: 'white',
              border: 'none',
              borderRadius: '8px',
              cursor: 'pointer',
              fontWeight: '600'
            }}>Cerrar</button>
          </div>
        </div>
      )}

      {/* Modal Rendición */}
      {showRendicionModal && (
        <div className="modal-overlay" onClick={() => setShowRendicionModal(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{maxWidth: '600px'}}>
            <h3>📋 Rendir Gastos</h3>
            <div className="form-group" style={{marginTop: '16px'}}>
              <label>💰 Saldo anterior (opcional)</label>
              <input
                type="number"
                value={saldoAnterior}
                onChange={(e) => setSaldoAnterior(e.target.value)}
                placeholder="Ej: -10000 (si debes) o 5000 (si tienes)"
                step="1000"
              />
              {saldoAnterior && (
                <div style={{marginTop: '8px', padding: '10px', background: '#f0fdf4', borderRadius: '6px', fontSize: '14px', fontWeight: '700'}}>
                  <span style={{color: '#6b7280'}}>Saldo: </span>
                  <span style={{color: saldoAnterior < 0 ? '#ef4444' : '#10b981'}}>$ <strong>{fmt(Math.abs(parseInt(saldoAnterior)))}</strong></span>
                </div>
              )}
              <p style={{fontSize: '12px', color: '#6b7280', marginTop: '4px'}}>Ej: -10000 (empresa te debe), +10000 (tu debes a empresa)</p>
            </div>
            <div className="form-group">
              <label>💳 Monto de transferencia recibida (opcional)</label>
              <input
                type="number"
                value={montoTransferencia}
                onChange={(e) => setMontoTransferencia(e.target.value)}
                placeholder="Ej: 100000"
                step="1000"
              />
              {montoTransferencia && (
                <div style={{marginTop: '8px', padding: '10px', background: '#dbeafe', borderRadius: '6px', fontSize: '14px', fontWeight: '700'}}>
                  <span style={{color: '#6b7280'}}>Transferencia: </span>
                  <span style={{color: '#10b981'}}>$ <strong>{fmt(parseInt(montoTransferencia))}</strong></span>
                </div>
              )}
              <p style={{fontSize: '12px', color: '#6b7280', marginTop: '4px'}}>Deja en blanco si no recibiste transferencia</p>
            </div>
            <div style={{marginTop: '16px', padding: '12px', background: '#f9fafb', borderRadius: '8px', maxHeight: '300px', overflowY: 'auto'}}>
              <pre style={{whiteSpace: 'pre-wrap', fontSize: '13px', margin: 0, fontFamily: 'monospace'}}>{generarRendicion()}</pre>
            </div>
            <div style={{display: 'flex', gap: '8px', marginTop: '16px'}}>
              <button onClick={copiarRendicion} style={{
                flex: 1,
                padding: '12px',
                background: '#10b981',
                color: 'white',
                border: 'none',
                borderRadius: '8px',
                cursor: 'pointer',
                fontWeight: '600'
              }}>📋 Copiar para WhatsApp</button>
              <button onClick={() => setShowRendicionModal(false)} style={{
                flex: 1,
                padding: '12px',
                background: '#6b7280',
                color: 'white',
                border: 'none',
                borderRadius: '8px',
                cursor: 'pointer',
                fontWeight: '600'
              }}>Cancelar</button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Crear Ingrediente */}
      {showNewIngredientModal && (
        <div className="modal-overlay" onClick={() => setShowNewIngredientModal(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <h3>Crear Nuevo Ingrediente</h3>
            <div className="form-group">
              <label>Nombre</label>
              <input value={newIngredient.name} onChange={(e) => setNewIngredient({...newIngredient, name: e.target.value})} />
            </div>
            <div className="form-group">
              <label>Categoría</label>
              <select value={newIngredient.category} onChange={(e) => setNewIngredient({...newIngredient, category: e.target.value})}>
                <option value="Ingredientes">Ingredientes</option>
                <option value="Insumos">Insumos</option>
                <option value="Panes">Panes</option>
                <option value="Carnes">Carnes</option>
                <option value="Aves">Aves</option>
                <option value="Pescados">Pescados</option>
                <option value="Embutidos">Embutidos</option>
                <option value="Lácteos">Lácteos</option>
                <option value="Verduras">Verduras</option>
                <option value="Salsas">Salsas</option>
              </select>
            </div>
            <div className="form-group">
              <label>Unidad</label>
              <select value={newIngredient.unit} onChange={(e) => setNewIngredient({...newIngredient, unit: e.target.value})}>
                <option value="kg">kg</option>
                <option value="unidad">unidad</option>
                <option value="litro">litro</option>
                <option value="gramo">gramo</option>
              </select>
            </div>
            <div className="form-group">
              <label>Costo por Unidad</label>
              <input type="number" value={newIngredient.cost_per_unit} onChange={(e) => setNewIngredient({...newIngredient, cost_per_unit: e.target.value})} />
            </div>
            <div className="form-group">
              <label>Proveedor</label>
              <input value={newIngredient.supplier} onChange={(e) => setNewIngredient({...newIngredient, supplier: e.target.value})} />
            </div>
            <div style={{display: 'flex', gap: '8px', marginTop: '16px'}}>
              <button onClick={handleCreateIngredient} className="btn-add" style={{flex: 1}}>Crear</button>
              <button onClick={() => setShowNewIngredientModal(false)} className="btn-remove" style={{flex: 1}}>Cancelar</button>
            </div>
          </div>
        </div>
      )}

      <style jsx>{`
        .compras-container {
          max-width: 1200px;
          margin: 0 auto;
          padding: 20px;
          padding-top: 80px;
          padding-bottom: 100px;
          background: linear-gradient(to bottom, #f8fafc 0%, #ffffff 100%);
          min-height: 100vh;
        }
        .compras-header-fixed {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          background: white;
          z-index: 100;
          box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .header-compact {
          display: flex;
          gap: 12px;
          padding: 12px 20px;
          background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }
        .dropdown-container {
          position: relative;
          flex: 1;
        }
        .dropdown-trigger {
          width: 100%;
          padding: 12px 16px;
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          background: white;
          cursor: pointer;
          font-weight: 600;
          font-size: 14px;
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 8px;
          transition: all 0.2s;
        }
        .dropdown-trigger:hover {
          border-color: #10b981;
          background: #f0fdf4;
        }
        .dropdown-menu {
          position: absolute;
          top: calc(100% + 4px);
          left: 0;
          right: 0;
          background: white;
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          box-shadow: 0 4px 12px rgba(0,0,0,0.1);
          z-index: 200;
          padding: 8px;
        }
        .dropdown-item {
          display: flex;
          justify-content: space-between;
          padding: 8px 12px;
          font-size: 13px;
        }
        .dropdown-item span {
          color: #6b7280;
          font-size: 11px;
        }
        .dropdown-item-btn {
          width: 100%;
          padding: 10px 12px;
          border: none;
          background: transparent;
          cursor: pointer;
          font-weight: 600;
          font-size: 14px;
          display: flex;
          align-items: center;
          gap: 8px;
          border-radius: 6px;
          transition: all 0.2s;
          text-align: left;
        }
        .dropdown-item-btn:hover {
          background: #f0fdf4;
          color: #10b981;
        }

        .form-row-compact {
          display: flex;
          gap: 12px;
          margin-bottom: 12px;
          align-items: stretch;
        }
        .form-group-compact {
          display: flex;
          flex-direction: column;
        }
        .form-group-compact input,
        .form-group-compact select {
          padding: 12px 16px;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          font-size: 15px;
          min-height: 48px;
          height: 48px;
          transition: all 0.2s;
          background: #f8fafc;
          width: 100%;
        }
        .form-group-compact input:focus,
        .form-group-compact select:focus {
          outline: none;
          border-color: #10b981;
          background: white;
          box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        @media (max-width: 768px) {
          .form-row-compact {
            flex-direction: row;
          }
        }
        .compras-header-compact {
          background: white;
          padding: 20px;
          border-radius: 16px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.08);
          margin-bottom: 24px;
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
          gap: 16px;
          cursor: pointer;
          transition: all 0.2s;
        }
        .compras-header-compact:hover {
          box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-item {
          display: flex;
          flex-direction: column;
          gap: 8px;
        }
        .stat-highlight {
          background: linear-gradient(135deg, #10b981 0%, #059669 100%);
          padding: 16px;
          border-radius: 12px;
          color: white;
        }
        .stat-label {
          font-size: 11px;
          color: #64748b;
          font-weight: 600;
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }
        .stat-highlight .stat-label {
          color: rgba(255,255,255,0.9);
        }
        .stat-value {
          font-size: 24px;
          font-weight: 700;
          color: #0f172a;
        }
        .stat-highlight .stat-value {
          color: white;
          font-size: 28px;
        }
        .stat-hint {
          font-size: 10px;
          color: rgba(255,255,255,0.8);
          margin-top: 4px;
        }
        .stat-divider {
          display: none;
        }
        @media (max-width: 768px) {
          .compras-header-compact {
            flex-direction: column;
            gap: 0;
            padding: 0;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
          }
          .stat-item {
            flex: 0 0 auto;
            min-width: 120px;
            padding: 12px;
            border-right: 1px solid #e5e7eb;
          }
          .stat-item:last-child {
            border-right: none;
          }
          .compras-header-compact > * {
            display: inline-flex;
            flex-direction: column;
          }
          .compras-header-compact {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
          }
          .stat-divider {
            display: none;
          }
          .stat-label {
            font-size: 8px;
            white-space: nowrap;
          }
          .stat-value {
            font-size: 13px;
            white-space: nowrap;
          }
          .stat-highlight {
            flex: 0 0 auto;
            min-width: 140px;
            text-align: left;
            padding: 12px;
            border: none;
            border-left: 3px solid #10b981;
          }
          .stat-hint {
            font-size: 8px;
          }
        }


        .compra-form {
          background: white;
          padding: 16px;
          border-radius: 16px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .form-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
          gap: 16px;
          margin-bottom: 16px;
        }
        .form-group {
          display: flex;
          flex-direction: column;
          gap: 6px;
        }
        .form-group label {
          font-weight: 600;
          font-size: 14px;
          color: #374151;
          display: flex;
          align-items: center;
          gap: 6px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
          padding: 12px 16px;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          font-size: 15px;
          min-height: 48px;
          transition: all 0.2s;
          background: #f8fafc;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
          outline: none;
          border-color: #10b981;
          background: white;
          box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        .items-section {
          margin-top: 24px;
          padding-top: 24px;
          border-top: 2px solid #e5e7eb;
        }
        .items-section h3 {
          display: flex;
          align-items: center;
          gap: 8px;
          margin-bottom: 16px;
        }
        .item-form {
          display: flex;
          flex-direction: column;
          gap: 12px;
          margin-bottom: 16px;
        }
        .item-form-row {
          display: flex;
          gap: 8px;
          align-items: center;
        }
        @media (max-width: 768px) {
          .item-form-row {
            flex-wrap: wrap;
          }
          .item-form-row > * {
            min-width: 0;
          }
        }
        .search-container {
          position: relative;
        }
        .search-results {
          position: absolute;
          top: calc(100% + 4px);
          left: 0;
          right: 0;
          background: white;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          max-height: 300px;
          overflow-y: auto;
          z-index: 10;
          box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .search-result-item {
          padding: 12px 16px;
          cursor: pointer;
          border-bottom: 1px solid #f1f5f9;
          transition: all 0.15s;
          min-height: 44px;
          display: flex;
          align-items: center;
        }
        .search-result-item:hover {
          background: #f8fafc;
        }
        .create-new {
          color: #10b981;
          font-weight: 600;
          display: flex;
          align-items: center;
          gap: 6px;
        }
        .modal-overlay {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          backdrop-filter: blur(4px);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 1000;
          animation: fadeIn 0.2s;
        }
        @keyframes fadeIn {
          from { opacity: 0; }
          to { opacity: 1; }
        }
        .modal-content {
          background: white;
          padding: 24px;
          border-radius: 16px;
          max-width: 500px;
          width: 90%;
          max-height: 90vh;
          overflow-y: auto;
          box-shadow: 0 20px 40px rgba(0,0,0,0.2);
          animation: slideUp 0.3s;
        }
        @keyframes slideUp {
          from { transform: translateY(20px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }
        .modal-content h3 {
          margin-bottom: 16px;
        }
        .item-form select,
        .item-form input {
          padding: 12px 16px;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          min-height: 48px;
          font-size: 15px;
          transition: all 0.2s;
          background: #f8fafc;
        }
        .item-form select:focus,
        .item-form input:focus {
          outline: none;
          border-color: #10b981;
          background: white;
          box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        .btn-add {
          padding: 14px 20px;
          background: linear-gradient(135deg, #10b981 0%, #059669 100%);
          color: white;
          border: none;
          border-radius: 10px;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          font-weight: 600;
          font-size: 15px;
          min-height: 48px;
          transition: all 0.2s;
          box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        .btn-add:hover {
          transform: translateY(-1px);
          box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        .items-list {
          background: #f8fafc;
          padding: 16px;
          border-radius: 12px;
          margin-top: 16px;
        }
        .item-row {
          display: grid;
          grid-template-columns: 2fr 1fr 1fr 1fr auto;
          gap: 12px;
          padding: 12px;
          background: white;
          border-radius: 8px;
          margin-bottom: 8px;
          align-items: center;
          transition: all 0.2s;
        }
        .item-row:hover {
          box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        @media (max-width: 768px) {
          .item-row {
            grid-template-columns: 2fr 0.8fr 0.6fr 0.8fr auto;
            gap: 6px;
            padding: 8px;
            font-size: 12px;
          }
          .item-row span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }
        }
        .subtotal {
          font-weight: 700;
          color: #10b981;
        }
        .btn-remove {
          padding: 8px;
          background: #ef4444;
          color: white;
          border: none;
          border-radius: 8px;
          cursor: pointer;
          min-width: 40px;
          min-height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: all 0.2s;
        }
        .btn-remove:hover {
          background: #dc2626;
          transform: scale(1.05);
        }
        .total-row {
          display: flex;
          justify-content: space-between;
          padding: 16px;
          background: linear-gradient(135deg, #10b981 0%, #059669 100%);
          color: white;
          border-radius: 10px;
          font-weight: 700;
          font-size: 20px;
          margin-top: 12px;
          box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-submit {
          position: fixed;
          bottom: 20px;
          left: 50%;
          transform: translateX(-50%);
          width: calc(100% - 40px);
          max-width: 1160px;
          z-index: 50;
        }
        .btn-submit button {
          width: 100%;
          padding: 16px;
          background: linear-gradient(135deg, #10b981 0%, #059669 100%);
          color: white;
          border: none;
          border-radius: 12px;
          font-size: 16px;
          font-weight: 700;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          min-height: 56px;
          transition: all 0.2s;
          box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
        }
        .btn-submit button:hover:not(:disabled) {
          transform: translateY(-2px);
          box-shadow: 0 8px 24px rgba(16, 185, 129, 0.5);
        }
        .btn-submit button:disabled {
          opacity: 0.5;
          cursor: not-allowed;
        }
        .historial-container {
          display: flex;
          flex-direction: column;
          gap: 16px;
        }
        .compra-card {
          background: white;
          padding: 16px;
          border-radius: 12px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.08);
          margin-bottom: 16px;
          transition: all 0.2s;
        }
        .compra-card:hover {
          box-shadow: 0 4px 12px rgba(0,0,0,0.1);
          transform: translateY(-2px);
        }
        .compra-header {
          display: flex;
          justify-content: space-between;
          align-items: start;
          margin-bottom: 12px;
        }
        .compra-header h4 {
          font-size: 18px;
          margin-bottom: 4px;
        }
        .compra-fecha {
          font-size: 12px;
          color: #6b7280;
        }
        .compra-monto {
          font-size: 24px;
          font-weight: bold;
          color: #10b981;
        }
        .compra-details {
          display: flex;
          gap: 8px;
          flex-wrap: wrap;
          margin-top: 8px;
        }
        .badge {
          padding: 6px 12px;
          border-radius: 6px;
          font-size: 11px;
          font-weight: 600;
          background: #f1f5f9;
          color: #64748b;
        }
        .badge-ingredientes { background: #dbeafe; color: #1e40af; }
        .badge-insumos { background: #fef3c7; color: #92400e; }
        .badge-pagado { background: #d1fae5; color: #065f46; }
        .compra-items {
          margin-top: 16px;
          background: #f8fafc;
          border-radius: 10px;
          padding: 12px;
        }
        .items-header {
          display: grid;
          grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
          gap: 8px;
          padding: 8px 8px 12px 8px;
          font-size: 11px;
          font-weight: 700;
          color: #6b7280;
          text-transform: uppercase;
          border-bottom: 2px solid #e5e7eb;
        }
        .items-header span:nth-child(4),
        .items-header span:nth-child(5) {
          text-align: right;
        }
        .compra-item {
          display: grid;
          grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
          gap: 8px;
          padding: 10px 8px;
          background: white;
          border-radius: 4px;
          margin-bottom: 4px;
          font-size: 13px;
          align-items: center;
        }
        .item-nombre {
          font-weight: 600;
          color: #374151;
        }
        .item-cantidad {
          color: #6b7280;
        }
        .item-precio {
          color: #6b7280;
        }
        .item-subtotal {
          color: #10b981;
          font-weight: 600;
          text-align: right;
        }
        .item-stock {
          text-align: right;
          color: #6b7280;
        }
        @media (max-width: 768px) {
          .items-header,
          .compra-item {
            grid-template-columns: 2fr 0.7fr 0.6fr 0.8fr 0.8fr;
            font-size: 10px;
            gap: 4px;
          }
          .items-header {
            font-size: 9px;
          }
        }
        .compra-notas {
          margin-top: 12px;
          padding: 12px;
          background: #f9fafb;
          border-radius: 6px;
          font-size: 14px;
          color: #6b7280;
        }
        .compra-sin-detalle {
          margin-top: 12px;
          padding: 16px;
          background: #fef3c7;
          border-radius: 6px;
          font-size: 13px;
          color: #92400e;
          text-align: center;
        }
        .empty-state {
          text-align: center;
          padding: 60px 20px;
          color: #9ca3af;
          font-size: 16px;
        }
        .spinner {
          display: inline-block;
          width: 16px;
          height: 16px;
          border: 2px solid rgba(255,255,255,0.3);
          border-top-color: white;
          border-radius: 50%;
          animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        @keyframes pulse {
          0%, 100% {
            transform: scale(1);
          }
          50% {
            transform: scale(1.01);
          }
        }
      `}</style>
    </div>
  );
}
