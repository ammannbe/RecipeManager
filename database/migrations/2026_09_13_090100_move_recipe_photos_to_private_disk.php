<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

/**
 * Recipe photos used to sit under the public disk and were served straight by the
 * web server. They now live on a private disk behind an authorized route.
 */
return new class extends Migration
{
    private string $public = 'app/public/recipes';

    private string $private = 'app/private/recipes';

    public function up(): void
    {
        $this->move(storage_path($this->public), storage_path($this->private));
    }

    public function down(): void
    {
        $this->move(storage_path($this->private), storage_path($this->public));
    }

    private function move(string $from, string $to): void
    {
        if (app()->runningUnitTests() || ! File::isDirectory($from)) {
            return;
        }

        File::ensureDirectoryExists($to);

        foreach (File::directories($from) as $directory) {
            $target = $to.'/'.basename($directory);

            // A previous partial run may already have moved this recipe.
            if (File::isDirectory($target)) {
                continue;
            }

            File::moveDirectory($directory, $target);
        }
    }
};
