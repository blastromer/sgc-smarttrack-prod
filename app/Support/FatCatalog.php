<?php

namespace App\Support;

use Illuminate\Support\Collection;

class FatCatalog
{
    /**
     * Official BHROD-SED SGC Functionality Assessment Tool (DO 26, s. 2022).
     * Same 12 primary indicators for public elementary and secondary schools.
     * Only the 12 primary Yes/No answers score. Other sub-indicators and additional MOVs do not.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function indicators(): array
    {
        return [
            self::fi(
                1,
                'sg',
                'Members informed of roles',
                'The SGC has members who are informed of and given the opportunity to exercise their roles and responsibilities in the council.',
                'FI1A',
                'The SGC has called meetings in order to create a venue for its decision-making process.',
                [['code' => 'FI1A', 'title' => 'Notice of meeting (at least 1 of 4 Regular Meetings)']],
                [['code' => 'FI1A-ADD', 'title' => 'Notice of meeting (2 to 4 Regular Meetings)']],
                [
                    self::other(
                        'FI1B',
                        'SGC members have been inducted and oriented of their roles and responsibilities as members and officers of the Council.',
                        [['code' => 'FI1B', 'title' => 'Membership / Induction Certificates (7 to 15 voting members) or SGC Resolution on the Official List of Voting Members']],
                        [
                            ['code' => 'FI1B-ADD1', 'title' => 'Membership / Induction Certificates (non-voting members)'],
                            ['code' => 'FI1B-ADD2', 'title' => 'SGC Resolution on the Official List of Members (non-voting members)'],
                        ],
                    ),
                    self::other(
                        'FI1C',
                        'The SGC has an organizational chart, including non-voting members, if applicable.',
                        [['code' => 'FI1C', 'title' => 'Draft / Operative Organizational Chart']],
                        [['code' => 'FI1C-ADD', 'title' => 'Approved / Adopted Organizational Chart']],
                    ),
                ],
            ),
            self::fi(
                2,
                'sg',
                'Consultative body in school policies',
                'The SGC has established its position as a consultative body in developing school policies.',
                'FI2A',
                'The SGC has participated actively in the formulation of the SIP/AIP and other DepEd programs, projects, and activities.',
                [['code' => 'FI2A', 'title' => 'Minutes of Meeting with SPT on SIP / AIP (at least 1 meeting)']],
                [
                    ['code' => 'FI2A-ADD1', 'title' => 'Minutes of Meetings with SPT on SIP / AIP (2 or more meetings)'],
                    ['code' => 'FI2A-ADD2', 'title' => 'Minutes of Meeting/s with SPT on other DepEd programs, projects, and activities (at least 1 meeting)'],
                    ['code' => 'FI2A-ADD3', 'title' => "SGC's Action Plan"],
                    ['code' => 'FI2A-ADD4', 'title' => 'SGC Resolution relative to the indicator (at least 1)'],
                ],
                [
                    self::other(
                        'FI2B',
                        'The SGC has passed recommendations to the School Head regarding concerns, policies, programs, and/or interventions raised by stakeholders.',
                        [['code' => 'FI2B', 'title' => 'SGC Resolution relative to the indicator (at least 1)']],
                        [['code' => 'FI2B-ADD', 'title' => 'SGC Resolutions relative to the indicator (2 or more)']],
                    ),
                    self::other(
                        'FI2C',
                        'The SGC has attended meetings on the importance of upholding the rights of the child.',
                        [['code' => 'FI2C', 'title' => 'Minutes of Meeting with CPU, CPC, or other similar DepEd organizations (at least 1 meeting)']],
                        [
                            ['code' => 'FI2C-ADD1', 'title' => 'Minutes of Meetings with CPU, CPC, or other similar DepEd organizations (2 or more meetings)'],
                            ['code' => 'FI2C-ADD2', 'title' => 'SGC Resolution on promoting the rights of the child (at least 1)'],
                        ],
                    ),
                ],
            ),
            self::fi(
                3,
                'sg',
                'Regular SGC meetings',
                'The SGC has conducted regular SGC meetings as prescribed in DO 26, s. 2022.',
                'FI3A',
                'The SGC has decided matters through a resolution, signed by all SGC voting members.',
                [['code' => 'FI3A', 'title' => 'SGC Resolution (at least 1)']],
                [
                    ['code' => 'FI3A-ADD1', 'title' => 'SGC Resolutions (2 or more)'],
                    ['code' => 'FI3A-ADD2', 'title' => "SGC's Action Plan"],
                ],
                [
                    self::other(
                        'FI3BCD',
                        'Other: meeting agenda support SIP/AIP (FI3B); quorum of 50%+1 (FI3C); regular meetings have minutes (FI3D).',
                        [['code' => 'FI3BCD', 'title' => 'Minutes of Meetings specifying required quorum (FI3B, FI3C, FI3D)']],
                        [],
                    ),
                ],
            ),
            self::fi(
                4,
                'sg',
                'Meetings with school committees',
                'The SGC has organized meetings with and attended meetings of different school committees and organizations to ensure alignment of work.',
                'FI4A',
                'The SGC has organized meetings with different school stakeholders to harmonize proposed and existing programs, projects, and activities.',
                [['code' => 'FI4A', 'title' => 'Minutes of Meeting with stakeholders on programs, projects, and activities (at least 1 meeting)']],
                [
                    ['code' => 'FI4A-ADD1', 'title' => 'Minutes of Meetings with stakeholders on programs, projects, and activities (2 or more meetings)'],
                    ['code' => 'FI4A-ADD2', 'title' => "SGC's Action Plan"],
                    ['code' => 'FI4A-ADD3', 'title' => 'SGC Resolution relative to the indicator (at least 1)'],
                ],
                [
                    self::other(
                        'FI4B',
                        'The SGC has been represented in meetings organized by different school committees and organizations.',
                        [['code' => 'FI4B', 'title' => 'Any document reporting the discussion from the meeting attended (at least 1 meeting)']],
                        [
                            ['code' => 'FI4B-ADD1', 'title' => 'Any documents reporting the discussion from the meeting attended (2 or more meetings)'],
                            ['code' => 'FI4B-ADD2', 'title' => 'Copy of the Minutes of Meetings from school committees and organizations'],
                        ],
                    ),
                    self::other(
                        'FI4C',
                        'The SGC has met and discussed with school stakeholders its role as oversight on school planning and resource use.',
                        [['code' => 'FI4C', 'title' => 'Minutes of Meetings with different school stakeholders (at least 1 meeting)']],
                        [
                            ['code' => 'FI4C-ADD1', 'title' => 'Minutes of Meetings with different school stakeholders (2 or more meetings)'],
                            ['code' => 'FI4C-ADD2', 'title' => 'SGC Resolution relative to the indicator (at least 1)'],
                        ],
                    ),
                ],
            ),
            self::fi(
                5,
                'sg',
                'Coordinate concerns to School Head',
                'The SGC has coordinated with the School Head the concerns of the different school committees and organizations to synchronize programs, projects, and activities in the school.',
                'FI5A',
                'The Co-Chairpersons have communicated the direction of the SGC to the School Head.',
                [['code' => 'FI5A', 'title' => 'Copy of the communication / transmittal letter to the School Head reflecting the direction of the SGC']],
                [
                    ['code' => 'FI5A-ADD1', 'title' => "Any document with citations on SGC's recommendation released by the school management / School Head"],
                    ['code' => 'FI5A-ADD2', 'title' => "School Head's acknowledgment of SGC (SOSA, speeches, newsletter, etc.)"],
                ],
                [],
            ),
            self::fi(
                6,
                'sg',
                'Stakeholder-initiated programs',
                'The SGC has taken part in the conduct of needs-based and appropriate stakeholder-initiated programs and activities (e.g. Brigada Eskwela, Gulayan sa Paaralan).',
                'FI6A',
                'The SGC has been involved in the development of stakeholder-initiated programs and activities.',
                [
                    ['code' => 'FI6A', 'title' => 'Minutes of Meeting with stakeholders on stakeholder-initiated programs and activities (at least 1 meeting)'],
                    ['code' => 'FI6A-2', 'title' => 'Concept note / Project brief, or similar document (at least 1)'],
                ],
                [
                    ['code' => 'FI6A-ADD1', 'title' => 'Concept note / Project brief, or similar document (2 or more)'],
                    ['code' => 'FI6A-ADD2', 'title' => 'Copy of the project proposal on stakeholder-initiated programs and activities'],
                    ['code' => 'FI6A-ADD3', 'title' => 'SIP, AIP, SRC, and SMEA (specify the page in the reports)'],
                ],
                [
                    self::other(
                        'FI6B',
                        'The SGC has monitored and evaluated the impact/success of stakeholder-initiated programs and activities.',
                        [['code' => 'FI6B', 'title' => 'Report on the assessment / monitoring and evaluation of stakeholder-initiated program and/or activity (at least 1)']],
                        [
                            ['code' => 'FI6B-ADD1', 'title' => 'Report on the assessment / monitoring and evaluation of stakeholder-initiated programs and/or activities (2 or more)'],
                            ['code' => 'FI6B-ADD2', 'title' => 'SIP, AIP, SRC, SMEA, and School Project Monitoring Reports'],
                        ],
                    ),
                    self::other(
                        'FI6C',
                        'The SGC has established linkages with other stakeholders and/or referred potential partners to the School Head.',
                        [['code' => 'FI6C', 'title' => 'SGC resolution on the referral of the identified potential partner (at least 1 partner)']],
                        [
                            ['code' => 'FI6C-ADD1', 'title' => 'SGC resolution on the referral of the identified potential partner (2 or more partners)'],
                            ['code' => 'FI6C-ADD2', 'title' => 'Copy of the MOA, DOD, DOA, etc., reflecting the name/s of the referred partner/s (at least 1)'],
                        ],
                    ),
                ],
            ),
            self::fi(
                7,
                'sg',
                'Recommendations to the LSB',
                'The SGC has recommended policies and programs to the Local School Board (LSB) to strengthen relationship with the LGU.',
                'FI7A',
                'The SGC has recommended policies and programs to the Local School Board (LSB) to strengthen relationship with the LGU.',
                [
                    ['code' => 'FI7A', 'title' => 'SGC Resolution recommending the SIP to LSB'],
                    ['code' => 'FI7A-2', 'title' => 'Any document recommending policy/program to the LSB, based on the SIP'],
                ],
                [['code' => 'FI7A-ADD', 'title' => 'Proof of endorsement of the SGC Resolution to the SDS and transmittal to the LSB']],
                [],
            ),
            self::fi(
                8,
                'sg',
                'Inclusive stakeholder representation',
                'The SGC has involved the different sectors to ensure inclusive representation of stakeholders in the council.',
                'FI8A',
                'The SGC has involved the different sectors to ensure inclusive representation of stakeholders in the council.',
                [['code' => 'FI8A', 'title' => 'SGC Resolution on involving various sectors']],
                [
                    ['code' => 'FI8A-ADD1', 'title' => 'Official list of members with expanded membership (inclusive and diverse in terms of age, gender, religion, ethnicity, and political beliefs)'],
                    ['code' => 'FI8A-ADD2', 'title' => 'SGC Resolution on inclusiveness, diversity, equity, and accessibility'],
                ],
                [],
            ),
            self::fi(
                9,
                'fm',
                'Participation in stakeholder activities',
                'The SGC has participated in school general assemblies, PTA conferences, stakeholder convergence, SOSA, and/or other stakeholder engagement activities and initiatives.',
                'FI9A',
                'The SGC has participated in school general assemblies, PTA conferences, stakeholder convergence, SOSA, and/or other stakeholder engagement activities and initiatives.',
                [['code' => 'FI9A', 'title' => 'SGC Report on the issues / concerns raised during school activities / events']],
                [
                    ['code' => 'FI9A-ADD1', 'title' => 'Minutes of Meetings (SGC meetings) where issues / concerns are discussed'],
                    ['code' => 'FI9A-ADD2', 'title' => 'Photo documentation of school activities / events'],
                ],
                [],
            ),
            self::fi(
                10,
                'fm',
                'Organized discussions and forums',
                'The SGC has organized discussions and forums that invite and inspire stakeholders to engage and participate.',
                'FI10A',
                'The SGC has organized discussions and forums that invite and inspire stakeholders to engage and participate.',
                [
                    ['code' => 'FI10A', 'title' => 'Documentation of the organized / conducted program (at least 1)'],
                    ['code' => 'FI10A-2', 'title' => 'Minutes of the meetings where issues / concerns are discussed'],
                ],
                [
                    ['code' => 'FI10A-ADD1', 'title' => "Documentation of the organized / conducted program (2 or more), following the SGC's Calendar of Events"],
                    ['code' => 'FI10A-ADD2', 'title' => 'Photo documentation of school activities / events'],
                ],
                [],
            ),
            self::fi(
                11,
                'fm',
                'Access to school data / SRC',
                'The SGC has assisted the school in communicating information to the school stakeholders through the SRC, Transparency Board, etc.',
                'FI11A',
                'The SGC has promoted access to school data and information through Transparency Board, SRC, and other reports on operations and performance of school programs and resource management.',
                [
                    ['code' => 'FI11A', 'title' => 'SGC Resolution on access to information (school data and information)'],
                    ['code' => 'FI11A-2', 'title' => "SGC's Action Plan on promoting access to information"],
                ],
                [
                    ['code' => 'FI11A-ADD1', 'title' => "Advocacy plan on the school's use of the Transparency Board, SRC, and other reports"],
                    ['code' => 'FI11A-ADD2', 'title' => "School Head's endorsement on the use of the Transparency Board, SRC, and other reports"],
                    ['code' => 'FI11A-ADD3', 'title' => 'Photo documentation of the transparency board or bulletin board'],
                ],
                [
                    self::other(
                        'FI11B',
                        'The SGC has established alternative communication platform/s (e.g. social media, email, or text blast) where SGC announcements and activities can be accessed.',
                        [['code' => 'FI11B', 'title' => 'SGC Resolution on the use of approved alternative communication platform/s']],
                        [['code' => 'FI11B-ADD', 'title' => "SGC's alternative communication platform with regular updates (offline printed materials or screenshot of online page)"]],
                    ),
                ],
            ),
            self::fi(
                12,
                'fm',
                'Suggestions to improve SIP/AIP',
                'The SGC has suggested ways of improving the quality of SIP, AIP, and other DepEd programs, projects, and activities.',
                'FI12A',
                'The SGC has suggested ways of improving the quality of SIP, AIP, and other DepEd programs, projects, and activities.',
                [['code' => 'FI12A', 'title' => 'SGC Resolution on recommendations for improving SIP, AIP, and other DepEd programs, projects, and activities (at least 1)']],
                [['code' => 'FI12A-ADD', 'title' => 'SGC Resolutions on recommendations for improving SIP, AIP, and other DepEd programs, projects, and activities (2 or more)']],
                [],
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function indicator(string $code): ?array
    {
        return collect(self::indicators())->firstWhere('code', $code);
    }

    public static function groupLabel(string $group): string
    {
        return $group === 'fm' ? 'Feedback Mechanism' : 'Structure for Shared Governance';
    }

    /**
     * @return array<int, array{code: string, title: string, indicator_code: string, kind: string, sub_code: string, scored: bool}>
     */
    public static function minimumSlots(string $indicatorCode): array
    {
        $indicator = self::indicator($indicatorCode);
        if (! $indicator) {
            return [];
        }

        return collect($indicator['minimum'])->map(fn (array $slot) => [
            'code' => $slot['code'],
            'title' => $slot['title'],
            'indicator_code' => $indicatorCode,
            'kind' => 'minimum',
            'sub_code' => $indicator['primary_code'],
            'scored' => true,
        ])->all();
    }

    /**
     * @return array<int, array{code: string, title: string, indicator_code: string, kind: string, sub_code: string, scored: bool}>
     */
    public static function optionalSlots(string $indicatorCode): array
    {
        $indicator = self::indicator($indicatorCode);
        if (! $indicator) {
            return [];
        }

        $slots = collect($indicator['additional'])->map(fn (array $slot) => [
            'code' => $slot['code'],
            'title' => $slot['title'],
            'indicator_code' => $indicatorCode,
            'kind' => 'additional',
            'sub_code' => $indicator['primary_code'],
            'scored' => false,
        ]);

        foreach ($indicator['others'] as $other) {
            foreach ($other['minimum'] as $slot) {
                $slots->push([
                    'code' => $slot['code'],
                    'title' => $slot['title'],
                    'indicator_code' => $indicatorCode,
                    'kind' => 'other',
                    'sub_code' => $other['code'],
                    'scored' => false,
                ]);
            }
            foreach ($other['additional'] as $slot) {
                $slots->push([
                    'code' => $slot['code'],
                    'title' => $slot['title'],
                    'indicator_code' => $indicatorCode,
                    'kind' => 'other',
                    'sub_code' => $other['code'],
                    'scored' => false,
                ]);
            }
        }

        return $slots->values()->all();
    }

    /**
     * @param  Collection<int, mixed>|\Illuminate\Database\Eloquent\Collection<int, mixed>  $indicators
     * @return Collection<int, mixed>
     */
    public static function sortIndicators($indicators)
    {
        $order = collect(self::indicators())->pluck('code')->flip();

        return $indicators->sortBy(fn ($indicator) => $order[$indicator->code] ?? 99)->values();
    }

    /**
     * @param  array<int, array{code: string, title: string}>  $minimum
     * @param  array<int, array{code: string, title: string}>  $additional
     * @param  array<int, array<string, mixed>>  $others
     * @return array<string, mixed>
     */
    private static function fi(
        int $number,
        string $group,
        string $title,
        string $fullTitle,
        string $primaryCode,
        string $primary,
        array $minimum,
        array $additional,
        array $others,
    ): array {
        return [
            'code' => 'FI'.$number,
            'sort' => $number,
            'group' => $group,
            'group_label' => self::groupLabel($group),
            'title' => $title,
            'full_title' => $fullTitle,
            'primary_code' => $primaryCode,
            'primary' => $primary,
            'mov_code' => $minimum[0]['code'],
            'mov_title' => $minimum[0]['title'],
            'minimum' => $minimum,
            'additional' => $additional,
            'others' => $others,
        ];
    }

    /**
     * @param  array<int, array{code: string, title: string}>  $minimum
     * @param  array<int, array{code: string, title: string}>  $additional
     * @return array<string, mixed>
     */
    private static function other(string $code, string $title, array $minimum, array $additional): array
    {
        return [
            'code' => $code,
            'title' => $title,
            'minimum' => $minimum,
            'additional' => $additional,
        ];
    }
}
