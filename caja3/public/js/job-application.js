// Configuración común
const MAX_CHARS = 711;
const TIME_LIMIT_SECONDS = 11 * 60;
const PHONE_LENGTH = 12;
const maxScorePossible = 50;

let keywords = {};
let timerInterval;
let timeRemaining = TIME_LIMIT_SECONDS;

// Elementos del DOM
const phase1 = document.getElementById('phase1');
const phase2 = document.getElementById('phase2');
const startQuestionsButton = document.getElementById('startQuestionsButton');
const textareas = document.querySelectorAll('#phase2 textarea');
const compatibilityBar = document.getElementById('compatibilityBar');
const compatibilityText = document.getElementById('compatibilityText');
const skillsList = document.getElementById('skillsList');
const submitButton = document.getElementById('submitButton');
const timerDisplay = document.getElementById('timer');
const attemptCounterDisplay = document.getElementById('attemptCounter');
const pasteAlert = document.getElementById('paste-alert');
const phoneInput = document.getElementById('telefono');
const phoneValidator = document.getElementById('phone-validator');

// Verificar autenticación y cargar datos del usuario
let currentUser = null;

async function checkAuthentication() {
    try {
        const token = localStorage.getItem('auth_token');
        const headers = {};
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }
        
        const response = await fetch('/api/auth/jobs_check_session.php', {
            headers: headers
        });
        const data = await response.json();
        if (!data.authenticated) {
            showLoginScreen();
            return false;
        }
        currentUser = data.user;
        populateUserData();
        return true;
    } catch (error) {
        showLoginScreen();
        return false;
    }
}

// Poblar datos del usuario en el formulario
function populateUserData() {
    if (currentUser) {
        document.getElementById('nombre').value = currentUser.nombre || '';
        document.getElementById('email').value = currentUser.email || '';
        document.getElementById('email').readOnly = true;
        
        // Mostrar foto de perfil
        const profileImg = document.getElementById('profile-img');
        if (profileImg && currentUser.foto_perfil) {
            profileImg.src = currentUser.foto_perfil;
            profileImg.style.display = 'block';
        }
    }
}

// Keywords ya no se cargan en frontend - análisis en servidor
async function loadKeywords(position) {
    // Keywords procesadas en servidor por seguridad
    keywords = {};
}

// Mostrar pantalla de login
function showLoginScreen() {
    const position = window.location.pathname.includes('maestro') ? 'maestro' : 'cajero';
    const color = position === 'maestro' ? 'amber' : 'blue';
    const title = position === 'maestro' ? 'Maestro/a Sanguchero/a' : 'Cajero/a';
    
    document.body.innerHTML = `
        <div class="min-h-screen bg-gray-100 flex items-center justify-center">
            <div class="bg-white p-8 rounded-2xl shadow-lg max-w-md w-full mx-4">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-${color}-600 mb-2">La Ruta 11</h1>
                    <h2 class="text-xl font-semibold text-gray-800 mb-2">Postulación ${title}</h2>
                    <p class="text-gray-600">Necesitas iniciar sesión para continuar</p>
                </div>
                
                <div class="mb-6">
                    <div class="bg-${color}-50 border border-${color}-200 p-4 rounded-lg mb-6">
                        <p class="text-sm text-${color}-700">
                            Para postular como ${title} necesitas tener una cuenta en La Ruta 11. 
                            Inicia sesión con Google para continuar con tu postulación.
                        </p>
                    </div>
                </div>
                
                <button 
                    onclick="handleGoogleLogin()"
                    class="w-full bg-white border border-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-full flex items-center justify-center gap-3 hover:bg-gray-50 transition-colors shadow-sm mb-4"
                >
                    <svg class="w-6 h-6" viewBox="0 0 48 48">
                        <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12s5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24s8.955,20,20,20s20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"></path>
                        <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"></path>
                        <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"></path>
                        <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.574l6.19,5.238C39.901,35.636,44,30.138,44,24C44,22.659,43.862,21.35,43.611,20.083z"></path>
                    </svg>
                    Iniciar Sesión con Google
                </button>
                
                <div class="text-center">
                    <a href="/" class="text-sm text-gray-500 hover:text-gray-700">← Volver a La Ruta 11</a>
                </div>
            </div>
        </div>
    `;
}

function handleGoogleLogin() {
    window.location.href = '/api/auth/google/jobs_login.php?redirect=' + encodeURIComponent(window.location.href);
}

// Validador de teléfono
function setupPhoneValidator() {
    phoneInput.addEventListener('input', () => {
        let sanitizedValue = phoneInput.value.replace(/[^0-9+]/g, '');
        if (sanitizedValue.lastIndexOf('+') > 0) {
            sanitizedValue = '+' + sanitizedValue.replace(/\+/g, '');
        }
        if (sanitizedValue.length > PHONE_LENGTH) {
            sanitizedValue = sanitizedValue.slice(0, PHONE_LENGTH);
        }
        if (phoneInput.value !== sanitizedValue) {
            phoneInput.value = sanitizedValue;
        }
        const number = phoneInput.value;
        const len = number.length;

        if (len === 0) {
            phoneValidator.textContent = '';
            return;
        }
        if (number[0] !== '+') {
            phoneValidator.textContent = 'Oops! Te falta el signo más "+". Ejemplo: +569...';
            phoneValidator.className = 'text-xs mt-1 h-4 text-red-500';
            return;
        }
        if (len < PHONE_LENGTH) {
            const missing = PHONE_LENGTH - len;
            phoneValidator.textContent = `Te faltan ${missing} dígito(s).`;
            phoneValidator.className = 'text-xs mt-1 h-4 text-yellow-600';
            return;
        }
        if (len === PHONE_LENGTH) {
            phoneValidator.textContent = 'Número de teléfono válido.';
            phoneValidator.className = 'text-xs mt-1 h-4 text-green-500';
        }
    });
}

function isPhoneValid() {
    return phoneInput.value.length === PHONE_LENGTH && phoneInput.value.startsWith('+');
}

// Timer
function startTimer() {
    timerInterval = setInterval(() => {
        timeRemaining--;
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        // Actualizar tiempo transcurrido cada 10 segundos
        if (window.currentApplicationId && (TIME_LIMIT_SECONDS - timeRemaining) % 10 === 0) {
            updateTimeElapsed();
        }
        
        if (timeRemaining <= 60 && !timerDisplay.classList.contains('timer-warning')) {
            timerDisplay.classList.add('timer-warning');
        }
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            timerDisplay.textContent = "00:00";
            timerDisplay.classList.remove('timer-warning');
            timerDisplay.classList.add('text-red-600');
            disableForm();
        }
    }, 1000);
}

// Actualizar tiempo transcurrido en BD
async function updateTimeElapsed() {
    if (!window.currentApplicationId) return;
    
    try {
        const timeElapsed = TIME_LIMIT_SECONDS - timeRemaining;
        const formData = new FormData();
        formData.append('application_id', window.currentApplicationId);
        formData.append('time_elapsed', timeElapsed);
        
        await fetch('/api/jobs/update_time_elapsed.php', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('Error actualizando tiempo:', error);
    }
}

function disableForm() {
    textareas.forEach(textarea => {
        textarea.disabled = true;
        textarea.classList.add('bg-gray-200');
    });
    submitButton.disabled = true;
    submitButton.textContent = 'Tiempo Terminado';
    compatibilityText.textContent = 'El tiempo ha terminado. Recarga para un nuevo intento.';
}

async function trackInput(questionNumber, inputText, action) {
    if (!window.currentApplicationId) return;
    
    try {
        const formData = new FormData();
        formData.append('application_id', window.currentApplicationId);
        formData.append('question_number', questionNumber);
        formData.append('input_text', inputText);
        formData.append('action', action);
        
        await fetch('/api/jobs/track_input.php', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('Error tracking input:', error);
    }
}

async function updateAnswer(questionNumber, answerText) {
    if (!window.currentApplicationId) return;
    
    try {
        const formData = new FormData();
        formData.append('application_id', window.currentApplicationId);
        formData.append('question_number', questionNumber);
        formData.append('answer_text', answerText);
        
        await fetch('/api/jobs/update_answers.php', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('Error updating answer:', error);
    }
}

// Configurar textareas
function setupTextareas() {
    textareas.forEach((textarea) => {
        textarea.addEventListener('paste', (e) => {
            e.preventDefault();
            pasteAlert.classList.remove('hidden');
            setTimeout(() => pasteAlert.classList.add('hidden'), 2000);
        });
        const counter = document.createElement('div');
        counter.className = 'text-right text-sm text-gray-500 mt-1 transition-colors duration-300';
        textarea.parentNode.insertBefore(counter, textarea.nextSibling);
        textarea.setAttribute('maxlength', MAX_CHARS);
        const updateCounter = () => {
            const remaining = MAX_CHARS - textarea.value.length;
            counter.textContent = `${remaining} caracteres`;
            counter.classList.toggle('text-red-500', remaining < 50);
            counter.classList.toggle('text-yellow-600', remaining >= 50 && remaining < 150);
            counter.classList.toggle('text-gray-500', remaining >= 150);
        };
        textarea.addEventListener('input', () => {
            updateCounter();
            
            // Tracking en tiempo real
            if (window.currentApplicationId) {
                clearTimeout(textarea.trackingTimer);
                textarea.trackingTimer = setTimeout(() => {
                    const questionNumber = Array.from(textareas).indexOf(textarea) + 1;
                    trackInput(questionNumber, textarea.value, 'typing');
                    updateAnswer(questionNumber, textarea.value);
                }, 500);
            }
            
            // Debounce para evitar muchas llamadas al servidor
            clearTimeout(textarea.debounceTimer);
            textarea.debounceTimer = setTimeout(calificarRespuestas, 1000);
        });
        
        textarea.addEventListener('blur', () => {
            if (window.currentApplicationId) {
                const questionNumber = Array.from(textareas).indexOf(textarea) + 1;
                trackInput(questionNumber, textarea.value, 'completed');
                updateAnswer(questionNumber, textarea.value);
            }
        });
        updateCounter();
    });
}

// Calificador
// Función para hashear texto
function hashText(text) {
    return CryptoJS.SHA256(text + 'salt_ruta11_2024').toString();
}

function calificarRespuestas() {
    if (timeRemaining <= 0) return;
    const textoCompleto = Array.from(textareas).map(t => t.value).join(' ').toLowerCase();
    if (textoCompleto.trim().length < 50) {
        updateUI(0);
        return;
    }
    
    // Enviar texto al servidor para análisis
    analyzeTextOnServer(textoCompleto);
}

// Análisis en el servidor
async function analyzeTextOnServer(texto) {
    try {
        const formData = new FormData();
        formData.append('texto', texto);
        formData.append('position', window.location.pathname.includes('maestro') ? 'maestro_sanguchero' : 'cajero');
        
        const response = await fetch('/api/jobs/analyze_text.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            const percentage = data.percentage;
            const skillsDetected = data.skills;
            updateUI(percentage, skillsDetected);
        }
    } catch (error) {
        console.error('Error analizando texto:', error);
    }
}

function updateUI(percentage, skillsDetected = {}) {
    if (timeRemaining <= 0 && percentage > 0) return;
    const roundedPercentage = Math.round(percentage);
    compatibilityBar.style.width = `${roundedPercentage}%`;
    compatibilityBar.textContent = `${roundedPercentage}%`;
    let text = 'Esperando...';
    if (roundedPercentage > 0) {
        if (roundedPercentage < 20) { text = 'Diamante en Bruto'; } 
        else if (roundedPercentage < 40) { text = 'Talento Emergente'; } 
        else if (roundedPercentage < 60) { text = 'Potencial Prometedor'; } 
        else if (roundedPercentage < 80) { text = 'Candidato Sólido'; } 
        else { text = 'Perfil Excepcional'; }
    }
    compatibilityText.textContent = text;
    compatibilityText.classList.toggle('text-orange-600', roundedPercentage > 0 && roundedPercentage < 20);
    compatibilityText.classList.toggle('text-yellow-600', roundedPercentage >= 20 && roundedPercentage < 40);
    compatibilityText.classList.toggle('text-blue-600', roundedPercentage >= 40 && roundedPercentage < 60);
    compatibilityText.classList.toggle('text-green-600', roundedPercentage >= 60 && roundedPercentage < 80);
    compatibilityText.classList.toggle('text-emerald-700', roundedPercentage >= 80);
    skillsList.innerHTML = '';
    if (Object.keys(skillsDetected).length > 0) {
         const sortedSkills = Object.entries(skillsDetected).sort(([,a],[,b]) => b.count - a.count);
         sortedSkills.forEach(([key, { count, label }]) => {
            const li = document.createElement('li');
            li.className = 'flex items-center fade-in';
            li.innerHTML = `<svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> <span>${label}</span>`;
            skillsList.appendChild(li);
         });
    } else if (roundedPercentage > 0) {
        skillsList.innerHTML = '<li class="text-gray-500">Analizando...</li>';
    }
    submitButton.disabled = roundedPercentage < 10;
}

// Verificar aplicaciones completadas
async function checkCompletedApplications(position) {
    try {
        const response = await fetch(`/api/jobs/get_application_summary.php?position=${position}`);
        const data = await response.json();
        
        if (data.success && data.has_completed) {
            // Agregar botón de resumen
            const summaryButton = document.createElement('button');
            summaryButton.type = 'button';
            summaryButton.className = 'w-full bg-green-500 text-white font-bold py-3 px-4 rounded-lg hover:bg-green-600 transition-colors mt-4';
            summaryButton.textContent = 'Ver Mi Resumen';
            summaryButton.onclick = () => showApplicationSummary(data.data, position);
            
            // Insertar después del botón principal
            startQuestionsButton.parentNode.insertBefore(summaryButton, startQuestionsButton.nextSibling);
        }
    } catch (error) {
        console.error('Error verificando aplicaciones completadas:', error);
    }
}

// Mostrar resumen de aplicación completada
function showApplicationSummary(appData, position) {
    const positionName = position === 'maestro_sanguchero' ? 'Maestro/a Sanguchero/a' : 'Cajero/a';
    const positionColor = position === 'maestro_sanguchero' ? 'amber' : 'blue';
    const positionIcon = position === 'maestro_sanguchero' ? '👨‍🍳' : '💼';
    const completedDate = new Date(appData.completed_at).toLocaleDateString('es-CL', { timeZone: 'America/Santiago' });
    const completedTime = new Date(appData.completed_at).toLocaleTimeString('es-CL', { 
        timeZone: 'America/Santiago',
        hour: '2-digit', 
        minute: '2-digit',
        hour12: false
    });
    
    document.body.innerHTML = `
        <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl p-8 w-full max-w-4xl">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="w-20 h-20 bg-gradient-to-br from-${positionColor}-400 to-${positionColor}-500 rounded-full flex items-center justify-center mx-auto mb-6">
                        <span class="text-4xl">${positionIcon}</span>
                    </div>
                    <h1 class="text-4xl font-bold text-gray-900 mb-2">Resumen de tu Postulación</h1>
                    <p class="text-xl text-gray-600">Tu evaluación para <strong>${positionName}</strong></p>
                </div>

                <!-- Resumen -->
                <div class="grid md:grid-cols-2 gap-8 mb-8">
                    <!-- Información de la postulación -->
                    <div class="bg-gray-50 rounded-2xl p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Detalles de tu Postulación</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Posición:</span>
                                <span class="font-semibold text-${positionColor}-600">${positionName}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Completada:</span>
                                <span class="font-semibold">${completedDate} a las ${completedTime}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Intento:</span>
                                <span class="font-semibold">#${appData.attempts} de ${appData.total_attempts}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Estado:</span>
                                <span class="font-semibold text-green-600">Completada</span>
                            </div>
                        </div>
                    </div>

                    <!-- Score -->
                    <div class="bg-gradient-to-br from-${positionColor}-50 to-${positionColor}-100 rounded-2xl p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Tu Evaluación</h3>
                        <div class="text-center">
                            <div class="text-5xl font-bold text-${positionColor}-600 mb-2">${Math.min(100, Math.round(appData.score))}%</div>
                            <p class="text-${positionColor}-700 font-medium mb-4">
                                ${appData.score >= 80 ? 'Perfil Excepcional' : appData.score >= 60 ? 'Candidato Sólido' : appData.score >= 40 ? 'Potencial Prometedor' : appData.score >= 20 ? 'Talento Emergente' : 'Diamante en Bruto'}
                            </p>
                            <div class="w-full bg-white/50 rounded-full h-3">
                                <div class="bg-${positionColor}-500 h-3 rounded-full" style="width: ${Math.min(100, Math.round(appData.score))}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estado actual -->
                <div class="bg-blue-50 rounded-2xl p-6 mb-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="text-2xl">📞</span>
                        Estado Actual
                    </h3>
                    <div class="text-center">
                        <div class="inline-flex items-center gap-2 bg-yellow-100 text-yellow-800 px-4 py-2 rounded-full text-sm font-medium mb-4">
                            ⏳ En revisión por nuestro equipo
                        </div>
                        <div id="contact-countdown" class="mb-4">
                            <p class="text-gray-700 font-medium mb-2">Te contactaremos antes de:</p>
                            <div class="flex justify-center gap-4">
                                <div class="bg-white rounded-lg p-3 shadow-sm">
                                    <div id="days-left" class="text-2xl font-bold text-blue-600">--</div>
                                    <div class="text-xs text-gray-500">Días</div>
                                </div>
                                <div class="bg-white rounded-lg p-3 shadow-sm">
                                    <div id="hours-left" class="text-2xl font-bold text-blue-600">--</div>
                                    <div class="text-xs text-gray-500">Horas</div>
                                </div>
                                <div class="bg-white rounded-lg p-3 shadow-sm">
                                    <div id="minutes-left" class="text-2xl font-bold text-blue-600">--</div>
                                    <div class="text-xs text-gray-500">Minutos</div>
                                </div>
                            </div>
                        </div>
                        <p class="text-gray-600 text-sm">
                            Si tu perfil encaja con lo que buscamos, nos pondremos en contacto contigo.
                        </p>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <button onclick="window.location.href='/jobs/${position === 'maestro_sanguchero' ? 'maestro-sanguchero' : 'cajero'}'" class="bg-green-500 text-white font-bold py-3 px-8 rounded-xl hover:bg-green-600 transition-colors">
                        Hacer Nuevo Intento
                    </button>
                    <button onclick="window.location.href='/jobs/'" class="bg-gray-500 text-white font-bold py-3 px-8 rounded-xl hover:bg-gray-600 transition-colors">
                        Volver al Inicio
                    </button>
                </div>

                <!-- Footer -->
                <div class="text-center mt-8 pt-6 border-t border-gray-200">
                    <p class="text-gray-500 text-sm">
                        ID de postulación: <span class="font-mono">${appData.id}</span>
                    </p>
                </div>
            </div>
        </div>
    `;
    
    // Iniciar cuenta regresiva de 3 días
    startContactCountdown(appData.completed_at);
}

// Cargar datos de aplicación previa
async function loadPreviousApplication(position) {
    try {
        const response = await fetch(`/api/jobs/get_application_data.php?position=${position}`);
        const data = await response.json();
        
        if (data.success && data.has_previous) {
            const appData = data.data;
            
            // Autocompletar formulario silenciosamente
            document.getElementById('telefono').value = appData.telefono || '';
            document.getElementById('instagram').value = appData.instagram || '';
            document.getElementById('nacionalidad').value = appData.nacionalidad || '';
            document.getElementById('genero').value = appData.genero || '';
            
            // Marcar requisitos
            if (appData.requisitos_legales) {
                appData.requisitos_legales.forEach(req => {
                    const checkbox = document.querySelector(`input[value="${req}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
            
            // Marcar cursos
            if (appData.curso_manipulador) {
                const cursoManipulador = document.querySelector(`input[name="curso_manipulador"][value="${appData.curso_manipulador}"]`);
                if (cursoManipulador) cursoManipulador.checked = true;
            }
            
            if (appData.curso_cajero) {
                const cursoCajero = document.querySelector(`input[name="curso_cajero"][value="${appData.curso_cajero}"]`);
                if (cursoCajero) cursoCajero.checked = true;
            }
            
            // Cambiar texto del botón según intentos
            if (appData.total_attempts > 0) {
                startQuestionsButton.textContent = `Intento #${appData.total_attempts + 1}`;
            }
            
            // Verificar si hay aplicaciones completadas
            checkCompletedApplications(position);
            
            return true; // Indica que se cargaron datos previos
        }
    } catch (error) {
        console.error('Error cargando aplicación previa:', error);
    }
    
    return false; // No hay aplicación previa o usuario no quiere continuar
}

// Pantalla de resumen
function showSummaryScreen(answers, finalScore, position) {
    clearInterval(timerInterval);
    
    const positionName = position === 'maestro_sanguchero' ? 'Maestro/a Sanguchero/a' : 'Cajero/a';
    const positionColor = position === 'maestro_sanguchero' ? 'amber' : 'blue';
    const positionIcon = position === 'maestro_sanguchero' ? '👨‍🍳' : '💼';
    
    document.body.innerHTML = `
        <div class="min-h-screen bg-white flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-lg border border-gray-200 p-8 w-full max-w-4xl">
                <!-- Header con animación -->
                <div class="text-center mb-12">
                    <div class="relative mb-8">
                        <div class="w-24 h-24 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg animate-pulse">
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="absolute -top-2 -right-2 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center animate-bounce">
                            <span class="text-lg">✨</span>
                        </div>
                    </div>
                    <h1 class="text-4xl font-bold text-gray-900 mb-4">
                        ¡Postulación Completada!
                    </h1>
                    <p class="text-xl text-gray-700 max-w-2xl mx-auto leading-relaxed">
                        Tu evaluación para <span class="font-bold text-${positionColor}-600">${positionName}</span> ha sido enviada exitosamente
                    </p>
                </div>

                <!-- Resumen mejorado -->
                <div class="grid lg:grid-cols-3 gap-8 mb-12">
                    <!-- Score destacado -->
                    <div class="lg:col-span-1">
                        <div class="bg-gradient-to-br from-${positionColor}-50 to-${positionColor}-100 rounded-3xl p-8 text-center border border-${positionColor}-200 shadow-lg">
                            <h3 class="text-lg font-bold text-gray-900 mb-6">Tu Evaluación</h3>
                            <div class="relative mb-6">
                                <div class="text-6xl font-black text-${positionColor}-600 mb-2">${Math.min(100, finalScore)}%</div>
                                <div class="absolute -top-2 -right-2 w-6 h-6 bg-green-400 rounded-full animate-ping"></div>
                            </div>
                            <p class="text-${positionColor}-700 font-semibold text-lg mb-6">
                                ${finalScore >= 80 ? '🎆 Perfil Excepcional' : finalScore >= 60 ? '🚀 Candidato Sólido' : finalScore >= 40 ? '🌱 Potencial Prometedor' : finalScore >= 20 ? '✨ Talento Emergente' : '💎 Diamante en Bruto'}
                            </p>
                            <div class="w-full bg-white/60 rounded-full h-4 shadow-inner">
                                <div class="bg-gradient-to-r from-${positionColor}-500 to-${positionColor}-600 h-4 rounded-full transition-all duration-2000 shadow-sm" style="width: ${Math.min(100, finalScore)}%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detalles de la postulación -->
                    <div class="lg:col-span-2">
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-3xl p-8 border border-gray-200 shadow-lg">
                            <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                                <span class="text-3xl">${positionIcon}</span>
                                Detalles de tu Postulación
                            </h3>
                            <div class="grid md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between p-3 bg-white rounded-xl shadow-sm">
                                        <span class="text-gray-600 font-medium">Posición:</span>
                                        <span class="font-bold text-${positionColor}-600">${positionName}</span>
                                    </div>
                                    <div class="flex items-center justify-between p-3 bg-white rounded-xl shadow-sm">
                                        <span class="text-gray-600 font-medium">Fecha:</span>
                                        <span class="font-semibold text-gray-900">${new Date().toLocaleDateString('es-CL', { timeZone: 'America/Santiago' })}</span>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between p-3 bg-white rounded-xl shadow-sm">
                                        <span class="text-gray-600 font-medium">Tiempo:</span>
                                        <span class="font-semibold text-gray-900">${Math.floor((TIME_LIMIT_SECONDS - timeRemaining) / 60)} minutos</span>
                                    </div>
                                    <div class="flex items-center justify-between p-3 bg-white rounded-xl shadow-sm">
                                        <span class="text-gray-600 font-medium">ID:</span>
                                        <span class="font-mono text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">${window.currentApplicationId.slice(-8)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Próximos pasos mejorados -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-3xl p-8 mb-12 border border-blue-200 shadow-lg">
                    <h3 class="text-2xl font-bold text-gray-900 mb-8 text-center flex items-center justify-center gap-3">
                        <span class="text-3xl">🚀</span>
                        ¿Qué sigue ahora?
                    </h3>
                    <div class="grid md:grid-cols-3 gap-8">
                        <div class="text-center group">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg group-hover:scale-110 transition-transform duration-300">
                                <span class="text-white font-bold text-xl">1</span>
                            </div>
                            <h4 class="font-bold text-gray-900 mb-3 text-lg">🔍 Revisión</h4>
                            <p class="text-gray-600 leading-relaxed">Nuestro equipo revisará tu postulación en las próximas <strong>48 horas</strong></p>
                        </div>
                        <div class="text-center group">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg group-hover:scale-110 transition-transform duration-300">
                                <span class="text-white font-bold text-xl">2</span>
                            </div>
                            <h4 class="font-bold text-gray-900 mb-3 text-lg">📞 Contacto</h4>
                            <p class="text-gray-600 leading-relaxed">Si tu perfil encaja, te contactaremos vía <strong>WhatsApp</strong> o <strong>email</strong></p>
                        </div>
                        <div class="text-center group">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg group-hover:scale-110 transition-transform duration-300">
                                <span class="text-white font-bold text-xl">3</span>
                            </div>
                            <h4 class="font-bold text-gray-900 mb-3 text-lg">🏢 Entrevista</h4>
                            <p class="text-gray-600 leading-relaxed">Coordinaremos una <strong>entrevista personal</strong> en nuestro local</p>
                        </div>
                    </div>
                </div>

                <!-- Acciones mejoradas -->
                <div class="flex flex-col sm:flex-row gap-6 justify-center mb-8">
                    <button onclick="window.location.href='/jobs/'" class="bg-${positionColor}-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-${positionColor}-700 transition-colors">
                        🏠 Volver al Inicio
                    </button>
                </div>

                <!-- Footer mejorado -->
                <div class="text-center pt-8 border-t border-gray-200">
                    <div class="bg-gradient-to-r from-orange-100 to-red-100 rounded-2xl p-6 border border-orange-200">
                        <p class="text-lg font-semibold text-gray-800 mb-2">
                            ¡Gracias por tu interés en formar parte de La Ruta 11!
                        </p>
                        <p class="text-gray-600">
                            Juntos crearemos la mejor experiencia gastronómica del norte de Chile 🎆
                        </p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Limpiar localStorage
    localStorage.removeItem(`${position}Attempts`);
    
    // Iniciar cuenta regresiva de 3 días
    startContactCountdown(appData.completed_at);
}

// Cuenta regresiva para contacto (3 días desde completed_at)
function startContactCountdown(completedAt) {
    // Crear fecha desde MySQL timestamp (formato: 2025-07-27 21:01:00)
    const completedDate = new Date(completedAt.replace(' ', 'T')); // Convertir a formato ISO
    const contactDeadline = new Date(completedDate.getTime() + (3 * 24 * 60 * 60 * 1000)); // +3 días
    
    console.log('MySQL timestamp:', completedAt);
    console.log('Fecha completada:', completedDate.toLocaleString('es-CL'));
    console.log('Deadline contacto:', contactDeadline.toLocaleString('es-CL'));
    console.log('Ahora:', new Date().toLocaleString('es-CL'));
    
    function updateContactCountdown() {
        const now = new Date();
        const timeLeft = contactDeadline.getTime() - now.getTime();
        
        console.log('--- Cálculo de tiempo ---');
        console.log('Ahora:', now.toLocaleString('es-CL'));
        console.log('Deadline:', contactDeadline.toLocaleString('es-CL'));
        console.log('Tiempo restante (ms):', timeLeft);
        console.log('Tiempo restante (horas):', Math.round(timeLeft / (1000 * 60 * 60 * 100)) / 100);
        console.log('Es positivo?', timeLeft > 0);
        
        if (timeLeft > 0) {
            const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            
            document.getElementById('days-left').textContent = days.toString().padStart(2, '0');
            document.getElementById('hours-left').textContent = hours.toString().padStart(2, '0');
            document.getElementById('minutes-left').textContent = minutes.toString().padStart(2, '0');
        } else {
            // Tiempo expirado
            document.getElementById('days-left').textContent = '00';
            document.getElementById('hours-left').textContent = '00';
            document.getElementById('minutes-left').textContent = '00';
            
            // Cambiar mensaje
            const countdownDiv = document.getElementById('contact-countdown');
            countdownDiv.innerHTML = `
                <div class="bg-red-100 border border-red-300 rounded-lg p-4">
                    <p class="text-red-700 font-medium">
                        El período de contacto ha finalizado. Si no recibiste respuesta, puedes hacer un nuevo intento.
                    </p>
                </div>
            `;
            
            clearInterval(contactInterval);
        }
    }
    
    updateContactCountdown();
    const contactInterval = setInterval(updateContactCountdown, 60000); // Actualizar cada minuto
}

// Inicialización
async function initJobApplication(position) {
    const isAuthenticated = await checkAuthentication();
    if (!isAuthenticated) return;
    
    // Intentar cargar aplicación previa
    const hasPrevious = await loadPreviousApplication(position);
    
    await loadKeywords(position);
    setupPhoneValidator();
    setupTextareas();
    
    startQuestionsButton.addEventListener('click', async () => {
        const nombre = document.getElementById('nombre').value;
        const telefono = document.getElementById('telefono').value;
        const instagram = document.getElementById('instagram').value;
        const nacionalidad = document.getElementById('nacionalidad').value;
        const genero = document.getElementById('genero').value;
        const requisitos = Array.from(document.querySelectorAll('input[name="requisitos[]"]:checked')).map(cb => cb.value);
        
        // Validaciones
        if (!nombre || !telefono || !nacionalidad || !genero) {
            alert('Por favor, completa todos los campos obligatorios.');
            return;
        }
        
        if (requisitos.length < 3) {
            alert('Debes aceptar todos los requisitos legales para continuar.');
            return;
        }
        
        if (!isPhoneValid()) {
            alert('Por favor, ingresa un número de teléfono válido (ej: +56912345678).');
            return;
        }
        
        // Validar curso obligatorio para maestro sanguchero
        if (position === 'maestro_sanguchero') {
            const cursoManipulador = document.querySelector('input[name="curso_manipulador"]:checked');
            if (!cursoManipulador) {
                alert('Debes indicar si tienes curso de manipulador de alimentos para continuar.');
                return;
            }
            if (cursoManipulador.value === 'no') {
                alert('El curso de manipulador de alimentos es obligatorio para esta posición.');
                return;
            }
        }
        
        // Deshabilitar botón mientras procesa
        startQuestionsButton.disabled = true;
        startQuestionsButton.textContent = 'Procesando...';
        
        try {
            // Llamar a start_application.php
            const formData = new FormData();
            formData.append('nombre', nombre);
            formData.append('telefono', telefono);
            formData.append('instagram', instagram);
            formData.append('nacionalidad', nacionalidad);
            formData.append('genero', genero);
            formData.append('position', position);
            
            // Agregar datos de cursos
            if (position === 'maestro_sanguchero') {
                const cursoManipulador = document.querySelector('input[name="curso_manipulador"]:checked');
                if (cursoManipulador) {
                    formData.append('curso_manipulador', cursoManipulador.value);
                }
            } else if (position === 'cajero') {
                const cursoCajero = document.querySelector('input[name="curso_cajero"]:checked');
                if (cursoCajero) {
                    formData.append('curso_cajero', cursoCajero.value);
                }
            }
            
            // Agregar requisitos como array
            requisitos.forEach(req => {
                formData.append('requisitos[]', req);
            });
            
            const response = await fetch('/api/jobs/start_application.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Guardar application_id para tracking
                window.currentApplicationId = result.application_id;
                
                // Continuar a preguntas
                phase1.classList.add('hidden');
                phase2.classList.remove('hidden');
                
                startTimer();
                
                // El número de intento se maneja en el servidor
                attemptCounterDisplay.textContent = startQuestionsButton.textContent;
                
                // Tracking ya configurado en setupTextareas
            } else {
                alert('Error: ' + result.error);
            }
        } catch (error) {
            console.log('Error de conexión en start_application, pero continuando');
            // Continuar con el flujo normal
            phase1.classList.add('hidden');
            phase2.classList.remove('hidden');
            startTimer();
        }
        
        // Rehabilitar botón
        startQuestionsButton.disabled = false;
        startQuestionsButton.textContent = 'Contestar Preguntas';
    });

    submitButton.addEventListener('click', async () => {
        if (!window.currentApplicationId) {
            alert('Error: No se encontró ID de aplicación. Recarga la página.');
            return;
        }
        
        submitButton.disabled = true;
        submitButton.textContent = 'Enviando...';
        
        // Preparar respuestas
        const answers = [
            {
                question: document.querySelector('label[for="pregunta1"]').textContent,
                answer: document.getElementById('pregunta1').value,
                time_spent: Math.floor((TIME_LIMIT_SECONDS - timeRemaining) / 3)
            },
            {
                question: document.querySelector('label[for="pregunta2"]').textContent,
                answer: document.getElementById('pregunta2').value,
                time_spent: Math.floor((TIME_LIMIT_SECONDS - timeRemaining) / 3)
            },
            {
                question: document.querySelector('label[for="pregunta3"]').textContent,
                answer: document.getElementById('pregunta3').value,
                time_spent: Math.floor((TIME_LIMIT_SECONDS - timeRemaining) / 3)
            }
        ];
        
        // Obtener score final de la barra y limitarlo a 100
        const compatibilityBarText = compatibilityBar.textContent || '0%';
        let finalScore = parseInt(compatibilityBarText.replace('%', '')) || 0;
        finalScore = Math.min(100, Math.max(0, finalScore)); // Limitar entre 0 y 100
        
        const formData = new FormData();
        formData.append('application_id', window.currentApplicationId);
        formData.append('answers', JSON.stringify(answers));
        formData.append('final_score', finalScore);
        
        try {
            const response = await fetch('/api/jobs/submit_application.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                showSummaryScreen(answers, finalScore, position);
            } else {
                alert('Error: ' + result.error);
                submitButton.disabled = false;
                submitButton.textContent = 'Enviar Postulación';
            }
        } catch (error) {
            // Si hay error de conexión pero la postulación se completó, mostrar pantalla de éxito
            console.log('Error de conexión, pero continuando con pantalla de éxito');
            showSummaryScreen(answers, finalScore, position);
        }
    });
}