import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import MaintenanceController from '@/actions/App/Http/Controllers/MaintenanceController';
import MovementController from '@/actions/App/Http/Controllers/MovementController';
import InputError from '@/components/input-error';
import { fieldClass } from '@/components/return-loan-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MaintenanceRow, Option } from '@/types';

/** YYYY-MM-DD in the browser's timezone (toISOString would give the UTC date). */
const today = () => new Date().toLocaleDateString('en-CA');

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function Shell({
    open,
    onClose,
    title,
    description,
    children,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}

function Footer({
    onClose,
    processing,
    label,
}: {
    onClose: () => void;
    processing: boolean;
    label: string;
}) {
    return (
        <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
                Cancel
            </Button>
            <Button disabled={processing}>{label}</Button>
        </DialogFooter>
    );
}

export function MoveAssetDialog({
    open,
    onClose,
    asset,
    locations,
}: {
    open: boolean;
    onClose: () => void;
    asset: { id: number; code: string; location: string };
    locations: Option[];
}) {
    return (
        <Shell
            open={open}
            onClose={onClose}
            title={`Move ${asset.code}`}
            description={`Currently at ${asset.location}.`}
        >
            <Form
                {...MovementController.store.form(asset.id)}
                onSuccess={onClose}
                className="space-y-4"
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            id="to_location_id"
                            label="New location"
                            error={errors.to_location_id}
                        >
                            <select
                                id="to_location_id"
                                name="to_location_id"
                                required
                                defaultValue=""
                                className={`h-9 ${fieldClass} py-0`}
                            >
                                <option value="" disabled>
                                    Pick a location
                                </option>
                                {locations
                                    .filter((l) => l.name !== asset.location)
                                    .map((l) => (
                                        <option key={l.id} value={l.id}>
                                            {l.code} · {l.name}
                                        </option>
                                    ))}
                            </select>
                        </Field>
                        <Field
                            id="moved_at"
                            label="Moved on (optional, defaults to now)"
                            error={errors.moved_at}
                        >
                            <Input
                                id="moved_at"
                                name="moved_at"
                                type="date"
                                max={today()}
                            />
                        </Field>
                        <Field
                            id="reason"
                            label="Reason (optional)"
                            error={errors.reason}
                        >
                            <Input
                                id="reason"
                                name="reason"
                                maxLength={255}
                                placeholder="e.g. Team relocation"
                            />
                        </Field>
                        <Footer
                            onClose={onClose}
                            processing={processing}
                            label="Move asset"
                        />
                    </>
                )}
            </Form>
        </Shell>
    );
}

export function StartMaintenanceDialog({
    open,
    onClose,
    asset,
}: {
    open: boolean;
    onClose: () => void;
    asset: { id: number; code: string };
}) {
    return (
        <Shell
            open={open}
            onClose={onClose}
            title={`Start maintenance for ${asset.code}`}
            description="The asset is set to maintenance until the job is completed."
        >
            <Form
                {...MaintenanceController.store.form(asset.id)}
                onSuccess={onClose}
                className="space-y-4"
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            id="description"
                            label="What is being done"
                            error={errors.description}
                        >
                            <textarea
                                id="description"
                                name="description"
                                rows={3}
                                required
                                maxLength={1000}
                                placeholder="e.g. Battery replacement"
                                className={fieldClass}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="started_at"
                                label="Started on"
                                error={errors.started_at}
                            >
                                <Input
                                    id="started_at"
                                    name="started_at"
                                    type="date"
                                    required
                                    defaultValue={today()}
                                    max={today()}
                                />
                            </Field>
                            <Field
                                id="cost"
                                label="Estimated cost, Rp (optional)"
                                error={errors.cost}
                            >
                                <Input
                                    id="cost"
                                    name="cost"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                />
                            </Field>
                        </div>
                        <Field
                            id="vendor"
                            label="Vendor (optional)"
                            error={errors.vendor}
                        >
                            <Input id="vendor" name="vendor" maxLength={150} />
                        </Field>
                        <Footer
                            onClose={onClose}
                            processing={processing}
                            label="Start maintenance"
                        />
                    </>
                )}
            </Form>
        </Shell>
    );
}

export function CompleteMaintenanceDialog({
    maintenance,
    onClose,
    assetCode,
}: {
    /** null closes the dialog */
    maintenance: MaintenanceRow | null;
    onClose: () => void;
    assetCode: string;
}) {
    return (
        <Shell
            open={maintenance !== null}
            onClose={onClose}
            title={`Complete maintenance of ${assetCode}`}
            description={
                maintenance
                    ? `${maintenance.description}. The asset becomes available again.`
                    : ''
            }
        >
            {maintenance && (
                <Form
                    {...MaintenanceController.complete.form(maintenance.id)}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="completed_at"
                                    label="Completed on"
                                    error={errors.completed_at}
                                >
                                    <Input
                                        id="completed_at"
                                        name="completed_at"
                                        type="date"
                                        required
                                        defaultValue={today()}
                                        min={maintenance.started_at}
                                        max={today()}
                                    />
                                </Field>
                                <Field
                                    id="final_cost"
                                    label="Final cost, Rp (optional)"
                                    error={errors.cost}
                                >
                                    <Input
                                        id="final_cost"
                                        name="cost"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        defaultValue={maintenance.cost ?? ''}
                                    />
                                </Field>
                            </div>
                            <Footer
                                onClose={onClose}
                                processing={processing}
                                label="Complete"
                            />
                        </>
                    )}
                </Form>
            )}
        </Shell>
    );
}
