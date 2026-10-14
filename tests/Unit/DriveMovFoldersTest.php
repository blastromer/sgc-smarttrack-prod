<?php

namespace Tests\Unit;

use App\Support\DriveMovFolders;
use App\Support\FatCatalog;
use Tests\TestCase;

class DriveMovFoldersTest extends TestCase
{
    public function test_catalog_has_forty_four_folders(): void
    {
        $this->assertCount(44, DriveMovFolders::catalog());
    }

    public function test_every_fat_slot_maps_to_a_drive_folder(): void
    {
        foreach (FatCatalog::indicators() as $indicator) {
            foreach ([...FatCatalog::minimumSlots($indicator['code']), ...FatCatalog::optionalSlots($indicator['code'])] as $slot) {
                $this->assertNotNull(
                    DriveMovFolders::urlForSlot($slot['code']),
                    $slot['code'].' should open a Drive folder'
                );
            }
        }

        $this->assertNotNull(DriveMovFolders::urlForSlot('Validity'));
    }

    public function test_minimum_and_additional_use_different_folders(): void
    {
        $this->assertNotSame(
            DriveMovFolders::urlForSlot('FI1A'),
            DriveMovFolders::urlForSlot('FI1A-ADD')
        );
        $this->assertStringContainsString('1Hn9BH4B8IgX8Mp7OYjLkV934TsarBvjg', (string) DriveMovFolders::urlForSlot('FI1A'));
        $this->assertStringContainsString('usp=drive_copy', (string) DriveMovFolders::urlForSlot('FI1A'));
    }

    public function test_folder_name_strips_slashes(): void
    {
        $this->assertSame('Cadiz Elementary School', DriveMovFolders::folderName('  Cadiz Elementary School  '));
        $this->assertSame('North - Cadiz', DriveMovFolders::folderName('North/Cadiz'));
    }

    public function test_second_minimum_slots_use_the_minimum_folder(): void
    {
        $this->assertSame(DriveMovFolders::urlForSlot('FI6A'), DriveMovFolders::urlForSlot('FI6A-2'));
        $this->assertSame(DriveMovFolders::urlForSlot('FI11A'), DriveMovFolders::urlForSlot('FI11A-2'));
        $this->assertSame(DriveMovFolders::urlForSlot('FI7A'), DriveMovFolders::urlForSlot('FI7A-2'));
    }
}
