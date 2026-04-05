import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect() {
    const current = localStorage.getItem('ac-theme');
    if (current === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    }
  }

  toggle() {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (dark) {
      document.documentElement.removeAttribute('data-theme');
      localStorage.setItem('ac-theme', 'light');
      return;
    }

    document.documentElement.setAttribute('data-theme', 'dark');
    localStorage.setItem('ac-theme', 'dark');
  }
}
