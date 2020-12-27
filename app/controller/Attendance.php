<?php

namespace app\controller;

use think\exception\ValidateException;
use app\validate\Attendance as AttendanceVerify;

use think\facade\Request;

use app\model\Attendance as AttendanceModel;
use app\model\AttendanceLog as AttendanceLogModel;
use app\model\Course as CourseModel;
use app\model\Classes as ClassesModel;
use app\controller\Base;

include "Event.php";
class Attendance extends Base
{
    public function index()
    {
        echo "这里是考勤模块";
    }
    //创建考勤
    public function createAttendance()
    {
        //0. 定义字段

        //1. 获取用户ID、传入数据
        $curUser = request()->uid;

        //2. 创建考勤
        // - 注意写入考勤人数（就是班级人数）
        // - 为班级的每一个人都创建考勤记录
        //      - 数字考勤：默认全部旷课
        //         0 旷课 - 默认
        //         1 出勤
        //         2 迟到
        //         3 请假
        //         4 事假
        //         5 病假
        //         6 公假
        //         7 早退

        //      - 传统考勤：默认全部出勤

        //3. 返回考勤

        //4. 返回信息

    }
    //删除考勤
    public function deleteAttendance( )
    {
        //0. 定义字段

        //1. 获取用户ID、传入数据
        $curUser = request()->uid;
        $AttendanceId = Request::route("attendance_id");
        
        //2. 判断相关数据是否存在：考勤项目
        $attendance = AttendanceModel::find($AttendanceId);
        // $attendance = 1;
        if (!$attendance) {
            return $this->build(NULL, "无此考勤信息", 404)->code(404);
        }
        //3. 判断权限：是否为老师
        $teacherId = ($attendance->classes->course)["user_id"];
        if ($teacherId !== $curUser) {
            return $this->build(null, "没有权限", 403)->code(403);
        }

        //4. 删除考勤
        // $AttendanceId = 20;
        return delete_Attendance($AttendanceId)?$this->build($AttendanceId,"删除成功"):$this->build($AttendanceId,"删除失败已回滚数据")->code(500);
        // return $this->build();
        // var_dump($attendance->classes->course);
        // return $teacherId . "-" .$curUser;
        // - 同时删除考勤记录表的相关记录

        //5. 返回信息
    }
    //编辑考勤
    public function updateAttendance()
    {
        // return "aga";
        //0. 定义字段
        $receive_field = ['title', 'describ','active'];  //接收字段
        $visible_field = ['id', 'active', 'title', 'type', 'date','sign','amount'];  //输出字段
        $write_field = ['title', 'describ','active']; //写入字段
        $hidden_field = ['update_time', 'delete_time'];  //隐藏字段  

        //1. 获取用户ID、传入数据
        $curUser = request()->uid;
        $AttendanceId = Request::route("attendance_id");

        //2. 判断相关数据是否存在：考勤项目
        $attendance = AttendanceModel::find($AttendanceId);
        if (!$attendance) {
            return $this->build(NULL, "无此考勤信息", 404)->code(404);
        }
        //3. 判断权限：是否为老师
        $teacherId = ($attendance->classes->course)["user_id"];
        if ($teacherId !== $curUser) {
            return $this->build(null, "没有权限", 403)->code(403);
        }
        //4. 更新数据
        $newData = Request::only($receive_field, 'post');

        try {
            validate(AttendanceVerify::class)->batch(true)->scene('updateAttendance')->check($newData);
        } catch (ValidateException $e) {
            return $this->build($e->getError(), "参数错误")->code(400);
        }
        //5. 返回新数据
        $attendance->save($newData);
        
        // hasCourse(56);
        return $this->build($attendance->hidden($hidden_field));
    }

    //TODO - 学生和老师身份分离为不同的API
    //学生：获取考勤码输入考勤码后依据课程开课时间设置状态
    //老师：生成考勤码到当前课程下的班级的考勤中
    public function getAttendance()
    {
        
        //0. 定义可见字段

        //1. 获取用户ID

        //2. 判断权限并获取身份：老师或成员

        //是否以学生身份在某个班级里,是则返回该该用户所在的班级列表

        
        //是否以老师身份拥有该课程

        //3. 根据身份生成数据
        // - 老师身份的数据

        // - 成员身份的数据


        //4. 返回数据
    }

    //TODO - 学生和老师身份分离为不同的API
    //老师：获取当前课程下的班级下的学生成员考勤记录
    public function getClassAttendance()
    {
        $visible_field = ['id', 'active', 'title', 'type', 'create_time','sign_in_user','amount_user'];  //输出字段
        //1. 获取用户ID
        $curUser = request()->uid;
        // return "哈哈哈".$curUser;
        $class = $AttendanceId = Request::route("class_id");
        //2. 判断权限并获取身份：老师或成员

        //是否以学生身份在该班级里
        $studentId = StudentInClass($curUser,$class);
    
        //是否以老师身份拥有该课程
        $teacherId = IfChargeClass($curUser,$class);
        
        //权限判断

        $identity = $teacherId ? $teacherId:($studentId?$studentId:false);
        if(!$identity){
            return $this->build(NULL, "无权限", 404)->code(404);
        }

        //
        //3. 获取数据

        if($identity===$teacherId)
            $attendance = AttendanceModel::whereIn('classes_id',$class)->visible($visible_field)->select();
        else{
            $visible_field = ['attendance_id', 'status'];
            $attendance = AttendanceLogModel::where('user_id',$curUser)->visible($visible_field)->select();
        }
        //4. 返回数据
        return $this->build($attendance); 
    }

    // TODO 接入Redis进行考勤
    //学生登入考勤系统
    public function signIn()
    {
        //0. 定义字段

        //1. 获取用户ID、考勤码
        $curUser = request()->uid;
        echo "输入考勤码进行考勤";

        //2. 判断当前用户是否属于考勤所属的班级

        //3. 判断考勤是否进行中
        // - 是
        //      - 修改考勤记录表记录
        // - 否
        //      - 报错

        //3. 返回成功信息

    }
    //获取单条学生考勤信息
    public function getUserAttendance()
    {
        // return $this->build(123456);
        // var_dump('哈哈哈') ;
        //0. 定义字段
        $visible_field = ['attendance_id','user_id', 'status'];  //输出字段
        //1. 获取用户ID、传入数据
        $curUser = request()->uid;
        $AttendanceId = Request::route("attendance_id");
        $studentId = Request::route("user_id");
        var_dump('考勤ID:'.$AttendanceId);
        var_dump('已登录用户ID:'.$curUser);
        var_dump('将要修改考勤的用户ID:'.$studentId."用户以学生身份加入过的班级:");
        // var_dump ("该考勤ID下所属班级".Attendance_belong($AttendanceId));
        // var_dump(StudentHadAttend($studentId,$AttendanceId));
        //2. 判断权限：老师 或 成员自己
        if(!StudentHadAttend($studentId,$AttendanceId)){
            return $this->build(NULL, "该课程无此用户考勤信息", 404)->code(404);
        }
        if(!HasAttendance($curUser,$AttendanceId)){
            return $this->build(NULL, "用户无权获取此考勤信息", 404)->code(404);
        }
        //3. 获取数据
        $attendance = AttendanceLogModel::where([
            'user_id'       => $studentId,
            'attendance_id' => $AttendanceId
        ])->select();
        if(!$attendance) {
            return $this->build(NULL, "无考勤记录", 404)->code(404);
        }
        $members = AttendanceLogModel::where([
            'user_id'       => $studentId,
            'attendance_id' => $AttendanceId
        ])->visible($visible_field)->select();
        //4. 返回数据
        // return '哈哈哈';
        // return json($members."h哈哈哈");
        return $this->build($members->visible($visible_field));
    }
    //老师:更新单个学生的考勤记录
    public function updateUserAttendance()
    {
        // BUG 创建与修改时间不自动更新
        //0. 定义字段
        $receive_field = ['status'];  //接收字段
        $visible_field = ['attendance_id','user_id', 'status'];  //输出字段
        $write_field = ['status']; //写入字段  
        //1. 获取用户ID、传入数据
        $curUser = request()->uid;
        $AttendanceId = Request::route("attendance_id");
        $studentId = Request::route("user_id");
        //2. 判断权限：
        //已登录用户名下是否已有该班级
        // var_dump('所属班级:'.$classId);
        var_dump('考勤ID:'.$AttendanceId);
        var_dump('已登录用户ID:'.$curUser);
        var_dump('将要修改考勤的用户ID:'.$studentId);
        $attendanceLog = AttendanceLogModel::where([
            'attendance_id' =>  $AttendanceId,
            'user_id'       =>  $studentId
        ])->find();
        if (!$attendanceLog) {
            return $this->build(NULL, "无此考勤信息", 404)->code(404);
        }
        if (!in_array(Attendance_belong($AttendanceId),HasClass($curUser))) {
            return $this->build(null, "没有权限", 403)->code(403);
        }

        //3. 修改保存数据
        $newData = Request::only($receive_field, 'post');

        try {
            validate(AttendanceVerify::class)->batch(true)->scene('updateUserLog')->check($newData);
        } catch (ValidateException $e) {
            return $this->build($e->getError(), "参数错误")->code(400);
        }
        $attendanceLog->save($newData);
        // return json($attendanceLog);
        //4. 返回新数据
        return $this->build($attendanceLog->visible($visible_field));
    }
}
