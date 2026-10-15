<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_privacy_policy_is_public_and_describes_google_drive(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy policy for SGC SmartTrack', false)
            ->assertSee('https://www.googleapis.com/auth/drive', false)
            ->assertSee('We do not use Google user data for advertising', false);
    }
}
