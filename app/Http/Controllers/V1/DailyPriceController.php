<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDailyPriceRequest;
use App\Http\Requests\UpdateDailyPriceRequest;
use App\Models\DailyPrice;
use App\Models\ItemVariety;
use App\Traits\ActivityLogTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DailyPriceController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:DailyPrice Index', ['only' => ['index', 'show', 'getTodayPrice']]),
            new Middleware('permission:DailyPrice List', ['only' => ['getActiveList']]),
            new Middleware('permission:DailyPrice Create', ['only' => ['store']]),
            new Middleware('permission:DailyPrice Update', ['only' => ['update']]),
            new Middleware('permission:DailyPrice Delete', ['only' => ['destroy']]),
        ];
    }

    /**
     * Display a listing of daily prices.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = DailyPrice::with([
                'itemVariety.itemType',
                'creator:id,name,username',
                'updater:id,name,username',
            ]);

            // Search by item variety name or code
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Filter by item variety
            if ($request->has('item_variety_id') && $request->item_variety_id != '') {
                $query->byItemVariety((int) $request->item_variety_id);
            }

            // Filter by item type
            if ($request->has('item_type_id') && $request->item_type_id != '') {
                $query->whereHas('itemVariety', function ($q) use ($request) {
                    $q->where('item_type_id', $request->item_type_id);
                });
            }

            // Filter by specific date
            if ($request->has('date') && $request->date != '') {
                $query->byDate($request->date);
            }

            // Filter by date range
            if ($request->has('from_date') || $request->has('to_date')) {
                $query->dateRange($request->get('from_date'), $request->get('to_date'));
            }

            $dailyPrices = $query->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            $this->logActivity('INDEX', 'DailyPrice', 'Retrieved daily price listing');

            return response()->json([
                'status' => 'success',
                'message' => 'Daily prices retrieved successfully',
                'data' => $dailyPrices,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve daily prices',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created daily price in storage.
     */
    public function store(CreateDailyPriceRequest $request)
    {
        try {
            $data = $request->validated();
            if (empty($data['date'])) {
                $data['date'] = Carbon::today()->toDateString();
            }
            $data['created_by'] = auth('api')->id() ?? auth()->id();

            $dailyPrice = DailyPrice::create($data);
            $dailyPrice->load([
                'itemVariety.itemType',
                'creator:id,name,username',
            ]);

            $this->logActivity('CREATE', 'DailyPrice', "Created daily price for {$dailyPrice->itemVariety?->name} on {$dailyPrice->date}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Daily price created successfully',
                'data' => $dailyPrice,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create daily price',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified daily price.
     */
    public function show(string $id)
    {
        try {
            $dailyPrice = DailyPrice::with([
                'itemVariety.itemType',
                'creator:id,name,username',
                'updater:id,name,username',
            ])->find($id);

            if (!$dailyPrice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Daily price not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'DailyPrice', "Viewed daily price ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Daily price retrieved successfully',
                'data' => $dailyPrice,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve daily price',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified daily price in storage.
     */
    public function update(UpdateDailyPriceRequest $request, string $id)
    {
        try {
            $dailyPrice = DailyPrice::find($id);

            if (!$dailyPrice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Daily price not found',
                ], 404);
            }

            $data = $request->validated();
            $data['updated_by'] = auth('api')->id() ?? auth()->id();

            $dailyPrice->update($data);
            $dailyPrice->load([
                'itemVariety.itemType',
                'creator:id,name,username',
                'updater:id,name,username',
            ]);

            $this->logActivity('UPDATE', 'DailyPrice', "Updated daily price ID: {$id}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Daily price updated successfully',
                'data' => $dailyPrice,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update daily price',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified daily price from storage.
     */
    public function destroy(string $id)
    {
        try {
            $dailyPrice = DailyPrice::find($id);

            if (!$dailyPrice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Daily price not found',
                ], 404);
            }

            $varietyName = $dailyPrice->itemVariety?->name ?? 'Unknown';
            $date = $dailyPrice->date;
            $dailyPrice->delete();

            $this->logActivity('DELETE', 'DailyPrice', "Deleted daily price for {$varietyName} on {$date}");

            return response()->json([
                'status' => 'success',
                'message' => 'Daily price deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete daily price',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Retrieve today's price (or specific date) for item varieties.
     */
    public function getTodayPrice(Request $request)
    {
        try {
            $targetDate = $request->get('date', Carbon::today()->toDateString());
            $query = DailyPrice::with(['itemVariety.itemType'])
                ->whereDate('date', $targetDate);

            if ($request->has('item_variety_id') && $request->item_variety_id != '') {
                $price = $query->where('item_variety_id', $request->item_variety_id)->first();

                return response()->json([
                    'status' => 'success',
                    'message' => $price ? 'Daily price retrieved successfully' : 'No price configured for the requested variety on this date',
                    'data' => $price,
                ], 200);
            }

            $prices = $query->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Daily prices retrieved successfully',
                'data' => $prices,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve daily price',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
