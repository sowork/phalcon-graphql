<?php
/**
 * @author: xingshenqiang<xingshenqiang@uniondrug.cn>
 * @date:   2019-04-20
 */

namespace Sowork\GraphQL\Http\Controllers;


use Phalcon\Mvc\Controller;

/**
 * graphql请求路由处理类
 * Class GraphqlController
 * @package Sowork\GraphQL\Http\Controllers
 */
class GraphQLController extends Controller
{
    public function queryAction(...$schema)
    {
        // ==============================================================
        $di = graphql_app();
        $di->set('profiler', function(){
            return new \Phalcon\Db\Profiler();
        }, true);

        //新建一个事件管理器
        $eventsManager = new \Phalcon\Events\Manager();

        //从di中获取共享的profiler实例
        $profiler = $di->getProfiler();

        //监听所有的db事件
        $eventsManager->attach('db', function($event, $connection) use ($profiler) {
            //一条语句查询之前事件，profiler开始记录sql语句
            if ($event->getType() == 'beforeQuery') {
                $profiler->startProfile($connection->getSQLStatement());
            }
            //一条语句查询结束，结束本次记录，记录结果会保存在profiler对象中
            if ($event->getType() == 'afterQuery') {
                $profiler->stopProfile();
            }
        });

        $connection = $di->get('db');

        //将事件管理器绑定到db实例中
        $connection->setEventsManager($eventsManager);
        // ======================================================


        $request = $this->request;

        $schema = implode('/', $schema);
        if (!$schema) {
            $schema = graphql_config('graphql.default_schema');
        }
        $body = getInputData($request);
        // 如果query没有发现，意味着这是一个批量请求
        $batch = $body['query'] ? [$body] : $body;

        $completedQueries = [];
        $paramsKey = graphql_config('graphql.params_key');
        $opts = [
            'context' => [],
            'schema'  => $schema,
        ];

        // ====================================================
        foreach ($batch as $batchItem) {
            $query = $batchItem['query'];
            $params = $batchItem[$paramsKey] ?? null;

            if (is_string($params)) {
                $params = json_decode($params, true);
            }

            $completedQueries[] = $this->graphql->query($query, $params, array_merge($opts, [
                'operationName' => $batchItem['operationName'] ?? null,
            ]));
        }
        // ====================================================

        //获取所有的prifler记录结果，这是一个数组，每条记录对应一个sql语句
        $profiles = $di->get('profiler')->getProfiles();
        //遍历输出
        foreach ($profiles as $profile) {
            echo "SQL语句: ", $profile->getSQLStatement(), "\n";
//            echo "开始时间: ", $profile->getInitialTime(), "\n";
//            echo "结束时间: ", $profile->getFinalTime(), "\n";
//            echo "消耗时间: ", $profile->getTotalElapsedSeconds(), "\n";
        }

        return $body['query'] ? json_encode($completedQueries, true) : json_encode($completedQueries[0], true);

//        $queryType = new ObjectType([
//            'name'   => 'Query',
//            'fields' => [
//                'echo' => [
//                    'type'    => Type::string(),
//                    'args'    => [
//                        'message' => ['type' => Type::string()],
//                    ],
//                    'resolve' => function ($root, $args) {
//                        return $root['prefix'] . $args['message'];
//                    }
//                ],
//            ],
//        ]);
//        $mutationType = new ObjectType([
//            'name'   => 'Calc',
//            'fields' => [
//                'sum' => [
//                    'type'    => Type::int(),
//                    'args'    => [
//                        'x' => ['type' => Type::int()],
//                        'y' => ['type' => Type::int()],
//                    ],
//                    'resolve' => function ($root, $args) {
//                        return $args['x'] + $args['y'];
//                    },
//                ],
//            ],
//        ]);
//        // See docs on schema options:
//        // http://webonyx.github.io/graphql-php/type-system/schema/#configuration-options
//        $graphqlSchema = new Schema([
//            'query'    => $queryType,
//            'mutation' => $mutationType,
//        ]);
//
////        $query = $this->request->getJsonRawBody()->query ?? '{}';
//        $result = $this->graphql->query($graphqlSchema);
//        echo json_encode($result);
    }
}