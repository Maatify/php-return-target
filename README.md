<div align="center">

# PHP Return Target

![Maatify.dev](https://www.maatify.dev/assets/img/img/maatify_logo_white.svg)

[![Status](https://img.shields.io/badge/Status-Development-blue)](README.md)
[![PHP](https://img.shields.io/badge/PHP-8.4-8892BF)](composer.json)
[![License](https://img.shields.io/badge/License-Proprietary-green)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-max-blue)](phpstan.neon)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)

[![Usage Guide](https://img.shields.io/badge/Usage%20Guide-current-blue)](docs/guides/USAGE_GUIDE.md)
[![Examples](https://img.shields.io/badge/Examples-maintained-blue)](examples/)
[![Package Reference](https://img.shields.io/badge/Package%20Reference-canonical-blue)](RETURN_TARGET_PACKAGE_REFERENCE.md)
[![Changelog](https://img.shields.io/badge/Changelog-Unreleased-lightgrey)](CHANGELOG.md)

Stateless signed internal return-target handling for framework-agnostic PHP applications.

</div>

---

## Status

**Development / Unpublished**

The canonical Runtime API, external Consumer Verification Harness, examples, and CI gates are implemented. No externally installable release exists.

## Key Features

- Validate internal return targets without rewriting their original representation.
- Issue opaque, expiring `rt1` HMAC tokens and verify them through a public service.
- Re-apply current target restrictions and expiry rules during verification.
- Keep HTTP, routing, authentication, redirect execution, and key loading under Host ownership.

## Requirements

- PHP `^8.4` (CI covers PHP `8.4` and `8.5`)
- `ext-hash`
- `ext-json`
- `maatify/crypto` `^1.0`
- `maatify/exceptions` `^1.0`
- `maatify/shared-common` `^1.0`

## Installation

There is no external installation command while the package is **Development / Unpublished**. Use the repository's Composer development setup and the documented public workflow.

## Quick Usage

```php
$service = new HmacReturnTargetService(
    new ReturnTargetConfig('admin-auth', 60),
    $hostKeyProvider,
    $hostClock,
    $optionalHostRestrictionPolicy,
);

$token = $service->issue('/orders/15');
$verified = $token === null ? null : $service->verify($token);
```

The Host supplies the `KeyProviderInterface`, `ClockInterface`, and optional restrict-only policy. See the [Usage Guide](docs/guides/USAGE_GUIDE.md) and [maintained example](examples/basic_usage.php) for a complete runnable construction.

## Public Runtime API

The public substitution boundary is `ReturnTargetServiceInterface`. The canonical implementation is `HmacReturnTargetService`; its public collaborators are `ReturnTargetConfig`, `ReturnTargetRestrictionPolicyInterface`, `KeyProviderInterface`, and `ClockInterface`. Successful verification returns `VerifiedReturnTargetDTO`; normal target, token, policy, or expiry rejection returns `false`/`null` according to the operation. The [Package Reference](RETURN_TARGET_PACKAGE_REFERENCE.md) is the complete canonical contract and API inventory.

## Boundaries

The package does not execute redirects and does not own HTTP, routing, sessions, authentication, authorization, persistence, databases, or token consumption state. The Host owns those concerns and the construction of key material and time policy.

## Documentation

- [Usage Guide](docs/guides/USAGE_GUIDE.md) — consumer fit, boundaries, and workflows.
- [Examples](examples/) — maintained Public API examples.
- [Package Reference](RETURN_TARGET_PACKAGE_REFERENCE.md) — canonical public/runtime/behavioral contract.
- [Changelog](CHANGELOG.md) — factual project history.

## Quality Status

The repository defines local and CI gates for Composer validation, Composer 2.10 dependency policy audit, latest/lowest dependency resolution, PHP 8.4/8.5 tests, PHPStan max, formatting, syntax, whitespace, examples, Consumer Verification, and workflow lint. GitHub `Final Gate` is the stable aggregate CI check; the actual status for a commit is reported by its GitHub Actions run.

## Development and Testing

Latest-compatible local sequence:

```text
composer validate --strict
composer update --no-interaction --prefer-dist --no-progress
composer check-platform-reqs
composer audit --no-interaction --abandoned=fail
composer check:syntax
composer check:whitespace
composer format:check
composer analyse
composer test:unit
composer test:system
composer check:examples
composer verify:consumer
composer check:workflows
```

Lowest-supported dependency sequence begins with:

```text
composer update --prefer-lowest --prefer-stable --no-interaction --prefer-dist --no-progress
composer check-platform-reqs
composer audit --no-interaction --abandoned=fail
composer check:syntax
composer format:check
composer analyse
composer test:unit
composer test:system
composer check:examples
```

Restore latest-compatible dependencies and rerun the final applicable local sequence after the lowest check. The Consumer Verification Harness performs two independent clean Composer consumer resolutions and removes generated consumer state after each run. CI tests PHP `8.4` and `8.5`; its `Final Gate` aggregates `quality`, `tests`, `lowest`, `consumer-verification`, and `workflow-lint`.

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
