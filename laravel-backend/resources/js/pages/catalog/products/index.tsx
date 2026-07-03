import { Head, Link, router } from '@inertiajs/react';
import { Archive, ImageOff, Package, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import {  DataTable } from '@/components/admin/data-table';
import type {Column, SortDir} from '@/components/admin/data-table';
import { DateRangeFilter } from '@/components/admin/date-range-filter';
import type { DatePreset } from '@/components/admin/date-range-filter';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';

type ProductRow = {
    id: number;
    title: string;
    sku: string;
    category: string | null;
    price: string;
    discount_price: string | null;
    stock_amount: number;
    in_stock: boolean;
    product_status: 'draft' | 'published' | 'disabled';
    main_image_url: string | null;
};

type Category = { id: number; title: string };

type Filters = {
    search: string;
    status: string;
    category_id: string;
    sort: string;
    dir: SortDir;
    range: DatePreset;
    from: string;
    to: string;
};

type Props = {
    products: ProductRow[];
    meta: { current_page: number; last_page: number; total: number };
    filters: Filters;
    categories: Category[];
    trashedCount: number;
};

const STATUS_STYLES: Record<ProductRow['product_status'], string> = {
    published: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    draft: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    disabled: 'bg-muted text-muted-foreground',
};

function StatusBadge({ status }: { status: ProductRow['product_status'] }) {
    return (
        <span
            className={`inline-block rounded-md px-2 py-0.5 text-xs font-medium capitalize ${STATUS_STYLES[status]}`}
        >
            {status}
        </span>
    );
}

function Thumb({ url, alt }: { url: string | null; alt: string }) {
    if (!url) {
        return (
            <div className="flex size-10 items-center justify-center rounded-md border bg-muted text-muted-foreground">
                <ImageOff className="size-4" />
            </div>
        );
    }

    return <img src={url} alt={alt} className="size-10 rounded-md border bg-white object-cover" />;
}

function Price({ row }: { row: ProductRow }) {
    if (row.discount_price) {
        return (
            <span className="whitespace-nowrap">
                <span className="font-medium">{row.discount_price}</span>{' '}
                <span className="text-xs text-muted-foreground line-through">{row.price}</span>
            </span>
        );
    }

    return <span className="font-medium whitespace-nowrap">{row.price}</span>;
}

type BulkAction = 'advance' | 'status' | 'category';

const selectClass = 'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

function BulkBar({
    count,
    categories,
    onApply,
    onClear,
}: {
    count: number;
    categories: Category[];
    onApply: (payload: Record<string, unknown>) => void;
    onClear: () => void;
}) {
    const [action, setAction] = useState<BulkAction>('status');
    const [productStatus, setProductStatus] = useState('published');
    const [categoryId, setCategoryId] = useState('');
    const [isAdvance, setIsAdvance] = useState(true);
    const [advanceType, setAdvanceType] = useState('full');
    const [partialType, setPartialType] = useState('percentage');
    const [partialAmount, setPartialAmount] = useState('');

    const apply = () => {
        if (action === 'status') {
            onApply({ action, product_status: productStatus });
        } else if (action === 'category') {
            if (!categoryId) {
return;
}

            onApply({ action, category_id: categoryId });
        } else {
            onApply({
                action,
                is_advance_payment: isAdvance,
                advance_payment_type: advanceType,
                partial_amount_type: advanceType === 'partial' ? partialType : null,
                partial_amount: advanceType === 'partial' ? partialAmount || 0 : null,
            });
        }
    };

    return (
        <div className="sticky bottom-4 z-20 mt-4 flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-lg sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2 text-sm">
                <span className="font-medium">{count} selected</span>
                <Button variant="ghost" size="sm" onClick={onClear} aria-label="Clear selection">
                    <X className="size-4" /> Clear
                </Button>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <select
                    aria-label="Bulk action"
                    value={action}
                    onChange={(e) => setAction(e.target.value as BulkAction)}
                    className={selectClass}
                >
                    <option value="status">Set status</option>
                    <option value="category">Set category</option>
                    <option value="advance">Advance payment</option>
                </select>

                {action === 'status' && (
                    <select
                        aria-label="Status value"
                        value={productStatus}
                        onChange={(e) => setProductStatus(e.target.value)}
                        className={selectClass}
                    >
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="disabled">Disabled</option>
                    </select>
                )}

                {action === 'category' && (
                    <select
                        aria-label="Category value"
                        value={categoryId}
                        onChange={(e) => setCategoryId(e.target.value)}
                        className={selectClass}
                    >
                        <option value="">Choose category…</option>
                        {categories.map((c) => (
                            <option key={c.id} value={String(c.id)}>
                                {c.title}
                            </option>
                        ))}
                    </select>
                )}

                {action === 'advance' && (
                    <>
                        <select
                            aria-label="Advance on or off"
                            value={isAdvance ? 'on' : 'off'}
                            onChange={(e) => setIsAdvance(e.target.value === 'on')}
                            className={selectClass}
                        >
                            <option value="on">Turn ON</option>
                            <option value="off">Turn OFF</option>
                        </select>
                        {isAdvance && (
                            <select
                                aria-label="Advance type"
                                value={advanceType}
                                onChange={(e) => setAdvanceType(e.target.value)}
                                className={selectClass}
                            >
                                <option value="full">Full</option>
                                <option value="partial">Partial</option>
                            </select>
                        )}
                        {isAdvance && advanceType === 'partial' && (
                            <>
                                <select
                                    aria-label="Partial type"
                                    value={partialType}
                                    onChange={(e) => setPartialType(e.target.value)}
                                    className={selectClass}
                                >
                                    <option value="percentage">Percentage</option>
                                    <option value="amount">Amount</option>
                                    <option value="shipping">Shipping</option>
                                </select>
                                {partialType !== 'shipping' && (
                                    <input
                                        type="number"
                                        min={0}
                                        aria-label="Partial value"
                                        value={partialAmount}
                                        onChange={(e) => setPartialAmount(e.target.value)}
                                        placeholder={partialType === 'percentage' ? '%' : '৳'}
                                        className={`w-24 ${selectClass}`}
                                    />
                                )}
                            </>
                        )}
                    </>
                )}

                <Button onClick={apply}>Apply</Button>
            </div>
        </div>
    );
}

export default function ProductsIndex({
    products,
    meta,
    filters,
    categories,
    trashedCount,
}: Props) {
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [selected, setSelected] = useState<Set<string | number>>(new Set());
    // "All rows matching the current filters" — resolved server-side so we never
    // ship a 1000-id array for a whole-catalog selection.
    const [allMatching, setAllMatching] = useState(false);

    const clearSelection = () => {
        setSelected(new Set());
        setAllMatching(false);
    };

    const apply = (next: Partial<Filters> & { page?: number }) => {
        // The matching set changes with the filters, so any filter/sort/page
        // change drops an in-flight "select all matching".
        clearSelection();
        const params: Record<string, string | number> = {};
        const merged = { ...filters, ...next };

        if (merged.search) {
            params.search = merged.search;
        }

        if (merged.status) {
            params.status = merged.status;
        }

        if (merged.category_id) {
            params.category_id = merged.category_id;
        }

        if (merged.sort !== 'position_order' || merged.dir !== 'asc') {
            params.sort = merged.sort;
            params.dir = merged.dir;
        }

        if (merged.range && merged.range !== 'all') {
            params.range = merged.range;

            if (merged.range === 'custom') {
                if (merged.from) {
                    params.from = merged.from;
                }

                if (merged.to) {
                    params.to = merged.to;
                }
            }
        }

        if (next.page && next.page > 1) {
            params.page = next.page;
        }

        router.get('/admin/catalog/products', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const onSearch = (value: string) => {
        if (searchTimer.current) {
            clearTimeout(searchTimer.current);
        }

        searchTimer.current = setTimeout(() => apply({ search: value, page: 1 }), 400);
    };

    const onSort = (sortKey: string) => {
        const dir: SortDir =
            filters.sort === sortKey && filters.dir === 'desc' ? 'asc' : 'desc';
        apply({ sort: sortKey, dir, page: 1 });
    };

    const remove = (row: ProductRow) =>
        router.delete(`/admin/catalog/products/${row.id}`, {
            preserveScroll: true,
            onBefore: () =>
                confirm(`Move “${row.title}” to the recycle bin? You can restore it later.`),
        });

    const toggleRow = (key: string | number) => {
        setAllMatching(false);
        setSelected((prev) => {
            const next = new Set(prev);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    };

    const toggleAll = (keys: Array<string | number>) => {
        setAllMatching(false);
        setSelected((prev) => {
            const allOn = keys.length > 0 && keys.every((k) => prev.has(k));
            const next = new Set(prev);
            keys.forEach((k) => (allOn ? next.delete(k) : next.add(k)));

            return next;
        });
    };

    const pageSelectedCount = products.filter((p) => selected.has(p.id)).length;
    const allPageSelected = products.length > 0 && pageSelectedCount === products.length;
    const effectiveCount = allMatching ? meta.total : selected.size;

    const applyBulk = (payload: Record<string, unknown>) => {
        const selection = allMatching
            ? { all_matching: true, filters }
            : { all_matching: false, ids: Array.from(selected) };

        router.post(
            '/admin/catalog/products/bulk',
            { ...selection, ...payload },
            {
                preserveScroll: true,
                onSuccess: clearSelection,
            },
        );
    };

    const RowActions = ({ row }: { row: ProductRow }) => (
        <div className="flex justify-end gap-1">
            <Button variant="ghost" size="icon" asChild aria-label="Edit">
                <Link href={`/admin/catalog/products/${row.id}/edit`}>
                    <Pencil className="size-4" />
                </Link>
            </Button>
            <Button variant="ghost" size="icon" aria-label="Delete" onClick={() => remove(row)}>
                <Trash2 className="size-4 text-destructive" />
            </Button>
        </div>
    );

    const columns: Column<ProductRow>[] = [
        {
            key: 'title',
            header: 'Product',
            sortKey: 'title',
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <Thumb url={row.main_image_url} alt={row.title} />
                    <div className="min-w-0">
                        <div className="truncate font-medium">{row.title}</div>
                        <div className="text-xs text-muted-foreground">{row.sku}</div>
                    </div>
                </div>
            ),
        },
        {
            key: 'category',
            header: 'Category',
            cell: (row) => (
                <span className="text-muted-foreground">{row.category ?? '—'}</span>
            ),
        },
        { key: 'price', header: 'Price', sortKey: 'price', cell: (row) => <Price row={row} /> },
        {
            key: 'stock_amount',
            header: 'Stock',
            sortKey: 'stock_amount',
            cell: (row) => (
                <span className={row.in_stock ? '' : 'text-destructive'}>
                    {row.in_stock ? row.stock_amount : 'Out'}
                </span>
            ),
        },
        {
            key: 'product_status',
            header: 'Status',
            cell: (row) => <StatusBadge status={row.product_status} />,
        },
        { key: 'actions', header: 'Actions', align: 'right', cell: (row) => <RowActions row={row} /> },
    ];

    const mobileCard = (row: ProductRow) => (
        <div className="flex items-center gap-3">
            <Thumb url={row.main_image_url} alt={row.title} />
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{row.title}</div>
                <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <StatusBadge status={row.product_status} />
                    <Price row={row} />
                    <span>·</span>
                    <span className={row.in_stock ? '' : 'text-destructive'}>
                        {row.in_stock ? `${row.stock_amount} in stock` : 'Out of stock'}
                    </span>
                </div>
            </div>
            <RowActions row={row} />
        </div>
    );

    const inputClass =
        'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

    return (
        <>
            <Head title="Products" />
            <div className="mx-auto w-full max-w-6xl p-4">
                <PageHeader
                    title="Products"
                    description={`${meta.total} product${meta.total === 1 ? '' : 's'} in your catalog.`}
                    actions={
                        <>
                            {trashedCount > 0 && (
                                <Button variant="outline" asChild>
                                    <Link href="/admin/catalog/products/trashed">
                                        <Archive className="size-4" /> Recycle bin ({trashedCount})
                                    </Link>
                                </Button>
                            )}
                            <Button asChild>
                                <Link href="/admin/catalog/products/create">
                                    <Plus className="size-4" /> New product
                                </Link>
                            </Button>
                        </>
                    }
                />

                <FilterBar
                    search={filters.search}
                    onSearch={onSearch}
                    placeholder="Search by title or SKU…"
                >
                    <select
                        aria-label="Filter by category"
                        defaultValue={filters.category_id}
                        onChange={(e) => apply({ category_id: e.target.value, page: 1 })}
                        className={inputClass}
                    >
                        <option value="">All categories</option>
                        {categories.map((c) => (
                            <option key={c.id} value={String(c.id)}>
                                {c.title}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Filter by status"
                        defaultValue={filters.status}
                        onChange={(e) => apply({ status: e.target.value, page: 1 })}
                        className={inputClass}
                    >
                        <option value="">All statuses</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="disabled">Disabled</option>
                    </select>
                    <DateRangeFilter
                        value={{ range: filters.range, from: filters.from, to: filters.to }}
                        onChange={(v) => apply({ ...v, page: 1 })}
                    />
                </FilterBar>

                {products.length === 0 ? (
                    <EmptyState
                        icon={Package}
                        title="No products found."
                        description="Try a different search or add your first product."
                        action={
                            <Button asChild>
                                <Link href="/admin/catalog/products/create">
                                    <Plus className="size-4" /> New product
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        {(allPageSelected || allMatching) && meta.total > products.length && (
                            <div className="mb-3 flex flex-wrap items-center justify-center gap-2 rounded-lg border border-accent/40 bg-accent/5 px-4 py-2 text-sm">
                                {allMatching ? (
                                    <>
                                        <span>
                                            All <strong>{meta.total}</strong> products matching the
                                            current filters are selected.
                                        </span>
                                        <button
                                            type="button"
                                            onClick={clearSelection}
                                            className="font-medium text-accent underline-offset-2 hover:underline"
                                        >
                                            Clear selection
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <span>All {products.length} on this page selected.</span>
                                        <button
                                            type="button"
                                            onClick={() => setAllMatching(true)}
                                            className="font-medium text-accent underline-offset-2 hover:underline"
                                        >
                                            Select all {meta.total} matching filters
                                        </button>
                                    </>
                                )}
                            </div>
                        )}

                        <DataTable
                            columns={columns}
                            rows={products}
                            rowKey={(row) => row.id}
                            renderMobileCard={mobileCard}
                            sort={filters.sort}
                            dir={filters.dir}
                            onSort={onSort}
                            selection={{ selected, onToggle: toggleRow, onToggleAll: toggleAll }}
                        />

                        {effectiveCount > 0 && (
                            <BulkBar
                                count={effectiveCount}
                                categories={categories}
                                onApply={applyBulk}
                                onClear={clearSelection}
                            />
                        )}

                        {meta.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Page {meta.current_page} of {meta.last_page}
                                </span>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page <= 1}
                                        onClick={() => apply({ page: meta.current_page - 1 })}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page >= meta.last_page}
                                        onClick={() => apply({ page: meta.current_page + 1 })}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: '/admin/catalog/products' }],
};
