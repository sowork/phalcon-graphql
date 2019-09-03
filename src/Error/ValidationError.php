<?php

declare(strict_types=1);

namespace Sowork\GraphQL\Error;

use GraphQL\Error\Error;
use Phalcon\Validation;
use Phalcon\Validation\Message\Group;

class ValidationError extends Error
{

    /** @var Validation */
    public $validation;

    public function __construct(string $message, Validation $validation)
    {
        parent::__construct($message);
        $this->validation = $validation;

        return $this;
    }

    public function getValidatorMessages(): Group
    {
        return $this->validation->getMessages();
    }

    public function getValidator(): Validation
    {
        return $this->validation;
    }
}