import { router } from '@inertiajs/react';
import { ChevronDown, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { PickOption } from '@/types';

type Props = {
    id: string;
    /** form field that receives the picked id */
    name: string;
    /** Inertia prop holding the options, refreshed by a partial reload */
    prop: string;
    /** query-string key the server filters that prop by */
    param: string;
    options: PickOption[] | undefined;
    placeholder: string;
};

/**
 * Combobox without extra dependencies: typing reloads only `prop` with `param`
 * (server-side search, max 20 results) and a click picks one into a hidden input.
 *
 * The list opens only on a click, typing or ArrowDown, never on plain focus, so a
 * dialog autofocusing this input does not pop the list open.
 */
export default function SearchPick({
    id,
    name,
    prop,
    param,
    options,
    placeholder,
}: Props) {
    const [term, setTerm] = useState('');
    const [picked, setPicked] = useState<PickOption | null>(null);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    const loadedFor = useRef<string | null>(null);
    const input = useRef<HTMLInputElement>(null);
    const listId = `${id}-list`;

    // fetch only while open; the first open loads right away, typing is debounced
    useEffect(() => {
        if (!open || loadedFor.current === term) {
            return;
        }

        const timer = setTimeout(
            () => {
                loadedFor.current = term;
                router.reload({
                    only: [prop],
                    data: { [param]: term },
                    preserveUrl: true,
                });
            },
            loadedFor.current === null ? 0 : 300,
        );

        return () => clearTimeout(timer);
    }, [open, term, prop, param]);

    const pick = (o: PickOption) => {
        setPicked(o);
        setOpen(false);
        setActive(-1);
    };

    const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        const count = options?.length ?? 0;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setOpen(true);
            setActive((i) => (count ? (i + 1) % count : -1));
        } else if (e.key === 'ArrowUp' && open) {
            e.preventDefault();
            setActive((i) => (count ? (i - 1 + count) % count : -1));
        } else if (e.key === 'Enter' && open) {
            // never submit the surrounding form from the search box
            e.preventDefault();
            const option = options?.[active];

            if (option) {
                pick(option);
            }
        }
    };

    // Escape closes the list only, not the dialog around it. Radix listens on the
    // document in the capture phase, so claim the key earlier, on window.
    useEffect(() => {
        if (!open) {
            return;
        }

        const onEscape = (e: globalThis.KeyboardEvent) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                setOpen(false);
            }
        };

        window.addEventListener('keydown', onEscape, true);

        return () => window.removeEventListener('keydown', onEscape, true);
    }, [open]);

    if (picked) {
        return (
            <div className="flex h-9 items-center justify-between rounded-md border px-3 text-sm">
                <input type="hidden" name={name} value={picked.id} />
                <span className="truncate">{picked.label}</span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-6"
                    aria-label="Change"
                    onClick={() => {
                        setPicked(null);
                        setOpen(true);
                        requestAnimationFrame(() => input.current?.focus());
                    }}
                >
                    <X />
                </Button>
            </div>
        );
    }

    return (
        <div className="relative">
            <Input
                ref={input}
                id={id}
                value={term}
                placeholder={placeholder}
                autoComplete="off"
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-autocomplete="list"
                aria-activedescendant={
                    open && active >= 0 ? `${listId}-${active}` : undefined
                }
                className="pr-8"
                onClick={() => setOpen(true)}
                onBlur={() => setOpen(false)}
                onKeyDown={onKeyDown}
                onChange={(e) => {
                    setTerm(e.target.value);
                    setActive(-1);
                    setOpen(true);
                }}
            />
            <ChevronDown
                aria-hidden
                className="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />
            {open && (
                <ul
                    id={listId}
                    role="listbox"
                    className="absolute inset-x-0 top-full z-50 mt-1 max-h-44 overflow-y-auto rounded-md border bg-popover text-sm shadow-md"
                >
                    {options === undefined && (
                        <li className="px-3 py-2 text-muted-foreground">
                            Loading…
                        </li>
                    )}
                    {options?.length === 0 && (
                        <li className="px-3 py-2 text-muted-foreground">
                            No matches.
                        </li>
                    )}
                    {options?.map((o, i) => (
                        <li
                            key={o.id}
                            id={`${listId}-${i}`}
                            role="option"
                            aria-selected={i === active}
                            className={`cursor-pointer px-3 py-1.5 ${
                                i === active ? 'bg-muted' : 'hover:bg-muted'
                            }`}
                            // keep focus in the input so onBlur does not close the list first
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => pick(o)}
                        >
                            {o.label}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
