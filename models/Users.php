<?php

namespace xenice\auth\models;

use xenice\auth\Model;

class Users extends Model
{
    protected $table = 'xenice_auth_users';
    
    protected $fields = [
        'id'=>['type'=>'bigint','range'=>'20','primary'=>true,'unique'=>true,'auto'=>true],
        'user_id'=>['type'=>'bigint','range'=>'20'],
        'token'=>['type'=>'varchar','range'=>'500'], 
        'register_time'=>['type'=>'TIMESTAMP','default'=>'CURRENT_TIMESTAMP'], // 注册时间
        'login_time'=>['type'=>'TIMESTAMP','default'=>'NULL'], // 上次登录时间
        'last_login_way'=>['type'=>'varchar','range'=>'100'], // 登录方式
        'wechat'=>['type'=>'varchar','range'=>'100'],
        'wechat_miniapp'=>['type'=>'varchar','range'=>'100'],
        'qq'=>['type'=>'varchar','range'=>'100'], 
        'weibo'=>['type'=>'varchar','range'=>'100'], 
        'wechat_avatar'=>['type'=>'varchar','range'=>'200'],
        'wechat_miniapp_avatar'=>['type'=>'varchar','range'=>'200'],
        'qq_avatar'=>['type'=>'varchar','range'=>'200'], 
        'weibo_avatar'=>['type'=>'varchar','range'=>'200'], 
        'wechat_name'=>['type'=>'varchar','range'=>'100'],
        'wechat_miniapp_name'=>['type'=>'varchar','range'=>'100'],
        'qq_name'=>['type'=>'varchar','range'=>'100'], 
        'weibo_name'=>['type'=>'varchar','range'=>'100'],
    ];
    
    public function __construct()
    {
        parent::__construct();
    }

    public function add($username, $password, $email, $sid = 0)
    {
        $userdata = [
            'user_login' =>  $username,
            'user_pass'  =>  $password, // When creating an user, `user_pass` is expected.
            'user_email'   => $email,
        ];
        
        $user_id = wp_insert_user( $userdata ) ;
        
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
            /*
        	$error_code = array_key_first( $user_id->errors );
        	$error_message = $user_id->errors[$error_code][0];*/
        }

        $data=[
            'token' => md5( uniqid ( mt_rand (), true )),
            'last_login_way'=>'h5',
            'user_id'=>$user_id,
        ];
        
        return $this->insert($data);

    }
    
    /*
    public function login($username, $password)
    {
        $row = $this->where('name', $username)->and('password', $password)->first();
        if($row){
            $data=[
                'last_login_way'=>'h5',
                'login_time'=>date('Y-m-d H:i:s', time())
            ];
            $this->where('id', $row['id'])->and('user_id', Theme::get('current_user_id'))->update($data);
            return $row;
        }
    }*/
    
    public function addSocial($user_id, $type, $openid, $nickname, $avatar)
    {

        $data=[
            'token' => md5( uniqid ( mt_rand (), true )),
            'last_login_way'=>$type,
            $type=>$openid,
            $type.'_avatar'=>$avatar,
            $type.'_name'=>$nickname,
            'user_id'=>$user_id,
        ];
        
        return $this->insert($data);

    }
    
    public function loginSocial($type, $openid)
    {
        $row = $this->where($type, $openid)->first();
        if($row){
            $data=[
                'last_login_way'=>$type,
                'login_time'=>date('Y-m-d H:i:s', time())
            ];
            
            $this->where('id', $row['id'])->update($data);
            return $row;
        }
    }
    
    public function getToken($customer_id)
    {

        $row = $this->where('id',$customer_id)->and('user_id', Theme::get('current_user_id'))->first();
	    
	    if(!empty($row['token'])){
	        return $row['token'];
	    }
    }
    
    
}