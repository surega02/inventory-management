import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AssetController from '@/actions/App/Http/Controllers/AssetController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/assets';
import type {
    AssetCondition,
    AssetDetail,
    CategoryOption,
    Option,
} from '@/types';

type Props = {
    asset: AssetDetail | null; // null = create
    categories: CategoryOption[];
    locations: Option[];
    conditions: AssetCondition[];
};

const fieldClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

export default function AssetForm({
    asset,
    categories,
    locations,
    conditions,
}: Props) {
    const [categoryId, setCategoryId] = useState('');
    const lifePlaceholder = categories.find(
        (c) => String(c.id) === categoryId,
    )?.default_useful_life_years;
    const title = asset ? `Edit ${asset.code}` : 'Add asset';

    return (
        <>
            <Head title={title} />

            <div className="max-w-2xl p-4">
                <Heading
                    title={title}
                    description={
                        asset
                            ? 'Code, category and location cannot change here. Move the asset to change its location.'
                            : 'The code is generated from the category, e.g. LAP-0321.'
                    }
                />

                <Form
                    {...(asset
                        ? AssetController.update.form(asset.id)
                        : AssetController.store.form())}
                    className="grid gap-4 sm:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={asset?.name}
                                    required
                                    maxLength={150}
                                />
                                <InputError message={errors.name} />
                            </div>

                            {asset ? (
                                <>
                                    <div className="grid gap-2">
                                        <Label>Category</Label>
                                        <p className="text-sm text-muted-foreground">
                                            {asset.category}
                                        </p>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Location</Label>
                                        <p className="text-sm text-muted-foreground">
                                            {asset.location}
                                        </p>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="category_id">
                                            Category
                                        </Label>
                                        <select
                                            id="category_id"
                                            name="category_id"
                                            required
                                            value={categoryId}
                                            onChange={(e) =>
                                                setCategoryId(e.target.value)
                                            }
                                            className={fieldClass}
                                        >
                                            <option value="" disabled>
                                                Choose a category
                                            </option>
                                            {categories.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.code} · {c.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.category_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="location_id">
                                            Location
                                        </Label>
                                        <select
                                            id="location_id"
                                            name="location_id"
                                            required
                                            defaultValue=""
                                            className={fieldClass}
                                        >
                                            <option value="" disabled>
                                                Choose a location
                                            </option>
                                            {locations.map((l) => (
                                                <option key={l.id} value={l.id}>
                                                    {l.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.location_id}
                                        />
                                    </div>
                                </>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="condition">Condition</Label>
                                <select
                                    id="condition"
                                    name="condition"
                                    defaultValue={asset?.condition ?? 'good'}
                                    className={fieldClass}
                                >
                                    {conditions.map((c) => (
                                        <option key={c} value={c}>
                                            {c.charAt(0).toUpperCase() +
                                                c.slice(1)}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.condition} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="brand">Brand</Label>
                                <Input
                                    id="brand"
                                    name="brand"
                                    defaultValue={asset?.brand ?? ''}
                                    maxLength={100}
                                />
                                <InputError message={errors.brand} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="serial_number">
                                    Serial number
                                </Label>
                                <Input
                                    id="serial_number"
                                    name="serial_number"
                                    defaultValue={asset?.serial_number ?? ''}
                                    maxLength={100}
                                />
                                <InputError message={errors.serial_number} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="purchase_date">
                                    Purchase date
                                </Label>
                                <Input
                                    id="purchase_date"
                                    name="purchase_date"
                                    type="date"
                                    defaultValue={asset?.purchase_date ?? ''}
                                />
                                <InputError message={errors.purchase_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="purchase_price">
                                    Purchase price (Rp)
                                </Label>
                                <Input
                                    id="purchase_price"
                                    name="purchase_price"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    inputMode="decimal"
                                    defaultValue={asset?.purchase_price ?? ''}
                                />
                                <InputError message={errors.purchase_price} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="useful_life_years">
                                    Useful life (years)
                                </Label>
                                <Input
                                    id="useful_life_years"
                                    name="useful_life_years"
                                    type="number"
                                    min={1}
                                    max={50}
                                    defaultValue={
                                        asset?.useful_life_years ?? ''
                                    }
                                    placeholder={
                                        lifePlaceholder
                                            ? `${lifePlaceholder} (category default)`
                                            : ''
                                    }
                                />
                                <InputError
                                    message={errors.useful_life_years}
                                />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows={3}
                                    maxLength={2000}
                                    defaultValue={asset?.notes ?? ''}
                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                />
                                <InputError message={errors.notes} />
                            </div>

                            <div className="flex gap-2 sm:col-span-2">
                                <Button disabled={processing}>
                                    {asset ? 'Save changes' : 'Create asset'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={asset ? show(asset.id) : index()}
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AssetForm.layout = {
    breadcrumbs: [{ title: 'Assets', href: index() }],
};
