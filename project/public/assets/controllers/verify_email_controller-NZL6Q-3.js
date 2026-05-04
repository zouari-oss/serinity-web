import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['verifyForm', 'otpInput', 'status', 'countdown', 'resendButton', 'verifyHint'];

  static values = {
    verifyUrl: String,
    resendUrl: String,
    loginUrl: String,
    expiresIn: Number,
  };

  connect() {
    this.remainingSeconds = 0;
    this.interval = null;

    const email = this.savedEmail();
    if (email === '') {
      this.redirectToLogin();
      return;
    }

    if (this.hasVerifyHintTarget) {
      this.verifyHintTarget.textContent = `Enter the 6-digit code sent to ${email}.`;
    }

    this.startCountdownFromStorage();
    if (this.hasOtpInputTarget) {
      this.otpInputTargets[0].focus();
    }
  }

  disconnect() {
    this.stopCountdown();
  }

  async verifyCodeAction(event) {
    event.preventDefault();

    const email = this.savedEmail();
    const code = this.collectOtpCode();
    if (email === '' || code.length !== 6) {
      this.showStatus('Please enter the full 6-digit code.', false);
      return;
    }

    this.setBusy(this.verifyFormTarget, true);
    const { ok, payload } = await this.postJson(this.verifyUrlValue, { email, code });
    this.setBusy(this.verifyFormTarget, false);
    this.showStatus(payload.message || 'Unable to verify code.', ok);

    if (!ok) return;

    const token = payload?.data?.token || '';
    localStorage.setItem('access_token', token);
    if (token) {
      document.cookie = `access_token=${encodeURIComponent(token)}; Path=/; SameSite=Lax`;
    }

    this.clearVerificationStorage();
    setTimeout(() => {
      window.location.href = String(payload?.data?.redirect || '/dashboard');
    }, 700);
  }

  async resendCodeAction(event) {
    event.preventDefault();

    const email = this.savedEmail();
    if (email === '') {
      this.redirectToLogin();
      return;
    }

    if (this.remainingSeconds > 0) {
      this.showStatus('Please wait for the timer before resending.', false);
      return;
    }

    const { ok, payload } = await this.postJson(this.resendUrlValue, { email, resend: true });
    if (!ok) {
      this.showStatus(payload.message || 'Unable to resend verification code.', false);
      const error = payload?.data?.error || '';
      if (error === 'resend_limit_reached') {
        this.clearVerificationStorage();
        setTimeout(() => this.redirectToLogin(), 700);
      }
      return;
    }

    this.showStatus('Verification code resent successfully.', true);
    sessionStorage.setItem('verificationCodeExpiresAt', String(Date.now() + (this.expiresInValue || 900) * 1000));
    this.startCountdownFromStorage();
  }

  onOtpInput(event) {
    const input = event.currentTarget;
    input.value = input.value.replace(/\D/g, '').slice(0, 1);
    if (input.value === '') return;

    const index = this.otpInputTargets.indexOf(input);
    const next = this.otpInputTargets[index + 1];
    if (next) next.focus();
  }

  onOtpKeydown(event) {
    const input = event.currentTarget;
    if (event.key !== 'Backspace' || input.value !== '') return;

    const index = this.otpInputTargets.indexOf(input);
    const prev = this.otpInputTargets[index - 1];
    if (prev) {
      prev.focus();
      prev.value = '';
    }
  }

  onOtpPaste(event) {
    event.preventDefault();
    const digits = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
    if (digits === '') return;

    this.otpInputTargets.forEach((input, index) => {
      input.value = digits[index] || '';
    });

    const lastFilled = Math.max(0, Math.min(digits.length - 1, this.otpInputTargets.length - 1));
    this.otpInputTargets[lastFilled].focus();
  }

  collectOtpCode() {
    if (!this.hasOtpInputTarget) return '';
    return this.otpInputTargets.map((input) => input.value.trim()).join('');
  }

  startCountdownFromStorage() {
    const expiresAt = Number(sessionStorage.getItem('verificationCodeExpiresAt') || 0);
    const remaining = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
    this.startCountdown(remaining);
  }

  startCountdown(seconds) {
    this.stopCountdown();
    this.remainingSeconds = Number(seconds) || 0;
    this.updateCountdownLabel();

    this.interval = setInterval(() => {
      this.remainingSeconds -= 1;
      this.updateCountdownLabel();
      if (this.remainingSeconds <= 0) {
        this.stopCountdown();
      }
    }, 1000);
  }

  stopCountdown() {
    if (this.interval !== null) {
      clearInterval(this.interval);
      this.interval = null;
    }
    if (this.remainingSeconds < 0) this.remainingSeconds = 0;
    this.updateCountdownLabel();
  }

  updateCountdownLabel() {
    if (!this.hasCountdownTarget || !this.hasResendButtonTarget) return;

    if (this.remainingSeconds <= 0) {
      this.countdownTarget.textContent = 'Code expired. You can request a new one.';
      this.resendButtonTarget.disabled = false;
      return;
    }

    const minutes = Math.floor(this.remainingSeconds / 60);
    const seconds = this.remainingSeconds % 60;
    this.countdownTarget.textContent = `Resend available in ${minutes}:${String(seconds).padStart(2, '0')}`;
    this.resendButtonTarget.disabled = true;
  }

  showStatus(message, success) {
    if (!this.hasStatusTarget) return;

    this.statusTarget.hidden = false;
    this.statusTarget.textContent = message;
    this.statusTarget.setAttribute('role', success ? 'status' : 'alert');
    this.statusTarget.setAttribute('aria-live', success ? 'polite' : 'assertive');
    this.statusTarget.classList.toggle('ac-inline-error', !success);
    this.statusTarget.classList.toggle('ac-inline-success', success);
  }

  setBusy(form, busy) {
    const button = form?.querySelector('button[type="submit"]');
    if (!button) return;
    button.disabled = busy;
    button.classList.toggle('is-loading', busy);
  }

  savedEmail() {
    return (sessionStorage.getItem('verificationEmail') || '').trim();
  }

  clearVerificationStorage() {
    sessionStorage.removeItem('verificationEmail');
    sessionStorage.removeItem('verificationCodeExpiresAt');
  }

  redirectToLogin() {
    window.location.href = this.loginUrlValue || '/login';
  }

  async postJson(url, payload) {
    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'include',
      });
      const body = await response.json().catch(() => ({}));
      return { ok: response.ok && body.success !== false, payload: body };
    } catch {
      return { ok: false, payload: { message: 'Network error. Please try again.' } };
    }
  }
}
