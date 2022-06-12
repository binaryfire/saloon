<?php

namespace Sammyjo20\Saloon\Http\Senders;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Sammyjo20\Saloon\Data\RequestDataType;
use Sammyjo20\Saloon\Http\MockResponse;
use Sammyjo20\Saloon\Http\PendingSaloonRequest;
use Sammyjo20\Saloon\Http\RequestSender;
use Sammyjo20\Saloon\Http\Responses\SaloonResponse;

class GuzzleSender extends RequestSender
{
    /**
     * @var GuzzleClient
     */
    protected GuzzleClient $client;

    /**
     * Create the HTTP client.
     */
    public function __construct()
    {
        $this->client = new GuzzleClient([
            'connect_timeout' => 10,
            'timeout' => 30,
            'http_errors' => true,
        ]);
    }

    /**
     * Send a request
     *
     * @param PendingSaloonRequest $saloonRequest
     * @param bool $asynchronous
     * @return SaloonResponse|PromiseInterface
     * @throws GuzzleException
     */
    public function processRequest(PendingSaloonRequest $saloonRequest, bool $asynchronous = false): SaloonResponse|PromiseInterface
    {
        return $asynchronous === true
            ? $this->sendAsynchronousRequest($saloonRequest)
            : $this->sendSynchronousRequest($saloonRequest);
    }

    /**
     * Send a synchronous request.
     *
     * @param PendingSaloonRequest $saloonRequest
     * @return SaloonResponse
     * @throws GuzzleException
     */
    protected function sendSynchronousRequest(PendingSaloonRequest $saloonRequest): SaloonResponse
    {
        $guzzleRequest = $this->createGuzzleRequest($saloonRequest);
        $guzzleRequestOptions = $this->createRequestOptions($saloonRequest);

        try {
            $guzzleResponse = $this->client->send($guzzleRequest, $guzzleRequestOptions);
        } catch (BadResponseException $exception) {
            return $this->createResponse($saloonRequest, $exception->getResponse(), $exception);
        }

        return $this->createResponse($saloonRequest, $guzzleResponse);
    }

    /**
     * Send an asynchronous request
     *
     * @param PendingSaloonRequest $saloonRequest
     * @return PromiseInterface
     */
    protected function sendAsynchronousRequest(PendingSaloonRequest $saloonRequest): PromiseInterface
    {
        $guzzleRequest = $this->createGuzzleRequest($saloonRequest);
        $guzzleRequestOptions = $this->createRequestOptions($saloonRequest);

        return $this->client->sendAsync($guzzleRequest, $guzzleRequestOptions)
            ->then(
                function (ResponseInterface $guzzleResponse) use ($saloonRequest) {
                    // Instead of the promise returning a Guzzle response, we want to return
                    // a Saloon response.

                    return $this->createResponse($saloonRequest, $guzzleResponse);
                },
                function (GuzzleException $guzzleException) use ($saloonRequest) {
                    // If the exception was a connect exception, we should return that in the
                    // promise instead rather than trying to convert it into a
                    // SaloonResponse, since there was no response.

                    if (! $guzzleException instanceof RequestException) {
                        throw $guzzleException;
                    }

                    $response = $this->createResponse($saloonRequest, $guzzleException->getResponse(), $guzzleException);

                    throw $response->toException();
                }
            );
    }

    /**
     * Create the Guzzle request
     *
     * @param PendingSaloonRequest $request
     * @return Request
     */
    private function createGuzzleRequest(PendingSaloonRequest $request): Request
    {
        return new Request($request->getMethod()->value, $request->getUrl());
    }

    /**
     * Build up all the request options
     *
     * @param PendingSaloonRequest $request
     * @return array
     */
    private function createRequestOptions(PendingSaloonRequest $request): array
    {
        $requestOptions = [
            RequestOptions::HEADERS => $request->headers()->all(),
        ];

        foreach ($request->config()->all() as $configVariable => $value) {
            $requestOptions[$configVariable] = $value;
        }

        // Build up the data options

        $data = $request->data()->all();

        match ($request->getDataType()) {
            RequestDataType::JSON => $requestOptions['json'] = $data,
            RequestDataType::MULTIPART => $requestOptions['multipart'] = $data,
            RequestDataType::FORM => $requestOptions['form_params'] = $data,
            RequestDataType::MIXED => $requestOptions['body'] = $data,
            default => null,
        };

        return $requestOptions;
    }

    /**
     * Create a response.
     *
     * @param PendingSaloonRequest $pendingSaloonRequest
     * @param Response $guzzleResponse
     * @param RequestException|null $exception
     * @return SaloonResponse
     */
    private function createResponse(PendingSaloonRequest $pendingSaloonRequest, Response $guzzleResponse, RequestException $exception = null): SaloonResponse
    {
        $responseClass = $pendingSaloonRequest->getResponseClass();

        /** @var SaloonResponse $response */
        $response = new $responseClass($pendingSaloonRequest, $guzzleResponse, $exception);

        // Run the response pipeline

        return $this->processResponse($pendingSaloonRequest, $response);
    }

    /**
     * Get the base class that the custom responses should extend.
     *
     * @return string
     */
    public function getBaseResponseClass(): string
    {
        return SaloonResponse::class;
    }
}
