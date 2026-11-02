<?php declare(strict_types = 0);
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
namespace Modules\CepWindowInspector\Includes;
require_once __DIR__.'/ChangeRepository.php';

/** Native Zabbix icons and hintboxes. All database strings enter HTML through escaped CTag content. */
class Changes {
    /** A log record is an update. Its CEP operation objects are the individual changes. */
    public static function changeCount(?string $details): array {
        $result = ['known' => 0, 'complete' => false];
        try {
            // Object decoding distinguishes an empty JSON object from an empty operation list.
            $value = json_decode($details ?? '', false, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            if (!is_object($value) || !isset($value->cep) || !is_array($value->cep)) return $result;
            $result['complete'] = true;
            foreach ($value->cep as $op) {
                if (is_object($op) && isset($op->operation) && is_int($op->operation) && $op->operation > 0) {
                    $result['known']++;
                }
                else {
                    $result['complete'] = false;
                }
            }
        }
        catch (\JsonException $e) {
            // Unreadable details do not imply zero changes.
        }
        return $result;
    }

    public static function countLabel(string $updates, int $changes, bool $complete): string {
        $update_label = $updates === '1' ? _('1 update') : sprintf(_('%s updates'), $updates);
        if (!$complete) {
            return $update_label.' · '.($changes > 0
                ? ($changes === 1 ? _('≥1 change') : sprintf(_('≥%s changes'), $changes)) : _('changes unavailable'));
        }
        return $update_label.' · '.($changes === 1 ? _('1 change') : sprintf(_('%s changes'), $changes));
    }

    public static function operations(?string $details): array {
        try {
            $value = json_decode($details ?? '', true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            return is_array($value) && isset($value['cep']) && is_array($value['cep'])
                && array_is_list($value['cep']) ? $value['cep'] : [];
        }
        catch (\JsonException $e) {
            return [];
        }
    }

    public static function icon($operation): string {
        if (!is_array($operation)) {
            return ZBX_ICON_CEP;
        }
        if (in_array($operation['operation'] ?? null, [4, 5, 6], true)) {
            $severity = $operation['severity'] ?? null;
            if (is_array($severity) && isset($severity['old'], $severity['new'])
                    && is_int($severity['old']) && is_int($severity['new'])) {
                if ($severity['new'] > $severity['old']) return ZBX_ICON_ARROW_UP_SMALL;
                if ($severity['new'] < $severity['old']) return ZBX_ICON_ARROW_DOWN_SMALL;
            }
        }
        return match ($operation['operation'] ?? null) {
            7 => ZBX_ICON_EYE_OFF, 8 => ZBX_ICON_EYE, default => ZBX_ICON_CEP
        };
    }

    public static function markerIcon(?string $details): string {
        $ops = self::operations($details);
        return count($ops) === 1 ? self::icon($ops[0]) : ZBX_ICON_CEP;
    }

    private static function value($value): string {
        if (is_string($value) || is_int($value)) return (string) $value;
        if (is_array($value) && array_key_exists('new', $value)) {
            return (array_key_exists('old', $value) ? self::value($value['old']) : _('Not recorded'))
                .' → '.self::value($value['new']);
        }
        return _('Not recorded');
    }

    public static function description($op): string {
        if (!is_array($op) || !isset($op['operation']) || !is_int($op['operation'])) {
            return _('Change details unavailable.');
        }
        $type = $op['operation'];
        if (in_array($type, [4, 5, 6], true)) {
            $s = $op['severity'] ?? null;
            if (!is_array($s) || !isset($s['new']) || !is_int($s['new'])) return _('Severity: details unavailable.');
            return _('Severity').': '.(isset($s['old']) && is_int($s['old'])
                ? \CSeverityHelper::getName($s['old']) : _('Not recorded')).' → '.\CSeverityHelper::getName($s['new']);
        }
        if ($type === 1) return _('Event name').': '.self::value($op['name'] ?? null);
        if ($type >= 11 && $type <= 17) {
            $labels = [11 => _('Tag added'), 12 => _('Tag set'), 13 => _('Tag value changed'),
                14 => _('Tag value increased'), 15 => _('Tag value decreased'), 16 => _('Tag renamed'), 17 => _('Tag removed')];
            $tag = is_array($op['tag'] ?? null) ? $op['tag'] : [];
            return $labels[$type].': '.self::value($tag['tag'] ?? null)
                .($type === 16 ? '' : ' = '.self::value($tag['value'] ?? null));
        }
        if ($type === 7) {
            $until = $op['suppress']['until'] ?? null;
            return _('Suppressed until').': '.(is_int($until)
                ? ($until === 0 ? _('Indefinitely') : \zbx_date2str(DATE_TIME_FORMAT_SECONDS, $until)) : _('Not recorded'));
        }
        if ($type === 19) return _('Set as symptom').'; '._('Cause event ID').': '.self::value($op['cause']['eventid'] ?? null);
        return match ($type) {
            2 => _('Event closed'), 3 => _('Event discarded'), 8 => _('Unsuppressed'),
            9 => _('First event cloned'), 10 => _('Last event cloned'), 18 => _('Window closed'),
            default => sprintf(_('Unknown operation (%s)'), $type)
        };
    }

    public static function request(string $windowid, string $eventid, int $snapshot): array {
        return ['action' => 'cep.window.changes', 'data' => ['windowid' => $windowid,
            'eventid' => $eventid, 'snapshot' => (string) $snapshot]];
    }

    public static function button(array $data, array $member) {
        $summary = $data['changes'][(string) $member['eventid']] ?? null;
        if ($summary === null || $summary['count'] === null) {
            return (new \CSpan(_('Unavailable')))->addClass(ZBX_STYLE_GREY);
        }
        if ($summary['count'] === '0') {
            return (new \CSpan(_('None recorded')))->addClass(ZBX_STYLE_GREY);
        }
        $label = $summary['label'];
        return (new \CButton('cep-changes', [(new \CSpan())->addClass(ZBX_ICON_CEP)->setAttribute('aria-hidden', 'true'), $label]))
            ->removeId()->setAttribute('data-cep-change', '1')->addClass('btn-link cep-change-count')->setAttribute('type', 'button')
            ->setAttribute('aria-label', $label.'; '._('Event ID').': '.$member['eventid'])
            ->setAjaxHint(self::request($data['windowid'], (string) $member['eventid'], $data['sampled_at']),
                'cep-change-hint', true, 'max-width: min(760px, calc(100vw - 32px));')
            ->setAttribute('data-hintbox', '0');
    }

    public static function popup(array $history, array $request): string {
        $heading = (new \CTag('h4', true, _('Change history').' · '._('Event ID').' '.$request['eventid']))
            ->setAttribute('tabindex', '-1')->addClass('cep-change-heading');
        $table = (new \CTableInfo())->setHeader([_('Time'), _('Rule'), _('Changes')])->addClass('cep-change-table');
        $known = 0;
        $complete = true;
        foreach ($history['rows'] as $index => $row) {
            $count = self::changeCount($row['details']);
            $known += $count['known'];
            $complete = $complete && $count['complete'];
            $rule = $row['rule_name'] ?? _('Rule unavailable');
            if ($row['rule_name'] !== null && Repository::validId((string) $row['cep_ruleid'], false)) {
                $rule = new \CLink($rule, (new \CUrl('zabbix.php'))->setArgument('action', 'popup')
                    ->setArgument('popup', 'ceprule.edit')->setArgument('cepruleid', (string) $row['cep_ruleid']));
            }
            $items = [];
            foreach (self::operations($row['details']) as $op) {
                $items[] = (new \CDiv([(new \CSpan())->addClass(self::icon($op))->setAttribute('aria-hidden', 'true'),
                    new \CSpan(self::description($op))]))->addClass('cep-change-operation');
            }
            if (!$items) $items[] = new \CSpan($count['complete'] ? _('No operations recorded.') : _('Change details unavailable.'));
            $time = [new \CDiv(\zbx_date2str(DATE_TIME_FORMAT_SECONDS, (int) $row['clock'])),
                (new \CDiv(sprintf(_('Update %s'), $history['offset'] + $index + 1)))
                    ->addClass('grey cep-change-update-number')];
            $tr = (new \CRow([$time,
                (new \CDiv([(new \CSpan())->addClass(ZBX_ICON_CEP)->setAttribute('aria-hidden', 'true'), $rule]))
                    ->addClass('cep-change-rule'), new \CCol($items)]))
                ->setAttribute('data-change-id', (string) $row['acknowledgeid']);
            if (($request['focus'] ?? '') === (string) $row['acknowledgeid']) $tr->addClass('cep-change-selected');
            $table->addRow($tr);
        }
        $nav = (new \CDiv())->addClass('cep-change-pagination');
        if ($history['rows']) {
            foreach (['first' => _('First'), 'previous' => _('Previous'), 'next' => _('Next')] as $direction => $label) {
                if (!$history['has'][$direction === 'first' ? 'previous' : $direction]) continue;
                $data = $request;
                unset($data['focus']);
                $data['direction'] = $direction;
                $data['anchor'] = $direction === 'first' ? '' : (string) $history['rows'][
                    $direction === 'previous' ? 0 : count($history['rows']) - 1]['acknowledgeid'];
                $nav->addItem((new \CButton('cep-history-page', $label))->removeId()->setAttribute('type', 'button')
                    ->setAttribute('data-cep-history-page', $data));
            }
        }
        $paged = $history['has']['previous'] || $history['has']['next'];
        $summary = ($paged ? _('On this page').': ' : '')
            .self::countLabel((string) count($history['rows']), $known, $complete);
        $count_note = $complete ? null : (new \CDiv(_('Some update details could not be read; the change count may be incomplete.')))
            ->addClass('grey cep-change-caption');
        $note = _('Updates from this window\'s CEP rule only. History cannot distinguish separate windows of the same rule.');
        return (new \CDiv([$heading, (new \CDiv($history['name']))->addClass('cep-change-event'),
            (new \CDiv($summary))->addClass('cep-change-total'), $count_note,
            (new \CDiv(_('Oldest first').' · '._('Snapshot').': '
                .\zbx_date2str(DATE_TIME_FORMAT_SECONDS, (int) $request['snapshot'])))->addClass('grey cep-change-caption'),
            $table, $nav, (new \CDiv($note))->addClass('grey cep-change-caption'),
            (new \CDiv())->addClass('cep-change-feedback')->setAttribute('aria-live', 'polite')]))
            ->addClass('cep-change-content')->toString();
    }
}
