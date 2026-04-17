import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'form', 'title', 'content', 'token'];

    open(event) {
        event.preventDefault();
        const trigger = event.currentTarget;

        this.formTarget.action = trigger.dataset.editUrl || this.formTarget.action;
        this.titleTarget.value = trigger.dataset.entryTitle || '';
        this.setEditorContent(trigger.dataset.entryContent || '');
        this.tokenTarget.value = trigger.dataset.csrf || '';
        this.modalTarget.hidden = false;
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }
        this.modalTarget.hidden = true;
    }

    closeOnBackdrop(event) {
        if (event.target === this.modalTarget) {
            this.close();
        }
    }

    setEditorContent(content) {
        if (!this.hasContentTarget) {
            return;
        }

        if ('value' in this.contentTarget) {
            this.contentTarget.value = content;
        } else {
            this.contentTarget.textContent = content;
        }
    }
}
