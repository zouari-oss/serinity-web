import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['input', 'table'];

  filter() {
    const term = this.inputTarget.value.toLowerCase();
    this.tableTarget.querySelectorAll('tbody tr').forEach((row) => {
      row.hidden = !row.innerText.toLowerCase().includes(term);
    });
  }
}
