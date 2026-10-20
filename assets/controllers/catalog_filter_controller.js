import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'content', 'counter', 'activeFilters', 'mobileSidebar'];

    connect() {
        this.debounceTimeout = null;
        this.isLoading = false;

        // Écouter popstate pour gérer l'historique du navigateur (boutons précédent/suivant)
        this.boundPopstate = () => this.loadFromUrl(window.location.href, false);
        window.addEventListener('popstate', this.boundPopstate);
    }

    disconnect() {
        if (this.boundPopstate) {
            window.removeEventListener('popstate', this.boundPopstate);
        }
        if (this.debounceTimeout) {
            clearTimeout(this.debounceTimeout);
        }
    }

    // Déclenché lors du changement d'une case à cocher, d'un bouton radio ou du tri
    filterChange() {
        this.applyFilters();
    }

    // Déclenché sur les inputs de prix min/max avec un debounce de 350ms
    debouncedPriceChange() {
        if (this.debounceTimeout) clearTimeout(this.debounceTimeout);
        this.debounceTimeout = setTimeout(() => {
            this.applyFilters();
        }, 350);
    }

    // Réinitialise tous les filtres
    reset() {
        if (!this.hasFormTarget) return;

        // Décocher les cases
        this.formTarget.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        this.formTarget.querySelectorAll('input[type="radio"]').forEach(rb => {
            if (rb.value === '') rb.checked = true;
            else rb.checked = false;
        });

        // Vider les inputs prix
        this.formTarget.querySelectorAll('input[type="number"]').forEach(inp => inp.value = '');

        // Réinitialiser le tri
        const sortSelect = this.formTarget.querySelector('select[name="tri"]');
        if (sortSelect) sortSelect.value = 'nouveautes';

        this.applyFilters();
    }

    // Suppression d'un filtre individuel depuis les badges "Filtres actifs"
    removeFilter(event) {
        event.preventDefault();
        const type = event.params.type;
        const value = event.params.value;

        if (!this.hasFormTarget) return;

        if (type === 'categorie') {
            const input = this.formTarget.querySelector(`input[name="categorie[]"][value="${value}"]`);
            if (input) input.checked = false;
        } else if (type === 'marque') {
            const input = this.formTarget.querySelector(`input[name="marque[]"][value="${value}"]`);
            if (input) input.checked = false;
        } else if (type === 'condition') {
            const defaultRadio = this.formTarget.querySelector('input[name="condition"][value=""]');
            if (defaultRadio) defaultRadio.checked = true;
        } else if (type === 'stock') {
            const stockCb = this.formTarget.querySelector('input[name="en_stock"]');
            if (stockCb) stockCb.checked = false;
        } else if (type === 'prix_min') {
            const minInp = this.formTarget.querySelector('input[name="prix_min"]');
            if (minInp) minInp.value = '';
        } else if (type === 'prix_max') {
            const maxInp = this.formTarget.querySelector('input[name="prix_max"]');
            if (maxInp) maxInp.value = '';
        }

        this.applyFilters();
    }

    // Basculer la sidebar filtre sur mobile
    toggleMobileSidebar() {
        if (this.hasMobileSidebarTarget) {
            this.mobileSidebarTarget.classList.toggle('hidden');
        }
    }

    closeMobileSidebar() {
        if (this.hasMobileSidebarTarget) {
            this.mobileSidebarTarget.classList.add('hidden');
        }
    }

    // Application des filtres via AJAX
    async applyFilters(pushToHistory = true) {
        if (!this.hasFormTarget) return;

        const formData = new FormData(this.formTarget);
        const searchParams = new URLSearchParams();

        for (const [key, val] of formData.entries()) {
            if (val !== null && val.trim() !== '') {
                searchParams.append(key, val.trim());
            }
        }

        // Toujours réinitialiser à la page 1 lors d'un nouveau filtrage
        searchParams.delete('page');

        const baseUrl = this.formTarget.getAttribute('action') || window.location.pathname;
        const queryStr = searchParams.toString();
        const targetUrl = queryStr ? `${baseUrl}?${queryStr}` : baseUrl;

        await this.loadFromUrl(targetUrl, pushToHistory);
    }

    async loadFromUrl(url, pushToHistory = true) {
        if (this.isLoading) return;
        this.isLoading = true;

        // Feedback visuel doux (skeleton/opacité)
        if (this.hasContentTarget) {
            this.contentTarget.classList.add('opacity-40', 'pointer-events-none', 'transition-opacity', 'duration-200');
        }

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                },
            });

            if (!response.ok) {
                window.location.href = url;
                return;
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // 1. Mettre à jour la grille de produits et la pagination
            const newContent = doc.querySelector('[data-catalog-filter-target="content"]');
            if (newContent && this.hasContentTarget) {
                this.contentTarget.innerHTML = newContent.innerHTML;
            }

            // 2. Mettre à jour le compteur de résultats
            const newCounter = doc.querySelector('[data-catalog-filter-target="counter"]');
            if (newCounter && this.hasCounterTarget) {
                this.counterTarget.innerHTML = newCounter.innerHTML;
            }

            // 3. Mettre à jour les badges de filtres actifs
            const newActiveFilters = doc.querySelector('[data-catalog-filter-target="activeFilters"]');
            if (newActiveFilters && this.hasActiveFiltersTarget) {
                this.activeFiltersTarget.innerHTML = newActiveFilters.innerHTML;
            }

            // 4. Mettre à jour l'URL sans recharger la page
            if (pushToHistory) {
                window.history.pushState({}, '', url);
            }

            // 5. Notifier le comparateur pour resynchroniser les états des boutons "+ Comparer"
            window.dispatchEvent(new CustomEvent('comparison:sync'));

        } catch (error) {
            console.error('Erreur de filtrage AJAX:', error);
            // Fallback en navigation classique
            window.location.href = url;
        } finally {
            this.isLoading = false;
            if (this.hasContentTarget) {
                this.contentTarget.classList.remove('opacity-40', 'pointer-events-none');
            }
        }
    }
}
