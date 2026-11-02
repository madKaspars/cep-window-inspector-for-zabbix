/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
(function(global) {
    'use strict';

    const MAX_TIME = 253402300799; // Last second of year 9999; never feed invalid dates to Intl.
    const validTime = value => typeof value === 'number' && Number.isFinite(value) && value >= 0 && value <= MAX_TIME;
    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const position = (time, range, width) => (time - range[0]) / (range[1] - range[0]) * width;
    const normalize = (start, end) => {
        const span = clamp(end - start, 1, MAX_TIME);
        const left = clamp(start, 0, MAX_TIME - span);
        return [left, left + span];
    };
    function fitRange(rows, snapshot, duration, periodStart, windowExpiry = null) {
        const times = [snapshot, periodStart, windowExpiry, ...rows.flatMap(row => [row.clock, row.expiry, row.recovery])].filter(validTime);
        let start = Math.min(...times), end = Math.max(...times);
        if (!Number.isFinite(start)) { start = 0; end = 60; }
        if (end - start < 1) {
            const half = Math.max(1, duration || 60) / 2;
            start -= half; end += half;
        }
        const padding = Math.max(0.5, (end - start) * 0.04);
        return normalize(start - padding, end + padding);
    }
    function zoomRange(range, factor, center = (range[0] + range[1]) / 2, maxSpan = MAX_TIME) {
        const span = clamp((range[1] - range[0]) * factor, 1, maxSpan);
        const ratio = clamp((center - range[0]) / (range[1] - range[0]), 0, 1);
        return normalize(center - span * ratio, center + span * (1 - ratio));
    }
    function panRange(range, fraction) {
        const shift = (range[1] - range[0]) * fraction;
        return normalize(range[0] + shift, range[1] + shift);
    }
    const civilFormatters = new Map();
    function civilParts(time, timezone) {
        if (!civilFormatters.has(timezone)) {
            civilFormatters.set(timezone, new Intl.DateTimeFormat('en-GB-u-nu-latn', {
                timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
            }));
        }
        const result = {};
        for (const part of civilFormatters.get(timezone).formatToParts(new Date(time * 1000))) {
            if (part.type !== 'literal') result[part.type] = Number(part.value);
        }
        return result;
    }
    // Calendar tick boundaries use the frontend timezone, including DST, not the browser timezone.
    function civilToTime(year, month, day, timezone) {
        const date = new Date(0);
        date.setUTCFullYear(year, month - 1, day);
        const target = date.getTime() / 1000;
        let result = target;
        for (let i = 0; i < 4; i++) {
            const parts = civilParts(result, timezone);
            const actual = new Date(0);
            actual.setUTCFullYear(parts.year, parts.month - 1, parts.day);
            actual.setUTCHours(parts.hour, parts.minute, parts.second, 0);
            const next = result + target - actual.getTime() / 1000;
            if (Math.abs(next - result) < 1) return next;
            result = next;
        }
        return result;
    }
    function ticks(range, width, timezone) {
        const target = (range[1] - range[0]) / Math.max(2, Math.floor(width / 115));
        const fixed = [1, 2, 5, 10, 15, 30, 60, 120, 300, 600, 900, 1800, 3600, 7200, 14400, 21600, 43200];
        const step = fixed.find(value => value >= target);
        const values = [];
        if (step) {
            for (let time = Math.ceil(range[0] / step) * step; time <= range[1] && values.length < 24; time += step) values.push(time);
            return {values, kind: step < 60 ? 'second' : 'time'};
        }
        const parts = civilParts(range[0], timezone);
        if (target <= 14 * 86400) {
            const days = [1, 2, 7, 14].find(value => value * 86400 >= target);
            const date = new Date(0);
            date.setUTCFullYear(parts.year, parts.month - 1, parts.day);
            let ordinal = Math.floor(date.getTime() / 86400000 / days) * days;
            for (let i = 0; i < 28; i++, ordinal += days) {
                const day = new Date(ordinal * 86400000);
                const time = civilToTime(day.getUTCFullYear(), day.getUTCMonth() + 1, day.getUTCDate(), timezone);
                if (time > range[1]) break;
                if (time >= range[0]) values.push(time);
            }
            return {values, kind: 'day'};
        }
        const months = [1, 2, 3, 6, 12, 24, 60, 120, 600, 1200, 12000].find(value => value * 2629800 >= target) || 12000;
        let index = Math.floor((parts.year * 12 + parts.month - 1) / months) * months;
        for (let i = 0; i < 28; i++, index += months) {
            const year = Math.floor(index / 12);
            if (year < 1) continue;
            if (year > 9999) break;
            const time = civilToTime(year, index % 12 + 1, 1, timezone);
            if (time > range[1]) break;
            if (time >= range[0]) values.push(time);
        }
        return {values, kind: months >= 12 ? 'year' : 'month'};
    }
    function segments(row, snapshot, range, width) {
        if (!validTime(row.clock) || !validTime(row.expiry) || row.expiry < row.clock) return [];
        const closed = row.state === 'closed';
        const recovery = closed && validTime(row.recovery) ? row.recovery : null;
        const elapsedEnd = Math.min(snapshot, row.expiry);
        // Missing recovery history may establish current status, but cannot establish a transition time.
        const parts = recovery === null
            ? [{kind: closed ? 'recovered' : 'elapsed', start: row.clock, end: elapsedEnd}]
            : [{kind: 'elapsed', start: row.clock, end: Math.min(recovery, elapsedEnd)},
                {kind: 'recovered', start: Math.max(recovery, row.clock), end: elapsedEnd}];
        parts.push({kind: closed ? 'recovered-estimate' : 'estimate', start: Math.max(snapshot, row.clock), end: row.expiry});
        return parts.filter(part => part.end > part.start && part.end > range[0] && part.start < range[1])
            .map(part => ({kind: part.kind, x: position(Math.max(range[0], part.start), range, width),
                width: position(Math.min(range[1], part.end), range, width) - position(Math.max(range[0], part.start), range, width)}));
    }
    const math = {validTime, normalize, position, fitRange, zoomRange, panRange, ticks, civilParts, civilToTime, segments};
    if (typeof module !== 'undefined' && module.exports) module.exports = math;
    if (!global.document) return;

    function node(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    }
    function svgNode(tag, attributes) {
        const element = document.createElementNS('http://www.w3.org/2000/svg', tag);
        for (const [name, value] of Object.entries(attributes)) element.setAttribute(name, value);
        return element;
    }
    class Timeline {
        constructor(root, state = null) {
            this.root = root;
            this.data = JSON.parse(root.dataset.cepTimeline);
            this.container = root.closest('#cep-member-tabs');
            this.table = this.container?.querySelector('#cep-member-table');
            if (!this.table || !this.container || !Array.isArray(this.data.rows) || this.data.rows.length > 100) throw new Error('Invalid timeline data');
            this.t = text => this.data.labels[text] || text;
            this.timezone = this.data.timezone;
            // Validate formatter support before hiding the usable server-rendered table.
            this.exact = new Intl.DateTimeFormat(document.documentElement.lang || 'en-GB', {
                timeZone: this.timezone, year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23', timeZoneName: 'short'
            });
            this.fit = fitRange(this.data.rows, this.data.sampledAt, this.data.duration, this.data.periodStart, this.data.windowExpiry);
            this.range = state?.range ? state.range.slice() : this.fit.slice();
            this.relative = state?.relative === true;
            this.selected = null;
            this.showUpdates = state?.showUpdates === true;
            this.root.classList.toggle('cep-tl-hide-updates', !this.showUpdates);
            try {
                this.build();
                this.observer = new ResizeObserver(() => this.schedule());
                this.observer.observe(this.chart);
                this.setView(state?.view || this.data.view, false);
                this.onPopState = () => this.setView(new URL(location.href).searchParams.get('view') || 'table', false);
                window.addEventListener('popstate', this.onPopState);
                root.dataset.cepReady = '1';
            }
            catch (error) { this.destroy(); throw error; }
        }
        getState() {
            return {view: this.root.hidden ? 'table' : 'timeline', range: this.range.slice(),
                relative: this.relative, showUpdates: this.showUpdates, selected: this.selected,
                scrollTop: this.pendingScroll?.top ?? this.scroll.scrollTop,
                scrollLeft: this.pendingScroll?.left ?? this.scroll.scrollLeft};
        }
        restorePosition(state) {
            if (!state) return;
            const selected = this.data.rows.find(row => row.id === state.selected);
            if (selected) this.select(selected, false);
            // SVG rendering changes row heights. Restore after drawing to avoid scroll anchoring drift.
            this.pendingScroll = {top: state.scrollTop, left: state.scrollLeft};
            this.schedule();
        }
        destroy() {
            this.observer?.disconnect();
            if (this.frame) cancelAnimationFrame(this.frame);
            if (this.onPopState) window.removeEventListener('popstate', this.onPopState);
        }
        button(text, handler, className = 'btn-alt') {
            const button = node('button', className, this.t(text));
            button.type = 'button';
            button.addEventListener('click', handler);
            return button;
        }
        build() {
            // Same structure and theme-owned classes as Zabbix CTabView, with per-page URL state.
            // Avoid the global tab cookie so another Zabbix page cannot override the Table default.
            this.container.classList.add('ui-tabs', 'ui-widget', 'ui-widget-content');
            this.tabs = node('ul', 'ui-tabs-nav tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header');
            this.tabs.setAttribute('role', 'tablist');
            this.tabs.setAttribute('aria-label', this.t('Member events'));
            const addTab = (view, label, panel) => {
                const item = node('li', 'ui-tabs-tab ui-state-default ui-corner-top');
                item.setAttribute('role', 'presentation');
                const tab = node('a', 'ui-tabs-anchor', this.t(label));
                tab.id = 'cep-tab-' + view; tab.href = '#' + panel.id;
                tab.setAttribute('role', 'tab'); tab.setAttribute('aria-controls', panel.id);
                panel.setAttribute('role', 'tabpanel'); panel.setAttribute('aria-labelledby', tab.id);
                panel.classList.add('ui-tabs-panel', 'ui-widget-content');
                tab.addEventListener('click', event => { event.preventDefault(); this.setView(view); });
                item.append(tab); this.tabs.append(item); return tab;
            };
            this.tabTable = addTab('table', 'Table', this.table);
            this.tabTimeline = addTab('timeline', 'Timeline', this.root);
            this.tabs.addEventListener('keydown', event => {
                const tabs = [this.tabTable, this.tabTimeline];
                if (!tabs.includes(event.target)) return;
                let index = tabs.indexOf(event.target);
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') index = 1 - index;
                else if (event.key === 'Home') index = 0;
                else if (event.key === 'End') index = 1;
                else if (event.key === ' ') index = tabs.indexOf(event.target);
                else return;
                event.preventDefault(); this.setView(index === 0 ? 'table' : 'timeline'); tabs[index].focus();
            });
            this.container.prepend(this.tabs);
            const tools = node('div', 'cep-tl-controls');
            this.fitButton = this.button('Fit page', () => { this.range = this.fit.slice(); this.draw(); });
            this.zoomIn = this.button('Zoom in', () => this.zoom(0.5));
            this.zoomOut = this.button('Zoom out', () => this.zoom(2));
            tools.append(this.fitButton, this.zoomIn, this.zoomOut,
                this.button('Earlier', () => this.pan(-0.25)), this.button('Later', () => this.pan(0.25)));
            const updatesControl = node('div', 'cep-tl-updates-toggle');
            const updatesLabel = node('span', null, this.t('Show CEP updates'));
            updatesLabel.id = 'cep-updates-label';
            // Match native CRadioButtonList modern markup; colors and focus are theme-owned.
            const updatesOptions = node('ul', 'radio-list-control');
            updatesOptions.setAttribute('role', 'radiogroup');
            updatesOptions.setAttribute('aria-labelledby', updatesLabel.id);
            for (const [value, label] of [['1', 'On'], ['0', 'Off']]) {
                const item = node('li'), input = node('input'), optionLabel = node('label', null, this.t(label));
                input.type = 'radio'; input.name = 'cep_show_updates'; input.value = value;
                input.id = 'cep-show-updates-' + value; input.checked = this.showUpdates === (value === '1');
                optionLabel.htmlFor = input.id;
                input.addEventListener('change', () => {
                    if (!input.checked) return;
                    this.showUpdates = input.value === '1';
                    for (const trigger of this.root.querySelectorAll('[data-cep-change]')) {
                        if (typeof hintBox !== 'undefined' && trigger.hintBoxItem) hintBox.deleteHint(trigger, false);
                    }
                    this.root.classList.toggle('cep-tl-hide-updates', !this.showUpdates);
                    this.schedule();
                });
                item.append(input, optionLabel); updatesOptions.append(item);
            }
            updatesControl.append(updatesLabel, updatesOptions); tools.append(updatesControl);
            const axisLabel = node('label', 'cep-tl-axis-label', this.t('Time axis'));
            const select = node('select'); select.setAttribute('aria-label', this.t('Time axis'));
            for (const [value, label] of [['absolute', 'Date / time'], ['relative', 'Relative to snapshot']]) {
                const option = node('option', null, this.t(label)); option.value = value; select.append(option);
            }
            select.value = this.relative ? 'relative' : 'absolute';
            select.addEventListener('change', () => { this.relative = select.value === 'relative'; this.draw(); });
            axisLabel.append(select); tools.append(axisLabel);
            this.visibleRange = node('div', 'cep-tl-range grey');
            this.visibleRange.setAttribute('aria-live', 'polite');
            this.main = node('div', 'cep-tl-main');
            this.chart = node('div', 'cep-tl-chart');
            this.scroll = node('div', 'cep-tl-scroll');
            this.scroll.tabIndex = 0;
            this.scroll.setAttribute('role', 'region');
            this.scroll.setAttribute('aria-label', this.t('Timeline'));
            this.scroll.title = this.t('Keyboard: left/right to pan, plus/minus to zoom, Home to fit this page.');
            this.scroll.addEventListener('keydown', event => {
                if (event.target !== this.scroll) return;
                const handlers = {'ArrowLeft': () => this.pan(-0.25), 'ArrowRight': () => this.pan(0.25),
                    '+': () => this.zoom(0.5), '=': () => this.zoom(0.5), '-': () => this.zoom(2),
                    'Home': () => { this.range = this.fit.slice(); this.draw(); }};
                if (handlers[event.key]) { event.preventDefault(); handlers[event.key](); }
            });
            this.header = node('div', 'cep-tl-row cep-tl-heading');
            this.axis = node('div', 'cep-tl-axis');
            if (this.data.causal) {
                this.root.classList.add('cep-tl-causal');
                this.header.append(node('div', 'cep-tl-role-cell', this.t('Role')));
            }
            this.header.append(node('div', 'cep-tl-event', this.t('Event')), this.axis,
                node('div', 'cep-tl-remaining', this.t('Time left')));
            this.scroll.append(this.header);
            this.rows = this.data.rows.map(row => {
                const element = node('div', 'cep-tl-row');
                element.dataset.eventId = row.id;
                const relationship = row.relationship;
                if (this.data.causal) {
                    element.classList.add('cep-tl-' + relationship.role);
                    const role = node('div', 'cep-tl-role-cell');
                    role.append(node('span', 'cep-role cep-role-' + relationship.role, relationship.label));
                    element.append(role);
                }
                const event = node('div', 'cep-tl-event');
                const name = this.button(row.name, () => this.select(row), 'cep-tl-event-name');
                // Event data must never be interpreted as a translation key or HTML.
                name.textContent = row.name;
                const meta = node('div', 'cep-tl-meta');
                meta.append(node('span', 'grey', this.t('Event ID') + ': ' + row.id),
                    node('span', row.state === 'open' ? 'red' : row.state === 'closed' ? 'green' : 'grey', row.status));
                if (row.state === 'closed' && !validTime(row.recovery)) {
                    meta.append(node('span', 'grey', this.t('Recovery time unavailable')));
                }
                event.append(name, meta);
                const updatesSummary = node('span', 'cep-tl-update-summary');
                const changes = row.changes || {count: null, markers: []};
                if (changes.count !== null && changes.count !== '0' && global.CepChanges) {
                    const count = document.createElement('button');
                    count.className = 'btn-link cep-change-count';
                    count.append(global.CepChanges.icon('zi-cep'), document.createTextNode(changes.label));
                    global.CepChanges.attach(count, this.data, row.id);
                    count.setAttribute('aria-label', count.textContent + '; ' + this.t('Event ID') + ': ' + row.id);
                    updatesSummary.append(count);
                    if (Number(changes.count) > changes.markers.length) count.title =
                        this.t('Latest 20 updates loaded; open history for all retained updates.');
                }
                else updatesSummary.append(node('span', 'grey', this.t('Changes') + ': '
                    + this.t(changes.count === null ? 'Unavailable' : 'None recorded')));
                meta.append(updatesSummary);

                if (this.data.causal) {
                    const relation = node('div', 'cep-tl-relationship grey');
                    if (relationship.causeId !== null) {
                        const cause = node(relationship.causeUrl ? 'a' : 'span', null, relationship.causeId);
                        if (relationship.causeUrl) cause.href = relationship.causeUrl;
                        cause.title = relationship.causeName;
                        relation.append(this.t('Cause') + ': ', cause);
                        if (relationship.causeInWindow === false) relation.title = this.t('Cause is outside this window');
                    }
                    else if (relationship.symptomCount !== null) {
                        relation.textContent = this.t('Linked symptoms') + ': ' + relationship.symptomCount;
                    }
                    if (relation.hasChildNodes()) event.append(relation);
                }
                const plot = this.button('', () => this.select(row), 'cep-tl-plot');
                plot.setAttribute('aria-label', row.name + '. '
                    + (this.data.causal ? this.t('Role') + ': ' + relationship.label + '. '
                        + (relationship.causeId ? this.t('Cause') + ': ' + relationship.causeId + '. ' : '') : '')
                    + row.status + '. ' + this.t('Recovery time') + ': '
                    + row.recoveryText + '. ' + this.t('Time left') + ': ' + row.remaining);
                plot.title = row.name + '\n' + this.t('Event time') + ': ' + row.clockText + '\n'
                    + this.t(this.data.sharedBoundary ? 'Shared period boundary' : 'Estimated expiry') + ': ' + row.expiryText
                    + '\n' + this.t('Recovery time') + ': ' + row.recoveryText
                    + '\n' + this.t('Time left') + ': ' + row.remaining;
                const remaining = node('div', 'cep-tl-remaining', row.remaining);
                if (validTime(row.expiry) && row.expiry <= this.data.sampledAt) remaining.classList.add('orange');
                remaining.title = row.expiryText;
                // Sibling buttons avoid nesting interactive change markers inside the event plot button.
                const plotCell = node('div', 'cep-tl-plot-cell');
                const markers = node('div', 'cep-tl-change-markers');
                plotCell.append(plot, markers);
                element.append(event, plotCell, remaining); this.scroll.append(element);
                return {element, plot, markers, name, row};
            });
            if (!this.rows.length) this.scroll.append(node('p', 'cep-tl-empty grey', this.t('No saved members on this page.')));
            this.chart.append(this.scroll);
            this.aside = node('aside', 'cep-tl-details'); this.aside.hidden = true;
            this.aside.setAttribute('aria-label', this.t('Selected event'));
            this.main.append(this.chart, this.aside);
            const legend = node('div', 'cep-tl-legend');
            for (const [kind, label] of [['elapsed', 'Before recovery / still open'], ['recovered', 'After recovery'],
                    ['recovery-point', 'Recorded recovery time'], ['estimate', 'Estimated remaining retention']]) {
                const item = node('span'); item.append(node('i', 'cep-tl-swatch cep-tl-' + kind
                    + (kind.startsWith('recover') ? ' green' : '')), document.createTextNode(this.t(label)));
                legend.append(item);
            }
            if (global.CepChanges) {
                const changeLegend = node('span', 'cep-tl-update-legend');
                changeLegend.append(global.CepChanges.icon('zi-cep'), document.createTextNode(this.t('Recorded CEP update')));
                legend.append(changeLegend);
            }
            legend.append(node('span', 'grey', this.t('Snapshot') + ': ' + this.exact.format(new Date(this.data.sampledAt * 1000))));
            legend.append(node('span', 'grey', this.t('Window expiry (estimate)') + ': ' + this.data.windowExpiryText));
            this.root.append(tools, this.visibleRange, this.main, legend,
                node('p', 'cep-tl-note grey', this.t(this.data.sharedBoundary
                    ? 'All members share the current period boundary. Bars show original event times, not recorded admission times.'
                    : 'Bars start at original event time, not admission time. Expiry is an estimate, not confirmed eviction or recovery.')),
                node('p', 'cep-tl-count grey', this.data.countText),
                node('p', 'cep-tl-note cep-tl-update-legend grey', this.t('Update markers show this window\'s CEP rule only. One update can contain several operations.')),
                node('p', 'cep-tl-note cep-tl-update-legend grey', this.t('Zoom in for crowded markers. Updates outside bars remain available through the history counts.')));
            if (this.data.rows.some(row => row.state === 'closed' && !validTime(row.recovery))) {
                this.root.append(node('p', 'cep-tl-note grey',
                    this.t('Recovered events stay green when recovery history is unavailable; no recovery marker is inferred.')));
            }
        }
        setView(view, update = true) {
            const timeline = view === 'timeline';
            this.root.hidden = !timeline; this.table.hidden = timeline;
            for (const [tab, selected] of [[this.tabTable, !timeline], [this.tabTimeline, timeline]]) {
                tab.setAttribute('aria-selected', String(selected)); tab.tabIndex = selected ? 0 : -1;
                tab.parentElement.classList.toggle('ui-tabs-active', selected);
                tab.parentElement.classList.toggle('ui-state-active', selected);
            }
            const value = timeline ? 'timeline' : 'table';
            if (update) {
                const current = new URL(location.href); current.searchParams.set('view', value);
                history.replaceState(history.state, '', current);
            }
            // Native redirect buttons use data-url. Preserve the selected view across refresh and cursor navigation.
            const scope = this.container.isConnected ? document : this.container;
            for (const item of scope.querySelectorAll('a[href],button[data-url]')) {
                if (this.tabs.contains(item)) continue;
                const attribute = item.hasAttribute('data-url') ? 'data-url' : 'href';
                const url = new URL(item.getAttribute(attribute), location.href);
                if (url.origin === location.origin && url.searchParams.get('action') === 'cep.window.inspect'
                        && url.searchParams.get('windowid') === this.data.windowid) {
                    url.searchParams.set('view', value);
                    item.setAttribute(attribute, url.pathname + url.search + url.hash);
                }
            }
            const filter = this.container.isConnected && document.querySelector('form[name="zbx_filter"] input[name="view"]');
            if (filter) filter.value = value;
            if (timeline) this.schedule();
        }
        schedule() {
            if (this.frame) cancelAnimationFrame(this.frame);
            this.frame = requestAnimationFrame(() => { this.frame = null; if (this.root.isConnected && !this.root.hidden) this.draw(); });
        }
        zoom(factor) {
            this.range = zoomRange(this.range, factor, undefined, Math.max(60, (this.fit[1] - this.fit[0]) * 4));
            this.draw();
        }
        pan(fraction) { this.range = panRange(this.range, fraction); this.draw(); }
        tickLabel(time, kind) {
            if (this.relative) {
                const seconds = Math.round(time - this.data.sampledAt);
                if (!seconds) return '0s';
                let rest = Math.abs(seconds);
                const parts = [];
                for (const [unit, size] of [['d', 86400], ['h', 3600], ['m', 60], ['s', 1]]) {
                    const count = Math.floor(rest / size);
                    if (count) { parts.push(count + unit); rest %= size; }
                    if (parts.length === 2) break;
                }
                return (seconds < 0 ? '−' : '+') + parts.join(' ');
            }
            const options = {timeZone: this.timezone};
            if (kind === 'second' || kind === 'time') {
                Object.assign(options, {hour: '2-digit', minute: '2-digit', hourCycle: 'h23'});
                if (kind === 'second') options.second = '2-digit';
                if (this.range[1] - this.range[0] >= 86400) Object.assign(options, {month: 'short', day: 'numeric'});
            }
            else if (kind === 'day') Object.assign(options, {month: 'short', day: 'numeric'});
            else if (kind === 'month') Object.assign(options, {month: 'short', year: 'numeric'});
            else options.year = 'numeric';
            return new Intl.DateTimeFormat(document.documentElement.lang || 'en-GB', options).format(new Date(time * 1000));
        }
        draw() {
            const width = this.axis.clientWidth;
            if (!width) return;
            const marks = ticks(this.range, width, this.timezone);
            this.axis.replaceChildren();
            this.axis.classList.remove('cep-tl-stacked-markers');
            this.visibleRange.textContent = this.exact.format(new Date(this.range[0] * 1000)) + ' — '
                + this.exact.format(new Date(this.range[1] * 1000));
            this.zoomIn.disabled = this.range[1] - this.range[0] <= 1.001;
            this.zoomOut.disabled = this.range[1] - this.range[0] >= Math.max(60, (this.fit[1] - this.fit[0]) * 4) - 0.01;
            const xs = marks.values.map(time => position(time, this.range, width));
            marks.values.forEach((time, index) => {
                const tick = node('span', 'cep-tl-tick', this.tickLabel(time, marks.kind));
                tick.style.left = xs[index] / width * 100 + '%';
                if (xs[index] < 50) tick.classList.add('cep-tl-tick-first');
                if (xs[index] > width - 50) tick.classList.add('cep-tl-tick-last');
                tick.title = this.exact.format(new Date(time * 1000)); this.axis.append(tick);
            });
            const snapshotX = position(this.data.sampledAt, this.range, width);
            const windowExpiryX = validTime(this.data.windowExpiry) ? position(this.data.windowExpiry, this.range, width) : null;
            const addBoundaryLabel = (x, className, text, time) => {
                if (x === null || x < 0 || x > width) return null;
                const label = node('span', className, this.t(text));
                label.title = this.exact.format(new Date(time * 1000)); this.axis.append(label);
                const half = Math.min(width / 2, label.offsetWidth / 2 + 4);
                label.style.left = clamp(x, half, width - half) / width * 100 + '%';
                return label;
            };
            const snapshotLabel = addBoundaryLabel(snapshotX, 'cep-tl-snapshot-label', 'Snapshot', this.data.sampledAt);
            const expiryLabel = addBoundaryLabel(windowExpiryX, 'cep-tl-window-expiry-label',
                'Window expiry (estimate)', this.data.windowExpiry);
            if (snapshotLabel && expiryLabel) {
                const snapshotBox = snapshotLabel.getBoundingClientRect(), expiryBox = expiryLabel.getBoundingClientRect();
                this.axis.classList.toggle('cep-tl-stacked-markers',
                    snapshotBox.left < expiryBox.right + 8 && expiryBox.left < snapshotBox.right + 8);
            }
            for (const item of this.rows) {
                const {row, plot} = item;
                item.element.classList.toggle('cep-tl-selected', this.selected === row.id);
                item.name.setAttribute('aria-expanded', String(this.selected === row.id));
                const svg = svgNode('svg', {viewBox: '0 0 ' + width + ' 80', width: '100%', height: '80',
                    preserveAspectRatio: 'none', 'aria-hidden': 'true'});
                // Shade only the remaining window interval; outside it uses the native row background.
                const futureStart = clamp(snapshotX, 0, width);
                const futureEnd = windowExpiryX === null ? futureStart : clamp(windowExpiryX, 0, width);
                if (futureEnd > futureStart) svg.append(svgNode('rect', {x: futureStart, y: 0,
                    width: futureEnd - futureStart, height: 80, class: 'cep-tl-future'}));
                svg.append(svgNode('path', {d: xs.map(x => 'M' + x + ' 0V80').join(' '), class: 'cep-tl-grid'}));
                if (validTime(this.data.periodStart)) {
                    const x = position(this.data.periodStart, this.range, width);
                    if (x >= 0 && x <= width) svg.append(svgNode('path', {d: 'M' + x + ' 0V80', class: 'cep-tl-period'}));
                }
                const parts = segments(row, this.data.sampledAt, this.range, width);
                for (const part of parts) svg.append(svgNode('rect', {x: part.x, y: 37, width: part.width, height: 18,
                    class: 'cep-tl-' + part.kind + (part.kind.startsWith('recovered') ? ' green' : '')}));
                let message = '';
                if (!validTime(row.clock)) message = this.t('Event time unavailable');
                else if (!validTime(row.expiry)) message = row.reason || this.t('Estimated expiry unavailable');
                else if (row.expiry < row.clock) message = this.t('Event time is after the estimated boundary');
                else if (row.expiry < this.range[0] || row.clock > this.range[1]) message = this.t('Outside visible range');
                // Point markers retain discoverability without exaggerating a short interval's duration.
                for (const [time, kind] of [[row.clock, 'event'], [row.expiry, 'expiry']]) {
                    if (!validTime(time)) continue;
                    const x = position(time, this.range, width);
                    if (x < 0 || x > width) continue;
                    if (kind === 'event' && (!parts.length || Math.abs((row.expiry - row.clock) / (this.range[1] - this.range[0]) * width) < 4)) {
                        svg.append(svgNode('circle', {cx: x, cy: 46, r: 3, class: 'cep-tl-point'
                            + (row.state === 'closed' ? ' cep-tl-recovered green' : '')}));
                    }
                    if (kind === 'expiry') svg.append(svgNode('path', {d: 'M' + x + ' 33V59', class: 'cep-tl-end'
                        + (row.state === 'closed' ? ' cep-tl-recovery-line green' : '')}));
                }
                const recoveryX = validTime(row.recovery) ? position(row.recovery, this.range, width) : null;
                if (snapshotX >= 0 && snapshotX <= width) svg.append(svgNode('path', {d: 'M' + snapshotX + ' 0V80',
                    class: 'cep-tl-snapshot' + (windowExpiryX === snapshotX ? ' cep-tl-window-expiry' : '')}));
                if (windowExpiryX !== null && windowExpiryX >= 0 && windowExpiryX <= width && windowExpiryX !== snapshotX) {
                    svg.append(svgNode('path', {d: 'M' + windowExpiryX + ' 0V80', class: 'cep-tl-window-expiry'}));
                }
                if (recoveryX !== null && recoveryX >= 0 && recoveryX <= width) {
                    if (validTime(row.clock) && validTime(row.expiry)
                            && row.recovery >= row.clock && row.recovery <= row.expiry) {
                        svg.append(svgNode('path', {d: 'M' + recoveryX + ' 37V55', class: 'cep-tl-recovery-divider'}));
                    }
                    // Center the diamond on the exact timestamp and within the 18px-high bar.
                    svg.append(svgNode('path', {d: 'M' + recoveryX + ' 40l6 6l-6 6l-6-6Z', class: 'cep-tl-recovery-marker'}));
                }
                plot.replaceChildren(svg);
                if (recoveryX !== null && recoveryX >= 0 && recoveryX <= width) {
                    const options = {timeZone: this.timezone, hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'};
                    if (this.range[1] - this.range[0] >= 86400) Object.assign(options, {year: 'numeric', month: 'short', day: 'numeric'});
                    const label = node('span', 'cep-tl-recovery-label green', this.t('Recovered') + ' '
                        + new Intl.DateTimeFormat(document.documentElement.lang || 'en-GB', options).format(new Date(row.recovery * 1000)));
                    label.title = this.t('Recovery time') + ': ' + row.recoveryText;
                    // Keep long translated timestamps inside the plot at both edges and while zooming.
                    label.style.left = clamp(recoveryX - 90, 4, Math.max(4, width - 224)) / width * 100 + '%';
                    plot.append(label);
                }
                if (message) plot.append(node('span', 'cep-tl-plot-message grey', message));
                // Native hintboxes are tied to their trigger; close them before replacing zoom-dependent markers.
                for (const old of item.markers.children) {
                    if (typeof hintBox !== 'undefined' && old.hintBoxItem) hintBox.deleteHint(old);
                }
                item.markers.replaceChildren();
                if (global.CepChanges && this.showUpdates) {
                    const spacing = matchMedia('(pointer: coarse)').matches ? 48 : 38;
                    for (const group of global.CepChanges.barMarkers(row.changes?.markers || [], row,
                            this.data.sampledAt, this.range, width, spacing)) {
                        const marker = group.markers[0];
                        const button = document.createElement('button');
                        button.className = 'cep-change-marker btn-icon';
                        button.style.left = group.x / width * 100 + '%';
                        // Compressed clusters can sit over the pale estimated segment; use native text there.
                        button.classList.toggle('cep-change-marker-estimated', group.x > snapshotX);
                        button.append(global.CepChanges.icon('zi-cep'));
                        if (group.markers.length > 1) button.append(node('span', 'cep-marker-count', String(group.markers.length)));
                        const label = global.CepChanges.markerLabel(group.markers, this.data.labels) + ' · '
                            + this.exact.format(new Date(marker.clock * 1000));
                        button.setAttribute('aria-label', row.name + '. ' + label);
                        button.title = label;
                        global.CepChanges.attach(button, this.data, row.id, marker);
                        item.markers.append(button);
                    }
                }
            }
            if (this.pendingScroll) {
                this.scroll.scrollTop = this.pendingScroll.top;
                this.scroll.scrollLeft = this.pendingScroll.left;
                this.pendingScroll = null;
            }
        }
        select(row, focus = true) {
            this.selected = row.id; this.aside.replaceChildren(); this.aside.hidden = false;
            const header = node('div', 'cep-tl-details-heading');
            const heading = node('h4', null, this.t('Selected event')); heading.tabIndex = -1;
            const close = this.button('Close details', () => {
                this.aside.hidden = true; this.selected = null; this.schedule();
                this.rows.find(item => item.row.id === row.id).name.focus();
            });
            header.append(heading, close);
            const details = node('dl');
            const add = (label, value, className) => details.append(node('dt', null, this.t(label)), node('dd', className, value));
            add('Event ID', row.id);
            if (this.data.causal) {
                add('Role', row.relationship.label);
                if (row.relationship.causeId !== null) {
                    const value = node('dd');
                    const cause = node(row.relationship.causeUrl ? 'a' : 'span', null, row.relationship.causeId);
                    if (row.relationship.causeUrl) cause.href = row.relationship.causeUrl;
                    cause.title = row.relationship.causeName;
                    value.append(cause, document.createTextNode(' · ' + row.relationship.causeName));
                    details.append(node('dt', null, this.t('Cause')), value);
                }
                if (row.relationship.symptomCount !== null) add('Linked symptoms', row.relationship.symptomCount);
            }
            if (this.data.technical) add('Event index', row.index);
            add('Problem status', row.status, row.state === 'open' ? 'red' : row.state === 'closed' ? 'green' : 'grey');
            add('Saved membership', this.t('Present'));
            add('Event time', row.clockText);
            add('Recovery time', row.recoveryText, validTime(row.recovery) ? 'green' : 'grey');
            if (validTime(this.data.periodStart)) add('Current period start', this.exact.format(new Date(this.data.periodStart * 1000)));
            add(this.data.sharedBoundary ? 'Shared period boundary' : 'Estimated expiry', row.expiryText);
            add('Time left', row.remaining);
            add('Admission time', this.t('Not recorded'));
            this.aside.append(header, node('p', 'cep-tl-detail-name', row.name), details);
            if (row.url) {
                const link = node('a', null, this.t('Open event')); link.href = row.url; this.aside.append(link);
            }
            if (focus) heading.focus({preventScroll: true});
            this.schedule();
        }
    }
    class SnapshotRefresh {
        constructor(snapshot, timeline, state = null) {
            this.snapshot = snapshot; this.timeline = timeline; this.state = state;
            this.button = snapshot.querySelector('[data-cep-refresh]');
            this.label = this.button.textContent;
            this.feedback = node('p', 'cep-refresh-feedback');
            this.feedback.setAttribute('role', 'status'); this.feedback.setAttribute('aria-live', 'polite');
            this.feedback.hidden = true;
            this.button.parentElement.after(this.feedback);
            this.button.parentElement.hidden = false;
            this.button.addEventListener('click', () => this.refresh());
            this.onPageHide = () => this.request?.abort();
            window.addEventListener('pagehide', this.onPageHide);
        }
        destroy() {
            this.request?.abort(); this.timeline?.destroy();
            window.removeEventListener('pagehide', this.onPageHide);
        }
        async refresh() {
            if (this.request || !this.snapshot.isConnected) return;
            const abort = new AbortController(); this.request = abort;
            const timer = setTimeout(() => abort.abort(), 15000);
            const buttonHadFocus = document.activeElement === this.button;
            this.button.disabled = true; this.button.textContent = this.button.dataset.refreshingLabel;
            this.button.setAttribute('aria-busy', 'true');
            this.feedback.hidden = true;
            let nextTimeline = null, nextRefresh = null;
            try {
                const url = new URL(this.snapshot.dataset.refreshUrl, location.href);
                if (url.origin !== location.origin || url.searchParams.get('action') !== 'cep.window.inspect'
                        || url.searchParams.get('windowid') !== this.snapshot.dataset.windowId) throw new Error('Invalid refresh URL');
                url.searchParams.set('view', this.timeline?.getState().view || this.state?.view || 'table');
                const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store', signal: abort.signal});
                if (!response.ok || response.redirected) throw new Error('Refresh failed');
                const html = await response.text();
                if (abort.signal.aborted || !this.snapshot.isConnected) return;
                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const next = parsed.getElementById('cep-window-snapshot');
                if (!next || next.dataset.windowId !== this.snapshot.dataset.windowId
                        || !next.querySelector('[data-cep-refresh]') || !next.querySelector('#cep-member-tabs')) {
                    throw new Error('Snapshot unavailable');
                }
                // Only the server-rendered snapshot is imported; response scripts are never executed.
                for (const script of next.querySelectorAll('script')) script.remove();
                const imported = document.importNode(next, true);
                const root = imported.querySelector('[data-cep-timeline]');
                if (!['0', '1'].includes(imported.dataset.windowPresent)
                        || (imported.dataset.windowPresent === '1') !== Boolean(root)) throw new Error('Incomplete snapshot');
                // Capture the latest controls, including changes made while the request was in flight.
                const state = this.timeline?.getState() || this.state;
                if (root) {
                    nextTimeline = new Timeline(root, state);
                    if (nextTimeline.data.windowid !== imported.dataset.windowId) throw new Error('Window mismatch');
                }
                nextRefresh = new SnapshotRefresh(imported, nextTimeline, state);
                const focused = this.snapshot.contains(document.activeElement) ? document.activeElement.id
                    : (buttonHadFocus && document.activeElement === document.body ? this.button.id : null);
                const scrollX = window.scrollX, scrollY = window.scrollY;
                for (const trigger of this.snapshot.querySelectorAll('[data-hintbox]')) {
                    if (typeof hintBox !== 'undefined' && trigger.hintBoxItem) hintBox.deleteHint(trigger, false);
                }
                this.destroy();
                this.snapshot.replaceWith(imported);
                nextTimeline?.restorePosition(state);
                nextTimeline?.schedule();
                nextRefresh.feedback.textContent = nextRefresh.button.dataset.successLabel;
                nextRefresh.feedback.classList.add('cep-refresh-success'); nextRefresh.feedback.hidden = false;
                if (focused !== null) (document.getElementById(focused) || nextRefresh.button).focus({preventScroll: true});
                window.scrollTo(scrollX, scrollY);
            }
            catch (error) {
                nextRefresh?.destroy();
                if (!nextRefresh) nextTimeline?.destroy();
                if (!this.snapshot.isConnected) return;
                this.feedback.classList.remove('cep-refresh-success');
                this.feedback.classList.add('red');
                this.feedback.textContent = this.button.dataset.errorLabel; this.feedback.hidden = false;
            }
            finally {
                clearTimeout(timer); this.request = null;
                this.button.disabled = false; this.button.textContent = this.label;
                this.button.removeAttribute('aria-busy');
                if (this.snapshot.isConnected && buttonHadFocus && document.activeElement === document.body) {
                    this.button.focus({preventScroll: true});
                }
            }
        }
    }
    function init() {
        for (const root of document.querySelectorAll('[data-cep-timeline]:not([data-cep-ready])')) {
            root.dataset.cepReady = '1';
            try { root.cepTimeline = new Timeline(root); }
            catch (error) {
                root.hidden = true;
                const table = document.getElementById('cep-member-table'); if (table) table.hidden = false;
                document.querySelector('#cep-member-tabs > .ui-tabs-nav')?.remove();
                root.before(node('p', 'grey', 'Timeline unavailable. The table remains available.'));
                console.error('CEP timeline:', error);
            }
        }
        for (const snapshot of document.querySelectorAll('#cep-window-snapshot')) {
            if (snapshot.querySelector('[data-cep-refresh]')) {
                new SnapshotRefresh(snapshot, snapshot.querySelector('[data-cep-timeline]')?.cepTimeline || null);
            }
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true});
    else init();
})(typeof window === 'undefined' ? globalThis : window);
