import React, { useState, useEffect, useRef, useCallback } from 'react';
import ComboModal from './modals/ComboModal.jsx';


// Se puede cargar el CSS directamente en el componente para asegurar la compatibilidad 100% con tu diseño de TV
const TvStyles = `
  .tv-container * { box-sizing: border-box; }
  .tv-container { 
    font-family: 'Montserrat', sans-serif; 
    background: #f5f5f5; 
    color: #333;
    overflow: hidden; 
    height: 100vh;
    width: 100vw;
    display: flex;
    flex-direction: column;
  }
  
  #main-header {
    background: #111827;
    color: white;
    padding: 10px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    z-index: 100;
  }
  .header-left { display: flex; align-items: center; gap: 20px; }
  .logo-header { height: 50px; object-fit: contain; }
  .header-text { font-size: 1.8vw; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; }
  
  .header-cart-btn {
    background: #000000;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 1.2vw;
    font-weight: 900;
    color: white;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.2s ease;
    border: 3px solid transparent;
  }
  .header-cart-btn.focused {
    background: #ff6b00;
    border: 3px solid white;
    box-shadow: 0 0 20px rgba(255,107,0,0.8);
    transform: scale(1.05);
  }
  .inactivity-timer {
    font-size: 1.1vw;
    color: #fca5a5;
    margin-right: 15px;
    font-weight: bold;
    font-variant-numeric: tabular-nums;
    text-align: right;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .inactivity-subtitle {
    font-size: 0.7vw;
    color: #9ca3af;
    font-weight: normal;
    margin-top: 2px;
  }

  #menu-container { 
    padding: 5px;
    height: calc(100vh - 135px); 
    display: flex;
    flex-direction: column;
    overflow: hidden;
    flex: 1;
  }
  
  .layout-split {
    display: flex;
    flex: 1;
    gap: 2px;
    overflow: hidden;
  }

  .column {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: rgba(255,255,255,0.02);
    border-radius: 4px;
    padding: 2px;
    overflow: hidden;
  }
  
  #main-footer {
    height: 60px;
    width: 100vw;
    display: flex;
    gap: 5px;
    padding: 0 5px 5px 5px;
    z-index: 100;
    background: #f5f5f5; 
  }
  .footer-panel {
    background: #ff6b00;
    color: white;
    text-align: center;
    padding: 10px;
    font-weight: 900;
    text-transform: uppercase;
    font-size: 1.2vw;
    box-shadow: 0 -4px 10px rgba(0,0,0,0.1);
    flex: 1;
    border-radius: 5px;
  }
  .footer-panel.right { flex: 0.68; } 

  .products-grid { 
    display: grid; 
    grid-template-columns: repeat(8, 1fr); 
    gap: 2px;
    overflow-y: auto;
    flex: 1;
    align-content: start; 
    grid-auto-rows: min-content; 
  }

  .column-right { flex: 0.68; } 
  .column-right .products-grid {
    grid-template-columns: repeat(8, 1fr); 
  }

  .products-grid::-webkit-scrollbar { width: 4px; }
  .products-grid::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
  
  .product-card { 
    background: #ffffff; 
    border: 2px solid transparent; 
    border-radius: 12px; 
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    cursor: pointer;
    aspect-ratio: 1/1.2; 
    transition: all 0.2s ease;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08); 
    margin: 4px;
  }
  
  .product-card.focused { 
    background-color: #ffffff !important; 
    outline: 5px solid #ff6b00; 
    outline-offset: -5px; 
    box-shadow: 0 0 25px rgba(255, 107, 0, 0.4);
    z-index: 50;
    transform: none !important;
  }
  .product-card.focused .product-name { color: #111827 !important; }
  .product-card.focused .product-price { color: #059669 !important; }

  .product-image { 
      width: 100%; 
      height: 50%; 
      object-fit: cover; 
      transition: transform 0.3s ease;
  }
  .product-image-placeholder { 
    width: 100%; 
    height: 50%; 
    background: transparent; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 1.5vw; 
  }
  
  .product-content { 
    padding: 4px; 
    flex: 1; 
    display: flex; 
    flex-direction: column; 
    justify-content: center; 
    background: transparent; 
    text-align: center; 
  }
  .product-name { 
    font-size: 0.75vw; 
    font-weight: 900; 
    color: #0f172a; 
    line-height: 1.1; 
    text-transform: uppercase; 
    overflow: hidden; 
    display: -webkit-box; 
    -webkit-line-clamp: 2; 
    line-clamp: 2; 
    -webkit-box-orient: vertical; 
    word-break: break-word;
    margin-bottom: 1px;
  }
  .product-price { 
    font-size: 1.1vw; 
    font-weight: 900; 
    color: #059669; 
  }
  
  .card-drink {
    aspect-ratio: 1/1.62 !important; 
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    justify-content: space-between; /* Alinea los extremos (imagen arriba, precio abajo) */
  }
  .card-drink .product-image { 
    flex: 1; /* La imagen absorbe el espacio disponible */
    height: auto !important; 
    max-height: clamp(60%, 70%, 75%) !important; /* Control responsivo estricto de altura */
    width: 100% !important; 
    object-fit: contain !important;
    margin: auto 0;
    mix-blend-mode: multiply; 
    border: none !important;
    padding: clamp(4px, 1vw, 12px); /* Padding fluido */
    transform: scale(1.15) !important;
  }
  .card-drink .product-content {
    flex: none; /* Altura fija para el footer */
    height: clamp(30px, 20%, 45px) !important; 
    background: transparent;
    border: none;
    padding: clamp(2px, 0.5vw, 5px) 0;
    display: flex;
    align-items: flex-end; /* Ancla estrictamente el precio al borde inferior */
    justify-content: center;
    border-top: 1px solid rgba(0,0,0,0.03); /* Para demarcar la zona visualmente */
  }
  .card-drink .product-price { 
    font-size: clamp(0.9rem, 1.2vw, 1.5rem) !important; 
    color: #059669;
    margin-bottom: 5px !important;
  }

  /* Modal Styles Native TV */
  .fullscreen-modal {
    display: flex;
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.95);
    z-index: 9999;
    align-items: center; justify-content: center;
  }
  .fullscreen-content { 
    width: 90vw; 
    height: 80vh; 
    background: white; 
    border-radius: 20px; 
    display: flex; 
    overflow: hidden; 
    position: relative; 
    color: #000;
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
  }
  .fullscreen-image-container {
    width: 40%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    padding: 40px;
  }
  .fullscreen-image { 
    width: 100%;
    height: auto;
    aspect-ratio: 4/5; 
    object-fit: cover; 
    border-radius: 20px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.1);
  }
  .fullscreen-details { width: 60%; padding: 40px 80px; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; }
  .fullscreen-id { font-size: 1vw; color: #64748b; font-weight: bold; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }
  .fullscreen-name { font-size: 4vw; font-weight: 900; margin-bottom: 15px; text-transform: uppercase; line-height: 1; }
  .fullscreen-desc { font-size: 1.4vw; color: #475569; font-weight: 500; margin-bottom: 40px; line-height: 1.5; max-width: 95%; }
  .fullscreen-price { font-size: 5vw; font-weight: 900; color: #059669; margin-bottom: 60px; }
  
  .modal-actions { display: flex; gap: 20px; width: 100%; }
  .modal-btn {
    flex: 1;
    padding: 20px;
    font-size: 1.5vw;
    font-weight: 900;
    border: none;
    border-radius: 15px;
    cursor: pointer;
    text-transform: uppercase;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
  }
  .btn-secondary { background: #e5e7eb; color: #374151; }
  .btn-primary { background: #ff6b00; color: white; }
  
  .modal-btn.focused {
    outline: 5px solid #3b82f6; 
    box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
  }
`;

const categories = {
  2: { name: 'Sandwiches', emoji: '🥪' },
  3: { name: 'Hamburguesas', emoji: '🍔' },
  4: { name: 'Completos', emoji: '🌭' },
  12: { name: 'Papas', emoji: '🍟' },
  8: { name: 'Combos', emoji: '🍽️' },
  11: { name: 'Bebidas', emoji: '🥤' },
  10: { name: 'Café & Té', emoji: '☕' }
};

export default function TvMenuApp() {
  const [allProducts, setAllProducts] = useState([]);
  const [foodCount, setFoodCount] = useState(0);
  const [loading, setLoading] = useState(true);
  
  // Carrito y Timer
  const [cart, setCart] = useState([]);
  const [cartModalOpen, setCartModalOpen] = useState(false);
  const [ticketOrderId, setTicketOrderId] = useState(null);
  const [inactivityTimer, setInactivityTimer] = useState(300);
  const inactivityIntervalRef = useRef(null);
  
  // Usamos un Ref para el índice para no re-renderizar todo el componente (vital para Smart TVs lentas)
  // -1 representa el botón del carrito en el Header
  const navIndexRef = useRef(0);
  const [modalOpen, setModalOpen] = useState(false);
  const [comboModalOpen, setComboModalOpen] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const modalButtonIndexRef = useRef(1); // 0: Volver, 1: Pedir
  const cartModalBtnIndexRef = useRef(1); // 0: Seguir viendo, 1: Enviar a caja

  const loadMenu = async () => {
    try {
      const baseUrl = window.location.port === '4321' ? 'http://localhost:3000' : '';
      const response = await fetch(`${baseUrl}/api/get_productos.php`);
      const products = await response.json();
      
      if (!Array.isArray(products)) throw new Error('Formato inválido');
      
      const menuProducts = products.filter(p => {
        const catId = parseInt(p.category_id);
        return p.is_active == 1 && ([2, 3, 4, 8, 12].includes(catId) || catId === 5);
      });
      
      const mappedProducts = menuProducts.map(product => {
        const catId = parseInt(product.category_id);
        const subcatId = parseInt(product.subcategory_id);
        let categoryKey = catId;
        
        if (catId === 12 || (catId === 5 && subcatId === 9)) categoryKey = 12;
        else if (catId === 5 && (subcatId === 10 || subcatId === 11)) categoryKey = 11;
        else if (catId === 5 && (subcatId === 27 || subcatId === 28)) categoryKey = 10;
        
        return {
          ...product,
          categoryKey,
          categoryName: categories[categoryKey]?.name || 'Otros'
        };
      });
      
      const order = [3, 2, 4, 12, 8, 11, 10];
      mappedProducts.sort((a, b) => order.indexOf(a.categoryKey) - order.indexOf(b.categoryKey));
      
      const isDev = window.location.port === '4321';
      
      // Permitimos explícitamente solo Sandwiches(2), Hamburguesas(3), Completos(4) y Papas(12) para comida
      const foods = mappedProducts.filter(p => [2, 3, 4, 12].includes(p.categoryKey));
      const combos = mappedProducts.filter(p => p.categoryKey === 8);
      
      if (isDev && combos.length === 0) {
          combos.push({id: 9991, name: "Combo XL Burger", price: 7500, categoryKey: 8, image_url: ""});
          combos.push({id: 9992, name: "Promo Duo", price: 12000, categoryKey: 8, image_url: ""});
      }
      
      const finalFoods = [...combos, ...foods];
      const drinks = mappedProducts.filter(p => [11, 10].includes(p.categoryKey));

      const getVolume = (p) => {
        const name = p.name ? p.name.toLowerCase() : '';
        if (name.includes('1.5') || name.includes('1,5')) return 1500;
        if (name.includes('350')) return 350;
        if (typeof p.cc_volume === 'number' && p.cc_volume > 0) return p.cc_volume;
        const match = name.match(/(\d+)\s*(cc|ml|l\b)/);
        if (match) {
            let val = parseInt(match[1], 10);
            if (match[2] === 'l') val *= 1000;
            return val;
        }
        return 0;
      };

      drinks.sort((a, b) => getVolume(b) - getVolume(a));

      const finalArray = [...finalFoods, ...drinks];
      setAllProducts(finalArray);
      setFoodCount(finalFoods.length);
      setLoading(false);
      
      setTimeout(() => updateFocusDOM(0), 100);
      
    } catch (error) {
      console.error('Error:', error);
      setLoading(false);
    }
  };

  // Timer de inactividad
  const resetTimer = useCallback(() => {
    setInactivityTimer(300);
  }, []);

  useEffect(() => {
    if (cart.length > 0 || modalOpen || comboModalOpen || cartModalOpen) {
      inactivityIntervalRef.current = setInterval(() => {
        setInactivityTimer(prev => {
          if (prev <= 1) {
            // Limpiar todo al llegar a 0
            setCart([]);
            setModalOpen(false);
            setComboModalOpen(false);
            setCartModalOpen(false);
            if (navIndexRef.current === -1) {
                updateFocusDOM(0); // Volver el foco a grid si estaba en carrito
            }
            return 300;
          }
          return prev - 1;
        });
      }, 1000);
    } else {
      clearInterval(inactivityIntervalRef.current);
      setInactivityTimer(300);
    }
    return () => clearInterval(inactivityIntervalRef.current);
  }, [cart.length, modalOpen, comboModalOpen, cartModalOpen]);

  useEffect(() => {
    loadMenu();
  }, []);

  const updateFocusDOM = useCallback((newIndex) => {
    document.querySelectorAll('.product-card.focused, .header-cart-btn.focused').forEach(el => el.classList.remove('focused'));
    
    if (newIndex === -1) {
        const cartBtn = document.querySelector('.header-cart-btn');
        if (cartBtn) cartBtn.classList.add('focused');
        navIndexRef.current = newIndex;
        return;
    }

    const cards = document.querySelectorAll('.product-card');
    const targetCard = cards[newIndex];
    if (targetCard) {
      targetCard.classList.add('focused');
      targetCard.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
      navIndexRef.current = newIndex;
    }
  }, []);

  const updateModalFocusDOM = () => {
    document.querySelectorAll('.modal-btn.focused').forEach(el => el.classList.remove('focused'));
    const btns = document.querySelectorAll('.modal-btn');
    if (btns[modalButtonIndexRef.current]) {
      btns[modalButtonIndexRef.current].classList.add('focused');
    }
  };

  const handleCreateOrder = (product) => {
    if (product.categoryKey === 8) {
        setComboModalOpen(true);
    } else {
        setCart(prev => [...prev, product]);
        setModalOpen(false);
        setSelectedProduct(null);
    }
  };

  const handleSendToCaja = async () => {
      if (cart.length === 0) return;
      
      const total = cart.reduce((sum, item) => sum + Number(item.price), 0);
      
      try {
          const response = await fetch('/api/save_tv_order.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ 
                  cart: cart.map(item => ({
                      id: item.id,
                      name: item.name,
                      price: item.price,
                      selections: item.selections || null
                  })), 
                  total 
              })
          });
          
          const result = await response.json();
          if (result.success) {
              setCart([]);
              setCartModalOpen(false);
              setTicketOrderId(result.order_id);
              if (navIndexRef.current === -1) updateFocusDOM(0);
          } else {
              alert("Error al enviar orden: " + (result.error || "Desconocido"));
          }
      } catch (err) {
          console.error("Error sending order:", err);
          alert("No se pudo conectar con el servidor para enviar la orden.");
      }
  };

  useEffect(() => {
    const handleKeyDown = (e) => {
      resetTimer(); // Cualquier botón resetea el timer

      if (comboModalOpen) {
          const btns = Array.from(document.querySelectorAll('.combo-nav-btn:not([disabled])'));
          if (btns.length === 0) return;

          let currentIndex = btns.findIndex(b => b.classList.contains('focused-combo'));
          if (currentIndex === -1) currentIndex = 0; // Inicia en el primero

          if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
              e.preventDefault();
              currentIndex = (currentIndex + 1) % btns.length;
          } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
              e.preventDefault();
              currentIndex = (currentIndex - 1 + btns.length) % btns.length;
          } else if (e.key === 'Enter') {
              e.preventDefault();
              btns[currentIndex].click(); // Simula el click original del Modal
              
              // Pequeño delay por si la UI se re-renderiza tras el click (ej: al clickear un botón)
              setTimeout(() => {
                const newBtns = Array.from(document.querySelectorAll('.combo-nav-btn:not([disabled])'));
                if (newBtns[currentIndex]) {
                    newBtns[currentIndex].focus();
                    newBtns[currentIndex].classList.add('focused-combo');
                    newBtns[currentIndex].style.outline = '5px solid #ff6b00';
                }
              }, 100);
              return;
          }

          document.querySelectorAll('.focused-combo').forEach(el => {
              el.classList.remove('focused-combo');
              el.style.outline = 'none';
              el.style.boxShadow = 'none';
          });
          
          const activeBtn = btns[currentIndex];
          activeBtn.classList.add('focused-combo');
          activeBtn.style.outline = '6px solid #ff6b00';
          activeBtn.style.boxShadow = '0 0 25px rgba(255,107,0, 0.9)';
          activeBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
          return;
      }

      if (e.keyCode === 461 || e.key === "GoBack" || e.key === "Escape") {
          if (cartModalOpen) { e.preventDefault(); setCartModalOpen(false); return; }
          if (modalOpen) { e.preventDefault(); setModalOpen(false); return; }
          if (navIndexRef.current === -1) { e.preventDefault(); updateFocusDOM(0); return; }
      }

      const total = allProducts.length;
      if (total === 0) return;

      if (cartModalOpen) {
          if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
              e.preventDefault();
              cartModalBtnIndexRef.current = cartModalBtnIndexRef.current === 0 ? 1 : 0;
              document.querySelectorAll('.cart-modal-btn.focused').forEach(el => el.classList.remove('focused'));
              const btns = document.querySelectorAll('.cart-modal-btn');
              if (btns[cartModalBtnIndexRef.current]) btns[cartModalBtnIndexRef.current].classList.add('focused');
          }
          if (e.key === 'Enter') {
              e.preventDefault();
              if (cartModalBtnIndexRef.current === 0) {
                  setCartModalOpen(false);
              } else {
                  if (cart.length > 0) handleSendToCaja();
              }
          }
          return;
      }

      if (modalOpen) {
          if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
              e.preventDefault();
              modalButtonIndexRef.current = modalButtonIndexRef.current === 0 ? 1 : 0;
              updateModalFocusDOM();
          }
          if (e.key === 'Enter') {
              e.preventDefault();
              if (modalButtonIndexRef.current === 0) {
                  setModalOpen(false);
              } else {
                  handleCreateOrder(selectedProduct);
              }
          }
          return;
      }

      let nextIndex = navIndexRef.current;
      
      if (nextIndex === -1) { // Estamos en el Header
          if (e.key === 'Enter') {
              e.preventDefault();
              setCartModalOpen(true);
              cartModalBtnIndexRef.current = 1;
              setTimeout(() => {
                const btns = document.querySelectorAll('.cart-modal-btn');
                if(btns[1]) btns[1].classList.add('focused');
              }, 50);
          } else if (e.key === 'ArrowDown') {
              e.preventDefault();
              nextIndex = 0;
              updateFocusDOM(nextIndex);
          }
          return; // Si estamos en -1 no hacemos navegación de grid lateral
      }

      const cols = 8;
      const isDrink = nextIndex >= foodCount;
      const localIdx = isDrink ? nextIndex - foodCount : nextIndex;
      const currentCol = localIdx % cols;
      const currentRow = Math.floor(localIdx / cols);

      switch(e.key) {
        case 'ArrowRight':
          e.preventDefault();
          if (currentCol < (cols - 1)) {
              nextIndex = Math.min(nextIndex + 1, (isDrink ? total - 1 : foodCount - 1));
          } else if (!isDrink) {
              let targetDrink = foodCount + (currentRow * cols);
              if (targetDrink < total) nextIndex = targetDrink;
          }
          break;
        case 'ArrowLeft':
          e.preventDefault();
          if (currentCol > 0) {
              nextIndex = Math.max(nextIndex - 1, (isDrink ? foodCount : 0));
          } else if (isDrink) {
              let targetFood = (currentRow * cols) + 7;
              if (targetFood < foodCount) nextIndex = targetFood;
              else nextIndex = foodCount - 1; 
          }
          break;
        case 'ArrowDown':
          e.preventDefault();
          let targetDown = nextIndex + cols;
          let maxSec = isDrink ? total - 1 : foodCount - 1;
          if (targetDown <= maxSec) nextIndex = targetDown;
          break;
        case 'ArrowUp':
          e.preventDefault();
          let targetUp = nextIndex - cols;
          let minSec = isDrink ? foodCount : 0;
          if (targetUp >= minSec) {
              nextIndex = targetUp;
          } else {
              // Brincar al carrito si subimos desde la fila 0
              if (currentRow === 0) {
                  nextIndex = -1;
              }
          }
          break;
        case 'Enter':
          e.preventDefault();
          setSelectedProduct(allProducts[nextIndex]);
          setModalOpen(true);
          modalButtonIndexRef.current = 1; // Default a Pedir
          setTimeout(updateModalFocusDOM, 50);
          break;
      }
      
      if (nextIndex !== navIndexRef.current) {
         updateFocusDOM(nextIndex);
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [allProducts, modalOpen, comboModalOpen, cartModalOpen, selectedProduct, foodCount, cart.length, updateFocusDOM, resetTimer]);

  const autoNormalizeImage = (img) => {
    if (!img.complete || img.naturalWidth === 0) return;
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = img.naturalWidth;
    canvas.height = img.naturalHeight;
    try {
        ctx.drawImage(img, 0, 0);
        const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        let minX = canvas.width, minY = canvas.height, maxX = 0, maxY = 0;
        let found = false;
        for (let y = 0; y < canvas.height; y++) {
            for (let x = 0; x < canvas.width; x++) {
                const i = (y * canvas.width + x) * 4;
                if (data[i+3] > 50 && (data[i] < 240 || data[i+1] < 240 || data[i+2] < 240)) {
                    if (x < minX) minX = x; if (x > maxX) maxX = x;
                    if (y < minY) minY = y; if (y > maxY) maxY = y;
                    found = true;
                }
            }
        }
        if (found) {
            const s = Math.min(canvas.width/(maxX-minX), canvas.height/(maxY-minY), 2.5) * 0.85;
            img.style.transform = `scale(${s})`;
        }
    } catch (e) { console.warn("CORS issue on image normalize"); }
  };

  if (loading) {
      return <div className="tv-container"><style>{TvStyles}</style><div style={{margin: 'auto'}}>SINTONIZANDO MENÚ...</div></div>;
  }

  const foodProducts = allProducts.slice(0, foodCount);
  const drinkProducts = allProducts.slice(foodCount);

  const cartTotal = cart.reduce((sum, item) => sum + Number(item.price), 0);

  return (
    <div className="tv-container">
      <style>{TvStyles}</style>

      <header id="main-header">
        <div className="header-left">
          <img src="/icon.ico" alt="La Ruta 11" className="logo-header" />
          <div className="header-text">La Ruta 11 Foodtrucks | <span style={{color: '#ff6b00'}}>Menú</span></div>
          {inactivityTimer <= 270 && inactivityTimer > 0 && cart.length > 0 && (
            <div className="inactivity-timer" style={{ marginLeft: '30px', textAlign: 'left', alignItems: 'flex-start' }}>
              <span>Borrando carrito en {Math.floor(inactivityTimer / 60).toString().padStart(2, '0')}:{(inactivityTimer % 60).toString().padStart(2, '0')}</span>
              <span className="inactivity-subtitle">Interactúa con el menú para reiniciar</span>
            </div>
          )}
        </div>
        <div className="header-cart-btn" data-index="-1">
          Ver Pre-pedido
          <div style={{background: 'white', color: 'black', padding: '5px 15px', borderRadius: '5px'}}>
            {cart.length} ítems / ${cartTotal.toLocaleString('es-CL')}
          </div>
        </div>
      </header>
      
      <div id="menu-container">
        <div className="layout-split">
          <div className="column column-left">
            <div className="products-grid">
              {foodProducts.map((p, index) => (
                <div 
                  key={p.id} 
                  className="product-card" 
                  data-index={index}
                >
                  {p.image_url ? (
                    <img src={p.image_url} alt={p.name} className="product-image" crossOrigin="anonymous" onLoad={(e) => autoNormalizeImage(e.target)} onError={(e) => { e.target.style.display='none'; e.target.nextSibling.style.display='flex'; }} />
                  ) : null}
                  <div className="product-image-placeholder" style={{ display: p.image_url ? 'none' : 'flex' }}>🍽️</div>
                  <div className="product-content">
                    <div className="product-name">{p.name}</div>
                    <div className="product-price">${Number(p.price).toLocaleString('es-CL')}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>
          
          <div className="column column-right">
            <div className="products-grid">
              {drinkProducts.map((p, index) => {
                const totalIndex = foodCount + index;
                return (
                  <div 
                    key={p.id} 
                    className="product-card card-drink" 
                    data-index={totalIndex}
                  >
                    {p.image_url ? (
                      <img src={p.image_url} alt={p.name} className="product-image" crossOrigin="anonymous" onLoad={(e) => autoNormalizeImage(e.target)} onError={(e) => { e.target.style.display='none'; e.target.nextSibling.style.display='flex'; }} />
                    ) : null}
                    <div className="product-image-placeholder" style={{ display: p.image_url ? 'none' : 'flex' }}>🍽️</div>
                    <div className="product-content">
                      <div className="product-price">${Number(p.price).toLocaleString('es-CL')}</div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </div>

      <footer id="main-footer">
        <div className="footer-panel">Hamburguesas, Completos, Sandwiches, Papitas fritas</div>
        <div className="footer-panel right">Elige tu bebida</div>
      </footer>

      {/* Modal Re-escrito para React */}
      {modalOpen && selectedProduct && !comboModalOpen && (
        <div className="fullscreen-modal" style={{zIndex: 9999}}>
          <div className="fullscreen-content">
            <div className="close-fullscreen" onClick={() => setModalOpen(false)} style={{position: 'absolute', top: '20px', right: '20px', fontSize: '40px', background: '#000', color: '#fff', width: '60px', height: '60px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor:'pointer', zIndex: 10}}>×</div>
            <div className="fullscreen-image-container">
              {selectedProduct.image_url ? (
                 <img src={selectedProduct.image_url} className="fullscreen-image" alt="" />
              ) : (
                 <div className="fullscreen-image" style={{display:'flex', alignItems:'center', justifyContent:'center', fontSize:'10vw', background:'#e2e8f0'}}>🍽️</div>
              )}
            </div>
            <div className="fullscreen-details">
              <div className="fullscreen-id">ID PRODUCTO: {selectedProduct.id}</div>
              <div className="fullscreen-name">{selectedProduct.name}</div>
              {selectedProduct.description && (
                  <div className="fullscreen-desc">{selectedProduct.description}</div>
              )}
              <div className="fullscreen-price">${Number(selectedProduct.price).toLocaleString('es-CL')}</div>
              
              <div className="modal-actions">
                <button className="modal-btn btn-secondary" onClick={() => setModalOpen(false)}>Seguir Mirando</button>
                <button 
                  className="modal-btn btn-primary focused" 
                  onClick={() => {
                    setCart(prev => [...prev, selectedProduct]);
                    setModalOpen(false);
                  }}
                >
                  Agregar al Carrito
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      <ComboModal
        combo={selectedProduct}
        isOpen={comboModalOpen}
        onClose={() => setComboModalOpen(false)}
        quantity={1}
        onAddToCart={(comboWithSelections) => {
          setCart(prev => [...prev, comboWithSelections]);
          setComboModalOpen(false);
          setModalOpen(false);
          setSelectedProduct(null);
        }}
      />

      {/* Cart Pre-Pedido Modal */}
      {cartModalOpen && (
        <div className="fullscreen-modal" style={{zIndex: 9999}}>
          <div className="fullscreen-content" style={{flexDirection: 'column', width: '70vw'}}>
            <div className="close-fullscreen" onClick={() => setCartModalOpen(false)} style={{position: 'absolute', top: '20px', right: '20px', fontSize: '40px', background: '#000', color: '#fff', width: '60px', height: '60px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor:'pointer', zIndex: 10}}>×</div>
            
            <div style={{padding: '30px', background: '#111827', color: 'white'}}>
                <h1 style={{fontSize: '2.5vw', fontWeight: 900, textTransform: 'uppercase'}}>Tu Pre-Pedido Actual</h1>
            </div>

            <div style={{flex: 1, padding: '30px', overflowY: 'auto'}}>
                {cart.length === 0 ? (
                    <div style={{fontSize: '2vw', color: '#666', textAlign: 'center', marginTop: '10%'}}>Tu bandeja está vacía.<br/>¡Navega y agrégale algo rico!</div>
                ) : (
                    cart.map((item, idx) => (
                        <div key={idx} style={{display: 'flex', justifyContent: 'space-between', padding: '15px 0', borderBottom: '2px solid #f1f5f9', fontSize: '1.5vw', fontWeight: 'bold'}}>
                            <div><span style={{color: '#ff6b00', marginRight: '10px'}}>{idx + 1}.</span> {item.name || "Combo Seleccionado"}</div>
                            <div style={{color: '#059669'}}>${Number(item.price).toLocaleString('es-CL')}</div>
                        </div>
                    ))
                )}
            </div>

            <div style={{padding: '30px', background: '#f8fafc', borderTop: '2px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                <div style={{fontSize: '2vw', fontWeight: 900}}>TOTAL: <span style={{color: '#ff6b00'}}>${cartTotal.toLocaleString('es-CL')}</span></div>
                
                <div style={{display: 'flex', gap: '20px'}}>
                    <button className="modal-btn btn-secondary cart-modal-btn">Seguir Mirando</button>
                    <button className="modal-btn btn-primary cart-modal-btn focused" style={{opacity: cart.length === 0 ? 0.3 : 1}}>Enviar a Caja</button>
                </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal Ticket */}
      {ticketOrderId && (
        <div style={{position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.85)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center'}}>
          <div style={{background: 'white', borderRadius: '24px', padding: '60px 80px', textAlign: 'center', maxWidth: '600px', width: '90%', boxShadow: '0 25px 60px rgba(0,0,0,0.4)'}}>
            <div style={{fontSize: '80px', marginBottom: '20px'}}>🎟️</div>
            <div style={{fontSize: '22px', color: '#64748b', marginBottom: '12px', fontWeight: 600}}>Tu número de orden es</div>
            <div style={{background: 'linear-gradient(135deg, #1d4ed8, #3b82f6)', borderRadius: '16px', padding: '30px 40px', marginBottom: '30px'}}>
              <div style={{fontSize: '80px', fontWeight: 900, color: 'white', letterSpacing: '-2px'}}>#{ticketOrderId}</div>
            </div>
            <div style={{fontSize: '24px', color: '#374151', marginBottom: '40px', lineHeight: 1.4}}>
              📸 Saca una foto<br/><span style={{color: '#6b7280', fontSize: '20px'}}>Este es tu número de orden 😊</span>
            </div>
            <div style={{fontSize: '18px', color: '#f97316', fontWeight: 700, marginBottom: '30px'}}>Acércate a caja para pagar</div>
            <button
              onClick={() => { setTicketOrderId(null); }}
              style={{background: '#1d4ed8', color: 'white', border: 'none', borderRadius: '12px', padding: '18px 60px', fontSize: '22px', fontWeight: 700, cursor: 'pointer'}}
            >
              Cerrar
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
