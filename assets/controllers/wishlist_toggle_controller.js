import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['icon']
    static values = { url: String, token: String }

    async toggle(event) {
        event.preventDefault()
        event.stopPropagation()

        const response = await fetch(this.urlValue, {
            method: 'POST',
            headers: { 
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.tokenValue
            },
        })

        if (response.redirected || !response.ok) {
            window.location.href = '/login';
            return;
        }

        const data = await response.json()

        if (data.added) {
            this.iconTarget.classList.add('text-orange-500')
            this.iconTarget.textContent = '♥'
            this.updateWishlistCount(1)
        } else {
            this.iconTarget.classList.remove('text-orange-500')
            this.iconTarget.textContent = '♡'
            this.updateWishlistCount(-1)
        }
    }

    updateWishlistCount(change) {
        const badge = document.querySelector('[data-wishlist-count]')
        if (badge) {
            const currentCount = parseInt(badge.textContent) || 0
            badge.textContent = currentCount + change
        }
    }
}