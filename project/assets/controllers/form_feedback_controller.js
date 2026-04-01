import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  validate(event) {
    const form = event.currentTarget;
    if (!form.checkValidity()) {
      event.preventDefault();
      form.reportValidity();
    }
  }
}
