<?php

namespace Tests\Unit;

use App\Support\ActionPlanFeed;
use App\Support\DocxHtmlPreview;
use App\Support\MovTemplates;
use Tests\TestCase;

class ActionPlanFeedTest extends TestCase
{
    public function test_apply_fills_action_plan_table_rows(): void
    {
        $source = MovTemplates::path('action-plan-sgc');
        $this->assertNotNull($source);

        $tmp = tempnam(sys_get_temp_dir(), 'action-plan');
        $this->assertNotFalse($tmp);
        copy($source, $tmp);

        $rows = [];
        for ($i = 1; $i <= ActionPlanFeed::ROW_COUNT; $i++) {
            $rows[] = [
                'area' => 'Area '.$i,
                'need' => 'Need '.$i,
                'objective' => 'Objective '.$i,
                'activities' => 'Activity '.$i,
                'outputs' => 'Output '.$i,
                'timeframe' => 'Q'.$i.' SY 2026-2027',
                'persons' => 'SGC Officers',
                'materials' => 'Meeting kit '.$i,
            ];
        }

        ActionPlanFeed::apply($tmp, $rows);
        $html = DocxHtmlPreview::fromPath($tmp);
        @unlink($tmp);

        $this->assertStringContainsString('Area 1', $html);
        $this->assertStringContainsString('Need 5', $html);
        $this->assertStringContainsString('Objective 3', $html);
        $this->assertStringContainsString('Activity 2', $html);
        $this->assertStringContainsString('Output 4', $html);
        $this->assertStringContainsString('Meeting kit 1', $html);
        $this->assertStringContainsString('AREAS OF CONCERN', $html);
    }
}
