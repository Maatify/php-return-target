# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project currently has no published release.

## [Unreleased]

### Added

- Initial selective pinned engineering Standards Adoption.
- Composer and package identity foundation for `maatify/php-return-target`.
- Runtime foundation: `ReturnTargetConfig`, `ReturnTargetExceptionInterface`, and `InvalidReturnTargetConfigurationException`.
- PHPStan maximum-level configuration covering the real `src/` and `tests/` paths; `FILE-05` is closed.
- Canonical internal `rt1` HMAC token codec, internal `VerifiedTokenPayloadDTO`, and `ReturnTargetCryptoConfigurationException`.
- Runtime requirements for `ext-hash`, `ext-json`, and `maatify/crypto` `^1.0`.
- Public `ReturnTargetServiceInterface` and canonical `HmacReturnTargetService`.
- Canonical target validation, optional restrict-only policy, `VerifiedReturnTargetDTO`, and shared `ClockInterface` expiry integration.
- Direct Runtime dependency on `maatify/shared-common` `^1.0`.
- Reproducible external Consumer Verification Harness with two clean-state runs.
- GitHub Actions CI for PHP 8.4 and 8.5, latest/lowest dependencies, Composer 2.10 policy audit, syntax, whitespace, PHPStan max, formatting, tests, examples, and workflow lint.
- Stable `Final Gate` aggregate CI job and immutable action pins.
- Consumer Usage Guide, runnable Public API example, `llms.txt`, and synchronized Package Reference/README workflow documentation.
- Security policy with private reporting through `support@maatify.dev`.
- CONTRIBUTING guide covering contribution paths, verification gates, testing boundaries, and reusable-library lock policy.
- Code of Conduct and linked governance/presentation documentation surfaces.
- Composer `support.security` metadata synchronized with the repository security policy URL.

### Current State

- The package remains `Development / Unpublished`.
- No version has been published.
