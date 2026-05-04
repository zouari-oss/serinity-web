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
        'criticalStatus',
        'criticalReasons',
        'resilienceScore',
        'resilienceLabel',
        'resilienceBreakdown',
        'insightSummary',
        'historyList',
        'historyEmpty',
        'historyMeta',
        'pageLabel',
        'prevPageButton',
        'nextPageButton',
        'filterSearch',
        'filterMomentType',
        'filterFromDate',
        'editModal',
        'editForm',
        'editEntryId',
        'editMoodLevelValue',
        'editSubmitButton',
    ];

    static values = {
        createUrl: String,
        historyUrl: String,
        summaryUrl: String,
        updateUrlTemplate: String,
        deleteUrlTemplate: String,
    };

    connect() {
        this.page = 1;
        this.limit = 100;
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
        return;
    }

    nextPage() {
        return;
    }

    updateEditMoodLevelLabel(event) {
        if (!this.hasEditMoodLevelValueTarget) {
            return;
        }

        this.editMoodLevelValueTarget.textContent = `${event.currentTarget.value}`;
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

        const baseQuery = new URLSearchParams({
            limit: String(this.limit),
        });

        const search = this.filterSearchTarget.value.trim();
        if (search !== '') {
            baseQuery.set('search', search);
        }

        const momentType = this.filterMomentTypeTarget.value;
        if (momentType !== '') {
            baseQuery.set('momentType', momentType);
        }

        const fromDate = this.filterFromDateTarget.value;
        if (fromDate !== '') {
            baseQuery.set('fromDate', fromDate);
        }

        try {
            const firstPageData = await this.fetchHistoryPage(baseQuery, 1);
            const totalPages = Number(firstPageData?.pagination?.totalPages || 1);
            const mergedData = {
                groups: { ...(firstPageData.groups || {}) },
                pagination: {
                    ...(firstPageData.pagination || {}),
                    page: 1,
                },
            };

            for (let page = 2; page <= totalPages; page += 1) {
                const pageData = await this.fetchHistoryPage(baseQuery, page);
                this.mergeHistoryGroups(mergedData.groups, pageData.groups || {});
            }

            this.renderHistory(mergedData);
        } catch (error) {
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

        const criticalPeriod = data.criticalPeriod || {};
        if (this.hasCriticalStatusTarget) {
            this.criticalStatusTarget.textContent = (criticalPeriod.status || 'stable').toUpperCase();
        }
        if (this.hasCriticalReasonsTarget) {
            this.criticalReasonsTarget.textContent = (criticalPeriod.reasons || []).join(' | ') || 'No strong risk signal detected in recent data.';
        }
        if (this.hasInsightSummaryTarget) {
            this.insightSummaryTarget.textContent = criticalPeriod.summary || 'Recent indicators look stable.';
        }

        const resilience = data.resilienceScore || {};
        const breakdown = resilience.breakdown || {};
        if (this.hasResilienceScoreTarget) {
            this.resilienceScoreTarget.textContent = String(resilience.score ?? 0);
        }
        if (this.hasResilienceLabelTarget) {
            this.resilienceLabelTarget.textContent = resilience.label || 'Stable';
        }
        if (this.hasResilienceBreakdownTarget) {
            this.resilienceBreakdownTarget.textContent = `Mood ${breakdown.mood ?? 0} + Tracking ${breakdown.tracking ?? 0} + Journaling ${breakdown.journaling ?? 0}`;
        }
    }

    renderHistory(data) {
        const groups = Object.values(data.groups ?? {});
        const pagination = data.pagination ?? { page: 1, totalPages: 1, total: 0 };

        this.page = 1;
        this.pageLabelTarget.textContent = 'All entries';
        this.historyMetaTarget.textContent = `${pagination.total || 0} entries`;

        this.prevPageButtonTarget.disabled = true;
        this.nextPageButtonTarget.disabled = true;

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
                row.appendChild(this.buildRowActions(entry));

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

    buildRowActions(entry) {
        const actions = document.createElement('div');
        actions.className = 'ac-mood-row-actions';

        const editButton = document.createElement('button');
        editButton.className = 'ac-ghost-btn';
        editButton.type = 'button';
        editButton.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">edit</span>';
        editButton.setAttribute('aria-label', 'Edit mood entry');
        editButton.addEventListener('click', () => this.openEditModal(entry));

        const deleteButton = document.createElement('button');
        deleteButton.className = 'ac-ghost-btn ac-btn-danger';
        deleteButton.type = 'button';
        deleteButton.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">delete</span>';
        deleteButton.setAttribute('aria-label', 'Delete mood entry');
        deleteButton.addEventListener('click', () => this.deleteEntry(entry.id));

        actions.append(editButton, deleteButton);

        return actions;
    }

    openEditModal(entry) {
        this.editEntryIdTarget.value = String(entry.id);
        this.editFormTarget.elements.momentType.value = entry.momentType || 'MOMENT';
        this.editFormTarget.elements.moodLevel.value = String(entry.moodLevel ?? 3);
        this.editMoodLevelValueTarget.textContent = String(entry.moodLevel ?? 3);

        this.editFormTarget.querySelectorAll('input[name="emotionKeys"]').forEach((input) => {
            input.checked = (entry.emotions || []).some((emotion) => emotion.key === input.value);
        });

        this.editFormTarget.querySelectorAll('input[name="influenceKeys"]').forEach((input) => {
            input.checked = (entry.influences || []).some((influence) => influence.key === input.value);
        });

        this.editModalTarget.hidden = false;
    }

    closeEditModal(event = null) {
        if (event && event.type === 'click' && event.currentTarget === this.editModalTarget && event.target !== this.editModalTarget) {
            return;
        }

        this.editModalTarget.hidden = true;
    }

    async submitEdit(event) {
        event.preventDefault();

        const entryId = this.editEntryIdTarget.value;
        if (!entryId) {
            this.showToast('Mood entry not found.', 'error');
            return;
        }

        const payload = {
            momentType: this.editFormTarget.elements.momentType.value,
            moodLevel: Number(this.editFormTarget.elements.moodLevel.value),
            emotionKeys: this.selectedValues('emotionKeys', this.editFormTarget),
            influenceKeys: this.selectedValues('influenceKeys', this.editFormTarget),
        };

        this.setEditSubmitState(true);

        try {
            const response = await fetch(this.entryUrl(this.updateUrlTemplateValue, entryId), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const body = await this.readJson(response);

            if (!response.ok || !body?.success) {
                throw new Error(body?.message || 'Failed to update mood entry.');
            }

            this.showToast(body.message || 'Mood entry updated successfully.', 'success');
            this.closeEditModal();
            await Promise.all([this.loadSummary(), this.loadHistory()]);
        } catch (error) {
            this.showToast(error.message || 'Failed to update mood entry.', 'error');
        } finally {
            this.setEditSubmitState(false);
        }
    }

    async deleteEntry(entryId) {
        if (!window.confirm('Delete this mood entry?')) {
            return;
        }

        try {
            const response = await fetch(this.entryUrl(this.deleteUrlTemplateValue, entryId), {
                method: 'DELETE',
            });
            const body = await this.readJson(response);

            if (!response.ok || !body?.success) {
                throw new Error(body?.message || 'Failed to delete mood entry.');
            }

            this.showToast(body.message || 'Mood entry deleted successfully.', 'success');
            await Promise.all([this.loadSummary(), this.loadHistory()]);
        } catch (error) {
            this.showToast(error.message || 'Failed to delete mood entry.', 'error');
        }
    }

    selectedValues(name, scope = this.formTarget) {
        return Array.from(scope.querySelectorAll(`input[name="${name}"]:checked`))
            .map((field) => field.value);
    }

    setSubmitState(isLoading) {
        this.submitButtonTarget.disabled = isLoading;
        this.submitButtonTarget.textContent = isLoading ? 'Saving...' : 'Save entry';
    }

    setEditSubmitState(isLoading) {
        this.editSubmitButtonTarget.disabled = isLoading;
        this.editSubmitButtonTarget.textContent = isLoading ? 'Saving...' : 'Save changes';
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

    async fetchHistoryPage(baseQuery, page) {
        const query = new URLSearchParams(baseQuery.toString());
        query.set('page', String(page));

        const response = await fetch(`${this.historyUrlValue}?${query.toString()}`);
        const body = await this.readJson(response);

        if (!response.ok || !body?.success || !body.data) {
            throw new Error(body?.message || 'Failed to load mood history.');
        }

        return body.data;
    }

    mergeHistoryGroups(targetGroups, sourceGroups) {
        Object.entries(sourceGroups).forEach(([groupKey, groupData]) => {
            if (!targetGroups[groupKey]) {
                targetGroups[groupKey] = {
                    label: groupData?.label || groupKey,
                    entries: [],
                };
            }

            targetGroups[groupKey].entries.push(...(groupData?.entries || []));
        });
    }

    entryUrl(template, entryId) {
        return template.replace('__id__', encodeURIComponent(String(entryId)));
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
