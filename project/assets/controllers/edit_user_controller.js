import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'form', 'userId', 'email', 'role', 'status'];
    static values = {
        apiBase: { type: String, default: '/api/admin/users' }
    }

    async openEditModal(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const userId = button.dataset.userId;
        const row = button.closest('tr');

        // Populate form with current data
        const email = row.querySelector('td:nth-child(2)').textContent.trim();
        const role = row.querySelector('td:nth-child(3) .ac-badge').textContent.trim();
        const status = row.querySelector('td:nth-child(4) .ac-badge').textContent.trim();

        // Set form values
        this.userIdTarget.value = userId;
        this.emailTarget.value = email;
        this.roleTarget.value = role;
        this.statusTarget.value = status;

        // Store row reference for update
        this.currentRow = row;

        // Show modal (assuming modal controller is on parent element)
        const modal = document.getElementById('edit-user-modal');
        if (modal) {
            modal.hidden = false;
            modal.classList.remove('ac-modal-exit');
            modal.classList.add('ac-modal-enter');
            this.emailTarget.focus();
        }
    }

    async submitEdit(event) {
        event.preventDefault();
        const formData = new FormData(this.formTarget);
        const userId = this.userIdTarget.value;
        const submitButton = event.submitter;

        const data = {
            email: formData.get('email'),
            role: formData.get('role'),
            accountStatus: formData.get('accountStatus')
        };

        // Include password if provided
        const password = formData.get('password');
        if (password && password.trim()) {
            data.password = password;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';

        try {
            const response = await fetch(`${this.apiBaseValue}/${userId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error?.error?.message || error?.message || 'Failed to update user');
            }

            // Update table row
            if (this.currentRow) {
                this.currentRow.querySelector('td:nth-child(2)').textContent = data.email;
                this.currentRow.querySelector('td:nth-child(3) .ac-badge').textContent = data.role;
                this.currentRow.querySelector('td:nth-child(3) .ac-badge').className = `ac-badge ac-badge-${data.role.toLowerCase()}`;
                this.currentRow.querySelector('td:nth-child(4) .ac-badge').textContent = data.accountStatus;
                this.currentRow.querySelector('td:nth-child(4) .ac-badge').className = `ac-badge ac-badge-${data.accountStatus === 'ACTIVE' ? 'success' : 'danger'}`;
            }

            this.closeModal();
            this.showToast('User updated successfully', 'success');
            this.formTarget.reset();
        } catch (error) {
            this.showToast(error.message, 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Save changes';
        }
    }

    closeModal() {
        const modal = document.getElementById('edit-user-modal');
        if (modal) {
            modal.classList.remove('ac-modal-enter');
            modal.classList.add('ac-modal-exit');
            setTimeout(() => {
                modal.hidden = true;
                modal.classList.remove('ac-modal-exit');
            }, 220);
        }
        this.currentRow = null;
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
