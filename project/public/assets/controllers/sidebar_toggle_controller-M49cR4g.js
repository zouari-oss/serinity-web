import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    toggle() {
        const layout = this.element.closest('.ac-app-layout');
        if (!layout) {
            return;
        }

        layout.classList.toggle('ac-sidebar-collapsed');
    }
}
