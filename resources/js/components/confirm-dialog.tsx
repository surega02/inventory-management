import { router } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ConfirmRequest = {
    /** Names the action and the object, e.g. "Delete category LAP?" */
    title: string;
    /** What happens next and whether it can be undone. */
    description: ReactNode;
    /** Verb label for the confirm button, e.g. "Delete category" (never "OK"). */
    confirmLabel: string;
    destructive?: boolean;
    method: 'post' | 'delete';
    url: string;
};

type Props = {
    /** null closes the dialog */
    request: ConfirmRequest | null;
    onClose: () => void;
};

/**
 * Replaces window.confirm: a modal alert that states the consequence, names the
 * action on its button, styles destructive actions red and keeps focus on Cancel
 * so Enter does not delete by accident. Sends the request itself and stays open
 * (button disabled) until the server answers.
 */
export default function ConfirmDialog({ request, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        if (!request) {
            return;
        }

        router.visit(request.url, {
            method: request.method,
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                onClose();
            },
        });
    };

    return (
        <Dialog
            open={request !== null}
            onOpenChange={(open) => !open && !processing && onClose()}
        >
            <DialogContent role="alertdialog" className="sm:max-w-md">
                {request && (
                    <>
                        <DialogHeader className="sm:flex-row sm:items-start sm:gap-4">
                            {request.destructive && (
                                <div className="mx-auto flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive sm:mx-0">
                                    <TriangleAlert className="size-5" />
                                </div>
                            )}
                            <div className="grid gap-2">
                                <DialogTitle>{request.title}</DialogTitle>
                                <DialogDescription>
                                    {request.description}
                                </DialogDescription>
                            </div>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                autoFocus
                                disabled={processing}
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    request.destructive
                                        ? 'destructive'
                                        : 'default'
                                }
                                disabled={processing}
                                onClick={confirm}
                            >
                                {processing ? 'Working…' : request.confirmLabel}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
