window.openProductEditModal = async function(productId = null) {
  const isNew = !productId;
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;padding:20px';
  
  const modal = document.createElement('div');
  modal.style.cssText = 'background:white;border-radius:12px;width:100%;max-width:900px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column';
  modal.innerHTML = '<div style="padding:40px;text-align:center">Cargando...</div>';
  
  overlay.appendChild(modal);
  document.body.appendChild(overlay);
  overlay.onclick = (e) => { if (e.target === overlay) overlay.remove(); };
  
  let product = { name: '', sku: '', description: '', price: '', cost_price: '', stock_quantity: 0, min_stock_level: 5, category_id: 1, is_active: 1 };
  
  if (!isNew) {
    try {
      const res = await fetch(`/api/get_productos.php?id=${productId}`);
      if (!res.ok) throw new Error('Error al cargar');
      const data = await res.json();
      if (data.error || data.message === 'Producto no encontrado') {
        alert('Producto no encontrado');
        overlay.remove();
        return;
      }
      product = data;
    } catch (err) {
      modal.innerHTML = '<div style="padding:40px;text-align:center;color:#dc2626">Error: ' + err.message + '</div>';
      setTimeout(() => overlay.remove(), 2000);
      return;
    }
  }
  
  const margin = product.price && product.cost_price ? Math.round((product.price - product.cost_price) / product.price * 100) : 0;
  const marginColor = margin >= 40 ? '#059669' : '#dc2626';
  
  modal.innerHTML = `
    <div style="padding:20px;border-bottom:1px solid #e5e5e5;display:flex;justify-content:space-between;align-items:center">
      <h2 style="margin:0;font-size:18px;font-weight:600">${isNew ? 'Nuevo Producto' : 'Editar Producto'}</h2>
      <button onclick="this.closest('div[style*=fixed]').remove()" style="background:none;border:none;font-size:24px;cursor:pointer;padding:0 8px">&times;</button>
    </div>
    <div style="display:flex;gap:2px;padding:0 20px;border-bottom:1px solid #e5e5e5;background:#f9fafb">
      <button id="tab1" onclick="switchTab(1)" style="padding:12px 20px;border:none;background:white;cursor:pointer;font-size:14px;font-weight:500;border-bottom:2px solid #0a0a0a">Básico</button>
      <button id="tab2" onclick="switchTab(2)" style="padding:12px 20px;border:none;background:transparent;cursor:pointer;font-size:14px;font-weight:500;border-bottom:2px solid transparent">Imágenes</button>
      <button id="tab3" onclick="switchTab(3)" style="padding:12px 20px;border:none;background:transparent;cursor:pointer;font-size:14px;font-weight:500;border-bottom:2px solid transparent">Ingredientes</button>
    </div>
    <div style="flex:1;overflow:auto;padding:20px">
      <div id="content1">
        <form id="productForm">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Nombre *</label>
            <input name="name" value="${product.name || ''}" required style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px"></div>
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">SKU</label>
            <input name="sku" value="${product.sku || ''}" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px"></div>
          </div>
          <div style="margin-bottom:12px"><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Descripción</label>
          <textarea name="description" rows="2" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px;resize:vertical">${product.description || ''}</textarea></div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px">
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Precio *</label>
            <input name="price" type="number" value="${product.price || ''}" required onchange="updateMargin()" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px"></div>
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Costo</label>
            <input name="cost_price" type="number" value="${product.cost_price || ''}" onchange="updateMargin()" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px"></div>
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Margen</label>
            <div id="marginDisplay" style="padding:8px;background:#f9fafb;border-radius:6px;font-size:14px;font-weight:600;color:${marginColor}">${margin}%</div></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Stock</label>
            <input name="stock_quantity" type="number" value="${product.stock_quantity || 0}" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px"></div>
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Stock Mínimo</label>
            <input name="min_stock_level" type="number" value="${product.min_stock_level || 5}" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px"></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Categoría</label>
            <select name="category_id" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px">
              <option value="1" ${product.category_id == 1 ? 'selected' : ''}>La Ruta 11</option>
              <option value="2" ${product.category_id == 2 ? 'selected' : ''}>Sandwiches</option>
              <option value="3" ${product.category_id == 3 ? 'selected' : ''}>Hamburguesas</option>
              <option value="4" ${product.category_id == 4 ? 'selected' : ''}>Completos</option>
              <option value="5" ${product.category_id == 5 ? 'selected' : ''}>Snacks</option>
              <option value="6" ${product.category_id == 6 ? 'selected' : ''}>Personalizar</option>
              <option value="7" ${product.category_id == 7 ? 'selected' : ''}>Extras</option>
              <option value="8" ${product.category_id == 8 ? 'selected' : ''}>Combos</option>
              <option value="12" ${product.category_id == 12 ? 'selected' : ''}>Papas</option>
            </select></div>
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Estado</label>
            <select name="is_active" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px">
              <option value="1" ${(product.is_active === undefined ? 1 : product.is_active) == 1 ? 'selected' : ''}>Activo</option>
              <option value="0" ${product.is_active == 0 ? 'selected' : ''}>Inactivo</option>
            </select></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px">
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Tiempo Prep (min)</label>
            <input name="preparation_time" type="number" value="${product.preparation_time || ''}" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px"></div>
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Gramos</label>
            <input name="grams" type="number" value="${product.grams || ''}" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px"></div>
            <div><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Calorías</label>
            <input name="calories" type="number" value="${product.calories || ''}" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px"></div>
          </div>
          <div style="margin-bottom:12px"><label style="display:block;margin-bottom:4px;font-weight:500;font-size:13px">Alérgenos</label>
          <input name="allergens" value="${product.allergens || ''}" placeholder="Ej: gluten, lácteos, frutos secos" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px"></div>
          ${!isNew ? `<input type="hidden" name="id" value="${product.id}">` : ''}
        </form>
      </div>
      <div id="content2" style="display:none">
        <div style="margin-bottom:16px">
          <h3 style="margin:0 0 8px 0;font-size:14px;font-weight:600">1. Imágenes Actuales</h3>
          <div id="currentImages" style="display:flex;gap:8px;flex-wrap:wrap;padding:12px;border:1px solid #e5e5e5;border-radius:6px;min-height:80px">
            <div style="color:#999;font-size:13px">Cargando...</div>
          </div>
        </div>
        <div style="margin-bottom:16px">
          <h3 style="margin:0 0 8px 0;font-size:14px;font-weight:600">2. Subir Nueva Imagen</h3>
          <div style="border:2px dashed #e5e5e5;border-radius:6px;padding:20px;text-align:center">
            <input type="file" id="imageInput" accept="image/*" style="display:none">
            <button type="button" onclick="document.getElementById('imageInput').click()" style="padding:10px 20px;background:#0a0a0a;color:white;border:none;border-radius:6px;cursor:pointer;font-size:14px">📎 Seleccionar Imagen</button>
            <p style="font-size:11px;color:#666;margin-top:8px">JPG, PNG, GIF, WEBP (máx. 10MB)</p>
          </div>
          <div id="imagePreview" style="margin-top:12px;display:none">
            <img id="previewImg" style="max-width:200px;border-radius:6px;border:1px solid #e5e5e5">
            <div style="margin-top:8px">
              <button type="button" onclick="uploadImage()" style="padding:8px 16px;background:#059669;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px">☁️ Subir a AWS S3</button>
            </div>
          </div>
          <div id="uploadProgress" style="display:none;margin-top:12px">
            <div style="background:#f5f5f5;border-radius:4px;height:8px;overflow:hidden">
              <div id="progressBar" style="background:#0a0a0a;height:100%;width:0%;transition:width 0.3s"></div>
            </div>
            <p id="uploadStatus" style="font-size:11px;color:#666;margin-top:4px">Subiendo...</p>
          </div>
        </div>
      </div>
      <div id="content3" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
          <div>
            <h3 style="margin:0 0 12px 0;font-size:14px;font-weight:600">📝 Receta Actual</h3>
            <div id="recipeList" style="border:1px solid #e5e5e5;border-radius:6px;padding:12px;min-height:200px;max-height:400px;overflow:auto">
              <div style="text-align:center;color:#999;padding:20px">Cargando...</div>
            </div>
          </div>
          <div>
            <h3 style="margin:0 0 12px 0;font-size:14px;font-weight:600">➕ Agregar Ingrediente</h3>
            <div style="display:flex;flex-direction:column;gap:12px">
              <div style="position:relative">
                <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:500">Ingrediente *</label>
                <input type="text" id="ingredientSearch" placeholder="Buscar ingrediente..." autocomplete="off" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px">
                <input type="hidden" id="selectedIngredientId">
                <div id="ingredientDropdown" style="position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #e5e5e5;border-top:none;border-radius:0 0 6px 6px;max-height:200px;overflow-y:auto;z-index:100;display:none"></div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                  <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:500">Cantidad *</label>
                  <input id="ingredientQty" type="number" step="0.01" placeholder="100" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px">
                </div>
                <div>
                  <label style="display:block;margin-bottom:4px;font-size:13px;font-weight:500">Unidad</label>
                  <select id="ingredientUnit" style="width:100%;padding:8px;border:1px solid #e5e5e5;border-radius:6px;font-size:14px">
                    <option value="g">Gramos (g)</option>
                    <option value="kg">Kilogramos (kg)</option>
                    <option value="ml">Mililitros (ml)</option>
                    <option value="l">Litros (l)</option>
                    <option value="unidad">Unidad</option>
                    <option value="cucharada">Cucharada</option>
                    <option value="taza">Taza</option>
                  </select>
                </div>
              </div>
              <button onclick="addIngredientToRecipe()" style="padding:10px;background:#0a0a0a;color:white;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:500">➕ Agregar a la Receta</button>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px">
                <button onclick="calculateCost()" style="padding:8px;background:#059669;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px">📊 Calcular Costo</button>
                <button onclick="applyCost()" style="padding:8px;background:#3b82f6;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px">💰 Aplicar Costo</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div style="padding:16px 20px;border-top:1px solid #e5e5e5;display:flex;gap:12px;justify-content:flex-end;background:#f9fafb">
      <button type="button" onclick="this.closest('div[style*=fixed]').remove()" style="padding:8px 16px;border:1px solid #e5e5e5;background:white;border-radius:6px;cursor:pointer;font-size:14px;font-weight:500">Cancelar</button>
      <button onclick="saveProduct()" style="padding:8px 16px;background:#0a0a0a;color:white;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:500">${isNew ? 'Crear' : 'Guardar'}</button>
    </div>
  `;
  
  window.switchTab = (tab) => {
    [1,2,3].forEach(i => {
      document.getElementById(`tab${i}`).style.background = i === tab ? 'white' : 'transparent';
      document.getElementById(`tab${i}`).style.borderBottomColor = i === tab ? '#0a0a0a' : 'transparent';
      document.getElementById(`content${i}`).style.display = i === tab ? 'block' : 'none';
    });
    if (tab === 2 && !isNew) loadImages();
    if (tab === 3 && !isNew) loadIngredients();
  };
  
  window.loadImages = async () => {
    try {
      const res = await fetch(`/api/get_product_images.php?product_id=${productId}&t=${Date.now()}`);
      const data = await res.json();
      const container = document.getElementById('currentImages');
      const images = data.success ? data.images : [];
      
      if (images.length === 0) {
        container.innerHTML = '<div style="color:#999;font-size:13px">Sin imágenes</div>';
      } else {
        container.innerHTML = images.map(img => `
          <div style="position:relative">
            <img src="${img.url}" style="width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #e5e5e5;cursor:pointer" onclick="window.open('${img.url}','_blank')">
            <button onclick="deleteImage('${img.url}')" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:#ef4444;color:white;border:none;border-radius:50%;cursor:pointer;font-size:10px">×</button>
          </div>
        `).join('');
      }
    } catch (err) {
      console.error('Error cargando imágenes:', err);
      document.getElementById('currentImages').innerHTML = '<div style="color:#dc2626;font-size:13px">Error al cargar</div>';
    }
  };
  
  document.getElementById('imageInput').onchange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    if (!['image/jpeg','image/jpg','image/png','image/gif','image/webp'].includes(file.type)) {
      alert('Tipo de archivo no válido');
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      alert('Archivo muy grande (máx 10MB)');
      return;
    }
    
    const reader = new FileReader();
    reader.onload = (e) => {
      document.getElementById('previewImg').src = e.target.result;
      document.getElementById('imagePreview').style.display = 'block';
      window.selectedFile = file;
    };
    reader.readAsDataURL(file);
  };
  
  window.uploadImage = async () => {
    if (!window.selectedFile) return;
    
    const formData = new FormData();
    formData.append('image', window.selectedFile);
    formData.append('product_id', productId);
    
    document.getElementById('uploadProgress').style.display = 'block';
    document.getElementById('uploadStatus').textContent = 'Subiendo a AWS S3...';
    
    try {
      const res = await fetch('/api/upload_image.php', { method: 'POST', body: formData });
      const result = await res.json();
      
      if (result.success) {
        document.getElementById('progressBar').style.width = '100%';
        document.getElementById('uploadStatus').textContent = '✅ Subida exitosa';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('uploadProgress').style.display = 'none';
        document.getElementById('imageInput').value = '';
        window.selectedFile = null;
        loadImages();
      } else {
        alert('Error: ' + result.error);
        document.getElementById('uploadProgress').style.display = 'none';
      }
    } catch (err) {
      alert('Error al subir');
      document.getElementById('uploadProgress').style.display = 'none';
    }
  };
  
  window.deleteImage = async (url) => {
    if (!confirm('¿Eliminar imagen?')) return;
    try {
      const formData = new FormData();
      formData.append('product_id', productId);
      formData.append('image_url', url);
      
      const res = await fetch('/api/delete_image_from_gallery.php', {
        method: 'POST',
        body: formData
      });
      const result = await res.json();
      if (result.success) {
        alert(`Imagen eliminada. Quedan ${result.image_count} imagen(es)`);
        loadImages();
      } else {
        alert('Error: ' + result.error);
      }
    } catch (err) {
      alert('Error al eliminar');
    }
  };
  
  window.loadIngredients = async () => {
    try {
      const [ingredientsRes, recipeRes] = await Promise.all([
        fetch(`/api/get_ingredientes.php?t=${Date.now()}`),
        fetch(`/api/get_product_recipe.php?product_id=${productId}&t=${Date.now()}`)
      ]);
      const ingredients = await ingredientsRes.json();
      const recipeData = await recipeRes.json();
      
      window.allIngredients = Array.isArray(ingredients) ? ingredients : [];
      window.currentRecipe = recipeData.success ? recipeData.recipe : [];
      
      console.log('Ingredientes cargados:', window.allIngredients.length);
      console.log('Receta actual:', window.currentRecipe);
      
      setupIngredientSearch();
      displayRecipe();
    } catch (err) {
      console.error('Error cargando ingredientes:', err);
      document.getElementById('recipeList').innerHTML = '<div style="text-align:center;color:#dc2626;padding:20px">Error al cargar</div>';
    }
  };
  
  window.setupIngredientSearch = () => {
    const search = document.getElementById('ingredientSearch');
    const dropdown = document.getElementById('ingredientDropdown');
    
    search.oninput = () => {
      const term = search.value.toLowerCase();
      if (term.length < 2) {
        dropdown.style.display = 'none';
        return;
      }
      
      const filtered = window.allIngredients.filter(i => 
        i.name.toLowerCase().includes(term)
      ).slice(0, 10);
      
      if (filtered.length === 0) {
        dropdown.innerHTML = '<div style="padding:8px;color:#999;font-size:13px">Sin resultados</div>';
      } else {
        dropdown.innerHTML = filtered.map(i => `
          <div onclick="selectIngredient(${i.id}, '${i.name.replace(/'/g, "\\'")}')"
               style="padding:8px;cursor:pointer;font-size:13px;border-bottom:1px solid #f5f5f5"
               onmouseover="this.style.background='#f9fafb'"
               onmouseout="this.style.background='white'">
            ${i.name} <span style="color:#666;font-size:11px">($${i.cost_per_unit}/${i.unit})</span>
          </div>
        `).join('');
      }
      dropdown.style.display = 'block';
    };
    
    search.onfocus = () => { if (search.value.length >= 2) dropdown.style.display = 'block'; };
    document.addEventListener('click', (e) => {
      if (!search.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
    });
  };
  
  window.selectIngredient = (id, name) => {
    document.getElementById('selectedIngredientId').value = id;
    document.getElementById('ingredientSearch').value = name;
    document.getElementById('ingredientDropdown').style.display = 'none';
  };
  
  window.displayRecipe = () => {
    const list = document.getElementById('recipeList');
    if (!window.currentRecipe || window.currentRecipe.length === 0) {
      list.innerHTML = '<div style="text-align:center;color:#999;padding:20px">Sin ingredientes<br><small>Agrega usando el formulario</small></div>';
    } else {
      list.innerHTML = window.currentRecipe.map((item, idx) => `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px;border-bottom:1px solid #f5f5f5">
          <div>
            <div style="font-weight:500;font-size:13px">${item.name}</div>
            <div style="font-size:11px;color:#666">${item.quantity} ${item.unit}</div>
          </div>
          <button onclick="removeIngredient(${idx})" style="background:#ef4444;color:white;border:none;padding:4px 8px;border-radius:4px;cursor:pointer;font-size:11px">×</button>
        </div>
      `).join('');
    }
  };
  
  window.addIngredientToRecipe = () => {
    const ingredientId = document.getElementById('selectedIngredientId').value;
    const ingredientName = document.getElementById('ingredientSearch').value;
    const quantity = document.getElementById('ingredientQty').value;
    const unit = document.getElementById('ingredientUnit').value;
    
    if (!ingredientId || !quantity) {
      alert('Selecciona ingrediente y cantidad');
      return;
    }
    
    if (!window.currentRecipe) window.currentRecipe = [];
    
    const existing = window.currentRecipe.findIndex(i => i.ingredient_id == ingredientId);
    if (existing >= 0) {
      window.currentRecipe[existing].quantity = parseFloat(quantity);
      window.currentRecipe[existing].unit = unit;
    } else {
      window.currentRecipe.push({
        ingredient_id: ingredientId,
        name: ingredientName,
        quantity: parseFloat(quantity),
        unit: unit
      });
    }
    
    document.getElementById('ingredientSearch').value = '';
    document.getElementById('selectedIngredientId').value = '';
    document.getElementById('ingredientQty').value = '';
    displayRecipe();
    saveRecipeToAPI();
  };
  
  window.removeIngredient = (idx) => {
    if (!confirm('¿Eliminar ingrediente?')) return;
    window.currentRecipe.splice(idx, 1);
    displayRecipe();
    saveRecipeToAPI();
  };
  
  window.saveRecipeToAPI = async () => {
    try {
      const res = await fetch('/api/save_product_recipe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          product_id: productId,
          ingredients: window.currentRecipe.map(item => ({
            ingredient_id: item.ingredient_id,
            quantity: item.quantity,
            unit: item.unit
          }))
        })
      });
      const result = await res.json();
      if (!result.success) console.error('Error guardando receta:', result.error);
    } catch (err) {
      console.error('Error:', err);
    }
  };
  
  window.calculateCost = async () => {
    try {
      const res = await fetch(`/api/calculate_product_cost.php?product_id=${productId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'update_product=0'
      });
      const result = await res.json();
      if (result.success && result.total_cost > 0) {
        alert(`Costo calculado: $${Math.round(result.total_cost)}\n${result.ingredients_count} ingredientes\n\nUsa "Aplicar Costo" para guardarlo.`);
        window.calculatedCost = result.total_cost;
      } else {
        alert(result.message || 'No se pudo calcular');
      }
    } catch (err) {
      alert('Error al calcular');
    }
  };
  
  window.applyCost = async () => {
    try {
      const res = await fetch(`/api/calculate_product_cost.php?product_id=${productId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'update_product=1'
      });
      const result = await res.json();
      if (result.success && result.total_cost > 0) {
        const form = document.getElementById('productForm');
        form.cost_price.value = Math.round(result.total_cost);
        updateMargin();
        alert('Costo aplicado: $' + Math.round(result.total_cost) + '\nRecuerda guardar el producto.');
      } else {
        alert(result.message || 'Error al aplicar');
      }
    } catch (err) {
      alert('Error al aplicar');
    }
  };
  
  window.updateMargin = () => {
    const form = document.getElementById('productForm');
    const price = parseFloat(form.price.value) || 0;
    const cost = parseFloat(form.cost_price.value) || 0;
    const margin = price && cost ? Math.round((price - cost) / price * 100) : 0;
    const display = document.getElementById('marginDisplay');
    display.textContent = margin + '%';
    display.style.color = margin >= 40 ? '#059669' : '#dc2626';
  };
  
  window.saveProduct = async () => {
    const form = document.getElementById('productForm');
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    
    const formData = new FormData(form);
    const endpoint = isNew ? '/api/add_producto.php' : '/api/update_producto.php';
    
    try {
      const res = await fetch(endpoint, { method: 'POST', body: formData });
      const result = await res.json();
      
      if (result.success) {
        alert(isNew ? 'Producto creado exitosamente' : 'Producto actualizado exitosamente');
        overlay.remove();
        if (window.loadProducts) window.loadProducts();
        else window.location.reload();
      } else {
        alert('Error: ' + (result.error || result.message || 'Error desconocido'));
      }
    } catch (err) {
      console.error('Error guardando producto:', err);
      alert('Error al guardar: ' + err.message);
    }
  };
};

window.addProduct = () => window.openProductEditModal();
