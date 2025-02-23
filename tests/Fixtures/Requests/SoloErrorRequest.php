<?php

declare(strict_types=1);

namespace Saloon\Tests\Fixtures\Requests;

use Saloon\Enums\Method;
use Saloon\Http\SoloRequest;

class SoloErrorRequest extends SoloRequest
{
    /**
     * Define the HTTP method.
     *
     * @var string
     */
    protected Method $method = Method::GET;

    /**
     * Define the endpoint for the request.
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return 'https://tests.saloon.dev/api/error';
    }
}
