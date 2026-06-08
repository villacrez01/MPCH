/**
 * Analisis Charts - Professional Charts with Chart.js
 * Sistema OTI - Real-time data visualization
 */

(function() {
    'use strict';
    
    const BASE_URL = window.location.origin + '/OTI/';
    let charts = {};

    let isInitialized = false;
    
    const CHART_COLORS = {
        primary: '#1e3f5f',
        primaryLight: '#4a7ba8',
        success: '#0f6a4e',
        successLight: '#2d9a72',
        warning: '#b8953d',
        warningLight: '#d4b56a',
        danger: '#b91c1c',
        dangerLight: '#dc4a4a',
        info: '#0369a1',
        infoLight: '#38a3d4',
        purple: '#4a7ba8',
        purpleLight: '#6b9bc4',
        pink: '#96782e',
        pinkLight: '#b8953d',
        cyan: '#0e7490',
        cyanLight: '#22a3b8',
        gray: '#7e92a9',
        grayLight: '#a8b8c9'
    };
    
    const chartDefaults = {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 800,
            easing: 'easeOutQuart'
        },
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    font: {
                        family: "'Outfit', sans-serif",
                        size: 12,
                        weight: '500'
                    },
                    color: '#64748b'
                }
            },
            tooltip: {
                backgroundColor: 'rgba(15, 38, 58, 0.96)',
                titleFont: {
                    family: "'Outfit', sans-serif",
                    size: 14,
                    weight: '600'
                },
                bodyFont: {
                    family: "'Outfit', sans-serif",
                    size: 13
                },
                padding: 12,
                cornerRadius: 8,
                displayColors: true,
                boxPadding: 6,
                usePointStyle: true
            }
        }
    };
    
    function init() {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js no cargado');
            return;
        }

        if (isInitialized) return;
        isInitialized = true;

        const initialData = window.analisisInitialData || {};

        initTicketsMensualChart(initialData.tickets_por_mes || []);
        initPrioridadChart(initialData.por_prioridad || []);
        initUbicacionesChart(initialData.por_ubicacion || []);
        initUsuariosChart(initialData.top_usuarios || []);
        initEquiposChart(initialData.equipos_por_tipo || []);
        initEstadoChart(initialData.por_estado || []);
        initEquiposEstadoChart(initialData.equipos || {
            total: 0,
            activos: 0,
            mantenimiento: 0,
            inactivos: 0
        });

        if (window.OTI && window.OTI.refreshIcons) {
            window.OTI.refreshIcons();
        }

        /* ── Actualización automática silenciosa cada 30 s ── */
        setInterval(function () {
            fetch(BASE_URL + 'app/api/stats.php')
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (data) {
                    if (data.error) return;
                    updateKPIs(data.stats || {});
                    updateCharts(data);
                    updateLastUpdated();
                })
                .catch(function () { /* silencioso */ });
        }, 30000);
    }

    /* ── Refrescado manual desde botón ── */
    window.refreshAnalisis = function () {
        var btn = document.getElementById('btn-refresh-analisis');
        if (btn) btn.classList.add('loading');

        fetch(BASE_URL + 'app/api/stats.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.error) {
                    updateKPIs(data.stats || {});
                    updateCharts(data);
                    updateLastUpdated();
                }
            })
            .catch(function () {})
            .then(function () {
                if (btn) btn.classList.remove('loading');
            });
    };

    function updateLastUpdated() {
        var el = document.getElementById('analisis-last-update');
        if (el) {
            el.innerHTML = '<span class="pulse-dot" style="width:8px;height:8px;border-radius:50%;background:#0f6a4e;display:inline-block;"></span> Actualizado ahora';
        }
    }
    
    function initTicketsMensualChart(data) {
        const ctx = document.getElementById('chart-tickets-mensual');
        if (!ctx) return;
        
        const labels = data.map(d => formatMonth(d.mes));
        const values = data.map(d => parseInt(d.count));
        
        const bgColor = createGradient(ctx, CHART_COLORS.primaryLight, CHART_COLORS.primary);
        
        charts.ticketsMensual = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Incidencias',
                    data: values,
                    borderColor: CHART_COLORS.primary,
                    backgroundColor: bgColor,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: CHART_COLORS.primary,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 6
                }]
            },
            options: {
                ...chartDefaults,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: "'Outfit', sans-serif", size: 11 },
                            color: '#94a3b8'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            font: { family: "'Outfit', sans-serif", size: 11 },
                            color: '#94a3b8',
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
    
    function initPrioridadChart(data) {
        const ctx = document.getElementById('chart-prioridad');
        if (!ctx) return;
        
        const labels = data.map(d => d.name);
        const values = data.map(d => parseInt(d.count));
        const colors = data.map(d => d.color || CHART_COLORS.primary);
        
        charts.prioridad = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                ...chartDefaults,
                cutout: '65%',
                plugins: {
                    ...chartDefaults.plugins,
                    legend: {
                        ...chartDefaults.plugins.legend,
                        position: 'right'
                    }
                }
            }
        });
    }
    
    function initUbicacionesChart(data) {
        const ctx = document.getElementById('chart-ubicaciones');
        if (!ctx) return;
        
        const labels = data.map(d => truncateText(d.name, 15));
        const values = data.map(d => parseInt(d.count));
        const bgColor = createBarGradient(ctx, CHART_COLORS.warning);
        
        charts.ubicaciones = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Incidencias',
                    data: values,
                    backgroundColor: bgColor,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 40
                }]
            },
            options: {
                ...chartDefaults,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            font: { family: "'Outfit', sans-serif", size: 11 },
                            color: '#94a3b8'
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { family: "'Outfit', sans-serif", size: 11 },
                            color: '#64748b'
                        }
                    }
                }
            }
        });
    }
    
    function initUsuariosChart(data) {
        const ctx = document.getElementById('chart-usuarios');
        if (!ctx) return;
        
        const labels = data.map(d => truncateText(d.name, 15));
        const values = data.map(d => parseInt(d.count));
        const bgColor = createBarGradient(ctx, CHART_COLORS.info);
        
        charts.usuarios = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Incidencias',
                    data: values,
                    backgroundColor: bgColor,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 40
                }]
            },
            options: {
                ...chartDefaults,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            font: { family: "'Outfit', sans-serif", size: 11 },
                            color: '#94a3b8'
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { family: "'Outfit', sans-serif", size: 11 },
                            color: '#64748b'
                        }
                    }
                }
            }
        });
    }
    
    function initEquiposChart(data) {
        const ctx = document.getElementById('chart-equipos');
        if (!ctx) return;
        
        const labels = data.map(d => truncateText(d.asset_type || 'Sin tipo', 15));
        const values = data.map(d => parseInt(d.count));
        
        charts.equipos = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Equipos',
                    data: values,
                    backgroundColor: CHART_COLORS.purple,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 40
                }]
            },
            options: {
                ...chartDefaults,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: "'Outfit', sans-serif", size: 11 },
                            color: '#94a3b8',
                            maxRotation: 45,
                            minRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            font: { family: "'Outfit', sans-serif", size: 11 },
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });
    }
    
    function initEstadoChart(data) {
        const ctx = document.getElementById('chart-estado');
        if (!ctx) return;
        
        const labels = data.map(d => d.name);
        const values = data.map(d => parseInt(d.count));
        
        const statusColors = {
            'Abierto': CHART_COLORS.danger,
            'En Proceso': CHART_COLORS.warning,
            'Resuelto': CHART_COLORS.success,
            'Cerrado': CHART_COLORS.gray
        };
        
        charts.estado = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Incidencias',
                    data: values,
                    backgroundColor: labels.map(l => statusColors[l] || CHART_COLORS.primary),
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                ...chartDefaults,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: "'Outfit', sans-serif", size: 11 },
                            color: '#94a3b8'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            font: { family: "'Outfit', sans-serif", size: 11 },
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });
    }
    
    function initEquiposEstadoChart(data) {
        const ctx = document.getElementById('chart-equipos-estado');
        if (!ctx) return;
        
        charts.equiposEstado = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Activos', 'Mantenimiento', 'Inactivos'],
                datasets: [{
                    data: [
                        parseInt(data.activos || 0),
                        parseInt(data.mantenimiento || 0),
                        parseInt(data.inactivos || 0)
                    ],
                    backgroundColor: [CHART_COLORS.success, CHART_COLORS.warning, CHART_COLORS.gray],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                ...chartDefaults,
                cutout: '65%'
            }
        });
    }
    
    function updateKPIs(stats) {
        const kpiElements = {
            'kpi-total': stats.total || 0,
            'kpi-resueltos': (parseInt(stats.resueltos || 0) + parseInt(stats.cerrados || 0)),
            'res-abiertos': stats.abiertos || 0,
            'res-proceso': stats.en_proceso || 0,
            'res-resueltos': stats.resueltos || 0,
            'res-cerrados': stats.cerrados || 0
        };
        
        Object.keys(kpiElements).forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                animateValue(el, parseInt(el.textContent) || 0, kpiElements[id], 500);
            }
        });
        
        const total = parseInt(stats.total || 0);
        const resueltos = parseInt(stats.resueltos || 0) + parseInt(stats.cerrados || 0);
        const tasa = total > 0 ? ((resueltos / total) * 100).toFixed(1) : 0;
        
        const tasaEl = document.getElementById('kpi-tasa');
        if (tasaEl) {
            animateValue(tasaEl, parseFloat(tasaEl.textContent) || 0, parseFloat(tasa), 500);
            tasaEl.textContent = tasa + '%';
        }
    }
    
    function updateCharts(data) {
        /* Tickets por mes (requiere ultimos_30_dias) */
        if (charts.ticketsMensual && data.ultimos_30_dias) {
            const monthlyData = aggregateByMonth(data.ultimos_30_dias);
            charts.ticketsMensual.data.labels = monthlyData.map(d => formatMonth(d.mes));
            charts.ticketsMensual.data.datasets[0].data = monthlyData.map(d => d.count);
            charts.ticketsMensual.update('none');
        }

        /* Tickets por prioridad */
        if (charts.prioridad && data.por_prioridad) {
            charts.prioridad.data.labels = data.por_prioridad.map(d => d.name);
            charts.prioridad.data.datasets[0].data = data.por_prioridad.map(d => parseInt(d.count));
            charts.prioridad.data.datasets[0].backgroundColor = data.por_prioridad.map(d => d.color || CHART_COLORS.primary);
            charts.prioridad.update('none');
        }

        /* Top ubicaciones */
        if (charts.ubicaciones && data.por_ubicacion) {
            charts.ubicaciones.data.labels = data.por_ubicacion.map(d => truncateText(d.name, 18));
            charts.ubicaciones.data.datasets[0].data = data.por_ubicacion.map(d => parseInt(d.count));
            charts.ubicaciones.update('none');
        }

        /* Top usuarios */
        if (charts.usuarios && data.top_usuarios) {
            charts.usuarios.data.labels = data.top_usuarios.map(d => truncateText(d.name, 18));
            charts.usuarios.data.datasets[0].data = data.top_usuarios.map(d => parseInt(d.count));
            charts.usuarios.update('none');
        }

        /* Equipos por tipo */
        if (charts.equipos && data.equipos_por_tipo) {
            charts.equipos.data.labels = data.equipos_por_tipo.map(d => truncateText(d.asset_type || 'Sin tipo', 15));
            charts.equipos.data.datasets[0].data = data.equipos_por_tipo.map(d => parseInt(d.count));
            charts.equipos.update('none');
        }

        /* Tickets por estado */
        if (charts.estado && data.por_estado) {
            charts.estado.data.labels = data.por_estado.map(d => d.name);
            charts.estado.data.datasets[0].data = data.por_estado.map(d => parseInt(d.count));
            charts.estado.update('none');
        }

        /* Equipos del sistema (doughnut) */
        if (charts.equiposEstado && data.equipos) {
            charts.equiposEstado.data.datasets[0].data = [
                parseInt(data.equipos.activos          ?? 0),
                parseInt(data.equipos.mantenimiento   ?? 0),
                parseInt(data.equipos.inactivos       ?? 0)
            ];
            charts.equiposEstado.update('none');
        }
    }

    function animateValue(el, start, end, duration) {
        if (start === end) return;
        const range = end - start;
        const startTime = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.floor(start + (range * eased));
            if (progress < 1) requestAnimationFrame(update);
        }
        
        requestAnimationFrame(update);
    }
    
    function createGradient(ctx, colorStart, colorEnd) {
        try {
            if (!ctx || !ctx.canvas || ctx.canvas.width === 0 || ctx.canvas.height === 0) {
                return colorStart + '40';
            }
            const height = ctx.canvas.height;
            const gradient = ctx.createLinearGradient(0, 0, 0, height);
            gradient.addColorStop(0, colorStart + '40');
            gradient.addColorStop(1, colorEnd + '10');
            return gradient;
        } catch (e) {
            return colorStart + '40';
        }
    }
    
    function createBarGradient(ctx, color) {
        try {
            if (!ctx || !ctx.canvas || ctx.canvas.width === 0 || ctx.canvas.height === 0) {
                return color;
            }
            const height = ctx.canvas.height;
            const gradient = ctx.createLinearGradient(0, 0, 0, height);
            gradient.addColorStop(0, color);
            gradient.addColorStop(1, color + '80');
            return gradient;
        } catch (e) {
            return color;
        }
    }
    
    function formatMonth(mes) {
        if (!mes) return '';
        const [year, month] = mes.split('-');
        const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return months[parseInt(month) - 1] + ' ' + year.slice(2);
    }
    
    function truncateText(text, maxLength) {
        if (!text) return '';
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength - 3) + '...';
    }
    
    function aggregateByMonth(data) {
        const grouped = {};
        data.forEach(item => {
            const date = new Date(item.date);
            const mes = date.toISOString().substring(0, 7);
            grouped[mes] = (grouped[mes] || 0) + parseInt(item.count);
        });
        
        return Object.keys(grouped).sort().map(mes => ({
            mes: mes,
            count: grouped[mes]
        }));
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    window.renderAnalisisCharts = init;
})();