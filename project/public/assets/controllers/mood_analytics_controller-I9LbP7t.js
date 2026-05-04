import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'filterForm',
        'fromDate',
        'toDate',
        'momentType',
        'level',
        'limit',
        'entriesMetric',
        'averageMetric',
        'typeMetric',
        'emotionMetric',
        'influenceMetric',
        'rangeMetric',
        'historyBody',
        'historyMeta',
        'pageLabel',
        'prevButton',
        'nextButton',
    ];

    static values = {
        summaryUrl: String,
        historyUrl: String,
    };

    connect() {
        this.currentPage = 1;
        this.ensureDefaultDates();
        this.loadData(1);
    }

    async applyFilters(event) {
        event.preventDefault();
        await this.loadData(1);
    }

    async resetFilters() {
        this.filterFormTarget.reset();
        this.ensureDefaultDates();
        await this.loadData(1);
    }

    async previousPage() {
        if (this.currentPage <= 1) {
            return;
        }

        await this.loadData(this.currentPage - 1);
    }

    async nextPage() {
        if (this.currentPage >= this.totalPages) {
            return;
        }

        await this.loadData(this.currentPage + 1);
    }

    async loadData(page) {
        this.currentPage = page;
        this.setLoadingState(true);

        const params = this.buildParams(page);
        const summaryParams = new URLSearchParams();
        ['fromDate', 'toDate'].forEach((key) => {
            const value = params.get(key);
            if (value) {
                summaryParams.set(key, value);
            }
        });

        try {
            const [summaryResponse, historyResponse] = await Promise.all([
                fetch(`${this.summaryUrlValue}?${summaryParams.toString()}`),
                fetch(`${this.historyUrlValue}?${params.toString()}`),
            ]);

            const summaryPayload = await summaryResponse.json();
            const historyPayload = await historyResponse.json();

            if (!summaryResponse.ok || !summaryPayload?.success) {
                throw new Error('Unable to load summary analytics.');
            }

            if (!historyResponse.ok || !historyPayload?.success) {
                throw new Error('Unable to load history analytics.');
            }

            this.renderSummary(summaryPayload.data);
            this.renderHistory(historyPayload.data);
        } catch (error) {
            this.renderError(error instanceof Error ? error.message : 'Failed to load mood analytics.');
        } finally {
            this.setLoadingState(false);
        }
    }

    buildParams(page) {
        const params = new URLSearchParams();
        const fromDate = this.fromDateTarget.value;
        const toDate = this.toDateTarget.value;
        const momentType = this.momentTypeTarget.value;
        const level = this.levelTarget.value;
        const limit = this.limitTarget.value;

        if (fromDate) {
            params.set('fromDate', fromDate);
        }
        if (toDate) {
            params.set('toDate', toDate);
        }
        if (momentType) {
            params.set('momentType', momentType);
        }
        if (level) {
            params.set('level', level);
        }

        params.set('page', String(page));
        params.set('limit', limit || '20');

        return params;
    }

    ensureDefaultDates() {
        const today = new Date();
        const start = new Date(today);
        start.setDate(today.getDate() - 6);

        if (!this.fromDateTarget.value) {
            this.fromDateTarget.value = this.toDateString(start);
        }

        if (!this.toDateTarget.value) {
            this.toDateTarget.value = this.toDateString(today);
        }
    }

    renderSummary(summary) {
        this.entriesMetricTarget.textContent = String(summary?.totals?.entries ?? 0);
        this.averageMetricTarget.textContent = summary?.totals?.averageMood ?? 'No data';
        this.typeMetricTarget.textContent = summary?.totals?.mostUsedType ?? 'No data';
        this.emotionMetricTarget.textContent = this.formatTopItem(summary?.topEmotion);
        this.influenceMetricTarget.textContent = this.formatTopItem(summary?.topInfluence);
        const fromDate = summary?.range?.fromDate ?? '—';
        const toDate = summary?.range?.toDate ?? '—';
        this.rangeMetricTarget.textContent = `${fromDate} → ${toDate}`;
    }

    renderHistory(history) {
        const rows = history?.rows ?? [];
        const pagination = history?.pagination ?? { page: 1, totalPages: 1, total: 0, limit: 20 };

        this.totalPages = Math.max(1, pagination.totalPages || 1);
        this.currentPage = Math.max(1, pagination.page || 1);

        if (rows.length === 0) {
            this.historyBodyTarget.innerHTML = '<tr><td colspan="4">No entries found for current filters.</td></tr>';
        } else {
            this.historyBodyTarget.innerHTML = rows.map((row) => `
                <tr>
                    <td>${this.escapeHtml(row.entryDate)}</td>
                    <td>${this.escapeHtml(row.userRole)}</td>
                    <td>${this.escapeHtml(row.momentType)}</td>
                    <td>${this.escapeHtml(String(row.moodLevel))}</td>
                </tr>
            `).join('');
        }

        this.historyMetaTarget.textContent = `${pagination.total} total entries`;
        this.pageLabelTarget.textContent = `Page ${this.currentPage} of ${this.totalPages}`;
        this.prevButtonTarget.disabled = this.currentPage <= 1;
        this.nextButtonTarget.disabled = this.currentPage >= this.totalPages;
    }

    renderError(message) {
        this.historyBodyTarget.innerHTML = `<tr><td colspan="4">${this.escapeHtml(message)}</td></tr>`;
        this.historyMetaTarget.textContent = 'Could not load analytics';
        this.entriesMetricTarget.textContent = '—';
        this.averageMetricTarget.textContent = '—';
        this.typeMetricTarget.textContent = '—';
        this.emotionMetricTarget.textContent = '—';
        this.influenceMetricTarget.textContent = '—';
        this.rangeMetricTarget.textContent = '—';
        this.pageLabelTarget.textContent = 'Page 1 of 1';
        this.prevButtonTarget.disabled = true;
        this.nextButtonTarget.disabled = true;
    }

    setLoadingState(isLoading) {
        this.filterFormTarget.querySelectorAll('input, select, button').forEach((element) => {
            element.disabled = isLoading;
        });

        if (isLoading) {
            this.historyMetaTarget.textContent = 'Loading…';
            this.historyBodyTarget.innerHTML = '<tr><td colspan="4">Loading…</td></tr>';
        }
    }

    formatTopItem(item) {
        if (!item || !item.label || item.usageCount <= 0) {
            return 'No data';
        }

        return `${item.label} (${item.usageCount})`;
    }

    toDateString(date) {
        return date.toISOString().slice(0, 10);
    }

    escapeHtml(value) {
        return value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }
}
