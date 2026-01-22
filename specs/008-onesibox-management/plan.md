# Implementation Plan: OnesiBox Management

**Branch**: `008-onesibox-management` | **Date**: 2026-01-22 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/008-onesibox-management/spec.md`

## Summary

Enhance the existing OnesiBox Filament resource with an improved multi-section form for creating/editing devices with recipient data, and add a dedicated relation manager for authentication token lifecycle management (generate, view last-used, revoke) with a copyable modal for newly generated tokens.

## Technical Context

**Language/Version**: PHP 8.4.17
**Primary Dependencies**: Laravel 12.47.0, Filament 5.0.0, Laravel Sanctum 4.2.4, spatie/laravel-activitylog
**Storage**: SQLite (development), MySQL/PostgreSQL (production-ready)
**Testing**: Pest 4.3.1 with Livewire testing support
**Target Platform**: Web (admin panel via Filament)
**Project Type**: Web application (Laravel monolith)
**Performance Goals**: Form submission < 2s, token generation < 5s
**Constraints**: Desktop-first (1024px+), no mobile optimization required
**Scale/Scope**: Admin panel for small-medium deployment (~100 devices)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

The project constitution is a template with placeholders. No specific gates or violations to check. Proceeding with standard Laravel/Filament best practices:

- [x] Use existing patterns from UserResource and OnesiBoxResource
- [x] Follow Filament 5 conventions for forms, tables, and relation managers
- [x] Write Pest tests for all new functionality
- [x] Use activity logging for audit trail
- [x] No new dependencies required

## Project Structure

### Documentation (this feature)

```text
specs/008-onesibox-management/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (N/A - no new API endpoints)
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (repository root)

```text
app/
├── Filament/
│   └── Resources/
│       └── OnesiBoxes/
│           ├── OnesiBoxResource.php           # Update: add relation manager
│           ├── Pages/
│           │   ├── CreateOnesiBox.php         # Existing
│           │   └── EditOnesiBox.php           # Existing
│           ├── Schemas/
│           │   ├── OnesiBoxForm.php           # Update: enhance with recipient creation
│           │   └── RecipientFieldset.php      # New: reusable recipient fields
│           ├── Tables/
│           │   └── OnesiBoxesTable.php        # Update: remove inline token action
│           └── RelationManagers/
│               └── TokensRelationManager.php  # New: dedicated token management
├── Models/
│   ├── OnesiBox.php                           # Existing (already has HasApiTokens)
│   └── Recipient.php                          # Existing
└── Actions/
    └── GenerateOnesiBoxToken.php              # New: encapsulated token generation logic

tests/
├── Feature/
│   └── Filament/
│       ├── OnesiBoxResourceTest.php           # New: form validation tests
│       └── OnesiBoxTokensRelationManagerTest.php  # New: token management tests
└── Unit/
    └── Actions/
        └── GenerateOnesiBoxTokenTest.php      # New: token generation unit tests
```

**Structure Decision**: Follows existing Filament resource organization pattern. New relation manager follows Filament 5 conventions. Token generation logic extracted to Action class for testability and reusability.

## Complexity Tracking

No constitution violations requiring justification. Implementation follows established patterns.
