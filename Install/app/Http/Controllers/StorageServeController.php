<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\ServeFile;
use Illuminate\Http\Request;

/**
 * Serves files from the "local" storage disk (replaces Laravel's closure-based
 * storage route so Ziggy's route reflection does not throw on closures).
 */
class StorageServeController extends Controller
{
    private const DISK = 'local';

    public function __invoke(Request $request, string $path): mixed
    {
        $config = config('filesystems.disks.'.self::DISK, []);
        $isProduction = app()->isProduction();

        return (new ServeFile(self::DISK, $config, $isProduction))($request, $path);
    }
}
