<?php
/**
 * @author: xingshenqiang<xingshenqiang@uniondrug.cn>
 * @date:   2019-05-24
 */

namespace Sowork\GraphQL\Error;


use GraphQL\Error\Error;

class ValidationError extends Error
{

    public $validator;

    public function setValidator($validator)
    {
        $this->validator = $validator;

        return $this;
    }

    public function getValidatorMessages()
    {
        return $this->validator ? $this->validator->messages():[];
    }
}