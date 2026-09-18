<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\User;
use App\Support\AssessmentEngine;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'romer.necesario@sgcsmarttrack.gov.ph'],
            [
                'name' => 'Romer Necesario',
                'password' => 'SmartTrack2026',
                'role' => 'super',
                'status' => 'active',
                'office' => 'System',
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'jovel.oberio@deped.gov.ph'],
            [
                'name' => 'Jovel J. Oberio',
                'password' => 'SmartTrack2026',
                'role' => 'division',
                'status' => 'active',
                'office' => 'SGOD / SGC Focal',
                'email_verified_at' => now(),
            ]
        );

        $head = User::query()->updateOrCreate(
            ['email' => 'school.head@deped.gov.ph'],
            [
                'name' => 'Maria Santos',
                'password' => 'SmartTrack2026',
                'role' => 'school_head',
                'status' => 'active',
                'school_name' => 'Sample Elementary School',
                'school_code' => '123456',
                'position' => 'School Head',
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'school.encoder@deped.gov.ph'],
            [
                'name' => 'Ana Reyes',
                'password' => 'SmartTrack2026',
                'role' => 'school',
                'status' => 'active',
                'school_name' => 'Sample Elementary School',
                'school_code' => '123456',
                'position' => 'SGC Coordinator',
                'email_verified_at' => now(),
            ]
        );

        Cycle::query()->updateOrCreate(
            ['name' => '2026 SGC Functionality Assessment'],
            [
                'level' => 'Public Elementary',
                'opens_at' => now()->subDays(17)->toDateString(),
                'deadline_at' => now()->addDays(12)->toDateString(),
                'status' => 'open',
            ]
        );

        AssessmentEngine::forSchool($head);
        $this->seedReturnedPacket($head);
    }

    private function seedReturnedPacket(User $school): void
    {
        $assessment = AssessmentEngine::forSchool($school);
        if (! $assessment || $assessment->indicators()->whereNotNull('answer')->exists()) {
            return;
        }

        foreach (['FI1', 'FI2', 'FI3', 'FI4', 'FI5', 'FI6', 'FI7', 'FI8', 'FI9', 'FI10'] as $code) {
            $assessment->indicators()->where('code', $code)->update(['answer' => 'yes']);
        }
        foreach (['FI11', 'FI12'] as $code) {
            $assessment->indicators()->where('code', $code)->update(['answer' => 'no']);
        }

        AssessmentEngine::ensureRequiredMovs($assessment->fresh());
        $assessment->load('movs');

        foreach ($assessment->movs as $mov) {
            $path = 'movs/'.$assessment->id.'/'.$mov->code.'.pdf';
            \Illuminate\Support\Facades\Storage::disk('local')->put($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
            $returned = $mov->code === 'FI3A';
            $mov->update([
                'original_name' => $returned ? 'FI3A-Minimum-Resolution.pdf' : $mov->code.'.pdf',
                'path' => $path,
                'mime' => 'application/pdf',
                'size' => $returned ? 680000 : 210000,
                'status' => $returned ? 'returned' : 'valid',
                'return_reason' => $returned ? 'Minutes do not show 50%+1 quorum' : null,
            ]);
        }

        $assessment->update([
            'status' => 'returned',
            'submitted_at' => now()->subDay(),
            'qa_certified_at' => null,
        ]);

        $fi3a = $assessment->movs()->where('code', 'FI3A')->first();
        if ($fi3a) {
            $school->notify(new \App\Notifications\MovReturned($fi3a));
        }
    }
}
