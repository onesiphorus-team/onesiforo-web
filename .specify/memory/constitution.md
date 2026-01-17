<!--
SYNC IMPACT REPORT
==================
Version change: 0.0.0 → 1.0.0 (MAJOR - initial constitution)
Modified principles: N/A (initial creation)
Added sections:
  - 8 Core Principles
  - Development Workflow section
  - Code Quality Gates section
  - Governance section
Removed sections: N/A
Templates requiring updates:
  - .specify/templates/plan-template.md ✅ (Constitution Check section compatible)
  - .specify/templates/spec-template.md ✅ (Requirements section compatible)
  - .specify/templates/tasks-template.md ✅ (Test-first workflow compatible)
Follow-up TODOs: None
-->

# Onesiforo Constitution

## Core Principles

### I. Test-First Development (NON-NEGOTIABLE)

Every feature MUST have tests written before implementation. This is non-negotiable.

- All tests MUST be written using Pest 4
- Every test MUST follow the **Arrange / Act / Assert** pattern:
  ```php
  it('does something', function () {
      // Arrange
      $user = User::factory()->create();

      // Act
      $response = $this->actingAs($user)->get('/dashboard');

      // Assert
      $response->assertOk();
  });
  ```
- Prefer Feature tests over Unit tests
- Browser tests (Pest 4) for critical user journeys
- Red-Green-Refactor cycle strictly enforced
- Tests MUST pass before any PR can be merged
- Use factories and datasets to reduce test duplication

### II. Livewire-First UI

All interactive UI components MUST use Livewire 4 with server-side state management.

- Flux UI components MUST be used when available (free edition)
- Fallback to standard Blade components only when Flux unavailable
- Minimize JavaScript; state lives on the server
- Use `wire:loading` and `wire:dirty` for loading states
- Add `wire:key` in all loops
- Single root element per Livewire component
- Authorization checks MUST be performed in all Livewire actions

### III. Modern Laravel 12 Conventions

Follow Laravel 12 structure, conventions, and modern PHP 8.4 patterns.

- Use Artisan `make:` commands for scaffolding (with `--no-interaction`)
- Eloquent relationships over raw queries; `Model::query()` not `DB::`
- Form Request classes for validation (no inline validation in controllers)
- Environment variables only in config files: `config('key')` not `env('KEY')`
- Middleware configured in `bootstrap/app.php`
- Console commands auto-discovered in `app/Console/Commands/`
- Named routes with `route()` helper for URL generation
- Queued jobs with `ShouldQueue` for time-consuming operations

### IV. Eloquent Model Standards

Models MUST follow modern Laravel 12 patterns without legacy conventions.

- **NO `$fillable` property** - All models are unguarded via `AppServiceProvider`
- Observers MUST be registered with the `#[ObservedBy]` attribute:
  ```php
  #[ObservedBy(UserObserver::class)]
  class User extends Model
  ```
- Scopes MUST be registered with the `#[ScopedBy]` attribute:
  ```php
  #[ScopedBy(ActiveScope::class)]
  class User extends Model
  ```
- Accessors and mutators MUST use `Attribute::make`:
  ```php
  protected function fullName(): Attribute
  {
      return Attribute::make(
          get: fn () => "{$this->first_name} {$this->last_name}",
      );
  }
  ```
- Casts defined in `casts()` method, not `$casts` property
- Explicit return type hints on all relationship methods
- Eager loading to prevent N+1 query problems

### V. Type Safety & Static Analysis

PHPStan/Larastan compliance is mandatory for all code.

- Larastan level 8+ compliance required
- Explicit return types on ALL methods and functions
- PHP 8.4 constructor property promotion in `__construct()`
- Array shape type definitions for complex arrays via PHPDoc
- No `@phpstan-ignore` annotations without documented justification
- No empty constructors with zero parameters

### VI. Code Quality Gates

All code MUST pass quality gates before merge.

- Pint formatting MUST pass: `vendor/bin/pint --dirty`
- Rector refactoring rules applied where configured
- All tests MUST pass: `php artisan test --compact`
- PHPStan/Larastan MUST pass with no errors
- Feature branches required; direct commits to `main` forbidden
- CI pipeline (tests, PHPStan, Pint) MUST be green

### VII. Security First

Security is embedded in every layer of the application.

- Authorization checks in ALL Livewire actions and controller methods
- Form validation on ALL user input via Form Request classes
- OWASP Top 10 awareness: no SQL injection, XSS, CSRF vulnerabilities
- Sensitive data (passwords, tokens, keys) NEVER logged or exposed
- Laravel Fortify for authentication flows
- Gates and Policies for authorization logic

### VIII. Observability & Simplicity

Code MUST be observable, debuggable, and simple.

- Laravel Pulse for application monitoring
- Structured logging for critical operations
- Laravel Reverb for real-time features
- Meaningful error messages with context
- **YAGNI**: Only implement what is explicitly required
- Start simple; no premature abstractions
- Three similar lines are better than a premature helper function
- No over-engineering; justify every added dependency

## Development Workflow

### Branch Strategy

- Feature branches from `main`: `feature/description` or `###-feature-name`
- All changes via Pull Request
- Squash merge preferred for clean history

### Commit Standards

- Descriptive commit messages explaining "why" not "what"
- Atomic commits: one logical change per commit
- Run `vendor/bin/pint --dirty` before committing

### Code Review Requirements

- All PRs require review before merge
- Reviewer MUST verify Constitution compliance
- Tests MUST be included for new functionality

## Code Quality Gates

### Pre-Merge Checklist

| Gate | Command | Required |
|------|---------|----------|
| Tests | `php artisan test --compact` | MUST pass |
| Static Analysis | `./vendor/bin/phpstan analyse` | MUST pass |
| Code Style | `vendor/bin/pint --dirty` | MUST pass |
| Constitution | Manual review | MUST comply |

### Continuous Integration

All gates enforced via GitHub Actions:
- Tests workflow
- PHPStan workflow
- Pint code style workflow

## Governance

### Amendment Process

1. Propose change via PR to this file
2. Document rationale for change
3. Update version according to semantic versioning:
   - **MAJOR**: Principle removal or incompatible redefinition
   - **MINOR**: New principle or significant expansion
   - **PATCH**: Clarifications, typos, non-semantic changes
4. Update `LAST_AMENDED_DATE`
5. Propagate changes to dependent templates if affected

### Compliance

- Constitution supersedes all other development practices
- All PRs and code reviews MUST verify compliance
- Complexity MUST be justified against Principle VIII (Simplicity)
- Violations require documented exception with rationale

### Dependent Artifacts

When Constitution changes, review and update if needed:
- `.specify/templates/plan-template.md` (Constitution Check section)
- `.specify/templates/spec-template.md` (Requirements alignment)
- `.specify/templates/tasks-template.md` (Task categorization)
- `CLAUDE.md` (Development guidelines)

**Version**: 1.0.0 | **Ratified**: 2026-01-17 | **Last Amended**: 2026-01-17
