import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['links'];

    toggle(event) {
        event.preventDefault();
        if (!this.hasLinksTarget) {
            return;
        }
        this.linksTarget.classList.toggle('is-open');
    }
}
