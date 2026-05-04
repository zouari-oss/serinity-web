document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById('sleepWeatherOverlay');
    const widget = document.getElementById('sleepWeatherWidget');
    const content = document.getElementById('sleepWeatherContent');
    const openBtn = document.getElementById('openSleepWeatherBtn');
    const closeBtn = document.getElementById('closeSleepWeatherBtn');

    function esc(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatHour(timestamp) {
        if (!timestamp) return '--:--';
        return new Date(timestamp * 1000).toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatDay(timestamp) {
        if (!timestamp) return '--';
        return new Date(timestamp * 1000).toLocaleDateString('fr-FR', {
            weekday: 'short',
            day: '2-digit',
            month: '2-digit'
        });
    }

    function getLoadingHtml() {
        return `
            <div class="sl-weather-loading sl-weather-loading--fancy">
                <div class="slw-spinner"></div>
                <div>
                    <strong>Chargement de la météo…</strong>
                    <div class="slw-loading-text">Analyse des conditions pour le sommeil</div>
                </div>
            </div>

            <div class="slw-card">
                <div class="slw-skeleton">
                    <div class="slw-skeleton__line slw-skeleton__line--sm"></div>
                    <div class="slw-skeleton__line slw-skeleton__line--lg"></div>
                    <div class="slw-skeleton__line slw-skeleton__line--md"></div>
                </div>
            </div>

            <div class="slw-card">
                <div class="slw-skeleton">
                    <div class="slw-skeleton__line slw-skeleton__line--md"></div>
                    <div class="slw-skeleton__line"></div>
                    <div class="slw-skeleton__line"></div>
                </div>
            </div>
        `;
    }

    function getErrorHtml(message) {
        return `
            <div class="sl-weather-error">
                <strong>Oups, la météo n’est pas disponible pour le moment.</strong>
                <div class="slw-note">${esc(message || 'Merci de réessayer dans quelques instants.')}</div>
                <div class="slw-error-actions">
                    <button type="button" class="slw-retry-btn" id="retrySleepWeatherBtn">Réessayer</button>
                </div>
            </div>
        `;
    }

    function openWidget() {
        if (!overlay || !widget || !content) return;

        overlay.hidden = false;
        widget.setAttribute('aria-hidden', 'false');

        requestAnimationFrame(() => {
            widget.classList.add('open');
        });

        loadWeather();
    }

    function closeWidget() {
        if (!overlay || !widget || !content) return;

        widget.classList.remove('open');
        widget.setAttribute('aria-hidden', 'true');

        setTimeout(() => {
            overlay.hidden = true;
            content.innerHTML = getLoadingHtml();
        }, 250);
    }

    async function loadWeather() {
        if (!content || !window.sleepWeatherApiUrl) return;

        content.innerHTML = getLoadingHtml();

        try {
            const response = await fetch(window.sleepWeatherApiUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Impossible de récupérer la météo.');
            }

            renderWeather(data);
        } catch (error) {
            content.innerHTML = getErrorHtml(error.message);

            const retryBtn = document.getElementById('retrySleepWeatherBtn');
            retryBtn?.addEventListener('click', loadWeather);
        }
    }

    function renderWeather(payload) {
        if (!content) return;

        const meteo = payload.meteo || {};
        const analyse = payload.analyse || {};
        const current = meteo.current || {};
        const forecast = meteo.forecast || [];
        const daily = meteo.daily || [];
        const location = meteo.location || {};
        const badge = analyse.badge || {};

        const cityLabel = [location.city, location.country].filter(Boolean).join(', ');
        const sourceLabel = payload.locationSource === 'user'
            ? 'Localisation personnalisée'
            : 'Localisation par défaut';

        const forecastHtml = forecast.map(item => `
            <div class="slw-item">
                <div class="slw-item__left">
                    ${item.icon_url ? `<img src="${esc(item.icon_url)}" alt="${esc(item.desc || '')}" class="slw-icon">` : ''}
                    <div>
                        <div class="slw-item__title">${esc(formatHour(item.dt))}</div>
                        <div class="slw-item__sub">${esc(item.desc || '')}</div>
                    </div>
                </div>
                <div class="slw-item__right">
                    <div class="slw-item__value">${esc(item.temp)}°C</div>
                    <div class="slw-item__meta">${esc(item.pop)}% pluie</div>
                </div>
            </div>
        `).join('');

        const dailyHtml = daily.map(item => `
            <div class="slw-item">
                <div class="slw-item__left">
                    ${item.icon_url ? `<img src="${esc(item.icon_url)}" alt="${esc(item.desc || '')}" class="slw-icon">` : ''}
                    <div>
                        <div class="slw-item__title">${esc(formatDay(item.dt))}</div>
                        <div class="slw-item__sub">${esc(item.desc || '')}</div>
                    </div>
                </div>
                <div class="slw-item__right">
                    <div class="slw-item__value">${esc(item.temp_min)}° / ${esc(item.temp_max)}°</div>
                    <div class="slw-item__meta">${esc(item.pop)}% pluie</div>
                </div>
            </div>
        `).join('');

        const conseilsHtml = (analyse.conseils || []).map(item => `<li>${esc(item)}</li>`).join('');

        content.innerHTML = `
            <div class="slw-card">
                <div class="slw-current-top">
                    <div>
                        <div class="slw-place">${esc(cityLabel || 'Localisation')}</div>
                        <div class="slw-temp">${esc(current.temp ?? '--')}°C</div>
                        <div class="slw-desc">${esc(current.desc || '')}</div>
                        <div class="slw-note">${esc(sourceLabel)}</div>
                    </div>
                    ${current.icon_url ? `<img src="${esc(current.icon_url)}" alt="${esc(current.desc || '')}" class="slw-icon">` : ''}
                </div>

                <div class="slw-grid">
                    <div class="slw-stat">
                        <div class="slw-stat__label">Ressenti</div>
                        <div class="slw-stat__value">${esc(current.feels_like ?? '--')}°C</div>
                    </div>

                    <div class="slw-stat">
                        <div class="slw-stat__label">Humidité</div>
                        <div class="slw-stat__value">${esc(current.humidity ?? '--')}%</div>
                    </div>

                    <div class="slw-stat">
                        <div class="slw-stat__label">Vent</div>
                        <div class="slw-stat__value">${esc(current.wind_speed ?? '--')} m/s</div>
                    </div>

                    <div class="slw-stat">
                        <div class="slw-stat__label">Pression</div>
                        <div class="slw-stat__value">${esc(current.pressure ?? '--')} hPa</div>
                    </div>
                </div>
            </div>

            <div class="slw-card">
                <div class="slw-score-row">
                    <div class="slw-score">Score : ${esc(analyse.score ?? '--')}/100</div>
                    <div class="slw-badge ${esc(badge.class || 'slw-badge--neutral')}">
                        <span>${esc(badge.emoji || '⚪')}</span>
                        <span>${esc(badge.label || 'Indisponible')}</span>
                    </div>
                </div>

                <div class="slw-summary">${esc(analyse.resume || '')}</div>
                <ul class="slw-advice-list">${conseilsHtml}</ul>
            </div>

            <div class="slw-card">
                <h4>Prochaines prévisions</h4>
                <div class="slw-list">
                    ${forecastHtml || '<div class="sl-weather-loading">Aucune donnée de prévision.</div>'}
                </div>
            </div>

            <div class="slw-card">
                <h4>Aperçu 3 jours</h4>
                <div class="slw-list">
                    ${dailyHtml || '<div class="sl-weather-loading">Aucune donnée journalière.</div>'}
                </div>
            </div>
        `;
    }

    openBtn?.addEventListener('click', openWidget);
    closeBtn?.addEventListener('click', closeWidget);
    overlay?.addEventListener('click', closeWidget);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeWidget();
        }
    });
});