import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.closeOnClickOutside = this.closeOnClickOutside.bind(this);
        this.closeOnEscape = this.closeOnEscape.bind(this);
        document.addEventListener('click', this.closeOnClickOutside);
        document.addEventListener('keydown', this.closeOnEscape);
    }

    disconnect() {
        document.removeEventListener('click', this.closeOnClickOutside);
        document.removeEventListener('keydown', this.closeOnEscape);
    }

    closeOnClickOutside(event) {
        if (!this.element.contains(event.target) && this.element.hasAttribute('open')) {
            this.element.removeAttribute('open');
        }
    }

    closeOnEscape(event) {
        if (event.key === 'Escape' && this.element.hasAttribute('open')) {
            this.element.removeAttribute('open');
        }
    }
}
