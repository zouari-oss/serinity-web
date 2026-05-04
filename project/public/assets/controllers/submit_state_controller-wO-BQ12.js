import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['button'];

  start(event) {
    const form = event.currentTarget;
    if (!form.checkValidity()) return;

    const button = form.querySelector('[data-submit-state-target="button"]');
    if (!button) return;

    button.classList.add('is-loading');
    button.disabled = true;
    
    const textSpan = button.querySelector('.ac-btn-text');
    const loaderSpan = button.querySelector('.ac-btn-loader');
    
    if (textSpan) textSpan.hidden = true;
    if (loaderSpan) loaderSpan.hidden = false;
  }

  reset() {
    if (!this.hasButtonTarget) return;
    
    const button = this.buttonTarget;
    button.classList.remove('is-loading');
    button.disabled = false;
    
    const textSpan = button.querySelector('.ac-btn-text');
    const loaderSpan = button.querySelector('.ac-btn-loader');
    
    if (textSpan) textSpan.hidden = false;
    if (loaderSpan) loaderSpan.hidden = true;
  }
}
