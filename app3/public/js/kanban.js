// Kanban JavaScript

let kanbanData = { columns: [], cards: [] };

// Initialize
document.addEventListener('DOMContentLoaded', async function() {
    document.getElementById('auth-loading').classList.add('hidden');
    loadKanbanData();
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    const refreshBtn = document.getElementById('refresh-btn');
    const mobileRefreshBtn = document.getElementById('mobile-refresh-btn');
    const syncBtn = document.getElementById('sync-btn');
    const kanbanViewBtn = document.getElementById('kanban-view-btn');
    const listViewBtn = document.getElementById('list-view-btn');
    
    if (refreshBtn) refreshBtn.addEventListener('click', loadKanbanData);
    if (mobileRefreshBtn) mobileRefreshBtn.addEventListener('click', loadKanbanData);
    if (syncBtn) syncBtn.addEventListener('click', syncKanbanStatus);
    
    const debugBtn = document.getElementById('debug-btn');
    if (debugBtn) debugBtn.addEventListener('click', debugKanbanStatus);
    
    // Mobile view toggles
    if (kanbanViewBtn) kanbanViewBtn.addEventListener('click', () => switchView('kanban'));
    if (listViewBtn) listViewBtn.addEventListener('click', () => switchView('list'));
    
    // Mobile column scroll indicators
    const mobileColumns = document.getElementById('kanban-columns-mobile');
    if (mobileColumns) {
        mobileColumns.addEventListener('scroll', updateColumnIndicators);
    }
}

// Load kanban data
async function loadKanbanData() {
    try {
        const loadingEl = document.getElementById('loading');
        const boardEl = document.getElementById('kanban-board');
        if (loadingEl) loadingEl.classList.remove('hidden');
        if (boardEl) boardEl.classList.add('hidden');
        
        const timestamp = Date.now();
        const response = await fetch(`/api/tracker/get_kanban.php?_t=${timestamp}`);
        const data = await response.json();
        
        if (data.success) {
            kanbanData = data.data;
            renderKanban();
        } else {
            console.error('Error loading kanban:', data.error);
        }
        
    } catch (error) {
        console.error('Error loading kanban:', error);
    } finally {
        const loadingEl = document.getElementById('loading');
        const boardEl = document.getElementById('kanban-board');
        if (loadingEl) loadingEl.classList.add('hidden');
        if (boardEl) boardEl.classList.remove('hidden');
    }
}

// Render kanban board
function renderKanban() {
    // Render desktop version
    const desktopContainer = document.getElementById('kanban-columns-desktop');
    if (desktopContainer) {
        desktopContainer.innerHTML = kanbanData.columns.map(column => {
            const columnCards = kanbanData.cards.filter(card => card.column_id == column.id);
            return `
                <div class="kanban-column bg-gray-100 rounded-lg p-4 min-w-80 flex-shrink-0" 
                     data-column-id="${column.id}"
                     ondrop="handleDrop(event)" 
                     ondragover="handleDragOver(event)">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900 flex items-center">
                            <div class="w-3 h-3 rounded-full mr-2" style="background-color: ${column.color}"></div>
                            ${column.name}
                            <span class="ml-2 bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded-full">${columnCards.length}</span>
                        </h3>
                    </div>
                    <div class="space-y-3">
                        ${columnCards.map(card => renderCard(card)).join('')}
                    </div>
                </div>
            `;
        }).join('');
    }
    
    // Render mobile version
    const mobileContainer = document.getElementById('kanban-columns-mobile');
    if (mobileContainer) {
        mobileContainer.innerHTML = kanbanData.columns.map(column => {
            const columnCards = kanbanData.cards.filter(card => card.column_id == column.id);
            return `
                <div class="mobile-column bg-gray-100 rounded-lg p-3 mr-4" 
                     data-column-id="${column.id}">
                    <div class="sticky top-0 bg-gray-100 pb-3 mb-3 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900 flex items-center text-sm">
                            <div class="w-2 h-2 rounded-full mr-2" style="background-color: ${column.color}"></div>
                            ${column.name}
                            <span class="ml-2 bg-white text-gray-700 text-xs px-2 py-1 rounded-full shadow-sm">${columnCards.length}</span>
                        </h3>
                    </div>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        ${columnCards.map(card => renderMobileCard(card)).join('')}
                    </div>
                </div>
            `;
        }).join('');
    }
    
    renderListView();
    setupColumnIndicators();
}

// Render individual card (same style as dashboard)
function renderCard(card) {
    console.log('Card data:', card); // Debug
    const positionName = card.position === 'maestro_sanguchero' ? 'Maestro Sanguchero' : 'Cajero';
    const statusBadge = getStatusBadge(card.status);
    const date = new Date(card.last_attempt || card.created_at).toLocaleDateString('es-CL');
    
    return `
        <div class="kanban-card bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow"
             draggable="true"
             data-card-id="${card.id}"
             ondragstart="handleDragStart(event)">
            
            <div class="flex items-start space-x-3">
                <img class="w-12 h-12 rounded-full flex-shrink-0" src="${card.foto_perfil || '/icon.png'}" alt="${card.nombre}">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            <div class="flex items-center gap-1">
                                <h4 class="text-base font-medium text-gray-900 truncate">${card.nombre}</h4>
                                <span class="text-base" title="${card.nacionalidad || 'Nacionalidad no especificada'}">${getFlagEmoji(card.nacionalidad)}</span>
                                ${getInterviewStatusBadge(card.user_id || card.id)}
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${card.position === 'maestro_sanguchero' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'}">
                            ${positionName}
                        </span>
                    </div>
                    
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-center">
                            ${getScoreBadge(card.best_score || card.score)}
                            <div class="text-xs text-gray-500">${card.total_attempts || 1} intento${(card.total_attempts || 1) > 1 ? 's' : ''}</div>
                        </div>
                        <div class="text-right">
                            ${statusBadge}
                            <div class="text-xs text-gray-500 mt-1">${date}</div>
                        </div>
                    </div>
                    
                    <!-- Estado del proceso -->
                    <div class="mb-3 p-2 bg-gray-50 rounded border-l-4 border-blue-400">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-gray-500">Estado:</span>
                            <span class="text-xs font-bold text-blue-600">
                                ${card.kanban_status || 'nuevo'}
                            </span>
                        </div>
                        <div class="flex gap-1">
                            <button onclick="changeStatus('${card.user_id || card.id}', 'nuevo')" class="reaction-btn ${(card.kanban_status || 'nuevo') === 'nuevo' ? 'active' : ''}">
                                📝
                            </button>
                            <button onclick="changeStatus('${card.user_id || card.id}', 'revisando')" class="reaction-btn ${card.kanban_status === 'revisando' ? 'active' : ''}">
                                👁️
                            </button>
                            <button onclick="changeStatus('${card.user_id || card.id}', 'entrevista')" class="reaction-btn ${card.kanban_status === 'entrevista' ? 'active' : ''}">
                                🗣️
                            </button>
                            <button onclick="changeStatus('${card.user_id || card.id}', 'contratado')" class="reaction-btn ${card.kanban_status === 'contratado' ? 'active' : ''}">
                                ✅
                            </button>
                            <button onclick="changeStatus('${card.user_id || card.id}', 'rechazado')" class="reaction-btn ${card.kanban_status === 'rechazado' ? 'active' : ''}">
                                ❌
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <a href="tel:${card.telefono}" class="text-xs text-blue-600 hover:text-blue-800 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                Llamar
                            </a>
                            ${card.instagram ? `<a href="https://instagram.com/${card.instagram}" target="_blank" class="text-xs text-pink-600 hover:text-pink-800 flex items-center">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.40s-.644-1.44-1.439-1.44z"/>
                                </svg>
                                IG
                            </a>` : ''}
                        </div>
                        <div class="flex gap-2">
                            <button onclick="viewCandidate('${card.user_id || card.id}', '${card.position}')" class="flex-1 text-xs bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700">Ver Detalle</button>
                            ${getInterviewButton(card.user_id || card.id, card.position)}
                            <button onclick="sendNotification('${card.user_id || card.id}')" class="text-xs bg-purple-600 text-white px-3 py-2 rounded hover:bg-purple-700">
                                📧
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Get status badge
function getStatusBadge(status) {
    const badges = {
        'completed': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completada</span>',
        'started': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">En Proceso</span>'
    };
    return badges[status] || '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Desconocido</span>';
}

// Get score badge
function getScoreBadge(score) {
    if (!score || score === 0) {
        return '<span class="text-sm text-gray-400">-</span>';
    }
    
    const scoreNum = Math.round(score);
    let colorClass = 'text-gray-600';
    
    if (scoreNum >= 80) colorClass = 'text-green-600';
    else if (scoreNum >= 60) colorClass = 'text-blue-600';
    else if (scoreNum >= 40) colorClass = 'text-yellow-600';
    else if (scoreNum >= 20) colorClass = 'text-orange-600';
    else colorClass = 'text-red-600';
    
    return `<span class="text-sm font-medium ${colorClass}">${scoreNum}%</span>`;
}

// Get score color (for mobile cards)
function getScoreColor(score) {
    if (!score || score === 0) return 'text-gray-400';
    
    const scoreNum = Math.round(score);
    if (scoreNum >= 80) return 'text-green-600';
    else if (scoreNum >= 60) return 'text-blue-600';
    else if (scoreNum >= 40) return 'text-yellow-600';
    else if (scoreNum >= 20) return 'text-orange-600';
    else return 'text-red-600';
}

// Get interview status badge
function getInterviewStatusBadge(candidateId) {
    // This would need to be populated from interview data
    return '';
}

// Get interview button
function getInterviewButton(candidateId, position) {
    return `<button onclick="startInterview('${candidateId}', '${position}')" class="text-xs bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700" title="Iniciar Entrevista">
                Entrevista
            </button>`;
}

// Get Kanban status text
function getKanbanStatusText(status) {
    const texts = {
        'nuevo': 'Nuevo',
        'revisando': 'Revisando', 
        'entrevista': 'Entrevista',
        'contratado': 'Contratado',
        'rechazado': 'Rechazado'
    };
    return texts[status] || 'Nuevo';
}

// Get status border color
function getStatusBorderColor(status) {
    const colors = {
        'nuevo': 'border-blue-400',
        'revisando': 'border-yellow-400',
        'entrevista': 'border-purple-400',
        'contratado': 'border-green-400',
        'rechazado': 'border-red-400'
    };
    return colors[status] || 'border-blue-400';
}

// Get status text color
function getStatusTextColor(status) {
    const colors = {
        'nuevo': 'text-blue-600',
        'revisando': 'text-yellow-600',
        'entrevista': 'text-purple-600',
        'contratado': 'text-green-600',
        'rechazado': 'text-red-600'
    };
    return colors[status] || 'text-blue-600';
}

// Get progress percentage
function getProgressPercentage(status) {
    const percentages = {
        'nuevo': 25,
        'revisando': 50,
        'entrevista': 75,
        'contratado': 100,
        'rechazado': 100
    };
    return percentages[status] || 25;
}

// Get progress bar color
function getProgressBarColor(status) {
    const colors = {
        'nuevo': 'bg-blue-500',
        'revisando': 'bg-yellow-500',
        'entrevista': 'bg-purple-500',
        'contratado': 'bg-green-500',
        'rechazado': 'bg-red-500'
    };
    return colors[status] || 'bg-blue-500';
}

// Get step state (for showing completed/inactive steps)
function getStepState(stepStatus, currentStatus) {
    const statusOrder = ['nuevo', 'revisando', 'entrevista', 'contratado', 'rechazado'];
    const stepIndex = statusOrder.indexOf(stepStatus);
    const currentIndex = statusOrder.indexOf(currentStatus);
    
    if (currentIndex > stepIndex) {
        return 'completed';
    }
    return 'inactive';
}



// Get flag emoji for nationality
function getFlagEmoji(nationality) {
    const flags = {
        'chilena': '🇨🇱',
        'chile': '🇨🇱',
        'argentina': '🇦🇷',
        'peruana': '🇵🇪',
        'peru': '🇵🇪',
        'boliviana': '🇧🇴',
        'bolivia': '🇧🇴',
        'colombiana': '🇨🇴',
        'colombia': '🇨🇴',
        'venezolana': '🇻🇪',
        'venezuela': '🇻🇪',
        'ecuatoriana': '🇪🇨',
        'ecuador': '🇪🇨',
        'brasileña': '🇧🇷',
        'brasil': '🇧🇷',
        'uruguaya': '🇺🇾',
        'uruguay': '🇺🇾',
        'paraguaya': '🇵🇾',
        'paraguay': '🇵🇾',
        'española': '🇪🇸',
        'españa': '🇪🇸',
        'italiana': '🇮🇹',
        'italia': '🇮🇹',
        'francesa': '🇫🇷',
        'francia': '🇫🇷',
        'alemana': '🇩🇪',
        'alemania': '🇩🇪',
        'estadounidense': '🇺🇸',
        'estados unidos': '🇺🇸',
        'canadiense': '🇨🇦',
        'canada': '🇨🇦',
        'mexicana': '🇲🇽',
        'mexico': '🇲🇽',
        'haitiana': '🇭🇹',
        'haiti': '🇭🇹'
    };
    
    if (!nationality) return '';
    
    const key = nationality.toLowerCase().trim();
    return flags[key] || '🌍';
}

// Drag and drop handlers
let draggedCard = null;

function handleDragStart(event) {
    draggedCard = event.target;
    event.dataTransfer.effectAllowed = 'move';
}

function handleDragOver(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    
    const column = event.currentTarget;
    column.classList.add('drag-over');
    
    // Crear preview de aterrizaje si no existe
    if (!column.querySelector('.drop-preview')) {
        const preview = document.createElement('div');
        preview.className = 'drop-preview';
        preview.innerHTML = '📍 Soltar aquí';
        
        // Insertar al final de las tarjetas
        const cardsContainer = column.querySelector('.space-y-3');
        if (cardsContainer) {
            cardsContainer.appendChild(preview);
        }
    }
}

function handleDrop(event) {
    event.preventDefault();
    const column = event.currentTarget;
    column.classList.remove('drag-over');
    
    // Remover preview
    const preview = column.querySelector('.drop-preview');
    if (preview) {
        preview.remove();
    }
    
    if (!draggedCard) return;
    
    const columnId = column.dataset.columnId;
    const cardId = draggedCard.dataset.cardId;
    
    if (columnId && cardId) {
        moveCard(cardId, columnId);
    }
    
    draggedCard = null;
}

// Move card via API
async function moveCard(cardId, toColumnId) {
    try {
        const response = await fetch('/api/tracker/move_kanban_card.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                card_id: cardId,
                to_column_id: toColumnId,
                new_position: 0
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadKanbanData(); // Reload to reflect changes
        } else {
            console.error('Error moving card:', data.error);
        }
        
    } catch (error) {
        console.error('Error moving card:', error);
    }
}

// Render mobile card (compact)
function renderMobileCard(card) {
    const positionName = card.position === 'maestro_sanguchero' ? 'Maestro' : 'Cajero';
    const scoreColor = getScoreColor(card.best_score);
    
    return `
        <div class="bg-white rounded-lg shadow-sm border p-3">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <img src="${card.foto_perfil || '/icon.png'}" alt="${card.nombre}" class="w-6 h-6 rounded-full flex-shrink-0">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1">
                            <h4 class="font-medium text-gray-900 line-clamp-1" style="font-size: clamp(0.75rem, 2vw, 0.875rem);">${card.nombre}</h4>
                            <span style="font-size: clamp(0.75rem, 2vw, 0.875rem);" title="${card.nacionalidad || 'Nacionalidad no especificada'}">${getFlagEmoji(card.nacionalidad)}</span>
                        </div>
                        <p class="text-gray-500 line-clamp-1" style="font-size: clamp(0.625rem, 1.5vw, 0.75rem);">${positionName}</p>
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="font-bold ${scoreColor}" style="font-size: clamp(0.875rem, 2.5vw, 1rem);">${card.best_score || 0}%</div>
                </div>
            </div>
            
            <!-- Estado del proceso móvil -->
            <div class="mb-2 p-2 bg-gray-50 rounded border-l-2 ${getStatusBorderColor(card.kanban_status || 'nuevo')}">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-gray-500">Estado:</span>
                    <span class="text-xs font-bold ${getStatusTextColor(card.kanban_status || 'nuevo')}">
                        ${getKanbanStatusText(card.kanban_status || 'nuevo')}
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1">
                    <div class="${getProgressBarColor(card.kanban_status || 'nuevo')} h-1 rounded-full" 
                         style="width: ${getProgressPercentage(card.kanban_status || 'nuevo')}%"></div>
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <span class="text-gray-500" style="font-size: clamp(0.625rem, 1.5vw, 0.75rem);">${card.total_attempts} intentos</span>
                <button onclick="viewCandidate('${card.user_id}', '${card.position}')" class="bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700" style="font-size: clamp(0.625rem, 1.5vw, 0.75rem);">
                    Ver
                </button>
            </div>
        </div>
    `;
}

// Render list view
function renderListView() {
    const listContainer = document.getElementById('list-cards');
    if (!listContainer) return;
    
    const allCards = kanbanData.cards;
    
    listContainer.innerHTML = allCards.map(card => {
        const positionName = card.position === 'maestro_sanguchero' ? 'Maestro' : 'Cajero';
        const scoreColor = getScoreColor(card.best_score);
        const currentColumn = kanbanData.columns.find(col => col.id == card.column_id);
        
        return `
            <div class="bg-white rounded-lg p-3 shadow-sm border">
                <!-- Header con info del candidato -->
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                        <img src="${card.foto_perfil || '/icon.png'}" alt="${card.nombre}" class="w-8 h-8 rounded-full flex-shrink-0">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1">
                                <h4 class="font-medium text-gray-900 line-clamp-1" style="font-size: clamp(0.875rem, 2.5vw, 1rem);">${card.nombre}</h4>
                                <span style="font-size: clamp(0.875rem, 2.5vw, 1rem);" title="${card.nacionalidad || 'Nacionalidad no especificada'}">${getFlagEmoji(card.nacionalidad)}</span>
                            </div>
                            <p class="text-gray-500 line-clamp-1" style="font-size: clamp(0.75rem, 2vw, 0.875rem);">${positionName}</p>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="font-bold ${scoreColor}" style="font-size: clamp(1rem, 3vw, 1.25rem);">${card.best_score || 0}%</div>
                        <div class="text-gray-500" style="font-size: clamp(0.625rem, 1.5vw, 0.75rem);">${card.total_attempts} intentos</div>
                    </div>
                </div>
                
                <!-- Estado del proceso en lista -->
                <div class="mb-3 p-2 bg-gray-50 rounded border-l-3 ${getStatusBorderColor(card.kanban_status || 'nuevo')}">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-gray-500">Estado del proceso:</span>
                        <span class="text-xs font-bold ${getStatusTextColor(card.kanban_status || 'nuevo')}">
                            ${getKanbanStatusText(card.kanban_status || 'nuevo')}
                        </span>
                    </div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-gray-400">Progreso</span>
                        <span class="text-xs font-medium ${getStatusTextColor(card.kanban_status || 'nuevo')}">
                            ${getProgressPercentage(card.kanban_status || 'nuevo')}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="${getProgressBarColor(card.kanban_status || 'nuevo')} h-2 rounded-full" 
                             style="width: ${getProgressPercentage(card.kanban_status || 'nuevo')}%"></div>
                    </div>
                    <div class="flex gap-1 mb-2">
                        ${kanbanData.columns.map(column => {
                            const isActive = column.id == card.column_id;
                            return `
                                <button 
                                    onclick="moveCardQuick('${card.id}', '${column.id}')"
                                    class="flex-1 py-1 px-2 rounded text-center transition-colors ${
                                        isActive 
                                            ? 'bg-blue-600 text-white' 
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    }"
                                    style="font-size: clamp(0.625rem, 1.5vw, 0.75rem);"
                                >
                                    ${column.name}
                                </button>
                            `;
                        }).join('')}
                    </div>
                </div>
                
                <!-- Acciones -->
                <div class="flex gap-2">
                    <button onclick="viewCandidate('${card.user_id}', '${card.position}')" class="flex-1 bg-blue-600 text-white py-2 px-3 rounded hover:bg-blue-700" style="font-size: clamp(0.75rem, 2vw, 0.875rem);">
                        Ver Detalle
                    </button>
                    <a href="tel:${card.telefono}" class="bg-green-600 text-white py-2 px-3 rounded hover:bg-green-700 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                    </a>
                </div>
            </div>
        `;
    }).join('');
}

// Switch between views (mobile)
function switchView(view) {
    const kanbanView = document.getElementById('kanban-board');
    const listView = document.getElementById('list-view');
    const kanbanBtn = document.getElementById('kanban-view-btn');
    const listBtn = document.getElementById('list-view-btn');
    
    if (view === 'kanban') {
        kanbanView.classList.remove('hidden');
        listView.classList.add('hidden');
        kanbanBtn.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
        kanbanBtn.classList.remove('text-gray-600');
        listBtn.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
        listBtn.classList.add('text-gray-600');
    } else {
        kanbanView.classList.add('hidden');
        listView.classList.remove('hidden');
        listBtn.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
        listBtn.classList.remove('text-gray-600');
        kanbanBtn.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
        kanbanBtn.classList.add('text-gray-600');
    }
}

// Setup column indicators
function setupColumnIndicators() {
    const indicatorsContainer = document.getElementById('column-indicators');
    
    indicatorsContainer.innerHTML = kanbanData.columns.map((column, index) => 
        `<div class="column-indicator ${index === 0 ? 'active' : 'inactive'}" data-column-index="${index}"></div>`
    ).join('');
}

// Update column indicators on scroll
function updateColumnIndicators() {
    const container = document.getElementById('kanban-columns-mobile');
    const indicators = document.querySelectorAll('.column-indicator');
    
    if (!container || indicators.length === 0) return;
    
    const scrollLeft = container.scrollLeft;
    const columnWidth = 280 + 16; // width + margin
    const currentColumn = Math.round(scrollLeft / columnWidth);
    
    indicators.forEach((indicator, index) => {
        if (index === currentColumn) {
            indicator.classList.remove('inactive');
            indicator.classList.add('active');
        } else {
            indicator.classList.remove('active');
            indicator.classList.add('inactive');
        }
    });
}

// Move card quickly from list view
async function moveCardQuick(cardId, toColumnId) {
    try {
        const response = await fetch('/api/tracker/move_kanban_card.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                card_id: cardId,
                to_column_id: toColumnId,
                new_position: 0
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadKanbanData(); // Reload to reflect changes
        } else {
            console.error('Error moving card:', data.error);
        }
        
    } catch (error) {
        console.error('Error moving card:', error);
    }
}

// View candidate details
function viewCandidate(userId, position) {
    window.location.href = `/jobsTracker/candidate/dynamic?id=${userId}&position=${position || 'cajero'}`;
}

// Change user status
async function changeStatus(userId, newStatus) {
    if (!confirm(`¿Cambiar estado a "${newStatus}"?`)) return;
    
    try {
        const response = await fetch('/api/tracker/update_kanban_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId,
                new_status: newStatus,
                send_notification: false
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadKanbanData(); // Reload to reflect changes
            alert('Estado actualizado correctamente');
        } else {
            alert('Error: ' + data.error);
        }
        
    } catch (error) {
        console.error('Error changing status:', error);
        alert('Error al cambiar estado');
    }
}

// Send notification
async function sendNotification(userId) {
    if (!confirm('¿Enviar notificación por email al candidato?')) return;
    
    try {
        const response = await fetch('/api/tracker/get_notification_status.php?user_id=' + userId);
        const statusData = await response.json();
        
        if (!statusData.success) {
            alert('Error obteniendo estado del usuario');
            return;
        }
        
        const currentStatus = statusData.data.current_status;
        
        const notifyResponse = await fetch('/api/tracker/update_kanban_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId,
                new_status: currentStatus,
                send_notification: true
            })
        });
        
        const notifyData = await notifyResponse.json();
        
        if (notifyData.success) {
            alert('Notificación enviada correctamente');
        } else {
            alert('Error enviando notificación: ' + notifyData.error);
        }
        
    } catch (error) {
        console.error('Error sending notification:', error);
        alert('Error al enviar notificación');
    }
}

// Sync kanban status
async function syncKanbanStatus() {
    if (!confirm('¿Sincronizar estados del kanban? Esto actualizará los estados según la posición de las tarjetas.')) return;
    
    try {
        const response = await fetch('/api/tracker/sync_kanban_status.php');
        const data = await response.json();
        
        if (data.success) {
            alert('Sincronización completada: ' + data.message);
            loadKanbanData();
        } else {
            alert('Error en sincronización: ' + data.error);
        }
        
    } catch (error) {
        console.error('Error syncing kanban:', error);
        alert('Error al sincronizar kanban');
    }
}

// Debug kanban status
async function debugKanbanStatus() {
    try {
        const response = await fetch('/api/tracker/debug_kanban_status.php');
        const data = await response.json();
        console.log('Debug data:', data);
        alert('Ver consola para datos de debug');
    } catch (error) {
        console.error('Error debugging:', error);
    }
}

// Remove drag-over class and preview when leaving
document.addEventListener('dragleave', function(event) {
    if (event.target.classList.contains('kanban-column')) {
        // Solo remover si realmente salimos de la columna
        if (!event.target.contains(event.relatedTarget)) {
            event.target.classList.remove('drag-over');
            const preview = event.target.querySelector('.drop-preview');
            if (preview) {
                preview.remove();
            }
        }
    }
});