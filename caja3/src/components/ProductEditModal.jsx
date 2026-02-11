import React, { useState, useEffect } from 'react';

export default function ProductEditModal({ productId, onClose, onSave }) {
  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(true);
  const [categories, setCategories] = useState([]);
  const [subcategories, setSubcategories] = useState([]);

  useEffect(() => {
    if (productId) {
      loadProduct();
      loadCategories();
    }
  }, [productId]);

  const loadProduct = async () => {
    try {
      const res = await fetch(`/api/get_productos.php?id=${productId}`);
      const data = await res.json();
      setProduct(Array.isArray(data) ? data[0] : data);
      setLoading(false);
    } catch (error) {
      console.error('Error:', error);
      setLoading(false);
    }
  };

  const loadCategories = async () => {
    try {
      const res = await fetch('/api/get_categories.php');
      const data = await res.json();
      setCategories(data.success ? data.categories : []);
    } catch (error) {
      console.error('Error:', error);
    }
  };

  const loadSubcategories = async (categoryId) => {
    try {
      const res = await fetch(`/api/get_subcategories.php?category_id=${categoryId}`);
      const data = await res.json();
      setSubcategories(data.success ? data.subcategories : []);
    } catch (error) {
      console.error('Error:', error);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    const formData = new FormData();
    Object.keys(product).forEach(key => {
      formData.append(key, product[key]);
    });

    try {
      const res = await fetch('/api/update_producto.php', {
        method: 'POST',
        body: formData
      });
      const result = await res.json();
      if (result.success) {
        onSave();
      } else {
        alert('Error: ' + result.error);
      }
    } catch (error) {
      alert('Error al guardar');
    }
  };

  if (loading) {
    return (
      <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999 }}>
        <div style={{ background: 'white', padding: '40px', borderRadius: '12px' }}>
          <div>Cargando...</div>
        </div>
      </div>
    );
  }

  if (!product) return null;

  const categoryNames = { 1: 'La Ruta 11', 2: 'Sandwiches', 3: 'Hamburguesas', 4: 'Completos', 5: 'Snacks', 6: 'Personalizar', 7: 'Extras', 8: 'Combos' };

  return (
    <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: '20px' }} onClick={onClose}>
      <div style={{ background: 'white', borderRadius: '12px', width: '100%', maxWidth: '800px', maxHeight: '90vh', overflow: 'auto' }} onClick={e => e.stopPropagation()}>
        <div style={{ padding: '24px', borderBottom: '1px solid #e5e5e5', display: 'flex', justifyContent: 'space-between', alignItems: 'center', position: 'sticky', top: 0, background: 'white', zIndex: 1 }}>
          <h2 style={{ margin: 0, fontSize: '20px', fontWeight: 600 }}>Editar Producto</h2>
          <button onClick={onClose} style={{ background: 'none', border: 'none', fontSize: '28px', cursor: 'pointer', padding: '0 8px', lineHeight: 1 }}>&times;</button>
        </div>

        <form onSubmit={handleSubmit} style={{ padding: '24px' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginBottom: '16px' }}>
            <div>
              <label style={{ display: 'block', marginBottom: '4px', fontWeight: 500, fontSize: '14px' }}>Nombre *</label>
              <input
                type="text"
                value={product.name || ''}
                onChange={e => setProduct({ ...product, name: e.target.value })}
                required
                style={{ width: '100%', padding: '8px 12px', border: '1px solid #e5e5e5', borderRadius: '6px', fontSize: '14px' }}
              />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '4px', fontWeight: 500, fontSize: '14px' }}>SKU</label>
              <input
                type="text"
                value={product.sku || ''}
                onChange={e => setProduct({ ...product, sku: e.target.value })}
                style={{ width: '100%', padding: '8px 12px', border: '1px solid #e5e5e5', borderRadius: '6px', fontSize: '14px' }}
              />
            </div>
          </div>

          <div style={{ marginBottom: '16px' }}>
            <label style={{ display: 'block', marginBottom: '4px', fontWeight: 500, fontSize: '14px' }}>Descripción</label>
            <textarea
              value={product.description || ''}
              onChange={e => setProduct({ ...product, description: e.target.value })}
              rows={3}
              style={{ width: '100%', padding: '8px 12px', border: '1px solid #e5e5e5', borderRadius: '6px', fontSize: '14px', resize: 'vertical' }}
            />
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '16px', marginBottom: '16px' }}>
            <div>
              <label style={{ display: 'block', marginBottom: '4px', fontWeight: 500, fontSize: '14px' }}>Precio *</label>
              <input
                type="number"
                value={product.price || ''}
                onChange={e => setProduct({ ...product, price: e.target.value })}
                required
                step="1"
                style={{ width: '100%', padding: '8px 12px', border: '1px solid #e5e5e5', borderRadius: '6px', fontSize: '14px' }}
              />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '4px', fontWeight: 500, fontSize: '14px' }}>Costo</label>
              <input
                type="number"
                value={product.cost_price || ''}
                onChange={e => setProduct({ ...product, cost_price: e.target.value })}
                step="1"
                style={{ width: '100%', padding: '8px 12px', border: '1px solid #e5e5e5', borderRadius: '6px', fontSize: '14px' }}
              />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '4px', fontWeight: 500, fontSize: '14px' }}>Margen</label>
              <div style={{ padding: '8px 12px', background: '#f9fafb', borderRadius: '6px', fontSize: '14px', fontWeight: 600, color: product.price && product.cost_price ? (((product.price - product.cost_price) / product.price * 100) >= 40 ? '#059669' : '#dc2626') : '#666' }}>
                {product.price && product.cost_price ? Math.round((product.price - product.cost_price) / product.price * 100) + '%' : '-'}
              </div>
            </div>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginBottom: '16px' }}>
            <div>
              <label style={{ display: 'block', marginBottom: '4px', fontWeight: 500, fontSize: '14px' }}>Stock</label>
              <input
                type="number"
                value={product.stock_quantity || ''}
                onChange={e => setProduct({ ...product, stock_quantity: e.target.value })}
                style={{ width: '100%', padding: '8px 12px', border: '1px solid #e5e5e5', borderRadius: '6px', fontSize: '14px' }}
              />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '4px', fontWeight: 500, fontSize: '14px' }}>Stock Mínimo</label>
              <input
                type="number"
                value={product.min_stock_level || ''}
                onChange={e => setProduct({ ...product, min_stock_level: e.target.value })}
                style={{ width: '100%', padding: '8px 12px', border: '1px solid #e5e5e5', borderRadius: '6px', fontSize: '14px' }}
              />
            </div>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginBottom: '16px' }}>
            <div>
              <label style={{ display: 'block', marginBottom: '4px', fontWeight: 500, fontSize: '14px' }}>Categoría</label>
              <select
                value={product.category_id || ''}
                onChange={e => {
                  setProduct({ ...product, category_id: e.target.value });
                  loadSubcategories(e.target.value);
                }}
                style={{ width: '100%', padding: '8px 12px', border: '1px solid #e5e5e5', borderRadius: '6px', fontSize: '14px' }}
              >
                {Object.entries(categoryNames).map(([id, name]) => (
                  <option key={id} value={id}>{name}</option>
                ))}
              </select>
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '4px', fontWeight: 500, fontSize: '14px' }}>Estado</label>
              <select
                value={product.is_active || '1'}
                onChange={e => setProduct({ ...product, is_active: e.target.value })}
                style={{ width: '100%', padding: '8px 12px', border: '1px solid #e5e5e5', borderRadius: '6px', fontSize: '14px' }}
              >
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
              </select>
            </div>
          </div>

          <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end', paddingTop: '16px', borderTop: '1px solid #e5e5e5' }}>
            <button type="button" onClick={onClose} style={{ padding: '10px 20px', border: '1px solid #e5e5e5', background: 'white', borderRadius: '6px', cursor: 'pointer', fontSize: '14px', fontWeight: 500 }}>
              Cancelar
            </button>
            <button type="submit" style={{ padding: '10px 20px', background: '#0a0a0a', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer', fontSize: '14px', fontWeight: 500 }}>
              Guardar Cambios
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
