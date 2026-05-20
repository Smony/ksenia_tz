<?php

namespace App\Providers;

use App\Services\Telephony\HttpTelephonyGateway;
use App\Services\Telephony\TelephonyGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TelephonyGateway::class, function () {
            return new HttpTelephonyGateway(
                endpoint: config('services.telephony.endpoint', ''),
            );
        });
    }
}
