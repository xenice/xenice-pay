<?php

namespace xenice\auth\models;

use xenice\auth\Model;

class Usermeta extends Model
{
    protected $table = 'usermeta';

    protected $fields = [
        'umeta_id'=>['type'=>'bigint','range'=>'20','primary'=>true,'unique'=>true,'auto'=>true],
        'user_id'=>['type'=>'bigint','range'=>'20'],
        'meta_key'=>['type'=>'varchar','range'=>'255'],
        'meta_value'=>['type'=>'longtext'],
    ];
    
    public function __construct()
    {
        parent::__construct();
    }
    
    public function register($user)
    {
        
        $user_id = $user->ID;
        $static_url = plugins_url('static/', __DIR__);
        
        $data = [
            'id'=>$user_id,
            'token'=>md5( uniqid ( mt_rand (), true )),
            'avatar'=> $static_url . 'images/avatar.png',
            'nickname'=>$user->display_name,
        ];
        
        update_user_meta($user_id, 'xenice_token', $data['token']);
        update_user_meta($user_id, 'xenice_token_expire', time()+(3600*24));
        update_user_meta($user_id, 'xenice_avatar', $data['avatar']);
        
        return $data;
    }
    
    public function login($user)
    {
        $user_id = $user->ID;
        $static_url = plugins_url('static/', __DIR__);
        $avatar = get_user_meta($user_id, 'xenice_avatar', true);
        
        if(empty($avatar)){
            $avatar = $static_url . 'images/avatar.png';
        }
        
        $data = [
            'id'=>$user_id,
            'token'=>md5( uniqid ( mt_rand (), true )),
            'avatar'=> $avatar,
            'nickname'=>$user->display_name,
        ];
        
        update_user_meta($user_id, 'xenice_token', $data['token']);
        update_user_meta($user_id, 'xenice_token_expire', time()+(3600*24));
        return $data;
    }
    
    /*
    public function logout($user_id)
    {
        update_user_meta($user_id, 'xenice_token', md5( uniqid ( mt_rand (), true )));
        update_user_meta($user_id, 'xenice_token_expire', 0);
    }*/
    
    public function verify($token)
    {
        if(empty($token)) return;
        $row = $this->where('meta_key', 'xenice_token')->and('meta_value', $token)->first();
        if(empty($row)) return;
        $time = get_user_meta($row['user_id'], 'xenice_token_expire', true);
        if($time>time()){
            return $row['user_id'];
        }
    }
    
    /*
    public function refreshToken($user_id)
    {
        update_user_meta($user_id, 'xenice_token', md5( uniqid ( mt_rand (), true )));
        update_user_meta($user_id, 'xenice_token_expire', time()+(3600*24));
    }*/
    
    
}