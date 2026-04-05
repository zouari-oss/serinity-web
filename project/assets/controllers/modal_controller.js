import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['container'];

  open(event) {
    const id = event.params.id;
    const modal = document.getElementById(id);
    if (modal) modal.hidden = false;
  }

  close(event) {
    const modal = event.currentTarget.closest('.ac-modal') || this.containerTarget;
    if (modal) modal.hidden = true;
  }
}
