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