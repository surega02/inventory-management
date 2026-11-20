/**
 * Chat history lives only in this browser (decision 20 Nov 2026): one localStorage
 * key per user, cleared on logout. The server never stores it; it only receives
 * the last MAX_SENT messages with each question.
 */

export type ChatLink = { label: string; url: string };

export type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
    links?: ChatLink[];
    /** Error notices are shown but never sent back as history. */
    error?: boolean;
};

const PREFIX = 'chat-history:';
const MAX_STORED = 40;
export const MAX_SENT = 6;
export const MAX_SENT_CONTENT = 2000;
export const MAX_MESSAGE = 500;

export function loadChatHistory(userId: number): ChatMessage[] {
    try {
        const raw = localStorage.getItem(PREFIX + userId);
        const parsed: unknown = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed) ? (parsed as ChatMessage[]) : [];
    } catch {
        return [];
    }
}

export function saveChatHistory(userId: number, messages: ChatMessage[]): void {
    try {
        localStorage.setItem(
            PREFIX + userId,
            JSON.stringify(messages.slice(-MAX_STORED)),
        );
    } catch {
        // storage full or blocked: the chat still works for this page view
    }
}

/** Removes every user's history from this browser; called on logout. */
export function clearAllChatHistory(): void {
    try {
        Object.keys(localStorage)
            .filter((key) => key.startsWith(PREFIX))
            .forEach((key) => localStorage.removeItem(key));
    } catch {
        // nothing to clear
    }
}

/** The short history the server accepts: last 6 non-error messages, content clipped. */
export function historyToSend(
    messages: ChatMessage[],
): { role: 'user' | 'assistant'; content: string }[] {
    return messages
        .filter((m) => !m.error)
        .slice(-MAX_SENT)
        .map((m) => ({
            role: m.role,
            content: m.content.slice(0, MAX_SENT_CONTENT),
        }));
}
