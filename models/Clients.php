<?php

namespace xenice\auth\models;

use xenice\auth\Model;

class Clients extends Model
{
    protected $table = 'xenice_auth_clients';
    
    public $client = '';

    protected $fields = [
        'id'=>['type'=>'bigint','range'=>'20','primary'=>true,'unique'=>true,'auto'=>true],
        'key'=>['type'=>'varchar','range'=>'200'], 
        'value'=>['type'=>'varchar','range'=>'200'], 
        'client'=>['type'=>'varchar','range'=>'200'],
        'create_time'=>['type'=>'TIMESTAMP','default'=>'CURRENT_TIMESTAMP'], // 创建时间
        'update_time'=>['type'=>'TIMESTAMP','value'=>'0000-00-00 00:00:00'],
    ];
    
    public function __construct($client = '')
    {
        if(!$client){
            $client = md5( uniqid ( mt_rand (), true ));
        }
        $this->client = $client;
        
        parent::__construct();
    }

    public function has($key)
    {
        $row = $this->where('key',$key)->and('client', $this->client)->first();
        if($row){
            return true;
        }
        return false;
    }
    
    public function get($key, $minutes = 0)
    {
        if($minutes){
            $time = time()-$minutes*60;
            $row = $this->where('key',$key)->and('client', $this->client)->and('update_time','>',$time)->first();
        }
        else{
            $row = $this->where('key',$key)->and('client', $this->client)->first();
        }
        if(!empty($row)){
            return $row['value'];
        }
    }
    
    public function set($key, $value)
    {
        if($this->has($key)){
            return $this->where('key',$key)->update(['value'=>$value,'update_time'=>date('Y-m-d H:i:s', time())]);
        }
        else{
            $data = [
                'key'=>$key,
                'value'=>$value,
                'client'=>$this->client,
                'update_time'=>date('Y-m-d H:i:s', time()),
            ];
            return $this->insert($data);
            
        }
    }

    public function del($key)
    {
        return $this->where('key',$key)->and('client', $this->client)->delete();
    }
    
}