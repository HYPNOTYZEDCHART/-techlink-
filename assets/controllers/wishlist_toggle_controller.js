import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['icon']
    static values = { url: String }

    async toggle(event) {
        event.preventDefault()

        const response = await fetch(this.urlValue, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })

        const data = await response.json()

        if (data.added) {
            this.iconTarget.classList.add('text-orange-500')
            this.iconTarget.textContent = '♥'
        } else {
            this.iconTarget.classList.remove('text-orange-500')
            this.iconTarget.textContent = '♡'
        }
    }
}