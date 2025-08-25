// Mission Search Integration Component
class MissionSearchIntegration {
    constructor(options = {}) {
        this.searchEndpoint = options.searchEndpoint || '/mission/search';
        this.listContainer = options.listContainer || '#mission-list';
        this.searchInput = options.searchInput || '#search-input';
        this.filterForm = options.filterForm || '#filter-form';
        this.paginationContainer = options.paginationContainer || '#pagination';
        this.loadingIndicator = options.loadingIndicator || '#loading';
        this.currentPage = 1;
        this.currentQuery = '';
        this.currentFilters = {};
        
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        // Search input with debounce
        const searchInput = document.querySelector(this.searchInput);
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.currentQuery = e.target.value;
                    this.currentPage = 1;
                    this.performSearch();
                }, 300);
            });
        }

        // Filter form submission
        const filterForm = document.querySelector(this.filterForm);
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.currentFilters = this.extractFilters(filterForm);
                this.currentPage = 1;
                this.performSearch();
            });
        }

        // Pagination clicks
        document.addEventListener('click', (e) => {
            if (e.target.matches('.pagination a[data-page]')) {
                e.preventDefault();
                this.currentPage = parseInt(e.target.dataset.page);
                this.performSearch();
            }
        });
    }

    extractFilters(form) {
        const formData = new FormData(form);
        const filters = {};
        
        for (let [key, value] of formData.entries()) {
            if (value.trim()) {
                filters[key] = value;
            }
        }
        
        return filters;
    }

    async performSearch() {
        try {
            this.showLoading(true);
            
            const params = new URLSearchParams({
                page: this.currentPage,
                ...this.currentFilters
            });
            
            if (this.currentQuery) {
                params.set('q', this.currentQuery);
            }

            const response = await fetch(`${this.searchEndpoint}?${params}`);
            const data = await response.json();

            if (response.ok) {
                this.updateResults(data);
                this.updatePagination(data);
                this.updateURL(params);
            } else {
                this.showError(data.error || 'Search failed');
            }
        } catch (error) {
            this.showError('Network error occurred');
            console.error('Search error:', error);
        } finally {
            this.showLoading(false);
        }
    }

    updateResults(data) {
        const container = document.querySelector(this.listContainer);
        if (!container) return;

        if (data.items && data.items.length > 0) {
            const html = data.items.map(mission => this.renderMissionItem(mission)).join('');
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="no-results">No missions found</div>';
        }
    }

    renderMissionItem(mission) {
        return `
            <div class="mission-item" data-id="${mission.id}">
                <h3 class="mission-title">
                    <a href="/mission/view/${mission.id}">${this.escapeHtml(mission.title)}</a>
                </h3>
                <p class="mission-description">${this.escapeHtml(mission.description || '')}</p>
                <div class="mission-meta">
                    <span class="service-date">Date: ${mission.serviceDate || 'N/A'}</span>
                    <span class="quantity">Quantity: ${mission.quantity || 0}</span>
                    <span class="country">Country: ${this.escapeHtml(mission.destinationCountry || '')}</span>
                    <span class="status status-${mission.status}">${this.escapeHtml(mission.status || '')}</span>
                </div>
            </div>
        `;
    }

    updatePagination(data) {
        const container = document.querySelector(this.paginationContainer);
        if (!container) return;

        if (data.pages > 1) {
            let html = '<div class="pagination">';
            
            // Previous button
            if (data.page > 1) {
                html += `<a href="#" class="pagination-btn" data-page="${data.page - 1}">Previous</a>`;
            }
            
            // Page numbers
            for (let i = 1; i <= data.pages; i++) {
                const isActive = i === data.page ? 'active' : '';
                html += `<a href="#" class="pagination-btn ${isActive}" data-page="${i}">${i}</a>`;
            }
            
            // Next button
            if (data.page < data.pages) {
                html += `<a href="#" class="pagination-btn" data-page="${data.page + 1}">Next</a>`;
            }
            
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '';
        }
    }

    updateURL(params) {
        const url = new URL(window.location);
        
        // Clear existing search/filter params
        url.searchParams.delete('q');
        url.searchParams.delete('page');
        Object.keys(this.currentFilters).forEach(key => {
            url.searchParams.delete(key);
        });
        
        // Add current params
        params.forEach((value, key) => {
            if (value) {
                url.searchParams.set(key, value);
            }
        });
        
        // Update browser history without reloading
        window.history.pushState({}, '', url);
    }

    showLoading(show) {
        const indicator = document.querySelector(this.loadingIndicator);
        if (indicator) {
            indicator.style.display = show ? 'block' : 'none';
        }
    }

    showError(message) {
        // You can customize this to show errors in your preferred way
        console.error('Search error:', message);
        
        // Example: show in a toast or alert
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        errorDiv.textContent = message;
        
        const container = document.querySelector(this.listContainer);
        if (container) {
            container.prepend(errorDiv);
            setTimeout(() => errorDiv.remove(), 5000);
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Public method to trigger search programmatically
    search(query = '', filters = {}) {
        this.currentQuery = query;
        this.currentFilters = filters;
        this.currentPage = 1;
        this.performSearch();
    }

    // Public method to refresh current results
    refresh() {
        this.performSearch();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Initialize the search integration
    window.missionSearch = new MissionSearchIntegration({
        searchEndpoint: '/mission/search',
        listContainer: '#mission-list',
        searchInput: '#search-input',
        filterForm: '#filter-form',
        paginationContainer: '#pagination',
        loadingIndicator: '#loading'
    });
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MissionSearchIntegration;
}
