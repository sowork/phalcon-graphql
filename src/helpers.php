<?php
/**
 * @author: xingshenqiang<xingshenqiang@uniondrug.cn>
 * @date:   2019-04-29
 */

if (!function_exists('getInputData')) {
    /**
     * 获取请求发送入参
     * @param \Phalcon\Http\Request $request
     * @return array
     */
    function getInputData(\Phalcon\Http\Request $request)
    {
        if ($request->getContentType() === 'application/json') {
            return $request->getJsonRawBody(true);
        } else if ($request->isPost()) {
            return $request->getPost();
        } else if ($request->isPut()) {
            return $request->getPut();
        } else if ($request->isGet()) {
            return $request->getQuery();
        }

        return [];
    }
}

if (!function_exists('graphql_config')) {
    /**
     * 获取配置服务对象
     * @return mixed|\Phalcon\Config
     */
    function graphql_config()
    {
        $args = func_get_args();
        $config = \Phalcon\Di::getDefault()->getShared('config');

        if (empty($args)) {
            return $config;
        }

        return call_user_func_array([$config, 'path'], $args);
    }
}

if (!function_exists('graphql_app')) {
    /**
     * 获取容器实例
     * @param string $name
     * @return Phalcon\Di
     */
    function graphql_app(string $name = '')
    {
        return $name ? \Phalcon\Di::getDefault()->get($name) : \Phalcon\Di::getDefault();
    }
}

if (!function_exists('studly_case')) {
    /**
     * 将- _和空格都去掉然后每个单词首字母大写
     * @param $value
     * @return mixed
     */
    function studly_case($value)
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return str_replace(' ', '', $value);
    }
}

