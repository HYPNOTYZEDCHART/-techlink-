import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this.element.addEventListener('submit', (event) => this.handleSubmit(event))
    }

    async handleSubmit(event) {
        event.preventDefault()

        const form = this.element
        const button = form.querySelector('button[type="submit"]')

        if (button) {
            button.disabled = true
            button.textContent = 'Ajout en cours...'
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })

            if (response.ok) {
                this.showToast('✔ Produit ajouté au panier')
                this.updateCartCount()
            } else {
                this.showToast('Une erreur est survenue', true)
            }
        } catch (e) {
            this.showToast('Une erreur est survenue', true)
        }

        if (button) {
            button.disabled = false
            button.textContent = 'Ajouter au panier'
        }
    }

    showToast(message, isError = false) {
        const toast = document.createElement('div')
        toast.textContent = message
        toast.className = `fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-full text-sm font-semibold shadow-xl transition-opacity duration-300 ${isError ? 'bg-red-500 text-white' : 'bg-green-500 text-black'}`
        document.body.appendChild(toast)

        setTimeout(() => {
            toast.style.opacity = '0'
            setTimeout(() => toast.remove(), 300)
        }, 2000)
    }

    updateCartCount() {
        const badge = document.querySelector('[data-cart-count]')
        if (badge) {
            badge.textContent = parseInt(badge.textContent) + 1
        }
    }
}