# Data Model: Caregiver Dashboard

**Feature**: 004-caregiver-dashboard
**Date**: 2026-01-22

## Existing Entities (No Changes Required)

Le entità principali sono già definite nel database. Questa feature le utilizza senza modifiche.

### OnesiBox (existing)

```
onesi_boxes
├── id: integer (PK)
├── name: varchar
├── serial_number: varchar (unique)
├── recipient_id: integer (FK → recipients.id, nullable)
├── firmware_version: varchar (nullable)
├── last_seen_at: datetime (nullable) → determina online/offline
├── is_active: boolean
├── notes: text (nullable)
├── created_at: datetime
├── updated_at: datetime
└── deleted_at: datetime (nullable, soft delete)
```

**Computed Properties**:
- `isOnline()`: `last_seen_at > now() - 5 minutes`
- `status`: Enum OnesiBoxStatus (Idle, Playing, Calling, Error) - **TO ADD**

### Recipient (existing)

```
recipients
├── id: integer (PK)
├── first_name: varchar
├── last_name: varchar
├── phone: varchar (nullable)
├── street: varchar (nullable)
├── city: varchar (nullable)
├── postal_code: varchar (nullable)
├── province: varchar (nullable)
├── emergency_contacts: json (nullable)
│   └── Array<{name: string, phone: string, relationship?: string}>
├── notes: text (nullable)
├── created_at: datetime
├── updated_at: datetime
└── deleted_at: datetime (nullable, soft delete)
```

**Computed Properties**:
- `full_name`: `"{first_name} {last_name}"`
- `full_address`: Formatted address string

### User ↔ OnesiBox Pivot (existing)

```
onesi_box_user
├── id: integer (PK)
├── onesi_box_id: integer (FK → onesi_boxes.id)
├── user_id: integer (FK → users.id)
├── permission: varchar (enum: 'full', 'read-only')
├── created_at: datetime
└── updated_at: datetime

UNIQUE(onesi_box_id, user_id)
```

---

## New Field Required

### OnesiBox.status

**Migration Required**: Add `status` column to `onesi_boxes` table.

```
ALTER TABLE onesi_boxes ADD COLUMN status varchar DEFAULT 'idle';
```

**Validation**: Enum OnesiBoxStatus values only (idle, playing, calling, error)

**Usage**: Real-time status display, broadcast event payload

---

## Existing Enums (No Changes Required)

### OnesiBoxPermission

```php
enum OnesiBoxPermission: string
{
    case Full = 'full';
    case ReadOnly = 'read-only';
}
```

### OnesiBoxStatus

```php
enum OnesiBoxStatus: string
{
    case Idle = 'idle';
    case Playing = 'playing';
    case Calling = 'calling';
    case Error = 'error';
}
```

---

## Entity Relationships Diagram

```
┌─────────────┐       ┌──────────────────┐       ┌─────────────┐
│    User     │       │  onesi_box_user  │       │  OnesiBox   │
│  (Caregiver)│──────▶│    (pivot)       │◀──────│             │
│             │  N:M  │  + permission    │  N:M  │             │
└─────────────┘       └──────────────────┘       └──────┬──────┘
                                                        │
                                                        │ 1:1
                                                        ▼
                                                 ┌─────────────┐
                                                 │  Recipient  │
                                                 │  (elderly)  │
                                                 └─────────────┘
```

---

## Query Patterns

### Get caregiver's OnesiBoxes with status

```php
User::find($id)
    ->onesiBoxes()
    ->with('recipient')
    ->withPivot('permission')
    ->get();
```

### Check permission for control

```php
$onesiBox->caregivers()
    ->where('user_id', $userId)
    ->wherePivot('permission', OnesiBoxPermission::Full->value)
    ->exists();
```

### Get recipient contacts

```php
$onesiBox->recipient?->only([
    'full_name',
    'phone',
    'full_address',
    'emergency_contacts'
]);
```

---

## State Transitions

### OnesiBoxStatus State Machine

```
         ┌──────────────────────────────────────┐
         │                                      │
         ▼                                      │
    ┌─────────┐    play_audio/video    ┌───────────┐
    │  Idle   │───────────────────────▶│  Playing  │
    └────┬────┘                        └─────┬─────┘
         │                                   │
         │ start_zoom           stop/finish  │
         │                                   │
         ▼                                   ▼
    ┌─────────┐                        ┌─────────┐
    │ Calling │◀───────────────────────│  Idle   │
    └────┬────┘     end_call           └─────────┘
         │
         │ hang_up
         ▼
    ┌─────────┐
    │  Idle   │
    └─────────┘

    Any state ──── error ────▶ Error ──── recover ────▶ Idle
```

---

## Data Validation Rules

| Entity | Field | Validation |
|--------|-------|------------|
| OnesiBox | status | enum:idle,playing,calling,error |
| OnesiBox | last_seen_at | nullable, date |
| Recipient | emergency_contacts | nullable, array, each: name required, phone required |
| Pivot | permission | enum:full,read-only |
