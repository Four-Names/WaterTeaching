<?php
namespace app\controller;
use think\facade\Config;
use think\facade\Request;
use think\Response;

/**
 * Class Base
 * @package app\controller
 */
abstract class Base
{
    /**
     * @var int
     */
    protected $page;

    /**
     * @var int
     */
    protected $pageSize;

    /**
     * Base constructor.
     */
    public function __construct()
    {
        //获取分页
        $this->page = (int)Request::param('page');

        //获取条数
        $this->pageSize = (int)Request::param('page_size', Config::get('app.page_size'));
    }

    //TODO: 把build提取成一个类，并从中间件中导入新类
    /**
     * @param $data
     * @param string $msg
     * @param int $code
     * @param string $type
     * @return Response
     */
    protected function build($data = NULL, string $msg = 'success', int $code = 0, string $type = 'json') : Response
    {
        //标准api结构生成
        $result = [
            //状态码
            'code'  => $code,
            //消息
            'msg'   => $msg,
            //数据
            'data'  => $data
        ];

        //返回api接口
        return Response::create($result, $type);
    }

    /**
     * 生成加课码
     * @return String
     */
    protected function createCode() {
        return strtoupper(iconv_substr(md5(uniqid()),0,4));
    }

    /**
     * @param $name
     * @param $arguments
     * @return Response
     */
    public function __call($name, $arguments)
    {
        //404，方法不存在的错误
        return $this->build([], '资源不存在~', 404);
    }
}