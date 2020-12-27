<?php

namespace app\controller;

use think\exception\ValidateException;

use app\validate\Classes as ClassVerify;

use think\facade\Request;
use think\facade\Validate;
use think\facade\Db;

use app\model\User as UserModel;
use app\model\Classes as ClassesModel;
use app\model\Member as MemberModel;
use app\model\Course as CourseModel;
use think\facade\Db;

use app\controller\Base;

include "Event.php";
class Classes extends Base
{ 
  public function index()
    {

        echo "Hello Here is Course Controller";
    }

    public function createClass()
    {
        $receive_field = ['title', 'describ', 'course_id'];  //接收字段
        $visible_field = ['id', 'course_id', 'title', 'describ', 'code'];  //输出字段
        $write_field = ['title', 'describ', 'course_id', 'code']; //写入字段

        //1. 获取提交信息
        $classData = Request::only($receive_field, 'post');

        //2. 数据整形
        if (array_key_exists('describ', $classData)) $classData["describ"] = trim($classData["describ"]);
        if (array_key_exists('title', $classData)) $classData["title"] = trim($classData["title"]);

        //3. 校验数据
        try {
            validate(ClassVerify::class)->batch(true)->scene('create')->check($classData);
        } catch (ValidateException $e) {
            return $this->build($e->getError(), "参数错误")->code(400);
        }

        //4. 生成加课码
        $code = $this->createCode();

        while (!Validate::rule(['code' => 'unique:classes,code'])->check(["code"  =>  $code])) {
            $code = $this->createCode();
        }

        $classData["code"] = $code;

        //5. 添加记录
        $course = ClassesModel::create($classData, $write_field)->visible($visible_field);

        //6. 返回课程信息
        return $this->build($course, "成功");
    }

    public function deleteClass()
    {
        //1. 获取用户ID、班级ID
        $userId = request()->uid;
        $classId = Request::route("class_id");

        //2. 判断是否有该班级
        $class = ClassesModel::with('member')->find($classId);
        if (!$class) {
            return $this->build(NULL, "无此班级", 204)->code(204);
        }

        //3. 判断是否有权限删除
        $curUser = $class->course()->value('user_id');
        if ($userId !== $curUser) {
            return $this->build(NULL, "没有权限", 403)->code(403);
        }

        //4. 删除班级
        //DONE: 添加事务处理
        Db::startTrans();//启动事务处理

            try {
                //code... 
                $class->where("id", $classId)->delete();
                $class->member()->where("classes_id", $classId)->delete();

                Db::commit();//提交
                return $this->build($class, "删除成功");
            } catch (\Exception $th) {
                
                Db::rollback();//回滚数据
                return $this->build(NULL,"删除失败，请稍后再试")->code(500);
            }

    }
    
    public function updateClass()
    {
        //0. 定义字段
        $write_field = ['title', 'describ'];  //接收、写入字段
        $hidden_field = ['update_time', 'delete_time'];  //隐藏字段

        //1. 获取用户ID、班级ID
        $curUser = request()->uid;
        $classId = Request::route("class_id");

        //2. 判断是否有该班级
        $class = ClassesModel::find($classId);
        if (!$class) {
            return $this->build(NULL, "无此班级", 204)->code(204);
        }

        //3. 判断是否有权限更新课程信息
        $userId = $class->course()->value('user_id');
        if ($curUser !== $userId) {
            return $this->build(NULL, "没有权限更新班级配置", 403)->code(403);
        }

        //4. 获取并校验数据
        $newData = Request::only($write_field, 'post');

        try {
            validate(ClassVerify::class)->batch(true)->scene('updateClass')->check($newData);
        } catch (ValidateException $e) {
            return $this->build($e->getError(), "参数错误")->code(400);
        }

        //5. 更新班级信息
        $class->save($newData);

        return $this->build($class->hidden($hidden_field), "更新成功");
    }

    public function getClass()
    {
        //0. 定义可显示字段
        $class_visible = ["id", "title", "code", "describ"];
        $course_visible = ["id", "title", "describ"];
        $teacher_visible = ["id", "username", "email", "avatar"];


        //1. 获取用户ID、班级ID
        $curUser = request()->uid;
        $classId = Request::route("class_id");

        //2. 判断是否存在班级
        $class = ClassesModel::find($classId);
        if (!$class) {
            return $this->build(NULL, "课程不存在", 204)->code(204);
        }

        //3. 判断权限：创建此课程或参加此课程
        $course = $class->course()->find();

        function isTeacher($curUser,$teacherId) {
            return $curUser === $teacherId;
        }

        function isMember($curUser, $classId)
        {
            return MemberModel::where([
                "user_id" => $curUser,
                "classes_id" => $classId
            ])->find();
        }

        if (!isTeacher($course["user_id"],$curUser) && !isMember($curUser, $classId)) {
            return $this->build(NULL, "没有操作权限", 403)->code(403);
        };

        $teacher = $course->user()->find();


        //4. 构建数据
        $result = [];
        $result["classes"] = $class->visible($class_visible);
        $result["course"] = $course->visible($course_visible);
        $result["teacher"] = $teacher->visible($teacher_visible);

        //5. 返回信息
        return $this->build($result);
    }

    public function joinClass()
    {
        //0. 定义可显示字段
        $class_visible = ["id", "title", "code", "describ"];
        $course_visible = ["id", "title", "describ"];
        $teacher_visible = ["id", "username", "email", "avatar"];

        //1. 获取加课码、USER ID
        $code = Request::route('joinCode');
        $userId = request()->uid;

        //2. 获取课程、校验课程
        //- 判断课程是否存在
        $class = ClassesModel::where('code', $code)->find();
        if (!$class) {
            return $this->build(NULL, "课程不存在", 204)->code(204);
        }

        //- 判断该用户是否加入自己的课程
        $user = $class->course()->value('user_id');
        if ($userId === $user) {
            return $this->build(NULL, "不能加入自己教的课程", 400)->code(400);
        }

        //3. 加入课程
        $write_field = ["user_id", "classes_id"];    //定义写入字段
        $visible_field = ["user_id", "classes_id", "create_time"];  //定义可见字段

        //- 获取ID
        $classesId = $class["id"];

        //- 写入数据库
        try {
            MemberModel::create(["user_id" => $userId, "classes_id" => $classesId], $write_field)->visible($visible_field);
        } catch (\Exception $e) {
            $errCode = $e->getCode();
            switch ($errCode) {
                case 10501: //10501：主键重复
                    return $this->build(Null, "已加入过该课程", 400)->code(400);
            }
            return $this->build(Null, "未知错误", 500)->code(500);
        }

        //4. 构造响应信息
        $class = $class->visible($class_visible);
        $course = $class->course()->find()->visible($course_visible);
        $teacher = UserModel::find($course["user_id"])->visible($teacher_visible);

        $result = [];
        $result["classes"] = $class; //班级ID
        $result["course"]  = $course;   //课程标题
        $result["teacher"] = $teacher;   //课程标题

        //5. 返回数据
        return $this->build($result);
    }

    public function delMember()
    {

        //0. 定义可接受字段
        $receive_field = ['class_id','user_id'];

        //1. 获取用户ID、班级ID、用户ID
        $curUserId = request()->uid;
        $data = Request::only($receive_field);
        $delUserId = (int)$data["user_id"];
        $classId = (int)$data["class_id"];

        //2. 判断权限
        //TODO 写异常处理
        //- 判断是否有是该课程的教师
        $class = ClassesModel::find($classId);
        if(!$class) {
            return $this->build(NULL, "课程不存在", 404)->code(404);
        }

        $teacherId = $class->course()->field('user_id')->find();
        if ($teacherId["user_id"] !== $curUserId && $delUserId !== $curUserId) { //如果不是删除自己也不是老师
            return $this->build(NULL, "没有操作权限", 403)->code(403);
        }


        //- 判断成员是否存在
        $member = MemberModel::where(["user_id" => $delUserId,"classes_id" => $classId])->find();
        if(!$member) {
            return $this->build(NULL,"成员不存在")->code(404);
        }

        //3. 执行删除操作
        $member->delete();

        //4. 返回报文
        return $this->build($data);

    }

    public function getMember()
    {
        //0. 定义可见字段
        $visible_field = ['id', 'username','number','avatar', 'class','nickname', "create_time"];

        //1. 获取班级ID
        $classId = Request::route('class_id');

        //2. 获取当前用户ID
        $user_id = request()->uid;

        //TODO: 判断是否存在班级
        //3. 获取班级信息
        $class = ClassesModel::find($classId);
        if(!$class) {
            return $this->build(NULL, "课程不存在", 404)->code(404);
        }

        //4. 判断是否为课程创建者
        $curUser = $class->course()->field('user_id')->find();
        if ($curUser["user_id"] !== $user_id) {
            return $this->build(NULL, "没有操作权限", 403)->code(403);
        }

        //4. 检索数据
        $users = $class->member()->field('user_id')->select();
        $idArray = [];
        foreach($users as $key => $velue) {
            $idArray[$key] = $velue["user_id"];
        }
        $members = UserModel::whereIn('id',$idArray)->visible($visible_field)->select();
        
        return $this->build($members);
    }
}
