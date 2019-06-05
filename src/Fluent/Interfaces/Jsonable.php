<?php
/**
 * @author: xingshenqiang<xingshenqiang@uniondrug.cn>
 * @date:   2019-05-23
 */

namespace Sowork\GraphQL\Fluent\Interfaces;


interface Jsonable
{
    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0);
}