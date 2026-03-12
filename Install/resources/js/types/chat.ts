import { PageProps, User } from '@/types';

export interface ChatMessage {
    id: string;
    type: 'user' | 'assistant' | 'system' | 'activity';
    content: string;
    timestamp: Date;
    activityType?: string;
    thinkingDuration?: number;
    /** Agent result: what was changed (for assistant messages after apply) */
    actionLog?: string[];
    /** Scope of change: e.g. "Home page only", "Global header" */
    scope?: string;
    /** Short "what changed" summary for completed agent edits */
    resultSummary?: string;
    /** Activity log: action executed, component affected, optional old/new values (from AI executor) */
    appliedChanges?: Array<{
        op?: string;
        section_id?: string;
        component?: string;
        summary?: string[];
        old_value?: unknown;
        new_value?: unknown;
    }>;
    /** Backend debug trace shown when AI claims success/failure */
    diagnosticLog?: string[];
}

export interface ChatProps extends PageProps {
    user: User;
}
