import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['error', 'otpStep', 'otpCode'];

  connect() {
    this.pendingChallengeId = null;
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
    const submitBtn = form.querySelector('button[type="submit"]');
    const btnContent = submitBtn?.querySelector('.ac-btn-content');
    const btnText = btnContent?.querySelector('.ac-btn-text');
    const btnSpinner = btnContent?.querySelector('.ac-btn-spinner');
    const isLoginForm = /\/api\/auth\/(login|signin)$/.test(form.action);
    if (!isLoginForm) {
      this.pendingChallengeId = null;
    }
    const is2faCheck = isLoginForm && this.pendingChallengeId !== null && this.pendingChallengeId !== '';

    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    try {
      this.setLoadingState(submitBtn, btnText, btnSpinner);

      let response;
      if (is2faCheck) {
        response = await fetch('/api/auth/2fa/check', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            challengeId: this.pendingChallengeId,
            code: this.hasOtpCodeTarget ? this.otpCodeTarget.value.trim() : '',
          }),
          credentials: 'include',
        });
      } else {
        const body = new URLSearchParams(new FormData(form));
        response = await fetch(form.action, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body,
          credentials: 'include',
        });
      }
      const payload = await response.json().catch(() => ({ success: false, message: 'Invalid server response.' }));

      if (!is2faCheck && payload?.data?.requires_2fa === true && payload?.data?.challengeId) {
        this.pendingChallengeId = String(payload.data.challengeId);
        this.enableOtpStep(submitBtn, btnText, btnSpinner);
        this.showError('Two-factor authentication required. Enter your authenticator code.');
        return;
      }

      if (!response.ok || !payload.success) {
        this.showError(payload.message || 'Authentication failed.');
        this.resetButtonState(submitBtn, btnText, btnSpinner);
        return;
      }

      if (!isLoginForm && payload?.data?.requiresEmailVerification === true) {
        const emailInput = form.querySelector('input[name="email"]');
        const signupEmail = (emailInput?.value || '').trim().toLowerCase();
        const expiresIn = Number(payload?.data?.expiresIn || 900);
        if (signupEmail) {
          sessionStorage.setItem('verificationEmail', signupEmail);
          sessionStorage.setItem('verificationCodeExpiresAt', String(Date.now() + expiresIn * 1000));
        }

        setTimeout(() => {
          window.location.href = String(payload?.data?.redirect || '/verify-email');
        }, 400);
        return;
      }

      const token = payload?.data?.token || '';
      localStorage.setItem('access_token', token);
      if (token) {
        document.cookie = `access_token=${encodeURIComponent(token)}; Path=/; SameSite=Lax`;
      }

      if (submitBtn && btnSpinner) {
        submitBtn.classList.remove('is-loading');
        submitBtn.classList.add('is-success');
        btnSpinner.innerHTML = '<span class="material-symbols-outlined">check_circle</span>';
      }

      const role = String(payload?.data?.user?.role || '').toUpperCase();
      const redirectPath = role === 'ADMIN' ? '/admin/dashboard' : '/user/dashboard';

      setTimeout(() => {
        window.location.href = redirectPath;
      }, 800);
    } catch (_error) {
      this.showError('Network error. Please try again.');
      this.resetButtonState(submitBtn, btnText, btnSpinner);
    }
  }

  setLoadingState(submitBtn, btnText, btnSpinner) {
    if (!submitBtn || !btnText || !btnSpinner) {
      return;
    }
    submitBtn.disabled = true;
    submitBtn.classList.add('is-loading');
    submitBtn.classList.remove('is-success');
    btnText.hidden = true;
    btnSpinner.hidden = false;
    btnSpinner.innerHTML = '<span class="material-symbols-outlined ac-spin">progress_activity</span>';
  }

  resetButtonState(submitBtn, btnText, btnSpinner) {
    if (!submitBtn || !btnText || !btnSpinner) {
      return;
    }
    submitBtn.disabled = false;
    submitBtn.classList.remove('is-loading');
    btnText.hidden = false;
    btnSpinner.hidden = true;
    btnSpinner.innerHTML = '<span class="material-symbols-outlined ac-spin">progress_activity</span>';
  }

  enableOtpStep(submitBtn, btnText, btnSpinner) {
    this.resetButtonState(submitBtn, btnText, btnSpinner);
    if (this.hasOtpStepTarget) {
      this.otpStepTarget.hidden = false;
    }
    if (this.hasOtpCodeTarget) {
      this.otpCodeTarget.required = true;
      this.otpCodeTarget.focus();
    }
    if (btnText) {
      btnText.textContent = 'Verify code';
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
