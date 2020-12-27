<?php
namespace app\controller;
use think\exception\ValidateException;
use think\facade\Validate;
use app\model\User as UserModel;
use app\model\Classes as ClassesModel;
use app\model\Member as MemberModel;
use app\model\Course as CourseModel;
    function delete_class_event($classId){
        ClassesModel::startTrans();//启动事务处理
        MemberModel::startTrans();//启动事务处理
        $hasDelClasses = ClassesModel::where('id',$classId)->delete();
        $hasDelMember = MemberModel::where('classes_id',$classId)->delete();
        $memberIsEmpty = MemberModel::where('classes_id',$classId)->select()->isEmpty();
        if($hasDelClasses) {
            try{
                ClassesModel::commit();
            }
            catch (\Exception $e) {
                ClassesModel::rollback();
                return false;
            }
        }
        if($memberIsEmpty){
            try{
                MemberModel::commit();
                return true;
            }
            catch (\Exception $e) {
                MemberModel::rollback();
                ClassesModel::rollback();
                return false;
            }
        }
        else{
            return true;
        }

    }
    ////判断该用户是否班级创建者
    //思路：在该用户创建的班级里面寻找对应classid
    function IfChargeClass($user,$class){
        $class_list = HasClass($user);
        $class = (int)$class;
        return in_array($class,$class_list[0]);
    }
    //判断是否为学生,是则返回该生加过的班级列表,否则返回false
    //思路：返回member下对应id的课程列表
    function StudentHasClass($user){
        $class_list = MemberModel::where("user_id",$user)->column('classes_id');
        return empty($class_list)?false:$class_list;
    }
    //判断该用户是否以学生身份加入某班级
    //思路：返回该用户已加入班级的id集合
    function StudentInClass($user,$class){
        return MemberModel::where([
            'user_id'      =>      $user,
            'classes_id'   =>      $class
        ])->select()?true:false;
    }
    //判断该用户是否以老师身份开有课程,有则返回课程列表否则返回false
    function HasCourse($user){
        $course_list  = CourseModel::where('user_id',$user)->column('id');
        return empty($course_list)?false:array($course_list);
        // array($course_list);
    }
    //判断该用户是否以学生身份参加过某考勤
    function StudentHadAttend($user,$attendance){
        return empty(
            AttendanceLogModel::where([
            'user_id'           => $user,
            'attendance_id'     => $attendance
        ])->column('user_id')
        )?false:true;
    }

    
    //获取该考勤id下所属的班级
    function Attendance_belong($AttendanceId){
        return AttendanceModel::where('id',$AttendanceId)->value('classes_id');
    }
    
    //获取班级下的

    //判断该次考勤的班级是否所属已登录用户
    // function IfChargeAttendance($user,$class,$attendance){
    //     $classes_id = IfChargeClass($user,$class);
    //     if(!empty($classes_id))
    //         return in_array(Attendance_belong($attendance),$classes_id);
    //     return false;
    // }
    //返回该用户下创建的所有班级id
    function HasClass($user){
        $course_list = HasCourse($user);
        // return $course_list;
        if($course_list){
            $classes_list = ClassesModel::whereIn('course_id',$course_list[0])->column('id');
            return empty($classes_list)?false:array($classes_list);
        }
        return false;
    }
    //判断该考勤是否属于已登录用户
    function HasAttendance($user,$attendance){
        $class = Attendance_belong($attendance);
        $class_list = HasClass($user);
        return in_array($class,$class_list)?true:false;
    }

    



//删除考勤
//思路：
//传入AttendanceId并删除相关的考勤记录
//删除Attendance表下的数据以及AttendanceLog下所有参与考勤的学生的数据(需要判断该AttendanceLog下是否已有学生与考勤，有则删除否则返回true)
//首先执行删除Attendance下的考勤记录，而后将所有参与过该次考勤下的学生考勤记录给删除

    function delete_Attendance($attendanceId){
        AttendanceModel::startTrans();//启动事务处理
        AttendanceLogModel::startTrans();//启动事务处理
        $hasDelAttendance = AttendanceModel::where('id',$attendanceId)->delete();
        $hasDelLog = AttendanceLogModel::where('attendance_id',$attendanceId)->delete();
        $LogIsEmpty = AttendanceLogModel::where('attendance_id',$attendanceId)->select()->isEmpty();
        if($hasDelAttendance) {
            try{
                AttendanceModel::commit();
            }
            catch (\Exception $e) {
                AttendanceModel::rollback();
                return false;
            }
        }
        if($LogIsEmpty){
            try{
                AttendanceLogModel::commit();
                return true;
            }
            catch (\Exception $e) {
                AttendanceLogModel::rollback();
                AttendanceModel::rollback();
                return false;
            }
        }
        else{
            return true;
        }
    }