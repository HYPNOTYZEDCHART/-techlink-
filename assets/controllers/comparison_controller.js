import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['bar', 'slotsContainer', 'countText', 'compareBtn', 'compareCountBadge'];
    static values = {
        stateUrl: { type: String, default: '/comparateur/etat' },
        addUrl: { type: String, default: '/comparateur/ajouter' },
        removeUrl: { type: String, default: '/comparateur/retirer' },
        clearUrl: { type: String, default: '/comparateur/vider' },
        compareUrl: { type: String, default: '/comparateur' },
    };

    connect() {
        this.fetchState();

        // Écouter les mises à jour globales (ex: navigation Turbo ou rafraîchissement catalogue)
        this.boundOnUpdate = () => this.fetchState();
        window.addEventListener('comparison:sync', this.boundOnUpdate);
        document.addEventListener('turbo:load', this.boundOnUpdate);
    }

    disconnect() {
        if (this.boundOnUpdate) {
            window.removeEventListener('comparison:sync', this.boundOnUpdate);
            document.removeEventListener('turbo:load', this.boundOnUpdate);
        }
    }

    async fetchState() {
        try {
            const response = await fetch(this.stateUrlValue, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            if (!response.ok) return;
            const data = await response.json();
            this.currentProducts = data.products || [];
            this.render(data);
        } catch (error) {
            console.error('Erreur chargement état comparateur:', error);
        }
    }

    async toggle(event) {
        event.preventDefault();
        event.stopPropagation();

        const button = event.currentTarget;
        const productId = parseInt(button.dataset.productId || event.params?.id, 10);
        if (!productId) return;

        const isAlreadyCompared = this.currentProducts && this.currentProducts.some(p => p.id === productId);

        if (isAlreadyCompared) {
            await this.executeRemove(productId);
        } else {
            if (this.currentProducts && this.currentProducts.length >= 3) {
                this.flashNotification('Maximum de 3 produits atteint dans le comparateur.', 'error');
                return;
            }
            await this.executeAdd(productId);
        }
    }

    async executeAdd(productId) {
        try {
            const response = await fetch(`${this.addUrlValue}/${productId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();
            if (data.success) {
                await this.fetchState();
                this.flashNotification(data.message || 'Produit ajouté au comparateur.', 'success');
            } else {
                this.flashNotification(data.message || 'Impossible d\'ajouter au comparateur.', 'error');
            }
        } catch (error) {
            console.error('Erreur ajout comparateur:', error);
            this.flashNotification('Erreur réseau lors de l\'ajout.', 'error');
        }
    }

    async executeRemove(productId) {
        try {
            const response = await fetch(`${this.removeUrlValue}/${productId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();
            if (data.success) {
                await this.fetchState();
                this.flashNotification(data.message || 'Produit retiré du comparateur.', 'info');
            }
        } catch (error) {
            console.error('Erreur retrait comparateur:', error);
        }
    }

    async remove(event) {
        event.preventDefault();
        event.stopPropagation();
        const id = parseInt(event.params?.id || event.currentTarget.dataset.id, 10);
        if (id) {
            await this.executeRemove(id);
        }
    }

    async clear(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        try {
            const response = await fetch(this.clearUrlValue, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();
            if (data.success) {
                await this.fetchState();
                this.flashNotification('Comparateur réinitialisé.', 'info');
            }
        } catch (error) {
            console.error('Erreur vidage comparateur:', error);
        }
    }

    render(data) {
        const count = data.count || 0;
        const products = data.products || [];

        // 1. Mise à jour de la barre flottante
        if (this.hasBarTarget) {
            if (count > 0) {
                this.barTarget.classList.remove('translate-y-36', 'opacity-0', 'pointer-events-none');
                this.barTarget.classList.add('translate-y-0', 'opacity-100', 'pointer-events-auto');
            } else {
                this.barTarget.classList.add('translate-y-36', 'opacity-0', 'pointer-events-none');
                this.barTarget.classList.remove('translate-y-0', 'opacity-100', 'pointer-events-auto');
            }
        }

        if (this.hasCountTextTarget) {
            this.countTextTarget.textContent = count;
        }

        if (this.hasCompareCountBadgeTarget) {
            this.compareCountBadgeTarget.textContent = count;
        }

        // 2. Rendu des slots
        if (this.hasSlotsContainerTarget) {
            let html = '';
            for (let i = 0; i < 3; i++) {
                const product = products[i];
                if (product) {
                    const imageSrc = product.image ? `/images/products/${product.image}` : '';
                    html += `
                        <div class="flex items-center gap-2 bg-white/5 border border-white/10 rounded-xl px-2.5 py-1.5 min-w-[130px] max-w-[160px] relative group shrink-0">
                            ${imageSrc ? `<img src="${imageSrc}" alt="" class="w-8 h-8 rounded-lg object-cover bg-neutral-900 border border-white/5 shrink-0">` : `<div class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center text-xs text-neutral-400">⚡</div>`}
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium text-white truncate">${this.escapeHtml(product.name)}</p>
                                <p class="text-[10px] text-cyan-400 font-mono">${this.escapeHtml(product.price)}</p>
                            </div>
                            <button
                                type="button"
                                data-action="click->comparison#remove"
                                data-comparison-id-param="${product.id}"
                                class="w-5 h-5 rounded-full bg-neutral-800 hover:bg-red-500/80 text-neutral-400 hover:text-white flex items-center justify-center text-xs transition"
                                title="Retirer"
                            >
                                ×
                            </button>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="hidden sm:flex items-center justify-center border border-dashed border-white/10 rounded-xl px-3 py-2 min-w-[120px] text-[11px] text-neutral-500 font-mono shrink-0">
                            + Slot vide
                        </div>
                    `;
                }
            }
            this.slotsContainerTarget.innerHTML = html;
        }

        // 3. Mise à jour du badge dans le Header
        const headerBadge = document.querySelector('[data-comparison-header-count]');
        if (headerBadge) {
            if (count > 0) {
                headerBadge.textContent = count;
                headerBadge.classList.remove('hidden');
                headerBadge.classList.add('flex');
            } else {
                headerBadge.classList.add('hidden');
                headerBadge.classList.remove('flex');
            }
        }

        // 4. Mise à jour de tous les boutons de comparaison de la page
        const comparedIds = new Set(products.map(p => p.id));
        document.querySelectorAll('[data-comparison-button]').forEach(btn => {
            const btnId = parseInt(btn.dataset.productId, 10);
            if (comparedIds.has(btnId)) {
                btn.setAttribute('aria-pressed', 'true');
                btn.classList.add('bg-cyan-500/20', 'text-cyan-300', 'border-cyan-500/50');
                btn.classList.remove('bg-neutral-900/90', 'text-neutral-400', 'border-neutral-800');
                const label = btn.querySelector('[data-comparison-button-label]');
                if (label) label.textContent = '✓ Comparé';
            } else {
                btn.setAttribute('aria-pressed', 'false');
                btn.classList.remove('bg-cyan-500/20', 'text-cyan-300', 'border-cyan-500/50');
                btn.classList.add('bg-neutral-900/90', 'text-neutral-400', 'border-neutral-800');
                const label = btn.querySelector('[data-comparison-button-label]');
                if (label) label.textContent = '+ Comparer';
            }
        });
    }

    flashNotification(message, type = 'info') {
        const existing = document.getElementById('comparison-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'comparison-toast';
        toast.className = `fixed bottom-24 right-6 z-50 px-4 py-2.5 rounded-xl border text-xs font-medium shadow-2xl flex items-center gap-2 transition-all duration-300 transform translate-y-4 opacity-0 ${
            type === 'success'
                ? 'bg-[#12151E] border-cyan-500/40 text-cyan-300'
                : type === 'error'
                ? 'bg-[#12151E] border-red-500/40 text-red-400'
                : 'bg-[#12151E] border-white/20 text-neutral-300'
        }`;
        toast.innerHTML = `<span>⚡</span><span>${this.escapeHtml(message)}</span>`;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-4', 'opacity-0');
        });

        setTimeout(() => {
            toast.classList.add('translate-y-4', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    }

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}
