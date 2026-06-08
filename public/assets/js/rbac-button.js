/**
 * Componente RBAC Button - Renderizado condicional de botones según permisos
 * @module rbac-button
 */

class RBACButton extends HTMLElement {
    static get observedAttributes() {
        return ['permission', 'variant', 'loading', 'disabled'];
    }

    constructor() {
        super();
        this._hasPermission = false;
        this._rendered = false;
    }

    connectedCallback() {
        if (!this._rendered) {
            this.render();
            this._rendered = true;
        }
        this.setupSubscription();
    }

    disconnectedCallback() {
        if (typeof this._unsubscribe === 'function') {
            this._unsubscribe();
            this._unsubscribe = null;
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'loading' || name === 'disabled') {
            this.render();
        }
    }

    setupSubscription() {
        if (!window.rbac?.isInitialized) {
            setTimeout(() => this.setupSubscription(), 100);
            return;
        }

        const unsubscribe = window.rbac.subscribe((snapshot) => {
            this._hasPermission = snapshot.permissions.includes(this.permission);
            this.render();
        });

        this._unsubscribe = unsubscribe;
    }

    get permission() {
        return this.getAttribute('permission');
    }

    get variant() {
        return this.getAttribute('variant') || 'primary';
    }

    get loading() {
        return this.hasAttribute('loading');
    }

    get disabled() {
        return this.hasAttribute('disabled');
    }

    get icon() {
        return this.getAttribute('icon') || null;
    }

    get action() {
        return this.getAttribute('action') || 'click';
    }

    get target() {
        return this.getAttribute('target') || null;
    }

    render() {
        const hasPermission = this.checkPermission();
        
        if (!hasPermission) {
            this.style.display = 'none';
            return;
        }

        this.style.display = '';

        const textoOriginal = this.textContent.trim() || this.getAttribute('label') || '';
        
        const buttonHTML = `
            <button 
                type="button"
                class="action-button action-button--${this.variant} ${this.loading ? 'loading' : ''}"
                ${this.disabled || this.loading ? 'disabled' : ''}
                aria-busy="${this.loading}"
                data-permission="${this.permission}">
                ${this.loading ? this.renderSpinner() : this.renderIcon()}
                <span class="button-text">${textoOriginal}</span>
            </button>
        `;

        this.innerHTML = buttonHTML;

        if (!this.disabled && !this.loading) {
            this.setupEventHandlers();
        }
    }

    checkPermission() {
        if (!this.permission) return true;
        if (!window.rbac) return true; // SSR fallback
        
        return window.rbac.can(this.permission) || window.rbac.permissions.has(this.permission);
    }

    renderIcon() {
        const icons = {
            create: `<svg viewBox="0 0 24 24" width="16" height="16"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>`,
            edit: `<svg viewBox="0 0 24 24" width="16" height="16"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>`,
            delete: `<svg viewBox="0 0 24 24" width="16" height="16"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2v-12H6v12zM19 4V7H5V4h14z"/></svg>`,
            save: `<svg viewBox="0 0 24 24" width="16" height="16"><path d="M19 3H4.99c-1.11 0-1.98.89-1.98 2L3 19c0 1.1.88 2 1.99 2H19c1.1 0 2-.9 2-2V5c0-1.11-.89-2-2-2zm-9 12H7v-2h5v2zm4-4H7V9h9v2z"/></svg>`,
            cancel: `<svg viewBox="0 0 24 24" width="16" height="16"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>`,
            default: ''
        };

        return icons[this.icon] || icons.default;
    }

    renderSpinner() {
        return `<svg class="spinner" viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none"><animateTransform attributeName="transform" type="rotate" dur="0.75s" values="0 12 12; 360 12 12" repeatCount="indefinite"/></circle></svg>`;
    }

    setupEventHandlers() {
        const button = this.querySelector('button');
        if (!button) return;

        button.addEventListener('click', (e) => this.handleClick(e));
        
        // Teclado: Enter o Space activan el botón
        button.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                button.click();
            }
        });
    }

    handleClick(event) {
        if (this.loading || this.disabled) return;

        const button = event.currentTarget;
        button.disabled = true;
        button.classList.add('loading');

        // Disparar evento personalizado
        this.dispatchEvent(new CustomEvent('rbac-click', {
            bubbles: true,
            composed: true,
            detail: { permission: this.permission }
        }));

        // Ejecutar acción definida
        this.executeAction();

        // Reset loading state después de acción
        setTimeout(() => {
            button.disabled = false;
            button.classList.remove('loading');
        }, 300);
    }

    executeAction() {
        switch (this.action) {
            case 'redirect':
                if (this.target) window.location.href = this.target;
                break;
            case 'api':
                this.callAPI(this.target);
                break;
            case 'submit':
                this.submitParentForm();
                break;
            case 'modal':
                this.openModal(this.target);
                break;
            case 'click':
            default:
                // No action, handled by parent
                break;
        }
    }

    async callAPI(endpoint) {
        if (!endpoint) return;

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    _csrf: document.querySelector('meta[name="csrf-token"]')?.content
                })
            });

            const data = await response.json();
            
            if (data.error) {
                this.showToast(data.error, 'error');
            } else {
                this.dispatchEvent(new CustomEvent('rbac-success', {
                    detail: data
                }));
            }
        } catch (error) {
            this.showToast('Error de conexión', 'error');
        }
    }

    submitParentForm() {
        const form = this.closest('form');
        if (form) form.dispatchEvent(new Event('submit'));
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('active');
    }

    showToast(message, type = 'info') {
        const event = new CustomEvent('rbac-toast', {
            detail: { message, type }
        });
        this.dispatchEvent(event);
    }
}

// Registrar el custom element
if ('customElements' in window) {
    customElements.define('rbac-button', RBACButton);
}