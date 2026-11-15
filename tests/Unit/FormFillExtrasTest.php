<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\DocxHtmlPreview;
use App\Support\FormFill;
use App\Support\MovTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormFillExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_extras_replace_placeholders_that_use_ampersand_entities(): void
    {
        $path = MovTemplates::path('documentation-sgc');
        $this->assertNotNull($path);

        $user = User::factory()->create([
            'role' => 'school',
            'school_code' => '654321',
            'school_name' => 'Sample ES',
        ]);

        $tmp = FormFill::docx($user, $path, [
            '[Insert project overview, including the date, venue, attendees, & leading organization]' => 'Overview sample covering date, venue, attendees, and lead organization.',
            'Obj1' => 'Strengthen stakeholder coordination',
            'Obj2' => 'Align SIP and AIP priorities',
            'Obj3' => 'Document agreements for follow through',
            'Obj4' => 'Mobilize partners for school programs',
        ]);

        $html = DocxHtmlPreview::fromPath($tmp);
        @unlink($tmp);

        $this->assertStringContainsString('Overview sample covering date, venue, attendees, and lead organization.', $html);
        $this->assertStringContainsString('Strengthen stakeholder coordination', $html);
        $this->assertStringNotContainsString('[Insert project overview, including the date, venue, attendees, & leading organization]', $html);
        $this->assertStringNotContainsString('Obj1', $html);
        $this->assertStringNotContainsString('Obj4', $html);
    }
}
