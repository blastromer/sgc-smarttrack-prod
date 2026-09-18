import type { LucideIcon } from 'lucide-vue-next';
import type { Flow, NavItem as SgcNavItem, Notice, Role } from './sgc';

export type { Role, AssessmentStatus, MovStatus, SubmitStep } from './sgc';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote?: { message: string; author: string };
    auth: Auth;
    sgc: {
        nav: SgcNavItem[];
        notices: Notice[];
        noticeHref: string;
        flow: Flow | null;
    } | null;
    flash?: {
        status?: string | null;
    };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: Role;
    role_label: string;
    status: 'active' | 'pending' | 'rejected';
    office?: string | null;
    school_name?: string | null;
    school_code?: string | null;
    position?: string | null;
    school_id?: number | null;
    division_id?: number | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
