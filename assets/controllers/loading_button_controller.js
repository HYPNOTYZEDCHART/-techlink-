import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this.element.addEventListener('submit', () => this.showLoading())
    }

    showLoading() {
        const button = this.element.querySelector('button[type="submit"]')
        if (button && !button.disabled) {
            button.disabled = true
            button.dataset.originalText = button.textContent
            button.textContent = 'Chargement...'
        }
    }
}