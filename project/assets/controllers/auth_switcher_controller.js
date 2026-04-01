import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['signup', 'signin', 'signupTab', 'signinTab'];

  connect() {
    // Initialize based on server-side state
    if (this.hasSigninTarget && !this.signinTarget.hidden && this.signinTarget.style.display !== 'none') {
      this.showSignin(null, false);
    } else if (this.hasSignupTarget && !this.signupTarget.hidden && this.signupTarget.style.display !== 'none') {
      this.showSignup(null, false);
    }
  }

  showSignin(event = null, animate = true) {
    if (event) event.preventDefault();
    
    if (!this.hasSigninTarget || !this.hasSignupTarget) return;

    // Fade out current panel
    if (animate && this.signupTarget.style.display !== 'none') {
      this.signupTarget.classList.add('ac-fade-out');
      
      setTimeout(() => {
        this.signupTarget.style.display = 'none';
        this.signupTarget.classList.remove('ac-fade-out', 'is-active');
        
        // Fade in signin panel
        this.signinTarget.style.display = 'block';
        this.signinTarget.classList.add('is-active', 'ac-fade-in');
        
        // Focus first input
        const firstInput = this.signinTarget.querySelector('input');
        if (firstInput) firstInput.focus();
        
        // Clean up animation class
        setTimeout(() => {
          this.signinTarget.classList.remove('ac-fade-in');
        }, 300);
      }, 300);
    } else {
      this.signupTarget.style.display = 'none';
      this.signupTarget.classList.remove('is-active');
      this.signinTarget.style.display = 'block';
      this.signinTarget.classList.add('is-active');
    }

    // Update tabs
    if (this.hasSigninTabTarget && this.hasSignupTabTarget) {
      this.signinTabTarget.classList.add('is-active');
      this.signinTabTarget.setAttribute('aria-selected', 'true');
      this.signupTabTarget.classList.remove('is-active');
      this.signupTabTarget.setAttribute('aria-selected', 'false');
    }
  }

  showSignup(event = null, animate = true) {
    if (event) event.preventDefault();
    
    if (!this.hasSigninTarget || !this.hasSignupTarget) return;

    // Fade out current panel
    if (animate && this.signinTarget.style.display !== 'none') {
      this.signinTarget.classList.add('ac-fade-out');
      
      setTimeout(() => {
        this.signinTarget.style.display = 'none';
        this.signinTarget.classList.remove('ac-fade-out', 'is-active');
        
        // Fade in signup panel
        this.signupTarget.style.display = 'block';
        this.signupTarget.classList.add('is-active', 'ac-fade-in');
        
        // Focus first input
        const firstInput = this.signupTarget.querySelector('input');
        if (firstInput) firstInput.focus();
        
        // Clean up animation class
        setTimeout(() => {
          this.signupTarget.classList.remove('ac-fade-in');
        }, 300);
      }, 300);
    } else {
      this.signinTarget.style.display = 'none';
      this.signinTarget.classList.remove('is-active');
      this.signupTarget.style.display = 'block';
      this.signupTarget.classList.add('is-active');
    }

    // Update tabs
    if (this.hasSigninTabTarget && this.hasSignupTabTarget) {
      this.signupTabTarget.classList.add('is-active');
      this.signupTabTarget.setAttribute('aria-selected', 'true');
      this.signinTabTarget.classList.remove('is-active');
      this.signinTabTarget.setAttribute('aria-selected', 'false');
    }
  }
}
