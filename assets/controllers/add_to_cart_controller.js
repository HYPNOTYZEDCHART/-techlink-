import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['button']

    add() {
        const button = this.buttonTarget
        const originalText = button.textContent

        button.textContent = '✔ Ajouté au panier'
        button.classList.add('bg-green-500', 'scale-105')
        button.classList.remove('bg-white', 'hover:bg-orange-500')

        setTimeout(() => {
            button.textContent = originalText
            button.classList.remove('bg-green-500', 'scale-105')
            button.classList.add('bg-white', 'hover:bg-orange-500')
        }, 1500)
    }
}