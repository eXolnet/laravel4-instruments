<?php namespace Exolnet\Instruments;

use Config;
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
		$application = Config::get('exolnet-instruments::application') ?: Str::slug(str_replace('.', '-', Config::get('app.name')));
		$server      = Config::get('exolnet-instruments::server')      ?: str_replace('.', '_', gethostname() ?: 'unknown');
		$environment = $this->app->environment();

		if ( ! $application) {
			throw new InstrumentsConfigurationException('Instruments needs an application name to works.');
		}

		return implode('.', ['applications', $application, $environment, $server]);
	}

	/**
	 * Get the default driver name.
	 *
	 * @return string
	 */
	public function getDefaultDriver()
	{
		return $this->app['config']['exolnet-instruments::driver'];
	}

	/**
	 * @return \Exolnet\Instruments\Drivers\StatsdDriver
	 */
	protected function createStatsdDriver()
	{
		$options = Config::get('exolnet-instruments::statsd') + [
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
