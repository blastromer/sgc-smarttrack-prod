export type Role = 'super' | 'division' | 'school';

export type AssessmentStatus =
    | 'not_started'
    | 'in_progress'
    | 'submitted'
    | 'under_review'
    | 'returned'
    | 'validated';

export type MovStatus = 'draft' | 'valid' | 'returned';

export type SubmitStep = 'encode' | 'movs' | 'qa' | 'submit' | 'review' | 'result';
