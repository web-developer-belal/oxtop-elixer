<?php
namespace Oxtop\Elixer;
use Illuminate\Support\ServiceProvider;

class Tor extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Rd::class, function () {
            return new Rd();
        });
    }

    public function boot()
    {
        app(Rd::class)->a5();
    }
}
