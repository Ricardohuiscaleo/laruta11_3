// Candidate Detail JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Obtener ID y posición del candidato desde query parameters
    const urlParams = new URLSearchParams(window.location.search);
    const candidateId = urlParams.get('id');
    const position = urlParams.get('position');
    
    console.log('Candidate ID from query:', candidateId);
    console.log('Position from query:', position);
    
    if (!candidateId) {
        showError('ID de candidato no especificado en la URL');
        return;
    }
    
    loadCandidateDetail(candidateId, position);
});

// Load candidate detail with cache busting
async function loadCandidateDetail(candidateId, position) {
    try {
        const timestamp = Date.now();
        const url = `../../../api/tracker/get_candidate_detail.php?id=${candidateId}&_t=${timestamp}${position ? `&position=${position}` : ''}`;
        const response = await fetch(url);
        
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
        
        if (data.success) {
            renderCandidateDetail(data.data);
        } else {
            showError(data.error || 'Error cargando datos del candidato');
        }
        
    } catch (error) {
        console.error('Error loading candidate detail:', error);
        showError('Error de conexión al servidor');
    }
}

// Render candidate detail
function renderCandidateDetail(candidate) {
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('candidate-details').classList.remove('hidden');
    
    // Profile header
    document.getElementById('candidate-photo').src = candidate.foto_perfil || '/icon.png';
    document.getElementById('candidate-name').textContent = candidate.nombre;
    document.getElementById('candidate-position').textContent = 
        candidate.position === 'maestro_sanguchero' ? 'Maestro/a Sanguchero/a' : 'Cajero/a';
    
    // Contact info
    document.getElementById('candidate-phone').href = `tel:${candidate.telefono}`;
    document.getElementById('phone-text').textContent = candidate.telefono;
    
    if (candidate.instagram) {
        document.getElementById('candidate-instagram').classList.remove('hidden');
        document.getElementById('candidate-instagram').href = `https://instagram.com/${candidate.instagram}`;
        document.getElementById('instagram-text').textContent = candidate.instagram;
        document.getElementById('no-instagram').style.display = 'none';
    } else {
        document.getElementById('no-instagram').style.display = 'block';
    }
    
    // Score and attempts
    document.getElementById('candidate-score').textContent = `${Math.round(candidate.best_score || 0)}%`;
    document.getElementById('candidate-attempts').textContent = `${candidate.total_attempts} intento${candidate.total_attempts > 1 ? 's' : ''}`;
    
    // Personal info
    document.getElementById('candidate-nationality').textContent = candidate.nacionalidad || '-';
    document.getElementById('candidate-gender').textContent = candidate.genero || '-';
    document.getElementById('candidate-email').textContent = candidate.email || '-';
    
    // Courses en ficha principal - solo mostrar si es relevante para la posición
    console.log('Position:', candidate.position, 'Manipulador:', candidate.curso_manipulador, 'Cajero:', candidate.curso_cajero);
    
    // Manipulador de Alimentos - relevante para maestro_sanguchero
    const manipuladorElement = document.getElementById('curso-manipulador-ficha');
    if (manipuladorElement) {
        if (candidate.position === 'maestro_sanguchero') {
            manipuladorElement.innerHTML = candidate.curso_manipulador === 'si' ? 
                '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"><svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>Completado</span>' :
                '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">No completado</span>';
        } else {
            manipuladorElement.innerHTML = '<span class="text-xs text-gray-400">No requerido</span>';
        }
    }
    
    // Curso de Cajero - relevante para cajero
    const cajeroElement = document.getElementById('curso-cajero-ficha');
    if (cajeroElement) {
        if (candidate.position === 'cajero') {
            cajeroElement.innerHTML = candidate.curso_cajero === 'si' ? 
                '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"><svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>Completado</span>' :
                '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-600">No completado</span>';
        } else {
            cajeroElement.innerHTML = '<span class="text-xs text-gray-400">No requerido</span>';
        }
    }
    
    // Courses en sección secundaria (mantener compatibilidad)
    let hasCursos = false;
    if (candidate.curso_manipulador === 'si') {
        document.getElementById('curso-manipulador').classList.remove('hidden');
        hasCursos = true;
    }
    if (candidate.curso_cajero === 'si') {
        document.getElementById('curso-cajero').classList.remove('hidden');
        hasCursos = true;
    }
    if (hasCursos) {
        document.getElementById('no-cursos').style.display = 'none';
    } else {
        document.getElementById('no-cursos').style.display = 'block';
    }
    
    // Status
    document.getElementById('candidate-status').innerHTML = getStatusBadge(candidate.status);
    document.getElementById('last-attempt').textContent = 
        new Date(candidate.last_attempt).toLocaleString('es-CL');
    
    if (candidate.completed_at) {
        document.getElementById('completed-date').classList.remove('hidden');
        document.getElementById('completed-at').textContent = 
            new Date(candidate.completed_at).toLocaleString('es-CL');
    }
    
    // Keyword Analysis - buscar en el último intento completado
    let keywordAnalysisFound = false;
    if (candidate.attempts && candidate.attempts.length > 0) {
        for (let attempt of candidate.attempts) {
            if (attempt.keyword_analysis) {
                const keywordHtml = typeof attempt.keyword_analysis === 'object' ? 
                    JSON.stringify(attempt.keyword_analysis, null, 2) : attempt.keyword_analysis;
                document.getElementById('keyword-analysis').innerHTML = `<pre class="whitespace-pre-wrap text-xs">${keywordHtml}</pre>`;
                keywordAnalysisFound = true;
                break;
            }
        }
    }
    
    if (!keywordAnalysisFound && candidate.keyword_analysis) {
        const keywordHtml = typeof candidate.keyword_analysis === 'object' ? 
            JSON.stringify(candidate.keyword_analysis, null, 2) : candidate.keyword_analysis;
        document.getElementById('keyword-analysis').innerHTML = `<pre class="whitespace-pre-wrap text-xs">${keywordHtml}</pre>`;
    }
    
    // Requisitos Legales en ficha
    if (candidate.requisitos_legales) {
        let requisitosData = candidate.requisitos_legales;
        
        // Si es string, intentar decodificar JSON
        if (typeof requisitosData === 'string') {
            try {
                requisitosData = JSON.parse(requisitosData);
            } catch (e) {
                // Si no es JSON válido, usar como string
            }
        }
        
        // Mostrar requisitos en ficha
        if (Array.isArray(requisitosData)) {
            const requisitosMap = {
                'mayor_edad': 'Soy mayor de 18 años',
                'cedula_vigente': 'Tengo cédula de identidad vigente',
                'permiso_trabajo': 'Tengo permiso legal para trabajar en Chile'
            };
            
            const requisitosHtml = requisitosData.map(req => {
                const texto = requisitosMap[req] || req.replace('_', ' ').toUpperCase();
                return `<div class="bg-green-50 p-3 rounded-lg flex items-center">
                    <svg class="w-4 h-4 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-sm font-medium text-green-800">${texto}</span>
                </div>`;
            }).join('');
            
            document.getElementById('requisitos-legales-ficha').innerHTML = requisitosHtml;
        }
        
        // Requisitos en sección secundaria - DESHABILITADO (ya está en ficha)
        // document.getElementById('requisitos-section').classList.remove('hidden');
    }
    
    // Datos Técnicos en ficha - con validación de elementos
    const techElements = {
        'tech-id-ficha': candidate.id || '-',
        'tech-user-id-ficha': candidate.user_id || '-',
        'tech-status-ficha': candidate.status || '-',
        'tech-attempts-ficha': candidate.total_attempts || '-',
        'tech-created-ficha': candidate.created_at ? new Date(candidate.created_at).toLocaleString('es-CL') : '-',
        'tech-updated-ficha': candidate.updated_at ? new Date(candidate.updated_at).toLocaleString('es-CL') : '-',
        'tech-completed-ficha': candidate.completed_at ? new Date(candidate.completed_at).toLocaleString('es-CL') : 'No completado',
        'tech-time-elapsed-ficha': candidate.time_elapsed ? `${Math.round(candidate.time_elapsed / 60)} min` : '-'
    };
    
    Object.keys(techElements).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = techElements[id];
        }
    });
    
    // Datos Técnicos en sección secundaria - DESHABILITADO (ya está en ficha)
    // Los datos técnicos ahora solo se muestran en la ficha principal
    
    // Fecha de último intento en ficha
    document.getElementById('last-attempt-ficha').textContent = 
        new Date(candidate.last_attempt).toLocaleString('es-CL');
    
    // Render attempts
    renderAttempts(candidate.attempts);
}

// Render attempts history
function renderAttempts(attempts) {
    const container = document.getElementById('attempts-list');
    
    if (!attempts || attempts.length === 0) {
        container.innerHTML = '<div class="p-6 text-center text-gray-500">No hay intentos registrados</div>';
        return;
    }
    
    container.innerHTML = `
        <div class="p-4 sm:p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                ${attempts.slice().reverse().map((attempt, index) => {
                    const date = new Date(attempt.created_at).toLocaleString('es-CL');
                    const completedDate = attempt.completed_at ? 
                        new Date(attempt.completed_at).toLocaleString('es-CL') : null;
                    
                    // Sistema de colores bonito
                    const colors = [
                        { bg: 'bg-blue-50', border: 'border-blue-200', header: 'bg-blue-100', accent: 'text-blue-600' },
                        { bg: 'bg-green-50', border: 'border-green-200', header: 'bg-green-100', accent: 'text-green-600' },
                        { bg: 'bg-purple-50', border: 'border-purple-200', header: 'bg-purple-100', accent: 'text-purple-600' },
                        { bg: 'bg-orange-50', border: 'border-orange-200', header: 'bg-orange-100', accent: 'text-orange-600' },
                        { bg: 'bg-pink-50', border: 'border-pink-200', header: 'bg-pink-100', accent: 'text-pink-600' },
                        { bg: 'bg-indigo-50', border: 'border-indigo-200', header: 'bg-indigo-100', accent: 'text-indigo-600' },
                        { bg: 'bg-teal-50', border: 'border-teal-200', header: 'bg-teal-100', accent: 'text-teal-600' },
                        { bg: 'bg-red-50', border: 'border-red-200', header: 'bg-red-100', accent: 'text-red-600' }
                    ];
                    const color = colors[index % colors.length];
                    
                    return `
                        <div class="bg-white border ${color.border} rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                            <!-- Header de la tarjeta -->
                            <div class="${color.header} px-4 py-3 border-b ${color.border}">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-semibold ${color.accent}" style="font-size: clamp(0.875rem, 2.5vw, 1rem);">Intento #${index + 1}</h4>
                                    <div class="flex items-center gap-2">
                                        <div class="text-center">
                                            <div class="font-bold ${getScoreColor(attempt.score)}" style="font-size: clamp(1.25rem, 3vw, 1.5rem);">${Math.round(attempt.score || 0)}%</div>
                                        </div>
                                        ${getStatusBadge(attempt.status)}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Información del intento -->
                            <div class="p-4">
                                <div class="grid grid-cols-1 gap-2 mb-4" style="font-size: clamp(0.75rem, 2vw, 0.875rem);">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 font-medium">Fecha:</span>
                                        <span class="text-gray-900 font-semibold">${date}</span>
                                    </div>
                                    ${completedDate ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 font-medium">Completado:</span>
                                        <span class="text-gray-900 font-semibold">${completedDate}</span>
                                    </div>
                                    ` : ''}
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 font-medium">Tiempo:</span>
                                        <span class="text-gray-900 font-semibold">${Math.round(attempt.time_elapsed / 60) || 0} min</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 font-medium">ID:</span>
                                        <span class="text-gray-900 font-mono text-xs">${attempt.id.slice(-8)}</span>
                                    </div>
                                </div>
                                
                                <!-- Skills detectadas -->
                                ${attempt.detected_skills ? `
                                    <div class="mb-4">
                                        <h6 class="font-semibold ${color.accent} text-sm border-b pb-1 mb-2">Skills Detectadas:</h6>
                                        <div class="flex flex-wrap gap-1">
                                            ${Object.entries(attempt.detected_skills).map(([key, skill]) => `
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${color.bg} ${color.accent} border ${color.border}">
                                                    ${skill.label} (${skill.count})
                                                </span>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                                
                                <!-- Respuestas -->
                                ${attempt.answers && attempt.answers.length > 0 ? `
                                    <div class="space-y-3">
                                        <h6 class="font-semibold ${color.accent} text-sm border-b pb-1">Respuestas:</h6>
                                        ${attempt.answers.map((answer, answerIndex) => {
                                            const questionText = getQuestionText(attempt.position, answerIndex + 1);
                                            return `
                                            <div class="${color.bg} rounded-lg p-3 border ${color.border}">
                                                <div class="mb-3">
                                                    <h6 class="font-semibold ${color.accent} mb-1" style="font-size: clamp(0.75rem, 2vw, 0.875rem);">Pregunta ${answerIndex + 1}:</h6>
                                                    <p class="text-gray-700 line-clamp-3" style="font-size: clamp(0.625rem, 1.8vw, 0.75rem);">${questionText}</p>
                                                </div>
                                                <div class="bg-white rounded p-3 border border-gray-100">
                                                    <h6 class="font-medium text-gray-800 mb-2" style="font-size: clamp(0.75rem, 2vw, 0.875rem);">Respuesta:</h6>
                                                    <p class="text-gray-900 line-clamp-4" style="font-size: clamp(0.75rem, 2vw, 0.875rem);">${answer.answer || 'Sin respuesta'}</p>
                                                </div>
                                            </div>
                                        `;
                                        }).join('')}
                                    </div>
                                ` : '<p class="text-gray-500 text-sm italic">Sin respuestas registradas</p>'}
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `;
}

// Render answers
function renderAnswers(answers) {
    if (!answers || answers.length === 0) return '';
    
    return `
        <div class="mt-4">
            <h5 class="font-medium text-gray-900 mb-3">Respuestas:</h5>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                ${answers.map((answer, index) => `
                    <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                        <div class="mb-3">
                            <h6 class="font-semibold text-gray-900 text-sm mb-2">Pregunta ${index + 1}</h6>
                            <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">${answer.question}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-sm text-gray-900 leading-relaxed line-clamp-6">${answer.answer || 'Sin respuesta'}</p>
                        </div>
                    </div>
                `).join('')}
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

// Get score color
function getScoreColor(score) {
    if (!score || score === 0) return 'text-gray-400';
    
    const scoreNum = Math.round(score);
    if (scoreNum >= 80) return 'text-green-600';
    else if (scoreNum >= 60) return 'text-blue-600';
    else if (scoreNum >= 40) return 'text-yellow-600';
    else if (scoreNum >= 20) return 'text-orange-600';
    else return 'text-red-600';
}

// Get question text based on position and question number
function getQuestionText(position, questionNumber) {
    const questions = {
        'cajero': {
            1: 'Llegas para abrir y notas que la caja registradora no enciende y hay una fila de 5 clientes esperando. El técnico no puede venir hasta la tarde. Describe paso a paso cómo organizas la atención, comunicas la situación a los clientes y te coordinas con el maestro sanguchero para mantener el servicio funcionando.',
            2: 'Un cliente llega muy molesto porque su pedido de ayer tenía la carne muy seca y quiere que le devuelvan el dinero, pero no tiene el ticket. Otros clientes están esperando y se nota la tensión. Describe cómo manejas esta situación para resolver el problema del cliente molesto, mantener la calma de los otros clientes y coordinar con cocina si es necesario.',
            3: 'Al final del día, revisas que varios clientes preguntaron por descuentos para estudiantes y familias numerosas. También notas que muchos piden el menú en inglés para turistas. Describe tu proceso de cierre de caja y limpieza, y luego propón dos ideas concretas para mejorar la experiencia del cliente basándote en estas observaciones.'
        },
        'maestro_sanguchero': {
            1: 'Llegas para abrir y al revisar el stock, notas que queda muy poca carne para la "Mechada Luco", nuestro sándwich más vendido. El proveedor no llega hasta la tarde. Describe paso a paso tu plan de acción desde ese momento hasta que abres, incluyendo cómo te coordinas con tu compañera cajera.',
            2: 'Plena hora punta. La plancha está llena y la cajera te canta un pedido nuevo, pero olvida mencionar que el cliente es alérgico al ajo. Te das cuenta justo cuando estás por entregar el sándwich. Narra cómo manejas este error crítico para garantizar la seguridad del cliente, tu comunicación con la cajera y cómo recuperas el ritmo del servicio.',
            3: 'Fue un día excelente, pero notaste que al menos cinco clientes preguntaron si teníamos opción vegetariana. Basado en esa observación, describe tu proceso de cierre y limpieza. Luego, ¿qué idea de sándwich vegetariano, con un toque nortino, le propondrías a tu compañera para implementar?'
        }
    };
    
    return questions[position]?.[questionNumber] || `Pregunta ${questionNumber}`;
}

// Show error
function showError(message) {
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('error').classList.remove('hidden');
    document.getElementById('error-message').textContent = message;
}