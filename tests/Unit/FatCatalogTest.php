<?php

namespace Tests\Unit;

use App\Support\FatCatalog;
use Tests\TestCase;

class FatCatalogTest extends TestCase
{
    public function test_national_fat_catalog_has_twelve_scored_primaries(): void
    {
        $indicators = FatCatalog::indicators();

        $this->assertCount(12, $indicators);
        $this->assertSame('Members informed of roles', $indicators[0]['title']);
        $this->assertSame('Stakeholder-initiated programs', FatCatalog::indicator('FI6')['title']);
        $this->assertSame('Inclusive stakeholder representation', FatCatalog::indicator('FI8')['title']);
        $this->assertSame('Participation in stakeholder activities', FatCatalog::indicator('FI9')['title']);
        $this->assertSame('Organized discussions and forums', FatCatalog::indicator('FI10')['title']);
        $this->assertSame('Suggestions to improve SIP/AIP', FatCatalog::indicator('FI12')['title']);
        $this->assertSame('Feedback Mechanism', FatCatalog::indicator('FI9')['group_label']);
        $this->assertCount(1, FatCatalog::minimumSlots('FI1'));
        $this->assertCount(2, FatCatalog::minimumSlots('FI6'));
        $this->assertCount(2, FatCatalog::minimumSlots('FI7'));
        $this->assertCount(2, FatCatalog::minimumSlots('FI10'));
        $this->assertCount(2, FatCatalog::minimumSlots('FI11'));
        $this->assertNotEmpty(FatCatalog::optionalSlots('FI1'));
        $this->assertSame('FI1A', FatCatalog::indicator('FI1')['mov_code']);
    }
}
