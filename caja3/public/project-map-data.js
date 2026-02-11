// Datos del proyecto La Ruta 11
const projectData = {
  pages: [
    {
      name: 'index.astro',
      path: '/src/pages/index.astro',
      icon: '🏠',
      type: 'user',
      description: 'Menú principal de la APP',
      apis: [
        '/api/app/track_visit.php',
        '/api/app/track_interaction.php',
        '/api/app/track_journey.php',
        '/api/get_productos.php'
      ],
      tables: ['visits', 'interactions', 'journey', 'productos'],
      component: 'MenuApp.jsx'
    },
    {
      name: 'checkout.astro',
      path: '/src/pages/checkout.astro',
      icon: '💳',
      type: 'user',
      description: 'Proceso de pago con TUU',
      apis: [
        '/api/tuu/create_payment_working.php',
        '/api/registrar_venta.php',
        '/api/process_sale_inventory.php'
      ],
      tables: ['tuu_orders', 'ventas', 'ingredientes'],
      component: 'CheckoutApp.jsx'
    },
    {
      name: 'admin/index.astro',
      path: '/src/pages/admin/index.astro',
      icon: '👨💼',
      type: 'admin',
      description: 'Panel administrativo principal',
      apis: [
        '/api/get_dashboard_kpis.php',
        '/api/get_productos.php',
        '/api/users/get_users.php',
        '/api/get_sales_analytics.php',
        '/api/get_dashboard_cards.php'
      ],
      tables: ['productos', 'usuarios', 'ventas', 'ingredientes', 'compras'],
      component: 'AdminSPA.jsx'
    },
    {
      name: 'admin/dashboard.astro',
      path: '/src/pages/admin/dashboard.astro',
      icon: '📊',
      type: 'admin',
      description: 'Dashboard de métricas',
      apis: [
        '/api/tuu/get_from_mysql.php',
        '/api/app/get_user_behavior.php',
        '/api/app/get_analytics.php'
      ],
      tables: ['tuu_orders', 'visits', 'interactions']
    },
    {
      name: 'compras.astro',
      path: '/src/pages/compras.astro',
      icon: '🛒',
      type: 'admin',
      description: 'Sistema de compras',
      apis: [
        '/api/compras/get_compras.php',
        '/api/compras/registrar_compra.php',
        '/api/compras/get_proveedores.php',
        '/api/compras/get_saldo_disponible.php'
      ],
      tables: ['compras', 'compras_items', 'proveedores', 'saldo_caja'],
      component: 'ComprasApp.jsx'
    },
    {
      name: 'ventas-detalle.astro',
      path: '/src/pages/ventas-detalle.astro',
      icon: '📈',
      type: 'admin',
      description: 'Detalle de ventas por turno',
      apis: [
        '/api/get_sales_detail.php',
        '/api/get_ventas_turno.php'
      ],
      tables: ['ventas', 'tuu_orders', 'productos'],
      component: 'VentasDetalle.jsx'
    },
    {
      name: 'admin/calidad.astro',
      path: '/src/pages/admin/calidad.astro',
      icon: '✅',
      type: 'admin',
      description: 'Control de calidad',
      apis: [
        '/api/get_questions.php',
        '/api/save_checklist.php',
        '/api/get_quality_score.php'
      ],
      tables: ['quality_questions', 'quality_checklists'],
      component: 'ChecklistApp.jsx'
    },
    {
      name: 'admin/ingredients.astro',
      path: '/src/pages/admin/ingredients.astro',
      icon: '🥬',
      type: 'admin',
      description: 'Gestión de ingredientes',
      apis: [
        '/api/get_ingredientes.php',
        '/api/save_ingrediente.php',
        '/api/update_ingrediente.php'
      ],
      tables: ['ingredientes', 'recetas']
    }
  ],
  apiGroups: [
    {
      name: 'Productos',
      icon: '📦',
      apis: [
        { name: 'get_productos.php', tables: ['productos', 'recetas', 'ingredientes'] },
        { name: 'add_producto.php', tables: ['productos'] },
        { name: 'update_producto.php', tables: ['productos'] },
        { name: 'get_product_recipe.php', tables: ['recetas', 'ingredientes'] }
      ]
    },
    {
      name: 'Ventas',
      icon: '💰',
      apis: [
        { name: 'registrar_venta.php', tables: ['ventas', 'ingredientes'] },
        { name: 'get_sales_analytics.php', tables: ['ventas', 'tuu_orders'] },
        { name: 'get_sales_detail.php', tables: ['ventas', 'tuu_orders'] },
        { name: 'process_sale_inventory.php', tables: ['ingredientes', 'recetas'] }
      ]
    },
    {
      name: 'TUU Pagos',
      icon: '💳',
      apis: [
        { name: 'tuu/create_payment_working.php', tables: ['tuu_orders'] },
        { name: 'tuu/callback.php', tables: ['tuu_orders'] },
        { name: 'tuu/get_from_mysql.php', tables: ['tuu_orders'] }
      ]
    },
    {
      name: 'Analytics',
      icon: '📊',
      apis: [
        { name: 'app/track_visit.php', tables: ['visits'] },
        { name: 'app/track_interaction.php', tables: ['interactions'] },
        { name: 'app/get_analytics.php', tables: ['visits', 'interactions'] }
      ]
    },
    {
      name: 'Compras',
      icon: '🛒',
      apis: [
        { name: 'compras/get_compras.php', tables: ['compras', 'compras_items'] },
        { name: 'compras/registrar_compra.php', tables: ['compras', 'ingredientes'] },
        { name: 'compras/get_proveedores.php', tables: ['proveedores'] }
      ]
    },
    {
      name: 'Dashboard',
      icon: '📈',
      apis: [
        { name: 'get_dashboard_kpis.php', tables: ['ventas', 'tuu_orders'] },
        { name: 'get_dashboard_cards.php', tables: ['compras', 'ingredientes'] }
      ]
    }
  ]
};

// Renderizar páginas
function renderPages(filter = 'all', search = '') {
  const grid = document.getElementById('pages-grid');
  let pages = projectData.pages;

  if (filter !== 'all') {
    pages = projectData.pages.filter(p => p.type === filter);
  }

  if (search) {
    pages = pages.filter(p => 
      p.name.toLowerCase().includes(search.toLowerCase()) ||
      p.description.toLowerCase().includes(search.toLowerCase())
    );
  }

  grid.innerHTML = pages.map(page => `
    <div class="card">
      <div class="card-header">
        <div class="card-icon">${page.icon}</div>
        <div>
          <div class="card-title">${page.name}</div>
          <div class="card-subtitle">${page.description}</div>
        </div>
      </div>
      <div style="margin-top: 1rem;">
        <span class="badge badge-page">${page.type === 'admin' ? 'Admin' : 'Usuario'}</span>
        <span class="badge badge-api">${page.apis.length} APIs</span>
        <span class="badge badge-table">${page.tables.length} Tablas</span>
      </div>
      <div class="apis-list">
        ${page.apis.slice(0, 3).map(api => `
          <div class="api-item">
            <span>🔌</span>
            <span>${api.split('/').pop()}</span>
          </div>
        `).join('')}
        ${page.apis.length > 3 ? `<div style="text-align: center; color: #94a3b8; font-size: 0.8rem; margin-top: 0.5rem;">+${page.apis.length - 3} más</div>` : ''}
      </div>
    </div>
  `).join('');
}

// Renderizar APIs
function renderAPIs(filter = 'all', search = '') {
  const grid = document.getElementById('apis-grid');
  let groups = projectData.apiGroups;

  if (search) {
    groups = groups.map(g => ({
      ...g,
      apis: g.apis.filter(api => 
        api.name.toLowerCase().includes(search.toLowerCase())
      )
    })).filter(g => g.apis.length > 0);
  }

  grid.innerHTML = groups.map(group => `
    <div class="card">
      <div class="card-header">
        <div class="card-icon">${group.icon}</div>
        <div>
          <div class="card-title">${group.name}</div>
          <div class="card-subtitle">${group.apis.length} endpoints</div>
        </div>
      </div>
      <div class="apis-list">
        ${group.apis.map(api => `
          <div class="api-item">
            <span>📡</span>
            <span>${api.name}</span>
          </div>
        `).join('')}
      </div>
    </div>
  `).join('');
}

// Event listeners
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const filter = btn.dataset.filter;
      const search = document.getElementById('search').value;
      renderPages(filter, search);
      renderAPIs(filter, search);
    });
  });

  document.getElementById('search').addEventListener('input', (e) => {
    const search = e.target.value;
    const filter = document.querySelector('.filter-btn.active').dataset.filter;
    renderPages(filter, search);
    renderAPIs(filter, search);
  });

  renderPages();
  renderAPIs();
});
