/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
(function (global) {
    'use strict';
    // Group nearby timestamps at the current zoom. Keep every record ID as a string.
    function cluster(markers, range, width, spacing = 38) {
        const groups = [];
        const available = Math.max(0, width - spacing);
        for (const marker of markers) {
            if (marker.clock < range[0] || marker.clock > range[1]) continue;
            const exact = (marker.clock - range[0]) / (range[1] - range[0]) * width;
            const x = Math.max(spacing / 2, Math.min(spacing / 2 + available, exact));
            const last = groups[groups.length - 1];
            if (last && x - last.x < spacing) last.markers.push(marker);
            else groups.push({x, markers: [marker]});
        }
        return groups;
    }
    function markerLabel(markers, labels = {}) {
        const text = key => labels[key] || key;
        const changes = markers.reduce((sum, marker) => sum + (marker.changeCount || 0), 0);
        const complete = markers.every(marker => marker.changesComplete === true);
        const updates = markers.length === 1 ? text('1 update') : text('%s updates').replace('%s', markers.length);
        const detail = complete
            ? (changes === 1 ? text('1 change') : text('%s changes').replace('%s', changes))
            : (changes > 0 ? (changes === 1 ? text('≥1 change') : text('≥%s changes').replace('%s', changes))
                : text('changes unavailable'));
        return updates + ' · ' + detail;
    }
    // Keep the visible symbol inside the bar and clear of its recovery diamond.
    // Very short/clipped bars and updates outside a bar remain available through the history count.
    function barMarkers(markers, row, snapshot, range, width, spacing = 38) {
        if (!Number.isFinite(row.clock) || !Number.isFinite(row.expiry) || row.expiry <= row.clock
                || range[1] <= range[0] || width <= 0) return [];
        const x = clock => (clock - range[0]) / (range[1] - range[0]) * width;
        const left = Math.max(0, x(row.clock)) + 16;
        const right = Math.min(width, x(row.expiry)) - 16;
        if (left > right) return [];
        const recovery = Number.isFinite(row.recovery) && row.recovery >= row.clock && row.recovery <= row.expiry
            ? x(row.recovery) : null;
        const inside = markers.filter(marker => marker.clock >= row.clock
            && marker.clock <= row.expiry && marker.clock <= snapshot);
        const result = [];
        for (const group of cluster(inside, range, width, spacing)) {
            let center = Math.max(left, Math.min(right, group.x));
            if (recovery !== null && Math.abs(center - recovery) < 26) {
                const candidates = [recovery - 26, recovery + 26].filter(value => value >= left && value <= right);
                if (!candidates.length) continue;
                center = candidates.reduce((best, value) => Math.abs(value - center) < Math.abs(best - center) ? value : best);
            }
            const previous = result[result.length - 1];
            if (previous && center - previous.x < spacing) previous.markers.push(...group.markers);
            else result.push({x: center, markers: [...group.markers]});
        }
        return result;
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = {cluster, markerLabel, barMarkers};
    if (!global.document) return;
    const icons = new Set(['zi-cep', 'zi-arrow-up-small', 'zi-arrow-down-small', 'zi-eye', 'zi-eye-off']);
    const translate = text => typeof t === 'function' ? t(text) : text;
    function attach(button, data, eventid, marker = null) {
        const request = {windowid: data.windowid, eventid, snapshot: String(data.sampledAt)};
        if (marker) Object.assign(request, {direction: 'focus', anchor: marker.id, focus: marker.id});
        button.type = 'button';
        button.dataset.cepChange = '1';
        Object.assign(button.dataset, {hintbox: '0', hintboxStatic: '1', hintboxHtml: '',
            hintboxClass: 'cep-change-hint', hintboxStyle: 'max-width: min(760px, calc(100vw - 32px));',
            hintboxPreload: JSON.stringify({action: 'cep.window.changes', data: request})});
    }
    function icon(name) {
        const span = document.createElement('span');
        span.className = icons.has(name) ? name : 'zi-cep';
        span.setAttribute('aria-hidden', 'true');
        return span;
    }
    global.CepChanges = {cluster, attach, icon, markerLabel, barMarkers};

    // Bypass native hover hints for CEP history; button clicks also cover Enter and Space.
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-cep-change]');
        if (!button || typeof hintBox === 'undefined') return;
        event.preventDefault();
        hintBox.showStaticHint(event, button, button.dataset.hintboxClass, false, button.dataset.hintboxStyle);
    });

    // Pagination stays inside the native hintbox. Responses are escaped server-rendered Zabbix HTML.
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-cep-history-page]');
        if (!button) return;
        event.preventDefault();
        const container = button.closest('.cep-change-hint');
        if (!container || container.getAttribute('aria-busy') === 'true') return;
        const request = JSON.parse(button.dataset.cepHistoryPage);
        container.setAttribute('aria-busy', 'true');
        const controls = [...container.querySelectorAll('[data-cep-history-page]')];
        controls.forEach(control => control.disabled = true);
        const abort = new AbortController(), timer = setTimeout(() => abort.abort(), 15000);
        try {
            const response = await fetch('zabbix.php?action=cep.window.changes', {
                method: 'POST', credentials: 'same-origin', signal: abort.signal,
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams(request)
            });
            if (!response.ok) throw new Error('HTTP error');
            const result = await response.json();
            if (result.error || typeof result.data !== 'string') throw new Error('History unavailable');
            if (!container.isConnected) return;
            container.innerHTML = result.data;
            container.querySelector('.cep-change-heading')?.focus({preventScroll: true});
            container.scrollTop = 0;
        }
        catch (error) {
            let feedback = container.querySelector('.cep-change-feedback');
            if (!feedback) { feedback = document.createElement('div'); container.append(feedback); }
            feedback.setAttribute('role', 'alert');
            feedback.textContent = translate('Unable to load change history. Retry or refresh the window.');
        }
        finally {
            clearTimeout(timer);
            container.removeAttribute('aria-busy');
            controls.forEach(control => control.disabled = false);
        }
    });
    if (typeof jQuery !== 'undefined') {
        // Add a bounded timeout and recovery UI only to this module's native AJAX hint requests.
        jQuery.ajaxPrefilter((options, original, xhr) => {
            if (!options.url.includes('action=cep.window.changes')) return;
            options.timeout = 15000;
            xhr.fail(() => {
                for (const target of document.querySelectorAll('[data-cep-change]')) {
                    const preload = JSON.parse(target.dataset.hintboxPreload);
                    if (jQuery.param(preload.data) !== options.data || !target.hintBoxItem) continue;
                    const container = target.hintBoxItem[0].querySelector('.cep-change-hint');
                    target.hintBoxItem[0].querySelector('#hintbox-preloader')?.remove();
                    if (!container) continue;
                    const message = document.createElement('p');
                    message.setAttribute('role', 'alert');
                    message.textContent = translate('Unable to load change history. Retry or refresh the window.');
                    const retry = document.createElement('button'); retry.type = 'button';
                    retry.textContent = translate('Retry');
                    retry.dataset.cepHistoryPage = JSON.stringify(preload.data);
                    container.replaceChildren(message, retry);
                }
            });
        });
        document.addEventListener('onShowStaticHint', event => {
            const target = event.target;
            if (target.dataset.cepChange && target.hintBoxItem) {
                target.hintBoxItem.attr('role', 'dialog').attr('aria-label', translate('Change history'));
            }
        }, true);
    }
})(typeof globalThis !== 'undefined' ? globalThis : this);
