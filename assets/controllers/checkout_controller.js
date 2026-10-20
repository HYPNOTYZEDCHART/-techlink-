import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'deliveryFeeSelect',
        'deliveryAddressBlock',
        'deliveryMethodInput',
        'paymentMethodInput',
        'couponInput',
        'couponMessage',
        'couponDiscountRow',
        'couponDiscountDisplay',
        'deliveryDisplay',
        'totalDisplay',
        'paymentOption',
        'deliveryOption',
        'submitButton'
    ];

    static values = {
        subtotal: Number,
        couponCheckUrl: String,
    };

    connect() {
        this.discount = 0;
        this.deliveryFee = 0;
        this.updateDeliveryFee();
        this.recalculateTotal();
    }

    setDeliveryMethod(event) {
        const method = event.currentTarget.dataset.method;
        this.deliveryMethodInputTarget.value = method;

        // Toggle UI states
        this.deliveryOptionTargets.forEach(card => {
            const isSelected = card.dataset.method === method;
            card.classList.toggle('border-orange-500', isSelected);
            card.classList.toggle('bg-orange-500/10', isSelected);
            card.classList.toggle('border-neutral-800', !isSelected);
            card.classList.toggle('bg-neutral-900/40', !isSelected);
        });

        if (method === 'PICKUP') {
            this.deliveryAddressBlockTarget.classList.add('hidden');
            this.deliveryFee = 0;
            this.deliveryDisplayTarget.textContent = 'Gratuit (Retrait Plateau)';
        } else {
            this.deliveryAddressBlockTarget.classList.remove('hidden');
            this.updateDeliveryFee();
        }

        this.recalculateTotal();
    }

    updateDeliveryFee() {
        if (this.deliveryMethodInputTarget.value === 'PICKUP') {
            this.deliveryFee = 0;
            this.deliveryDisplayTarget.textContent = 'Gratuit (Retrait Plateau)';
            this.recalculateTotal();
            return;
        }

        const fee = parseInt(this.deliveryFeeSelectTarget.value, 10);
        if (!isNaN(fee) && fee > 0) {
            this.deliveryFee = fee;
            this.deliveryDisplayTarget.textContent = fee.toLocaleString('fr-FR') + ' XOF';
        } else {
            this.deliveryFee = 0;
            this.deliveryDisplayTarget.textContent = 'Sélectionnez une zone';
        }

        this.recalculateTotal();
    }

    setPaymentMethod(event) {
        const method = event.currentTarget.dataset.payment;
        this.paymentMethodInputTarget.value = method;

        this.paymentOptionTargets.forEach(card => {
            const isSelected = card.dataset.payment === method;
            card.classList.toggle('border-orange-500', isSelected);
            card.classList.toggle('bg-orange-500/10', isSelected);
            card.classList.toggle('border-neutral-800', !isSelected);
            card.classList.toggle('bg-neutral-900/40', !isSelected);
            
            const radio = card.querySelector('input[type="radio"]');
            if (radio) radio.checked = isSelected;
        });
    }

    async applyCoupon(event) {
        event.preventDefault();
        const code = this.couponInputTarget.value.trim();
        if (!code) return;

        const btn = event.currentTarget;
        const initialText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Vérification...';

        try {
            const formData = new FormData();
            formData.append('code', code);

            const response = await fetch(this.couponCheckUrlValue, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.valid) {
                this.discount = data.discount;
                this.couponMessageTarget.textContent = data.message;
                this.couponMessageTarget.className = 'text-xs text-green-400 mt-2 block';
                this.couponDiscountRowTarget.classList.remove('hidden');
                this.couponDiscountDisplayTarget.textContent = '- ' + data.discount.toLocaleString('fr-FR') + ' XOF';
            } else {
                this.discount = 0;
                this.couponMessageTarget.textContent = data.message;
                this.couponMessageTarget.className = 'text-xs text-red-400 mt-2 block';
                this.couponDiscountRowTarget.classList.add('hidden');
            }
        } catch (e) {
            this.couponMessageTarget.textContent = 'Erreur lors de la vérification du code.';
            this.couponMessageTarget.className = 'text-xs text-red-400 mt-2 block';
        } finally {
            btn.disabled = false;
            btn.textContent = initialText;
            this.recalculateTotal();
        }
    }

    recalculateTotal() {
        const subtotal = this.subtotalValue;
        const total = Math.max(0, subtotal - this.discount) + this.deliveryFee;
        this.totalDisplayTarget.textContent = total.toLocaleString('fr-FR') + ' XOF';
    }
}
