<?php

namespace app\controller;

use think\exception\ValidateException;
use app\validate\Course as CourseVerify;

use think\facade\Request;

use app\model\User as UserModel;
use app\model\Classes as ClassesModel;
use app\model\Member as MemberModel;
use app\model\Course as CourseModel;
use app\controller\Base;

include "Event.php";
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
        if (array_key_exists('describ', $courseData)) $courseData["describ"] = trim($courseData["describ"]);
        if (array_key_exists('title', $courseData)) $courseData["title"] = trim($courseData["title"]);

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

    public function deleteCourse()
    {
        //1. 获取用户ID、课程ID
        $curUser = request()->uid;
        $courseId = Request::route("course_id");

        //2. 判断是否有该课程
        $course = CourseModel::with('classes')->find($courseId);
        if (!$course) {
            return $this->build(NULL, "无此课程", 204)->code(204);
        }

        // //3. 判断是否有权限删除
        $userId = $course['user_id'];
        if ($userId !== $curUser) {
            return $this->build(null, "没有权限", 403)->code(403);
        }

        // //4. 删除课程
        // TODO: 添加事务处理
        // TODO: 删除课程对应的用户
        //启动事务处理
        CourseModel::startTrans();
        //思路：获取course_id后再获得该course_id下的班级列表,对该班级列表进行删除班级
        $class_list = ClassesModel::where('course_id',$courseId)->column('id');
        $hasDelClasses = CourseModel::where('id',$courseId)->delete();
        $string="";
        $count = count($class_list);
        $size = 0;
        try{
            foreach($class_list as $class)
                delete_class_event($class)==true?++$size:$size;
            if($size==$count){
                CourseModel::commit();
                return $this->build($course,"删除成功");
            }
            else{
                CourseModel::rollback();
                var_dump($string."班级数：$count ,成功删除数：$size");
                return $this->build($course,"删除课程失败,已回滚数据1")->code(500);
            }
            
        }
        catch (\Exception $e) {
            CourseModel::rollback();
            var_dump($string."班级数：$count,成功删除数：$size");
            return $this->build($course,"删除课程失败,已回滚数据2")->code(500);
        }
        
        // $course->together(["classes"])->where("id", $courseId)->delete();
        // $course->classes()->where("course_id", $courseId)->delete();

        // return $this->build(NULL, "删除成功");
    }

    public function updateCourse()
    {
        //0. 定义字段
        $write_field = ['title', 'describ'];  //接收、写入字段
        $hidden_field = [ 'update_time', 'delete_time'];  //隐藏字段

        //1. 获取用户ID、课程ID
        $curUser = request()->uid;
        $courseId = Request::route("course_id");


        //2. 判断是否有该课程
        $course = CourseModel::find($courseId);
        if (!$course) {
            return $this->build(NULL, "无此课程", 204)->code(204);
        }

        //3. 判断是否有权限更新
        $userId = $course['user_id'];
        if ($userId !== $curUser) {
            return $this->build(null, "没有权限", 403)->code(403);
        }


        //4. 获取并校验数据
        $newData = Request::only($write_field, 'post');

        try {
            validate(CourseVerify::class)->batch(true)->scene('updateCourse')->check($newData);
        } catch (ValidateException $e) {
            return $this->build($e->getError(), "参数错误")->code(400);
        }

        //5. 更新课程信息
        $course->save($newData);
        
        //6. 返回课程信息
        return $this->build($course->hidden($hidden_field));
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
        if (array_key_exists('describ', $courseData)) $courseData["describ"] = trim($courseData["describ"]);
        if (array_key_exists('title', $courseData)) $courseData["title"] = trim($courseData["title"]);

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


    public function getTeach()
    {
        $visible_field = ['title', 'describ', 'id', 'code'];  //定义输出字段

        //1. 获取用户ID
        $userId = request()->uid;

        //2. 获取课程列表
        $user = UserModel::find($userId);
        $courses = $user->course()->visible($visible_field)->select();

        if ($courses->isEmpty()) {
            return $this->build(NULL, "无课程")->code(204);
        }

        //3. 获取班级列表
        foreach ($courses as $course) {
            $course->classes = $course->classes()->visible($visible_field)->select();
        }

        //4. 返回数据
        return $this->build($courses);
    }

    public function getStudy()
    {
        //0. 定义可见字段
        $class_visible =["id","title","code","describ"];
        $course_visible = ["id","title","describ"];
        $teacher_visible = ["id","username","email","avatar"];


        //1. 获取用户id
        $userId = request()->uid;

        //2. 获取听课列表
        //- 获取班级信息
        $classes = MemberModel::where('user_id', $userId)->select();

        if ($classes->isEmpty()) {
            return $this->build(NULL, "没有加入课程", 204)->code(204);
        }


        //- 查询汇总结果
        $result = [];
        foreach ($classes as $key => $value) {
            $classId = $value["classes_id"]; //班级ID 


            $class = ClassesModel::find($classId)->visible($class_visible);
            $course = $class->course()->find()->visible($course_visible);
            $teacher = UserModel::find($course["user_id"])->visible($teacher_visible);

            // $courseTitle =  ClassesModel::find($value["classes_id"])->course()->value('title');
            $result[$key] = [];
            $result[$key]["classes"] = $class; //班级
            $result[$key]["course"]  = $course;   //课程
            $result[$key]["teacher"] = $teacher;   //教师
        }


        return $this->build($result);
    }

}
