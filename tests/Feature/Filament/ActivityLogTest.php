<?php

declare(strict_types=1);

use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Pages\ViewActivity;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Oltrematica\RoleLite\Models\Role;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    // Ensure roles exist
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);
    Role::query()->firstOrCreate(['name' => 'caregiver']);

    // Create and login as super-admin
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);
});

it('can render the activity list page', function (): void {
    $this->get(ActivityResource::getUrl('index'))
        ->assertSuccessful();
});

it('can list activities', function (): void {
    // Create some activity
    activity()
        ->performedOn($this->admin)
        ->causedBy($this->admin)
        ->log('Test activity');

    livewire(ListActivities::class)
        ->assertCanSeeTableRecords(Activity::all());
});

it('displays date column', function (): void {
    activity()->log('Test activity');

    livewire(ListActivities::class)
        ->assertTableColumnExists('created_at');
});

it('displays causer column', function (): void {
    activity()
        ->causedBy($this->admin)
        ->log('Test activity');

    livewire(ListActivities::class)
        ->assertTableColumnExists('causer.name');
});

it('displays description column', function (): void {
    activity()->log('Test description');

    livewire(ListActivities::class)
        ->assertTableColumnExists('description');
});

it('displays subject type column', function (): void {
    activity()
        ->performedOn($this->admin)
        ->log('Test activity');

    livewire(ListActivities::class)
        ->assertTableColumnExists('subject_type');
});

it('can filter by causer', function (): void {
    $otherUser = User::factory()->create();

    // Create activity by current admin
    activity()
        ->causedBy($this->admin)
        ->log('Admin activity');

    // Create activity by other user
    activity()
        ->causedBy($otherUser)
        ->log('Other user activity');

    livewire(ListActivities::class)
        ->filterTable('causer', $this->admin->id)
        ->assertCanSeeTableRecords(
            Activity::query()->where('causer_id', $this->admin->id)->get()
        );
});

it('can filter by description', function (): void {
    activity()->log('User created');
    activity()->log('User updated');

    livewire(ListActivities::class)
        ->filterTable('description', 'User created')
        ->assertCanSeeTableRecords(
            Activity::query()->where('description', 'User created')->get()
        );
});

it('can search activities', function (): void {
    activity()->log('Searchable activity');
    activity()->log('Other activity');

    livewire(ListActivities::class)
        ->searchTable('Searchable')
        ->assertCanSeeTableRecords(
            Activity::query()->where('description', 'like', '%Searchable%')->get()
        );
});

it('logs user creation activity', function (): void {
    $user = User::factory()->create();

    // The LogsActivityAllDirty trait should log the creation
    expect(Activity::query()->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->exists()
    )->toBeTrue();
});

it('logs user update activity', function (): void {
    $user = User::factory()->create();
    $user->update(['name' => 'Updated Name']);

    expect(Activity::query()->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('description', 'updated')
        ->exists()
    )->toBeTrue();
});

it('can render the activity view page', function (): void {
    $activity = activity()
        ->performedOn($this->admin)
        ->causedBy($this->admin)
        ->log('Test activity');

    $this->get(ActivityResource::getUrl('view', ['record' => $activity]))
        ->assertSuccessful();
});

it('can view an activity with nested properties', function (): void {
    // Create activity with nested properties (like user update)
    $activity = activity()
        ->performedOn($this->admin)
        ->causedBy($this->admin)
        ->withProperties([
            'old' => ['name' => 'Old Name', 'email' => 'old@example.com'],
            'attributes' => ['name' => 'New Name', 'email' => 'new@example.com'],
        ])
        ->log('updated');

    livewire(ViewActivity::class, ['record' => $activity->id])
        ->assertSuccessful();
});

it('can view an activity with simple string properties', function (): void {
    // Create activity with simple properties
    $activity = activity()
        ->causedBy($this->admin)
        ->withProperties([
            'role' => 'admin',
            'invited_by' => 'test@example.com',
        ])
        ->log('User invited');

    livewire(ViewActivity::class, ['record' => $activity->id])
        ->assertSuccessful();
});

it('can view an activity without properties', function (): void {
    $activity = activity()
        ->causedBy($this->admin)
        ->log('Simple action');

    // This activity has empty properties, so Details section is hidden
    $this->get(ActivityResource::getUrl('view', ['record' => $activity]))
        ->assertSuccessful();
});

it('has an index on the activity log creation date used by the default sort', function (): void {
    expect(Schema::hasIndex('activity_log', ['created_at']))->toBeTrue();
});

it('does not rescan the activity log for the action filter options on every render', function (): void {
    activity()->log('Test activity');

    $scans = 0;
    DB::listen(function (QueryExecuted $query) use (&$scans): void {
        if (str_contains($query->sql, 'distinct') && str_contains($query->sql, 'description')) {
            $scans++;
        }
    });

    livewire(ListActivities::class)->assertSuccessful();
    livewire(ListActivities::class)->assertSuccessful();

    expect($scans)->toBe(1);
});

it('still offers every logged action in the action filter', function (): void {
    activity()->log('First action');
    activity()->log('Second action');

    livewire(ListActivities::class)
        ->filterTable('description', 'Second action')
        ->assertCanSeeTableRecords(Activity::query()->where('description', 'Second action')->get())
        ->assertCanNotSeeTableRecords(Activity::query()->where('description', 'First action')->get());

    expect(ActivitiesTable::descriptionOptions())
        ->toHaveKeys(['First action', 'Second action']);
});

it('can run the creation date index migration where the index already exists', function (): void {
    $migration = require database_path('migrations/2026_11_14_223154_add_created_at_index_to_activity_log_table.php');

    expect(Schema::hasIndex('activity_log', ['created_at']))->toBeTrue();

    $migration->up();

    expect(Schema::hasIndex('activity_log', ['created_at']))->toBeTrue();
});
