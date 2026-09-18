<?php

namespace App\Support;

class FatCatalog
{
    /**
     * Official SGC FAT 12 functionality indicators (school encoding sheet).
     *
     * @return array<int, array{code: string, title: string, mov_code: string, mov_title: string}>
     */
    public static function indicators(): array
    {
        return [
            ['code' => 'FI1', 'title' => 'Members informed of roles', 'mov_code' => 'FI1A', 'mov_title' => 'Notice of meeting / membership list'],
            ['code' => 'FI2', 'title' => 'Consultative body', 'mov_code' => 'FI2A', 'mov_title' => 'SPT / consultative minutes'],
            ['code' => 'FI3', 'title' => 'Regular SGC meetings', 'mov_code' => 'FI3A', 'mov_title' => 'Minutes showing 50%+1 quorum'],
            ['code' => 'FI4', 'title' => 'Meetings with committees', 'mov_code' => 'FI4A', 'mov_title' => 'Joint committee minutes'],
            ['code' => 'FI5', 'title' => 'Coordinate to School Head', 'mov_code' => 'FI5A', 'mov_title' => 'Coordination notes / action sheet'],
            ['code' => 'FI6', 'title' => 'Participate in SIP/AIP', 'mov_code' => 'FI6A', 'mov_title' => 'SIP/AIP participation proof'],
            ['code' => 'FI7', 'title' => 'LSB recommendations', 'mov_code' => 'FI7A', 'mov_title' => 'Recommendation to LSB'],
            ['code' => 'FI8', 'title' => 'Resource generation', 'mov_code' => 'FI8A', 'mov_title' => 'Resource generation program'],
            ['code' => 'FI9', 'title' => 'Communication system', 'mov_code' => 'FI9A', 'mov_title' => 'Communication plan / notices'],
            ['code' => 'FI10', 'title' => 'Report to stakeholders', 'mov_code' => 'FI10A', 'mov_title' => 'Stakeholder report'],
            ['code' => 'FI11', 'title' => 'Transparency / SRC', 'mov_code' => 'FI11A', 'mov_title' => 'School Report Card'],
            ['code' => 'FI12', 'title' => 'Monitoring and feedback', 'mov_code' => 'FI12A', 'mov_title' => 'Monitoring / feedback record'],
        ];
    }

    /**
     * @return array{code: string, title: string, mov_code: string, mov_title: string}|null
     */
    public static function indicator(string $code): ?array
    {
        return collect(self::indicators())->firstWhere('code', $code);
    }
}
