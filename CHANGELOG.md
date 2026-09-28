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

### Not Implemented Yet

- The public service, target validation, restriction policy, and Clock flow remain outside this Work Unit.
- The package remains `Development / Unpublished`; `DEC-003` Runtime is not complete.
