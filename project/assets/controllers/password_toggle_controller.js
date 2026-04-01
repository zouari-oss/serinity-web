import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['input', 'icon'];

  toggle(event) {
    const btn = event.currentTarget;
    const input = btn.parentElement.querySelector('input');
    if (!input) return;

    const hidden = input.type === 'password';
    input.type = hidden ? 'text' : 'password';
    
    // Update icon
    const icon = btn.querySelector('[data-password-toggle-target="icon"]');
    if (icon) {
      icon.textContent = hidden ? 'visibility_off' : 'visibility';
      icon.classList.add('ac-pulse');
      setTimeout(() => icon.classList.remove('ac-pulse'), 300);
    }
  }
}
