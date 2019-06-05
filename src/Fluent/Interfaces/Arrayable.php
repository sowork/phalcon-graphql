<?php
/**
 * @author: xingshenqiang<xingshenqiang@uniondrug.cn>
 * @date:   2019-05-23
 */

namespace Sowork\GraphQL\Fluent\Interfaces;


interface Arrayable
{
    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray();
}