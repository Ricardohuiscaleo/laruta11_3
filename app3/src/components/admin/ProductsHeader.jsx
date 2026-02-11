import { List, Edit, Search, Plus } from 'lucide-react';

export default function ProductsHeader({ onAddProduct, onSearch }) {
  return (
    <div className="products-header">
      <div className="tabs">
        <div className="tab-list">
          <button className="tab active">
            <List size={16} className="icon" />
            Lista de Productos
          </button>
          <button className="tab" style={{ display: 'none' }}>
            <Edit size={16} className="icon" />
            Editar Producto
          </button>
        </div>
      </div>
      
      <div className="toolbar">
        <div className="quick-actions">
          <button className="btn btn-success" onClick={onAddProduct}>
            <Plus size={16} />
            Nuevo Producto
          </button>
          <div className="search">
            <Search size={16} className="search-icon" />
            <input 
              type="text" 
              className="search-box" 
              placeholder="Buscar productos..." 
              onKeyUp={(e) => onSearch(e.target.value)}
            />
          </div>
        </div>
      </div>
    </div>
  );
}