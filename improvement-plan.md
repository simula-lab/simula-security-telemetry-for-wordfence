# Improvement Plan for `simula-wordfence-grafana-integration.php`

This plan is based on a review of the current plugin with focus on modularity, expandability, clarity, redundancy, and efficiency.

The goal is to improve the code without forcing a risky rewrite. Each phase is intentionally small enough to implement and verify independently.

## Principles

- Prefer small refactors over architecture churn.
- Keep behavior stable while reducing duplication.
- Improve boundaries before adding new features.
- Make future metric or exporter additions cheaper.

## Phase 1: Extract Shared Helpers

### Goal

Remove duplicated low-level helpers and centralize common logic used by metrics and incident export.

### Changes

1. Add a small shared internal utility class for:
   - SQL identifier quoting
   - column resolution from candidate lists
   - common string cleanup helpers if still shared after review

2. Replace duplicated helper implementations currently split across:
   - `Simula_Wordfence_Grafana_Wordfence`
   - `Simula_Wordfence_Grafana_Incidents`

3. Add one reusable path validator helper for file settings so:
   - `.prom` validation
   - `.log` / `.jsonl` validation
   share the same absolute-path and extension-check flow.

### Why first

This is low risk, reduces immediate redundancy, and makes later refactors simpler.

### Deliverable

- No behavior change
- Fewer duplicated helpers
- Simpler sanitization methods

## Phase 2: Split the Wordfence Access Layer

### Goal

Reduce the size and responsibility of `Simula_Wordfence_Grafana_Wordfence`.

### Changes

1. Keep one class focused on schema and table discovery:
   - hits table resolution
   - table existence
   - column metadata
   - candidate-column lookup

2. Move query-specific collector logic into a separate class, for example:
   - blocked-request query helpers
   - window-count SQL builders
   - lockout collectors
   - two-factor collectors
   - scan issue collectors

3. Keep the current public behavior stable while narrowing each class responsibility.

### Why second

The current class is the biggest structural bottleneck. Splitting it improves modularity and makes new metrics easier to place.

### Deliverable

- One schema/discovery class
- One query/collector class
- Clearer ownership of database-related logic

## Phase 3: Break Up `export_metrics()`

### Goal

Turn the metrics export path into smaller, easier-to-read units.

### Changes

1. Split `Simula_Wordfence_Grafana_Service::export_metrics()` into three stages:
   - collect source data
   - build metric lines
   - persist output and state

2. Extract metric-specific renderers into private methods grouped by concern, for example:
   - core export status metrics
   - blocked-event metrics
   - login/rate-limit/brute-force metrics
   - lockout/two-factor metrics
   - scan metrics

3. Keep one thin orchestrator method that coordinates the flow.

### Why third

This is the biggest clarity improvement and makes the file much easier to navigate.

### Deliverable

- Smaller methods
- Easier code review
- Lower risk when adding or changing a single metric family

## Phase 4: Make Metric Rendering More Declarative

### Goal

Reduce repetitive HELP/TYPE/label-writing boilerplate.

### Changes

1. Introduce a small internal metric line builder or rendering helper.

2. Standardize how gauge/counter families are emitted.

3. Where practical, represent metric family metadata in one place so code does not repeat:
   - help text
   - metric type
   - common labels

4. Keep special-case metrics as custom renderers where needed.

### Why fourth

This improves expandability, but it should come after the metrics exporter is already broken into smaller pieces.

### Deliverable

- Less repeated string assembly
- Cleaner metric output code
- Lower chance of inconsistent HELP/TYPE formatting

## Phase 5: Consolidate State Persistence

### Goal

Reduce repeated `update_option()` calls during a single export run.

### Changes

1. Review every state write in:
   - metrics output
   - incident export
   - combined result merge

2. Move toward one state accumulator per run.

3. Persist once at the end of the export flow where practical.

4. Keep early failure handling explicit, but avoid unnecessary writes on success paths.

### Why fifth

This is mostly an efficiency and consistency improvement. It is easier after service responsibilities are cleaner.

### Deliverable

- Fewer option writes per run
- More predictable state transitions
- Cleaner failure/success bookkeeping

## Phase 6: Clarify Exporter Gating

### Goal

Make it explicit whether incident export is subordinate to the global exporter toggle or independently controlled.

### Changes

1. Decide the intended behavior:
   - `enabled` disables everything
   - or metrics and incidents are independently switchable

2. Update naming and control flow to match that choice.

3. Reflect the behavior clearly in:
   - admin descriptions
   - export orchestration
   - manual export behavior

### Why sixth

This is a product-behavior clarification more than a pure refactor. It should follow the structural cleanup.

### Deliverable

- No ambiguity in toggle behavior
- Easier future expansion to additional exporters

## Phase 7: Clean Up Admin Rendering

### Goal

Improve maintainability of the settings page without changing the UI.

### Changes

1. Split `settings_page()` into smaller methods:
   - request handling
   - metrics settings section
   - incident settings section
   - current state section
   - sample output section

2. Keep markup inline if desired, but isolate sections into named render methods.

3. Reuse small helpers for repetitive table rows where useful.

### Why seventh

The admin page works, but it is harder to read and maintain than the rest of the plugin.

### Deliverable

- Smaller admin methods
- Clearer page structure
- Easier future settings additions

## Suggested Implementation Order

1. Phase 1
2. Phase 2
3. Phase 3
4. Phase 5
5. Phase 4
6. Phase 6
7. Phase 7

This order prioritizes low-risk cleanup first, then structural changes, then ergonomics.

## Verification Checklist Per Phase

- Run `php -l simula-wordfence-grafana-integration.php`
- Confirm plugin activation still works
- Confirm manual export still works
- Confirm metrics file output is unchanged unless intentionally modified
- Confirm incident log export still appends correctly
- Confirm admin settings save and reload correctly

## Definition of Done

The improvement work is complete when:

- shared helpers are centralized
- Wordfence schema logic and query logic are separated
- metrics export is split into smaller units
- state updates are simpler and less repetitive
- exporter behavior is easier to extend without adding more large methods
