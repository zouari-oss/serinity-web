import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'form',
        'submitButton',
        'moodLevelValue',
        'weeklyCount',
        'weeklyAverage',
        'mostUsedType',
        'summaryRange',
        'topEmotion',
        'topInfluence',
        'historyList',
        'historyEmpty',
        'historyMeta',
        'pageLabel',
        'prevPageButton',
        'nextPageButton',
        'filterSearch',
        'filterMomentType',
        'filterFromDate',
    ];

    static values = {
        createUrl: String,
        historyUrl: String,
        summaryUrl: String,
    };

    connect() {
        this.page = 1;
        this.limit = 10;
        this.loadingHistory = false;
        this.loadSummary();
        this.loadHistory();
    }

    updateMoodLevelLabel(event) {
        if (!this.hasMoodLevelValueTarget) {
            return;
        }

        this.moodLevelValueTarget.textContent = `${event.currentTarget.value}`;
    }

    async submitEntry(event) {
        event.preventDefault();

        const payload = {
            momentType: this.formTarget.elements.momentType.value,
            moodLevel: Number(this.formTarget.elements.moodLevel.value),
            emotionKeys: this.selectedValues('emotionKeys'),
            influenceKeys: this.selectedValues('influenceKeys'),
            note: this.formTarget.elements.note.value,
        };

        this.setSubmitState(true);

        try {
            const response = await fetch(this.createUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const body = await this.readJson(response);

            if (!response.ok || !body?.success) {
                throw new Error(body?.message || 'Failed to create mood entry.');
            }

            this.showToast(body.message || 'Mood entry created successfully.', 'success');
            this.formTarget.reset();
            this.formTarget.elements.moodLevel.value = '3';
            this.moodLevelValueTarget.textContent = '3';
            this.page = 1;
            await Promise.all([this.loadSummary(), this.loadHistory()]);
        } catch (error) {
            this.showToast(error.message || 'Failed to create mood entry.', 'error');
        } finally {
            this.setSubmitState(false);
        }
    }

    applyFilters(event) {
        event.preventDefault();
        this.page = 1;
        this.loadHistory();
    }

    prevPage() {
        if (this.page <= 1 || this.loadingHistory) {
            return;
        }

        this.page -= 1;
        this.loadHistory();
    }

    nextPage() {
        if (this.loadingHistory) {
            return;
        }

        this.page += 1;
        this.loadHistory();
    }

    async loadSummary() {
        try {
            const response = await fetch(`${this.summaryUrlValue}?days=7`);
            const body = await this.readJson(response);

            if (!response.ok || !body?.success || !body.data) {
                throw new Error(body?.message || 'Failed to load mood summary.');
            }

            this.renderSummary(body.data);
        } catch (error) {
            this.showToast(error.message || 'Failed to load mood summary.', 'error');
        }
    }

    async loadHistory() {
        this.loadingHistory = true;
        this.historyMetaTarget.textContent = 'Loading history...';
        this.prevPageButtonTarget.disabled = true;
        this.nextPageButtonTarget.disabled = true;

        const query = new URLSearchParams({
            page: String(this.page),
            limit: String(this.limit),
        });

        const search = this.filterSearchTarget.value.trim();
        if (search !== '') {
            query.set('search', search);
        }

        const momentType = this.filterMomentTypeTarget.value;
        if (momentType !== '') {
            query.set('momentType', momentType);
        }

        const fromDate = this.filterFromDateTarget.value;
        if (fromDate !== '') {
            query.set('fromDate', fromDate);
        }

        try {
            const response = await fetch(`${this.historyUrlValue}?${query.toString()}`);
            const body = await this.readJson(response);

            if (!response.ok || !body?.success || !body.data) {
                throw new Error(body?.message || 'Failed to load mood history.');
            }

            this.renderHistory(body.data);
        } catch (error) {
            if (this.page > 1) {
                this.page -= 1;
            }
            this.historyMetaTarget.textContent = 'Failed to load history.';
            this.showToast(error.message || 'Failed to load mood history.', 'error');
        } finally {
            this.loadingHistory = false;
        }
    }

    renderSummary(data) {
        this.weeklyCountTarget.textContent = String(data.weeklyCount ?? 0);
        this.weeklyAverageTarget.textContent = data.weeklyAverageMood ?? '--';
        this.mostUsedTypeTarget.textContent = data.mostUsedType ?? 'No data';
        this.summaryRangeTarget.textContent = `${data.fromDate ?? '--'} → ${data.toDate ?? '--'}`;
        this.topEmotionTarget.textContent = this.formatTop(data.topEmotion);
        this.topInfluenceTarget.textContent = this.formatTop(data.topInfluence);
    }

    renderHistory(data) {
        const groups = Object.values(data.groups ?? {});
        const pagination = data.pagination ?? { page: 1, totalPages: 1, total: 0 };

        this.page = Number(pagination.page || 1);
        this.pageLabelTarget.textContent = `Page ${pagination.page || 1}`;
        this.historyMetaTarget.textContent = `${pagination.total || 0} entries`;

        this.prevPageButtonTarget.disabled = this.page <= 1;
        this.nextPageButtonTarget.disabled = this.page >= Number(pagination.totalPages || 1);

        this.historyListTarget.replaceChildren();
        this.historyEmptyTarget.hidden = groups.length > 0;

        if (groups.length === 0) {
            return;
        }

        groups.forEach((group) => {
            const groupSection = document.createElement('article');
            groupSection.className = 'ac-mood-history-group';

            const groupTitle = document.createElement('h4');
            groupTitle.textContent = group.label || 'Unknown';
            groupSection.appendChild(groupTitle);

            (group.entries || []).forEach((entry) => {
                const row = document.createElement('div');
                row.className = 'ac-mood-entry-row';

                const rowHead = document.createElement('div');
                rowHead.className = 'ac-row-between';

                const typeBadge = document.createElement('span');
                typeBadge.className = `ac-badge ac-badge-${entry.momentType === 'DAY' ? 'primary' : 'secondary'}`;
                typeBadge.textContent = entry.momentType || 'MOMENT';

                const level = document.createElement('strong');
                level.className = 'ac-mood-level-pill';
                level.textContent = `Level ${entry.moodLevel ?? '-'}/5`;

                rowHead.appendChild(typeBadge);
                rowHead.appendChild(level);

                row.appendChild(rowHead);
                row.appendChild(this.buildTagList('Emotions', entry.emotions || []));
                row.appendChild(this.buildTagList('Influences', entry.influences || []));

                if (entry.note) {
                    const note = document.createElement('p');
                    note.className = 'ac-mood-note';
                    note.textContent = entry.note;
                    row.appendChild(note);
                }

                groupSection.appendChild(row);
            });

            this.historyListTarget.appendChild(groupSection);
        });
    }

    buildTagList(label, items) {
        const wrapper = document.createElement('div');
        wrapper.className = 'ac-mood-tag-row';

        const title = document.createElement('span');
        title.className = 'ac-mood-tag-label';
        title.textContent = `${label}:`;
        wrapper.appendChild(title);

        const tags = document.createElement('div');
        tags.className = 'ac-mood-tags';

        items.forEach((item) => {
            const tag = document.createElement('span');
            tag.className = 'ac-badge ac-badge-secondary';
            tag.textContent = item.label || item.key || '';
            tags.appendChild(tag);
        });

        wrapper.appendChild(tags);

        return wrapper;
    }

    selectedValues(name) {
        return Array.from(this.formTarget.querySelectorAll(`input[name="${name}"]:checked`))
            .map((field) => field.value);
    }

    setSubmitState(isLoading) {
        this.submitButtonTarget.disabled = isLoading;
        this.submitButtonTarget.textContent = isLoading ? 'Saving...' : 'Save entry';
    }

    formatTop(item) {
        if (!item || !item.label || item.label === 'No data') {
            return 'No data';
        }

        return `${item.label} (${item.usageCount ?? 0})`;
    }

    async readJson(response) {
        return response.json().catch(() => null);
    }

    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `ac-toast ac-toast-${type}`;
        toast.textContent = message;
        toast.style.bottom = '2rem';

        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 300ms ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}
