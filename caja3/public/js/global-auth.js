// Global Authentication System for Jobs Tracker SaaS

class JobsTrackerAuth {
    constructor() {
        this.currentUser = null;
        this.isAuthenticated = false;
        this.authPromise = null;
        
        // Initialize from cache immediately
        this.initFromCache();
    }
    
    // Initialize from cache on construction
    initFromCache() {
        const cachedUser = this.getCachedUser();
        if (cachedUser) {
            this.currentUser = cachedUser;
            this.isAuthenticated = true;
            console.log('🔐 Global Auth: Initialized from cache:', cachedUser.nombre);
        }
    }

    // Single authentication check - shared across all pages
    async authenticate() {
        // If already authenticating, return the same promise
        if (this.authPromise) {
            return this.authPromise;
        }

        // If already authenticated, return immediately
        if (this.isAuthenticated && this.currentUser) {
            console.log('🔐 Global Auth: Using cached authentication');
            return { success: true, user: this.currentUser };
        }

        // Check sessionStorage first
        const cachedUser = this.getCachedUser();
        if (cachedUser) {
            console.log('🔐 Global Auth: Using session cache');
            this.currentUser = cachedUser;
            this.isAuthenticated = true;
            return { success: true, user: this.currentUser };
        }

        console.log('🔐 Global Auth: Starting fresh authentication...');

        this.authPromise = this._performAuth();
        const result = await this.authPromise;
        this.authPromise = null;

        return result;
    }

    // Cache user in session
    cacheUser(user) {
        try {
            sessionStorage.setItem('jobsTracker_user', JSON.stringify({
                user: user,
                timestamp: Date.now()
            }));
        } catch (e) {
            console.warn('Could not cache user');
        }
    }

    // Get cached user (valid for 30 minutes)
    getCachedUser() {
        try {
            const cached = sessionStorage.getItem('jobsTracker_user');
            if (!cached) return null;
            
            const data = JSON.parse(cached);
            const thirtyMinutes = 30 * 60 * 1000; // 30 minutos
            
            if (Date.now() - data.timestamp < thirtyMinutes) {
                return data.user;
            } else {
                sessionStorage.removeItem('jobsTracker_user');
                return null;
            }
        } catch (e) {
            return null;
        }
    }

    // Clear cache
    clearCache() {
        try {
            sessionStorage.removeItem('jobsTracker_user');
        } catch (e) {}
    }

    async _performAuth() {
        try {
            this.showDebug('Verificando sesión...');
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);
            
            const response = await fetch('/api/auth/tracker_check_session.php', {
                signal: controller.signal,
                cache: 'no-cache'
            });
            
            clearTimeout(timeoutId);
            
            this.showDebug(`Status: ${response.status}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const text = await response.text();
            this.showDebug(`Response: ${text.substring(0, 100)}...`);
            
            const data = JSON.parse(text);
            this.showDebug(`Auth: ${data.authenticated ? 'OK' : 'FAIL'}`);

            if (data.authenticated && data.user) {
                this.currentUser = data.user;
                this.isAuthenticated = true;
                this.cacheUser(data.user);
                console.log('🔐 Global Auth: User authenticated:', this.currentUser.nombre);
                return { success: true, user: this.currentUser };
            } else {
                console.log('🔐 Global Auth: Not authenticated');
                this.clearCache();
                return { success: false };
            }
        } catch (error) {
            this.showDebug(`Error: ${error.message}`);
            this.clearCache();
            return { success: false, error };
        }
    }
    
    showDebug(message) {
        const debugEl = document.getElementById('debug-info');
        if (debugEl) {
            debugEl.innerHTML += `<div>${new Date().toLocaleTimeString()}: ${message}</div>`;
        }
        console.log('🔐', message);
    }

    // Logout from all pages
    async logout() {
        try {
            await fetch('/api/auth/tracker_logout.php', { method: 'POST' });
        } catch (error) {
            console.error('Logout error:', error);
        }
        
        this.currentUser = null;
        this.isAuthenticated = false;
        this.clearCache();
        window.location.href = '/jobsTracker/login';
    }

    // Get current user (cached)
    getUser() {
        return this.currentUser;
    }

    // Check if authenticated (cached)
    isAuth() {
        return this.isAuthenticated;
    }

    // Setup UI for authenticated user
    setupAuthenticatedUI() {
        if (!this.currentUser) return;

        // Update sidebar
        const sidebarUserName = document.getElementById('sidebar-user-name');
        const sidebarUserAvatar = document.getElementById('sidebar-user-avatar');
        
        if (sidebarUserName) sidebarUserName.textContent = this.currentUser.nombre;
        if (sidebarUserAvatar) sidebarUserAvatar.src = this.currentUser.foto_perfil;

        // Update mobile
        const userAvatarMobile = document.getElementById('user-avatar-mobile');
        if (userAvatarMobile) userAvatarMobile.src = this.currentUser.foto_perfil;

        // Show UI elements
        const sidebar = document.getElementById('sidebar');
        const userInfoMobile = document.getElementById('user-info-mobile');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');

        if (sidebar) {
            sidebar.style.display = '';
            sidebar.classList.remove('hidden');
        }
        if (userInfoMobile) {
            userInfoMobile.classList.remove('hidden');
            userInfoMobile.classList.add('flex');
        }
        if (mobileMenuBtn) mobileMenuBtn.classList.remove('hidden');

        // Setup logout buttons
        this.setupLogoutButtons();
    }

    setupLogoutButtons() {
        const sidebarLogoutBtn = document.getElementById('sidebar-logout-btn');
        const mobileLogoutBtn = document.getElementById('mobile-logout-btn');

        if (sidebarLogoutBtn) {
            sidebarLogoutBtn.addEventListener('click', () => this.logout());
        }
        if (mobileLogoutBtn) {
            mobileLogoutBtn.addEventListener('click', () => this.logout());
        }
    }

    // Setup mobile menu toggle
    setupMobileMenu() {
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                const menu = document.getElementById('mobile-menu');
                if (menu) menu.classList.toggle('hidden');
            });
        }
    }
}

// Global instance
window.JobsAuth = new JobsTrackerAuth();

// Helper function for pages
window.initJobsTrackerAuth = async function() {
    if (window.location.pathname.includes('/login')) {
        return;
    }

    const result = await window.JobsAuth.authenticate();
    
    if (result.success) {
        window.JobsAuth.setupAuthenticatedUI();
        window.JobsAuth.setupMobileMenu();
        return true;
    } else {
        window.location.href = '/jobsTracker/login';
        return false;
    }
};

console.log('🔐 Global Auth System loaded');