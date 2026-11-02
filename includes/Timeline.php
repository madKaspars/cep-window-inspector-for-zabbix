<?php declare(strict_types = 0);
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
namespace Modules\CepWindowInspector\Includes;
require_once __DIR__.'/Relationships.php';

/** Presentation only. Uses the same bounded, authorized member page as the table. */
class Timeline {
    public static function json(array $data): string {
        $labels = [];
        foreach ([
            'Timeline', 'Table', 'Member events', 'Role', 'Cause', 'Symptom', 'Linked symptoms',
            'Cause is outside this window', 'Role unavailable', 'Fit page', 'Zoom in', 'Zoom out', 'Earlier', 'Later',
            'Date / time', 'Relative to snapshot', 'Time axis', 'Snapshot', 'Window expiry (estimate)', 'Event', 'Time left',
            'Event time', 'Estimated expiry', 'Shared period boundary', 'Current period start',
            'Selected event', 'Event ID', 'Event index', 'Problem status', 'Saved membership', 'Present',
            'Admission time', 'Not recorded', 'Open event', 'Close details', 'Unavailable',
            'Event history unavailable', 'Event age at snapshot', 'Estimated remaining retention',
            'Recovery time', 'Recovery time unavailable', 'Recovered', 'Before recovery / still open',
            'After recovery', 'Recorded recovery time', 'Changes', 'None recorded',
            '1 update', '%s updates', '1 change', '%s changes', '≥1 change', '≥%s changes', 'changes unavailable',
            'Recorded CEP update', 'Show CEP updates', 'On', 'Off', 'Latest 20 updates loaded; open history for all retained updates.',
            'Zoom in for crowded markers. Updates outside bars remain available through the history counts.',
            'Update markers show this window\'s CEP rule only. One update can contain several operations.',
            'Recovered events stay green when recovery history is unavailable; no recovery marker is inferred.',
            'No saved members on this page.', 'Outside visible range', 'Event time unavailable',
            'Estimated expiry unavailable', 'Event time is after the estimated boundary',
            'Keyboard: left/right to pan, plus/minus to zoom, Home to fit this page.',
            'Bars start at original event time, not admission time. Expiry is an estimate, not confirmed eviction or recovery.',
            'All members share the current period boundary. Bars show original event times, not recorded admission times.',
            'Timeline unavailable. The table remains available.'
        ] as $text) {
            $labels[$text] = _($text);
        }
        $rows = [];
        foreach ($data['members'] as $member) {
            $link = null;
            if ($member['event_timestamp'] !== null && (int) $member['source'] === EVENT_SOURCE_TRIGGERS
                    && (int) $member['object'] === EVENT_OBJECT_TRIGGER) {
                $link = 'tr_events.php?'.http_build_query(['triggerid' => (string) $member['objectid'],
                    'eventid' => (string) $member['eventid']], '', '&');
            }
            $clock = $member['event_timestamp'] === null ? null : (int) $member['event_timestamp'];
            $recovery = $member['recovery_timestamp'] === null ? null : (int) $member['recovery_timestamp'];
            $at = $member['expiry']['at'];
            $rows[] = [
                'id' => (string) $member['eventid'], 'index' => (string) $member['event_index'],
                'name' => $member['name'] ?? _('Event history unavailable'),
                'clock' => $clock, 'expiry' => $at, 'reason' => $member['expiry']['reason'],
                'recovery' => $recovery,
                'recoveryText' => $recovery === null
                    ? ($member['problem_status'] === 'Open' ? '—' : _('Unavailable'))
                    : \zbx_date2str(DATE_TIME_FORMAT_SECONDS, $recovery),
                'clockText' => $clock === null ? _('Unavailable') : \zbx_date2str(DATE_TIME_FORMAT_SECONDS, $clock),
                'expiryText' => $at === null ? $member['expiry']['reason'] : \zbx_date2str(DATE_TIME_FORMAT_SECONDS, $at),
                'remaining' => Timing::remaining($at, $data['sampled_at']),
                'status' => _($member['problem_status']),
                'state' => $member['problem_status'] === 'Open' ? 'open'
                    : ($member['problem_status'] === 'Recovered / closed' ? 'closed' : 'unknown'),
                'url' => $link,
                'changes' => $data['changes'][(string) $member['eventid']] ?? ['count' => null, 'markers' => []],
                'relationship' => (int) $data['window']['window_type'] === 2
                    ? Relationships::member($member, $data['relationships']) : null
            ];
        }
        return json_encode([
            'rows' => $rows, 'sampledAt' => $data['sampled_at'], 'timezone' => date_default_timezone_get(),
            // The existing whole-window summary includes members on every page, not just these rows.
            'windowExpiry' => $data['window']['expiry']['at'],
            'windowExpiryText' => $data['window']['expiry']['at'] === null
                ? $data['window']['expiry']['reason']
                : \zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['window']['expiry']['at']),
            'duration' => Timing::durationSeconds($data['window']['duration']),
            'periodStart' => (int) $data['window']['window_type'] === 2 ? (int) $data['window']['created_at'] : null,
            'sharedBoundary' => (int) $data['window']['window_type'] === 2,
            'causal' => (int) $data['window']['window_type'] === 2,
            'view' => $data['view'], 'windowid' => (string) $data['windowid'],
            'technical' => $data['details'], 'labels' => $labels,
            'countText' => sprintf(_('%1$s loaded on this page · %2$s saved members (separate reads)'),
                count($rows), $data['counts']['members'])
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
