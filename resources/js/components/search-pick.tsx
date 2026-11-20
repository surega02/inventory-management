import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
    const first = useRef(true);

    useEffect(() => {
        const delay = first.current ? 0 : 300;
        first.current = false;
        const timer = setTimeout(
            () =>
                router.reload({
                    only: [prop],
                    data: { [param]: term },
                    preserveUrl: true,
                }),
            delay,
        );

        return () => clearTimeout(timer);
    }, [term, prop, param]);

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
                    onClick={() => setPicked(null)}
                >
                    <X />
                </Button>
            </div>
        );
    }

    return (
        <div className="grid gap-1">
            <Input
                id={id}
                value={term}
                placeholder={placeholder}
                autoComplete="off"
                onChange={(e) => setTerm(e.target.value)}
            />
            <ul
                role="listbox"
                className="max-h-44 overflow-y-auto rounded-md border text-sm"
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
                {options?.map((o) => (
                    <li key={o.id} role="option" aria-selected={false}>
                        <button
                            type="button"
                            className="w-full px-3 py-1.5 text-left hover:bg-muted"
                            onClick={() => setPicked(o)}
                        >
                            {o.label}
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
