<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Repositories\Eloquent\CustomerRepository;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin customer list (gated `orders.view`). Read-only directory with order
 * count + total spent aggregates and the shared search / date / sort controls.
 */
class CustomerController extends Controller
{
    public function __construct(private readonly CustomerRepository $customers) {}

    public function index(Request $request): Response
    {
        $listQuery = $this->customers->queryFrom($request);
        $paginator = $this->customers->adminList($listQuery);

        return Inertia::render('customers/index', [
            'customers' => collect($paginator->items())->map(fn (Customer $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'mobile' => $c->mobile,
                'email' => $c->email,
                'orders_count' => (int) $c->orders_count,
                'total_spent' => Money::fromMinor((int) ($c->total_spent_minor ?? 0))->format(),
                'joined' => $c->created_at?->toDateString(),
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $listQuery->search ?? '',
                'sort' => $listQuery->sort,
                'dir' => $listQuery->dir,
                'range' => $listQuery->dateRange->preset,
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
            ],
        ]);
    }
}
