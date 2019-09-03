<?php

declare(strict_types=1);

return [
    'alnum' => Phalcon\Validation\Validator\Alnum::class,
    'alpha' => Phalcon\Validation\Validator\Alpha::class,
    'date' => Phalcon\Validation\Validator\Date::class,
    'digit' => Phalcon\Validation\Validator\Digit::class,
    'file' => Phalcon\Validation\Validator\File::class,
    'unique' => Phalcon\Validation\Validator\Uniqueness::class,
    'numeric' => Phalcon\Validation\Validator\Numericality::class,
    'required' => Phalcon\Validation\Validator\PresenceOf::class,
    'confirmed' => Phalcon\Validation\Validator\Identical::class,
    'email' => Phalcon\Validation\Validator\Email::class,
    'not_in' => Phalcon\Validation\Validator\ExclusionIn::class,
    'in' => Phalcon\Validation\Validator\InclusionIn::class,
    'regex' => Phalcon\Validation\Validator\Regex::class,
    'length' => Phalcon\Validation\Validator\StringLength::class,
    'between' => Phalcon\Validation\Validator\Between::class,
    'confirm' => Phalcon\Validation\Validator\Confirmation::class,
    'url' => Phalcon\Validation\Validator\Url::class,
    'credit_card' => Phalcon\Validation\Validator\CreditCard::class,
    'callback' => Phalcon\Validation\Validator\Callback::class
];