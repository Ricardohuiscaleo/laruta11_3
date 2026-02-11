// Autenticación para admin
class AdminAuth {
    static async login(username, password) {
        try {
            const response = await fetch('/api/admin_auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'login', username, password })
            });
            return await response.json();
        } catch (error) {
            return { success: false, message: 'Error de conexión' };
        }
    }

    static async checkAuth() {
        try {
            const response = await fetch('/api/admin_auth.php');
            return await response.json();
        } catch (error) {
            return { success: false, authenticated: false };
        }
    }

    static async requireAuth() {
        const auth = await this.checkAuth();
        if (!auth.authenticated) {
            this.showLoginModal();
            return false;
        }
        return true;
    }

    static showLoginModal() {
        const modal = document.createElement('div');
        modal.innerHTML = `
            <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                <div style="background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 400px;">
                    <h2 style="margin: 0 0 1rem 0; text-align: center; color: #1f2937;">🍔 La Ruta 11 Admin</h2>
                    <form id="admin-login-form">
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Usuario:</label>
                            <input type="text" id="admin-username" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px;" value="admin" required>
                        </div>
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Contraseña:</label>
                            <input type="password" id="admin-password" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px;" value="ruta11admin" required>
                        </div>
                        <button type="submit" style="width: 100%; background: #ea580c; color: white; padding: 0.75rem; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">
                            Iniciar Sesión
                        </button>
                    </form>
                    <div id="login-error" style="margin-top: 1rem; color: #dc2626; text-align: center; display: none;"></div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        document.getElementById('admin-login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('admin-username').value;
            const password = document.getElementById('admin-password').value;
            
            const result = await AdminAuth.login(username, password);
            
            if (result.success) {
                document.body.removeChild(modal);
                location.reload();
            } else {
                const errorDiv = document.getElementById('login-error');
                errorDiv.textContent = result.message;
                errorDiv.style.display = 'block';
            }
        });
    }
}

// Auto-verificar autenticación al cargar
document.addEventListener('DOMContentLoaded', async () => {
    if (window.location.pathname.includes('/admin/')) {
        await AdminAuth.requireAuth();
    }
});