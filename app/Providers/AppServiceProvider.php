<?php

namespace App\Providers;

use App\Models\Buyer;
use App\Models\Invoice;
use App\Models\QualityInspection;
use App\Models\Receipt;
use App\Models\StockBag;
use App\Models\StockDispatch;
use App\Models\StockInBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /* Polymorphic Relation Morph Map */
        Relation::morphMap([
            'user'               => User::class,
            'supplier'           => Supplier::class,
            'warehouse'          => Warehouse::class,
            'buyer'              => Buyer::class,
            'stock_in_batch'     => StockInBatch::class,
            'stock_bag'          => StockBag::class,
            'receipt'            => Receipt::class,
            'quality_inspection' => QualityInspection::class,
            'stock_dispatch'     => StockDispatch::class,
            'invoice'            => Invoice::class,
        ]);

        /* Rate Limiters */
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
