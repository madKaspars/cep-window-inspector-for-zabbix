<?php declare(strict_types = 0);
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
namespace Modules\CepWindowInspector\Includes;

/** Estimates from persisted timestamps and the current configured duration. No database writes. */
class Timing {
    public static function durationSeconds(?string $value): ?int {
        // Match the server's zbx_is_time_suffix(): seconds or one of s/m/h/d/w, up to INT_MAX.
        // Do not guess runtime values for user macros, including macro functions.
        if ($value === null || preg_match('/^([0-9]+)([smhdw]?)$/D', $value, $match) !== 1) {
            return null;
        }
        $digits = ltrim($match[1], '0');
        if ($digits === '' || strlen($digits) > 10) {
            return null;
        }
        $factor = ['' => 1, 's' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800][$match[2]];
        $number = (int) $digits;
        return $number > intdiv(2147483647, $factor) ? null : $number * $factor;
    }

    private static function unavailable(string $reason): array {
        return ['at' => null, 'reason' => $reason];
    }

    private static function expiry($clock, int $duration): array {
        if ($clock === null || !ctype_digit((string) $clock)
                || strlen((string) $clock) > strlen((string) PHP_INT_MAX)
                || (strlen((string) $clock) === strlen((string) PHP_INT_MAX)
                    && strcmp((string) $clock, (string) PHP_INT_MAX) > 0)
                || (int) $clock > PHP_INT_MAX - $duration) {
            return self::unavailable(_('Timestamp unavailable'));
        }
        return ['at' => (int) $clock + $duration, 'reason' => ''];
    }

    public static function forEvent(array $window, array $member): array {
        $duration = self::durationSeconds($window['duration']);
        if ($duration === null) {
            return self::unavailable(_('Duration unresolved or invalid'));
        }
        switch ((int) $window['window_type']) {
            case 2: // Cause and symptoms: all members share the current period boundary.
                return self::expiry($window['created_at'], $duration);
            case 1: // Simple, tag correlation and pattern match evict by original event.clock.
            case 3:
            case 4:
                return $member['event_timestamp'] === null
                    ? self::unavailable(_('Event history unavailable'))
                    : self::expiry($member['event_timestamp'], $duration);
        }
        return self::unavailable(_('Unknown window type'));
    }

    public static function forWindow(array $window, ?array $summary): array {
        $duration = self::durationSeconds($window['duration']);
        if ($duration === null) {
            return self::unavailable(_('Duration unresolved or invalid'));
        }
        switch ((int) $window['window_type']) {
            case 2:
                return self::expiry($window['created_at'], $duration);
            case 1:
            case 3:
            case 4:
                if ($summary === null) {
                    return self::unavailable(_('Membership summary unavailable'));
                }
                if ((int) $summary['members'] === 0) {
                    return self::unavailable(_('No saved members'));
                }
                if ((string) $summary['members'] !== (string) $summary['known_clocks']) {
                    return self::unavailable(_('Some event history is unavailable'));
                }
                // Last current member's age limit, not created_at + duration and not just this page.
                return self::expiry($summary['latest_event_timestamp'], $duration);
        }
        return self::unavailable(_('Unknown window type'));
    }

    public static function remaining(?int $expiry, int $now): string {
        if ($expiry === null) {
            return _('Unavailable');
        }
        $seconds = abs($expiry - $now);
        if ($seconds === 0) {
            return _('Due now');
        }
        $duration = sprintf(_('%dd %02dh %02dm %02ds'), intdiv($seconds, 86400),
            intdiv($seconds % 86400, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        return $expiry < $now ? sprintf(_('Overdue by %s'), $duration) : $duration;
    }
}
