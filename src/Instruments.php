<?php namespace Exolnet\Instruments;

use Auth;
use Closure;
use Exception;
use Exolnet\Instruments\Drivers\Driver;
use Exolnet\Instruments\Middleware\InstrumentsMiddleware;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Swift_Message;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Instruments
{
	/**
	 * @var \Exolnet\Instruments\Drivers\Driver
	 */
	private $driver;

	/**
	 * @var null|float
	 */
	private $requestStartTime = null;

	/**
	 * Instruments constructor.
	 *
	 * @param \Exolnet\Instruments\Drivers\Driver $driver
	 */
	public function __construct(Driver $driver)
	{
		$this->driver = $driver;
	}

	/**
	 * @return \Exolnet\Instruments\Drivers\Driver
	 */
	public function getDriver()
	{
		return $this->driver;
	}

	/**
	 * @return void
	 */
	public function boot()
	{
		$this->listenHttp();
		$this->listenDatabase();
		$this->listenMail();
		$this->listenAuth();
	}

	/**
	 * @return void
	 */
	protected function listenHttp()
	{
		app('router')->before(function(Request $request) {
			$this->requestStartTime = microtime(true);

			$this->collectRequest($request);

			app()->error(function(Exception $exception) use ($request) {
				$this->collectException($request, $exception);
			});
		});

		app('router')->after(function ($request, $response) {
			$this->collectResponse($request, $response);
		});
	}

	/**
	 * @return void
	 */
	protected function listenDatabase()
	{
		app('events')->listen('illuminate.query', function($query, $bindings, $time, $connection) {
			$query = trim($query);

			if (preg_match('/^(SELECT|UPDATE|DELETE)/i', $query, $match)) {
				$type  = strtolower($match[1]);
				$table = $this->extractTableName($query, 'FROM');
			} elseif (preg_match('/^(INSERT|REPLACE)/i', $query, $match)) {
				$type  = strtolower($match[1]);
				$table = $this->extractTableName($query, 'INTO');
			} else {
				$type  = 'questions';
				$table = 'null';
			}

			$this->driver->timing('sql.'. $connection .'.'. $table .'.'. $type .'.query_time', $time);
		});
	}

	/**
	 * @param string $query
	 * @param string $afterKeyword
	 * @return string
	 */
	protected function extractTableName($query, $afterKeyword)
	{
		if ( ! preg_match('/\s+'. preg_quote($afterKeyword) .'\s(?:\S+\.)?(\S+)/i', $query, $match)) {
			return 'null';
		}

		return trim($match[1], '`');
	}

	/**
	 * @return void
	 */
	protected function listenMail()
	{
		app('events')->listen('mailer.sending', function(Swift_Message $message) {
			$this->driver->increment('mail.send.count');

			$recipients = [
				'to' => count($message->getTo()),
				'cc' => count($message->getCc()),
				'bcc' => count($message->getBcc()),
			];

			foreach ($recipients as $recipient => $recipientCount) {
				if ($recipientCount > 0) {
					$this->driver->increment('mail.recipients.'. $recipient .'.count', $recipientCount);
				}
			}
		});
	}

	/**
	 * @return void
	 */
	protected function listenAuth()
	{
		app('events')->listen('auth.attempt', function() {
			$this->driver->increment('authentication.login.attempt.count');
		});

		app('events')->listen('auth.login', function() {
			$this->driver->increment('authentication.login.success.count');
		});

		app('events')->listen('auth.fail', function() {
			$this->driver->increment('authentication.login.fail.count');
		});

		app('events')->listen('auth.logout', function() {
			$this->driver->increment('authentication.logout.success.count');
		});
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	public function guessRequestType(Request $request)
	{
		$userAgent    = strtolower($request->header('User-Agent'));
		$contentType  = array_get($request->getAcceptableContentTypes(), 0);

		if (strpos($userAgent, 'googlebot') !== false) {
			return 'googlebot';
		} elseif ($request->ajax()) {
			return 'ajax';
		} elseif ($request->header('X-PJAX')) {
			return 'pjax';
		} elseif ($contentType === null) {
			return 'raw';
		} elseif (Str::contains($contentType, ['application/rss+xml', 'application/rdf+xml', 'application/atom+xml'])) {
			return 'feed';
		} elseif ($request->wantsJson() || Str::contains($contentType, ['application/xml', 'text/xml'])) {
			return 'api';
		} elseif ($contentType !== 'text/html') {
			return 'other';
		} elseif (Auth::check()) {
			return 'user';
		}

		return 'public';
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 * @return string
	 */
	public function getRequestContext(Request $request)
	{
		return implode('.', [
			$request->getScheme(),
			strtolower($request->getMethod()),
			$this->guessRequestType($request),
			'_'. str_replace('/', '_', trim($request->getPathInfo(), '/')),
		]);
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 * @param \Symfony\Component\HttpFoundation\Response $response
	 * @return mixed
	 */
	public function collectResponse(Request $request, Response $response)
	{
		$this->collectRequest($request);

		// Collect response time
		if ($this->requestStartTime !== null) {
			$timeMetric = 'response.' . $this->getRequestContext($request) . '.response_time';
			$this->driver->timing($timeMetric, microtime(true) - $this->requestStartTime);
		}

		// Collect response code
		$responseCode = $response instanceof Response ? $response->getStatusCode() : 200;
		$codeMetric   = 'response.'. $this->getRequestContext($request) .'.'. $responseCode .'.count';

		$this->driver->increment($codeMetric);

		return $response;
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 * @return void
	 */
	public function collectRequest(Request $request)
	{
		$requestMetric = 'request.'. $this->getRequestContext($request) .'.count';
		$this->driver->increment($requestMetric);
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 * @param \Exception $e
	 * @return void
	 */
	public function collectException(Request $request, Exception $e)
	{
		// Collect status code
		$responseCode = $e instanceof HttpException ? $e->getStatusCode() : 500;
		$codeMetric   = 'response.'. $this->getRequestContext($request) .'.'. $responseCode .'.count';

		$this->driver->increment($codeMetric);

		// Collection exception type
		$exceptionPath   = trim(str_replace('\\', '_', get_class($e)), '_');
		$exceptionMetric = 'exceptions.'. $this->getRequestContext($request) .'.exception.'. $exceptionPath .'.count';

		$this->driver->increment($exceptionMetric);
	}
}
