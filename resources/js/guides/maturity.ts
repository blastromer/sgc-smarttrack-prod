export type MaturityStep = {
    key: string;
    label: string;
    href: string;
};

export type PageGuide = {
    title: string;
    lead: string;
    steps: string[];
    jump?: { href: string; label: string };
};

const levelOneNote = 'Completing this step counts toward Level I, Being Organized.';
const levelTwoNote = 'Completing this step counts toward Level II. The year’s indicators and Division validation stay on that level.';
const levelThreeNote = 'Completing this step counts toward Level III.';

export const pageGuides: Record<string, PageGuide> = {
    structure: {
        title: 'Establish the council structure and officers',
        lead: 'Record the officers here, then prepare the organizational chart.',
        steps: [
            'Enter the elected co-chair, designated co-chair, secretary, and school head.',
            'Save form data. Those names are stamped on the official Word files.',
            'Open Templates and prepare the Organizational chart.',
            levelOneNote,
        ],
        jump: { href: '#sgc-officers', label: 'Go to SGC officers' },
    },
    members: {
        title: 'Confirm the members',
        lead: 'Prepare the official member list and membership certificates, then upload the finished files.',
        steps: [
            'Open or download Official list of SGC members.',
            'Open or download Membership certificate for the voting members.',
            'Complete the remaining blanks in Word or PowerPoint.',
            'Upload the finished files on MOV files, from the matching slot on My assessment.',
            levelOneNote,
        ],
        jump: { href: '#template-members-list-sgc', label: 'Go to the member list' },
    },
    operations: {
        title: 'Start basic operations',
        lead: 'Set the next meeting here, then prepare the notice and the minutes.',
        steps: [
            'Enter the meeting subject, date and time, venue, and purpose.',
            'Save form data.',
            'Open Templates and prepare the Notice of Meeting and the Minutes of the Meeting.',
            'Upload the completed records on MOV files.',
            levelOneNote,
        ],
        jump: { href: '#sgc-meeting', label: 'Go to the meeting defaults' },
    },
    encode: {
        title: 'Encode the assessment and upload the minimum MOVs',
        lead: 'Each indicator is answered on this page. A Yes needs its minimum MOV on the same card.',
        steps: [
            'Open an indicator and choose Yes or No.',
            'For every Yes, upload the minimum MOV shown on that card. Additional files do not score.',
            'Replace a returned file before it can be accepted.',
            levelTwoNote,
        ],
    },
    baseline: {
        title: 'Meet the baseline indicator requirements',
        lead: 'Level II steps up when two MOV files are uploaded: minutes with different school stakeholders, and the stakeholder monitoring report.',
        steps: [
            'Open MOV files and upload Minutes of Meetings with different school stakeholders.',
            'Upload the stakeholder monitoring report on the same page.',
            'Save both files. The school and the Division then show Level II when the other Level II steps are already done.',
            levelTwoNote,
        ],
    },
    stakeholders: {
        title: 'Connect the school with its stakeholders',
        lead: 'Use the meeting records to show the council working with stakeholders.',
        steps: [
            'Prepare the Notice of Meeting and the Minutes of the Meeting.',
            'Complete the blanks in Word.',
            'Upload the finished records on MOV files.',
            levelTwoNote,
        ],
        jump: { href: '#template-notice-sgc', label: 'Go to the notice of meeting' },
    },
    improvement: {
        title: 'Lead school improvement',
        lead: 'Prepare the plan the council will use to lead school improvement. The indicators for this level are not assigned yet.',
        steps: [
            'Open or download the Action / advocacy plan.',
            'Complete the blanks in Word.',
            'Upload the finished file on MOV files.',
            levelThreeNote,
        ],
        jump: { href: '#template-action-plan-sgc', label: 'Go to the action plan' },
    },
    partnerships: {
        title: 'Sustain community partnerships',
        lead: 'Prepare the project brief the council will use with community partners. The indicators for this level are not assigned yet.',
        steps: [
            'Open or download the Concept note / project brief.',
            'Complete the blanks in Word.',
            'Upload the finished file on MOV files.',
            levelThreeNote,
        ],
        jump: { href: '#template-concept-note-sgc', label: 'Go to the concept note' },
    },
    submit: {
        title: 'School Head QA, submit, and Division validation',
        lead: 'The School Head checks the packet on this page, then sends it to the Division.',
        steps: [
            'Read the checklist. Every row must be clear before Division will accept the packet.',
            'Certify School Head QA.',
            'Submit to Division. The Division validates the packet.',
            'If a MOV is returned, replace that file, certify QA again, and resubmit.',
            levelTwoNote,
        ],
    },
};

const href = (key: string) => `/school/${destination(key)}?guide=${key}`;

const destination = (key: string) => {
    if (key === 'members' || key === 'stakeholders' || key === 'improvement' || key === 'partnerships') {
        return 'templates';
    }
    if (key === 'encode' || key === 'baseline') {
        return 'assessment';
    }
    if (key === 'submit') {
        return 'submit';
    }

    return 'form-data';
};

export const maturityStep = (key: keyof typeof pageGuides, label: string): MaturityStep => ({
    key,
    label,
    href: href(key),
});
