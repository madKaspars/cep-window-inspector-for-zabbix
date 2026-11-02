<?php declare(strict_types = 0);
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
use Modules\CepWindowInspector\Includes\Changes;
/** @var CView $this */
use Modules\CepWindowInspector\Includes\Timing;
use Modules\CepWindowInspector\Includes\Timeline;
use Modules\CepWindowInspector\Includes\Relationships;
require_once dirname(__DIR__).'/includes/Timeline.php';

$url = static function(array $args = []) use ($data): CUrl {
    $url = (new CUrl('zabbix.php'))->setArgument('action', 'cep.window.inspect');
    if ($data['details']) {
        $url->setArgument('details', 1);
    }
    $url->setArgument('view', $data['view']);
    foreach ($args as $key => $value) {
        if ($value !== '') {
            $url->setArgument($key, $value);
        }
    }
    return $url;
};
$timestamp = static function($value): string {
    return $value === null ? _('Unavailable') : zbx_date2str(DATE_TIME_FORMAT_SECONDS, (int) $value);
};
$expiry = static function(array $value) use ($timestamp): string {
    return $value['at'] === null ? $value['reason'] : $timestamp($value['at']);
};
$remaining = static function(array $value) use ($data, $expiry): CSpan {
    return (new CSpan(Timing::remaining($value['at'], $data['sampled_at'])))
        ->addClass($value['at'] === null ? ZBX_STYLE_GREY
            : ($value['at'] <= $data['sampled_at'] ? ZBX_STYLE_ORANGE : null))
        ->setAttribute('title', $expiry($value));
};
$group = static function(array $window): string {
    $parts = [];
    if ($window['hostid'] !== null && $window['hostid'] !== '0') {
        $parts[] = _('Host').': '.($window['host_name'] ?? $window['hostid']);
    }
    if ($window['groupid'] !== null && $window['groupid'] !== '0') {
        $parts[] = _('Host group').': '.($window['host_group_name'] ?? $window['groupid']);
    }
    if ($window['tags'] !== '') {
        $parts[] = _('Tags').': '.$window['tags'].' = '.$window['tags_value'];
    }
    return $parts ? implode('; ', $parts)
        : ((int) $window['group_by'] === 0 ? _('None (shared window)') : _('Group key unavailable'));
};
$rule_status = static function(array $window): CSpan {
    if ($window['rule_status'] === null) {
        return (new CSpan(_('Unavailable')))->addClass(ZBX_STYLE_GREY);
    }
    return (new CSpan((int) $window['rule_status'] === 0 ? _('Enabled') : _('Disabled')))
        ->addClass((int) $window['rule_status'] === 0 ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);
};
$rule_name = static function(array $window) {
    return $window['rule_name'] === null
        ? (new CSpan(_('Missing rule')))->addClass(ZBX_STYLE_GREY)
        : (new CLink($window['rule_name'], (new CUrl('zabbix.php'))
            ->setArgument('action', 'popup')
            ->setArgument('popup', 'ceprule.edit')
            ->setArgument('cepruleid', $window['cep_ruleid'])))
            ->setAttribute('title', _('Open rule configuration').' — '._('Rule ID').': '.$window['cep_ruleid']);
};
$capacity = static function(array $window): string {
    return $window['capacity'] === '0' ? _('Unlimited') : ($window['capacity'] ?? _('Unavailable'));
};
$types = class_exists('CCepRuleHelper') && method_exists('CCepRuleHelper', 'getWindowLabelStrings')
    ? CCepRuleHelper::getWindowLabelStrings() : [];

// Use native pager styles while retaining bounded cursor queries and honest row counts.
$pager = static function(int $count, bool $is_later, CUrl $first, ?CUrl $next): CDiv {
    $nav = (new CTag('nav', true))->addClass(ZBX_STYLE_PAGER_CONTAINER)
        ->setAttribute('aria-label', _('Page navigation'));
    if ($is_later) {
        $nav->addItem((new CLink(_('First'), $first))->setAttribute('aria-label', _('Go to first page')));
    }
    if ($next !== null) {
        $nav->addItem((new CLink(_('Next'), $next))->setAttribute('aria-label', _('Go to next page')));
    }
    $nav->addItem((new CDiv(sprintf(_('Displaying %s records'), $count)))->addClass(ZBX_STYLE_TABLE_STATS));
    return (new CDiv($nav))->addClass(ZBX_STYLE_PAGER);
};
$current_args = ['windowid' => $data['windowid'], 'ruleid' => $data['ruleid'],
    'before' => $data['before'], 'after_index' => $data['after_index'], 'after_eventid' => $data['after_eventid']];
$controls = new CList();
if ($data['windowid'] !== '') {
    $controls->addItem((new CRedirectButton(_('All windows'), $url(['ruleid' => $data['ruleid'], 'before' => $data['before'], 'view' => 'table'])))
        ->addClass(ZBX_STYLE_BTN_ALT));
}
$controls->addItem((new CRedirectButton(_('Refresh'), $url($current_args)))->addClass(ZBX_STYLE_BTN_ALT));
$page = (new CHtmlPage())->setTitle($data['windowid'] === '' ? _('CEP windows') : _('CEP window details'))
    ->setControls((new CTag('nav', true, $controls))->setAttribute('aria-label', _('Content controls')));
$page->addItem((new CFilter())
    ->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'cep.window.inspect'))
    ->setActiveTab($data['windowid'] === '' ? 1 : 0)
    ->addFilterTab(_('Filter'), [
        (new CFormGrid())->addItem([
            new CLabel(_('Rule ID'), 'ruleid'),
            new CFormField((new CTextBox('ruleid', $data['ruleid']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
                ->setAttribute('placeholder', _('All rules'))->setAttribute('inputmode', 'numeric')),
            new CLabel(_('Window ID'), 'windowid'),
            new CFormField((new CTextBox('windowid', $data['windowid']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
                ->setAttribute('placeholder', _('All windows'))->setAttribute('inputmode', 'numeric'))
        ]),
        (new CFormGrid())->addItem([
            new CLabel(_('Show technical details'), 'details'),
            new CFormField((new CCheckBox('details', '1'))->setChecked($data['details']))
        ])
    ])->addVar('action', 'cep.window.inspect')->addVar('view', $data['view']));
if ($data['error'] !== '') {
    error($data['error']);
    $page->show();
    return;
}
$snapshot = (new CDiv())->setId('cep-window-snapshot')
    ->setAttribute('data-window-id', $data['windowid'])
    ->setAttribute('data-window-present', $data['window'] !== null ? '1' : '0')
    ->setAttribute('data-refresh-url', $url($current_args)->getUrl());
$page->addItem($snapshot);
$refresh = (new CDiv((new CButton('cep-snapshot-refresh', _('Refresh')))
    ->setAttribute('type', 'button')->addClass(ZBX_STYLE_BTN_ALT)
    ->setAttribute('data-cep-refresh', '1')
    ->setAttribute('data-refreshing-label', _('Refreshing…'))
    ->setAttribute('data-error-label', _('Unable to refresh. The previous snapshot and settings are still displayed. Try again.'))
    ->setAttribute('data-success-label', _('Snapshot refreshed.'))))
    ->addClass('cep-member-refresh')->setAttribute('hidden', 'hidden');
if ($data['timing_error'] !== '') {
    $snapshot->addItem((new CTag('p', true, $data['timing_error']))->addClass(ZBX_STYLE_ORANGE));
}
$snapshot->addItem((new CTag('p', true, [
    _('Saved database state'), ' · ', _('Read at').': '.$timestamp($data['sampled_at']), ' ',
    makeHelpIcon(_('Counts and members can briefly differ while workers update the database. Time left is calculated at the displayed read time. Refresh to update.'))
]))->addClass(ZBX_STYLE_GREY));

if ($data['windowid'] === '') {
    $header = [_('Window'), _('Rule'), _('Status'), _('Type'), _('Group'), _('Members'), _('Duration'), _('Capacity'),
        [_('Time left'), ' ', makeHelpIcon(_('Latest age limit of current members, or the shared period boundary for cause and symptoms grouping. New events and rule operations can change this.'))]];
    if ($data['details']) {
        $header = array_merge($header, [_('Window / period start'), _('Expiry estimate')]);
    }
    $table = (new CTableInfo())->addClass('cep-window-list')->setHeader($header)->setNoDataMessage(_('No saved CEP windows found.'),
        _('Try another rule ID or refresh after events enter a window.'), ZBX_ICON_SEARCH_LARGE);
    foreach ($data['windows'] as $window) {
        $window_link = (new CLink(sprintf(_('View window #%s'), $window['cep_windowid']),
            $url(['windowid' => $window['cep_windowid'], 'ruleid' => $data['ruleid'],
                'before' => $data['before'], 'view' => 'table'])))->addClass('cep-window-open');
        $row = [(new CCol($window_link))->addClass('cep-window-open-cell'),
            (new CCol($rule_name($window)))->addClass(ZBX_STYLE_WORDBREAK), $rule_status($window),
            $window['window_type'] === null ? _('Unknown') : ($types[(int) $window['window_type']] ?? _('Unknown')),
            (new CCol($group($window)))->addClass(ZBX_STYLE_WORDBREAK), $window['members'],
            $window['duration'] ?? _('Unavailable'), $capacity($window), $remaining($window['expiry'])];
        if ($data['details']) {
            $row[] = $timestamp($window['created_at']);
            $row[] = $expiry($window['expiry']);
        }
        $table->addRow((new CRow($row))->addClass('cep-window-row'));
    }
    $next = null;
    if ($data['more']) {
        $last = end($data['windows']);
        $next = $url(['ruleid' => $data['ruleid'], 'before' => $last['cep_windowid']]);
    }
    $snapshot->addItem($table)->addItem($pager(count($data['windows']), $data['before'] !== '',
        $url(['ruleid' => $data['ruleid']]), $next));
}
elseif ($data['window'] === null) {
    $snapshot->addItem((new CDiv([$refresh,
        (new CTableInfo())->setHeader([_('Window')])->setNoDataMessage(_('Window not found'),
            _('It may have expired, been closed, or been removed.'), ZBX_ICON_SEARCH_LARGE)
    ]))->setId('cep-member-tabs')->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER));
}
else {
    $window = $data['window'];
    $causal = (int) $window['window_type'] === 2;
    $role_badge = static function(string $role, string $label): CSpan {
        return (new CSpan($label))->addClass('cep-role')->addClass('cep-role-'.$role);
    };
    $counts = $data['counts'];
    $details = (new CTableInfo())
        ->addRow([_('Window ID'), $window['cep_windowid']])->addRow([_('Rule'), $rule_name($window)])
        ->addRow([_('Rule status'), $rule_status($window)])
        ->addRow([_('Type'), $window['window_type'] === null ? _('Unknown') : ($types[(int) $window['window_type']] ?? _('Unknown'))])
        ->addRow([_('Group'), (new CCol($group($window)))->addClass(ZBX_STYLE_WORDBREAK)])
        ->addRow([(int) $window['window_type'] === 2 ? _('Current period start') : _('Created'), $timestamp($window['created_at'])])
        ->addRow([_('Configured duration'), $window['duration'] ?? _('Unavailable')])
        ->addRow([_('Configured capacity'), $capacity($window)]);
    $summary = (new CTableInfo())
        ->addRow([_('Saved members'), $counts['members']])
        ->addRow([_('Open'), (new CSpan($counts['open_count']))->addClass(ZBX_STYLE_RED)])
        ->addRow([_('Recovered / closed'), (new CSpan($counts['closed_count']))->addClass(ZBX_STYLE_GREEN)])
        ->addRow([_('History unavailable'), (new CSpan($counts['unavailable_count']))->addClass(ZBX_STYLE_GREY)])
        ->addRow([[_('Expiry estimate'), ' ', makeHelpIcon(_('Latest age limit of all saved members, or the current shared period boundary. This is not confirmation of window closure.'))], $expiry($window['expiry'])])
        ->addRow([_('Time left'), $remaining($window['expiry'])]);
    $snapshot->addItem((new CDiv([
        new CDiv((new CSection($details))->setHeader(new CTag('h4', true, _('Window details')))->addClass(ZBX_STYLE_ROUNDED_SURFACE)),
        new CDiv((new CSection($summary))->setHeader(new CTag('h4', true, _('Membership summary')))->addClass(ZBX_STYLE_ROUNDED_SURFACE))
    ]))->addClass(ZBX_STYLE_COLUMNS)->addClass(ZBX_STYLE_COLUMNS_2));
    $role_summary = null;
    if ($causal) {
        $context = $data['relationships'];
        $relationship_bar = (new CDiv())->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)->addClass('cep-relationship-summary');
        if ($context === null) {
            $relationship_bar->addItem((new CSpan(_('Relationship summary unavailable. Roles below use each member\'s saved link.')))
                ->addClass(ZBX_STYLE_GREY));
        }
        else {
            foreach ($context['groups'] as $cause_group) {
                $id = (string) $cause_group['eventid'];
                $cause = $context['events'][$id] ?? null;
                $cause_url = Relationships::eventUrl($cause);
                $group_row = (new CDiv([
                    $role_badge('cause', _('Cause')),
                    $cause_url === null ? new CSpan($id) : new CLink($id, $cause_url),
                    (new CSpan($cause['name'] ?? _('Event history unavailable')))->addClass('cep-cause-name')->addClass(ZBX_STYLE_WORDBREAK),
                    (new CSpan(_('Linked symptoms').': '.($context['symptom_counts'][$id] ?? 0)))
                        ->addClass('cep-cause-count')->setAttribute('title', _('Saved symptoms in this window, across all pages.'))
                ]))->addClass('cep-cause-group');
                if ($cause !== null && $cause['member_eventid'] === null) {
                    $group_row->addItem((new CSpan(_('Cause is outside this window')))->addClass(ZBX_STYLE_GREY));
                }
                $relationship_bar->addItem($group_row);
            }
            if (!$context['groups']) {
                $relationship_bar->addItem((new CSpan(_('No cause identified in the saved data.')))->addClass(ZBX_STYLE_GREY));
            }
            if ((int) $context['summary']['cause_groups'] > count($context['groups'])) {
                $relationship_bar->addItem((new CSpan(sprintf(_('Showing %1$s of %2$s cause groups.'),
                    count($context['groups']), $context['summary']['cause_groups'])))->addClass(ZBX_STYLE_GREY));
            }
            $role_summary = (new CDiv(_('Cause members').': '.$context['summary']['causes'].' · '
                ._('Symptoms').': '.$context['summary']['symptoms'].' · '
                ._('Role unavailable').': '.$context['summary']['unknown']))
                ->addClass('cep-role-summary')->addClass(ZBX_STYLE_GREY)
                ->setAttribute('title', _('Current saved roles across all member pages; counts and rows are separate reads.'));
        }
        $relationship_bar->addItem((new CDiv(_('Shared window expiry').': '.$expiry($window['expiry'])))
            ->addClass('cep-shared-expiry')->addClass(ZBX_STYLE_GREY));
        $snapshot->addItem($relationship_bar);
    }
    $header = [_('Event time'), _('Status'), _('Event name'), _('Event ID'), _('Changes'), _('Recovery time'),
        [_('Estimated expiry'), ' ', makeHelpIcon(_('Estimated time-based membership limit. Earlier removal is possible; an overdue estimate does not mean the problem has closed.'))], _('Time left')];
    if ($causal) {
        array_unshift($header, _('Role'));
    }
    if ($data['details']) {
        $header = array_merge($header, [_('Event index'), _('Unix timestamp'), _('Recovery event ID'), _('Closed by CEP rule ID')]);
    }
    $table = (new CTableInfo())->setHeader($header)->setNoDataMessage(_('This window currently has no saved members.'),
        _('Refresh to read its latest saved state.'), ZBX_ICON_SEARCH_LARGE);
    if ($causal) {
        $table->addClass('cep-causal-table');
    }
    foreach ($data['members'] as $member) {
        $eventid = $member['eventid'];
        if ($member['event_timestamp'] !== null && (int) $member['source'] === EVENT_SOURCE_TRIGGERS
                && (int) $member['object'] === EVENT_OBJECT_TRIGGER) {
            $eventid = new CLink($member['eventid'], (new CUrl('tr_events.php'))
                ->setArgument('triggerid', $member['objectid'])->setArgument('eventid', $member['eventid']));
        }
        $status_class = $member['problem_status'] === 'Open' ? ZBX_STYLE_RED
            : ($member['problem_status'] === 'Recovered / closed' ? ZBX_STYLE_GREEN : ZBX_STYLE_GREY);
        $name = $member['name'] ?? _('Event history unavailable');
        if ($causal) {
            $relationship = Relationships::member($member, $data['relationships']);
            $name = (new CDiv(new CSpan($name)))->addClass('cep-member-name')->addClass('cep-name-'.$relationship['role']);
            if ($relationship['role'] === 'symptom') {
                $cause_link = $relationship['causeUrl'] === null ? new CSpan($relationship['causeId'])
                    : new CLink($relationship['causeId'], $relationship['causeUrl']);
                $cause_link->setAttribute('title', $relationship['causeName']);
                $name->addItem((new CDiv([_('Cause').': ', $cause_link]))->addClass('cep-member-relation')->addClass(ZBX_STYLE_GREY));
            }
            elseif ($relationship['symptomCount'] !== null) {
                $name->addItem((new CDiv(_('Linked symptoms').': '.$relationship['symptomCount']))
                    ->addClass('cep-member-relation')->addClass(ZBX_STYLE_GREY));
            }
        }
        $row = [$timestamp($member['event_timestamp']), (new CSpan(_($member['problem_status'])))->addClass($status_class),
            (new CCol($name))->addClass(ZBX_STYLE_WORDBREAK),
            $eventid, Changes::button($data, $member), (new CSpan($member['recovery_timestamp'] === null
                ? ($member['problem_status'] === 'Open' ? '—' : _('Unavailable'))
                : $timestamp($member['recovery_timestamp'])))
                ->addClass($member['recovery_timestamp'] === null ? ZBX_STYLE_GREY : ZBX_STYLE_GREEN),
            $expiry($member['expiry']), $remaining($member['expiry'])];
        if ($causal) {
            array_unshift($row, $role_badge($relationship['role'], $relationship['label']));
        }
        if ($data['details']) {
            $row = array_merge($row, [$member['event_index'], $member['event_timestamp'] ?? '—',
                $member['recovery_eventid'] ?? '—', $member['closed_by_cep_ruleid'] ?? '—']);
        }
        $table->addRow($causal ? (new CRow($row))->setAttribute('data-event-id', (string) $member['eventid']) : $row);
    }
    $next = null;
    if ($data['more']) {
        $last = end($data['members']);
        $next = $url(['windowid' => $data['windowid'], 'ruleid' => $data['ruleid'], 'before' => $data['before'],
            'after_index' => $last['event_index'], 'after_eventid' => $last['eventid']]);
    }
    $timeline = (new CDiv())->setId('cep-member-timeline')->addClass('cep-timeline')->setAttribute('hidden', 'hidden')
        ->setAttribute('data-cep-timeline', Timeline::json($data));
    $table_view = (new CDiv([$table, (new CDiv($data['changes_error'] ?: _('Updates from this window\'s CEP rule only. An update groups changes recorded together. ≥ marks a minimum when details are incomplete. None recorded means no retained updates are attributed to this rule.')))->addClass('grey cep-change-caption')]))->setId('cep-member-table');
    // Start with a usable table even without JavaScript. Timeline adds the native tab structure on initialization.
    $snapshot->addItem((new CDiv([$refresh, $table_view, $timeline, $role_summary, $pager(count($data['members']), $data['after_index'] !== '',
        $url(['windowid' => $data['windowid'], 'ruleid' => $data['ruleid'], 'before' => $data['before']]), $next)]))
        ->setId('cep-member-tabs')->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER));
}
$page->addItem((new CSectionCollapsible((new CTableInfo())
    ->addRow([_('Membership'), _('A recovered event can remain a saved member until eviction. Problem status and window membership are separate.')])
    ->addRow([_('Changes'), _('Recorded CEP updates for current members, from this window\'s rule up to the snapshot. Updates with no recorded rule are excluded. An update is one log record; changes are its individual operations. The latest 20 matching updates provide markers and change counts; ≥ indicates a minimum when details are incomplete. Popup counts describe the displayed page and update numbers follow this rule\'s chronological history. Logs have no window ID or admission time, so separate windows of the same rule can share history. Purged history is not reconstructed.')])
    ->addRow([_('Recovery time'), _('Recorded time of the linked recovery event. Unavailable means that recovery history could not be read; no timestamp is inferred from expiry.')])
    ->addRow([_('Sliding windows'), _('Each member age limit is its original event time plus duration. The window estimate is the latest limit across all saved members. New events can move it later; queue order can delay eviction.')])
    ->addRow([_('Cause and symptoms grouping'), _('Members share the current period boundary: saved period start plus duration.')])
    ->addRow([_('Cause and symptom roles'), _('Roles show current saved event relationships, independently of open/recovered status. Cause links and counts include saved members on other pages. If both event history and a saved cause link are unavailable, the role is unavailable. Roles can change after event-rank operations.')])
    ->addRow([_('Estimates'), _('Rule operations can remove members earlier. Worker processing and database updates can delay removal. Due or overdue does not confirm removal or problem closure.')])
    ->addRow([_('Configuration'), _('Duration and capacity are configured values; macros are not resolved here. Times use your frontend timezone.')])
))->setId('cep-window-help')->setToggleLabel(_('About this data'))->setExpanded(false)->addClass(ZBX_STYLE_ROUNDED_SURFACE));
$page->show();
