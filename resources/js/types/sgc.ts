export type Role = 'super' | 'division' | 'school' | 'school_head';

export type AssessmentStatus =
    | 'not_started'
    | 'in_progress'
    | 'submitted'
    | 'under_review'
    | 'returned'
    | 'validated';

export type MovStatus = 'draft' | 'valid' | 'returned';

export type SubmitStep = 'encode' | 'movs' | 'qa' | 'submit' | 'review' | 'result';

export type Tone = 'ok' | 'warn' | 'bad' | 'todo';

export interface Kpi {
    label: string;
    value: string;
    hint?: string | null;
    tone?: string | null;
}

export interface ChartBar {
    label: string;
    value: string;
    height: number;
    tone?: string | null;
}

export interface ChartBlock {
    title: string;
    hint: string;
    bars: ChartBar[];
}

export interface BadgeCell {
    badge: string;
    tone: Tone | string;
}

export type TableCell = string | BadgeCell;

export interface NavItem {
    label: string;
    href: string;
}

export interface Notice {
    id?: string;
    unread: boolean;
    title: string;
    detail: string;
    when: string;
    href?: string | null;
}

export interface FlowStep {
    n: string;
    label: string;
    hint: string;
    href: string;
    state: 'done' | 'now' | 'lock' | string;
}

export interface Flow {
    banner: string;
    path: string;
    steps: FlowStep[];
}

export interface CheckItem {
    mark: string;
    tone: Tone | string;
    title: string;
    hint: string;
}

export function isBadge(cell: TableCell): cell is BadgeCell {
    return typeof cell === 'object' && cell !== null && 'badge' in cell;
}
