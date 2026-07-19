import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['header']

    initialize() {
        this._onScroll = this.onScroll.bind(this)
    }

    connect() {
        window.addEventListener('scroll', this._onScroll)
    }

    onScroll() {
        if (window.scrollY > 50) {
            this.headerTarget.classList.add('py-2')
            this.headerTarget.classList.remove('py-4')
        } else {
            this.headerTarget.classList.add('py-4')
            this.headerTarget.classList.remove('py-2')
        }
    }

    disconnect() {
        window.removeEventListener('scroll', this._onScroll)
    }
}