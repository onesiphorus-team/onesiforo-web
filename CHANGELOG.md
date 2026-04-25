# Changelog

Tutte le modifiche rilevanti di questo progetto sono documentate in questo file.

## [0.10.2] - 2026-04-25

### Bug Fixes

- Mount web middleware on screenshot download route

## [0.10.1] - 2026-04-25

### Bug Fixes

- Allow web session guard on screenshot download route

## [0.10.0] - 2026-04-24

### Bug Fixes

- Align rollup historical records to startOfHour
- Address critical and important review findings
- Satisfy Pint, PHPStan level 7, and Arch rules

### Features

- Create appliance_screenshots table
- Add screenshot_enabled and interval fields to onesi_boxes
- Add ApplianceScreenshot with file cleanup on delete
- Add screenshots and latestScreenshot relations to OnesiBox
- Add StoreScreenshotRequest validation rules
- Add ProcessScreenshotAction to persist and dispatch
- Make ApplianceScreenshotReceived a broadcast event
- Extend onesibox channel with admin role and use it for screenshot event
- Add ApplianceScreenshotPolicy with admin/caregiver access
- Register screenshot-upload rate limiter and screenshot policy
- Add screenshot upload endpoint
- Add signed route for screenshot download
- Expose screenshot config in heartbeat response
- Add prune-screenshots command with 24h+rollup retention
- Run prune-screenshots every 5m + daily orphan sweep
- Add screenshot diagnostic section to OnesiBox form
- Register ManageScreenshots custom page
- Add screenshot action to edit and list views
- Add ScreenshotsViewer livewire component
- Add ScreenshotCarousel component with compact and full variants
- Embed compact screenshot carousel in box list cards
- Embed full screenshot carousel in box detail view

## [0.9.4] - 2026-04-24

### Bug Fixes

- Aggiorna coda comandi in tempo reale con wire:poll

## [0.9.3] - 2026-04-23

### Features

- Mostra volume attuale e rinomina azione in Zoom (#183)

## [0.9.2] - 2026-04-23

### Bug Fixes

- Eager-load nested recipient.congregation on OnesiBoxes list (#181)

## [0.9.1] - 2026-04-23

### Bug Fixes

- Riparare e semplificare la modale del volume

## [0.9.0] - 2026-04-23

### Bug Fixes

- Remove remoteUser from devcontainer to fix permission errors
- Remove npm install from devcontainer postCreateCommand
- Address code review issues for adunanze feature
- Resolve PHPStan return type errors in Filament resources
- Address second round of code review findings for adunanze
- Make adunanze column migrations idempotent
- Address third round of review findings for adunanze
- Address review findings on OnesiBoxDetail computeds
- Harden HeroCard — lock isPaused, add auth tests, correct icon/variant
- Harden BottomBar — add isInCall computed, auth tests, remove redundant if
- Guard QuickPlaySheet tab selection and test back()
- Use grid layout on BottomBar for uniform mobile spacing
- Rename Stream YouTube label to Playlist JW Stream
- Force details open on desktop via Alpine and move action bar under header
- Address PR review — gate QuickPlaySheet, HeroCard action permissions, @once style, DRY accordions, extra auth tests
- Consolidate QuickPlaySheetTest imports and add authorize() to BottomBar::openNew()

### Features

- Add Docker development environment with FrankenPHP
- Add MeetingType enum
- Add MeetingJoinMode enum
- Add Congregation model with scheduling logic
- Add congregation_id FK to recipients
- Add meeting_join_mode and notifications to OnesiBox
- Add MeetingInstance model with factory
- Add MeetingAttendanceStatus enum and missing test files
- Add MeetingAttendance model with factory
- Add meetings:check-upcoming scheduler command
- Add meetings:auto-join scheduler command
- Add meetings:cleanup command for stale instances
- Register meeting scheduler commands
- Complete meeting attendance on heartbeat meeting exit
- Install telegram notification channel
- Add MeetingUpcomingNotification with database, broadcast, and telegram channels
- Add Filament CongregationResource with form and table
- Add app_version badge column to OnesiBox admin table
- Add MeetingSchedule Livewire component with join/skip/adhoc
- Wire MeetingSchedule into OnesiBox detail page
- Add git-cliff configuration for automatic changelog
- Migrate changelog workflow to git-cliff
- Expose congregation assignment in recipient UI
- Add error_code column for granular error tracking
- Accept and persist error_code via API
- Broadcast PlaybackEventReceived for reactive UI
- Add PlayStreamItem case to CommandType enum
- Add JwStreamUrl validation rule for stream.jw.org URLs
- Add sendStreamItemCommand to OnesiBoxCommandService
- StreamPlayer skeleton with state restoration from commands
- Add playFromStart/next/previous/stop methods to StreamPlayer
- Add Echo listener and dismissError to StreamPlayer
- Add full Blade view for StreamPlayer component
- Mount StreamPlayer Livewire component in OnesiBox detail
- Add sendPauseCommand to OnesiBoxCommandService
- Add sendResumeCommand to OnesiBoxCommandService
- Add heroState computed to OnesiBoxDetail
- Add isInCall, isMediaPaused, accordionDefaults computeds
- Add HeroCard skeleton with idle variant
- Render media variant with progress bar in HeroCard
- Render call variant in HeroCard
- Render offline variant in HeroCard
- Add pause/resume/stop/leaveZoom actions to HeroCard
- Add BottomBar skeleton with visibility gating
- Wire Stop slot in BottomBar
- Wire Volume slot via modal hosting VolumeControl
- Wire New and Call slots in BottomBar
- Add QuickPlaySheet skeleton with initial menu
- Restructure onesi-box-detail view with hero, accordion body, bottom bar
- Responsive desktop layout with inline actions and open accordions
- Upgrade spatie/laravel-activitylog v4 → v5

### Refactoring

- Apply rector, pint, and phpstan fixes
- Move session creation to QuickPlaySheet, gate session accordion
- Simplify session creation hierarchy on mobile

### Build

- Bump laravel/boost from 2.2.1 to 2.2.3
- Bump sentry/sentry-laravel from 4.21.0 to 4.21.1
- Bump livewire/flux from 2.12.2 to 2.13.0
- Bump driftingly/rector-laravel from 2.1.9 to 2.1.12
- Bump spatie/laravel-ray from 1.43.6 to 1.43.7
- Bump filament/filament from 5.2.4 to 5.3.2
- Bump sentry/sentry-laravel from 4.21.1 to 4.22.0
- Bump filament/filament from 5.3.2 to 5.3.5
- Bump laravel/pulse from 1.6.0 to 1.7.0
- Bump barryvdh/laravel-debugbar from 4.0.10 to 4.1.3
- Bump laravel/framework from 12.53.0 to 12.54.1
- Bump laravel/boost from 2.2.3 to 2.3.1
- Bump laravel/fortify from 1.35.0 to 1.36.1
- Bump laravel/pint from 1.27.1 to 1.29.0
- Bump pestphp/pest from 4.4.1 to 4.4.2
- Bump rector/rector from 2.3.8 to 2.3.9
- Bump filament/filament from 5.3.5 to 5.4.1
- Bump laravel/reverb from 1.8.0 to 1.8.1
- Bump laravel/boost from 2.3.1 to 2.3.4
- Bump dedoc/scramble from 0.13.14 to 0.13.16
- Bump laravel/pulse from 1.7.0 to 1.7.2
- Bump laravel/sail from 1.53.0 to 1.54.0
- Bump driftingly/rector-laravel from 2.1.12 to 2.2.0
- Bump blade-ui-kit/blade-heroicons from 2.6.0 to 2.7.0
- Bump sentry/sentry-laravel from 4.22.0 to 4.23.0
- Bump oltrematica/laravel-role-lite from 1.0.3 to 1.0.4
- Bump dev-to-geek/laravel-init from 0.2.0 to 0.2.1
- Bump laravel/fortify from 1.36.1 to 1.36.2
- Bump livewire/livewire from 4.2.1 to 4.2.2
- Bump livewire/flux from 2.13.0 to 2.13.1
- Bump laravel/boost from 2.3.4 to 2.4.1
- Bump sentry/sentry-laravel from 4.23.0 to 4.24.0
- Bump pestphp/pest from 4.4.2 to 4.4.3
- Bump laravel/sail from 1.54.0 to 1.55.0
- Bump dedoc/scramble from 0.13.16 to 0.13.17
- Bump laravel/reverb from 1.8.1 to 1.9.0
- Bump laravel/reverb from 1.9.0 to 1.10.0
- Bump filament/filament from 5.4.1 to 5.4.4
- Bump livewire/flux from 2.13.1 to 2.13.2
- Bump rector/rector from 2.3.9 to 2.4.0
- Bump livewire/livewire from 4.2.2 to 4.2.4
- Bump barryvdh/laravel-debugbar from 4.1.3 to 4.2.4
- Bump pestphp/pest from 4.4.3 to 4.4.5
- Bump laravel/sail from 1.55.0 to 1.56.0
- Bump rector/rector from 2.4.0 to 2.4.1
- Bump pestphp/pest from 4.4.5 to 4.5.0
- Bump pestphp/pest-plugin-browser from 4.3.0 to 4.3.1
- Bump laravel/boost from 2.4.1 to 2.4.3
- Bump laravel/pulse from 1.7.2 to 1.7.3
- Bump barryvdh/laravel-debugbar from 4.2.4 to 4.2.6
- Bump filament/filament from 5.4.4 to 5.5.0
- Bump sentry/sentry-laravel from 4.24.0 to 4.25.0
- Bump dedoc/scramble from 0.13.17 to 0.13.18
- Bump spatie/laravel-ray from 1.43.7 to 1.43.8
- Bump dedoc/scramble from 0.13.18 to 0.13.20
- Bump filament/filament from 5.5.0 to 5.5.2
- Bump larastan/larastan from 3.9.3 to 3.9.6
- Bump driftingly/rector-laravel from 2.2.0 to 2.3.0
- Bump laravel/tinker from 2.11.1 to 3.0.2
- Bump oltrematica/laravel-role-lite from 1.0.4 to 2.0.0
- Bump dependabot/fetch-metadata from 2.5.0 to 3.0.0
- Bump ramsey/composer-install from 3 to 4

## [0.8.9] - 2026-02-28

### Bug Fixes

- Use MySQL-compatible DATE_ADD in ExpirePlaybackSessionsCommand (#106)

### Build

- Bump laravel/reverb from 1.7.1 to 1.8.0
- Bump larastan/larastan from 3.9.2 to 3.9.3
- Bump sentry/sentry-laravel from 4.20.1 to 4.21.0
- Bump barryvdh/laravel-debugbar from 4.0.7 to 4.0.9
- Bump spatie/laravel-activitylog from 4.11.0 to 4.12.1
- Bump laravel/boost from 2.2.0 to 2.2.1
- Bump livewire/flux from 2.12.1 to 2.12.2
- Bump rector/rector from 2.3.7 to 2.3.8
- Bump spatie/laravel-login-link from 1.6.3 to 1.6.6
- Bump livewire/livewire from 4.1.4 to 4.2.1
- Bump laravel/fortify from 1.34.1 to 1.35.0
- Bump laravel/pulse from 1.5.0 to 1.6.0
- Bump filament/filament from 5.2.2 to 5.2.4

## [0.8.8] - 2026-02-27

### Bug Fixes

- LogViewer reads 'lines' array instead of nonexistent 'logs' key
- LogViewer reads 'lines' array instead of nonexistent 'logs' key

## [0.8.7] - 2026-02-27

### Bug Fixes

- Restrict viewApiDocs gate and use Roles enum in OnesiBoxPolicy
- Route volume commands through OnesiBoxCommandService

### Features

- Add session_id index and scheduled playback event pruning
- Add Reverb WebSocket monitoring to Pulse dashboard

### Performance

- Filter expired sessions in SQL instead of PHP
- Exclude telemetry fields from activity log on OnesiBox

### Build

- Bump laravel/framework from 12.51.0 to 12.52.0
- Bump livewire/flux from 2.12.0 to 2.12.1
- Bump dedoc/scramble from 0.13.13 to 0.13.14
- Bump spatie/laravel-ray from 1.43.5 to 1.43.6
- Bump pestphp/pest-plugin-laravel from 4.0.0 to 4.1.0
- Bump nunomaduro/collision from 8.8.3 to 8.9.1
- Bump pestphp/pest from 4.3.2 to 4.4.1
- Bump filament/filament from 5.2.1 to 5.2.2
- Bump rector/rector from 2.3.6 to 2.3.7
- Bump laravel/boost from 2.1.1 to 2.2.0
- Bump pestphp/pest-plugin-browser from 4.2.1 to 4.3.0

## [0.8.6] - 2026-02-20

### Bug Fixes

- Prevent duplicate advance in playlist mode

## [0.8.5] - 2026-02-17

### Features

- Include payload in NewCommand WebSocket broadcast

## [0.8.4] - 2026-02-16

### Bug Fixes

- Address code review findings

## [0.8.3] - 2026-02-16

### Bug Fixes

- Use serial_number instead of id for appliance WebSocket channel

## [0.8.2] - 2026-02-14

### Bug Fixes

- Dispatch WebSocket broadcast for volume commands

## [0.8.1] - 2026-02-14

### Bug Fixes

- Correct type handling in appliance broadcast channel authorization

## [0.8.0] - 2026-02-14

### Bug Fixes

- Address review findings — port mismatch, toOthers, allowed_origins
- PR review findings — ShouldBroadcastNow, broadcast resilience, docs
- Update test to match renamed log message (queued → dispatched)
- Add session expiration, clear stale status, and status-aware UI
- PR review findings — session race condition, SQL compat, title wipe

### Features

- Enable Reverb WebSocket broadcasting for appliance commands
- Store media position/duration and meeting URL/joined_at from heartbeat

### Build

- Bump dev-to-geek/laravel-init from 0.1.6 to 0.2.0

## [0.7.15] - 2026-02-08

### Features

- Add granular volume slider with debounce and touch-friendly controls
- Redesign volume control with visual bar, mute toggle and dropdown

## [0.7.14] - 2026-02-08

### Features

- Add 50% volume preset level

## [0.7.13] - 2026-02-08

### Bug Fixes

- Use custom orderQueryUsing for BelongsToMany role grouping
- Revert filters layout to default dropdown

### Features

- Improve user form layout with sections and account status
- Redesign user form layout and translate entire admin panel to Italian

### Refactoring

- Remove role grouping and display filters above table content

## [0.7.12] - 2026-02-08

### Bug Fixes

- Validate wifi signal_dbm must be negative (max:0)

### Features

- Improve admin panel dashboard, navigation and users table

### Refactoring

- Use event() helper instead of static dispatch

## [0.7.11] - 2026-02-07

### Bug Fixes

- Relax heartbeat validation for real-world sensor edge cases

## [0.7.10] - 2026-02-07

### Features

- Add mute (0%) button with speaker-x-mark icon to volume control

## [0.7.9] - 2026-02-07

### Bug Fixes

- Restrict volume levels to 60-100 range

## [0.7.8] - 2026-02-07

### Bug Fixes

- Update volume display in real-time on dashboard

## [0.7.7] - 2026-02-07

### Bug Fixes

- Resolve PHPStan errors and skip incompatible Rector rule

### Features

- Add timed video playlist sessions for OnesiBox

### Refactoring

- Improve DRY, fix N+1 queries, and apply Laravel idioms
- Wire Actions into controllers and use ApiErrorCode enum

## [0.7.3] - 2026-01-31

### Features

- Update

## [0.1.0] - 2026-01-22


