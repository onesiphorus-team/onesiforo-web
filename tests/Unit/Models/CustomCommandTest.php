<?php

declare(strict_types=1);

use App\Models\CustomCommand;
use App\Models\OnesiBox;

it('casts static_args to array', function (): void {
    $box = OnesiBox::factory()->create();
    $cmd = CustomCommand::factory()->forBox($box)->create([
        'static_args' => ['--foo', 'bar'],
    ]);

    expect($cmd->fresh()->static_args)->toBe(['--foo', 'bar']);
});

it('enabled scope filters out disabled commands', function (): void {
    $box = OnesiBox::factory()->create();
    CustomCommand::factory()->forBox($box)->create(['is_enabled' => true]);
    CustomCommand::factory()->forBox($box)->disabled()->create();

    expect(CustomCommand::query()->enabled()->count())->toBe(1);
});

it('ordered scope sorts by sort_order then name', function (): void {
    $box = OnesiBox::factory()->create();
    CustomCommand::factory()->forBox($box)->create(['name' => 'B', 'sort_order' => 1]);
    CustomCommand::factory()->forBox($box)->create(['name' => 'A', 'sort_order' => 1]);
    CustomCommand::factory()->forBox($box)->create(['name' => 'C', 'sort_order' => 0]);

    $names = CustomCommand::query()->ordered()->pluck('name')->all();

    expect($names)->toBe(['C', 'A', 'B']);
});

it('exposes the script_name regex matching only safe basenames', function (): void {
    $re = CustomCommand::SCRIPT_NAME_REGEX;

    expect(preg_match($re, 'to-box.sh'))->toBe(1);
    expect(preg_match($re, 'do_something_v2.sh'))->toBe(1);
    expect(preg_match($re, 'simple.sh'))->toBe(1);

    expect(preg_match($re, '../escape.sh'))->toBe(0);
    expect(preg_match($re, 'sub/dir/file.sh'))->toBe(0);
    expect(preg_match($re, 'no-extension'))->toBe(0);
    expect(preg_match($re, 'has space.sh'))->toBe(0);
});
