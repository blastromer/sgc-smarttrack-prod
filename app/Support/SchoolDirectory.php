<?php

namespace App\Support;

class SchoolDirectory
{
    /**
     * @return list<string>
     */
    public static function encoderPositions(): array
    {
        return [
            'Teacher I',
            'Teacher II',
            'Teacher III',
            'Master Teacher I',
            'Master Teacher II',
            'Master Teacher III',
            'SGC Coordinator',
            'SGC Secretary',
            'SGC Teacher-Member',
            'SPED Teacher',
            'Guidance Counselor',
            'Administrative Officer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function headPositions(): array
    {
        return [
            'School Head',
            'Principal I',
            'Principal II',
            'Principal III',
            'Principal IV',
            'Head Teacher I',
            'Head Teacher II',
            'Head Teacher III',
            'Head Teacher IV',
            'Head Teacher V',
            'Head Teacher VI',
            'Teacher-in-Charge',
            'Officer-in-Charge',
        ];
    }

    /**
     * @return list<string>
     */
    public static function positionsFor(?string $role): array
    {
        return $role === 'school_head' ? self::headPositions() : self::encoderPositions();
    }

    /**
     * @return array{school: list<string>, school_head: list<string>}
     */
    public static function positionsByRole(): array
    {
        return [
            'school' => self::encoderPositions(),
            'school_head' => self::headPositions(),
        ];
    }
}
