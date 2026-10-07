<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteAppearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteAppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_encoder_can_open_configuration(): void
    {
        $encoder = User::factory()->create(['role' => 'school', 'status' => 'active']);

        $this->actingAs($encoder)
            ->get('/configuration')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Configuration')
                ->where('sgc.appearance.current.theme', 'night')
                ->where('sgc.appearance.can_publish', false));
    }

    public function test_encoder_can_save_personal_appearance(): void
    {
        $encoder = User::factory()->create(['role' => 'school', 'status' => 'active']);

        $this->actingAs($encoder)
            ->from('/configuration')
            ->post('/configuration', [
                'theme' => 'day',
                'accent' => '#1a7a75',
                'font' => 'source',
                'text_size' => 'lg',
                'density' => 'compact',
            ])
            ->assertRedirect('/configuration')
            ->assertCookie(SiteAppearance::COOKIE);
    }

    public function test_super_can_publish_site_default(): void
    {
        Storage::fake('local');
        $super = User::factory()->create([
            'role' => 'super',
            'status' => 'active',
            'school_name' => null,
            'school_code' => null,
        ]);

        $this->actingAs($super)
            ->from('/configuration')
            ->post('/configuration', [
                'theme' => 'forest',
                'accent' => '#3cb88a',
                'font' => 'atkinson',
                'text_size' => 'md',
                'density' => 'comfortable',
                'publish' => true,
            ])
            ->assertRedirect('/configuration');

        $this->assertSame('forest', SiteAppearance::site()['theme']);
        $this->assertSame('#3cb88a', SiteAppearance::site()['accent']);
    }

    public function test_encoder_cannot_publish_site_default(): void
    {
        Storage::fake('local');
        $encoder = User::factory()->create(['role' => 'school', 'status' => 'active']);

        $this->actingAs($encoder)
            ->post('/configuration', [
                'theme' => 'contrast',
                'accent' => '#3dffd4',
                'font' => 'georgia',
                'text_size' => 'xl',
                'density' => 'compact',
                'publish' => true,
            ])
            ->assertRedirect();

        $this->assertFalse(Storage::disk('local')->exists('site-appearance.json'));
    }
}
