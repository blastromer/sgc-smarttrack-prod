<?php

namespace App\Support;

use App\Models\Assessment;
use App\Models\Mov;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OperationalReset
{
    public static function run(): void
    {
        $paths = Mov::query()->whereNotNull('path')->pluck('path');

        DB::transaction(function () {
            Assessment::query()->delete();
            User::query()->whereIn('role', ['school', 'school_head'])->delete();
            DB::table('notifications')->delete();
        });

        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }
    }
}
