import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import MasterDataPage from '@/components/master-data-page';
import { index } from '@/routes/categories';
import type { Paginated } from '@/types';

type Category = {
    id: number;
    code: string;
    name: string;
    default_useful_life_years: number | null;
    assets_count: number;
    code_locked: boolean;
    deleted_at: string | null;
    purge_blocker: string | null;
};

type Props = {
    categories: Paginated<Category>;
    view: 'active' | 'deleted';
    deleted_count: number;
};

export default function Categories({ categories, view, deleted_count }: Props) {
    return (
        <MasterDataPage<Category>
            title="Categories"
            description="Asset types. The code becomes the asset-code prefix, e.g. LAP-0001."
            singular="Category"
            rows={categories}
            columns={[
                {
                    label: 'Code',
                    cell: (c) => <span className="font-mono">{c.code}</span>,
                },
                { label: 'Name', cell: (c) => c.name },
                {
                    label: 'Useful life',
                    cell: (c) =>
                        c.default_useful_life_years
                            ? `${c.default_useful_life_years} yr`
                            : '—',
                    className: 'hidden sm:table-cell',
                },
                {
                    label: 'Assets',
                    cell: (c) => c.assets_count,
                    className: 'text-right',
                },
            ]}
            fields={[
                {
                    name: 'code',
                    label: 'Code',
                    placeholder: 'LAP',
                    required: true,
                    hint: '2–10 uppercase letters or digits.',
                    lockedFor: (c) =>
                        c.code_locked &&
                        'Locked: asset codes already use this prefix.',
                },
                {
                    name: 'name',
                    label: 'Name',
                    placeholder: 'Laptop',
                    required: true,
                },
                {
                    name: 'default_useful_life_years',
                    label: 'Default useful life (years)',
                    type: 'number',
                    placeholder: '4',
                },
            ]}
            storeForm={CategoryController.store.form()}
            updateForm={(c) => CategoryController.update.form(c.id)}
            destroyUrl={(c) => CategoryController.destroy.url(c.id)}
            trash={{
                view,
                deletedCount: deleted_count,
                activeUrl: index.url(),
                deletedUrl: index.url({ query: { view: 'deleted' } }),
                restoreUrl: (c) => CategoryController.restore.url(c.id),
                forceDeleteUrl: (c) => CategoryController.forceDelete.url(c.id),
            }}
        />
    );
}

Categories.layout = {
    breadcrumbs: [{ title: 'Categories', href: index() }],
};
