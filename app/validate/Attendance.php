<?php

declare(strict_types=1);

namespace app\validate;

use think\Validate;

class Attendance extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名'	=>	['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'title|考勤标题'               =>      'require|max:32',
        'describ|描述'                 =>      'max:100',
        'class_id|课程ID'              =>      'require',
        'status|考勤状态'              =>       'between:0,7'
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名'	=>	'错误信息'
     *
     * @var array
     */
    protected $message = [
        'title.require'      =>      '考勤标题不得为空',
        'title.max'          =>      '考勤标题不得超过32个字符',
        'describ.max'        =>      '考勤描述不得超过32个字符',
        "status.between"         =>      '考勤范围错误',
    ];

    protected $scene = [
        'create'                   =>      ['title', 'describ', 'class_id'],
        'updateAttendance'        =>      ['title', 'describ'],
        'updateUserLog'           =>      ['status',]
    ];
}
