// Keywords Management JavaScript

let keywordsData = { keywords: [], stats: {} };
let keywordCounter = 0;

// Initialize
document.addEventListener('DOMContentLoaded', async function() {
    const isAuth = await initJobsTrackerAuth();
    if (isAuth) {
        loadKeywordsData();
    }
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    document.getElementById('save-btn').addEventListener('click', saveKeywords);
}

// Load keywords data
async function loadKeywordsData() {
    try {
        document.getElementById('loading').classList.remove('hidden');
        
        const timestamp = Date.now();
        const response = await fetch(`/api/tracker/get_keywords.php?_t=${timestamp}`);
        const data = await response.json();
        
        if (data.success) {
            keywordsData = data.data;
            renderStats();
            renderKeywords();
        } else {
            console.error('Error loading keywords:', data.error);
        }
        
    } catch (error) {
        console.error('Error loading keywords:', error);
    } finally {
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('stats-section').classList.remove('hidden');
        document.getElementById('keywords-section').classList.remove('hidden');
    }
}

// Render stats
function renderStats() {
    const maestroStats = keywordsData.stats['maestro_sanguchero'] || {};
    const cajeroStats = keywordsData.stats['cajero'] || {};
    
    document.getElementById('maestro-candidates').textContent = maestroStats.total_candidates || 0;
    document.getElementById('maestro-score').textContent = maestroStats.avg_score ? Math.round(maestroStats.avg_score) + '%' : '0%';
    document.getElementById('maestro-applications').textContent = maestroStats.total_applications || 0;
    
    document.getElementById('cajero-candidates').textContent = cajeroStats.total_candidates || 0;
    document.getElementById('cajero-score').textContent = cajeroStats.avg_score ? Math.round(cajeroStats.avg_score) + '%' : '0%';
    document.getElementById('cajero-applications').textContent = cajeroStats.total_applications || 0;
}

// Render keywords
function renderKeywords() {
    const keywordsList = document.getElementById('keywords-list');
    
    keywordsList.innerHTML = keywordsData.keywords.map(category => {
        const positionText = {
            'both': 'Ambas posiciones',
            'maestro_sanguchero': 'Solo Maestro Sanguchero', 
            'cajero': 'Solo Cajero'
        }[category.position] || category.position;
        
        const wordsCount = Array.isArray(category.words) ? category.words.length : 0;
        const maxPoints = (category.weight * wordsCount).toFixed(1);
        
        return `
            <div class="border border-gray-200 rounded-lg p-4" data-category-id="${category.id}">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex-1">
                        <input type="text" value="${category.label}" 
                               class="font-semibold text-gray-900 bg-transparent border-none p-0 w-full focus:outline-none focus:ring-2 focus:ring-blue-500 rounded px-2"
                               onchange="updateCategory(${category.id}, 'label', this.value)">
                        <div class="flex items-center gap-4 text-sm text-gray-600 mt-1">
                            <span>Categoría: ${category.category}</span>
                            <span>Aplica a: ${positionText}</span>
                            <span>Peso: 
                                <input type="number" value="${category.weight}" min="0.1" max="10" step="0.1" 
                                       class="w-16 px-1 py-0 border border-gray-300 rounded text-xs"
                                       onchange="updateCategory(${category.id}, 'weight', this.value)">
                            </span>
                            <span>Palabras: ${wordsCount}</span>
                            <span class="font-medium text-blue-600">Máx: ${maxPoints} pts</span>
                        </div>
                    </div>
                    <button onclick="deleteCategory(${category.id})" class="text-red-600 hover:text-red-800 p-1 ml-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
                <div class="bg-gray-50 p-3 rounded">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs text-gray-500">Palabras clave:</p>
                        <button onclick="addWord(${category.id})" class="text-xs bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700">
                            + Palabra
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1" id="words-${category.id}">
                        ${Array.isArray(category.words) ? 
                            category.words.map((word, index) => `
                                <div class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded flex items-center gap-1">
                                    <input type="text" value="${word}" 
                                           class="bg-transparent border-none p-0 text-xs w-auto min-w-16 focus:outline-none"
                                           onchange="updateWord(${category.id}, ${index}, this.value)">
                                    <button onclick="removeWord(${category.id}, ${index})" class="text-blue-600 hover:text-blue-800">
                                        ×
                                    </button>
                                </div>
                            `).join('') :
                            '<span class="text-gray-400 text-xs">No hay palabras definidas</span>'
                        }
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Render individual keyword row
function renderKeywordRow(keyword) {
    const id = keyword.id || `new_${keywordCounter++}`;
    
    return `
        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg" data-keyword-id="${id}">
            <div class="flex-1">
                <input type="text" 
                       value="${keyword.keyword || ''}" 
                       placeholder="Palabra clave..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       onchange="updateKeyword('${id}', 'keyword', this.value)">
            </div>
            <div class="w-24">
                <input type="number" 
                       value="${keyword.weight || 1}" 
                       min="0.1" 
                       max="10" 
                       step="0.1"
                       placeholder="Peso"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       onchange="updateKeyword('${id}', 'weight', this.value)">
            </div>
            <button onclick="removeKeyword('${id}')" class="text-red-600 hover:text-red-800 p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </div>
    `;
}



// Update category
function updateCategory(id, field, value) {
    const category = keywordsData.keywords.find(k => k.id == id);
    if (category) {
        category[field] = field === 'weight' ? parseFloat(value) : value;
        renderKeywords();
    }
}

// Update word
function updateWord(categoryId, wordIndex, newValue) {
    const category = keywordsData.keywords.find(k => k.id == categoryId);
    if (category && Array.isArray(category.words)) {
        category.words[wordIndex] = newValue;
        renderKeywords();
    }
}

// Add word
async function addWord(categoryId) {
    const category = keywordsData.keywords.find(k => k.id == categoryId);
    if (category) {
        if (!Array.isArray(category.words)) {
            category.words = [];
        }
        category.words.push('nueva palabra');
        
        // Guardar inmediatamente en BD
        try {
            const response = await fetch('/api/tracker/save_keywords.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_category',
                    data: {
                        id: category.id,
                        label: category.label,
                        weight: category.weight,
                        words: category.words
                    }
                })
            });
            
            const data = await response.json();
            if (data.success) {
                renderKeywords();
                showMessage('Palabra clave agregada y guardada', 'success');
            } else {
                showMessage('Error guardando: ' + data.error, 'error');
            }
        } catch (error) {
            showMessage('Error al guardar palabra', 'error');
        }
    }
}

// Remove word
function removeWord(categoryId, wordIndex) {
    const category = keywordsData.keywords.find(k => k.id == categoryId);
    if (category && Array.isArray(category.words)) {
        category.words.splice(wordIndex, 1);
        renderKeywords();
    }
}

// Delete category
async function deleteCategory(id) {
    if (!confirm('¿Estás seguro de eliminar esta categoría?')) return;
    
    try {
        const response = await fetch('/api/tracker/save_keywords.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_category',
                data: { id }
            })
        });
        
        const data = await response.json();
        if (data.success) {
            showMessage('Categoría eliminada', 'success');
            loadKeywordsData();
        } else {
            showMessage('Error: ' + data.error, 'error');
        }
    } catch (error) {
        showMessage('Error al eliminar categoría', 'error');
    }
}

// Save keywords
async function saveKeywords() {
    try {
        const saveBtn = document.getElementById('save-btn');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Guardando...';
        
        // Save all categories
        for (const category of keywordsData.keywords) {
            const response = await fetch('/api/tracker/save_keywords.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_category',
                    data: {
                        id: category.id,
                        label: category.label,
                        weight: category.weight,
                        words: category.words
                    }
                })
            });
            
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error);
            }
        }
        
        showMessage('Keywords guardadas exitosamente', 'success');
        loadKeywordsData();
        
    } catch (error) {
        showMessage('Error al guardar: ' + error.message, 'error');
    } finally {
        const saveBtn = document.getElementById('save-btn');
        saveBtn.disabled = false;
        saveBtn.textContent = 'Guardar Cambios';
    }
}

// Show message
function showMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 ${
        type === 'success' ? 'bg-green-600' : 'bg-red-600'
    }`;
    messageDiv.textContent = message;
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.remove();
    }, 3000);
}