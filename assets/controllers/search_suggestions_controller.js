import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['input', 'results']

    connect() {
        this.timeout = null
        this._onClickOutside = this.onClickOutside.bind(this)
        document.addEventListener('click', this._onClickOutside)
    }

    disconnect() {
        document.removeEventListener('click', this._onClickOutside)
    }

    search() {
        clearTimeout(this.timeout)

        const query = this.inputTarget.value.trim()

        if (query.length < 2) {
            this.hideResults()
            return
        }

        this.timeout = setTimeout(() => {
            fetch(`/api/recherche-produits?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(products => this.renderResults(products))
        }, 300)
    }

    renderResults(products) {
        if (products.length === 0) {
            this.resultsTarget.innerHTML = '<p class="p-4 text-sm text-neutral-500">Aucun résultat</p>'
            this.showResults()
            return
        }

        this.resultsTarget.innerHTML = products.map(product => `
            <a href="/produit/${product.slug}" class="flex items-center gap-3 px-4 py-3 hover:bg-neutral-900 transition">
                <div class="w-12 h-12 rounded-lg bg-neutral-900 flex-shrink-0 overflow-hidden">
                    ${product.image ? `<img src="/images/products/${product.image}" class="w-full h-full object-cover">` : ''}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-neutral-500">${product.brand}</p>
                    <p class="text-sm font-medium truncate">${product.name}</p>
                </div>
                <p class="text-sm text-orange-500 font-semibold whitespace-nowrap">${Math.round(product.price / 100).toLocaleString('fr-FR')} XOF</p>
            </a>
        `).join('')

        this.showResults()
    }

    showResults() {
        this.resultsTarget.classList.remove('hidden')
    }

    hideResults() {
        this.resultsTarget.classList.add('hidden')
    }

    onClickOutside(event) {
        if (!this.element.contains(event.target)) {
            this.hideResults()
        }
    }
}