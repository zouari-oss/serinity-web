import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu'];

    connect() {
        this.onWindowClick = (event) => {
            if (!this.element.contains(event.target)) {
                this.closeMenu();
            }
        };
        window.addEventListener('click', this.onWindowClick);
    }

    disconnect() {
        window.removeEventListener('click', this.onWindowClick);
    }

    toggleMenu(event) {
        event.preventDefault();
        event.stopPropagation();
        if (!this.hasMenuTarget) {
            return;
        }
        this.menuTarget.hidden = !this.menuTarget.hidden;
    }

    closeMenu() {
        if (this.hasMenuTarget) {
            this.menuTarget.hidden = true;
        }
    }

    async logout(event) {
        event.preventDefault();
        this.closeMenu();

        const accessToken = this.cookie('access_token');

        try {
            await fetch('/api/auth/logout', {
                method: 'POST',
                headers: accessToken ? { Authorization: `Bearer ${accessToken}` } : {}
            });
        } finally {
            this.clearCookie('access_token');
            this.clearCookie('refresh_token');
            this.clearCookie('jwt');
            window.location.href = '/';
        }
    }

    cookie(name) {
        const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const match = document.cookie.match(new RegExp(`(?:^|; )${escapedName}=([^;]*)`));
        return match ? decodeURIComponent(match[1]) : '';
    }

    clearCookie(name) {
        document.cookie = `${name}=; Max-Age=0; path=/`;
        document.cookie = `${name}=; Max-Age=0; path=/; SameSite=Lax`;
        document.cookie = `${name}=; Max-Age=0; path=/; Secure; SameSite=None`;
    }
}
