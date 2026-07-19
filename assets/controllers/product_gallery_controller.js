import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['mainImage']

    change(event) {
        const newSrc = event.currentTarget.dataset.image
        this.mainImageTarget.src = newSrc
    }
}