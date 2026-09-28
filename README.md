<div align="center">

# PHP Return Target

![Maatify.dev](https://www.maatify.dev/assets/img/img/maatify_logo_white.svg)

[![Status](https://img.shields.io/badge/Status-Development-blue)](README.md)
[![PHP](https://img.shields.io/badge/PHP-8.4-8892BF)](composer.json)
[![License](https://img.shields.io/badge/License-Proprietary-green)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-max-blue)](phpstan.neon)

[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)

[![Package Reference](https://img.shields.io/badge/Package%20Reference-current-blue)](RETURN_TARGET_PACKAGE_REFERENCE.md)
[![Changelog](https://img.shields.io/badge/Changelog-Unreleased-lightgrey)](CHANGELOG.md)

A development-stage Composer package for return-target handling. Runtime implementation has started, but no externally installable release exists yet.

</div>

---

## Status

**Development / Unpublished**

The package identity and initial Composer metadata are established. The canonical public Runtime slice is implemented, while consumer verification, CI, examples, and release readiness remain outstanding.

## Requirements

- PHP `^8.4`
- `ext-hash`
- `ext-json`
- `maatify/crypto` `^1.0`
- `maatify/exceptions` `^1.0`
- `maatify/shared-common` `^1.0`

## Installation

No installation command is available because there is no externally published/resolvable package version.

## Public Runtime API

See [RETURN_TARGET_PACKAGE_REFERENCE.md](RETURN_TARGET_PACKAGE_REFERENCE.md) for the complete canonical Public Runtime Contract.

- `ReturnTargetServiceInterface` — public substitution boundary for `accepts()`, `issue()`, and `verify()`.
- `HmacReturnTargetService` — canonical implementation using `ReturnTargetConfig`, a crypto `KeyProviderInterface`, `ClockInterface`, and an optional restrict-only policy.
- `ReturnTargetRestrictionPolicyInterface` — optional Host-owned policy receiving the validated single-decoded inspection target.
- `ReturnTargetConfig` — validated audience and TTL configuration.
- `VerifiedReturnTargetDTO` — exact public result shape: `target` and `expiresAt`.
- `ReturnTargetExceptionInterface`, `InvalidReturnTargetConfigurationException`, and `ReturnTargetCryptoConfigurationException` — package exception contracts.

`accepts()` and `issue()` reject unsafe targets normally. `issue()` preserves the exact original target in the signed token. `verify()` uses the shared Clock, rejects at `now >= expiresAt`, re-applies canonical validation and the current restriction policy, and returns the exact original target on success. The package does not execute redirects.

## Implemented Runtime Foundation

- `ReturnTargetConfig`
- `ReturnTargetExceptionInterface`
- `InvalidReturnTargetConfigurationException`
- `ReturnTargetCryptoConfigurationException`

`FILE-05` is closed by the real source implementation and a PHPStan maximum-level configuration covering `src/` and `tests/`.

The canonical internal `rt1` token and crypto codec is implemented and composed by the public service. `CanonicalReturnTargetValidator` is package-internal and performs one percent-decoding pass for security inspection only. The internal codec and `VerifiedTokenPayloadDTO` remain non-public and are not Host extension points.

The internal codec is not a Public API and is not Host-replaceable.

## Boundary

This is a standalone, framework-agnostic Composer package. It does not own a Host application's HTTP, router, session, controller, or authentication flow, and it has no persistence or database behavior currently. The package remains `Development / Unpublished`.

## Documentation

- [RETURN_TARGET_PACKAGE_REFERENCE.md](RETURN_TARGET_PACKAGE_REFERENCE.md) — current canonical public/runtime/behavioral package contract.
- [CHANGELOG.md](CHANGELOG.md) — change history.

## License

This package is proprietary software owned by Maatify. See [LICENSE](LICENSE) for the applicable terms.

## Author

Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>
Backend Lead & Technical Architect<br>
[https://www.maatify.dev](https://www.maatify.dev)

---

<div align="center">

[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)

</div>
