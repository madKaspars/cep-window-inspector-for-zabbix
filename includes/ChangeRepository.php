<?php declare(strict_types = 0);
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
namespace Modules\CepWindowInspector\Includes;
require_once __DIR__.'/Repository.php';

/** CEP acknowledgements belong to events, not to a particular window instance. SELECT only. */
class ChangeRepository {
    public const MARKER_LIMIT = 20;
    public const PAGE_SIZE = 50;

    private static function id(string $id): string {
        if (!Repository::validId($id, false)) {
            throw new \InvalidArgumentException('Invalid identifier.');
        }
        return \zbx_dbstr($id);
    }

    private static function rows(string $sql, ?int $limit = null): array {
        $result = \DBselect($sql, $limit);
        if ($result === false) {
            throw new \RuntimeException(_('Change history is temporarily unavailable.'));
        }
        $rows = [];
        while ($row = \DBfetch($result, false)) {
            $rows[] = $row;
        }
        return $rows;
    }

    private static function filter(int $snapshot, string $ruleid): string {
        if ($snapshot < 1 || $snapshot > time()) {
            throw new \InvalidArgumentException('Invalid snapshot.');
        }
        return '(a.action & '.ZBX_PROBLEM_UPDATE_CEP.')<>0 AND a.clock<='.$snapshot
            .' AND a.cep_ruleid='.self::id($ruleid);
    }

    /** At most 100 members x 20 records. Operation totals are minimums when details are incomplete. */
    public static function summaries(array $members, int $snapshot, string $ruleid): array {
        $result = [];
        if (!$members) {
            return $result;
        }
        if (count($members) > Repository::PAGE_SIZE) {
            throw new \InvalidArgumentException('Member page too large.');
        }
        $ids = [];
        foreach ($members as $member) {
            $id = (string) $member['eventid'];
            $ids[] = self::id($id);
            $result[$id] = ['count' => $member['event_timestamp'] === null ? null : '0',
                'changeCount' => 0, 'changesComplete' => true, 'markers' => []];
        }
        // Supported MySQL 8/PostgreSQL: limit each partition without 100 separate reads.
        $sql = 'SELECT h.acknowledgeid,h.eventid,h.clock,h.details,h.total FROM ('
            .'SELECT a.acknowledgeid,a.eventid,a.clock,a.details,'
            .'COUNT(*) OVER (PARTITION BY a.eventid) AS total,'
            .'ROW_NUMBER() OVER (PARTITION BY a.eventid ORDER BY a.clock DESC,a.acknowledgeid DESC) AS position'
            .' FROM acknowledges a WHERE a.eventid IN ('.implode(',', $ids).') AND '.self::filter($snapshot, $ruleid)
            .') h WHERE h.position<='.self::MARKER_LIMIT.' ORDER BY h.eventid,h.clock,h.acknowledgeid';
        foreach (self::rows($sql) as $row) {
            $id = (string) $row['eventid'];
            $changes = Changes::changeCount($row['details']);
            $result[$id]['count'] = (string) $row['total'];
            $result[$id]['changeCount'] += $changes['known'];
            $result[$id]['changesComplete'] = $result[$id]['changesComplete'] && $changes['complete']
                && (int) $row['total'] <= self::MARKER_LIMIT;
            $result[$id]['markers'][] = ['id' => (string) $row['acknowledgeid'], 'clock' => (int) $row['clock'],
                'icon' => Changes::markerIcon($row['details']),
                'changeCount' => $changes['known'], 'changesComplete' => $changes['complete']];
        }
        foreach ($result as &$summary) {
            $summary['label'] = $summary['count'] === null ? _('Unavailable')
                : Changes::countLabel($summary['count'], $summary['changeCount'], $summary['changesComplete']);
        }
        unset($summary);
        return $result;
    }

    /** Resolve the rule from the saved window; every history read and cursor uses the same rule scope. */
    public static function history(string $windowid, string $eventid, int $snapshot,
            string $direction = 'first', string $anchor = ''): array {
        $event = self::rows('SELECT e.name,w.cep_ruleid FROM cep_window_event we'
            .' JOIN cep_window w ON w.cep_windowid=we.cep_windowid LEFT JOIN events e ON e.eventid=we.eventid'
            .' WHERE we.cep_windowid='.self::id($windowid).' AND we.eventid='.self::id($eventid), 1);
        if (!$event) {
            throw new \RuntimeException(_('This event is no longer a saved member of this window. Refresh the window.'));
        }
        if (!in_array($direction, ['first', 'next', 'previous', 'focus'], true)
                || (($direction === 'first') !== ($anchor === ''))) {
            throw new \InvalidArgumentException('Invalid history cursor.');
        }
        $where = 'a.eventid='.self::id($eventid).' AND '.self::filter($snapshot, (string) $event[0]['cep_ruleid']);
        $cursor = '';
        if ($anchor !== '') {
            $point = self::rows('SELECT a.clock,a.acknowledgeid FROM acknowledges a WHERE '.$where
                .' AND a.acknowledgeid='.self::id($anchor), 1);
            if (!$point) {
                throw new \RuntimeException(_('This history entry is no longer available. Refresh the window.'));
            }
            $operator = $direction === 'previous' ? '<' : '>';
            $clock = (int) $point[0]['clock'];
            if ($direction === 'focus') {
                // Include up to 24 preceding updates so small histories remain complete.
                $preceding = self::rows('SELECT a.clock,a.acknowledgeid FROM acknowledges a WHERE '.$where
                    .' AND (a.clock<'.$clock.' OR (a.clock='.$clock.' AND a.acknowledgeid<='
                    .self::id($anchor).')) ORDER BY a.clock DESC,a.acknowledgeid DESC', 25);
                $start = $preceding[count($preceding) - 1];
                $clock = (int) $start['clock'];
                $anchor = (string) $start['acknowledgeid'];
            }
            $cursor = ' AND (a.clock'.$operator.$clock.' OR (a.clock='.$clock
                .' AND a.acknowledgeid'.$operator.($direction === 'focus' ? '=' : '').self::id($anchor).'))';
        }
        $order = $direction === 'previous' ? ' DESC' : ' ASC';
        $rows = self::rows('SELECT a.acknowledgeid,a.clock,a.details,a.cep_ruleid,r.name AS rule_name'
            .' FROM acknowledges a LEFT JOIN cep_rule r ON r.cep_ruleid=a.cep_ruleid WHERE '.$where.$cursor
            .' ORDER BY a.clock'.$order.',a.acknowledgeid'.$order, self::PAGE_SIZE + 1);
        if (count($rows) > self::PAGE_SIZE) {
            array_pop($rows);
        }
        if ($direction === 'previous') {
            $rows = array_reverse($rows);
        }
        $has = ['previous' => false, 'next' => false];
        $offset = 0;
        if ($rows) {
            // Only a scalar is returned; ordinals count updates, independently of operation counts.
            $first = $rows[0];
            $clock = (int) $first['clock'];
            $offset = (int) self::rows('SELECT COUNT(*) AS previous_updates FROM acknowledges a WHERE '.$where
                .' AND (a.clock<'.$clock.' OR (a.clock='.$clock.' AND a.acknowledgeid<'
                .self::id((string) $first['acknowledgeid']).'))')[0]['previous_updates'];
            $has['previous'] = $offset > 0;
            $last = $rows[count($rows) - 1];
            $clock = (int) $last['clock'];
            $has['next'] = (bool) self::rows('SELECT a.acknowledgeid FROM acknowledges a WHERE '.$where
                .' AND (a.clock>'.$clock.' OR (a.clock='.$clock.' AND a.acknowledgeid>'
                .self::id((string) $last['acknowledgeid']).'))', 1);
        }
        return ['name' => $event[0]['name'] ?? _('Event history unavailable'), 'rows' => $rows, 'has' => $has,
            'offset' => $offset];
    }
}
