import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'backdrop',
        'panel',
        'itemsContainer',
        'itemCountText',
        'totalPriceText',
        'shippingPercentText',
        'shippingProgressBar',
        'shippingMessageText',
        'footer',
        'checkoutBtn'
    ];

    static values = {
        stateUrl: { type: String, default: '/panier/etat' },
        updateUrl: { type: String, default: '/panier/modifier' },
        removeUrl: { type: String, default: '/panier/supprimer' },
        checkoutUrl: { type: String, default: '/panier/commander' },
        cartUrl: { type: String, default: '/panier' },
    };

    connect() {
        this.isOpen = false;
        this.boundKeydown = this.onKeydown.bind(this);
        this.boundOpen = () => this.open();
        this.boundRefresh = () => this.fetchState();

        window.addEventListener('cart:open', this.boundOpen);
        window.addEventListener('cart:refresh', this.boundRefresh);
        document.addEventListener('keydown', this.boundKeydown);

        // Intercepter les clics sur les liens du panier dans l'en-tête pour ouvrir le tiroir
        document.querySelectorAll('a[href$="/panier"]').forEach(link => {
            // Ne pas intercepter le lien dans le drawer lui-même
            if (!this.element.contains(link)) {
                link.addEventListener('click', (e) => {
                    // Si on est déjà sur la page /panier, laisser la navigation normale
                    if (window.location.pathname === '/panier') return;
                    e.preventDefault();
                    this.open();
                });
            }
        });

        // Chargement initial de l'état
        this.fetchState();
    }

    disconnect() {
        window.removeEventListener('cart:open', this.boundOpen);
        window.removeEventListener('cart:refresh', this.boundRefresh);
        document.removeEventListener('keydown', this.boundKeydown);
        document.body.classList.remove('overflow-hidden');
    }

    onKeydown(event) {
        if (event.key === 'Escape' && this.isOpen) {
            this.close();
        }
    }

    open() {
        this.isOpen = true;
        this.fetchState();

        if (this.hasBackdropTarget) {
            this.backdropTarget.classList.remove('opacity-0', 'pointer-events-none');
            this.backdropTarget.classList.add('opacity-100', 'pointer-events-auto');
        }

        if (this.hasPanelTarget) {
            this.panelTarget.classList.remove('translate-x-full');
            this.panelTarget.classList.add('translate-x-0');
        }

        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.isOpen = false;

        if (this.hasBackdropTarget) {
            this.backdropTarget.classList.remove('opacity-100', 'pointer-events-auto');
            this.backdropTarget.classList.add('opacity-0', 'pointer-events-none');
        }

        if (this.hasPanelTarget) {
            this.panelTarget.classList.remove('translate-x-0');
            this.panelTarget.classList.add('translate-x-full');
        }

        document.body.classList.remove('overflow-hidden');
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
            this.render(data);
        } catch (error) {
            console.error('Erreur chargement état panier:', error);
        }
    }

    render(data) {
        const count = data.count || 0;
        const total = data.total || 0;
        const formattedTotal = data.formattedTotal || '0 XOF';
        const items = data.items || [];
        const percent = data.freeShippingPercent || 0;
        const remaining = data.freeShippingRemaining || 0;
        const remainingFormatted = data.freeShippingRemainingFormatted || '0 XOF';

        // 1. Compteurs
        if (this.hasItemCountTextTarget) {
            this.itemCountTextTarget.textContent = count;
        }
        if (this.hasTotalPriceTextTarget) {
            this.totalPriceTextTarget.textContent = formattedTotal;
        }

        // 2. Synchroniser les compteurs dans le Header
        document.querySelectorAll('[data-cart-count]').forEach(el => {
            el.textContent = count;
        });

        // 3. Barre de livraison
        if (this.hasShippingProgressBarTarget) {
            this.shippingProgressBarTarget.style.width = `${percent}%`;
        }
        if (this.hasShippingPercentTextTarget) {
            this.shippingPercentTextTarget.textContent = `${percent}%`;
        }
        if (this.hasShippingMessageTextTarget) {
            if (percent >= 100) {
                this.shippingMessageTextTarget.innerHTML = `<span class="text-emerald-400 font-bold">🎉 Félicitations ! Livraison offerte à Dakar.</span>`;
            } else {
                this.shippingMessageTextTarget.innerHTML = `Plus que <strong class="text-orange-400">${remainingFormatted}</strong> pour la livraison offerte.`;
            }
        }

        // 4. Liste des articles
        if (this.hasItemsContainerTarget) {
            if (items.length === 0) {
                this.itemsContainerTarget.innerHTML = `
                    <div class="py-16 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center mx-auto mb-3 text-2xl text-neutral-500">
                            🛒
                        </div>
                        <p class="text-sm font-bold text-white mb-1">Votre panier est vide</p>
                        <p class="text-xs text-neutral-400 mb-5 max-w-[220px] mx-auto">
                            Découvrez notre sélection de laptops, stations de travail et smartphones.
                        </p>
                        <a
                            href="/produits"
                            data-action="click->cart-drawer#close"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-orange-500 hover:bg-orange-400 text-black font-semibold text-xs rounded-xl shadow-lg transition"
                        >
                            <span>Explorer le catalogue</span>
                            <span>→</span>
                        </a>
                    </div>
                `;
                if (this.hasFooterTarget) {
                    this.footerTarget.classList.add('opacity-50', 'pointer-events-none');
                }
            } else {
                if (this.hasFooterTarget) {
                    this.footerTarget.classList.remove('opacity-50', 'pointer-events-none');
                }

                let html = '';
                items.forEach(item => {
                    const imageSrc = item.image ? `/images/products/${item.image}` : '';
                    html += `
                        <div class="flex items-start gap-3 p-3 rounded-xl bg-[#12151E] border border-white/5 hover:border-white/10 transition group">
                            <!-- Image -->
                            <a href="/produit/${item.slug}" class="w-16 h-16 rounded-lg bg-neutral-950 border border-white/5 overflow-hidden shrink-0 flex items-center justify-center p-1">
                                ${imageSrc ? `<img src="${imageSrc}" alt="" class="w-full h-full object-contain">` : `<span class="text-lg text-neutral-600">⚡</span>`}
                            </a>

                            <!-- Infos -->
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-mono text-neutral-400 uppercase">${this.escapeHtml(item.brand || 'TechLink')}</p>
                                <h4 class="text-xs font-bold text-white truncate mb-1">
                                    <a href="/produit/${item.slug}" class="hover:text-cyan-400 transition">
                                        ${this.escapeHtml(item.name)}
                                    </a>
                                </h4>

                                ${item.selectedColor ? `<p class="text-[10px] text-neutral-400 mb-2">Couleur : <span class="text-white">${this.escapeHtml(item.selectedColor)}</span></p>` : ''}

                                <div class="flex items-center justify-between gap-2 mt-2">
                                    <!-- Stepper quantité -->
                                    <div class="flex items-center border border-white/10 rounded-lg bg-black/40 overflow-hidden">
                                        <button
                                            type="button"
                                            data-action="click->cart-drawer#updateQty"
                                            data-cart-id="${item.id}"
                                            data-qty="${item.quantity - 1}"
                                            data-token="${item.updateToken}"
                                            class="w-6 h-6 flex items-center justify-center text-neutral-400 hover:text-white hover:bg-white/10 text-xs transition"
                                            title="Diminuer"
                                        >−</button>
                                        <span class="w-7 text-center font-mono text-xs text-white">${item.quantity}</span>
                                        <button
                                            type="button"
                                            data-action="click->cart-drawer#updateQty"
                                            data-cart-id="${item.id}"
                                            data-qty="${item.quantity + 1}"
                                            data-token="${item.updateToken}"
                                            ${item.quantity >= item.stock ? 'disabled' : ''}
                                            class="w-6 h-6 flex items-center justify-center text-neutral-400 hover:text-white hover:bg-white/10 text-xs transition disabled:opacity-30 disabled:cursor-not-allowed"
                                            title="Augmenter"
                                        >+</button>
                                    </div>

                                    <!-- Prix sous-total -->
                                    <div class="text-right">
                                        <p class="text-xs font-mono font-bold text-orange-400">${this.escapeHtml(item.formattedItemTotal)}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Bouton Supprimer -->
                            <button
                                type="button"
                                data-action="click->cart-drawer#removeItem"
                                data-cart-id="${item.id}"
                                data-token="${item.removeToken}"
                                class="text-neutral-500 hover:text-red-400 p-1 text-sm transition"
                                title="Supprimer cet article"
                            >
                                ×
                            </button>
                        </div>
                    `;
                });
                this.itemsContainerTarget.innerHTML = html;
            }
        }
    }

    async updateQty(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const id = button.dataset.cartId;
        const qty = parseInt(button.dataset.qty, 10);
        const token = button.dataset.token;

        if (!id || isNaN(qty) || !token) return;

        const formData = new FormData();
        formData.append('_token', token);
        formData.append('quantity', qty);

        try {
            const response = await fetch(`${this.updateUrlValue}/${id}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
            });
            const data = await response.json();
            if (data.success) {
                await this.fetchState();
            } else if (data.error) {
                alert(data.error);
            }
        } catch (error) {
            console.error('Erreur mise à jour quantité:', error);
        }
    }

    async removeItem(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const id = button.dataset.cartId;
        const token = button.dataset.token;

        if (!id || !token) return;

        const formData = new FormData();
        formData.append('_token', token);

        try {
            const response = await fetch(`${this.removeUrlValue}/${id}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
            });
            const data = await response.json();
            if (data.success) {
                await this.fetchState();
            }
        } catch (error) {
            console.error('Erreur suppression article:', error);
        }
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
