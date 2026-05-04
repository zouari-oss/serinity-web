import { Controller } from '@hotwired/stimulus';

const CHART_IDS = {
    emotion: 'mood-emotion-distribution-chart',
    influence: 'mood-influence-distribution-chart',
    entryType: 'mood-entry-type-distribution-chart',
};

export default class extends Controller {
    static targets = [
        'emotionContainer',
        'influenceContainer',
        'entryTypeContainer',
        'emotionFallback',
        'influenceFallback',
        'entryTypeFallback',
    ];

    static values = {
        modalId: String,
        emotionData: Array,
        influenceData: Array,
        entryTypeData: Object,
    };

    connect() {
        this.readyPromise = null;
        this.chartsLoaded = false;
        this.ensureGoogleChartsLoader();
    }

    onModalOpened(event) {
        const openedModalId = event.detail?.id || '';
        if (openedModalId !== this.modalIdValue) {
            return;
        }

        this.hideFallbacks();

        window.requestAnimationFrame(() => {
            window.setTimeout(() => {
                this.drawCharts();
            }, 30);
        });
    }

    async drawCharts() {
        try {
            await this.ensureGoogleChartsReady();
            this.drawEmotionChart();
            this.drawInfluenceChart();
            this.drawEntryTypeChart();
        } catch (error) {
            this.showFallback(this.emotionFallbackTarget, 'Unable to load Google Charts for emotion distribution.');
            this.showFallback(this.influenceFallbackTarget, 'Unable to load Google Charts for influence distribution.');
            this.showFallback(this.entryTypeFallbackTarget, 'Unable to load Google Charts for entry type balance.');
        }
    }

    drawEmotionChart() {
        if (this.emotionContainerTarget.id !== CHART_IDS.emotion) {
            this.showFallback(this.emotionFallbackTarget, 'Chart target mismatch for emotion distribution.');
            return;
        }

        const rows = Array.isArray(this.emotionDataValue) ? this.emotionDataValue : [];
        if (rows.length === 0) {
            this.showFallback(this.emotionFallbackTarget, 'Not enough emotion data in this timeframe yet.');
            return;
        }

        try {
            const data = [['Emotion', 'Count']];
            rows.forEach((item) => data.push([item.label, Number(item.usageCount || 0)]));

            const chart = new google.visualization.PieChart(this.emotionContainerTarget);
            chart.draw(
                google.visualization.arrayToDataTable(data),
                {
                    title: 'Emotion distribution over this week.',
                    pieHole: 0.45,
                    pieSliceText: 'value',
                    legend: { position: 'right' },
                    colors: ['#2F6F6D', '#88BDBC', '#F39C6B', '#7CB9E8', '#B7A1FF', '#F1C453'],
                    chartArea: { left: 12, top: 48, width: '88%', height: '78%' },
                    height: 280,
                }
            );
        } catch (error) {
            this.showFallback(this.emotionFallbackTarget, 'Unable to render emotion distribution right now.');
        }
    }

    drawInfluenceChart() {
        if (this.influenceContainerTarget.id !== CHART_IDS.influence) {
            this.showFallback(this.influenceFallbackTarget, 'Chart target mismatch for influence distribution.');
            return;
        }

        const rows = Array.isArray(this.influenceDataValue) ? this.influenceDataValue : [];
        if (rows.length === 0) {
            this.showFallback(this.influenceFallbackTarget, 'Not enough influence data in this timeframe yet.');
            return;
        }

        try {
            const data = [['Influence', 'Count']];
            rows.forEach((item) => data.push([item.label, Number(item.usageCount || 0)]));

            const chart = new google.visualization.PieChart(this.influenceContainerTarget);
            chart.draw(
                google.visualization.arrayToDataTable(data),
                {
                    title: 'Influence distribution over this week.',
                    pieHole: 0.45,
                    pieSliceText: 'value',
                    legend: { position: 'right' },
                    colors: ['#5B7CFA', '#88BDBC', '#F39C6B', '#7CB9E8', '#B7A1FF', '#F1C453'],
                    chartArea: { left: 12, top: 48, width: '88%', height: '78%' },
                    height: 280,
                }
            );
        } catch (error) {
            this.showFallback(this.influenceFallbackTarget, 'Unable to render influence distribution right now.');
        }
    }

    drawEntryTypeChart() {
        if (this.entryTypeContainerTarget.id !== CHART_IDS.entryType) {
            this.showFallback(this.entryTypeFallbackTarget, 'Chart target mismatch for entry type balance.');
            return;
        }

        const dayCount = Number(this.entryTypeDataValue?.day || 0);
        const momentCount = Number(this.entryTypeDataValue?.moment || 0);
        if (dayCount === 0 && momentCount === 0) {
            this.showFallback(this.entryTypeFallbackTarget, 'Not enough entry type data in this timeframe yet.');
            return;
        }

        try {
            const chart = new google.visualization.ColumnChart(this.entryTypeContainerTarget);
            chart.draw(
                google.visualization.arrayToDataTable([
                    ['Entry type', 'Entries'],
                    ['Day', dayCount],
                    ['Moment', momentCount],
                ]),
                {
                    title: 'Mood entry type balance (Day vs Moment)',
                    colors: ['#2F6F6D'],
                    legend: { position: 'none' },
                    hAxis: { title: 'Entry type' },
                    vAxis: { title: 'Entries', minValue: 0 },
                    chartArea: { left: 56, top: 48, width: '80%', height: '70%' },
                    height: 280,
                }
            );
        } catch (error) {
            this.showFallback(this.entryTypeFallbackTarget, 'Unable to render entry type balance right now.');
        }
    }

    ensureGoogleChartsLoader() {
        if (window.google?.charts) {
            return;
        }

        if (document.querySelector('script[data-google-charts-loader="true"]')) {
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://www.gstatic.com/charts/loader.js';
        script.async = true;
        script.dataset.googleChartsLoader = 'true';
        document.head.appendChild(script);
    }

    ensureGoogleChartsReady() {
        if (this.readyPromise) {
            return this.readyPromise;
        }

        this.readyPromise = new Promise((resolve, reject) => {
            const startedAt = Date.now();
            const tryLoad = () => {
                if (!window.google?.charts) {
                    if (Date.now() - startedAt > 5000) {
                        reject(new Error('Google charts loader timed out.'));
                        return;
                    }
                    window.setTimeout(tryLoad, 30);
                    return;
                }

                if (this.chartsLoaded) {
                    resolve();
                    return;
                }

                google.charts.load('current', { packages: ['corechart'], language: 'en' });
                google.charts.setOnLoadCallback(() => {
                    this.chartsLoaded = true;
                    resolve();
                });
            };

            try {
                tryLoad();
            } catch (error) {
                reject(error);
            }
        });

        return this.readyPromise;
    }

    hideFallbacks() {
        this.emotionFallbackTarget.hidden = true;
        this.influenceFallbackTarget.hidden = true;
        this.entryTypeFallbackTarget.hidden = true;
    }

    showFallback(target, message) {
        target.textContent = message;
        target.hidden = false;
    }
}
