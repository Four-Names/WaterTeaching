<?php

namespace app\controller;

use think\exception\ValidateException;
use app\validate\Attendance as AttendanceVerify;

use think\facade\Request;
use think\facade\Db;

use app\model\Attendance as AttendanceModel;
use app\model\Classes as ClassesModel;
use app\model\AttendanceLog as AttendanceLogModel;

use app\model\Classes as ClassesModel;
use app\model\Member as MemberModel;
use app\model\User as UserModel;
use app\model\Course as CourseModel;

use app\controller\Base;
use think\db\Builder;

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
        $receive_field = ['title', 'describ', 'classes_id', 'type'];  //接收字段
        $visible_field = ['id', 'classes_id', 'title', 'active', 'amount_user', 'type', 'create_time'];  //输出字段
        $write_field = ['title', 'describ', 'classes_id', 'code', 'type', 'sign_in_user', 'amount_user' , "ip"]; //写入字段
        $write_field_log = ["attendance_id","user_id","status"];


        //1. 获取用户ID、传入数据
        $curUser = request()->uid;
        $attenData = Request::only($receive_field, 'post'); //获取提交信息

        //      - 提取具体数据
        $classId = $attenData["classes_id"];
        $attenType = $attenData["type"];
        
        //      - 获取成员列表
        $members = MemberModel::where("classes_id", $classId)->select(); //获取班级成员列表
        $membersCount = $members->count();

        //      - 统计并获取获取该次考勤人数
        $attenData["amount_user"] = $membersCount; //成员计数

        //      - 生成考勤码
        $code = $this->createCode();
        $attenData["code"] = $code;

        //      - 数据整形,去掉左右的空白字符
        if (array_key_exists('describ', $attenData)) $attenData["describ"] = trim($attenData["describ"]);
        if (array_key_exists('title', $attenData)) $attenData["title"] = trim($attenData["title"]);

        //      - 校验数据
        try {
            validate(AttendanceVerify::class)->batch(true)->scene('create')->check($attenData);
        } catch (ValidateException $e) {
            return $this->build($e->getError(), "参数错误")->code(400);
        }

        //      - 判断班级
        $class = ClassesModel::find($classId);
        if(!$class) {
            return $this->build(NULL, "没有该班级", 404)->code(404);
        }

        $teacherId = $class->course->value("user_id");

        //          TODO 注意INT类型能表示的上限
        if((int)$teacherId !== (int)$curUser) {
            return $this->build(NULL, "没有操作权限", 403)->code(403);
        }


        //2. 创建考勤
        //          TODO 添加事务处理
        Db::startTrans();//启动事务处理

        try {
            //3. 创建考勤记录
            $attendance = AttendanceModel::create($attenData, $write_field)->visible($visible_field); //写入attendance记录

       
        
            //      - 判断考勤类型
            //           - 0：数字考勤，默认全部旷课
            //                  0 旷课      - 默认
            //                  1 出勤
            //           - 1：传统考勤：默认全部出勤
            $status = 0;

            if((int)$attenType === 1) {
                $status = 1;
            }

            //      - 为班级的每一个人都创建考勤记录
            $attenId = $attendance["id"];
            $logs = [];

            foreach ($members as $key => $member) {
                $log = [
                    "attendance_id"     =>      $attenId,
                    "user_id"           =>      $member["user_id"],//使用value()会出现BUG
                    "status"            =>      $status,
                    "ip"                =>      Request::instance()->ip(),
                    "changes"           =>      1,
                ];
                $logs[$key] = $log;
            }

                //      - 写入数据库
                $AttendanceLog = new AttendanceLogModel;
                $AttendanceLog->saveAll($logs);
                Db::commit();//提交
            } catch (\Exception $th) {
            
                Db::rollback();//回滚数据
                return $this->build(NULL,"创建考勤失败")->code(500);
            }
        
        
        //TODO 限制写入字段

        //4. 返回考勤信息
        return $this->build($attendance, "创建成功");

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
        return delete_Attendance($AttendanceId)?$this->build($AttendanceId,"删除成功"):$this->build($AttendanceId,"删除失败已回滚数据")->code(500);


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


    public function getAttendance()
    {
        //. 定义可见字段
        $select_fields = ["id" , "title" , "active" , "type"  , "sign_in_user as sign" , "amount_user as amount" ,"create_time as date"];  
        //TODO WCH: 这里定义为数据库字段，这样的

        //. 获取用户ID
        $userId = request()->uid;
        $attenId = Request::route("attendance_id"); //获取考勤ID
        //然后
        $attendance = AttendanceModel::field($select_fields)->find($attenId);//获取考勤记录
        $atten = AttendanceModel::find($attenId);//获取考勤号
        if(!$atten) {
            return $this->build(NULL, "不存在该考勤", 204)->code(204);
        }

        //2. 判断权限并获取身份：老师、班内学生、其他成员
        if( CourseModel::where("user_id" , $userId)->find() )
        {
            $role = 1;//判断为老师
        }
        else if ( MemberModel::where("user_id" , $userId)->find() )
        {
            $role = 2;//判断为学生
        }else
        {
            //判断是否为其他用户
            return $this->build(NULL, "没有操作权限", 403)->code(403);//其他成员
        }
        
        //- 获取考勤记录列表
        $attendanceLogs = AttendanceLogModel::where("attendance_id", $attenId)->select();//获取参加该次考勤的用户信息

        //. 准备除了logs字段意外的其他数据

        //TODO　不用特意为学生准备数据了，学生没有logs字段OK
        $result = [];   // <----- 最终结果的容器，
        switch ($role) {
            //老师 - 在这里生成logs数据，不需要break，因为老师也要准备那些基础数据
            case 1: 
                $shapeLogs = [];
                foreach ($attendanceLogs as $key => $attendanceLog) {
                    // $oneLog =  ;
                    $user = UserModel::find($attendanceLog["user_id"]);
                    $log = [
                        "id"                =>          $user["id"],
                        "username"          =>          $user["username"],
                        "class"             =>          $attendance["classes_id"],
                        "date"              =>          $attendance["date"],//odk
                        "ip"                =>          $attendanceLog["ip"],
                        "status"            =>          $attendanceLog["status"],
                        "changes"           =>          $attendanceLog["changes"],
                        "avatar"            =>          null,
                        "number"            =>          null
                    ];
                    $shapeLogs[$key] = $log;
                }
                $attendance["logs"] = $shapeLogs;


                case 2: 
                    //- 学生身份的数据
                    
                    
                    $attenLog = AttendanceLogModel::where( [
                        "attendance_id"     =>          $attenId,
                        "user_id"           =>          $userId
                        ])->find();
                        
                        $attendance["status"] = $attenLog["status"];
                        $result = $attendance;

                break;

        }
        
        //4. 返回数据
        return $this->build($result, "查询成功");   //TODO <--------- 返回数据我改成了result
    }

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
        $userId = request()->uid;
        $code = Request::route("code");//获取输入的考勤码
        
        //获取考勤信息
        $attendance = AttendanceModel::where("code" , $code)->find();//匹配考勤码code的考勤记录
        $attenId = $attendance["id"];//当前考勤ID

        //当前用户的本次考勤记录
        $attenLog = AttendanceLogModel::where( [
            "attendance_id"     =>          $attenId,
            "user_id"           =>          $userId
            ])->find();
            
        
        //2. 判断当前用户是否属于考勤所属的班级
        $memberShip = MemberModel::where( [
            "user_id"           =>          $userId , 
            "classes_id"        =>          $attendance["classes_id"]
        ])->find();
        if (!$memberShip) {
            return $this->build(NULL, "当前用户不属于该班级！", 204)->code(204);
        }
        

        //3. 判断考勤是否进行中
        if($attendance["active"] == 1)
        {
            //判断是否重复签到
            if($attenLog["status"] == 0)
            {
            //- 是
            //     - 修改考勤记录表记录
            $attenLog["status"] = 1;//签到状态修改
            $attenLog["changes"] += 1;//修改考勤的次数
            $attenLog->save();

            //签到人数统计
            $attendance["sign_in_user"] = AttendanceLogModel::where("status" , 1)->count();
            $attendance->save();
            }
            else
            {
                return $this->build(NULL, "您已成功签到，无需重复签到", 403)->code(403);
            }

        }
        else{
            //- 否
            //     - 报错
            return $this->build(null, "考勤已结束", 400)->code(400);
        }
        
        $attendance ->save();
        
        //3. 返回成功信息
        return $this->build($attenLog, "签到成功");

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
