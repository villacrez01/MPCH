/**
 * Sistema de tiempo real para OTI
 * Versión optimizada: SSE (Server-Sent Events) con fallback a polling
 */

(function() {
    'use strict';

    const BASE_URL = window.location.origin + '/OTI/';
    let isAdmin = false;
    let currentPage = '';
    let eventSource = null;
    let updateInterval = null;
    let useSSE = true;
    let lastData = null;

    function init() {
        const adminElement = document.getElementById('is-admin');
        const userElement = document.getElementById('user-id');
        
        isAdmin = adminElement ? adminElement.value === '1' : false;
        
        if (!isAdmin && userElement) {
            const roleElement = document.getElementById('user-role');
            if (roleElement) {
                const roleText = roleElement.textContent.toLowerCase();
                isAdmin = roleText.includes('admin') || 
                         roleText.includes('director') || 
                         roleText.includes('jefe') ||
                         roleText.includes('coordinador') ||
                         roleText.includes('supervisor');
            }
        }
        
        const path = window.location.pathname;
        if (path.includes('admin/dashboard')) currentPage = 'admin-dashboard';
        else if (path.includes('admin/tickets')) currentPage = 'admin-tickets';
        else if (path.includes('admin/equipos')) currentPage = 'admin-equipos';
        else if (path.includes('admin/usuarios')) currentPage = 'admin-usuarios';
        else if (path.includes('admin/estructura')) currentPage = 'admin-estructura';
        else if (path.includes('admin/analisis')) currentPage = 'admin-analisis';
        else if (path.includes('user/dashboard')) currentPage = 'user-dashboard';
        else if (path.includes('user/tickets')) currentPage = 'user-tickets';
        else if (path.includes('user/ticket-detalle')) currentPage = 'user-ticket-detalle';
        else if (path.includes('user/reportar')) currentPage = 'user-reportar';

injectMobileMenu();

        if (useSSE && (currentPage === 'admin-dashboard' || currentPage === 'user-dashboard')) {
            initSSE();
        } else {
            fetchAllData();
            updateInterval = setInterval(fetchAllData, 30000);
        }
        
        fetchNotifications();
        setInterval(fetchNotifications, 480000);
    }

    function initSSE() {
        try {
            let scope = currentPage.startsWith('user-') ? 'user' : 'admin';
            eventSource = new EventSource(BASE_URL + 'app/api/sse.php?scope=' + scope);
            
            eventSource.onopen = function() {
                console.log('SSE conectado');
                if (updateInterval) {
                    clearInterval(updateInterval);
                    updateInterval = null;
                }
            };
            
            eventSource.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    handleDataUpdate(data);
                } catch (e) {
                    console.error('Error al analizar datos SSE:', e);
                }
            };
            
            eventSource.addEventListener('update', function(event) {
                try {
                    const data = JSON.parse(event.data);
                    handleDataUpdate(data);
                } catch (e) {
                    console.error('Error en evento de actualización:', e);
                }
            });
            
            eventSource.addEventListener('connected', function(event) {
                const data = JSON.parse(event.data);
                console.log('SSE conectado:', data);
            });
            
            eventSource.addEventListener('error', function() {
                console.warn('Evento de error SSE recibido, fallback al manejador onerror');
            });
            
            eventSource.onerror = function(e) {
                console.warn('Error SSE, cambiando a polling:', e);
                closeSSE();
                useSSE = false;
                fetchAllData();
                updateInterval = setInterval(fetchAllData, 30000);
            };
            
        } catch (e) {
            console.warn('SSE no disponible, usando polling:', e);
            useSSE = false;
            fetchAllData();
            updateInterval = setInterval(fetchAllData, 30000);
        }
    }

    function closeSSE() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
    }

    function handleDataUpdate(data) {
        if (data.error) return;
        
        lastData = data;
        
        updateStats(data);
        
        if (currentPage === 'admin-dashboard') {
            updateAdminDashboard(data);
        } else if (currentPage === 'user-dashboard') {
            updateUserDashboard(data);
        } else if (currentPage === 'user-ticket-detalle') {
            updateUserTicketDetail();
            // Auto-refresh ticket detail every 2 minutes for real-time updates
            if (!updateInterval) {
                updateInterval = setInterval(updateUserTicketDetail, 120000);
            }
        } else if (currentPage === 'admin-usuarios' && typeof window.refreshUsers === 'function') {
            window.refreshUsers();
        }
    }

    async function fetchAllData() {
        try {
            let scope = currentPage.startsWith('user-') ? 'user' : 'admin';
            const statsRes = await fetch(BASE_URL + 'app/api/stats.php?scope=' + scope);
            if (statsRes.ok && statsRes.headers.get('content-type')?.includes('application/json')) {
                const statsData = await statsRes.json();
                if (!statsData.error) {
                    handleDataUpdate(statsData);
                }
            }
        } catch (error) {
            console.warn('Error al obtener datos:', error);
        }
    }

    async function fetchNotifications() {
        try {
            const notifRes = await fetch(BASE_URL + 'app/api/notifications.php');
            if (notifRes.ok && notifRes.headers.get('content-type')?.includes('application/json')) {
                const notifData = await notifRes.json();
                updateNotifications(notifData);
            }
        } catch (error) {
            console.warn('Error al obtener notificaciones:', error);
        }
    }

    function updateStats(data) {
        const stats = data.stats || {};
        
        const elements = {
            'stat-total': stats.total || 0,
            'stat-abiertos': stats.abiertos || 0,
            'stat-proceso': stats.en_proceso || 0,
            'stat-resueltos': stats.resueltos || 0,
            'stat-cerrados': stats.cerrados || 0
        };

        Object.keys(elements).forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                animateValue(el, parseInt(el.textContent) || 0, elements[id], 500);
            }
        });
    }

    function updateNotifications(data) {
        const badge = document.getElementById('notif-badge');
        const list = document.getElementById('notif-list');
        
        if (badge) {
            const count = data.unread_count || 0;
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline-block' : 'none';
        }
        
        // Solo actualizar el HTML del listado si NO estamos en la página de notificaciones
        const isNotifPage = list && list.closest('.notif-page');
        if (list && !isNotifPage && data.notifications) {
            if (data.notifications.length === 0) {
                list.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-muted);">Sin notificaciones</div>';
            } else {
                let html = '';
                data.notifications.slice(0, 5).forEach(n => {
                    const iconSvg = n.ticket_id 
                        ? '<svg viewBox="0 0 24 24" fill="#0284c7"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>'
                        : '<svg viewBox="0 0 24 24" fill="#059669"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
                    const iconClass = n.ticket_id ? 'ticket' : 'success';
                    html += `
                        <a href="${BASE_URL}user/tickets" class="notif-item ${n.is_read ? '' : 'unread'}">
                            <div class="notif-icon ${iconClass}">${iconSvg}</div>
                            <div class="notif-content">
                                <div class="notif-title">${escapeHtml(n.title || 'Notificación')}</div>
                                <div class="notif-time">${formatDate(n.created_at)}</div>
                            </div>
                        </a>
                    `;
                });
                list.innerHTML = html;
            }
        }
    }

    function updateAdminDashboard(data) {
        if (document.getElementById('tickets-list')) {
            updateTicketsList(data.tickets_recientes || []);
        }
        
        if (data.equipos) {
            updateValue('equipos-total', data.equipos.total || 0);
            updateValue('equipos-activos', data.equipos.activos || 0);
            updateValue('equipos-mantenimiento', data.equipos.mantenimiento || 0);
        }
        
        if (data.usuarios) {
            updateValue('usuarios-total', data.usuarios.total || 0);
        }
        
        if (document.getElementById('timeline-container')) {
            renderTimeline(data.actividad_reciente || []);
        }
        
        if (document.getElementById('equipos-donut')) {
            renderEquiposDonut(data.equipos || {});
        }
        
        if (document.getElementById('usuarios-bar')) {
            renderUsuariosBar(data.usuarios || {});
        }
    }
    
    function renderTimeline(items) {
        const container = document.getElementById('timeline-container');
        const skeleton = document.getElementById('timeline-skeleton');
        const emptyState = document.getElementById('timeline-empty');
        
        if (!container) return;
        
        if (skeleton) skeleton.style.display = 'none';
        
        if (!items || items.length === 0) {
            if (skeleton) skeleton.style.display = 'none';
            if (emptyState) emptyState.style.display = 'flex';
            return;
        }
        
        if (emptyState) emptyState.style.display = 'none';
        
        let html = '<div class="timeline animate-fade-in">';
        items.forEach((item, index) => {
            const dotClass = item.tipo === 'ticket' ? (item.status_class || 'ticket') : (item.tipo || 'ticket');
            const badgeClass = item.tipo === 'ticket' ? (item.status_class || 'ticket') : (item.tipo || 'ticket');
            
            html += `
                <div class="timeline-item" style="animation-delay: ${index * 50}ms;">
                    <div class="timeline-dot ${dotClass}"></div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <div class="timeline-title">${escapeHtml(item.titulo || 'Sin título')}</div>
                            <span class="timeline-badge ${badgeClass}">${escapeHtml(item.badge || item.tipo || 'Ticket')}</span>
                        </div>
                        <div class="timeline-desc">${escapeHtml(item.descripcion || '')}</div>
                        <div class="timeline-meta">
                            <span>${item.tiempo || formatDate(item.fecha)}</span>
                            ${item.usuario ? '<span>' + escapeHtml(item.usuario) + '</span>' : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    }
    
    function renderEquiposDonut(data) {
        const total = data.total || 0;
        const activos = data.activos || 0;
        const mantenimiento = data.mantenimiento || 0;
        const inactivos = data.inactivos || 0;
        
        const totalEl = document.getElementById('equipos-total-value');
        const activosEl = document.getElementById('equipos-activos-value');
        const mantEl = document.getElementById('equipos-mantenimiento-value');
        const inactEl = document.getElementById('equipos-inactivos-value');
        
        if (totalEl) animateValue(totalEl, parseInt(totalEl.textContent) || 0, total, 500);
        if (activosEl) activosEl.textContent = activos;
        if (mantEl) mantEl.textContent = mantenimiento;
        if (inactEl) inactEl.textContent = inactivos;
        
        const circle = document.getElementById('donut-equipos-circle');
        if (circle && total > 0) {
            const circumference = 2 * Math.PI * 38;
            const activePercent = activos / total;
            const offset = circumference * (1 - activePercent);
            circle.style.strokeDasharray = circumference;
            circle.style.strokeDashoffset = offset;
            circle.style.stroke = 'var(--success)';
        }
    }
    
    function renderUsuariosBar(data) {
        const total = data.total || 0;
        const activos = data.activos || 0;
        
        const totalValue = document.getElementById('hbar-total-value');
        const activosValue = document.getElementById('hbar-activos-value');
        const activosBar = document.getElementById('hbar-activos');
        
        if (totalValue) {
            animateValue(totalValue, parseInt(totalValue.textContent) || 0, total, 500);
            totalValue.textContent = total;
        }
        if (activosValue) activosValue.textContent = activos;
        if (activosBar) {
            const percent = total > 0 ? Math.round((activos / total) * 100) : 0;
            activosBar.style.width = percent + '%';
        }
    }

    function updateUserDashboard(data) {
        if (document.getElementById('tickets-list')) {
            updateTicketsList(data.tickets_recientes || []);
        }
    }

    function updateUserTicketDetail() {
        const ticketId = document.getElementById('ticket-id');
        if (!ticketId || !ticketId.value) return;
        
        fetch(BASE_URL + 'app/api/user_tickets.php?action=get-ticket&id=' + ticketId.value)
            .then(function(res) { return res.json(); })
            .then(function(ticket) {
                if (ticket.error || !ticket.id) return;
                
                const statusEl = document.getElementById('ticket-status');
                if (statusEl && ticket.status_name) {
                    statusEl.textContent = ticket.status_name;
                    statusEl.className = 'status-badge ' + getStatusClass(ticket.status_name);
                }
                
                const cancelBtn = document.getElementById('btn-cancel');
                if (cancelBtn) {
                    if (ticket.can_cancel) {
                        cancelBtn.disabled = false;
                        cancelBtn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>Cancelar Ticket';
                    } else {
                        cancelBtn.disabled = true;
                        cancelBtn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>Ticket en proceso';
                    }
                }
                
                if (typeof loadActivities === 'function') loadActivities();
            })
            .catch(function() {});
    }

    function updateTicketsList(tickets) {
        const container = document.getElementById('tickets-list');
        if (!container) return;

        if (!tickets || tickets.length === 0) {
            container.innerHTML = '<div class="empty-state"><svg viewBox="0 0 24 24"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg><div class="empty-title">No hay tickets</div></div>';
            return;
        }

        let html = '';
        tickets.forEach(ticket => {
            const statusClass = getStatusClass(ticket.status_name);
            const priorityClass = getPriorityClass(ticket.priority_name);
            const url = isAdmin ? `${BASE_URL}admin/tickets?id=${ticket.id}` : `${BASE_URL}user/ticket-detalle?id=${ticket.id}`;
            
            html += `
                <a href="${url}" class="ticket-item">
                    <div class="ticket-status ${statusClass}"></div>
                    <div class="ticket-info">
                        <div class="ticket-code">${escapeHtml(ticket.code)}</div>
                        <div class="ticket-title">${escapeHtml(ticket.title)}</div>
                        <div class="ticket-meta">
                            <span>${formatDate(ticket.created_at)}</span>
                            <span class="ticket-priority ${priorityClass}">${ticket.priority_name || 'Media'}</span>
                        </div>
                    </div>
                    <span class="ticket-arrow">→</span>
                </a>
            `;
        });

        container.innerHTML = html;
    }

    function updateValue(id, value) {
        const el = document.getElementById(id);
        if (el) {
            animateValue(el, parseInt(el.textContent) || 0, value, 500);
        }
    }

    function animateValue(el, start, end, duration) {
        if (start === end) return;
        const range = end - start;
        const startTime = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            el.textContent = Math.floor(start + (range * progress));
            if (progress < 1) requestAnimationFrame(update);
        }
        
        requestAnimationFrame(update);
    }

    function getStatusClass(status) {
        const map = { 'Abierto': 'abierto', 'En Proceso': 'en-proceso', 'Resuelto': 'resuelto', 'Cerrado': 'cerrado', 'Cancelado': 'cancelado' };
        return map[status] || 'abierto';
    }

    function getPriorityClass(priority) {
        const map = { 'Sin Prioridad': 'sin-prioridad', 'Baja': 'baja', 'Media': 'media', 'Alta': 'alta', 'Crítica': 'critica' };
        return map[priority] || 'media';
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const diff = Date.now() - date.getTime();
        
        if (diff < 60000) return 'Ahora';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm';
        if (diff < 86400000) return Math.floor(diff / 3600000) + 'h';
        
        return date.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit' });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function injectMobileMenu() {
        const header = document.querySelector('.header-bar');
        const sidebar = document.querySelector('.sidebar');
        let overlay = document.querySelector('.sidebar-overlay');
        
        if (header && sidebar && !document.getElementById('menu-toggle-btn')) {
            const toggleBtn = document.createElement('button');
            toggleBtn.id = 'menu-toggle-btn';
            toggleBtn.className = 'menu-toggle-btn';
            toggleBtn.setAttribute('aria-label', 'Abrir Menú');
            toggleBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>';
            
            const headerLeft = header.querySelector('.header-left');
            if (headerLeft) {
                headerLeft.insertBefore(toggleBtn, headerLeft.firstChild);
            } else {
                header.insertBefore(toggleBtn, header.firstChild);
            }
            
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('open');
                overlay = document.querySelector('.sidebar-overlay');
                if (overlay) {
                    overlay.classList.toggle('active');
                }
            });
            
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024) {
                    if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                        sidebar.classList.remove('open');
                        overlay = document.querySelector('.sidebar-overlay');
                        if (overlay) {
                            overlay.classList.remove('active');
                        }
                    }
                }
            });
        }
        
        if (!overlay && document.body) {
            const newOverlay = document.createElement('div');
            newOverlay.className = 'sidebar-overlay';
            document.body.appendChild(newOverlay);
            newOverlay.addEventListener('click', function() {
                if (!sidebar) { newOverlay.classList.remove('active'); return; }
                sidebar.classList.remove('open');
                newOverlay.classList.remove('active');
            });
        }
    }

    window.toggleNotifications = function() {
        // Redirect to notifications page if no dropdown
        const dropdown = document.getElementById('notif-dropdown');
        if (dropdown) {
            dropdown.classList.toggle('show');
        } else {
            window.location.href = (typeof BASE_URL !== 'undefined' ? BASE_URL : '/OTI/') + 'user/notificaciones';
        }
    };

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.notif-btn') && !e.target.closest('.notif-dropdown')) {
            const dropdown = document.getElementById('notif-dropdown');
            if (dropdown) dropdown.classList.remove('show');
        }
    });

    document.addEventListener('click', function(e) {
        const actionBtn = e.target.closest('.action-btn');
        if (actionBtn) {
            console.log('ACTION BTN CLICK detected on', actionBtn.className);
            const onclick = actionBtn.getAttribute('onclick');
            console.log('Raw onclick handler:', onclick);
        }
    });

    window.addEventListener('beforeunload', function() {
        closeSSE();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

