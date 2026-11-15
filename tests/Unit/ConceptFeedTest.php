<?php

namespace Tests\Unit;

use App\Support\ConceptFeed;
use App\Support\DocxHtmlPreview;
use App\Support\MovTemplates;
use Tests\TestCase;

class ConceptFeedTest extends TestCase
{
    public function test_apply_fills_header_objectives_activities_and_budget(): void
    {
        $source = MovTemplates::path('concept-note-sgc');
        $this->assertNotNull($source);

        $tmp = tempnam(sys_get_temp_dir(), 'concept');
        $this->assertNotFalse($tmp);
        copy($source, $tmp);

        ConceptFeed::apply($tmp, [
            'title' => 'Project Learning Continuity Kits',
            'supervisor' => 'School Head',
            'organization' => 'School Governance Council',
            'start_date' => 'August 1, 2026',
            'end_date' => 'March 30, 2027',
            'background' => 'This concept note proposes a school-community support activity for SY 2026-2027.',
            'objectives' => [
                'Strengthen stakeholder coordination',
                'Align SIP and AIP priorities',
                'Mobilize partners for learning continuity',
            ],
            'activities' => [
                'Conduct planning workshop with SGC officers',
                'Prepare learner support kits',
                'Monitor distribution during suspensions',
            ],
            'accomplishments' => [
                'Approved concept note and activity calendar',
                'Assembled continuity kits for priority learners',
                'Documented partner commitments',
            ],
            'indicators' => [
                'Concept note endorsed by SGC officers',
                'Kits ready within 24 hours of suspension',
                'Partner support logged each quarter',
            ],
            'budget' => [
                ['item' => 'Printing of modules', 'quantity' => '100', 'unit_price' => '10', 'total' => '1000'],
                ['item' => 'Bond paper', 'quantity' => '20', 'unit_price' => '250', 'total' => '5000'],
                ['item' => 'Transport support', 'quantity' => '1', 'unit_price' => '3000', 'total' => '3000'],
            ],
        ]);

        $html = DocxHtmlPreview::fromPath($tmp);
        @unlink($tmp);

        $this->assertStringContainsString('Project Learning Continuity Kits', $html);
        $this->assertStringContainsString('School Head', $html);
        $this->assertStringContainsString('August 1, 2026', $html);
        $this->assertStringContainsString('This concept note proposes a school-community support activity', $html);
        $this->assertStringContainsString('Strengthen stakeholder coordination', $html);
        $this->assertStringContainsString('Conduct planning workshop with SGC officers', $html);
        $this->assertStringContainsString('Printing of modules', $html);
        $this->assertStringContainsString('9,000', $html); // 1000 + 5000 + 3000 grand total
        $this->assertStringNotContainsString('[Insert exhaustive background', $html);
        $this->assertDoesNotMatchRegularExpression('/>(Obj|MA|EA|IA)\d+</', $html);
        $this->assertStringNotContainsString('>Specific activity<', $html);
    }
}
