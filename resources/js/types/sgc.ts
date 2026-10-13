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
    /** Optional card color: blue, green, orange, red, or purple. */
    accent?: string | null;
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

export interface AiAssist {
    template: string;
    hint: string;
    next: string;
    unencoded: string[];
    missing: string[];
    returned: string[];
    href: string;
    open: number;
    live?: boolean;
    role?: 'school' | 'division' | 'super' | string;
    uses?: string[];
    ideas?: string[];
    graphs?: string;
    prompts?: string[];
    page?: {
        key: string;
        title: string;
        explain: string;
    };
}

export interface CheckItem {
    mark: string;
    tone: Tone | string;
    title: string;
    hint: string;
}

export type DashboardStatusKey = 'validated' | 'for_validation' | 'returned' | 'in_progress' | 'not_started';

export interface DashboardPayload {
    cycles: { id: number; name: string }[];
    cycle_id: number | null;
    cycle_name: string | null;
    total: number;
    kpis: { key: string; label: string; value: number; hint: string }[];
    status: { key: DashboardStatusKey; label: string; count: number; pct: number }[];
    indicators: { code: string; title: string; pct: number }[];
    activities: { type: 'submitted' | 'validated' | 'returned' | 'account' | string; text: string; when: string }[];
    schools: {
        id: number;
        name: string;
        school_code: string;
        status: DashboardStatusKey;
        score: string;
        submitted: string | null;
        validated: string | null;
        review_id: number | null;
        can_validate: boolean;
        view_href: string | null;
    }[];
    hrefs: { indicators: string; activities: string; schools: string; queue: string | null };
}

export function isBadge(cell: TableCell): cell is BadgeCell {
    return typeof cell === 'object' && cell !== null && 'badge' in cell;
}
