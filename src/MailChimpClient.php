<?php

declare(strict_types=1);

namespace NAttreid\MailChimp;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use NAttreid\MailChimp\Hooks\MailChimpConfig;
use Nette\InvalidStateException;
use Nette\SmartObject;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * Class Client
 *
 * @author Attreid <attreid@gmail.com>
 */
class MailChimpClient
{
	use SmartObject;

	/** @var Client */
	private $client;

	/** @var string */
	private $uri;

	/** @var MailChimpConfig */
	private $config;

	/** @var bool */
	private $debug;

	public function __construct(bool $debug, MailChimpConfig $config)
	{
		$this->config = $config;
		$this->uri = "https://{$config->dc}.api.mailchimp.com/3.0/";
		$this->debug = $debug;
	}

	/**
	 * @param ResponseInterface $response
	 * @return stdClass|null
	 */
	private function getResponse(ResponseInterface $response): ?stdClass
	{
		$json = $response->getBody()->getContents();
		if (!empty($json)) {
			return Json::decode($json);
		}
		return null;
	}

	private function getClient(): Client
	{
		if ($this->client === null) {
			if (Strings::match($this->config->dc, '/^us([1-9]|1[0-6])$/') === null) {
				throw new InvalidStateException('Invalid dc (available: us1 - us14)');
			}
			$this->client = new Client(['base_uri' => $this->uri]);
		}
		return $this->client;
	}

	/**
	 * @param string $method
	 * @param string $url
	 * @param array $args
	 * @return stdClass|null
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	private function request(string $method, string $url, array $args = []): ?stdClass
	{
		if (empty($this->config->apiKey)) {
			throw new CredentialsNotSetException('ApiKey must be set');
		}

		try {
			$options = [
				RequestOptions::AUTH => [
					'user',
					$this->config->apiKey
				]
			];

			if (count($args) >= 1) {
				$options[RequestOptions::JSON] = $args;
			}

			$response = $this->getClient()->request($method, $url, $options);

			switch ($response->getStatusCode()) {
				case 200:
				case 201:
					return $this->getResponse($response);
				case 204:
					return new stdClass();
			}
		} catch (ClientException $ex) {
			switch ($ex->getCode()) {
				default:
					throw new MailChimpClientException($ex);
					break;
				case 400:
				case 404:
				case 422:
					if ($this->debug) {
						throw new MailChimpClientException($ex);
					} else {
						return null;
					}
			}
		} catch (\Exception $ex) {
			throw new MailChimpClientException($ex);
		}
		return null;
	}

	/**
	 * @throws CredentialsNotSetException
	 */
	private function checkList(): void
	{
		if (empty($this->config->listId)) {
			throw new CredentialsNotSetException('ListId must be set');
		}
	}

	/**
	 * @param string $url
	 * @return stdClass|null
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	private function get(string $url): ?stdClass
	{
		return $this->request('GET', $url);
	}

	/**
	 * @param string $url
	 * @param string[] $args
	 * @return stdClass|null
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	private function post(string $url, array $args = []): ?stdClass
	{
		return $this->request('POST', $url, $args);
	}

	/**
	 * @param string $url
	 * @return bool
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	private function delete(string $url): bool
	{
		return $this->request('DELETE', $url) !== null;
	}

	/**
	 * @param string $url
	 * @param string[] $args
	 * @return stdClass|null
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	private function patch(string $url, array $args = []): ?stdClass
	{
		return $this->request('PATCH', $url, $args);
	}

	/**
	 * @param string $url
	 * @param string[] $args
	 * @return stdClass|null
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	private function put(string $url, array $args = []): ?stdClass
	{
		return $this->request('PUT', $url, $args);
	}

	/**
	 * Aliveness test
	 * @return stdClass
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	public function ping(): stdClass
	{
		return $this->get('');
	}

	/**
	 * Get information about all lists
	 * @param string $email
	 * @param int $limit
	 * @param int $offset
	 * @return stdClass|null
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	public function findLists(string $email = null, int $limit = 10, int $offset = 0): ?stdClass
	{
		$args = [
			'limit=' . $limit,
			'offset=' . $offset
		];
		if ($email !== null) {
			$args[] = 'email=' . $email;
		}
		return $this->get('lists' . '?' . implode('&', $args));
	}

	/**
	 * Get information about a specific list
	 * @param string $id
	 * @return stdClass|null
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	public function getList(string $id): ?stdClass
	{
		return $this->get('lists/' . $id);
	}

	/**
	 * Get information about members in a list
	 * @return stdClass|null
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	public function findMembers(): ?stdClass
	{
		$this->checkList();
		return $this->get("lists/{$this->config->listId}/members");
	}

	/**
	 * Get information about a specific list member
	 * @param string $email
	 * @return stdClass|null
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	public function getMember(string $email): ?stdClass
	{
		$this->checkList();
		return $this->get("lists/{$this->config->listId}/members/" . md5($email));
	}

	/**
	 * Add or update a list member
	 * @param string $email
	 * @param string $name
	 * @param string $surname
	 * @return stdClass|null
	 * @throws CredentialsNotSetException
	 * @throws MailChimpClientException
	 */
	public function createMember(string $email, string $name = null, string $surname = null): ?stdClass
	{
		$this->checkList();
		$data = [
			'email_address' => $email,
			'status_if_new' => 'subscribed',
			'status' => 'subscribed'
		];
		if ($name !== null) {
			$data['merge_fields']['FNAME'] = $name;
		}
		if ($surname !== null) {
			$data['merge_fields']['LNAME'] = $surname;
		}
		return $this->put("lists/{$this->config->listId}/members/" . md5($email), $data);
	}
}
