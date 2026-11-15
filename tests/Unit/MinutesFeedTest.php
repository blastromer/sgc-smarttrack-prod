<?php

namespace Tests\Unit;

use App\Support\DocxHtmlPreview;
use App\Support\MinutesFeed;
use App\Support\MovTemplates;
use Tests\TestCase;

class MinutesFeedTest extends TestCase
{
    public function test_sample_minutes_fill_the_present_table_and_mark_absent_none(): void
    {
        $attendance = MinutesFeed::attendance();

        $this->assertCount(7, $attendance['present']);
        $this->assertSame('MAYDE C. CRUZ', $attendance['present'][0]['name']);
        $this->assertSame('School Principal', $attendance['present'][0]['position']);
        $this->assertSame([], $attendance['absent']);

        $source = MovTemplates::path('minutes-sgc');
        $this->assertNotNull($source);
        $tmp = tempnam(sys_get_temp_dir(), 'minutes');
        $this->assertNotFalse($tmp);
        copy($source, $tmp);

        MinutesFeed::apply($tmp);
        $html = DocxHtmlPreview::fromPath($tmp);
        @unlink($tmp);

        $this->assertStringContainsString('MAYDE C. CRUZ', $html);
        $this->assertStringContainsString('MARTHA KAMILA I. ASA', $html);
        $this->assertStringContainsString('Local Government Unit (Barangay)', $html);
        $this->assertStringContainsString('None', $html);
        $this->assertCount(5, MinutesFeed::agenda());
        $this->assertStringContainsString('Term 2 Re-affirmation', $html);
        $this->assertStringContainsString('Quarterly Progress Report', $html);
        $this->assertStringNotContainsString('[Agenda 1]', $html);
    }
}
