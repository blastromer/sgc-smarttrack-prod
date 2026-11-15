<?php

namespace Tests\Unit;

use App\Support\DocxHtmlPreview;
use App\Support\MovTemplates;
use App\Support\ResolutionFeed;
use Tests\TestCase;

class ResolutionFeedTest extends TestCase
{
    public function test_apply_fills_subject_whereas_resolved_and_date_by_position(): void
    {
        $source = MovTemplates::path('resolution-sgc');
        $this->assertNotNull($source);

        $tmp = tempnam(sys_get_temp_dir(), 'resolution');
        $this->assertNotFalse($tmp);
        copy($source, $tmp);

        $whereas = [];
        for ($i = 1; $i <= ResolutionFeed::WHEREAS_COUNT; $i++) {
            $whereas[] = 'Distinct whereas clause number '.$i;
        }

        ResolutionFeed::apply($tmp, [
            'subject' => 'CONTINUOUS SIP AND AIP IMPLEMENTATION',
            'whereas' => $whereas,
            'resolved' => [
                'Adopt the continuous SIP monitoring plan for the school year',
                'Endorse quarterly progress reporting to stakeholders',
            ],
            'day' => '15',
            'month' => 'November',
            'year' => '2026',
        ]);

        $html = DocxHtmlPreview::fromPath($tmp);
        @unlink($tmp);

        $this->assertStringContainsString('CONTINUOUS SIP AND AIP IMPLEMENTATION', $html);
        $this->assertStringContainsString('Distinct whereas clause number 1', $html);
        $this->assertStringContainsString('Distinct whereas clause number 9', $html);
        $this->assertStringContainsString('Adopt the continuous SIP monitoring plan for the school year', $html);
        $this->assertStringContainsString('Endorse quarterly progress reporting to stakeholders', $html);
        $this->assertStringContainsString('this 15 of November, 2026', $html);
        $this->assertStringNotContainsString('[State the Subject]', $html);
        $this->assertStringNotContainsString('[State details]', $html);
        $this->assertStringNotContainsString('[day]', $html);
    }
}
