/**
 * Global Search Component for OTI
 * Activated with Cmd+K / Ctrl+K
 */

(function() {
    'use strict';

    const BASE_URL = window.location.origin + '/OTI/';
    let searchModal = null;
    let searchInput = null;
    let resultsContainer = null;
    let currentIndex = -1;
    let searchResults = [];
    let debounceTimer = null;

    function init() {
        try {
            createSearchModal();
            bindKeyboardShortcuts();
            console.log('Search: initialized successfully');
        } catch (e) {
            console.error('Search: init error', e);
        }
    }

    function createSearchModal() {
        if (document.getElementById('global-search')) return;

        const modal = document.createElement('div');
        modal.id = 'global-search';
        modal.className = 'search-modal';
        modal.innerHTML = `
            <div class="search-backdrop"></div>
            <div class="search-container" role="dialog" aria-modal="true" aria-label="Búsqueda global">
                <div class="search-header">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <input type="text" 
                           id="search-input" 
                           class="search-input" 
                           placeholder="Buscar tickets, usuarios, equipos..."
                           autocomplete="off"
                           aria-label="Campo de búsqueda">
                    <kbd class="search-shortcut">ESC</kbd>
                </div>
                <div class="search-results" id="search-results" role="listbox">
                    <div class="search-hint">
                        <span>Escribe para buscar en tickets, usuarios y equipos</span>
                    </div>
                </div>
                <div class="search-footer">
                    <div class="search-help">
                        <span><kbd>↑</kbd><kbd>↓</kbd> navegar</span>
                        <span><kbd>Enter</kbd> seleccionar</span>
                        <span><kbd>ESC</kbd> cerrar</span>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        searchModal = modal;
        searchInput = document.getElementById('search-input');
        resultsContainer = document.getElementById('search-results');

        const backdrop = modal.querySelector('.search-backdrop');
        backdrop.addEventListener('click', closeSearch);

        searchInput.addEventListener('input', handleSearchInput);
        searchInput.addEventListener('keydown', handleSearchKeydown);
    }

    function bindKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                openSearch();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !isInputFocused()) {
                e.preventDefault();
                openSearch();
            }
        });
    }

    function isInputFocused() {
        const activeElement = document.activeElement;
        return activeElement && (activeElement.tagName === 'INPUT' || 
                               activeElement.tagName === 'TEXTAREA' || 
                               activeElement.isContentEditable);
    }

    function openSearch() {
        if (searchModal) {
            searchModal.classList.add('active');
            searchInput.focus();
            searchInput.select();
            document.body.style.overflow = 'hidden';
        }
    }

    function closeSearch() {
        if (searchModal) {
            searchModal.classList.remove('active');
            searchInput.value = '';
            resetResults();
            document.body.style.overflow = '';
            currentIndex = -1;
            searchResults = [];
        }
    }

    function handleSearchInput(e) {
        const query = e.target.value.trim();
        
        clearTimeout(debounceTimer);
        
        if (query.length < 2) {
            showHint('Escribe al menos 2 caracteres para buscar');
            return;
        }
        
        debounceTimer = setTimeout(() => {
            performSearch(query);
        }, 300);
    }

    function handleSearchKeydown(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            navigateResults(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            navigateResults(-1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            selectCurrentResult();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closeSearch();
        }
    }

    function navigateResults(direction) {
        const items = resultsContainer.querySelectorAll('.search-result-item');
        if (items.length === 0) return;

        items[currentIndex]?.classList.remove('selected');
        
        currentIndex += direction;
        
        if (currentIndex < 0) currentIndex = items.length - 1;
        if (currentIndex >= items.length) currentIndex = 0;
        
        items[currentIndex]?.classList.add('selected');
        items[currentIndex]?.scrollIntoView({ block: 'nearest' });
    }

    function selectCurrentResult() {
        const items = resultsContainer.querySelectorAll('.search-result-item.selected');
        if (items.length > 0) {
            const url = items[0].dataset.url;
            if (url) {
                window.location.href = url;
            }
        }
    }

    async function performSearch(query) {
        showLoading();
        
        try {
            const response = await fetch(BASE_URL + 'app/api/search.php?q=' + encodeURIComponent(query), {
                credentials: 'same-origin'
            });
            
            if (!response.ok) throw new Error('Búsqueda fallida');
            
            const data = await response.json();
            displayResults(data);
        } catch (error) {
            showError('Error al realizar la búsqueda');
        }
    }

    function displayResults(data) {
        if (!data.results || data.results.length === 0) {
            showEmpty('No se encontraron resultados');
            return;
        }

        searchResults = data.results;
        currentIndex = -1;
        
        let html = '';
        let currentCategory = '';
        
        data.results.forEach((result, index) => {
            if (result.category !== currentCategory) {
                currentCategory = result.category;
                html += `<div class="search-result-category">${escapeHtml(result.category)}</div>`;
            }
            
            html += `
                <a href="${escapeHtml(result.url)}" class="search-result-item" data-index="${index}" data-url="${escapeHtml(result.url)}">
                    <div class="search-result-icon ${escapeHtml(result.iconClass)}">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="${escapeHtml(result.icon)}"/></svg>
                    </div>
                    <div class="search-result-content">
                        <div class="search-result-title">${escapeHtml(result.title)}</div>
                        <div class="search-result-meta">${escapeHtml(result.meta)}</div>
                    </div>
                    ${result.badge ? `<span class="search-result-badge ${result.badgeClass}">${escapeHtml(result.badge)}</span>` : ''}
                </a>
            `;
        });
        
        resultsContainer.innerHTML = html;
        
        const firstItem = resultsContainer.querySelector('.search-result-item');
        if (firstItem) {
            firstItem.classList.add('selected');
            currentIndex = 0;
        }
    }

    function showHint(message) {
        resultsContainer.innerHTML = '<div class="search-hint"><span></span></div>';
        const span = resultsContainer.querySelector('.search-hint span');
        if (span) span.textContent = message;
    }

    function showLoading() {
        resultsContainer.innerHTML = `
            <div class="search-loading">
                <div class="spinner"></div>
                <span>Buscando...</span>
            </div>
        `;
    }

    function showEmpty(message) {
        resultsContainer.innerHTML = `
            <div class="search-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <span>${message}</span>
            </div>
        `;
    }

    function showError(message) {
        resultsContainer.innerHTML = `
            <div class="search-error">
                <span>${message}</span>
            </div>
        `;
    }

    function resetResults() {
        resultsContainer.innerHTML = `
            <div class="search-hint">
                <span>Escribe para buscar en tickets, usuarios y equipos</span>
            </div>
        `;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    window.addEventListener('DOMContentLoaded', init);
})();