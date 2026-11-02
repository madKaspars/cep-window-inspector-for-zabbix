# Displayed data and limitations

## Using the timeline

The detail page opens on **Table**. Select **Timeline** for the chart. The selected view is retained in the page URL, filter, Refresh button and member cursor links. Returning to All windows and opening another window starts on Table. No preference is written to the database or shared with other Zabbix tabs.

**Fit page** fits the members loaded on the current page, their known recovery timestamps, the snapshot, the whole-window expiry estimate and, where applicable, the current period start. It does not plot unloaded members. Cursor paging still loads at most 100 members per page; a count identifies loaded rows and the separately read saved-member total. Counts may briefly disagree while workers update membership. The whole-window expiry boundary and summary cover all saved members, including unloaded pages.

Zoom and pan change only the time axis. When the chart region has keyboard focus, left/right pan, plus/minus zoom, and Home fits the page. Very short intervals get point markers without exaggerating their duration. Hover or focus a bar for exact timestamps; click a name or bar for details. At narrower viewports the detail panel appears below the chart. The chart can scroll horizontally within its container; the native Zabbix page minimum width still applies.

**Date / time** uses the frontend timezone, regardless of the browser timezone. **Relative to snapshot** shows offsets from the displayed database read time. Calendar-month ticks account for timezone offsets and daylight saving. Configured durations remain elapsed seconds: `365d` means 365 × 86400 seconds.

The snapshot marker and time-left values remain fixed until **Refresh**. There is no background polling or animated countdown implying freshly read data.

## Cause and symptom roles

The Role column and cause summary appear only for cause-and-symptom windows. They reflect the same persisted `event_symptom.cause_eventid` relationship used by native Zabbix event queries. A member with a saved cause link is a symptom; a member with available event history and no saved cause link is a cause. If both event history and a saved cause link are unavailable, its role is unavailable. These are current saved ranks, not a reconstruction of the rank at admission; native event-rank operations can change them.

The summary and linked-symptom counts cover only memberships saved in the selected window, across all pages. They do not count unrelated symptoms elsewhere in Zabbix. A cause outside the window is identified when its event history is available. Missing cause history leaves its ID visible without an invented event link. Several cause groups may exist after rank changes; up to five are summarized, while member rows always show their own direct cause ID. The original membership order and 100-member cursor pagination are preserved.

Counts, relationships and member rows are separate reads and can briefly disagree during worker or rank updates. If the supplementary relationship summary cannot be read, each row still shows the role from its own saved link; unavailable counts are not shown as zero. Causal detail pages use up to four supplementary SELECT queries with bounded metadata results, not per-event queries. Other window types do not query relationships.

## What change history means

Change history comes from CEP update records in `acknowledges` (`action & ZBX_PROBLEM_UPDATE_CEP`, `details`, `cep_ruleid`). The rule column identifies the rule that recorded each update. Its saved ID must match the rule ID of the selected window; history from other rules and updates without a recorded rule are excluded. Timestamps are the log write time, with second precision; equal timestamps are ordered by acknowledgement ID. The original event text and any known before/after values are read from the recorded JSON, not inferred from the event's current state.

These records have **no window ID**, and membership has no recorded admission timestamp. The popup therefore shows retained CEP updates from the selected window's rule for that member event up to the displayed snapshot, including updates that may have happened in another window of the same rule or outside its current membership. It does not claim that the selected window caused every update. Manual problem updates are not included. A rule's current name is shown; its historical name is not recorded here.

**None recorded** means no retained CEP log records were attributed to this window's rule; it does not prove that an event was never changed. Missing event history or a failed history read shows **Unavailable**. Malformed details and deleted rules remain visible as unavailable details/rule instead of silently disappearing. No past history is reconstructed or recorded by this module.

Update counts and marker summaries use one batched SELECT for the current page of at most 100 members, returning at most 2,000 marker records. Individual change counts are calculated from the operation objects in these loaded records. They are exact only if every update from this window's rule for that event is included and every operation entry can be counted; otherwise ≥ identifies a lower bound. Popup operation counts apply to the displayed page, and a separate scalar COUNT supplies chronological update numbers. The database must still count and rank the retained CEP logs matching this window's rule for those members; work scales with their retained history, not only window duration. Popup reads use the event ID, the saved window's rule ID and chronological cursors. This was not load-tested against a production MySQL/PostgreSQL database.

The snapshot time is an upper bound, not a database transaction snapshot. Counts, membership and popup reads can differ if logs are inserted or removed between reads. Refresh the window for a new snapshot. Timeline markers show the latest 20 updates from this window's rule; older retained entries remain accessible through the count link. Popup history requires JavaScript.

## What the bars mean

- Bars start at original event time, not admission time. The inspected membership schema does not record exact entry times.
- Endpoints are estimates from current configuration and saved timestamps. Solid means elapsed time up to the snapshot or age limit; dashed means estimated future retention. Blue precedes recorded recovery; green follows it. A white diamond inside the bar marks the recovery event's recorded time, independently of the expiry estimate. Missing recovery history leaves a green status/bar with an unavailable-time explanation, without inventing a transition.
- Simple, tag-correlation and pattern-match age limits use original event time plus duration. Cause-and-symptom members share the saved current period start plus duration. A dotted period-start line and detail fields identify that boundary.
- Due/overdue does not confirm eviction or problem recovery. Operations can remove members earlier; queue order, worker scheduling and database updates can delay removal. Recovered problems may remain saved members.
- Unknown event times produce an explanation instead of an invented bar. If a causal deadline is known but history is missing, only that deadline is marked. Unresolved macros prevent a guessed endpoint.
- This is **current saved membership**, not historical playback. Removed memberships are deleted in the inspected schema. No recorder is added.

## Access and compatibility

Menu and controller remain Super-admin-only because direct SQL does not apply ordinary API host permission filtering. All module data queries remain SELECTs. Event/rule links use native Zabbix pages and their permissions.

Tested with **Zabbix 8.0.0rc3**. Automated validation uses the official RC3 release, commit `090f06dc325f1629c5cdc2bb3354a184e15e55d4`, including its native module loader, PHP HTML classes and helpers. Required tables: cep_window, cep_window_event, cep_rule, cep_rule_window, events, event_recovery and event_symptom, including event_recovery.cep_ruleid and event_symptom.cause_eventid. Change history additionally requires acknowledges.acknowledgeid, eventid, clock, action, details and cep_ruleid, plus the native CEP icon and update constant. The summary query uses SQL window functions, supported by current Zabbix database versions; it was exercised through a local SQLite adapter. A CCepRuleHelper class alone does not establish schema compatibility. Development schemas may change across CEP builds.

Requires a current Zabbix-supported browser with JavaScript, SVG, Intl timezone formatting and ResizeObserver for the timeline. The server-rendered table remains the fallback. No core frontend changes are needed.

