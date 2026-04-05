import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['message'];

    connect() {
        this.messageTargets.forEach((item, index) => {
            const text = item.textContent.trim();
            if (!text) return;
            const type = item.dataset.type || 'success';
            this.showToast(text, type, index);
        });
    }

    showToast(message, type = 'success', index = 0) {
        const toast = document.createElement('div');
        toast.className = `ac-toast ac-toast-${type}`;
        toast.textContent = message;
        toast.style.bottom = `${2 + index * 4}rem`;

        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 300ms ease';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }
}
