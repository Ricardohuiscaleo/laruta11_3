import ProductEditModal from './ProductEditModal.jsx';

let currentProductId = null;
let modalRoot = null;

export function openProductEditModal(productId) {
  currentProductId = productId;
  
  if (!modalRoot) {
    modalRoot = document.getElementById('product-edit-modal-root');
  }
  
  if (modalRoot && window.ReactDOM && window.React) {
    window.ReactDOM.render(
      window.React.createElement(ProductEditModal, {
        productId: currentProductId,
        onClose: closeProductEditModal,
        onSave: handleProductSave
      }),
      modalRoot
    );
  }
}

export function closeProductEditModal() {
  if (modalRoot && window.ReactDOM) {
    window.ReactDOM.unmountComponentAtNode(modalRoot);
  }
  currentProductId = null;
}

export function handleProductSave() {
  if (window.loadProducts) {
    window.loadProducts();
  }
}

// Exponer globalmente
if (typeof window !== 'undefined') {
  window.openProductEditModal = openProductEditModal;
  window.closeProductEditModal = closeProductEditModal;
}
