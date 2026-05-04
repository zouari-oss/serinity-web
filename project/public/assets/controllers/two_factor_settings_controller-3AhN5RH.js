import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['state', 'status', 'setup', 'qrContent', 'qrImage', 'code'];
  static values = {
    enabled: Boolean,
  };

  connect() {
    this.renderState();
  }

  async enable() {
    const response = await this.request('/api/auth/2fa/enable');
    if (!response.success) {
      this.showStatus(response.message || 'Unable to initialize two-factor setup.', 'error');
      return;
    }

    if (this.hasSetupTarget) {
      this.setupTarget.hidden = false;
    }
    if (this.hasQrContentTarget) {
      this.qrContentTarget.value = String(response?.data?.qrContent || '');
    }
    if (this.hasQrImageTarget) {
      const src = String(response?.data?.qrCode || '');
      this.qrImageTarget.src = src;
      this.qrImageTarget.hidden = src === '';
    }
    this.enabledValue = false;
    this.renderState();
    this.showStatus('Setup created. Add the account in your authenticator app, then verify with a code.', 'success');
  }

  async verify() {
    const code = this.hasCodeTarget ? this.codeTarget.value.trim() : '';
    if (!code) {
      this.showStatus('Enter the authentication code to verify setup.', 'error');
      return;
    }

    const response = await this.request('/api/auth/2fa/verify', { code });
    if (!response.success) {
      this.showStatus(response.message || 'Invalid authentication code.', 'error');
      return;
    }

    this.enabledValue = true;
    this.renderState();
    if (this.hasSetupTarget) {
      this.setupTarget.hidden = true;
    }
    this.showStatus(response.message || 'Two-factor authentication enabled.', 'success');
  }

  async disable() {
    const response = await this.request('/api/auth/2fa/disable');
    if (!response.success) {
      this.showStatus(response.message || 'Unable to disable two-factor authentication.', 'error');
      return;
    }

    this.enabledValue = false;
    this.renderState();
    if (this.hasSetupTarget) {
      this.setupTarget.hidden = true;
    }
    if (this.hasCodeTarget) {
      this.codeTarget.value = '';
    }
    if (this.hasQrContentTarget) {
      this.qrContentTarget.value = '';
    }
    if (this.hasQrImageTarget) {
      this.qrImageTarget.src = '';
      this.qrImageTarget.hidden = true;
    }
    this.showStatus(response.message || 'Two-factor authentication disabled.', 'success');
  }

  renderState() {
    if (!this.hasStateTarget) {
      return;
    }
    const enabled = this.enabledValue === true;
    this.stateTarget.textContent = enabled ? 'Enabled' : 'Disabled';
    this.stateTarget.style.color = enabled ? 'var(--success)' : 'var(--muted)';
  }

  async request(url, body = null) {
    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : '{}',
        credentials: 'include',
      });
      return await response.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
    } catch (_error) {
      return { success: false, message: 'Network error. Please try again.' };
    }
  }

  showStatus(message, type) {
    if (!this.hasStatusTarget) {
      return;
    }
    this.statusTarget.hidden = false;
    this.statusTarget.textContent = message;
    this.statusTarget.style.color = type === 'error' ? 'var(--danger)' : 'var(--success)';
  }
}
