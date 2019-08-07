<?php

declare(strict_types=1);

namespace Sowork\GraphQL;

use GraphQL\Utils\Utils;
use GraphQL\Server\RequestError;
use GraphQL\Error\InvariantViolation;
use Phalcon\Http\Request;

class GraphQLUploadMiddleware
{
    /**
     * Process the request and return either a modified request or the original one.
     * @param Request $request
     * @return array
     * @throws RequestError
     */
    public function processRequest(Request $request): array
    {
        $contentType = $request->getHeader('content-type') ?: '';

        if (mb_stripos($contentType, 'multipart/form-data') !== false) {
            $this->validateParsedBody($request);
            return $this->parseUploadedFiles($request);
        }

        return getInputData($request);
    }

    /**
     * Inject uploaded files defined in the 'map' key into the 'variables' key.
     * @param Request $request
     * @return array
     * @throws RequestError
     */
    private function parseUploadedFiles(Request $request): array
    {
        $bodyParams = getInputData($request);
        if (! isset($bodyParams['map'])) {
            throw new RequestError('The request must define a `map`');
        }

        $map = json_decode($bodyParams['map'], true);
        $result = json_decode($bodyParams['operations'], true);
        if (isset($result['operationName'])) {
            $result['operation'] = $result['operationName'];
            unset($result['operationName']);
        }

        foreach ($map as $fileKey => $locations) {
            foreach ($locations as $location) {
                $items = &$result;
                foreach (explode('.', $location) as $key) {
                    if (! isset($items[$key]) || ! is_array($items[$key])) {
                        $items[$key] = [];
                    }
                    $items = &$items[$key];
                }

                $items = $request->getUploadedFiles()[$fileKey];
            }
        }

        return $result;
    }

    /**
     * Validates that the request meet our expectations.
     * @param Request $request
     * @return void
     * @throws RequestError
     */
    private function validateParsedBody(Request $request): void
    {
        $bodyParams = getInputData($request);

        if (null === $bodyParams) {
            throw new InvariantViolation(
                'Request is expected to provide parsed body for "multipart/form-data" requests but got null'
            );
        }

        if (! is_array($bodyParams)) {
            throw new RequestError(
                'GraphQL Server expects JSON object or array, but got '.Utils::printSafeJson($bodyParams)
            );
        }

        if (empty($bodyParams)) {
            throw new InvariantViolation(
                'Request is expected to provide parsed body for "multipart/form-data" requests but got empty array'
            );
        }
    }
}
