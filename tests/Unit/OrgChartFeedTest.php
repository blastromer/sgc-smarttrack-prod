<?php

namespace Tests\Unit;

use App\Models\SchoolFormProfile;
use App\Models\User;
use App\Support\DocxHtmlPreview;
use App\Support\MovTemplates;
use App\Support\OrgChartFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgChartFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_puts_form_data_officers_into_chart_boxes_and_preview(): void
    {
        $user = User::factory()->create([
            'role' => 'school',
            'school_code' => '654321',
            'school_name' => 'Sample ES',
        ]);

        SchoolFormProfile::query()->create([
            'school_code' => '654321',
            'school_name' => 'Sample ES',
            'school_head_name' => 'Ana Principal',
            'co_chair_designated' => 'Ben Designated',
            'co_chair_elected' => 'Cara Elected',
            'secretary_name' => 'Dina Secretary',
        ]);

        $source = MovTemplates::path('org-chart-sgc');
        $this->assertNotNull($source);
        $tmp = tempnam(sys_get_temp_dir(), 'org-chart');
        $this->assertNotFalse($tmp);
        copy($source, $tmp);

        OrgChartFeed::apply($tmp, $user);
        $html = DocxHtmlPreview::fromPath($tmp);
        @unlink($tmp);

        $this->assertStringContainsString('Ana Principal', $html);
        $this->assertStringContainsString('School Principal', $html);
        $this->assertStringContainsString('Ben Designated', $html);
        $this->assertStringContainsString('Cara Elected', $html);
        $this->assertStringContainsString('Dina Secretary', $html);
        $this->assertStringContainsString('Voting Council Members', $html);
    }

    public function test_preview_renders_org_chart_textbox_roles_without_fill(): void
    {
        $source = MovTemplates::path('org-chart-sgc');
        $this->assertNotNull($source);

        $html = DocxHtmlPreview::fromPath($source);

        $this->assertStringContainsString('School Principal', $html);
        $this->assertStringContainsString('Designated Co-Chairperson', $html);
        $this->assertStringContainsString('Elected Co-Chairperson', $html);
    }
}
