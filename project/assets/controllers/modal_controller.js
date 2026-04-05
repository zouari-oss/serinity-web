import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['container'];

  open(event) {
    const id = event.params.id;
    const modal = document.getElementById(id);
    if (!modal) return;

    modal.hidden = false;
    modal.classList.remove('ac-modal-exit');
    modal.classList.add('ac-modal-enter');
  }

  close(event) {
    const modal = event.currentTarget.closest('.ac-modal') || this.containerTarget;
    if (!modal) return;

    modal.classList.remove('ac-modal-enter');
    modal.classList.add('ac-modal-exit');
    window.setTimeout(() => {
      modal.hidden = true;
      modal.classList.remove('ac-modal-exit');
    }, 220);
  }
}
