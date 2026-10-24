# Admin Telemetry Dashboard Plan

## Purpose

Add a dedicated **Security Telemetry** page in the WordPress administration
menu. This is an operator-facing overview of the site's current security
posture and exporter health; it is not a configuration screen and must not be
placed under **Settings**.

The dashboard deliberately excludes all Wordfence data: no Wordfence firewall
activity, scan data, incidents, login activity, tables, or configuration is
shown on this page.

## Product decision

The first release presents the current locally collected snapshot. Prometheus
and Grafana remain the systems of record for historical telemetry and graphs.
The plugin will not add a second time-series store in WordPress, embed charts,
or query Prometheus/Grafana from wp-admin.

## Scope

### Separate administration page

- Register a top-level **Security Telemetry** administration-menu page.
- Require the existing `manage_options` capability.
- Keep the existing **Settings > Security Telemetry** page for configuration
  and manual exporter actions.
- Use server-rendered WordPress admin components. Do not introduce a frontend
  charting dependency for the initial release.

### Current-state cards

Display four concise, accessible cards with a timestamp, source-status state,
and explanation for each degraded result:

| Card | Current-state data |
| --- | --- |
| Runtime posture | PHP version/support status, SAPI, selected safe settings, allowlisted extensions, and OPcache posture when available. |
| WordPress posture | Core/update posture, plugin and theme update counts, debug/file-edit controls, and administrator security posture. |
| Change signals | WordPress-side drift and IoC metrics already collected by the plugin, such as plugin/theme changes, administrator changes, suspicious content signals, and executable files in uploads. |
| Exporter health | Last fast/slow export, success state, freshness, scheduled runs, and output/write failures. |

The dashboard must use only the plugin's current state and allowed WordPress
posture collectors. It must not initiate expensive full scans or query
Wordfence tables while rendering the page.

### Grafana handoff

Add an optional, administrator-configured Grafana dashboard URL. When set,
show a clearly labelled **View historical telemetry in Grafana** link. The
link is a convenience handoff only; the plugin does not make server-side or
browser-side requests to Grafana or Prometheus.

## Privacy and security requirements

- Do not render Wordfence-derived values, including data cached in plugin
  state.
- Do not render raw PHP configuration, environment variables, filesystem
  paths, usernames, email addresses, IP addresses, URLs, or incident data.
- Reuse the PHP runtime posture allowlist and opt-in disclosure boundary.
- Escape all labels and values for their output context.
- Require `manage_options` for every dashboard and dashboard-setting action.
- Validate the optional Grafana URL as an absolute `https` URL; render it with
  `esc_url()` and use an ordinary link rather than an iframe.
- Do not add public routes, REST endpoints, AJAX endpoints, or remote requests
  for this feature.

## UX guidelines

- Lead with a compact overall state: healthy, review, action required, or data
  unavailable.
- Use text and icons together; do not depend on colour alone.
- Explain unavailable data rather than displaying a misleading zero or a
  healthy state.
- Each card should link to the relevant configuration area or WordPress core
  screen when a local remediation action exists.
- Identify PHP runtime posture as opt-in if its metric group is disabled.

## Implementation steps

1. Define the dashboard page slug, capability check, navigation placement, and
   a small view-model that reads existing state without collecting new data.
2. Implement the exporter-health card and test healthy, stale, failed, and
   unavailable states.
3. Implement runtime and WordPress posture cards using bounded, already
   collected values; ensure Wordfence data is structurally excluded.
4. Implement the change-signals card using only non-Wordfence drift and IoC
   data already present in the slow-collector cache.
5. Add the optional Grafana URL setting, validation, handoff link, and admin
   help text.
6. Add accessibility, capability, escaping, and empty-state tests; document
   the page and the Grafana handoff in both READMEs.

## Acceptance criteria

- The dashboard appears as a top-level wp-admin page and is unavailable to
  users without `manage_options`.
- Existing telemetry settings and exporter behavior are unchanged.
- Rendering the dashboard does not query Wordfence tables or display any
  Wordfence-derived state.
- No charting library, iframe, public endpoint, remote query, or local
  time-series persistence is introduced.
- Runtime data respects the existing PHP posture opt-in state and allowlist.
- Every status has an understandable text equivalent and an explicit
  unavailable state.
- The Grafana link is absent until a validated HTTPS URL is configured and is
  rendered safely when present.
- Unit and smoke tests cover access control, Wordfence exclusion, data
  availability states, URL validation, output escaping, and menu registration.
