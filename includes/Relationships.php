<?php declare(strict_types = 0);
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
namespace Modules\CepWindowInspector\Includes;

/** Presentation of the same saved event_symptom links used by Zabbix event.get. */
class Relationships {
    public static function eventUrl(?array $event): ?string {
        if ($event === null || $event['event_timestamp'] === null
                || (int) $event['source'] !== EVENT_SOURCE_TRIGGERS || (int) $event['object'] !== EVENT_OBJECT_TRIGGER) {
            return null;
        }
        return 'tr_events.php?'.http_build_query(['triggerid' => (string) $event['objectid'],
            'eventid' => (string) $event['eventid']], '', '&');
    }

    public static function member(array $member, ?array $context): array {
        $causeid = isset($member['cause_eventid']) && (string) $member['cause_eventid'] !== '0'
            ? (string) $member['cause_eventid'] : null;
        $role = $causeid !== null ? 'symptom'
            : (array_key_exists('cause_eventid', $member) && $member['event_timestamp'] !== null ? 'cause' : 'unknown');
        $cause = $causeid === null ? null : ($context['events'][$causeid] ?? null);
        return [
            'role' => $role,
            'label' => $role === 'cause' ? _('Cause') : ($role === 'symptom' ? _('Symptom') : _('Unavailable')),
            'causeId' => $causeid,
            'causeName' => $causeid === null ? null : ($cause['name'] ?? _('Event history unavailable')),
            'causeUrl' => self::eventUrl($cause),
            'causeInWindow' => $cause === null ? null : $cause['member_eventid'] !== null,
            'symptomCount' => $role === 'cause' && $context !== null
                ? ($context['symptom_counts'][(string) $member['eventid']] ?? 0) : null
        ];
    }
}
