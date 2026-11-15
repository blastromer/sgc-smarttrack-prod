<?php

namespace Tests\Unit;

use App\Support\MovDraft;
use App\Support\MovTemplates;
use Tests\TestCase;

class MovDraftTest extends TestCase
{
    public function test_documentation_narrative_blanks_include_overview_and_objectives(): void
    {
        $path = MovTemplates::path('documentation-sgc');
        $this->assertNotNull($path);

        $blanks = MovDraft::narrativeBlanks($path);

        $this->assertContains('[Insert project overview, including the date, venue, attendees, & leading organization]', $blanks);
        $this->assertContains('Obj1', $blanks);
        $this->assertContains('Obj2', $blanks);
        $this->assertContains('Obj3', $blanks);
        $this->assertContains('Obj4', $blanks);
        $this->assertTrue(MovDraft::isNarrative('Obj1'));
        $this->assertFalse(MovDraft::isNarrative('[insert full name & signature, SGC Secretary]'));
    }
}
