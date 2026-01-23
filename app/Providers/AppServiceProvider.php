<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Rifa;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\RifaRepository;
use App\Repositories\RifaRepositoryInterface;
use App\Services\MercadoPago as MercadoPagoService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registra os repositórios
        $this->app->bind(OrderRepository::class, function ($app) {
            return new OrderRepository(new Order());
        });

        $this->app->bind(PaymentRepository::class, function ($app) {
            return new PaymentRepository(new Payment());
        });

        $this->app->bind(RifaRepositoryInterface::class, function ($app) {
            return new RifaRepository(new Rifa());
        });

        // Registra os serviços
        $this->app->bind(OrderService::class, function ($app) {
            return new OrderService(
                $app->make(OrderRepository::class)
            );
        });

        $this->app->bind(PaymentService::class, function ($app) {
            return new PaymentService(
                $app->make(PaymentRepository::class),
                $app->make(OrderRepository::class),
                $app->make(MercadoPagoService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (env('APP_HTTPS', false)) {
            URL::forceScheme('https');
        }
    }
}
