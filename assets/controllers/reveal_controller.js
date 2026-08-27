import { Controller } from '@hotwired/stimulus';

/*
 * Reveal Controller
 * Utilise l'IntersectionObserver pour déclencher des animations
 * quand l'élément entre dans le viewport.
 */
export default class extends Controller {
    static values = {
        threshold: { type: Number, default: 0.1 },
        rootMargin: { type: String, default: '0px 0px -50px 0px' }
    }

    connect() {
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.element.classList.add('is-revealed');
                    // On arrête d'observer une fois révélé pour les perfs
                    this.observer.unobserve(this.element);
                }
            });
        }, {
            threshold: this.thresholdValue,
            rootMargin: this.rootMarginValue
        });

        this.observer.observe(this.element);
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }
}
