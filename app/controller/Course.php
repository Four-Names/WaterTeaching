<?php

namespace app\controller;

use think\exception\ValidateException;
use app\validate\Course as CourseVerify;

use think\facade\Request;

use app\model\Course as CourseModel;
use app\controller\Base;

class Course extends Base
{
    public function index()
    {

        echo "Hello Here is Course Controller";
    }

    public function createCourse()
    {
        $receive_field = ['title', 'describ'];  //接收字段
        $visible_field = ['id', 'user_id', 'title', 'describ'];  //输出字段
        $write_field = $visible_field; //写入字段


        //1. 获取USER ID
        $userId = request()->uid;

        //2. 获取提交信息
        $courseData = Request::only($receive_field, 'post');
        $courseData["user_id"] = $userId;

        //3. 数据整形
        $courseData["describ"] = trim($courseData["describ"]);
        $courseData["title"] = trim($courseData["title"]);

        //4. 校验数据
        try {
            validate(CourseVerify::class)->batch(true)->scene('create')->check($courseData);
        } catch (ValidateException $e) {
            return $this->build($e->getError(), "参数错误")->code(400);
        }

        //5. 添加记录
        $course = CourseModel::create($courseData, $write_field)->visible($visible_field);

        //6. 返回课程信息
        return $this->build($course, "成功");
    }

    public function createClass()
    {
        $receive_field = ['title', 'describ', 'course_id'];  //接收字段
        $visible_field = ['id', 'course_id', 'title', 'describ'];  //输出字段
        $write_field = $visible_field; //写入字段


        //1. 获取USER ID
        $userId = request()->uid;

        //2. 获取提交信息
        $courseData = Request::only($receive_field, 'post');

        //3. 数据整形
        $courseData["describ"] = trim($courseData["describ"]);
        $courseData["title"] = trim($courseData["title"]);

        //4. 校验数据
        try {
            validate(CourseVerify::class)->batch(true)->scene('create')->check($courseData);
        } catch (ValidateException $e) {
            return $this->build($e->getError(), "参数错误")->code(400);
        }

        //5. 添加记录
        $course = CourseModel::create($courseData, $write_field)->visible($visible_field);

        //6. 返回课程信息
        return $this->build($course, "成功");
    }
}