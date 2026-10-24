# PHP Runtime Posture Telemetry Plan

## Context

This is a follow-up action from the PHP2Shell incident post-mortem. It is
planned for the next **major release**. That release should help operators
identify PHP runtimes that are old, unexpectedly configured, or missing
security-relevant capabilities, without turning the exporter into a `phpinfo()`
dump.

The exporter writes local Prometheus textfile metrics. That is still a
meaningful disclosure boundary: anyone with access to Prometheus, a dashboard,
or the generated `.prom` file may be able to view exported labels and values.

## Goal

Add an opt-in PHP runtime posture metric group that exports a small, stable,
documented allowlist of PHP metadata and security-relevant effective settings.
Use it to support version inventory, patch-status alerting, and configuration
drift detection after security incidents.

## Non-goals

- Do not export raw `phpinfo()` output or retain it anywhere.
- Do not export PHP environment variables, request headers, or server globals.
- Do not export filesystem paths, including loaded `php.ini` paths, include
  paths, upload directories, temporary directories, or session-save paths.
- Do not export extension configuration dumps, cached-script inventories, or
  arbitrary INI values.
- Do not introduce a network dependency or send telemetry to an external
  service.

## Proposed metric families

All families below belong to a new `php_runtime_posture` metric group and are
disabled by default. The configured metric prefix and existing `site` label
continue to apply.

### Runtime identity

```text
<prefix>_php_runtime_info{site,version,sapi,architecture} 1
<prefix>_php_version_supported{site} 0|1
```

- `version` is the running PHP version, from `PHP_VERSION`.
- `sapi` is the PHP Server API, from `PHP_SAPI` (for example, `fpm-fcgi` or
  `apache2handler`).
- `architecture` is normalized to `32` or `64` from `PHP_INT_SIZE`.
- `php_version_supported` is evaluated from a maintained, plugin-side PHP
  support policy; it must not make outbound requests during export.

### Security-relevant extensions

```text
<prefix>_php_extension_info{site,extension} 1
```

Export only the following fixed allowlist when present:

```text
curl, mbstring, mysqli, openssl, pdo_mysql, sodium, xml, zip, opcache
```

The extension name must be selected from this list, not from an unrestricted
enumeration of `get_loaded_extensions()`. This keeps the label vocabulary
bounded and avoids publishing a complete attack-surface inventory. Absence is
represented by no sample; dashboards and alerts can use `absent()` where it is
important to distinguish unavailable extensions.

### Safe effective settings

```text
<prefix>_php_display_errors_enabled{site} 0|1
<prefix>_php_expose_php_enabled{site} 0|1
<prefix>_php_allow_url_fopen_enabled{site} 0|1
<prefix>_php_session_cookie_secure_enabled{site} 0|1
<prefix>_php_session_cookie_httponly_enabled{site} 0|1
<prefix>_php_session_cookie_samesite_info{site,value} 1
<prefix>_php_memory_limit_bytes{site} <bytes>
<prefix>_php_max_execution_time_seconds{site} <seconds>
<prefix>_php_upload_max_filesize_bytes{site} <bytes>
<prefix>_php_post_max_size_bytes{site} <bytes>
```

- Boolean settings are normalized to `0` or `1`.
- `session.cookie_samesite` is limited to the normalized values `lax`,
  `strict`, `none`, `empty`, or `unknown`.
- Size values are parsed into bytes; an unlimited or unparseable value is
  represented by a documented sentinel or omitted consistently across all size
  metrics.

### OPcache posture

When the fixed `opcache` extension is available and its status API can be read
without warnings, export:

```text
<prefix>_php_opcache_enabled{site} 0|1
<prefix>_php_opcache_jit_enabled{site} 0|1
<prefix>_php_opcache_memory_usage_ratio{site} <0..1>
```

Do not export cached script names, cache keys, paths, or detailed OPcache
configuration.

## Implementation plan

1. Ship this work in the next major release. Document the new metric group,
   opt-in default, disclosure boundary, and any dashboard or alert migration
   guidance in the upgrade notes before release.
2. Add `php_runtime_posture` to the metric definitions and set its default to
   disabled, with admin copy that explains the operational-information
   disclosure trade-off.
3. Add a dedicated collector with compatibility guards for PHP 7.4 and for
   disabled or unavailable functions. A missing source must yield a safe,
   documented result rather than fail the whole export.
4. Keep collection local and read-only. Use native constants and a narrowly
   scoped allowlist of `ini_get()` keys; never call `phpinfo()`.
5. Add a renderer that emits the metric families above with bounded labels and
   Prometheus-safe numeric values.
6. Document each metric, its source, default state, and interpretation in the
   plugin README/readme and release notes.

## Acceptance criteria

- The feature is disabled by default and can be enabled independently in the
  existing metric-family controls.
- No metric contains a path, environment-variable value, raw INI dump,
  request value, or arbitrary extension name.
- A repository search confirms there is no call to `phpinfo()`.
- PHP 7.4 remains supported; missing OPcache or unavailable INI values do not
  make an export fail.
- Unit tests cover version-policy boundaries, boolean and byte-size parsing,
  SameSite normalization, the extension allowlist, unavailable OPcache, and
  label escaping.
- Prometheus output validation covers both enabled and disabled states.
- Major-release upgrade notes identify this as a PHP2Shell post-mortem action,
  state the telemetry disclosure boundary clearly, and explain that the group
  is opt-in rather than enabled automatically for existing installations.

## Follow-up alerting guidance

Provide optional example alerts for an unsupported PHP version,
`display_errors` enabled in production, missing secure/HttpOnly session cookie
flags, and unexpected disappearance of the OPcache posture metric. Alert
thresholds and production-versus-development policy should remain operator
configurable rather than enforced by the plugin.
