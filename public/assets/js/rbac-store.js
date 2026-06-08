/**
 * RBAC Store - Gestión reactiva de permisos en el cliente
 * @module rbac-store
 */

class RBACStore {
    constructor() {
        this.permissions = new Set();
        this.roleHierarchy = {};
        this.subscribers = new Set();
        this.currentRole = null;
        this.isInitialized = false;
    }

    normalizePermissionCode(permissionCode) {
        if (!permissionCode || typeof permissionCode !== 'string') return '';
        return permissionCode.replace(/:/g, '.').trim();
    }

    async init() {
        try {
            const response = await fetch(this.getApiUrl('get-user-permissions'));
            if (!response.ok) {
                return;
            }

            const data = await response.json();
            
            this.permissions = new Set(
                (data.permissions || []).map(p => this.normalizePermissionCode(p.codigo))
            );
            this.roleHierarchy = data.role_hierarchy || {};
            this.currentRole = data.role_name || null;
            this.isInitialized = true;
            
            this.notifySubscribers();
        } catch (error) {
        }
    }

    getApiUrl(action) {
        const baseUrl = document.querySelector('meta[name="base-url"]')?.content || '';
        return `${baseUrl}app/api/permissions.php?action=${action}`;
    }

    can(permissionCode) {
        const normalizedPermission = this.normalizePermissionCode(permissionCode);

        // Admin/superadmin tiene acceso total
        if (this.permissions.has('*')) return true;
        
        // Verificar permiso directo
        if (this.permissions.has(normalizedPermission)) return true;
        
        // Verificar wildcards (ej: 'tickets.*' incluye 'tickets.editar')
        const wildcard = this.getWildcardForPermission(normalizedPermission);
        if (wildcard && this.permissions.has(wildcard)) return true;
        
        return false;
    }

    getWildcardForPermission(permissionCode) {
        const normalizedPermission = this.normalizePermissionCode(permissionCode);
        const parts = normalizedPermission.split('.');
        if (parts.length < 2) return null;
        
        for (let i = parts.length - 1; i > 0; i--) {
            const wildcard = parts.slice(0, i).join('.') + '.*';
            if (this.permissions.has(wildcard)) return wildcard;
        }
        
        return null;
    }

    canAny(permissions) {
        return permissions.some(p => this.can(p));
    }

    canAll(permissions) {
        return permissions.every(p => this.can(p));
    }

    getRolePriority() {
        return this.roleHierarchy.priority || 0;
    }

    subscribe(callback) {
        this.subscribers.add(callback);
        return () => this.subscribers.delete(callback);
    }

    notifySubscribers() {
        const snapshot = this.getSnapshot();
        this.subscribers.forEach(cb => {
            try {
                cb(snapshot);
            } catch (e) {
            }
        });
    }

    getSnapshot() {
        return {
            permissions: Array.from(this.permissions),
            role: this.currentRole,
            priority: this.getRolePriority(),
            isInitialized: this.isInitialized
        };
    }

    async refresh() {
        await this.init();
    }
}

// Instancia global singleton
const rbacStore = new RBACStore();

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    console.log('RBACStore: DOM ready, initializing...');
    rbacStore.init().catch(e => console.error('RBACStore: init failed', e));
});

// Exponer globalmente
window.rbac = rbacStore;