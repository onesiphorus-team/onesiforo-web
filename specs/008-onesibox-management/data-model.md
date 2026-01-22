# Data Model: OnesiBox Management

**Branch**: `008-onesibox-management` | **Date**: 2026-01-22

## Entity Overview

This feature works with existing entities. No new database migrations are required.

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────────────┐
│   OnesiBox      │──────▶│   Recipient     │       │ PersonalAccessToken     │
│                 │  N:1  │                 │       │ (Sanctum)               │
│                 │       │                 │       │                         │
│ HasApiTokens    │◀──────────────────────────────▶│ tokenable (polymorphic) │
│                 │  1:N  │                 │       │                         │
└─────────────────┘       └─────────────────┘       └─────────────────────────┘
```

## Entities

### OnesiBox (existing)

**Table**: `onesi_boxes`

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, AUTO | |
| name | VARCHAR(255) | NOT NULL | Device friendly name |
| serial_number | VARCHAR(255) | NOT NULL, UNIQUE | Device identifier |
| recipient_id | BIGINT | FK → recipients.id, NULL | Can be unassigned |
| firmware_version | VARCHAR(50) | NULL | e.g., "1.0.0" |
| last_seen_at | DATETIME | NULL | Last heartbeat timestamp |
| is_active | BOOLEAN | DEFAULT TRUE | Enables/disables device |
| status | VARCHAR(20) | DEFAULT 'pending' | OnesiBoxStatus enum |
| notes | TEXT | NULL | Admin notes |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |
| deleted_at | DATETIME | NULL | Soft delete |

**Relationships**:
- `belongsTo(Recipient)` via `recipient_id`
- `morphMany(PersonalAccessToken)` via `HasApiTokens` trait

**Validation Rules** (for form):
- `name`: required, string, max:255
- `serial_number`: required, string, max:255, unique:onesi_boxes,serial_number (ignoring current record on edit)
- `recipient_id`: nullable, exists:recipients,id
- `firmware_version`: nullable, string, max:50
- `is_active`: boolean

---

### Recipient (existing)

**Table**: `recipients`

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, AUTO | |
| first_name | VARCHAR(255) | NOT NULL | |
| last_name | VARCHAR(255) | NOT NULL | |
| phone | VARCHAR(50) | NULL | Italian format expected |
| street | VARCHAR(255) | NULL | |
| city | VARCHAR(100) | NULL | |
| postal_code | VARCHAR(10) | NULL | Italian CAP |
| province | VARCHAR(2) | NULL | Italian province code |
| emergency_contacts | JSON | NULL | Array of {name, phone, relationship?} |
| notes | TEXT | NULL | |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |
| deleted_at | DATETIME | NULL | Soft delete |

**Relationships**:
- `hasOne(OnesiBox)` via `onesi_boxes.recipient_id`

**Validation Rules** (for inline creation form):
- `first_name`: required, string, max:255
- `last_name`: required, string, max:255
- `phone`: nullable, string, regex for Italian phone format
- `street`: nullable, string, max:255
- `city`: nullable, string, max:100
- `postal_code`: nullable, string, max:10
- `province`: nullable, string, max:2

---

### PersonalAccessToken (Sanctum - existing)

**Table**: `personal_access_tokens`

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, AUTO | |
| tokenable_type | VARCHAR(255) | NOT NULL | "App\Models\OnesiBox" |
| tokenable_id | BIGINT | NOT NULL | OnesiBox ID |
| name | VARCHAR(255) | NOT NULL | Token description |
| token | VARCHAR(64) | NOT NULL, UNIQUE | SHA-256 hash |
| abilities | JSON | NULL | ["*"] for full access |
| last_used_at | DATETIME | NULL | Auto-updated by Sanctum |
| expires_at | DATETIME | NULL | 1 year from creation |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |

**Relationships**:
- `morphTo(tokenable)` → OnesiBox

**No custom validation** - managed by Sanctum

---

## State Transitions

### OnesiBox Status (existing enum)

```
pending → active → inactive
    ↓        ↓
    └────────┴────→ deleted (soft)
```

Not modified by this feature.

### Token Lifecycle (new business logic)

```
[Generate Action]
       │
       ▼
   ┌────────┐     [Revoke Action]
   │ Active │ ──────────────────→ [Deleted]
   └────────┘
       │
       │ (time passes)
       ▼
   ┌─────────┐
   │ Expired │ ──→ (still in DB, but
   └─────────┘      authentication fails)
```

- **Active**: Token exists, `expires_at` > now()
- **Expired**: Token exists, `expires_at` <= now()
- **Deleted**: Token removed from database

---

## Indexes (existing)

No new indexes required. Existing indexes:
- `personal_access_tokens_tokenable_type_tokenable_id_index` - efficient token lookup by owner
- `personal_access_tokens_token_unique` - token uniqueness
- `personal_access_tokens_expires_at_index` - efficient expiration queries
- `onesi_boxes_serial_number_unique` - serial number uniqueness

---

## Data Volume Assumptions

- ~100 OnesiBox devices (small-medium deployment)
- ~1-3 tokens per device on average
- ~300 tokens maximum in system
- Token generation: rare (device setup/replacement)
- Token queries: frequent (every API request)
