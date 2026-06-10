# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial package extract: core model, migration, services, middleware, 404 logging listener, config, cache observer, and Orchestra Testbench feature tests (P5.1).
- Optional Filament admin: `Website404RedirectsFilamentPlugin`, `Website404RedirectResource`, and CRUD/view pages (P5.2).
- `RedirectAdminAuthorizer` contract for host-defined admin access control.
- Install and configuration documentation (P5.3).

## Versioning policy

| Change type | Semver |
|-------------|--------|
| Bugfixes, docs | Patch (`1.0.x`) |
| Backward-compatible features | Minor (`1.x.0`) |
| Breaking config, schema, or **path normalization** behaviour | Major (`2.0.0`) |

Path normalization (`normalize_lowercase`, `max_path_length`, segment rules) affects how rows are deduplicated and matched. Any change that alters how paths are stored or compared must be released as a **major** version and called out here.
