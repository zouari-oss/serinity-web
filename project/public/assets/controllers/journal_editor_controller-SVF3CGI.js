import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'modal',
        'form',
        'title',
        'content',
        'token',
        'dayModal',
        'dayTitle',
        'dayEntries',
        'deleteModal',
        'deleteForm',
        'deleteToken',
        'dayData',
    ];

    open(event) {
        event.preventDefault();
        const trigger = event.currentTarget;

        this.formTarget.action = trigger.dataset.editUrl || this.formTarget.action;
        this.titleTarget.value = trigger.dataset.entryTitle || '';
        this.setEditorContent(trigger.dataset.entryContent || '');
        this.tokenTarget.value = trigger.dataset.csrf || '';
        this.modalTarget.hidden = false;
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }
        this.modalTarget.hidden = true;
    }

    closeOnBackdrop(event) {
        if (event.target === this.modalTarget) {
            this.close();
        }
    }

    openDay(event) {
        event.preventDefault();
        const date = event.currentTarget.dataset.date;
        const dayData = this.dayDataTargets.find((item) => item.dataset.date === date);
        if (!dayData) {
            return;
        }

        this.dayTitleTarget.textContent = this.formatDateLabel(date);
        this.dayEntriesTarget.replaceChildren();

        const entries = dayData.querySelectorAll('[data-entry-id]');
        entries.forEach((entryElement) => {
            this.dayEntriesTarget.appendChild(this.buildDayEntryCard(entryElement.dataset));
        });

        this.dayModalTarget.hidden = false;
    }

    closeDay(event) {
        if (event) {
            event.preventDefault();
        }
        this.dayModalTarget.hidden = true;
    }

    closeDayOnBackdrop(event) {
        if (event.target === this.dayModalTarget) {
            this.closeDay();
        }
    }

    openDelete(event) {
        event.preventDefault();
        this.openDeleteConfirmation({
            deleteUrl: event.currentTarget.dataset.deleteUrl,
            deleteCsrf: event.currentTarget.dataset.deleteCsrf,
        });
    }

    openDeleteConfirmation({ deleteUrl, deleteCsrf }) {
        this.deleteFormTarget.action = deleteUrl || this.deleteFormTarget.action;
        this.deleteTokenTarget.value = deleteCsrf || '';
        this.deleteModalTarget.hidden = false;
    }

    closeDelete(event) {
        if (event) {
            event.preventDefault();
        }
        this.deleteModalTarget.hidden = true;
    }

    closeDeleteOnBackdrop(event) {
        if (event.target === this.deleteModalTarget) {
            this.closeDelete();
        }
    }

    confirmDelete(event) {
        event.preventDefault();
        this.deleteFormTarget.submit();
    }

    setEditorContent(content) {
        if (!this.hasContentTarget) {
            return;
        }

        if ('value' in this.contentTarget) {
            this.contentTarget.value = content;
        } else {
            this.contentTarget.textContent = content;
        }
    }

    buildDayEntryCard(entry) {
        const card = document.createElement('article');
        card.className = 'ac-journal-card';

        const header = document.createElement('div');
        header.className = 'ac-row-between';

        const titleRow = document.createElement('div');
        titleRow.className = 'ac-journal-title-row';

        const title = document.createElement('h5');
        title.textContent = entry.entryTitle || '';
        titleRow.appendChild(title);

        if (entry.entryTopEmotion) {
            const emotionTag = document.createElement('span');
            emotionTag.className = 'ac-journal-emotion-tag';
            emotionTag.textContent = this.formatEmotionLabel(entry.entryTopEmotion);

            const score = Number.parseFloat(entry.entryTopEmotionScore || '');
            if (!Number.isNaN(score)) {
                emotionTag.title = `Score: ${score.toFixed(2)}`;
            }

            titleRow.appendChild(emotionTag);
        }

        header.appendChild(titleRow);

        const time = document.createElement('small');
        time.className = 'ac-journal-time';
        time.textContent = entry.entryTime || '';
        header.appendChild(time);

        card.appendChild(header);

        const richContent = document.createElement('div');
        richContent.className = 'ac-journal-rich-content';
        richContent.innerHTML = entry.entryContent || '';
        card.appendChild(richContent);

        const actions = document.createElement('div');
        actions.className = 'ac-journal-actions';

        const editButton = document.createElement('button');
        editButton.type = 'button';
        editButton.className = 'ac-ghost-btn';
        editButton.textContent = 'Edit';
        editButton.addEventListener('click', () => {
            this.open({
                preventDefault() {},
                currentTarget: {
                    dataset: {
                        editUrl: entry.editUrl || '',
                        entryTitle: entry.entryTitle || '',
                        entryContent: entry.entryContent || '',
                        csrf: entry.editCsrf || '',
                    },
                },
            });
            this.closeDay();
        });

        const deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.className = 'ac-ghost-btn';
        deleteButton.textContent = 'Delete';
        deleteButton.addEventListener('click', () => {
            this.openDeleteConfirmation({
                deleteUrl: entry.deleteUrl || '',
                deleteCsrf: entry.deleteCsrf || '',
            });
        });

        actions.appendChild(editButton);
        actions.appendChild(deleteButton);
        card.appendChild(actions);

        return card;
    }

    formatEmotionLabel(label) {
        return String(label || '')
            .replace(/[_-]+/g, ' ')
            .trim()
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    formatDateLabel(dateValue) {
        const date = new Date(`${dateValue}T00:00:00`);
        if (Number.isNaN(date.getTime())) {
            return dateValue;
        }

        return date.toLocaleDateString(undefined, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }
}
