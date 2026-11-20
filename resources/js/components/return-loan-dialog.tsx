import { Form } from '@inertiajs/react';
import LoanController from '@/actions/App/Http/Controllers/LoanController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { AssetCondition } from '@/types';

export const fieldClass =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

type Props = {
    /** null closes the dialog */
    loan: { id: number; assetCode: string; borrower: string } | null;
    conditions: AssetCondition[];
    onClose: () => void;
};

export default function ReturnLoanDialog({ loan, conditions, onClose }: Props) {
    return (
        <Dialog
            open={loan !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {loan && (
                    <>
                        <DialogHeader>
                            <DialogTitle>Return {loan.assetCode}</DialogTitle>
                            <DialogDescription>
                                Borrowed by {loan.borrower}. The asset becomes
                                available again, or broken if marked below.
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            {...LoanController.return.form(loan.id)}
                            onSuccess={onClose}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="return_condition">
                                            Condition on return
                                        </Label>
                                        <select
                                            id="return_condition"
                                            name="return_condition"
                                            defaultValue="good"
                                            className={`h-9 ${fieldClass} py-0`}
                                        >
                                            {conditions.map((c) => (
                                                <option key={c} value={c}>
                                                    {c.charAt(0).toUpperCase() +
                                                        c.slice(1)}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.return_condition}
                                        />
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="mark_broken"
                                            value="1"
                                            className="size-4"
                                        />
                                        Mark the asset as broken
                                    </label>
                                    <div className="grid gap-2">
                                        <Label htmlFor="return_notes">
                                            Notes (optional)
                                        </Label>
                                        <textarea
                                            id="return_notes"
                                            name="notes"
                                            rows={2}
                                            maxLength={1000}
                                            className={fieldClass}
                                        />
                                        <InputError message={errors.notes} />
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={onClose}
                                        >
                                            Cancel
                                        </Button>
                                        <Button disabled={processing}>
                                            Record return
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
