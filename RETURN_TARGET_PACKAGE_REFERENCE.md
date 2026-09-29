# Return Target Package Reference

## Package Purpose

The current package concern is stateless signed internal return-target handling. The package remains `Development / Unpublished`.

## Public Runtime Contract

- `Maatify\\ReturnTarget\\Service\\ReturnTargetServiceInterface` — `accepts(string): bool`, `issue(string): ?string`, and `verify(string): ?VerifiedReturnTargetDTO`.
- `Maatify\\ReturnTarget\\Service\\HmacReturnTargetService` — canonical implementation.
- `Maatify\\ReturnTarget\\Validation\\ReturnTargetRestrictionPolicyInterface` — optional restrict-only Host policy.
- `Maatify\\ReturnTarget\\Config\\ReturnTargetConfig` — immutable validated audience and `1..3600` second TTL.
- `Maatify\\ReturnTarget\\DTO\\VerifiedReturnTargetDTO` — readonly result with exactly `target` and `expiresAt`.
- `Maatify\\ReturnTarget\\Exception\\ReturnTargetExceptionInterface` — package exception marker.
- `Maatify\\ReturnTarget\\Exception\\InvalidReturnTargetConfigurationException` — invalid configuration.
- `Maatify\\ReturnTarget\\Exception\\ReturnTargetCryptoConfigurationException` — classified canonical crypto/key configuration failure.

### Shared Service Behavioral Floor

`ReturnTargetServiceInterface` is a common substitution boundary for the canonical
service and complete Host-supplied implementations. A Host implementation may choose
its own token format, cryptographic mechanism, key management, validation internals,
TTL policy, and storage/persistence internals. It conforms only when `accepts()` reports
current internal-target acceptance, `issue()` returns an opaque token for an accepted
target or null for normal rejection without a fallback target, and `verify()` returns a
currently accepted, non-expired `VerifiedReturnTargetDTO` or null for normal rejection.
`expiresAt` is the authoritative Unix timestamp in seconds.

The guarantees below are canonical-only properties of `HmacReturnTargetService`, not
additional requirements imposed on every Host implementation.

The service constructor is `HmacReturnTargetService(ReturnTargetConfig, KeyProviderInterface, ClockInterface, ?ReturnTargetRestrictionPolicyInterface = null)`. `accepts()` validates the original target and invokes the optional policy exactly once with the single-percent-decoded inspection representation. `issue()` returns null for normal rejection, uses `ClockInterface::now()->getTimestamp()`, and signs the exact original representation. `verify()` passes the Clock timestamp to the internal codec, re-applies current validation and policy, rejects when `now >= exp`, and returns the exact recovered original representation.

The validator rejects unsafe RFC 3986 target forms, original targets larger than 2048
bytes, raw or decoded controls including DEL, whitespace, backslash, `#`, malformed
percent escapes, authority-form exposure, and raw or single-decoded path dot segments.
It performs one percent-decoding inspection pass only, rejects second-stage valid
percent-escape ambiguity, and never trims, normalizes, recursively decodes, or rewrites.
The exact original representation is preserved in canonical token payloads and public
results; the inspection representation is never stored or returned as a replacement.

The service does not catch unknown external/provider or Host-policy throwables. Current
package-owned mappings are `InvalidReturnTargetConfigurationException` to the
`maatify/exceptions` `INVALID_ARGUMENT` contract and
`ReturnTargetCryptoConfigurationException` to `MAATIFY_ERROR`. Classified codec
failures remain governed by the codec contract; unknown failures are not blanket-wrapped
or swallowed. The direct Runtime dependencies are PHP `^8.4`, `ext-hash`, `ext-json`,
`maatify/crypto ^1.0`, `maatify/exceptions ^1.0`, and `maatify/shared-common ^1.0`.

## Current Boundary

- The package currently has no persistence, database, SQL, or PDO behavior.
- The package does not own framework, HTTP, router, session, or controller behavior.
- The package does not contain Host-specific authentication flows.
- The Host owns HTTP, router, session, authentication, authorization, actual redirect execution, fallback destinations, and Host-specific route policy.
- The internal token/crypto codec and `VerifiedTokenPayloadDTO` are not Public APIs and are not Host-replaceable.

## Source Topology

**Source Topology: Single Capability**, as recorded in the Owner-approved [DEC-002 — Single Capability Source Topology](docs/decisions/DEC-002-SINGLE-CAPABILITY-SOURCE-TOPOLOGY.md).

The `Adapter/`, `Config/`, `DTO/`, `Exception/`, `Service/`, and `Validation/` responsibilities are materialized for the implemented Runtime.

`FILE-05` is closed by the real source implementation and PHPStan maximum-level configuration covering `src/`, `tests/`, `examples/`, and `consumer-verification/`.

## Composer Ownership

Composer identity, requirements, dependencies, autoloading, configuration, stability, and distribution metadata are owned by `composer.json`.

The current direct Runtime dependencies are `php ^8.4`, `ext-hash *`, `ext-json *`, `maatify/crypto ^1.0`, `maatify/exceptions ^1.0`, and `maatify/shared-common ^1.0`.

## Technical Consumer Workflow

The canonical consumer workflow is:

```text
Host input
→ ReturnTargetServiceInterface / canonical HmacReturnTargetService
→ canonical acceptance / optional Host restriction
→ token issue or verify
→ VerifiedReturnTargetDTO / normal null rejection
→ Host-owned redirect decision and execution
```

The Host constructs and supplies the public `KeyProviderInterface`, the `ClockInterface` implementation, and the optional `ReturnTargetRestrictionPolicyInterface`. The Host also owns key loading, HTTP, router, session, authentication, authorization, fallback destinations, and redirect execution. The canonical package implementation owns target validation and token verification; it never executes a redirect.

See the [Usage Guide](docs/guides/USAGE_GUIDE.md) for integration guidance and [examples/](examples/) for maintained Public API examples. Those artifacts explain and demonstrate this contract; they are not alternative contract sources.

## Extension Guide

### Complete Host Implementation

A Host may implement `ReturnTargetServiceInterface` as a complete replacement
implementation. It is bound only by the Shared Behavioral Floor described in
this reference. It may differ in token format, cryptography, key management,
validation internals, TTL policy, and storage or persistence internals, as
specified by DEC-003 and the Public Contract.

### Canonical HMAC Restriction Extension

With `HmacReturnTargetService`, the only Host extension point inside the
canonical implementation is `ReturnTargetRestrictionPolicyInterface`. It is a
restrict-only policy and does not expand canonical safety guarantees.

### Non-Extension Internals

The following are not extension surfaces:

- `HmacReturnTargetTokenCodec`;
- `CanonicalReturnTargetValidator`; and
- `VerifiedTokenPayloadDTO`.

No additional interface is created for these internals.

## Persistence / Operational Read Classification: Out of Scope

The package owns no persisted state, package tables, or schema, and no
operational reporting surface is applicable to the current contract. This is a
current-state classification and does not generalize the package's future
scope.
