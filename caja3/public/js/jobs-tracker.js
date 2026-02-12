// Jobs Tracker Dashboard JavaScript

let allCandidates = [];

// Initialize dashboard
document.addEventListener('DOMContentLoaded', async function() {
    const isAuth = await initJobsTrackerAuth();
    if (isAuth) {
        showDashboard();
        loadDashboardData();
        initQRCode();
        // Google Maps se inicializará desde el callback
        console.log('Dashboard cargado, esperando Google Maps callback...');
        startRealTimeUpdates();
    }
    setupEventListeners();
});

// Show dashboard content
function showDashboard() {
    document.getElementById('auth-loading').classList.add('hidden');
    document.getElementById('dashboard-content').classList.remove('hidden');
}

// Setup event listeners
function setupEventListeners() {
    const applyFiltersBtn = document.getElementById('apply-filters');
    const mobileRefreshBtn = document.getElementById('mobile-refresh-btn');
    const downloadQRBtn = document.getElementById('download-qr');
    const downloadPosterBtn = document.getElementById('download-poster');
    const copyLinkBtn = document.getElementById('copy-link');
    
    if (applyFiltersBtn) applyFiltersBtn.addEventListener('click', applyFilters);
    if (mobileRefreshBtn) mobileRefreshBtn.addEventListener('click', handleRefresh);
    if (downloadQRBtn) downloadQRBtn.addEventListener('click', downloadQRCode);
    if (downloadPosterBtn) downloadPosterBtn.addEventListener('click', downloadPoster);
    if (copyLinkBtn) copyLinkBtn.addEventListener('click', copyJobsLink);
}

// Handle refresh - Limpiar caché completo de la app
function handleRefresh() {
    // Visual feedback
    const btn = document.getElementById('refresh-btn');
    if (btn) btn.classList.add('animate-spin');
    
    // Limpiar todo el caché y recargar página
    if ('caches' in window) {
        caches.keys().then(names => {
            names.forEach(name => {
                caches.delete(name);
            });
        });
    }
    
    // Limpiar storage local
    localStorage.clear();
    sessionStorage.clear();
    
    // Recargar página forzando descarga desde servidor
    setTimeout(() => {
        window.location.reload(true);
    }, 500);
}



// Load dashboard data with cache busting
async function loadDashboardData() {
    try {
        const timestamp = Date.now();
        // Load stats with cache busting
        const statsResponse = await fetch(`/api/tracker/get_stats.php?_t=${timestamp}`);
        
        if (!statsResponse.ok) {
            throw new Error(`HTTP error! status: ${statsResponse.status}`);
        }
        
        const statsText = await statsResponse.text();
        let stats;
        try {
            stats = JSON.parse(statsText);
        } catch (e) {
            console.error('Invalid JSON response:', statsText);
            throw new Error('Invalid JSON response from server');
        }
        
        if (stats.success) {
            document.getElementById('total-candidates').textContent = stats.data.total;
            document.getElementById('completed-applications').textContent = stats.data.completed;
            document.getElementById('pending-applications').textContent = stats.data.pending;
            document.getElementById('average-score').textContent = stats.data.average_score + '%';
        }
        
        // Load interviews status
        await loadInterviewsStatus();
        
        // Load candidates
        loadCandidates();
        
    } catch (error) {
        console.error('Error loading dashboard data:', error);
    }
}

// Load interviews status
let interviewsStatus = {};
async function loadInterviewsStatus() {
    try {
        const response = await fetch('/api/tracker/get_interviews_status.php');
        const data = await response.json();
        
        if (data.success) {
            interviewsStatus = data.data;
        }
    } catch (error) {
        console.error('Error loading interviews status:', error);
    }
}

// Load candidates
async function loadCandidates(filters = {}) {
    try {
        document.getElementById('loading').classList.remove('hidden');
        document.getElementById('no-results').classList.add('hidden');
        
        const params = new URLSearchParams(filters);
        params.append('_t', Date.now()); // Cache busting
        const response = await fetch('/api/tracker/get_candidates.php?' + params);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('Invalid JSON response:', responseText);
            throw new Error('Invalid JSON response from server');
        }
        
        document.getElementById('loading').classList.add('hidden');
        
        if (data.success) {
            allCandidates = data.data;
            renderCandidatesTable(allCandidates);
        } else {
            document.getElementById('no-results').classList.remove('hidden');
        }
        
    } catch (error) {
        console.error('Error loading candidates:', error);
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('no-results').classList.remove('hidden');
    }
}

// Render candidates cards
function renderCandidatesTable(candidates) {
    const cardsContainer = document.getElementById('candidates-cards');
    
    if (candidates.length === 0) {
        document.getElementById('no-results').classList.remove('hidden');
        cardsContainer.innerHTML = '<div class="grid grid-cols-1 lg:grid-cols-2 gap-4"><div class="col-span-full text-center py-8 text-gray-500">No se encontraron candidatos</div></div>';
        return;
    }
    
    // Render cards for all devices
    renderCards(candidates, cardsContainer);
}



// Render cards for all devices
function renderCards(candidates, container) {
    const cardsHTML = candidates.map(candidate => {
        const positionName = candidate.position === 'maestro_sanguchero' ? 'Maestro Sanguchero' : 'Cajero';
        const statusBadge = getStatusBadge(candidate.status);
        const date = new Date(candidate.last_attempt || candidate.created_at).toLocaleDateString('es-CL');
        
        return `
            <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-start space-x-3">
                    <img class="w-12 h-12 rounded-full flex-shrink-0" src="${candidate.foto_perfil || '/icon.png'}" alt="${candidate.nombre}">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <div class="flex items-center gap-1">
                                    <h4 class="text-base font-medium text-gray-900 truncate">${candidate.nombre}</h4>
                                    <span class="text-base" title="${candidate.nacionalidad || 'Nacionalidad no especificada'}">${getFlagEmoji(candidate.nacionalidad)}</span>
                                    ${getInterviewStatusBadge(candidate.user_id || candidate.id)}
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${candidate.position === 'maestro_sanguchero' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'}">
                                ${positionName}
                            </span>
                        </div>
                        
                        <div class="flex items-center justify-between mb-3">
                            <div class="text-center">
                                ${getScoreBadge(candidate.best_score || candidate.score)}
                                <div class="text-xs text-gray-500">${candidate.total_attempts || 1} intento${(candidate.total_attempts || 1) > 1 ? 's' : ''}</div>
                            </div>
                            <div class="text-right">
                                ${statusBadge}
                                <div class="text-xs text-gray-500 mt-1">${date}</div>
                            </div>
                        </div>
                        
                        <!-- Estado del proceso -->
                        <div class="mb-3 p-3 bg-gray-50 rounded-lg">
                            <div class="text-xs text-gray-500 mb-2">Estado del proceso:</div>
                            <div class="flex justify-between items-center" data-user-kanban-status="${candidate.user_id || candidate.id}">
                                <div class="flex gap-1">
                                    <button onclick="changeStatus('${candidate.user_id || candidate.id}', 'nuevo')" class="reaction-btn ${(candidate.kanban_status || 'nuevo') === 'nuevo' ? 'active' : ''}" data-status="nuevo" title="Nuevo">
                                        📝
                                    </button>
                                    <button onclick="changeStatus('${candidate.user_id || candidate.id}', 'revisando')" class="reaction-btn ${candidate.kanban_status === 'revisando' ? 'active' : ''}" data-status="revisando" title="Revisando">
                                        👁️
                                    </button>
                                    <button onclick="changeStatus('${candidate.user_id || candidate.id}', 'entrevista')" class="reaction-btn ${candidate.kanban_status === 'entrevista' ? 'active' : ''}" data-status="entrevista" title="Entrevista">
                                        🗣️
                                    </button>
                                    <button onclick="changeStatus('${candidate.user_id || candidate.id}', 'contratado')" class="reaction-btn ${candidate.kanban_status === 'contratado' ? 'active' : ''}" data-status="contratado" title="Contratado">
                                        ✅
                                    </button>
                                    <button onclick="changeStatus('${candidate.user_id || candidate.id}', 'rechazado')" class="reaction-btn ${candidate.kanban_status === 'rechazado' ? 'active' : ''}" data-status="rechazado" title="Rechazado">
                                        ❌
                                    </button>
                                </div>
                                <div class="text-xs font-medium text-gray-700">
                                    ${getKanbanStatusText(candidate.kanban_status || 'nuevo')}
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <a href="tel:${candidate.telefono}" class="text-xs text-blue-600 hover:text-blue-800 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    Llamar
                                </a>
                                ${candidate.instagram ? `<a href="https://instagram.com/${candidate.instagram}" target="_blank" class="text-xs text-pink-600 hover:text-pink-800 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.40s-.644-1.44-1.439-1.44z"/>
                                    </svg>
                                    IG
                                </a>` : ''}
                            </div>
                            <div class="flex gap-2">
                                <button onclick="viewCandidate('${candidate.user_id || candidate.id}', '${candidate.position}')" class="flex-1 text-xs bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700">Ver Detalle</button>
                                ${getInterviewButton(candidate.user_id || candidate.id, candidate.position)}
                                <button onclick="sendNotification('${candidate.user_id || candidate.id}')" class="text-xs bg-purple-600 text-white px-3 py-2 rounded hover:bg-purple-700">
                                    📧
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = `<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">${cardsHTML}</div>`;
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

// Get interview status badge
function getInterviewStatusBadge(candidateId) {
    const interviewStatus = interviewsStatus[candidateId];
    
    if (!interviewStatus) {
        return '';
    }
    
    switch (interviewStatus.status) {
        case 'draft':
            return '<span class="ml-1 text-xs" title="Entrevista en borrador">📝</span>';
        case 'completed':
            return '<span class="ml-1 text-xs" title="Entrevista completada">✅</span>';
        case 'callback_scheduled':
            return '<span class="ml-1 text-xs" title="Llamada programada">📞</span>';
        default:
            return '';
    }
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

// Apply filters
function applyFilters() {
    const filters = {
        position: document.getElementById('filter-position').value,
        status: document.getElementById('filter-status').value,
        min_score: document.getElementById('filter-score').value
    };
    
    // Remove empty filters
    Object.keys(filters).forEach(key => {
        if (!filters[key]) delete filters[key];
    });
    
    loadCandidates(filters);
}

// View candidate details
function viewCandidate(candidateId, position) {
    window.location.href = `/jobsTracker/candidate/dynamic?id=${candidateId}&position=${position}`;
}

// Start interview
function startInterview(candidateId, position) {
    window.location.href = `/jobsTracker/entrevista/?id=${candidateId}&position=${position}`;
}

// Get interview button based on status
function getInterviewButton(candidateId, position) {
    const interviewStatus = interviewsStatus[candidateId];
    
    if (!interviewStatus) {
        return `<button onclick="startInterview('${candidateId}', '${position}')" class="text-xs bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700" title="Iniciar Entrevista">
                    Entrevista
                </button>`;
    }
    
    switch (interviewStatus.status) {
        case 'draft':
            return `<button onclick="startInterview('${candidateId}', '${position}')" class="text-xs bg-yellow-600 text-white px-3 py-2 rounded hover:bg-yellow-700" title="Continuar Borrador">
                        Entrevista
                    </button>`;
        case 'completed':
            return `<button onclick="startInterview('${candidateId}', '${position}')" class="text-xs bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700" title="Ver Entrevista">
                        Entrevista
                    </button>`;
        case 'callback_scheduled':
            return `<button onclick="startInterview('${candidateId}', '${position}')" class="text-xs bg-orange-600 text-white px-3 py-2 rounded hover:bg-orange-700" title="Llamada Programada">
                        Entrevista
                    </button>`;
        default:
            return `<button onclick="startInterview('${candidateId}', '${position}')" class="text-xs bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700" title="Iniciar Entrevista">
                        Entrevista
                    </button>`;
    }
}

// Download PDF
function downloadPDF(candidateId) {
    window.open(`/api/tracker/generate_pdf.php?id=${candidateId}`, '_blank');
}

// Send email to candidate
async function sendEmail(candidateId, candidateName, position) {
    if (!confirm(`¿Enviar email a ${candidateName} informando que hemos revisado su postulación?`)) {
        return;
    }
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '⏳';
    button.disabled = true;
    
    try {
        const response = await fetch('/api/tracker/send_candidate_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                candidate_id: candidateId,
                position: position
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            button.innerHTML = '✓';
            button.style.backgroundColor = '#10b981';
            setTimeout(() => {
                button.innerHTML = originalText;
                button.style.backgroundColor = '';
                button.disabled = false;
            }, 2000);
        } else {
            alert('Error enviando email: ' + data.error);
            button.innerHTML = originalText;
            button.disabled = false;
        }
        
    } catch (error) {
        alert('Error de conexión al enviar email');
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// Handle URL parameters for login success
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('login') === 'success') {
    window.history.replaceState({}, document.title, window.location.pathname);
    checkAuthentication();
}

// QR Code Functions
function initQRCode() {
    loadQRStats();
    refreshPosterPreview();
}

function refreshPosterPreview() {
    // Refrescar vista previa del poster con timestamp para evitar caché
    const posterImg = document.getElementById('poster-preview');
    if (posterImg) {
        posterImg.src = '/api/tracker/generate_qr_poster.php?t=' + Date.now();
    }
}

// Mapa de ubicaciones con Google Maps
let map;
let markers = [];

function initMap() {
    try {
        console.log('Inicializando mapa...');
        const mapElement = document.getElementById('qr-map');
        if (!mapElement) {
            console.error('Elemento qr-map no encontrado');
            return;
        }
        
        // Centrar en Arica, Chile
        map = new google.maps.Map(mapElement, {
            zoom: 10,
            center: { lat: -18.4746, lng: -70.3127 },
            mapTypeId: 'roadmap',
            styles: [
                {
                    featureType: 'poi.business',
                    elementType: 'labels',
                    stylers: [{ visibility: 'off' }]
                }
            ]
        });
        
        console.log('Mapa inicializado, cargando ubicaciones...');
        loadQRLocations();
    } catch (error) {
        console.error('Error inicializando mapa:', error);
        // Mostrar tabla de ubicaciones como fallback
        showLocationTable();
    }
}

async function loadQRLocations() {
    try {
        const response = await fetch('/api/tracker/get_qr_locations.php');
        const data = await response.json();
        
        if (data.success && data.locations.length > 0) {
            // Limpiar marcadores existentes
            markers.forEach(marker => marker.setMap(null));
            markers = [];
            
            // Agregar marcadores
            data.locations.forEach(location => {
                const marker = new google.maps.Marker({
                    position: { lat: location.lat, lng: location.lng },
                    map: map,
                    title: `${location.views} vista${location.views > 1 ? 's' : ''}`,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: Math.max(10, location.views * 4),
                        fillColor: '#f59e0b',
                        fillOpacity: 0.8,
                        strokeColor: '#d97706',
                        strokeWeight: 2
                    }
                });
                
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="padding: 8px; font-family: Inter, sans-serif;">
                            <h4 style="margin: 0 0 4px 0; font-weight: bold; color: #111827;">${location.city}</h4>
                            <p style="margin: 0 0 4px 0; font-size: 13px; color: #6b7280;">${location.region}, ${location.country}</p>
                            <p style="margin: 0; font-size: 13px; font-weight: 500; color: #f59e0b;">${location.views} vista${location.views > 1 ? 's' : ''}</p>
                        </div>
                    `
                });
                
                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });
                
                markers.push(marker);
            });
            
            // Ajustar vista del mapa
            if (data.locations.length === 1) {
                map.setCenter({ lat: data.locations[0].lat, lng: data.locations[0].lng });
                map.setZoom(12);
            } else {
                const bounds = new google.maps.LatLngBounds();
                data.locations.forEach(location => {
                    bounds.extend({ lat: location.lat, lng: location.lng });
                });
                map.fitBounds(bounds);
            }
        }
    } catch (error) {
        console.error('Error cargando ubicaciones:', error);
    }
}

// Fallback: mostrar tabla de ubicaciones
async function showLocationTable() {
    try {
        const response = await fetch('/api/tracker/get_qr_locations.php');
        const data = await response.json();
        
        if (data.success && data.locations.length > 0) {
            const tableHTML = `
                <div class="p-4">
                    <h4 class="font-bold mb-4 text-gray-900">Ubicaciones de Vistas del QR</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ciudad</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Región</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">País</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Vistas</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${data.locations.map(location => `
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900">${location.city}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600">${location.region}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600">${location.country}</td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                ${location.views}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            document.getElementById('qr-map').innerHTML = tableHTML;
        } else {
            document.getElementById('qr-map').innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><p>No hay ubicaciones registradas aún</p></div>';
        }
    } catch (error) {
        console.error('Error mostrando tabla de ubicaciones:', error);
        document.getElementById('qr-map').innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><p>Error cargando ubicaciones</p></div>';
    }
}

function generateQRCode() {
    const container = document.getElementById('qr-display');
    const url = 'https://ruta11app.agenterag.com/jobs/';
    
    // Usar API externa directamente (más confiable)
    const img = document.createElement('img');
    img.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(url)}&margin=10`;
    img.alt = 'QR Code';
    img.style.width = '200px';
    img.style.height = '200px';
    img.style.border = '2px solid #e5e7eb';
    img.style.borderRadius = '8px';
    container.appendChild(img);
}

function downloadQRCode() {
    const url = 'https://ruta11app.agenterag.com/jobs/';
    
    // Descargar QR de alta calidad desde API
    const link = document.createElement('a');
    link.download = 'ruta11-jobs-qr.png';
    link.href = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&format=png&margin=20&data=${encodeURIComponent(url)}`;
    link.click();
}

function downloadPoster() {
    // Descargar poster completo con QR integrado
    const link = document.createElement('a');
    link.download = 'ruta11-poster-empleos.png';
    link.href = '/api/tracker/generate_qr_poster.php';
    link.click();
}

function copyJobsLink() {
    const url = 'https://ruta11app.agenterag.com/jobs/';
    navigator.clipboard.writeText(url).then(() => {
        const btn = document.getElementById('copy-link');
        const originalText = btn.textContent;
        btn.textContent = 'Copiado!';
        btn.classList.add('bg-green-600');
        btn.classList.remove('bg-gray-600');
        
        setTimeout(() => {
            btn.textContent = originalText;
            btn.classList.remove('bg-green-600');
            btn.classList.add('bg-gray-600');
        }, 2000);
    });
}

async function loadQRStats() {
    try {
        const response = await fetch('/api/tracker/get_qr_stats.php?_t=' + Date.now());
        const data = await response.json();
        
        if (data.success) {
            // Actualizar con animación suave
            updateCounterWithAnimation('total-qr-views', data.stats.total_views || 0);
            updateCounterWithAnimation('today-qr-views', data.stats.today_views || 0);
            
            const lastViewEl = document.getElementById('last-qr-view');
            if (lastViewEl) lastViewEl.textContent = data.stats.last_view || '-';
            
            const viewsCountEl = document.getElementById('qr-views-count');
            if (viewsCountEl) viewsCountEl.textContent = `${data.stats.total_views || 0} vistas`;
            
            // Mostrar indicador de actualización
            showUpdateIndicator();
        }
    } catch (error) {
        console.error('Error cargando estadísticas QR:', error);
    }
}

function updateCounterWithAnimation(elementId, newValue) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const currentValue = parseInt(element.textContent) || 0;
    
    if (newValue !== currentValue) {
        element.style.transform = 'scale(1.1)';
        element.style.color = '#10b981';
        
        setTimeout(() => {
            element.textContent = newValue;
            element.style.transform = 'scale(1)';
            element.style.color = '';
        }, 150);
    }
}

function showUpdateIndicator() {
    const indicator = document.getElementById('live-indicator');
    if (indicator) {
        indicator.style.backgroundColor = '#dcfce7';
        indicator.style.color = '#166534';
        
        setTimeout(() => {
            indicator.style.backgroundColor = '';
            indicator.style.color = '';
        }, 1000);
    }
}

// Sistema de actualizaciones en tiempo real
let updateInterval;

function startRealTimeUpdates() {
    // Actualizar cada 30 segundos
    updateInterval = setInterval(() => {
        loadQRStats();
        loadDashboardData();
        if (map) loadQRLocations();
    }, 30000);
    
    // También actualizar cuando la ventana vuelve a tener foco
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            loadQRStats();
            loadDashboardData();
        }
    });
}

function stopRealTimeUpdates() {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
}

// Change user status with immediate visual feedback
async function changeStatus(userId, newStatus) {
    const statusNames = {
        'revisando': 'Revisando',
        'entrevista': 'Entrevista', 
        'contratado': 'Contratado',
        'rechazado': 'Rechazado'
    };
    
    if (!confirm(`¿Cambiar estado a "${statusNames[newStatus]}"?`)) return;
    
    // Find all buttons for this user and show loading
    const userButtons = document.querySelectorAll(`[onclick*="'${userId}'"]`);
    const originalStates = [];
    
    userButtons.forEach(btn => {
        if (btn.onclick && btn.onclick.toString().includes('changeStatus')) {
            originalStates.push({
                element: btn,
                originalText: btn.innerHTML,
                originalClass: btn.className
            });
            btn.innerHTML = '⏳';
            btn.disabled = true;
            btn.className = btn.className.replace(/bg-\w+-\d+/, 'bg-gray-400');
        }
    });
    
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
            // Show success feedback
            userButtons.forEach(btn => {
                if (btn.onclick && btn.onclick.toString().includes('changeStatus')) {
                    btn.innerHTML = '✅';
                    btn.className = 'px-2 py-1 text-xs bg-green-500 text-white rounded';
                }
            });
            
            // Update status badge immediately
            updateStatusBadgeInDOM(userId, newStatus);
            
            // Show toast notification
            showToast(`Estado cambiado a ${statusNames[newStatus]}`, 'success');
            
            // Restore buttons after 1.5s and reload data
            setTimeout(() => {
                originalStates.forEach(state => {
                    state.element.innerHTML = state.originalText;
                    state.element.className = state.originalClass;
                    state.element.disabled = false;
                });
                loadCandidates(); // Reload to sync with server
            }, 1500);
            
        } else {
            throw new Error(data.error);
        }
        
    } catch (error) {
        console.error('Error changing status:', error);
        
        // Restore original state on error
        originalStates.forEach(state => {
            state.element.innerHTML = state.originalText;
            state.element.className = state.originalClass;
            state.element.disabled = false;
        });
        
        showToast('Error al cambiar estado: ' + error.message, 'error');
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

// Get Kanban status badge
function getKanbanStatusBadge(status) {
    const badges = {
        'nuevo': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">📝 Nuevo</span>',
        'revisando': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">👁️ Revisando</span>',
        'entrevista': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">🗣️ Entrevista</span>',
        'contratado': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✅ Contratado</span>',
        'rechazado': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">❌ Rechazado</span>'
    };
    return badges[status] || badges['nuevo'];
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

// Update status badge in DOM immediately
function updateStatusBadgeInDOM(userId, newStatus) {
    // Update desktop kanban status column
    const kanbanStatusCell = document.querySelector(`[data-user-kanban-status="${userId}"]`);
    if (kanbanStatusCell) {
        kanbanStatusCell.innerHTML = getKanbanStatusBadge(newStatus);
        kanbanStatusCell.style.animation = 'pulse 0.5s ease-in-out';
        setTimeout(() => kanbanStatusCell.style.animation = '', 500);
    }
    
    // Update mobile reactions
    const reactionContainers = document.querySelectorAll(`[data-user-kanban-status="${userId}"]`);
    reactionContainers.forEach(container => {
        // Remove active class from all buttons
        container.querySelectorAll('.reaction-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Add active class to current status
        const activeBtn = container.querySelector(`[data-status="${newStatus}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
            // Animate the button
            activeBtn.style.transform = 'scale(1.2)';
            setTimeout(() => activeBtn.style.transform = '', 200);
        }
        
        // Update status text
        const statusText = container.querySelector('.text-xs.font-medium');
        if (statusText) {
            statusText.textContent = getKanbanStatusText(newStatus);
        }
    });
}

// Add CSS for reaction buttons
if (!document.getElementById('reaction-styles')) {
    const style = document.createElement('style');
    style.id = 'reaction-styles';
    style.textContent = `
        .reaction-btn {
            padding: 6px 8px;
            border: 2px solid transparent;
            border-radius: 20px;
            background: #f3f4f6;
            font-size: 16px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .reaction-btn:hover {
            transform: scale(1.1);
            background: #e5e7eb;
        }
        .reaction-btn.active {
            border-color: #3b82f6;
            background: #dbeafe;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
        }
    `;
    document.head.appendChild(style);
}

// Show toast notification
function showToast(message, type = 'info') {
    // Remove existing toast
    const existingToast = document.getElementById('status-toast');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.id = 'status-toast';
    toast.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white font-medium transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    }`;
    toast.textContent = message;
    toast.style.transform = 'translateX(100%)';
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => toast.style.transform = 'translateX(0)', 100);
    
    // Animate out and remove
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Refresh QR Map manually
function refreshQRMap() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Cargando...';
    btn.disabled = true;
    
    if (map) {
        loadQRLocations();
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 2000);
    } else {
        // Si no hay mapa, intentar inicializarlo
        if (typeof google !== 'undefined' && google.maps) {
            initMap();
        }
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 2000);
    }
}

// DISABLED FOR CAJA: No se solicita ubicación en caja3
// Función para trackear vista SIN ubicación
function trackQRViewWithLocation() {
    console.log('⚠️ Ubicación desactivada en caja3');
    // Registrar sin coordenadas
    fetch('/api/tracker/track_qr_view.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({})
    }).catch(() => {});
}