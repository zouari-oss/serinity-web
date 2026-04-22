import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['preview', 'loader', 'error', 'button'];
  static values = {
    endpoint: String,
    statusEndpoint: String,
    auto: Boolean,
  };

  connect() {
    this.isGenerating = false;

    if (this.autoValue) {
      this.generate();
    }
  }

  async generate(event) {
    if (event) event.preventDefault();
    if (this.isGenerating || !this.hasPreviewTarget) return;

    if (this.previewTarget.getAttribute('src')?.trim() === '') {
      this.showError('Please upload a profile image first.');
      return;
    }

    this.isGenerating = true;
    this.toggleLoading(true);
    this.showError('');

    try {
      const firstResult = await this.requestGenerate();
      if (firstResult.error) {
        this.showError(firstResult.error);
        return;
      }

      if (firstResult.image) {
        this.swapPreview(firstResult.image);
        return;
      }

      const polledImage = await this.pollAvatar();
      if (!polledImage) {
        this.showError('Avatar generation is taking longer than expected. Please retry in a few seconds.');
        return;
      }

      this.swapPreview(polledImage);
    } catch {
      this.showError('Network error while generating avatar.');
    } finally {
      this.toggleLoading(false);
      this.isGenerating = false;
    }
  }

  toggleLoading(isLoading) {
    if (this.hasLoaderTarget) {
      this.loaderTarget.hidden = !isLoading;
    }

    if (this.hasButtonTarget) {
      this.buttonTarget.disabled = isLoading;
    }
  }

  showError(message) {
    if (!this.hasErrorTarget) return;

    this.errorTarget.hidden = message === '';
    this.errorTarget.textContent = message;
  }

  async requestGenerate() {
    const response = await fetch(this.endpointValue, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    });

    const payload = await response.json().catch(() => ({}));
    if (response.status === 202 || payload.status === 'processing') {
      return { processing: true };
    }

    if (!response.ok) {
      return { error: payload.message || 'Unable to generate avatar.' };
    }

    if (typeof payload.image !== 'string' || payload.image.trim() === '') {
      return { error: payload.message || 'Invalid avatar response.' };
    }

    return { image: payload.image };
  }

  async requestStatus() {
    const endpoint = this.hasStatusEndpointValue ? this.statusEndpointValue : this.endpointValue;
    const response = await fetch(endpoint, {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    });

    const payload = await response.json().catch(() => ({}));
    if (response.status === 202 || payload.status === 'processing') {
      return { processing: true };
    }

    if (!response.ok) {
      return { error: payload.message || 'Avatar is not ready.' };
    }

    if (typeof payload.image !== 'string' || payload.image.trim() === '') {
      return { error: payload.message || 'Invalid avatar response.' };
    }

    return { image: payload.image };
  }

  async pollAvatar() {
    const maxAttempts = 90;
    const intervalMs = 2000;

    for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
      await this.wait(intervalMs);
      const result = await this.requestStatus();
      if (result.error) {
        return null;
      }
      if (result.image) {
        return result.image;
      }
    }

    return null;
  }

  swapPreview(image) {
    const imageData = image.startsWith('data:image/')
      ? image
      : `data:image/png;base64,${image}`;

    this.previewTarget.style.opacity = '0';
    window.setTimeout(() => {
      this.previewTarget.src = imageData;
      this.previewTarget.style.opacity = '1';
    }, 200);
  }

  wait(ms) {
    return new Promise((resolve) => {
      window.setTimeout(resolve, ms);
    });
  }
}
