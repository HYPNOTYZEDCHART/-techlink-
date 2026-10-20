import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'backdrop', 'input', 'results', 'placeholder', 'spinner']

    connect() {
        this.timeout = null
        this.selectedIndex = -1
        this._onKeyDown = this.onKeyDown.bind(this)
        window.addEventListener('keydown', this._onKeyDown)
    }

    disconnect() {
        window.removeEventListener('keydown', this._onKeyDown)
    }

    onKeyDown(event) {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault()
            this.open()
            return
        }

        if (event.key === 'Escape' && !this.modalTarget.classList.contains('hidden')) {
            event.preventDefault()
            this.close()
            return
        }

        if (!this.modalTarget.classList.contains('hidden')) {
            if (event.key === 'ArrowDown') {
                event.preventDefault()
                this.navigateResults(1)
            } else if (event.key === 'ArrowUp') {
                event.preventDefault()
                this.navigateResults(-1)
            } else if (event.key === 'Enter') {
                const links = this.resultsTarget.querySelectorAll('a')
                if (this.selectedIndex >= 0 && links[this.selectedIndex]) {
                    event.preventDefault()
                    links[this.selectedIndex].click()
                }
            }
        }
    }

    open() {
        this.modalTarget.classList.remove('hidden')
        document.body.classList.add('overflow-hidden')
        setTimeout(() => {
            this.inputTarget.focus()
            this.inputTarget.select()
        }, 50)
    }

    close() {
        this.modalTarget.classList.add('hidden')
        document.body.classList.remove('overflow-hidden')
        this.selectedIndex = -1
    }

    onBackdropClick(event) {
        if (event.target === this.backdropTarget) {
            this.close()
        }
    }

    onInput() {
        clearTimeout(this.timeout)
        const query = this.inputTarget.value.trim()

        if (query.length < 2) {
            this.resultsTarget.innerHTML = ''
            this.resultsTarget.classList.add('hidden')
            if (this.hasPlaceholderTarget) {
                this.placeholderTarget.classList.remove('hidden')
            }
            if (this.hasSpinnerTarget) {
                this.spinnerTarget.classList.add('hidden')
            }
            return
        }

        if (this.hasPlaceholderTarget) {
            this.placeholderTarget.classList.add('hidden')
        }
        if (this.hasSpinnerTarget) {
            this.spinnerTarget.classList.remove('hidden')
        }

        this.timeout = setTimeout(() => {
            fetch(`/api/recherche-produits?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(products => this.renderResults(products, query))
                .catch(() => {
                    this.resultsTarget.innerHTML = '<p class="p-4 text-xs text-rose-500 font-mono">Erreur de chargement.</p>'
                    this.resultsTarget.classList.remove('hidden')
                })
                .finally(() => {
                    if (this.hasSpinnerTarget) {
                        this.spinnerTarget.classList.add('hidden')
                    }
                })
        }, 200)
    }

    renderResults(products, query) {
        this.selectedIndex = -1
        this.resultsTarget.classList.remove('hidden')

        if (products.length === 0) {
            this.resultsTarget.innerHTML = `
                <div class="p-8 text-center">
                    <p class="text-neutral-400 text-sm mb-1">Aucun produit ne correspond à <span class="text-white font-semibold">"${query}"</span></p>
                    <p class="text-xs text-neutral-500">Essaie avec un mot-clé plus court ou un nom de marque (ex: Apple, Samsung, Dell).</p>
                </div>
            `
            return
        }

        this.resultsTarget.innerHTML = `
            <div class="px-4 py-2 border-b border-neutral-800 text-[11px] font-mono uppercase tracking-wider text-neutral-500 flex justify-between">
                <span>${products.length} résultat${products.length > 1 ? 's' : ''} trouvé${products.length > 1 ? 's' : ''}</span>
                <span class="text-neutral-600">Naviguer avec ↑ ↓ • Entrée</span>
            </div>
            <div class="divide-y divide-neutral-900 max-h-[60vh] overflow-y-auto">
                ${products.map((p, idx) => `
                    <a href="/produit/${p.slug}" data-result-index="${idx}" class="search-result-item flex items-center gap-4 px-4 py-3.5 hover:bg-neutral-900/80 transition group">
                        <div class="w-12 h-12 rounded-xl bg-neutral-900 border border-neutral-800 flex-shrink-0 overflow-hidden flex items-center justify-center">
                            ${p.image ? `<img src="/images/products/${p.image}" class="w-full h-full object-cover">` : `<span class="text-xs text-neutral-600 font-mono">TL</span>`}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-xs font-mono text-neutral-500">${p.brand}</span>
                                ${p.category ? `<span class="text-[10px] text-neutral-500">• ${p.category}</span>` : ''}
                                ${p.condition ? `<span class="text-[10px] px-1.5 py-0.5 rounded-full font-mono ${p.condition.badgeClasses}">${p.condition.label}</span>` : ''}
                            </div>
                            <p class="text-sm font-semibold text-white group-hover:text-orange-500 transition truncate">${p.name}</p>
                            ${p.sku ? `<p class="text-[10px] text-neutral-600 font-mono">SKU: ${p.sku}</p>` : ''}
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm font-bold text-orange-500 whitespace-nowrap">${p.formattedPrice}</p>
                            <span class="text-[10px] font-mono ${p.inStock ? 'text-emerald-400' : 'text-rose-500'}">
                                ${p.inStock ? '● En stock' : '○ Rupture'}
                            </span>
                        </div>
                    </a>
                `).join('')}
            </div>
            <div class="p-3 bg-neutral-950/80 border-t border-neutral-900 text-center">
                <a href="/produits?q=${encodeURIComponent(query)}" class="text-xs text-orange-500 hover:underline font-mono">
                    Voir tous les résultats pour "${query}" →
                </a>
            </div>
        `
    }

    navigateResults(direction) {
        const items = this.resultsTarget.querySelectorAll('.search-result-item')
        if (items.length === 0) return

        items.forEach(el => el.classList.remove('bg-neutral-800/80'))

        this.selectedIndex += direction
        if (this.selectedIndex >= items.length) this.selectedIndex = 0
        if (this.selectedIndex < 0) this.selectedIndex = items.length - 1

        const active = items[this.selectedIndex]
        if (active) {
            active.classList.add('bg-neutral-800/80')
            active.scrollIntoView({ block: 'nearest' })
        }
    }
}
