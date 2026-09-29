# Contributing to maatify/php-return-target

## Package Identity

`maatify/php-return-target` is a framework-agnostic stateless return-target
library. The package owns its return-target acceptance, token issue and
verification boundaries. The Host owns HTTP, routing, sessions, authentication,
actual redirect execution, and key storage or loading policy.

## Ways to Contribute

Contributions may include bug fixes, tests, documentation corrections, and
compatible Runtime improvements. Keep changes focused on the affected contract
and do not add unrelated cleanup to the same change.

## Local Verification

Run the following latest-compatible sequence:

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

Run the following lowest-supported sequence:

```text
composer update --prefer-lowest --prefer-stable --no-interaction --prefer-dist --no-progress
composer check-platform-reqs
composer audit --no-interaction --abandoned=fail
composer check:syntax
composer check:whitespace
composer format:check
composer analyse
composer test:unit
composer test:system
composer check:examples
```

Restore the latest-compatible state after the lowest-supported run.

## Testing Boundary

The repository has a Unit suite, a System suite, and a Consumer Verification
Harness. It has no Integration/database/service suite because this package owns
no persistence or external-service Runtime boundary. Do not invent a
`test:integration` command.

## Pull Requests

Pull Requests should be focused, update tests or documentation with the
affected contract, pass the applicable gates, disclose failures and skips, and
avoid unrelated changes.

## Architecture / Contract Changes

Changes to the Public API, security boundary, token protocol, source topology,
dependency architecture, or Host/package ownership require the applicable
decision or governance path before implementation when they constitute a
material decision.

## Security Reporting

Report vulnerabilities privately to `support@maatify.dev`, not through a public
issue.

## composer.lock

This reusable library may generate `composer.lock` temporarily during
dependency resolution. `composer.lock` must not be committed and must not ship
in the package source. A temporary lock file is not a finding by itself.
