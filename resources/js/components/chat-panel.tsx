import { Link, usePage } from '@inertiajs/react';
import { Bot, MessageCircle, SendHorizontal, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    historyToSend,
    loadChatHistory,
    MAX_MESSAGE,
    saveChatHistory,
} from '@/lib/chat-history';
import type { ChatLink, ChatMessage } from '@/lib/chat-history';
import { cn } from '@/lib/utils';
import { messages as chatMessages } from '@/routes/chat';
import type { Auth } from '@/types';

type ChatResponse = {
    reply: string;
    links: ChatLink[];
    meta: { tool_calls: number; truncated: boolean };
};

type ChatError = {
    error?: { code?: string; message?: string; retry_after?: number };
};

const SUGGESTIONS = {
    employee: [
        'What am I borrowing right now?',
        'Kapan pinjaman saya jatuh tempo?',
    ],
    manager: [
        'Which loans are overdue?',
        'Berapa jumlah aset per kategori?',
        'Show assets under maintenance.',
    ],
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function errorText(status: number, body: ChatError): string {
    const error = body.error;

    if (status === 429 && error?.retry_after) {
        const minutes = Math.max(1, Math.ceil(error.retry_after / 60));

        return `You have reached the limit of 20 messages per hour. Try again in about ${minutes} minute${minutes === 1 ? '' : 's'}.`;
    }

    if (status === 401 || status === 419) {
        return 'Your session has expired. Please reload the page and sign in again.';
    }

    return error?.message ?? 'Something went wrong. Please try again.';
}

export function ChatPanel() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const user = auth.user;
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState<ChatMessage[]>(() =>
        loadChatHistory(user.id),
    );
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        saveChatHistory(user.id, messages);
    }, [user.id, messages]);

    useEffect(() => {
        if (open) {
            bottomRef.current?.scrollIntoView({ block: 'end' });
        }
    }, [open, messages, sending]);

    const suggestions =
        user.role === 'employee' ? SUGGESTIONS.employee : SUGGESTIONS.manager;

    async function send(text: string) {
        const message = text.trim();

        if (message === '' || sending) {
            return;
        }

        const history = historyToSend(messages);
        setMessages((prev) => [...prev, { role: 'user', content: message }]);
        setInput('');
        setSending(true);

        let reply: ChatMessage;

        try {
            const response = await fetch(chatMessages.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message, history }),
            });
            const body: unknown = await response.json().catch(() => ({}));

            if (response.ok) {
                const data = body as ChatResponse;
                reply = {
                    role: 'assistant',
                    content: data.reply || '(no answer)',
                    links: data.links,
                };
            } else {
                reply = {
                    role: 'assistant',
                    content: errorText(response.status, body as ChatError),
                    error: true,
                };
            }
        } catch {
            reply = {
                role: 'assistant',
                content:
                    'Could not reach the server. Check your connection and try again.',
                error: true,
            };
        }

        setMessages((prev) => [...prev, reply]);
        setSending(false);
        inputRef.current?.focus();
    }

    function onSubmit(event: FormEvent) {
        event.preventDefault();
        void send(input);
    }

    function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
        if (
            event.key === 'Enter' &&
            !event.shiftKey &&
            !event.nativeEvent.isComposing
        ) {
            event.preventDefault();
            void send(input);
        }
    }

    return (
        <>
            <Button
                size="icon"
                className="fixed right-4 bottom-4 z-40 size-12 rounded-full shadow-lg"
                onClick={() => setOpen(true)}
                aria-label="Open assistant"
                data-test="chat-open"
            >
                <MessageCircle className="size-5" />
            </Button>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent
                    side="right"
                    className="w-full gap-0 sm:max-w-md"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        inputRef.current?.focus();
                    }}
                >
                    <SheetHeader className="border-b pr-12">
                        <div className="flex items-center justify-between gap-2">
                            <SheetTitle className="flex items-center gap-2">
                                <Bot className="size-5" />
                                Assistant
                            </SheetTitle>
                            {messages.length > 0 && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setMessages([])}
                                    disabled={sending}
                                >
                                    <Trash2 />
                                    Clear
                                </Button>
                            )}
                        </div>
                        <SheetDescription>
                            Read-only. Answers only from data you can access.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 space-y-3 overflow-y-auto p-4">
                        {messages.length === 0 && (
                            <div className="space-y-3 text-sm text-muted-foreground">
                                <p>
                                    Ask about assets, loans
                                    {user.role === 'employee'
                                        ? ''
                                        : ', maintenance'}{' '}
                                    or inventory numbers, in English or
                                    Indonesian.
                                </p>
                                <div className="flex flex-col items-start gap-2">
                                    {suggestions.map((s) => (
                                        <Button
                                            key={s}
                                            variant="outline"
                                            size="sm"
                                            className="h-auto py-1.5 text-left whitespace-normal"
                                            onClick={() => void send(s)}
                                        >
                                            {s}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {messages.map((m, i) => (
                            <div
                                key={i}
                                className={cn(
                                    'flex flex-col gap-2',
                                    m.role === 'user'
                                        ? 'items-end'
                                        : 'items-start',
                                )}
                            >
                                <div
                                    className={cn(
                                        'max-w-[85%] rounded-lg px-3 py-2 text-sm break-words whitespace-pre-wrap',
                                        m.role === 'user'
                                            ? 'bg-primary text-primary-foreground'
                                            : m.error
                                              ? 'border border-destructive/40 bg-destructive/10 text-destructive'
                                              : 'bg-muted',
                                    )}
                                >
                                    {m.content}
                                </div>
                                {m.links && m.links.length > 0 && (
                                    <div className="flex max-w-[85%] flex-wrap gap-1.5">
                                        {m.links.map((link) => (
                                            <Link
                                                key={link.url}
                                                href={link.url}
                                                onClick={() => setOpen(false)}
                                                className="rounded-md border px-2 py-0.5 text-xs font-medium hover:bg-accent"
                                            >
                                                {link.label}
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}

                        {sending && (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Spinner />
                                Thinking…
                            </div>
                        )}
                        <div ref={bottomRef} />
                    </div>

                    <form onSubmit={onSubmit} className="border-t p-4">
                        <div className="flex items-end gap-2">
                            <textarea
                                ref={inputRef}
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={onKeyDown}
                                maxLength={MAX_MESSAGE}
                                rows={2}
                                placeholder="Ask a question…"
                                aria-label="Message"
                                className="min-h-[2.5rem] flex-1 resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <Button
                                type="submit"
                                size="icon"
                                disabled={sending || input.trim() === ''}
                                aria-label="Send"
                            >
                                <SendHorizontal />
                            </Button>
                        </div>
                        <p className="mt-1 text-right text-xs text-muted-foreground">
                            {input.length}/{MAX_MESSAGE}
                        </p>
                    </form>
                </SheetContent>
            </Sheet>
        </>
    );
}
