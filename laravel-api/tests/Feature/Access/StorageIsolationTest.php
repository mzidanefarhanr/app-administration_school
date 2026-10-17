<?php

use Illuminate\Support\Facades\Storage;

test('testing filesystem disk is isolated testing disk under framework testing', function () {
    $defaultDisk = config('filesystems.default');
    expect($defaultDisk)->toBe('testing');

    $root = config('filesystems.disks.testing.root');
    $safePrefix = str_replace('\\', '/', storage_path('framework/testing'));

    expect(str_starts_with(str_replace('\\', '/', $root), $safePrefix))->toBeTrue();
    expect(str_contains(str_replace('\\', '/', $root), 'app/public'))->toBeFalse();
});

test('storage write read delete operates cleanly on testing disk', function () {
    $disk = Storage::disk();

    $testFile = 'isolation_check_'.bin2hex(random_bytes(4)).'.txt';
    $content = 'isolated-content-'.time();

    $disk->put($testFile, $content);
    expect($disk->exists($testFile))->toBeTrue();
    expect($disk->get($testFile))->toBe($content);

    $disk->delete($testFile);
    expect($disk->exists($testFile))->toBeFalse();
});

test('guard rejects unsafe non-testing storage disk configuration', function () {
    $unsafeApp = clone app();
    $unsafeApp['config']->set('filesystems.default', 'public');
    $unsafeApp['config']->set('filesystems.disks.public.root', public_path('storage'));

    expect(function () use ($unsafeApp) {
        $this->guardAgainstUnsafeStorage($unsafeApp);
    })->toThrow(RuntimeException::class, 'Refused to run tests against an unsafe storage disk');
});
