import { useState, useEffect } from 'react';
import { Trash2, Plus, ArrowLeft, ClipboardList } from 'lucide-react';

export default function MermasApp() {
  const [activeTab, setActiveTab] = useState('mermar');
  const [ingredientes, setIngredientes] = useState([]);
  const [productos, setProductos] = useState([]);
  const [itemType, setItemType] = useState('ingredient');
  const [mermaItems, setMermaItems] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [filteredIngredientes, setFilteredIngredientes] = useState([]);
  const [currentItem, setCurrentItem] = useState({
    ingrediente_id: '',
    nombre_item: '',
    cantidad: '',
    unidad: 'kg',
    stock_actual: 0,
    costo_unitario: 0
  });
  const [reason, setReason] = useState('');
  const [loading, setLoading] = useState(false);
  const [mermasHistorial, setMermasHistorial] = useState([]);

  useEffect(() => {
    loadIngredientes();
    loadProductos();
    if (activeTab === 'historial') loadMermasHistorial();
  }, [activeTab]);

  const loadIngredientes = async () => {
    try {
      const response = await fetch(`/api/get_ingredientes.php?t=${Date.now()}`);
      const data = await response.json();
      setIngredientes(Array.isArray(data) ? data.filter(i => i.is_active) : []);
    } catch (error) {
      console.error('Error:', error);
    }
  };

  const loadProductos = async () => {
    try {
      const response = await fetch(`/api/get_productos.php?t=${Date.now()}`);
      const data = await response.json();
      setProductos(Array.isArray(data) ? data.filter(p => p.is_active) : []);
    } catch (error) {
      console.error('Error:', error);
    }
  };

  const loadMermasHistorial = async () => {
    try {
      const response = await fetch(`/api/get_mermas.php?t=${Date.now()}`);
      const data = await response.json();
      setMermasHistorial(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error:', error);
      setMermasHistorial([]);
    }
  };

  const fuzzyMatch = (str, pattern) => {
    const strLower = str.toLowerCase();
    const patternLower = pattern.toLowerCase();
    let patternIdx = 0;
    let score = 0;
    
    for (let i = 0; i < strLower.length && patternIdx < patternLower.length; i++) {
      if (strLower[i] === patternLower[patternIdx]) {
        score += (patternIdx === 0 || strLower[i-1] === ' ') ? 2 : 1;
        patternIdx++;
      }
    }
    return patternIdx === patternLower.length ? score : 0;
  };

  const handleSearchChange = (e) => {
    const term = e.target.value;
    setSearchTerm(term);
    if (!term.trim()) {
      setFilteredIngredientes([]);
    } else {
      const items = itemType === 'ingredient' ? ingredientes : productos;
      const filtered = items
        .map(i => ({ ...i, score: fuzzyMatch(i.name, term), type: itemType }))
        .filter(i => i.score > 0)
        .sort((a, b) => b.score - a.score)
        .slice(0, 10);
      setFilteredIngredientes(filtered);
    }
  };

  const handleIngredienteSelect = (item) => {
    if (item.type === 'ingredient') {
      setCurrentItem({
        item_id: item.id,
        item_type: 'ingredient',
        nombre_item: item.name,
        cantidad: '',
        unidad: item.unit,
        stock_actual: item.current_stock,
        costo_unitario: parseFloat(item.cost_per_unit)
      });
    } else {
      setCurrentItem({
        item_id: item.id,
        item_type: 'product',
        nombre_item: item.name,
        cantidad: '',
        unidad: 'unidad',
        stock_actual: item.stock_quantity,
        costo_unitario: parseFloat(item.cost_price)
      });
    }
    setSearchTerm(item.name);
    setFilteredIngredientes([]);
  };

  const handleAddItem = () => {
    if (!currentItem.item_id || !currentItem.cantidad) {
      alert('Complete item y cantidad');
      return;
    }

    const cantidad = parseFloat(currentItem.cantidad);
    if (cantidad > currentItem.stock_actual) {
      alert(`Cantidad excede stock disponible (${currentItem.stock_actual} ${currentItem.unidad})`);
      return;
    }

    const subtotal = cantidad * currentItem.costo_unitario;
    setMermaItems([...mermaItems, { ...currentItem, cantidad, subtotal }]);
    setCurrentItem({
      item_id: '',
      item_type: itemType,
      nombre_item: '',
      cantidad: '',
      unidad: itemType === 'ingredient' ? 'kg' : 'unidad',
      stock_actual: 0,
      costo_unitario: 0
    });
    setSearchTerm('');
  };

  const handleRemoveItem = (index) => {
    setMermaItems(mermaItems.filter((_, i) => i !== index));
  };

  const handleSubmit = async () => {
    if (mermaItems.length === 0) {
      alert('Agregue al menos un item');
      return;
    }
    if (!reason) {
      alert('Seleccione un motivo');
      return;
    }

    setLoading(true);
    try {
      for (const item of mermaItems) {
        await fetch('/api/registrar_merma.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            item_type: item.item_type,
            item_id: item.item_id,
            quantity: item.cantidad,
            reason: reason
          })
        });
      }
      alert('✅ Mermas registradas exitosamente');
      setMermaItems([]);
      setReason('');
      setActiveTab('historial');
      loadMermasHistorial();
    } catch (error) {
      alert('❌ Error al registrar mermas');
    } finally {
      setLoading(false);
    }
  };

  const fmt = (n) => Math.round(n).toLocaleString('es-CL');
  const totalCost = mermaItems.reduce((sum, item) => sum + item.subtotal, 0);

  return (
    <div style={{minHeight: '100vh', background: '#f9fafb', paddingBottom: '80px'}}>
      <header style={{background: 'white', boxShadow: '0 1px 3px rgba(0,0,0,0.1)', borderBottom: '1px solid #e5e7eb', position: 'sticky', top: 0, zIndex: 10}}>
        <div style={{maxWidth: '1024px', margin: '0 auto', padding: '16px', display: 'flex', alignItems: 'center', gap: '16px'}}>
          <button onClick={() => window.location.href = '/'} style={{padding: '8px', background: 'transparent', border: 'none', cursor: 'pointer', borderRadius: '50%'}}>
            <ArrowLeft size={24} />
          </button>
          <div>
            <h1 style={{fontSize: '20px', fontWeight: 'bold', color: '#1f2937'}}>Gestión de Mermas</h1>
            <p style={{fontSize: '14px', color: '#6b7280'}}>Control de desperdicios</p>
          </div>
        </div>
        <div style={{maxWidth: '1024px', margin: '0 auto', padding: '0 16px', display: 'flex', gap: '8px', borderTop: '1px solid #e5e7eb'}}>
          <button onClick={() => setActiveTab('mermar')} style={{flex: 1, padding: '12px', background: 'transparent', border: 'none', borderBottom: activeTab === 'mermar' ? '3px solid #dc2626' : '3px solid transparent', color: activeTab === 'mermar' ? '#dc2626' : '#6b7280', fontWeight: activeTab === 'mermar' ? '600' : '400', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', transition: 'all 0.2s'}}>
            <Trash2 size={18} /> Mermar
          </button>
          <button onClick={() => setActiveTab('historial')} style={{flex: 1, padding: '12px', background: 'transparent', border: 'none', borderBottom: activeTab === 'historial' ? '3px solid #dc2626' : '3px solid transparent', color: activeTab === 'historial' ? '#dc2626' : '#6b7280', fontWeight: activeTab === 'historial' ? '600' : '400', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', transition: 'all 0.2s'}}>
            <ClipboardList size={18} /> Registro de Mermas
          </button>
        </div>
      </header>

      <main style={{maxWidth: '1024px', margin: '0 auto', padding: '24px 16px'}}>
        {activeTab === 'mermar' ? (
        <div style={{background: 'white', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)', padding: '24px'}}>
          <h3 style={{fontSize: '18px', fontWeight: '600', marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px'}}>
            <Trash2 size={20} /> Agregar Items
          </h3>
          
          <div style={{marginBottom: '16px'}}>
            <div style={{display: 'flex', gap: '8px', marginBottom: '12px'}}>
              <button onClick={() => { setItemType('ingredient'); setSearchTerm(''); setFilteredIngredientes([]); }} style={{padding: '8px 16px', background: itemType === 'ingredient' ? '#dc2626' : '#e5e7eb', color: itemType === 'ingredient' ? 'white' : '#374151', border: 'none', borderRadius: '6px', fontWeight: '600', cursor: 'pointer'}}>Ingredientes</button>
              <button onClick={() => { setItemType('product'); setSearchTerm(''); setFilteredIngredientes([]); }} style={{padding: '8px 16px', background: itemType === 'product' ? '#dc2626' : '#e5e7eb', color: itemType === 'product' ? 'white' : '#374151', border: 'none', borderRadius: '6px', fontWeight: '600', cursor: 'pointer'}}>Productos</button>
            </div>
          </div>
          
          <div style={{display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '12px', marginBottom: '16px'}}>
            <div style={{position: 'relative', gridColumn: 'span 2'}}>
              <input
                type="text"
                placeholder={`Buscar ${itemType === 'ingredient' ? 'ingrediente' : 'producto'}...`}
                value={searchTerm}
                onChange={handleSearchChange}
                style={{width: '100%', padding: '10px 12px', border: '2px solid #e5e7eb', borderRadius: '8px', fontSize: '14px'}}
              />
              {filteredIngredientes.length > 0 && (
                <div style={{position: 'absolute', top: '100%', left: 0, right: 0, background: 'white', border: '2px solid #e5e7eb', borderTop: 'none', borderRadius: '0 0 8px 8px', maxHeight: '200px', overflowY: 'auto', zIndex: 10, boxShadow: '0 4px 6px rgba(0,0,0,0.1)'}}>
                  {filteredIngredientes.map(item => (
                    <div key={item.id} onMouseDown={() => handleIngredienteSelect(item)} style={{padding: '10px', cursor: 'pointer', borderBottom: '1px solid #f3f4f6'}} onMouseEnter={(e) => e.target.style.background = '#f9fafb'} onMouseLeave={(e) => e.target.style.background = 'white'}>
                      <strong>{item.name}</strong> <span style={{color: '#999', fontSize: '12px'}}>({item.type === 'ingredient' ? item.current_stock : item.stock_quantity} {item.type === 'ingredient' ? item.unit : 'unidad'})</span>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <input
              type="number"
              placeholder="Cantidad"
              value={currentItem.cantidad}
              onChange={(e) => setCurrentItem({...currentItem, cantidad: e.target.value})}
              step="0.01"
              style={{padding: '10px 12px', border: '2px solid #e5e7eb', borderRadius: '8px', fontSize: '14px'}}
            />

            <select
              value={currentItem.unidad}
              onChange={(e) => setCurrentItem({...currentItem, unidad: e.target.value})}
              style={{padding: '10px 12px', border: '2px solid #e5e7eb', borderRadius: '8px', fontSize: '14px'}}
            >
              <option value="kg">kg</option>
              <option value="unidad">unidad</option>
              <option value="litro">litro</option>
              <option value="gramo">gramo</option>
            </select>

            <button onClick={handleAddItem} style={{padding: '10px 16px', background: '#dc2626', color: 'white', border: 'none', borderRadius: '8px', fontWeight: '600', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px'}}>
              <Plus size={18} /> Agregar
            </button>
          </div>

          {currentItem.item_id && (
            <div style={{padding: '12px', background: '#f9fafb', borderRadius: '8px', marginBottom: '16px', fontSize: '14px'}}>
              <div style={{display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px'}}>
                <div><span style={{color: '#6b7280'}}>Stock:</span> <strong>{currentItem.stock_actual} {currentItem.unidad}</strong></div>
                <div><span style={{color: '#6b7280'}}>Costo/unidad:</span> <strong>${fmt(currentItem.costo_unitario)}</strong></div>
              </div>
            </div>
          )}

          {currentItem.cantidad && currentItem.item_id && (
            <div style={{padding: '12px', background: '#fef2f2', border: '2px solid #fca5a5', borderRadius: '8px', marginBottom: '16px'}}>
              <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                <span style={{fontSize: '14px', fontWeight: '600', color: '#991b1b'}}>💰 Costo de merma:</span>
                <span style={{fontSize: '18px', fontWeight: 'bold', color: '#991b1b'}}>${fmt(parseFloat(currentItem.cantidad) * currentItem.costo_unitario)}</span>
              </div>
            </div>
          )}

          {mermaItems.length > 0 && (
            <>
              <h4 style={{fontWeight: '600', marginBottom: '12px', color: '#374151'}}>Items a mermar:</h4>
              <div style={{marginBottom: '16px'}}>
                {mermaItems.map((item, index) => (
                  <div key={index} style={{display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr auto', gap: '12px', padding: '12px', background: '#f9fafb', borderRadius: '8px', alignItems: 'center', marginBottom: '8px'}}>
                    <span>{item.nombre_item}</span>
                    <span>{item.cantidad} {item.unidad}</span>
                    <span>${fmt(item.costo_unitario)}</span>
                    <span style={{fontWeight: '600', color: '#dc2626'}}>${fmt(item.subtotal)}</span>
                    <button onClick={() => handleRemoveItem(index)} style={{padding: '8px', background: '#ef4444', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer'}}>
                      <Trash2 size={16} />
                    </button>
                  </div>
                ))}
              </div>

              <div style={{padding: '16px', background: '#fef2f2', border: '2px solid #dc2626', borderRadius: '8px'}}>
                <div style={{display: 'flex', justifyContent: 'space-between', marginBottom: '12px'}}>
                  <span style={{fontWeight: '600'}}>COSTO TOTAL MERMAS</span>
                  <span style={{fontSize: '20px', fontWeight: 'bold', color: '#dc2626'}}>${fmt(totalCost)}</span>
                </div>

                <div style={{marginBottom: '12px'}}>
                  <label style={{display: 'block', fontSize: '14px', fontWeight: '500', color: '#374151', marginBottom: '8px'}}>Motivo *</label>
                  <select value={reason} onChange={(e) => setReason(e.target.value)} style={{width: '100%', padding: '10px 12px', border: '2px solid #e5e7eb', borderRadius: '8px', fontSize: '14px'}}>
                    <option value="">Seleccionar motivo</option>
                    <option value="Nuevo producto">Nuevo producto</option>
                    <option value="Vencido">Vencido</option>
                    <option value="Dañado">Dañado</option>
                    <option value="Quemado">Quemado</option>
                    <option value="Caído">Caído</option>
                    <option value="Mal estado">Mal estado</option>
                    <option value="Otro">Otro</option>
                  </select>
                </div>

                <button onClick={handleSubmit} disabled={loading} style={{width: '100%', padding: '12px', background: '#dc2626', color: 'white', border: 'none', borderRadius: '8px', fontWeight: 'bold', cursor: 'pointer', fontSize: '16px'}}>
                  {loading ? 'Registrando...' : 'Registrar Mermas'}
                </button>
              </div>
            </>
          )}
        </div>
        ) : (
        <div style={{background: 'white', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)', padding: '24px'}}>
          <h3 style={{fontSize: '18px', fontWeight: '600', marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px'}}>
            <ClipboardList size={20} /> Historial de Mermas
          </h3>
          {mermasHistorial.length === 0 ? (
            <p style={{textAlign: 'center', color: '#6b7280', padding: '40px'}}>No hay mermas registradas</p>
          ) : (
            <div style={{display: 'flex', flexDirection: 'column', gap: '12px'}}>
              {mermasHistorial.map((merma) => (
                <div key={merma.id} style={{padding: '16px', background: '#f9fafb', borderRadius: '8px', border: '1px solid #e5e7eb'}}>
                  <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'start', marginBottom: '8px'}}>
                    <div>
                      <h4 style={{fontWeight: '600', color: '#1f2937', marginBottom: '4px'}}>{merma.item_name}</h4>
                      <p style={{fontSize: '14px', color: '#6b7280'}}>{merma.quantity} {merma.unit}</p>
                    </div>
                    <div style={{textAlign: 'right'}}>
                      <p style={{fontSize: '16px', fontWeight: '600', color: '#dc2626'}}>${fmt(merma.cost)}</p>
                      <p style={{fontSize: '12px', color: '#6b7280'}}>{new Date(merma.created_at).toLocaleDateString('es-CL')}</p>
                    </div>
                  </div>
                  <div style={{display: 'flex', alignItems: 'center', gap: '8px', marginTop: '8px', padding: '8px', background: 'white', borderRadius: '6px'}}>
                    <span style={{fontSize: '13px', color: '#6b7280'}}>Motivo:</span>
                    <span style={{fontSize: '13px', fontWeight: '500', color: '#374151'}}>{merma.reason}</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
        )}
      </main>
    </div>
  );
}
