<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetDashboardRequest;
use App\Models\StockBag;
use App\Models\StockInBatch;
use App\Models\StockDispatch;
use App\Models\QualityInspection;
use App\Models\Invoice;
use App\Models\VehicleLog;
use App\Models\Vehicle;
use App\Models\Supplier;
use App\Models\Buyer;
use App\Models\Warehouse;
use App\Models\Branch;
use App\Models\ItemType;
use App\Models\ActivityLog;
use App\Traits\ActivityLogTrait;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Carbon\Carbon;

class DashboardController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Dashboard Index', ['only' => ['summary', 'analytics', 'operational']]),
        ];
    }

    /**
     * Comprehensive Executive Dashboard Summary & KPI Metric Cards.
     */
    public function summary(GetDashboardRequest $request)
    {
        try {
            $validated = $request->validated();
            $authUser = auth('api')->user();
            $accessibleWarehouseIds = $authUser ? $authUser->getAccessibleWarehouseIds() : null;

            // Date Range Resolution
            $dateRange = $this->resolveDateRange($validated);

            // 1. IN-STOCK INVENTORY METRICS (Pure Eloquent ORM)
            $stockBagQuery = StockBag::where('status', 'in_stock');
            $this->applyWarehouseScope($stockBagQuery, $accessibleWarehouseIds, 'warehouse_id');
            $this->applyFilters($stockBagQuery, $validated);

            $inStockBags = (int) (clone $stockBagQuery)->count();
            $inStockWeight = (float) (clone $stockBagQuery)->sum('bag_weight');
            $totalCostValue = (float) (clone $stockBagQuery)->sum('total_price');
            $expectedSalesValue = (float) (clone $stockBagQuery)->sum('total_sales_amount');
            $expectedProfitMargin = $expectedSalesValue - $totalCostValue;

            // 2. STOCK RECEIVING (STOCK IN BATCHES) METRICS (Pure Eloquent ORM)
            $stockInQuery = StockInBatch::query();
            $this->applyWarehouseScope($stockInQuery, $accessibleWarehouseIds, 'warehouse_id');
            $this->applyFilters($stockInQuery, $validated);
            if ($dateRange['from']) {
                $stockInQuery->whereBetween('received_date', [$dateRange['from'], $dateRange['to']]);
            }

            $totalBatches = (int) (clone $stockInQuery)->count();
            $receivedBags = (int) (clone $stockInQuery)->sum('total_bags');
            $receivedWeightKg = (float) (clone $stockInQuery)->sum('net_weight'); // Correct database schema column

            // 3. STOCK DISPATCH METRICS (Pure Eloquent ORM)
            $dispatchQuery = StockDispatch::query();
            $this->applyWarehouseScope($dispatchQuery, $accessibleWarehouseIds, 'warehouse_id');
            $this->applyFilters($dispatchQuery, $validated);
            if ($dateRange['from']) {
                $dispatchQuery->whereBetween('dispatch_date', [$dateRange['from'], $dateRange['to']]);
            }

            $totalDispatches = (int) (clone $dispatchQuery)->count();
            $completedDispatches = (int) (clone $dispatchQuery)->whereIn('status', ['confirmed', 'gate_exited'])->count();
            $dispatchedBags = (int) (clone $dispatchQuery)->sum('total_bags');
            $dispatchedWeightKg = (float) (clone $dispatchQuery)->sum('total_weight');

            // 4. QUALITY INSPECTION METRICS (Pure Eloquent ORM)
            $qualityQuery = QualityInspection::query();
            if ($dateRange['from']) {
                $qualityQuery->whereBetween('inspected_at', [$dateRange['from'], $dateRange['to']]);
            }

            $totalInspections = (int) (clone $qualityQuery)->count();
            $passedInspections = (int) (clone $qualityQuery)->whereIn('inspection_result', ['passed', 'pass', 'accepted', 'approved'])->count();
            $failedInspections = (int) (clone $qualityQuery)->whereIn('inspection_result', ['failed', 'fail', 'rejected'])->count();
            $gradeACount = (int) (clone $qualityQuery)->where('grade', 'A')->count();
            $gradeBCount = (int) (clone $qualityQuery)->where('grade', 'B')->count();
            $gradeCCount = (int) (clone $qualityQuery)->where('grade', 'C')->count();
            $avgMoisture = (float) (clone $qualityQuery)->avg('moisture_percentage');
            $avgDefect = (float) (clone $qualityQuery)->avg('broken_percentage');

            $passRate = $totalInspections > 0 ? round(($passedInspections / $totalInspections) * 100, 2) : 0.0;

            // 5. INVOICE & FINANCIAL METRICS (Pure Eloquent ORM)
            $invoiceQuery = Invoice::query();
            if ($dateRange['from']) {
                $invoiceQuery->whereBetween('invoice_date', [$dateRange['from'], $dateRange['to']]);
            }

            $totalInvoices = (int) (clone $invoiceQuery)->count();
            $totalInvoicedAmount = (float) (clone $invoiceQuery)->sum('total_amount');
            $paidInvoicesCount = (int) (clone $invoiceQuery)->where('payment_status', 'paid')->count();
            $totalPaidAmount = (float) (clone $invoiceQuery)->where('payment_status', 'paid')->sum('total_amount');
            $unpaidInvoicesCount = (int) (clone $invoiceQuery)->where('payment_status', 'unpaid')->count();
            $pendingReceivables = (float) (clone $invoiceQuery)->whereIn('payment_status', ['unpaid', 'pending', 'overdue'])->sum('total_amount');
            $overdueInvoicesCount = (int) (clone $invoiceQuery)->where(function ($q) {
                $q->where('payment_status', 'overdue')
                  ->orWhere(function ($q2) {
                      $q2->where('payment_status', 'unpaid')
                         ->where('due_date', '<', Carbon::now()->toDateString());
                  });
            })->count();

            // 6. FLEET & YARD METRICS (Pure Eloquent ORM)
            $vehiclesInside = VehicleLog::whereNull('exit_time')->count();
            $totalActiveVehicles = Vehicle::where('is_active', 1)->count();
            $availableVehicles = Vehicle::where('is_active', 1)->where('availability_status', 'available')->count();

            // 7. SYSTEM ENTITIES TOTALS (Pure Eloquent ORM)
            $systemCounts = [
                'active_suppliers'  => Supplier::where('is_active', 1)->count(),
                'active_buyers'     => Buyer::where('is_active', 1)->count(),
                'active_warehouses' => Warehouse::where('is_active', 1)->count(),
                'active_branches'   => Branch::where('is_active', 1)->count(),
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Dashboard summary metrics retrieved successfully',
                'data' => [
                    'filter_period' => [
                        'from_date' => $dateRange['from'] ? $dateRange['from']->toDateString() : null,
                        'to_date'   => $dateRange['to'] ? $dateRange['to']->toDateString() : null,
                        'period'    => $validated['period'] ?? 'custom',
                    ],
                    'kpi_cards' => [
                        'inventory' => [
                            'in_stock_bags'          => $inStockBags,
                            'in_stock_weight_kg'     => $inStockWeight,
                            'total_cost_value'       => $totalCostValue,
                            'expected_sales_value'   => $expectedSalesValue,
                            'expected_profit_margin' => $expectedProfitMargin,
                        ],
                        'stock_receiving' => [
                            'total_batches'          => $totalBatches,
                            'received_bags'          => $receivedBags,
                            'received_weight_kg'     => $receivedWeightKg,
                        ],
                        'stock_dispatch' => [
                            'total_dispatches'       => $totalDispatches,
                            'completed_dispatches'   => $completedDispatches,
                            'dispatched_bags'        => $dispatchedBags,
                            'dispatched_weight_kg'   => $dispatchedWeightKg,
                        ],
                        'quality' => [
                            'total_inspections'      => $totalInspections,
                            'passed_inspections'     => $passedInspections,
                            'failed_inspections'     => $failedInspections,
                            'pass_rate_percentage'   => $passRate,
                            'grade_a_count'          => $gradeACount,
                            'grade_b_count'          => $gradeBCount,
                            'grade_c_count'          => $gradeCCount,
                            'avg_moisture_content'   => round($avgMoisture, 2),
                            'avg_defect_percentage'  => round($avgDefect, 2),
                        ],
                        'financials' => [
                            'total_invoices'         => $totalInvoices,
                            'total_invoiced_amount'  => $totalInvoicedAmount,
                            'total_paid_amount'      => $totalPaidAmount,
                            'pending_receivables'    => $pendingReceivables,
                            'paid_invoices_count'    => $paidInvoicesCount,
                            'unpaid_invoices_count'  => $unpaidInvoicesCount,
                            'overdue_invoices_count' => $overdueInvoicesCount,
                        ],
                        'fleet' => [
                            'vehicles_inside_premises' => $vehiclesInside,
                            'total_active_vehicles'   => $totalActiveVehicles,
                            'available_vehicles'      => $availableVehicles,
                        ],
                        'system_entities' => $systemCounts,
                    ]
                ]
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate dashboard summary',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Detailed Analytical Charts & Time-Series Data (Pure Eloquent ORM).
     */
    public function analytics(GetDashboardRequest $request)
    {
        try {
            $validated = $request->validated();
            $authUser = auth('api')->user();
            $accessibleWarehouseIds = $authUser ? $authUser->getAccessibleWarehouseIds() : null;

            $dateRange = $this->resolveDateRange($validated);

            // 1. INVENTORY BREAKDOWN BY WAREHOUSE (Pure Eloquent ORM)
            $warehouses = Warehouse::where('is_active', 1)->get();
            if (is_array($accessibleWarehouseIds)) {
                $warehouses = $warehouses->whereIn('id', $accessibleWarehouseIds);
            }
            $stockByWarehouse = $warehouses->map(function ($warehouse) {
                $bagQuery = StockBag::where('warehouse_id', $warehouse->id)->where('status', 'in_stock');
                return [
                    'warehouse_id'    => $warehouse->id,
                    'warehouse_name'  => $warehouse->name,
                    'warehouse_code'  => $warehouse->code,
                    'total_bags'      => $bagQuery->count(),
                    'total_weight_kg' => (float) $bagQuery->sum('bag_weight'),
                ];
            })->filter(fn($item) => $item['total_bags'] > 0)->values();

            // 2. INVENTORY BREAKDOWN BY ITEM TYPE (Pure Eloquent ORM)
            $itemTypes = ItemType::where('is_active', 1)->get();
            $stockByItemType = $itemTypes->map(function ($itemType) use ($accessibleWarehouseIds) {
                $bagQuery = StockBag::where('item_type_id', $itemType->id)->where('status', 'in_stock');
                $this->applyWarehouseScope($bagQuery, $accessibleWarehouseIds, 'warehouse_id');
                return [
                    'item_type_id'   => $itemType->id,
                    'item_type_name' => $itemType->name,
                    'total_bags'     => $bagQuery->count(),
                    'total_weight_kg' => (float) $bagQuery->sum('bag_weight'),
                ];
            })->filter(fn($item) => $item['total_bags'] > 0)->values();

            // 3. TOP 5 SUPPLIERS BY RECEIVED WEIGHT (Pure Eloquent ORM)
            $suppliers = Supplier::where('is_active', 1)->get();
            $topSuppliers = $suppliers->map(function ($supplier) use ($dateRange, $accessibleWarehouseIds) {
                $batchQuery = StockInBatch::where('supplier_id', $supplier->id);
                $this->applyWarehouseScope($batchQuery, $accessibleWarehouseIds, 'warehouse_id');
                if ($dateRange['from']) {
                    $batchQuery->whereBetween('received_date', [$dateRange['from'], $dateRange['to']]);
                }
                return [
                    'supplier_id'              => $supplier->id,
                    'supplier_name'            => $supplier->name,
                    'supplier_phone'           => $supplier->phone_primary,
                    'total_batches'            => $batchQuery->count(),
                    'total_received_weight_kg' => (float) $batchQuery->sum('net_weight'),
                ];
            })->sortByDesc('total_received_weight_kg')->take(5)->values();

            // 4. TOP 5 BUYERS BY PURCHASE REVENUE (Pure Eloquent ORM)
            $buyers = Buyer::where('is_active', 1)->get();
            $topBuyers = $buyers->map(function ($buyer) use ($dateRange) {
                $invoiceQuery = Invoice::where('buyer_id', $buyer->id);
                if ($dateRange['from']) {
                    $invoiceQuery->whereBetween('invoice_date', [$dateRange['from'], $dateRange['to']]);
                }
                return [
                    'buyer_id'       => $buyer->id,
                    'buyer_name'     => $buyer->brand_name,
                    'total_invoices' => $invoiceQuery->count(),
                    'total_spent'    => (float) $invoiceQuery->sum('total_amount'),
                ];
            })->sortByDesc('total_spent')->take(5)->values();

            // 5. MONTHLY STOCK IN VS DISPATCH TREND (LAST 6 MONTHS - Pure Eloquent ORM)
            $monthlyFlowTrend = [];
            for ($i = 5; $i >= 0; $i--) {
                $startOfMonth = Carbon::now()->subMonths($i)->startOfMonth();
                $endOfMonth = Carbon::now()->subMonths($i)->endOfMonth();

                $inQuery = StockInBatch::whereBetween('received_date', [$startOfMonth, $endOfMonth]);
                $this->applyWarehouseScope($inQuery, $accessibleWarehouseIds, 'warehouse_id');
                $incomingKg = (float) $inQuery->sum('net_weight');

                $outQuery = StockDispatch::whereBetween('dispatch_date', [$startOfMonth, $endOfMonth]);
                $this->applyWarehouseScope($outQuery, $accessibleWarehouseIds, 'warehouse_id');
                $outgoingKg = (float) $outQuery->sum('total_weight');

                $monthlyFlowTrend[] = [
                    'month'       => $startOfMonth->format('Y-m'),
                    'month_name'  => $startOfMonth->format('M Y'),
                    'incoming_kg' => $incomingKg,
                    'outgoing_kg' => $outgoingKg,
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Dashboard analytical chart data retrieved successfully',
                'data' => [
                    'stock_by_warehouse'  => $stockByWarehouse,
                    'stock_by_item_type'  => $stockByItemType,
                    'top_suppliers'       => $topSuppliers,
                    'top_buyers'          => $topBuyers,
                    'monthly_flow_trend'  => $monthlyFlowTrend,
                ]
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate dashboard analytics',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Quick Operational Feeds & Action Items for Dashboard Widgets.
     */
    public function operational(GetDashboardRequest $request)
    {
        try {
            $authUser = auth('api')->user();
            $accessibleWarehouseIds = $authUser ? $authUser->getAccessibleWarehouseIds() : null;

            // 1. Pending Gate Exits / Unconfirmed Dispatches
            $pendingDispatchesQuery = StockDispatch::with(['warehouse:id,name', 'buyer:id,brand_name'])
                ->whereIn('status', ['draft', 'pending', 'confirmed'])
                ->orderBy('created_at', 'desc')
                ->limit(5);
            $this->applyWarehouseScope($pendingDispatchesQuery, $accessibleWarehouseIds, 'warehouse_id');
            $pendingDispatches = $pendingDispatchesQuery->get();

            // 2. Recent Quality Inspections
            $recentInspections = QualityInspection::with(['stockBag:id,bag_number', 'inspectedBy:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            // 3. Vehicles currently inside premises
            $vehiclesInside = VehicleLog::with(['vehicle:id,vehicle_number,vehicle_type', 'warehouse:id,name'])
                ->whereNull('exit_time')
                ->orderBy('entry_time', 'desc')
                ->limit(5)
                ->get();

            // 4. Audit Log Activity Feed
            $activityLogs = ActivityLog::with('user:id,name,username')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Operational feeds retrieved successfully',
                'data' => [
                    'pending_dispatches' => $pendingDispatches,
                    'recent_inspections' => $recentInspections,
                    'vehicles_in_yard'   => $vehiclesInside,
                    'recent_activities'  => $activityLogs,
                ]
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve operational feeds',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Helper: Resolve Date Range from input parameters or shortcuts.
     */
    private function resolveDateRange(array $validated): array
    {
        $from = null;
        $to = null;

        if (!empty($validated['from_date'])) {
            $from = Carbon::parse($validated['from_date'])->startOfDay();
            $to = !empty($validated['to_date']) ? Carbon::parse($validated['to_date'])->endOfDay() : Carbon::now()->endOfDay();
        } elseif (!empty($validated['period'])) {
            switch ($validated['period']) {
                case 'today':
                    $from = Carbon::now()->startOfDay();
                    $to = Carbon::now()->endOfDay();
                    break;
                case 'this_week':
                    $from = Carbon::now()->startOfWeek();
                    $to = Carbon::now()->endOfWeek();
                    break;
                case 'this_month':
                    $from = Carbon::now()->startOfMonth();
                    $to = Carbon::now()->endOfMonth();
                    break;
                case 'this_year':
                    $from = Carbon::now()->startOfYear();
                    $to = Carbon::now()->endOfYear();
                    break;
                case 'all_time':
                default:
                    $from = null;
                    $to = null;
                    break;
            }
        } else {
            // Default to Current Month if no date specified
            $from = Carbon::now()->startOfMonth();
            $to = Carbon::now()->endOfMonth();
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Helper: Apply Warehouse Tenancy Scoping
     */
    private function applyWarehouseScope($query, ?array $accessibleWarehouseIds, string $column = 'warehouse_id')
    {
        if (is_array($accessibleWarehouseIds)) {
            $query->whereIn($column, $accessibleWarehouseIds);
        }
    }

    /**
     * Helper: Apply Common Filters (branch, warehouse, item_type, item_variety)
     */
    private function applyFilters($query, array $validated)
    {
        if (!empty($validated['branch_id'])) {
            $query->where('branch_id', $validated['branch_id']);
        }
        if (!empty($validated['warehouse_id'])) {
            $query->where('warehouse_id', $validated['warehouse_id']);
        }
        if (!empty($validated['item_type_id'])) {
            $query->where('item_type_id', $validated['item_type_id']);
        }
        if (!empty($validated['item_variety_id'])) {
            $query->where('item_variety_id', $validated['item_variety_id']);
        }
    }
}
