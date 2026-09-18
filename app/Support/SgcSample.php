<?php

namespace App\Support;

class SgcSample
{
    public static function accounts(): array
    {
        return [
            ['email' => 'romer.necesario@sgcsmarttrack.gov.ph', 'role' => 'Super Admin'],
            ['email' => 'jovel.oberio@deped.gov.ph', 'role' => 'Division Admin'],
            ['email' => 'school.head@deped.gov.ph', 'role' => 'School Admin'],
        ];
    }

    public static function notices(string $role): array
    {
        return match ($role) {
            'super' => [
                ['unread' => true, 'title' => '12 days left in the 2026 cycle', 'detail' => '21 schools are still overdue.', 'when' => 'Today'],
                ['unread' => true, 'title' => 'Cadiz submission at 76%', 'detail' => '142 of 186 elementary schools submitted.', 'when' => 'Sep 17'],
                ['unread' => false, 'title' => '19 MOVs returned to schools', 'detail' => 'Most common reason: FI3 quorum.', 'when' => 'Sep 16'],
            ],
            'division' => [
                ['unread' => true, 'title' => 'Mabini ES missed the deadline', 'detail' => 'No SGC FAT submission on file.', 'when' => 'Today'],
                ['unread' => true, 'title' => '18 assessments in the queue', 'detail' => 'San Jose ES is next for validation.', 'when' => 'Sep 17'],
                ['unread' => false, 'title' => 'FI3A MOV returned to Sample ES', 'detail' => 'Minutes do not show 50%+1 quorum.', 'when' => 'Sep 17'],
            ],
            default => [
                ['unread' => true, 'title' => 'FI3A Minimum MOV returned', 'detail' => 'Minutes do not show 50%+1 quorum.', 'when' => 'Sep 17'],
                ['unread' => true, 'title' => 'T-14 checkpoint', 'detail' => '7 of 12 indicators encoded.', 'when' => 'Sep 16'],
                ['unread' => false, 'title' => '2026 SGC FAT cycle opened', 'detail' => 'Deadline Sep 30, 2026.', 'when' => 'Sep 1'],
            ],
        };
    }

    public static function nav(string $role): array
    {
        return match ($role) {
            'super' => [
                ['label' => 'Overview', 'href' => '/super'],
                ['label' => 'Divisions', 'href' => '/super/divisions'],
                ['label' => 'Users & roles', 'href' => '/super/users'],
                ['label' => 'Cycles', 'href' => '/super/cycles'],
            ],
            'division' => [
                ['label' => 'Overview', 'href' => '/division'],
                ['label' => 'Validation queue', 'href' => '/division/queue'],
                ['label' => 'Schools', 'href' => '/division/schools'],
                ['label' => 'Registrations', 'href' => '/division/registrations'],
                ['label' => 'Alerts', 'href' => '/division/alerts'],
            ],
            'school_head' => [
                ['label' => 'Dashboard', 'href' => '/school'],
                ['label' => 'My assessment', 'href' => '/school/assessment'],
                ['label' => 'MOV files', 'href' => '/school/movs'],
                ['label' => 'Submit', 'href' => '/school/submit'],
                ['label' => 'Encoders', 'href' => '/school/encoders'],
                ['label' => 'Notifications', 'href' => '/school/notifications'],
            ],
            default => [
                ['label' => 'Dashboard', 'href' => '/school'],
                ['label' => 'My assessment', 'href' => '/school/assessment'],
                ['label' => 'MOV files', 'href' => '/school/movs'],
                ['label' => 'Submit', 'href' => '/school/submit'],
                ['label' => 'Notifications', 'href' => '/school/notifications'],
            ],
        };
    }

    public static function noticeHref(string $role): string
    {
        return match ($role) {
            'super' => '/super',
            'division' => '/division/registrations',
            'school_head' => '/school/encoders',
            default => '/school/notifications',
        };
    }

    public static function flow(): array
    {
        return [
            'banner' => 'You are here: fix returned MOV, then submit',
            'path' => 'Path: Encode → MOVs → School Head QA → Submit to Division → Validation → Functional (10/12).',
            'steps' => [
                ['n' => '1', 'label' => 'Encode', 'hint' => '7 / 12 FIs', 'href' => '/school/assessment', 'state' => 'done'],
                ['n' => '2', 'label' => 'MOVs', 'hint' => '1 returned', 'href' => '/school/movs', 'state' => 'now'],
                ['n' => '3', 'label' => 'School Head QA', 'hint' => 'Not started', 'href' => '/school/submit', 'state' => 'lock'],
                ['n' => '4', 'label' => 'Submit', 'hint' => 'Blocked', 'href' => '/school/submit', 'state' => 'lock'],
                ['n' => '5', 'label' => 'Division', 'hint' => 'Waiting', 'href' => '/school/submit', 'state' => 'lock'],
                ['n' => '6', 'label' => 'Result', 'hint' => 'Not yet', 'href' => '/school/submit', 'state' => 'lock'],
            ],
        ];
    }

    public static function page(string $key): array
    {
        return match ($key) {
            'super.overview' => [
                'title' => 'System overview',
                'subtitle' => 'Pilot metrics · SDO Cadiz City',
                'chip' => 'SY 2025–2026',
                'kpis' => [
                    self::kpi('Schools in cycle', '186', '100% of elementary list'),
                    self::kpi('Functional SGCs', '68', '36.6% (≥ 10 of 12 FIs)'),
                    self::kpi('Submission rate', '76%', '142 of 186 submitted'),
                    self::kpi('Overdue', '21', 'Need deadline alerts', 'bad'),
                    self::kpi('Validated', '68', 'Division QA complete'),
                    self::kpi('Under review', '48', 'In validation queue', 'warn'),
                    self::kpi('Returned MOVs', '19', 'Awaiting school resubmit', 'bad'),
                    self::kpi('Admin accounts', '12', '1 Super · 3 Division · 8 School'),
                ],
                'charts' => [
                    [
                        'title' => 'Indicator performance',
                        'hint' => 'Share of schools meeting each FI',
                        'bars' => [
                            self::bar('FI7 LSB', '42%', 42, 'bad'),
                            self::bar('FI11 SRC', '51%', 51, 'warn'),
                            self::bar('FI3 Meet', '58%', 58, 'warn'),
                            self::bar('FI5 Coord', '73%', 73),
                            self::bar('FI1 Roles', '81%', 81),
                        ],
                    ],
                    [
                        'title' => 'Compliance funnel',
                        'hint' => '186 elementary schools in cycle',
                        'bars' => [
                            self::bar('Not started', '24', 32, 'bad'),
                            self::bar('In progress', '20', 27, 'warn'),
                            self::bar('Submitted', '74', 100),
                            self::bar('In review', '48', 65, 'warn'),
                            self::bar('Validated', '68', 92),
                        ],
                    ],
                ],
            ],
            'super.divisions' => [
                'title' => 'Divisions',
                'subtitle' => 'Pilot is one SDO. Region scale-up can add rows later.',
                'kpis' => [
                    self::kpi('Active SDOs', '1', 'Cadiz City'),
                    self::kpi('Schools mapped', '186', 'Elementary'),
                    self::kpi('Division Admins', '3', 'Focal + validators'),
                ],
                'headers' => ['Division', 'Region', 'Schools', 'Submission', 'Functional', 'Status'],
                'rows' => [
                    ['Schools Division of Cadiz City', 'Negros Island Region', '186', '76%', '68 (36.6%)', self::badge('Pilot live', 'ok')],
                    ['SDO sample (not activated)', 'Negros Island Region', '—', '—', '—', self::badge('Queued', 'warn')],
                ],
            ],
            'super.users' => [
                'title' => 'Users & roles',
                'subtitle' => 'Roles are assigned. Users do not self-select Super or Division.',
                'kpis' => [
                    self::kpi('Total users', '12'),
                    self::kpi('Super Admin', '1'),
                    self::kpi('Division Admin', '3'),
                    self::kpi('School Admin', '8'),
                ],
                'headers' => ['Name', 'Email', 'Role', 'Office / school', 'Status'],
                'rows' => [
                    ['Romer Necesario', 'romer.necesario@sgcsmarttrack.gov.ph', 'Super Admin', 'System', self::badge('Active', 'ok')],
                    ['Jovel J. Oberio', 'jovel.oberio@deped.gov.ph', 'Division Admin', 'SGOD / SGC Focal', self::badge('Active', 'ok')],
                    ['ASDS Validator', 'asds.cadiz@deped.gov.ph', 'Division Admin', 'Composite Team', self::badge('Active', 'ok')],
                    ['PSDS District 1', 'psds.d1@deped.gov.ph', 'Division Admin', 'District 1', self::badge('Active', 'ok')],
                    ['Maria Santos', 'school.head@deped.gov.ph', 'School Admin', 'Sample ES 123456', self::badge('Active', 'ok')],
                    ['Juan Dela Cruz', 'juan.delacruz@deped.gov.ph', 'School Admin', 'San Jose ES', self::badge('Pending', 'warn')],
                ],
            ],
            'super.cycles' => [
                'title' => 'Assessment cycles',
                'subtitle' => 'One official SGC FAT cycle is open for public elementary schools.',
                'kpis' => [
                    self::kpi('Open cycles', '1'),
                    self::kpi('Days remaining', '12'),
                    self::kpi('On-time target', '90%'),
                ],
                'headers' => ['Cycle', 'Level', 'Opens', 'Deadline', 'Schools', 'Status'],
                'rows' => [
                    ['2026 SGC Functionality Assessment', 'Public Elementary', 'Sep 1, 2026', 'Sep 30, 2026', '186', self::badge('Open', 'ok')],
                    ['Secondary SGC monitoring (manual)', 'Public Secondary', '—', '—', '—', self::badge('Not in tool', 'warn')],
                ],
            ],
            'division.overview' => [
                'title' => 'Division dashboard',
                'subtitle' => 'Jovel J. Oberio · SGC Focal · SDO Cadiz City',
                'chip' => 'Deadline in 12 days',
                'kpis' => [
                    self::kpi('Schools', '186', 'Elementary cycle'),
                    self::kpi('Submitted', '142', '76% compliance'),
                    self::kpi('Overdue', '21', 'No submission yet', 'bad'),
                    self::kpi('Functional', '68', '≥ 10 of 12 FIs'),
                    self::kpi('Queue', '18', 'Awaiting validation'),
                    self::kpi('Returned', '19', 'Invalid MOVs', 'bad'),
                    self::kpi('TA flagged', '27', 'Below 10/12', 'warn'),
                    self::kpi('Avg FIs met', '8.4', 'Division mean'),
                ],
                'charts' => [
                    [
                        'title' => 'Weakest indicators',
                        'hint' => 'Division rate of schools meeting the FI',
                        'bars' => [
                            self::bar('FI7 LSB', '42%', 42, 'bad'),
                            self::bar('FI11 SRC', '51%', 51, 'warn'),
                            self::bar('FI3 Meet', '58%', 58, 'warn'),
                            self::bar('FI4 Comm', '64%', 64),
                            self::bar('FI1 Roles', '81%', 81),
                        ],
                    ],
                    [
                        'title' => 'Submission funnel',
                        'hint' => '186 elementary schools',
                        'bars' => [
                            self::bar('Not started', '24', 32, 'bad'),
                            self::bar('In progress', '20', 27, 'warn'),
                            self::bar('Submitted', '74', 100),
                            self::bar('In review', '48', 65, 'warn'),
                            self::bar('Validated', '68', 92),
                        ],
                    ],
                ],
            ],
            'division.queue' => [
                'title' => 'Validation queue',
                'subtitle' => 'Review MOVs against SGC FAT Volume 2 rules.',
                'kpis' => [
                    self::kpi('Awaiting review', '18', null, 'warn'),
                    self::kpi('Resubmitted', '6'),
                    self::kpi('Validated today', '4'),
                ],
                'headers' => ['School', 'School ID', 'Self-score', 'Status', 'Submitted'],
                'rows' => [
                    ['San Jose Elementary School', '123101', '9/12', self::badge('Needs validation', 'warn'), 'Sep 16'],
                    ['Rizal Central Elementary', '123102', '10/12', self::badge('Resubmitted', 'ok'), 'Sep 17'],
                    ['Mabini Elementary School', '123103', '0/12', self::badge('Overdue', 'bad'), '—'],
                    ['Bonifacio ES', '123104', '11/12', self::badge('In review', 'warn'), 'Sep 15'],
                    ['Luna Elementary School', '123105', '12/12', self::badge('Validated', 'ok'), 'Sep 14'],
                ],
            ],
            'division.schools' => [
                'title' => 'Schools',
                'subtitle' => 'Sample elementary schools in the 2026 SGC cycle.',
                'kpis' => [
                    self::kpi('Listed', '186'),
                    self::kpi('Functional', '68'),
                    self::kpi('Not yet', '118', null, 'warn'),
                ],
                'headers' => ['School', 'District', 'FIs met', 'MOVs', 'Result'],
                'rows' => [
                    ['Sample Elementary School', 'District 1', '7/12', '14', self::badge('Not yet', 'warn')],
                    ['San Jose ES', 'District 1', '9/12', '18', self::badge('Not yet', 'warn')],
                    ['Rizal Central ES', 'District 2', '10/12', '22', self::badge('Functional', 'ok')],
                    ['Luna ES', 'District 2', '12/12', '28', self::badge('Functional', 'ok')],
                    ['Mabini ES', 'District 3', '0/12', '0', self::badge('No data', 'bad')],
                ],
            ],
            'division.alerts' => [
                'title' => 'Alerts',
                'subtitle' => 'Deadline and non-compliance notices sent to School Heads.',
                'kpis' => [
                    self::kpi('Overdue schools', '21', null, 'bad'),
                    self::kpi('Emails queued', '21'),
                    self::kpi('Returned MOVs', '19', null, 'bad'),
                ],
                'headers' => ['When', 'School', 'Trigger', 'Channel', 'Status'],
                'rows' => [
                    ['Sep 18, 07:00', 'Mabini ES', 'Deadline missed', 'Email', self::badge('Sent', 'bad')],
                    ['Sep 17, 07:00', 'San Jose ES', 'T-3 incomplete', 'Email', self::badge('Sent', 'warn')],
                    ['Sep 17, 14:22', 'Sample ES', 'FI3A MOV returned', 'Email', self::badge('Sent', 'warn')],
                    ['Sep 16, 07:00', '21 schools', 'T-7 reminder', 'Email digest', self::badge('Sent', 'ok')],
                ],
            ],
            'school.dashboard' => [
                'title' => 'School dashboard',
                'subtitle' => 'Sample Elementary School · School ID 123456',
                'chip' => 'Deadline in 12 days',
                'kpis' => [
                    self::kpi('Indicators met', '7 / 12', 'Need 10 to be functional'),
                    self::kpi('MOVs uploaded', '14', 'Minimum + additional'),
                    self::kpi('Returned', '1', 'FI3A quorum', 'bad'),
                    self::kpi('Self-score', 'Not yet', '58% complete', 'warn'),
                ],
                'charts' => [
                    [
                        'title' => 'Indicator status',
                        'hint' => '7 met · 1 returned · 4 not started',
                        'bars' => [
                            self::bar('FI1', 'Y', 100),
                            self::bar('FI2', 'Y', 100),
                            self::bar('FI3', 'R', 40, 'bad'),
                            self::bar('FI4', '—', 20, 'warn'),
                            self::bar('FI5', '—', 8, 'bad'),
                            self::bar('FI6', 'Y', 100),
                            self::bar('FI7', '—', 8, 'bad'),
                            self::bar('FI8', 'Y', 100),
                        ],
                    ],
                ],
                'progress' => [
                    'width' => 58,
                    'text' => '58% complete · need 3 more FIs',
                    'actions' => [
                        '1. Replace FI3A minutes (quorum).',
                        '2. Complete FI4–FI7 Minimum MOVs.',
                        '3. School Head QA, then submit to Division.',
                    ],
                ],
            ],
            'school.assessment' => [
                'title' => 'My assessment',
                'subtitle' => '2026 SGC Functionality Assessment · public elementary',
                'kpis' => [
                    self::kpi('Complete', '7'),
                    self::kpi('Returned', '1', null, 'bad'),
                    self::kpi('Not started', '4', null, 'warn'),
                ],
                'headers' => ['Indicator', 'Title', 'Answer', 'MOV', 'Status'],
                'rows' => [
                    ['FI1', 'Members informed of roles', 'Yes', 'Minimum uploaded', self::badge('Complete', 'ok')],
                    ['FI2', 'Consultative body', 'Yes', 'Minimum uploaded', self::badge('Complete', 'ok')],
                    ['FI3', 'Regular SGC meetings', 'Yes', 'FI3A returned', self::badge('Returned', 'bad')],
                    ['FI4', 'Meetings with committees', '—', 'Missing', self::badge('In progress', 'warn')],
                    ['FI5', 'Coordinate to School Head', '—', 'None', self::badge('Not started', 'warn')],
                    ['FI7', 'LSB recommendations', '—', 'None', self::badge('Not started', 'warn')],
                ],
            ],
            'school.movs' => [
                'title' => 'MOV files',
                'subtitle' => 'Means of Verification uploaded for this cycle.',
                'kpis' => [
                    self::kpi('Files', '14'),
                    self::kpi('Valid', '12'),
                    self::kpi('Returned', '1', null, 'bad'),
                ],
                'headers' => ['File', 'Indicator', 'Type', 'Size', 'Status'],
                'rows' => [
                    ['FI1A-Minimum-Notice-of-Meeting.pdf', 'FI1A', 'Minimum', '420 KB', self::badge('Valid', 'ok')],
                    ['FI1B-Minimum-Membership-List.pdf', 'FI1B', 'Minimum', '210 KB', self::badge('Valid', 'ok')],
                    ['FI3A-Minimum-Resolution.pdf', 'FI3A', 'Minimum', '680 KB', self::badge('Returned — no quorum', 'bad')],
                    ['FI2A-Minimum-Minutes-SPT.pdf', 'FI2A', 'Minimum', '1.1 MB', self::badge('Valid', 'ok')],
                    ['Validity-Form-2026.pdf', 'Validity', 'Required', '90 KB', self::badge('Draft', 'warn')],
                ],
            ],
            'school.submit' => [
                'title' => 'Submit to Division',
                'subtitle' => 'Sample Elementary School · 2026 SGC Functionality Assessment',
                'chip' => 'Deadline in 12 days',
                'checks' => [
                    ['mark' => '✓', 'tone' => 'ok', 'title' => 'Cycle is open', 'hint' => 'Deadline Sep 30, 2026'],
                    ['mark' => '7', 'tone' => 'warn', 'title' => 'Encode 12 functionality indicators', 'hint' => '7 complete · FI4, FI5, FI7 still open'],
                    ['mark' => '!', 'tone' => 'bad', 'title' => 'Replace returned FI3A MOV', 'hint' => 'Minutes do not show 50%+1 quorum'],
                    ['mark' => '○', 'tone' => 'warn', 'title' => 'Finalize Validity Form', 'hint' => 'Still marked Draft'],
                    ['mark' => '3', 'tone' => 'todo', 'title' => 'School Head QA', 'hint' => 'Certify answers before send'],
                    ['mark' => '4', 'tone' => 'todo', 'title' => 'Submit to SDO Cadiz City', 'hint' => 'Locks the school packet for validation'],
                    ['mark' => '5', 'tone' => 'todo', 'title' => 'Division review', 'hint' => 'Composite Team checks MOVs against FAT Volume 2'],
                    ['mark' => '6', 'tone' => 'todo', 'title' => 'Result', 'hint' => 'Functional if 10 of 12 FIs are validated'],
                ],
                'after' => [
                    '1. The school packet is time-stamped and locked.',
                    '2. It appears in the Division validation queue.',
                    '3. A validator accepts or returns each Minimum MOV.',
                    '4. If returned, you resubmit only the flagged files.',
                    '5. When 10 of 12 FIs pass, the SGC is marked Functional.',
                ],
                'packet' => [
                    'School ID 123456 · Sample Elementary School',
                    'Self-score: not yet · 7 of 12 encoded',
                    'MOVs: 14 files · 12 valid · 1 returned · 1 draft',
                    'Destination: SGC Focal, SDO Cadiz City',
                ],
            ],
            'school.notifications' => [
                'title' => 'Notifications',
                'subtitle' => 'School Head and designated encoder both receive these.',
                'kpis' => [
                    self::kpi('Unread', '2', null, 'warn'),
                    self::kpi('This cycle', '5'),
                ],
                'headers' => ['Date', 'Message', 'Type'],
                'rows' => [
                    ['Sep 17', 'FI3A Minimum MOV returned — minutes do not show 50%+1 quorum.', self::badge('Action needed', 'bad')],
                    ['Sep 16', 'T-14 checkpoint: 7 of 12 indicators encoded.', self::badge('Reminder', 'warn')],
                    ['Sep 1', '2026 SGC FAT cycle opened. Deadline Sep 30, 2026.', self::badge('Info', 'ok')],
                ],
            ],
            default => [],
        };
    }

    /**
     * @return array{label: string, value: string, hint: ?string, tone: ?string}
     */
    private static function kpi(string $label, string $value, ?string $hint = null, ?string $tone = null): array
    {
        return compact('label', 'value', 'hint', 'tone');
    }

    /**
     * @return array{label: string, value: string, height: int, tone: ?string}
     */
    private static function bar(string $label, string $value, int $height, ?string $tone = null): array
    {
        return compact('label', 'value', 'height', 'tone');
    }

    /**
     * @return array{badge: string, tone: string}
     */
    private static function badge(string $badge, string $tone): array
    {
        return compact('badge', 'tone');
    }
}
