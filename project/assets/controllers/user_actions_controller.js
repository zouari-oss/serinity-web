import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        userId: String,
        apiBase: { type: String, default: '/api/admin/users' }
    }

    async toggleStatus(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const userId = button.dataset.userId;
        const row = button.closest('tr');
        const statusBadge = row.querySelector('td:nth-child(4) .ac-badge');
        const currentStatus = statusBadge.textContent.trim();
        const newStatus = currentStatus === 'ACTIVE' ? 'DISABLED' : 'ACTIVE';
        this.pendingAction = async () => {
            button.disabled = true;
            try {
                const response = await fetch(`${this.apiBaseValue}/${userId}/status`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ accountStatus: newStatus })
                });

                const payload = await response.json().catch(() => null);
                if (!response.ok) {
                    throw new Error(payload?.error?.message || payload?.message || 'Failed to update status');
                }

                statusBadge.textContent = newStatus;
                statusBadge.className = `ac-badge ac-badge-${newStatus === 'ACTIVE' ? 'success' : 'danger'}`;
                this.showToast('Status updated successfully', 'success');
            } catch (error) {
                this.showToast(error.message, 'error');
            } finally {
                button.disabled = false;
            }
        };

        this.openConfirmationModal(
            'Change user status',
            `Change ${button.dataset.userEmail || 'this user'} status to ${newStatus}?`,
            `Yes, set to ${newStatus}`
        );
    }

    async deleteUser(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const userId = button.dataset.userId;
        const row = button.closest('tr');
        const email = button.dataset.userEmail || row.querySelector('td:nth-child(2)').textContent.trim();

        this.pendingAction = async () => {
            button.disabled = true;
            try {
                const response = await fetch(`${this.apiBaseValue}/${userId}`, {
                    method: 'DELETE'
                });

                const payload = await response.json().catch(() => null);
                if (!response.ok) {
                    throw new Error(payload?.error?.message || payload?.message || 'Failed to delete user');
                }

                row.style.transition = 'opacity 300ms ease';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);

                this.showToast('User deleted successfully', 'success');
            } catch (error) {
                this.showToast(error.message, 'error');
                button.disabled = false;
            }
        };

        this.openConfirmationModal(
            'Delete user',
            `Are you sure you want to delete "${email}"? This action cannot be undone.`,
            'Yes, delete user'
        );
    }

    openPreview(event) {
        event.preventDefault();
        const button = event.currentTarget;

        document.getElementById('preview-user-username').value = button.dataset.userUsername || '';
        document.getElementById('preview-user-email').value = button.dataset.userEmail || '';
        document.getElementById('preview-user-role').value = button.dataset.userRole || '';
        document.getElementById('preview-user-status').value = button.dataset.userAccountStatus || '';
        document.getElementById('preview-user-presence').value = button.dataset.userPresenceStatus || '';
        document.getElementById('preview-user-first-name').value = button.dataset.userFirstName || '';
        document.getElementById('preview-user-last-name').value = button.dataset.userLastName || '';
        document.getElementById('preview-user-country').value = button.dataset.userCountry || '';
        document.getElementById('preview-user-state').value = button.dataset.userState || '';
        document.getElementById('preview-user-about').value = button.dataset.userAboutMe || '';

        const image = document.getElementById('preview-user-image');
        const imageEmpty = document.getElementById('preview-user-image-empty');
        const profileImageUrl = button.dataset.userProfileImageUrl || '';
        if (profileImageUrl) {
            image.src = profileImageUrl;
            image.hidden = false;
            imageEmpty.hidden = true;
        } else {
            image.src = '';
            image.hidden = true;
            imageEmpty.hidden = false;
        }

        this.openModalById('preview-user-modal');
    }

    async createUser(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const submitButton = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);

        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(formData).toString()
            });

            const payload = await response.json().catch(() => null);
            if (!response.ok) {
                throw new Error(payload?.error?.message || payload?.message || 'Failed to create user');
            }

            this.showToast('User created successfully', 'success');

            const modal = document.getElementById('create-user-modal');
            if (modal) {
                modal.classList.remove('ac-modal-enter');
                modal.classList.add('ac-modal-exit');
                window.setTimeout(() => {
                    modal.hidden = true;
                    modal.classList.remove('ac-modal-exit');
                    window.location.reload();
                }, 220);
            } else {
                window.location.reload();
            }
        } catch (error) {
            this.showToast(error.message, 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Save';
        }
    }

    async confirmPendingAction() {
        if (!this.pendingAction) {
            return;
        }

        const action = this.pendingAction;
        this.pendingAction = null;
        this.closeModalById('confirm-user-action-modal');
        await action();
    }

    cancelPendingAction() {
        this.pendingAction = null;
    }

    openConfirmationModal(title, message, confirmText = 'Confirm') {
        const titleElement = document.getElementById('confirm-user-action-title');
        const messageElement = document.getElementById('confirm-user-action-message');
        const buttonElement = document.getElementById('confirm-user-action-button');
        if (titleElement) titleElement.textContent = title;
        if (messageElement) messageElement.textContent = message;
        if (buttonElement) buttonElement.textContent = confirmText;
        this.openModalById('confirm-user-action-modal');
    }

    openModalById(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.hidden = false;
        modal.classList.remove('ac-modal-exit');
        modal.classList.add('ac-modal-enter');
    }

    closeModalById(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('ac-modal-enter');
        modal.classList.add('ac-modal-exit');
        window.setTimeout(() => {
            modal.hidden = true;
            modal.classList.remove('ac-modal-exit');
        }, 220);
    }

    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `ac-toast ac-toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: var(--shadow-lg);
            animation: slideInRight 300ms ease;
            z-index: 9999;
        `;
        
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 300ms ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}
