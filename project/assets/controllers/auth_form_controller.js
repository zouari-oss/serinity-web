import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['error'];

  connect() {
    const params = new URLSearchParams(window.location.search);
    const oauthError = (params.get('oauth_error') || '').trim();
    if (!oauthError) {
      return;
    }

    const messageByCode = {
      google_auth_failed: 'Unable to authenticate with Google.',
      google_auth_state_mismatch: 'Google login session expired or host mismatch. Please retry from localhost.',
      google_email_not_verified: 'Your Google account email must be verified before signing in.',
      google_account_link_conflict: 'This email is already linked to another Google account.',
      google_auth_invalid_payload: 'Invalid Google authentication data. Please try again.',
    };
    this.showError(messageByCode[oauthError] || 'Unable to authenticate with Google.');
  }

  async submit(event) {
    event.preventDefault();

    const form = event.currentTarget;
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const btnContent = submitBtn?.querySelector('.ac-btn-content');
    const btnText = btnContent?.querySelector('.ac-btn-text');
    const btnSpinner = btnContent?.querySelector('.ac-btn-spinner');

    try {
      // Show loading state
      if (submitBtn && btnText && btnSpinner) {
        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');
        btnText.hidden = true;
        btnSpinner.hidden = false;
      }

      const body = new URLSearchParams(new FormData(form));
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
        credentials: 'include',
      });

      const payload = await response.json().catch(() => ({ success: false, message: 'Invalid server response.' }));

      if (!response.ok || !payload.success) {
        this.showError(payload.message || 'Authentication failed.');
        // Reset button state on error
        if (submitBtn && btnText && btnSpinner) {
          submitBtn.disabled = false;
          submitBtn.classList.remove('is-loading');
          btnText.hidden = false;
          btnSpinner.hidden = true;
        }
        return;
      }

      // Store token
      const token = payload?.data?.token || '';
      localStorage.setItem('access_token', token);
      if (token) {
        document.cookie = `access_token=${encodeURIComponent(token)}; Path=/; SameSite=Lax`;
      }
      
      // Show success state
      if (submitBtn && btnSpinner) {
        submitBtn.classList.remove('is-loading');
        submitBtn.classList.add('is-success');
        btnSpinner.innerHTML = '<span class="material-symbols-outlined">check_circle</span>';
      }
      
      const role = String(payload?.data?.user?.role || '').toUpperCase();
      const redirectPath = role === 'ADMIN' ? '/admin/dashboard' : '/user/dashboard';

      // Redirect after animation
      setTimeout(() => {
        window.location.href = redirectPath;
      }, 800);
      
    } catch (e) {
      this.showError('Network error. Please try again.');
      // Reset button state on error
      if (submitBtn && btnText && btnSpinner) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('is-loading');
        btnText.hidden = false;
        btnSpinner.hidden = true;
      }
    }
  }

  showError(message) {
    if (!this.hasErrorTarget) return;
    this.errorTarget.textContent = message;
    this.errorTarget.hidden = false;
    this.errorTarget.classList.remove('ac-slide-in-down');
    
    // Trigger reflow for animation restart
    void this.errorTarget.offsetWidth;
    this.errorTarget.classList.add('ac-slide-in-down');
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
      this.hideError();
    }, 5000);
  }

  hideError() {
    if (!this.hasErrorTarget) return;
    this.errorTarget.classList.add('ac-fade-out');
    setTimeout(() => {
      this.errorTarget.hidden = true;
      this.errorTarget.classList.remove('ac-fade-out', 'ac-slide-in-down');
    }, 300);
  }
}
