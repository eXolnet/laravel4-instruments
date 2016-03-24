<?php namespace Exolnet\Instruments;

use Illuminate\Support\ServiceProvider;

class InstrumentsServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('exolnet/instruments', 'exolnet-instruments', __DIR__.'/..');

		$this->app['instruments']->boot();
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->singleton('instruments.factory', function($app) {
			return new InstrumentsManager($app);
		});

		$this->app->singleton('instruments.driver', function ($app) {
			return $app['instruments.factory']->driver();
		});

		$this->app->singleton('instruments', function ($app) {
			return new Instruments($app['instruments.driver']);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return ['instruments', 'instruments.factory', 'instruments.store'];
	}
}
