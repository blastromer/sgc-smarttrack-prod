import type { Role } from '@/types/sgc';

export type DocRole = Role;

export interface WalkStep {
    n: number;
    title: string;
    detail: string;
    href?: string;
    action?: string;
    snapshot?: string;
}

export interface RoleScreen {
    name: string;
    href: string;
    purpose: string;
    snapshot?: string;
}

export interface RoleGuide {
    id: DocRole;
    label: string;
    summary: string;
    who: string;
    canDo: string[];
    cannotDo: string[];
    screens: RoleScreen[];
    walkthrough: WalkStep[];
    notes: string[];
}

export function shot(name: string): string {
    return `/assets/docs/${name}.png`;
}

export const fatRules = {
    order: 'DepEd Order 26, s. 2022',
    indicators: '12 functionality indicators (FIs), with 19 sub-indicators in the FAT packet.',
    functional: 'Functional status requires 10 of 12 Yes answers, each Yes backed by a valid Minimum MOV.',
    validity: 'The Validity Form is required for every packet.',
    returnRule: 'Division returns only the flagged MOV. The school replaces that file, recertifies School Head QA, then resubmits. Do not rebuild the whole packet.',
    packet: 'One FAT packet is shared by School ID (school_code) and cycle. Encoder and School Head of the same school work on the same packet.',
};

export const roleMatrix: { action: string; super: string; division: string; school_head: string; school: string }[] = [
    { action: 'Create first Super Admin', super: 'Yes — /setup once', division: 'No', school_head: 'No', school: 'No' },
    { action: 'Create Division Admin', super: 'Users & roles', division: 'No', school_head: 'No', school: 'No' },
    { action: 'Accept School Head', super: 'No', division: 'Registrations', school_head: 'No', school: 'No' },
    { action: 'Accept Encoder', super: 'No', division: 'No', school_head: 'Encoders', school: 'No' },
    { action: 'Encode Yes / No', super: 'No', division: 'No', school_head: 'Yes', school: 'Yes' },
    { action: 'Upload MOV', super: 'No', division: 'No', school_head: 'Yes', school: 'Yes' },
    { action: 'Remove unapproved MOV', super: 'No', division: 'No', school_head: 'Remove', school: 'Request only' },
    { action: 'Certify QA and submit', super: 'No', division: 'No', school_head: 'Yes', school: 'No' },
    { action: 'Withdraw packet', super: 'No', division: 'No', school_head: 'If no MOV is valid yet', school: 'No' },
    { action: 'Accept or return MOV', super: 'No', division: 'Validation queue', school_head: 'No', school: 'No' },
    { action: 'Reset school and FAT data', super: 'Type RESET FAT DATA', division: 'No', school_head: 'No', school: 'No' },
];

export const roleGuides: RoleGuide[] = [
    {
        id: 'super',
        label: 'Super Admin',
        summary: 'Opens the portal, creates the Division Admin, watches live counts, and can clear school FAT data for a new cycle.',
        who: 'Platform owner. One Super Admin is created on /setup. Super does not encode school packets or validate MOVs.',
        canDo: [
            'Create the first Super Admin on /setup while the system is empty.',
            'Create Division Admin accounts under Users & roles.',
            'Watch Overview, Divisions, and Cycles with live counts from the database.',
            'Reset operational data (School Heads, Encoders, packets, MOVs) without dropping Super or Division accounts.',
        ],
        cannotDo: [
            'Register as a school or accept School Heads.',
            'Encode indicators, upload MOVs, or certify QA.',
            'Accept or return school files in the validation queue.',
        ],
        screens: [
            { name: 'Overview', href: '/super', purpose: 'Live KPIs for schools, packets, and cycle progress.', snapshot: shot('super-overview') },
            { name: 'Divisions', href: '/super/divisions', purpose: 'SDO Cadiz City record for this single-division portal.', snapshot: shot('super-divisions') },
            { name: 'Users & roles', href: '/super/users', purpose: 'Create Division Admin and reset FAT data.', snapshot: shot('super-users') },
            { name: 'Cycles', href: '/super/cycles', purpose: 'Confirm the open FAT cycle and deadline.', snapshot: shot('super-cycles') },
        ],
        walkthrough: [
            {
                n: 1,
                title: 'Create Super Admin (first run only)',
                detail: 'Open /setup while no Super Admin exists. Enter full name, email, and password. This form disappears after the first Super is created.',
                href: '/setup',
                action: 'Create Super Admin',
            },
            {
                n: 2,
                title: 'Sign in',
                detail: 'Open Sign in and use the Super Admin email. The portal opens Overview.',
                href: '/login',
                action: 'Sign in to SmartTrack',
                snapshot: shot('login'),
            },
            {
                n: 3,
                title: 'Create Division Admin',
                detail: 'Open Users & roles. Fill full name, DepEd email, office, position, and password. Super is the only role that can create Division Admin. School Heads still self-register later.',
                href: '/super/users',
                action: 'Create Division Admin',
                snapshot: shot('super-users'),
            },
            {
                n: 4,
                title: 'Hand over Division credentials',
                detail: 'Give the Division Admin their email and temporary password. They should sign in and change the password under Account.',
                href: '/account',
                snapshot: shot('account'),
            },
            {
                n: 5,
                title: 'Confirm the FAT cycle',
                detail: 'Open Cycles and confirm the assessment window is open. Schools cannot submit without an open cycle.',
                href: '/super/cycles',
                snapshot: shot('super-cycles'),
            },
            {
                n: 6,
                title: 'Watch live counts',
                detail: 'Overview and Divisions show real database counts, not sample figures. Empty production is expected until schools register.',
                href: '/super',
                snapshot: shot('super-overview'),
            },
            {
                n: 7,
                title: 'Reset FAT data only when needed',
                detail: 'On Users & roles, type RESET FAT DATA exactly, then Clear school and FAT data. This removes School Heads, Encoders, packets, and MOV files. Super and Division stay. Do not use this as a daily tool.',
                href: '/super/users',
                action: 'Type RESET FAT DATA',
                snapshot: shot('super-users'),
            },
        ],
        notes: [
            'Production starts empty on purpose. Do not seed demo school accounts on the live site.',
            'This portal is one SDO (Cadiz City), one domain. Super does not create extra divisions as tenants.',
        ],
    },
    {
        id: 'division',
        label: 'Division Admin',
        summary: 'Accepts School Heads, validates MOV files, and records Functional or Not Yet Functional for SDO Cadiz City.',
        who: 'SDO Cadiz City SGC focal or validator. Created by Super Admin. Does not register on the public Register page.',
        canDo: [
            'Accept pending School Head registrations.',
            'See schools that belong to this division.',
            'Review submitted packets, download MOVs, Accept or Return a file with a reason.',
            'Complete review so the school result is posted.',
        ],
        cannotDo: [
            'Create Super Admin or another Division Admin.',
            'Accept Encoders — that is the School Head.',
            'Encode a school packet or certify School Head QA.',
        ],
        screens: [
            { name: 'Overview', href: '/division', purpose: 'Live submission and validation counts.', snapshot: shot('division-overview') },
            { name: 'Validation queue', href: '/division/queue', purpose: 'Packets waiting for MOV review.', snapshot: shot('division-queue') },
            { name: 'Schools', href: '/division/schools', purpose: 'Accepted schools and packet status.', snapshot: shot('division-schools') },
            { name: 'Registrations', href: '/division/registrations', purpose: 'Accept School Heads only.', snapshot: shot('division-registrations') },
            { name: 'Alerts', href: '/division/alerts', purpose: 'Overdue or returned packets.', snapshot: shot('division-alerts') },
        ],
        walkthrough: [
            {
                n: 1,
                title: 'Sign in',
                detail: 'Use the DepEd email Super Admin created. Change the password under Account after first sign-in.',
                href: '/login',
                action: 'Sign in to SmartTrack',
                snapshot: shot('login'),
            },
            {
                n: 2,
                title: 'Accept School Heads',
                detail: 'Open Registrations. Each row is a pending School Head. Confirm school name and School ID are unique, then Accept. Teachers never appear here.',
                href: '/division/registrations',
                action: 'Accept',
                snapshot: shot('division-registrations'),
            },
            {
                n: 3,
                title: 'Confirm the school list',
                detail: 'Open Schools. Accepted School Heads now appear with their School ID. Encoders stay pending until that Head accepts them.',
                href: '/division/schools',
                snapshot: shot('division-schools'),
            },
            {
                n: 4,
                title: 'Wait for a submitted packet',
                detail: 'Open Validation queue. A row appears after the School Head certifies QA and clicks Submit to Division.',
                href: '/division/queue',
                snapshot: shot('division-queue'),
            },
            {
                n: 5,
                title: 'Open Review',
                detail: 'Click Review on a packet. Download each MOV. Check Minimum MOV for every Yes and the Validity Form.',
                href: '/division/queue',
                action: 'Review',
                snapshot: shot('division-review'),
            },
            {
                n: 6,
                title: 'Accept or Return each file',
                detail: 'Accept a valid file. For an invalid file, type why in Return reason, then Return. Only that file goes back to the school.',
                action: 'Accept or Return',
                snapshot: shot('division-review'),
            },
            {
                n: 7,
                title: 'Complete the review',
                detail: 'When files are decided, complete the review. Functional needs 10 of 12 Yes with valid MOVs. The school sees the result on Submit.',
                action: 'Complete review',
                snapshot: shot('division-review'),
            },
            {
                n: 8,
                title: 'Watch returned packets come back',
                detail: 'If you returned a file, the school replaces it, the Head recertifies QA, and resubmits. The packet returns to the queue. Check Alerts for overdue schools.',
                href: '/division/alerts',
                snapshot: shot('division-alerts'),
            },
        ],
        notes: [
            'Division accepts School Heads only. Encoder accept lives under the School Head Encoders page.',
            'Do not ask a school to rebuild the whole packet for one invalid MOV.',
        ],
    },
    {
        id: 'school_head',
        label: 'School Head',
        summary: 'Owns the school identity, accepts teachers, certifies QA, submits to Division, and can remove or withdraw unapproved files.',
        who: 'Principal, Head Teacher, Teacher-in-Charge, or Officer-in-Charge. Registers with a unique school name and School ID.',
        canDo: [
            'Register as School Head and wait for Division accept.',
            'Accept Encoder (teacher) registrations for the same School ID.',
            'Encode indicators and upload MOVs, or let the Encoder do that work.',
            'Remove an unapproved MOV, certify School Head QA, submit, and withdraw if Division has not accepted any file yet.',
        ],
        cannotDo: [
            'Create Division Admin.',
            'Accept another School Head.',
            'Register a second Head with the same school name or School ID.',
            'Remove a MOV that Division already marked valid.',
        ],
        screens: [
            { name: 'Dashboard', href: '/school', purpose: 'Packet progress and 6-step FAT path.', snapshot: shot('head-dashboard') },
            { name: 'My assessment', href: '/school/assessment', purpose: 'Yes / No for 12 functionality indicators.', snapshot: shot('head-assessment') },
            { name: 'MOV files', href: '/school/movs', purpose: 'Upload, remove, or withdraw files.', snapshot: shot('head-movs') },
            { name: 'Submit', href: '/school/submit', purpose: 'Certify QA, submit, or withdraw.', snapshot: shot('head-submit') },
            { name: 'Encoders', href: '/school/encoders', purpose: 'Accept teachers for this School ID.', snapshot: shot('head-encoders') },
            { name: 'Notifications', href: '/school/notifications', purpose: 'Encoder requests and Division returns.', snapshot: shot('head-notifications') },
        ],
        walkthrough: [
            {
                n: 1,
                title: 'Register as School Head',
                detail: 'Open Register. Choose School Head. Select position from the list. Enter a unique school name and School ID. Use your DepEd email. You stay pending until Division accepts you.',
                href: '/register',
                action: 'I am registering as → School Head',
                snapshot: shot('register-head'),
            },
            {
                n: 2,
                title: 'Wait for Division accept',
                detail: 'Division Admin opens Registrations and clicks Accept. After that you can sign in and approve teachers. Encoders cannot register until you are active.',
                href: '/login',
                snapshot: shot('login'),
            },
            {
                n: 3,
                title: 'Accept teachers',
                detail: 'Open Encoders. Teachers who used your exact school name and School ID appear here. Click Accept so they can encode.',
                href: '/school/encoders',
                action: 'Accept',
                snapshot: shot('head-encoders'),
            },
            {
                n: 4,
                title: 'Encode the 12 indicators',
                detail: 'Open My assessment. Answer Yes or No for each FI. A Yes needs a Minimum MOV later. You or the Encoder can encode.',
                href: '/school/assessment',
                action: 'Yes or No',
                snapshot: shot('head-assessment'),
            },
            {
                n: 5,
                title: 'Check MOV files',
                detail: 'Open MOV files. Confirm a file for every Yes plus the Validity Form. If a draft file is wrong, Remove it. If the Encoder asked for removal, that row shows Removal requested.',
                href: '/school/movs',
                action: 'Remove',
                snapshot: shot('head-movs'),
            },
            {
                n: 6,
                title: 'Certify School Head QA',
                detail: 'Open Submit. When the checklist is clear, click Certify School Head QA. Teachers cannot click this button.',
                href: '/school/submit',
                action: 'Certify School Head QA',
                snapshot: shot('head-submit'),
            },
            {
                n: 7,
                title: 'Submit to Division',
                detail: 'Click Submit to Division. The packet enters the Division validation queue. After this, files lock until you withdraw or Division returns a file.',
                href: '/school/submit',
                action: 'Submit to Division',
                snapshot: shot('head-submit'),
            },
            {
                n: 8,
                title: 'Withdraw if not yet approved',
                detail: 'If you need to change files and Division has not marked any MOV valid, click Withdraw from Division. Then remove or replace files, certify QA again, and resubmit.',
                href: '/school/submit',
                action: 'Withdraw from Division',
                snapshot: shot('head-submit'),
            },
            {
                n: 9,
                title: 'Fix a returned MOV',
                detail: 'If Division returned a file: the Encoder replaces only that file on MOV files. You certify QA again, then click Resubmit to Division.',
                href: '/school/movs',
                snapshot: shot('head-movs'),
            },
        ],
        notes: [
            'School name and School ID must be unique among School Heads.',
            'Encoder and Head share one packet through the same School ID.',
        ],
    },
    {
        id: 'school',
        label: 'Encoder',
        summary: 'Teacher who encodes Yes / No, uploads MOVs, and requests removal of a wrong file. Cannot submit to Division.',
        who: 'Teacher, SGC coordinator, or other school staff on the Encoder position list. Registers only after an active School Head exists for that School ID.',
        canDo: [
            'Register as Encoder using the Head’s school name and School ID.',
            'Encode Yes / No on My assessment.',
            'Upload Minimum MOVs and the Validity Form.',
            'Request removal of an unapproved file so the School Head can delete it.',
            'Replace a MOV that Division returned.',
        ],
        cannotDo: [
            'Register before the School Head is active for that School ID.',
            'Use a different school name from the Head.',
            'Certify School Head QA, submit, or withdraw the packet.',
            'Remove a file directly — request removal instead.',
        ],
        screens: [
            { name: 'Dashboard', href: '/school', purpose: 'See packet progress. Submit stays locked for Encoder.', snapshot: shot('encoder-dashboard') },
            { name: 'My assessment', href: '/school/assessment', purpose: 'Answer Yes or No for 12 FIs.', snapshot: shot('encoder-assessment') },
            { name: 'MOV files', href: '/school/movs', purpose: 'Upload files and request removal.', snapshot: shot('encoder-movs') },
            { name: 'Submit', href: '/school/submit', purpose: 'Read the checklist. QA and Submit buttons are for the Head only.', snapshot: shot('encoder-submit') },
            { name: 'Notifications', href: '/school/notifications', purpose: 'Accept notice, returned MOV, and Head actions.', snapshot: shot('encoder-notifications') },
        ],
        walkthrough: [
            {
                n: 1,
                title: 'Confirm the School Head is active',
                detail: 'Your Principal must already be registered, accepted by Division, and active. Ask for the exact school name and School ID. You cannot register until that Head exists.',
            },
            {
                n: 2,
                title: 'Register as Encoder',
                detail: 'Open Register. Choose Encoder. Select your position. Type the same school name and School ID as the Head. Use your DepEd email.',
                href: '/register',
                action: 'I am registering as → Encoder',
                snapshot: shot('register-encoder'),
            },
            {
                n: 3,
                title: 'Wait for School Head accept',
                detail: 'You stay pending. The Head opens Encoders and clicks Accept. Then you can sign in and encode.',
                href: '/login',
                snapshot: shot('login'),
            },
            {
                n: 4,
                title: 'Encode all 12 indicators',
                detail: 'Open My assessment. Click Yes or No for each functionality indicator. Yes requires a Minimum MOV on the next page.',
                href: '/school/assessment',
                action: 'Yes or No',
                snapshot: shot('encoder-assessment'),
            },
            {
                n: 5,
                title: 'Upload MOV files',
                detail: 'Open MOV files. Upload the Minimum MOV for every Yes, plus the Validity Form. Wait for School Head review and Division validation.',
                href: '/school/movs',
                snapshot: shot('encoder-movs'),
            },
            {
                n: 6,
                title: 'Request removal if a file is wrong',
                detail: 'If the packet is not yet approved and a file is wrong, click Request removal. The Head is notified and can Remove the file so you can upload again.',
                href: '/school/movs',
                action: 'Request removal',
                snapshot: shot('encoder-movs'),
            },
            {
                n: 7,
                title: 'Leave QA and Submit to the Head',
                detail: 'Open Submit to see the checklist. Only the School Head can Certify School Head QA and Submit to Division.',
                href: '/school/submit',
                snapshot: shot('encoder-submit'),
            },
            {
                n: 8,
                title: 'Replace a returned file',
                detail: 'If Division returns a MOV, replace only that file. The Head certifies QA again and resubmits. You do not rebuild the whole packet.',
                href: '/school/movs',
                snapshot: shot('encoder-movs'),
            },
        ],
        notes: [
            'Position list changes with “I am registering as.” Encoder positions are teacher and SGC staff titles, not Principal titles.',
            'One School ID = one packet. Do not invent a second school code for the same school.',
        ],
    },
];

export function guideFor(role: Role): RoleGuide {
    return roleGuides.find((guide) => guide.id === role) ?? roleGuides[3];
}
