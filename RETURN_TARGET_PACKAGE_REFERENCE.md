# Return Target Package Reference

## Package Purpose

The current package concern is return-target handling. Runtime implementation has started, but the package remains `Development / Unpublished`.

## Current Runtime Contract

- `Maatify\\ReturnTarget\\Config\\ReturnTargetConfig` — immutable configuration with canonical audience and TTL validation.
- `Maatify\\ReturnTarget\\Exception\\ReturnTargetExceptionInterface` — package exception marker contract.
- `Maatify\\ReturnTarget\\Exception\\InvalidReturnTargetConfigurationException` — canonical configuration failure.
- `Maatify\\ReturnTarget\\Exception\\ReturnTargetCryptoConfigurationException` — canonical crypto and key-configuration failure.

The internal `HmacReturnTargetTokenCodec` implements the canonical `rt1` token and HMAC boundary. `VerifiedTokenPayloadDTO` is an internal verification result. The public `HmacReturnTargetService`, target validation, restriction policy, and Clock flow are not implemented yet. This package does not claim completion of the `DEC-003` Runtime.

## Current Boundary

- The package currently has no persistence, database, SQL, or PDO behavior.
- The package does not own framework, HTTP, router, session, or controller behavior.
- The package does not contain Host-specific authentication flows.
- The currently implemented Public Runtime API is limited to `ReturnTargetConfig`, `ReturnTargetExceptionInterface`, and `InvalidReturnTargetConfigurationException`.
- Service, token, validation, and crypto Runtime APIs are not implemented yet.

## Source Topology

**Source Topology: Single Capability**, as recorded in the Owner-approved [DEC-002 — Single Capability Source Topology](docs/decisions/DEC-002-SINGLE-CAPABILITY-SOURCE-TOPOLOGY.md).

The `Config/` and `Exception/` responsibilities are materialized for the implemented Runtime foundation.

`FILE-05` is closed by the real source implementation and PHPStan maximum-level configuration covering `src/` and `tests/`.

## Composer Ownership

Composer identity, requirements, dependencies, autoloading, configuration, stability, and distribution metadata are owned by `composer.json`.

The current direct Runtime dependencies are `php ^8.4`, `ext-hash *`, `ext-json *`, `maatify/crypto ^1.0`, and `maatify/exceptions ^1.0`.
