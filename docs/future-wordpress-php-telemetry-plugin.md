# Future Idea: Standalone WordPress and PHP Telemetry Plugin

## Status

Deferred for future product planning. This is not part of the current
Wordfence telemetry plugin's release scope.

## Idea

Create a separate sibling plugin, tentatively named **Simula WordPress Security
Telemetry**, that exports WordPress and PHP security telemetry without a
Wordfence dependency.

The plugin would not require Wordfence to be installed, would not read
Wordfence database tables, and would not export Wordfence incidents, firewall
activity, scans, authentication data, or configuration.

## Candidate telemetry scope

- PHP runtime posture, using the bounded opt-in allowlist established for this
  project.
- WordPress core, plugin, and theme update posture.
- WordPress configuration posture, including safe debug and file-modification
  controls.
- User, role, administrator, and account-change signals.
- Plugin/theme installation and state drift.
- Cron and options persistence signals.
- Content and filesystem IoC signals, including executable files in uploads.
- Exporter health, scheduling, and output status.
- A dedicated wp-admin current-state dashboard, as described in
  `admin-telemetry-dashboard-plan.md`.

## Reusable foundations

The current project already contains reusable implementation patterns for
settings, scheduling, state caching, Prometheus textfile output, WP-CLI,
admin UI, privacy-aware labels, PHP runtime posture, WordPress posture, and
WordPress drift/IoC collection.

## Required separation work

The standalone plugin should be built around a WordPress/PHP-only export
orchestrator. It must not inherit the current export flow's mandatory
Wordfence-hits-table check, Wordfence-backed metric families, or incident
exporter. Shared code should be extracted only where it has no Wordfence
dependency and can retain a clear ownership and release boundary.

## Decision criteria before starting

- Confirm that a second installable plugin is preferable to making the current
  plugin conditionally Wordfence-free.
- Define a new metric prefix and stable metric contract to prevent ambiguity
  between Wordfence and non-Wordfence telemetry.
- Set retention, privacy, and dashboard expectations without duplicating
  Prometheus as a WordPress-resident time-series store.
- Establish independent WordPress.org packaging, documentation, support, and
  upgrade policies.
