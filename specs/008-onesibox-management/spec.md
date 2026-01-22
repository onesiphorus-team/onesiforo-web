# Feature Specification: OnesiBox Management

**Feature Branch**: `008-onesibox-management`
**Created**: 2026-01-22
**Status**: Draft
**Input**: User description: "Come admin o super admin voglio poter aggiungere nuove onesibox. Il form deve farmi inserire i dati della box e del recipient. Deve essere elegante, funzionale, con validazioni. Deve poi esserci un relation manager per gestire i token di autenticazione: generare nuovi, vedere ultimi login con token, revocarli. Quando viene generato viene aperta una modale che permette di copiare il token. Poi i token non sono più visibili."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Create New OnesiBox with Recipient (Priority: P1)

As an Admin or Super Admin, I want to create a new OnesiBox with associated recipient data in a single, elegant form so that I can quickly onboard new devices and their assigned elderly recipients.

**Why this priority**: This is the core functionality that enables new device deployments. Without the ability to create OnesiBox records with recipient data, no other features can be used.

**Independent Test**: Can be fully tested by navigating to the OnesiBox creation page, filling the form with valid box and recipient data, and verifying the records are created with proper relationships.

**Acceptance Scenarios**:

1. **Given** I am logged in as an Admin or Super Admin, **When** I navigate to the OnesiBox creation page, **Then** I see a well-organized form with sections for device information and recipient information
2. **Given** I am on the OnesiBox creation form, **When** I fill all required fields with valid data and submit, **Then** a new OnesiBox is created with the associated recipient linked
3. **Given** I am on the OnesiBox creation form, **When** I leave required fields empty and attempt to submit, **Then** I see clear validation errors indicating which fields are required
4. **Given** I am on the OnesiBox creation form, **When** I enter a serial number that already exists, **Then** I see a validation error indicating the serial number must be unique
5. **Given** I am filling the recipient section, **When** I can either select an existing recipient or create a new one inline, **Then** the system handles both scenarios correctly

---

### User Story 2 - Generate Authentication Token (Priority: P1)

As an Admin or Super Admin, I want to generate new authentication tokens for an OnesiBox so that the physical device can authenticate with the server securely.

**Why this priority**: Authentication tokens are essential for device-server communication. Without tokens, OnesiBox devices cannot connect to the platform.

**Independent Test**: Can be fully tested by navigating to an OnesiBox edit page, accessing the token management relation manager, generating a new token, and verifying the token is displayed in a copyable modal.

**Acceptance Scenarios**:

1. **Given** I am on an OnesiBox edit page, **When** I access the token management section, **Then** I see a relation manager displaying existing tokens
2. **Given** I am in the token management section, **When** I click to generate a new token, **Then** a modal appears displaying the newly generated token in plain text
3. **Given** the token generation modal is displayed, **When** I see the token, **Then** I have a clearly visible "Copy to clipboard" button
4. **Given** the token generation modal is displayed, **When** I close the modal or navigate away, **Then** I can never see the plain text token again (only hashed version stored)
5. **Given** I am generating a token, **When** the process completes, **Then** the token is given a descriptive name (e.g., timestamp or device context)

---

### User Story 3 - View Token Usage History (Priority: P2)

As an Admin or Super Admin, I want to see when tokens were last used so that I can monitor device activity and identify potentially compromised or unused tokens.

**Why this priority**: Security monitoring is important but secondary to basic token generation. This provides visibility into device authentication patterns.

**Independent Test**: Can be fully tested by viewing the token list for an OnesiBox and verifying that last usage timestamps are displayed for each token.

**Acceptance Scenarios**:

1. **Given** I am viewing the token management relation manager, **When** I look at the token list, **Then** I see the "Last Used" timestamp for each token
2. **Given** a token has been used by a device, **When** I view the token list, **Then** the "Last Used" column shows the most recent authentication time
3. **Given** a token has never been used, **When** I view the token list, **Then** the "Last Used" column shows "Never" or equivalent
4. **Given** I am viewing tokens, **When** I look at the list, **Then** I can identify the token creation date and name/description

---

### User Story 4 - Revoke Authentication Token (Priority: P2)

As an Admin or Super Admin, I want to revoke existing tokens so that I can disable compromised credentials or clean up unused tokens.

**Why this priority**: Security management requires the ability to revoke access. This is essential for incident response and token lifecycle management.

**Independent Test**: Can be fully tested by selecting a token from the list and revoking it, then verifying it no longer appears as active and cannot be used for authentication.

**Acceptance Scenarios**:

1. **Given** I am viewing the token management relation manager, **When** I see a token I want to revoke, **Then** I have a clear action to revoke/delete it
2. **Given** I click to revoke a token, **When** the confirmation dialog appears, **Then** I must confirm the action before the token is revoked
3. **Given** I confirm token revocation, **When** the process completes, **Then** the token is removed from the list
4. **Given** a revoked token, **When** a device attempts to authenticate with it, **Then** the authentication fails

---

### User Story 5 - Form Validation and UX (Priority: P2)

As an Admin or Super Admin, I want clear validation messages and an elegant user interface so that I can efficiently manage OnesiBox records without confusion.

**Why this priority**: Good UX increases efficiency and reduces errors. This supports all other user stories by providing a polished interface.

**Independent Test**: Can be fully tested by intentionally triggering various validation rules and verifying that error messages are clear, specific, and displayed near the relevant fields.

**Acceptance Scenarios**:

1. **Given** I submit the form with invalid data, **When** validation fails, **Then** errors are displayed inline next to the relevant fields
2. **Given** I am entering a phone number, **When** I enter an invalid format, **Then** the validation indicates the expected format
3. **Given** I am entering a serial number, **When** I enter invalid characters, **Then** the validation explains the allowed format
4. **Given** the form has multiple sections, **When** I view the form, **Then** sections are logically grouped and visually distinct
5. **Given** I complete a form successfully, **When** I submit, **Then** I receive clear feedback that the operation succeeded

---

### Edge Cases

- What happens when creating an OnesiBox for a recipient that already has an OnesiBox assigned? (System allows it - one recipient can have multiple boxes over time, soft-deleted boxes don't count)
- How does the system handle token generation when the device has many active tokens? (No hard limit imposed - administrators are trusted to manage tokens responsibly)
- What happens if token generation fails due to a system error? (User sees an error notification and can retry)
- How is the form displayed on mobile or smaller screens? (Form is optimized for desktop admin use, viewports 1024px+)
- What happens if a user tries to revoke the only active token for a device? (System allows it with a warning that the device will lose connectivity)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow Admin and Super Admin users to create new OnesiBox records via a Filament resource form
- **FR-002**: System MUST display a multi-section form with device information (name, serial number, firmware version, active status) and recipient information
- **FR-003**: System MUST validate all required fields (name, serial number for device; first name, last name for recipient) before saving
- **FR-004**: System MUST enforce unique serial numbers across all OnesiBox records
- **FR-005**: System MUST allow selecting an existing recipient or creating a new recipient inline
- **FR-006**: System MUST validate recipient fields (first name, last name required; phone number format if provided)
- **FR-007**: System MUST provide a relation manager on the OnesiBox edit page for managing authentication tokens
- **FR-008**: System MUST allow generating new API tokens with a single action
- **FR-009**: System MUST display the newly generated token in a modal immediately after creation, allowing the user to copy it to clipboard
- **FR-010**: System MUST only show the plain text token once; after the modal is closed, the token is never displayed again
- **FR-011**: System MUST display token name, creation date, and last used timestamp in the relation manager table
- **FR-012**: System MUST allow revoking/deleting tokens with a confirmation step
- **FR-013**: System MUST display inline validation errors for all form fields
- **FR-014**: System MUST log all token creation and revocation events in the activity log
- **FR-015**: System MUST set token expiration to a configurable period (default 1 year from creation)
- **FR-016**: System MUST grant tokens full device access abilities (all OnesiBox API endpoints)

### Key Entities

- **OnesiBox**: Represents the physical device; key attributes include name, serial_number (unique), recipient_id, firmware_version, is_active, status, notes
- **Recipient**: Represents the elderly person using the device; key attributes include first_name, last_name, phone, address fields (street, city, postal_code, province), emergency_contacts, notes
- **PersonalAccessToken**: Sanctum token associated with OnesiBox (polymorphic via tokenable); key attributes include name, token (hashed), abilities, last_used_at, expires_at, created_at

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Administrators can create a new OnesiBox with recipient data in under 2 minutes
- **SC-002**: Token generation and copying to clipboard completes in under 5 seconds
- **SC-003**: 100% of form validation errors display inline next to the relevant field
- **SC-004**: All token management actions (create, view, revoke) are accessible from a single page without additional navigation
- **SC-005**: Token last-used timestamps update correctly after each device authentication
- **SC-006**: All token creation and revocation events are logged for audit purposes
- **SC-007**: Form works correctly on viewports 1024px and wider

## Clarifications

### Session 2026-01-22

- Q: Should authentication tokens have an expiration date, or remain valid indefinitely until manually revoked? → A: Tokens expire after configurable period (default 1 year)
- Q: What abilities/permissions should be granted to OnesiBox authentication tokens? → A: Full device access (all OnesiBox API endpoints)

## Assumptions

- Admin and Super Admin roles already exist in the system with appropriate permissions
- The existing OnesiBox model already has Sanctum HasApiTokens trait configured
- The recipient model and its fields already exist in the database
- Activity logging is already configured in the application using spatie/laravel-activitylog
- Filament 5 is the admin panel framework being used
