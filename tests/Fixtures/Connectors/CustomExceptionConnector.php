<?php

declare(strict_types=1);

namespace Saloon\Tests\Fixtures\Connectors;

use Throwable;
use Saloon\Http\Connector;
use Saloon\Contracts\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Tests\Fixtures\Exceptions\ConnectorRequestException;

class CustomExceptionConnector extends Connector
{
    use AcceptsJson;

    /**
     * Define the base url of the api.
     *
     * @return string
     */
    public function resolveBaseUrl(): string
    {
        return apiUrl();
    }

    /**
     * Customise the request exception handler
     *
     * @param \Saloon\Contracts\Response $response
     * @param \Throwable|null $senderException
     * @return \Throwable|null
     */
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        return new ConnectorRequestException($response, 'Oh yee-naw.', 0, $senderException);
    }
}
