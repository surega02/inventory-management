import LocationController from '@/actions/App/Http/Controllers/LocationController';
import MasterDataPage from '@/components/master-data-page';
import { index } from '@/routes/locations';
import type { Paginated } from '@/types';

type Location = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    assets_count: number;
};

export default function Locations({
    locations,
}: {
    locations: Paginated<Location>;
}) {
    return (
        <MasterDataPage<Location>
            title="Locations"
            description="Where assets are kept."
            singular="Location"
            rows={locations}
            columns={[
                {
                    label: 'Code',
                    cell: (l) => <span className="font-mono">{l.code}</span>,
                },
                { label: 'Name', cell: (l) => l.name },
                {
                    label: 'Description',
                    cell: (l) => l.description ?? '—',
                    className: 'hidden md:table-cell text-muted-foreground',
                },
                {
                    label: 'Assets',
                    cell: (l) => l.assets_count,
                    className: 'text-right',
                },
            ]}
            fields={[
                {
                    name: 'code',
                    label: 'Code',
                    placeholder: 'HO-L2-IT',
                    required: true,
                    hint: '2–20 uppercase letters, digits or dashes.',
                },
                {
                    name: 'name',
                    label: 'Name',
                    placeholder: 'Head Office L2 – IT Room',
                    required: true,
                },
                { name: 'description', label: 'Description', type: 'textarea' },
            ]}
            storeForm={LocationController.store.form()}
            updateForm={(l) => LocationController.update.form(l.id)}
            destroyUrl={(l) => LocationController.destroy.url(l.id)}
        />
    );
}

Locations.layout = {
    breadcrumbs: [{ title: 'Locations', href: index() }],
};
