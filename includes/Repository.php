<?php declare(strict_types = 0);
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
namespace Modules\CepWindowInspector\Includes;

/** Read-only database access. Controller must authorize the user before calling. */
class Repository {
    public const PAGE_SIZE = 100;

    public static function validId(string $value, bool $allow_empty = true): bool {
        if ($value === '') {
            return $allow_empty;
        }
        return preg_match('/^[1-9][0-9]{0,18}$/D', $value) === 1
            && (strlen($value) < 19 || strcmp($value, '9223372036854775807') <= 0);
    }

    private static function id(string $value): string {
        if (!self::validId($value, false)) {
            throw new \InvalidArgumentException('Invalid identifier.');
        }
        return \zbx_dbstr($value);
    }

    private static function rows(string $sql, ?int $limit = null): array {
        $result = \DBselect($sql, $limit);
        if ($result === false) {
            throw new \RuntimeException('Cannot read CEP data. Check the feature-branch schema and database access.');
        }
        $rows = [];
        // Preserve SQL NULLs for missing history, recovery IDs and optional joins.
        while ($row = \DBfetch($result, false)) {
            $rows[] = $row;
        }
        return $rows;
    }

    private static function windowSql(): string {
        return 'SELECT w.cep_windowid,w.cep_ruleid,w.group_by,w.groupid,w.hostid,'
            .'w.tags,w.tags_value,w.created_at,r.name AS rule_name,r.status AS rule_status,'
            .'rw.type AS window_type,rw.duration,rw.capacity,h.host AS host_name,g.name AS host_group_name'
            .' FROM cep_window w'
            .' LEFT JOIN cep_rule r ON r.cep_ruleid=w.cep_ruleid'
            .' LEFT JOIN cep_rule_window rw ON rw.cep_ruleid=w.cep_ruleid'
            .' LEFT JOIN hosts h ON h.hostid=w.hostid'
            .' LEFT JOIN hstgrp g ON g.groupid=w.groupid';
    }

    public static function windows(string $ruleid, string $before): array {
        $where = [];
        if ($ruleid !== '') {
            $where[] = 'w.cep_ruleid='.self::id($ruleid);
        }
        if ($before !== '') {
            $where[] = 'w.cep_windowid<'.self::id($before);
        }
        $rows = self::rows(self::windowSql().($where ? ' WHERE '.implode(' AND ', $where) : '')
            .' ORDER BY w.cep_windowid DESC', self::PAGE_SIZE + 1);
        $more = count($rows) > self::PAGE_SIZE;
        if ($more) {
            array_pop($rows);
        }
        $counts = [];
        if ($rows) {
            $ids = array_map(static function(array $row): string {
                return self::id($row['cep_windowid']);
            }, $rows);
            foreach (self::rows('SELECT cep_windowid,COUNT(*) AS members FROM cep_window_event'
                .' WHERE cep_windowid IN ('.implode(',', $ids).') GROUP BY cep_windowid') as $row) {
                $counts[$row['cep_windowid']] = $row['members'];
            }
        }
        foreach ($rows as &$row) {
            $row['members'] = $counts[$row['cep_windowid']] ?? '0';
        }
        unset($row);
        return ['rows' => $rows, 'more' => $more];
    }

    public static function window(string $windowid): ?array {
        $rows = self::rows(self::windowSql().' WHERE w.cep_windowid='.self::id($windowid), 1);
        return $rows ? $rows[0] : null;
    }

    public static function members(string $windowid, string $after_index, string $after_eventid, bool $causal = false): array {
        // Same joins and event status semantics as the requested query.
        $sql = 'SELECT we.event_index,we.eventid,e.name,e.clock AS event_timestamp,'
            .'e.source,e.object,e.objectid,'
            ."CASE WHEN e.eventid IS NULL THEN 'Event history unavailable'"
            ." WHEN er.r_eventid IS NULL THEN 'Open' ELSE 'Recovered / closed' END AS problem_status,"
            .'er.r_eventid AS recovery_eventid,er.cep_ruleid AS closed_by_cep_ruleid,'
            .'re.clock AS recovery_timestamp'
            .($causal ? ',es.cause_eventid' : '')
            .' FROM cep_window_event we'
            .' LEFT JOIN events e ON e.eventid=we.eventid'
            .' LEFT JOIN event_recovery er ON er.eventid=we.eventid'
            .' LEFT JOIN events re ON re.eventid=er.r_eventid'
            .($causal ? ' LEFT JOIN event_symptom es ON es.eventid=we.eventid' : '')
            .' WHERE we.cep_windowid='.self::id($windowid);
        if ($after_index !== '') {
            // Cursor includes eventid so duplicate indexes cannot skip members.
            if ($after_index !== '0') {
                self::id($after_index);
            }
            $index = \zbx_dbstr($after_index);
            $sql .= ' AND (we.event_index>'.$index.' OR (we.event_index='.$index
                .' AND we.eventid>'.self::id($after_eventid).'))';
        }
        $rows = self::rows($sql.' ORDER BY we.event_index,we.eventid', self::PAGE_SIZE + 1);
        $more = count($rows) > self::PAGE_SIZE;
        if ($more) {
            array_pop($rows);
        }
        return ['rows' => $rows, 'more' => $more];
    }

    /** Current saved ranks, scoped to one window. No inference from timestamps or cursor position. */
    public static function relationships(string $windowid, array $members): array {
        $id = self::id($windowid);
        $linked = 'es.cause_eventid IS NOT NULL AND es.cause_eventid<>0';
        $cause = 'CASE WHEN '.$linked.' THEN es.cause_eventid ELSE e.eventid END';
        $from = ' FROM cep_window_event we LEFT JOIN events e ON e.eventid=we.eventid'
            .' LEFT JOIN event_symptom es ON es.eventid=we.eventid WHERE we.cep_windowid='.$id;
        $summary = self::rows('SELECT COUNT(DISTINCT '.$cause.') AS cause_groups,'
            .'COALESCE(SUM(CASE WHEN '.$linked.' THEN 0 WHEN e.eventid IS NOT NULL THEN 1 ELSE 0 END),0) AS causes,'
            .'COALESCE(SUM(CASE WHEN '.$linked.' THEN 1 ELSE 0 END),0) AS symptoms,'
            .'COALESCE(SUM(CASE WHEN '.$linked.' THEN 0 WHEN e.eventid IS NULL THEN 1 ELSE 0 END),0) AS unknown'
            .$from)[0];
        // Limit the summary to five cause groups; totals still cover all saved members.
        $groups = self::rows('SELECT '.$cause.' AS eventid'.$from.' AND ('.$cause.') IS NOT NULL'
            .' GROUP BY '.$cause.' ORDER BY '.$cause, 5);
        $ids = array_column($groups, 'eventid');
        foreach ($members as $member) {
            $ids[] = $member['eventid'];
            if ($member['cause_eventid'] !== null && (string) $member['cause_eventid'] !== '0') {
                $ids[] = $member['cause_eventid'];
            }
        }
        $ids = array_values(array_unique(array_map('strval', $ids)));
        $events = $symptom_counts = [];
        if ($ids) {
            $in = implode(',', array_map([self::class, 'id'], $ids));
            // At most 205 IDs: five groups plus the 100 displayed members and their direct causes.
            foreach (self::rows('SELECT e.eventid,e.name,e.clock AS event_timestamp,e.source,e.object,e.objectid,'
                .'we.eventid AS member_eventid FROM events e LEFT JOIN cep_window_event we'
                .' ON we.eventid=e.eventid AND we.cep_windowid='.$id.' WHERE e.eventid IN ('.$in.')') as $event) {
                $events[(string) $event['eventid']] = $event;
            }
            foreach (self::rows('SELECT es.cause_eventid,COUNT(*) AS symptoms FROM cep_window_event we'
                .' JOIN event_symptom es ON es.eventid=we.eventid WHERE we.cep_windowid='.$id
                .' AND es.cause_eventid IN ('.$in.') GROUP BY es.cause_eventid') as $row) {
                $symptom_counts[(string) $row['cause_eventid']] = (int) $row['symptoms'];
            }
        }
        return ['summary' => $summary, 'groups' => $groups, 'events' => $events, 'symptom_counts' => $symptom_counts];
    }

    public static function counts(string $windowid): array {
        $rows = self::rows('SELECT COUNT(*) AS members,'
            .'COALESCE(SUM(CASE WHEN e.eventid IS NOT NULL AND er.r_eventid IS NULL THEN 1 ELSE 0 END),0) AS open_count,'
            .'COALESCE(SUM(CASE WHEN e.eventid IS NOT NULL AND er.r_eventid IS NOT NULL THEN 1 ELSE 0 END),0) AS closed_count,'
            .'COALESCE(SUM(CASE WHEN e.eventid IS NULL THEN 1 ELSE 0 END),0) AS unavailable_count'
            .' FROM cep_window_event we LEFT JOIN events e ON e.eventid=we.eventid'
            .' LEFT JOIN event_recovery er ON er.eventid=we.eventid'
            .' WHERE we.cep_windowid='.self::id($windowid));
        return $rows[0];
    }

    public static function expirySummaries(array $windowids): array {
        if (!$windowids) {
            return [];
        }
        $ids = array_map([self::class, 'id'], $windowids);
        $summaries = array_fill_keys($windowids, [
            'members' => '0', 'known_clocks' => '0', 'latest_event_timestamp' => null
        ]);
        // One bounded batch for the displayed windows, including members on every member page.
        // Recovered events still count if their saved membership exists.
        foreach (self::rows('SELECT we.cep_windowid,COUNT(*) AS members,COUNT(e.clock) AS known_clocks,'
            .'MAX(e.clock) AS latest_event_timestamp'
            .' FROM cep_window_event we LEFT JOIN events e ON e.eventid=we.eventid'
            .' WHERE we.cep_windowid IN ('.implode(',', $ids).') GROUP BY we.cep_windowid') as $row) {
            $summaries[$row['cep_windowid']] = $row;
        }
        return $summaries;
    }
}
