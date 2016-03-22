<?php namespace Exolnet\Instruments;

use Exolnet\Instruments\Drivers\LogDriver;
use Exolnet\Instruments\Drivers\NullDriver;
use Exolnet\Instruments\Drivers\StatsdDriver;
use Exolnet\Instruments\Exceptions\InstrumentsConfigurationException;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use League\StatsD\Client;

class InstrumentsManager extends Manager
{
	public function getNamespace()
	{
		$application = config('instruments.application') ?: Str::slug(config('app.name'));
		$server      = config('instruments.server')      ?: str_replace('.', '-', gethostname());
		$environment = $this->app->environment();

		if ( ! $application) {
			throw new InstrumentsConfigurationException('Instruments needs an application name to works.');
		}

		return implode('.', ['applications', $application, $server, $environment]);
	}

	/**
	 * Get the default driver name.
	 *
	 * @return string
	 */
	public function getDefaultDriver()
	{
		return $this->app['config']['instruments.driver'];
	}

	/**
	 * @return \Exolnet\Instruments\Drivers\StatsdDriver
	 */
	protected function createStatsdDriver()
	{
		$options = config('instruments.options') + [
			'namespace' => $this->getNamespace(),
		];

		$client = new Client();
		$client->configure($options);

		return new StatsdDriver($client);
	}

	/**
	 * @return \Exolnet\Instruments\Drivers\LogDriver
	 */
	protected function createLogDriver()
	{
		return new LogDriver();
	}

	/**
	 * @return \Exolnet\Instruments\Drivers\NullDriver
	 */
	protected function createNullDriver()
	{
		return new NullDriver();
	}
}