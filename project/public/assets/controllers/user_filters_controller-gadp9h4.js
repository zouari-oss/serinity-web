import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.timeout = null;
    }

    disconnect() {
        if (this.timeout !== null) {
            clearTimeout(this.timeout);
            this.timeout = null;
        }
    }

    onInput() {
        if (this.timeout !== null) {
            clearTimeout(this.timeout);
        }

        this.timeout = window.setTimeout(() => {
            this.element.requestSubmit();
        }, 300);
    }

    onChange() {
        this.element.requestSubmit();
    }
}
