<?php

namespace xenice\auth\models;

use xenice\auth\Model;

class Domains extends Model
{
    protected $table = 'xenice_auth_domains';
    
    protected $fields = [
        'id'=>['type'=>'bigint','range'=>'20','primary'=>true,'unique'=>true,'auto'=>true],
        'key'=>['type'=>'varchar','range'=>'100'], 
        'value'=>['type'=>'varchar','range'=>'100'], 
        'domain'=>['type'=>'varchar','range'=>'100'], 
        'time'=>['type'=>'TIMESTAMP','value'=>'0000-00-00 00:00:00'],
    ];
    
    public function __construct()
    {
        parent::__construct();
    }

    public function has($key, $url = '')
    {
        $row = $this->where('key',$key)->and('domain', $this->domain($url))->first();
        if($row){
            return true;
        }
        return false;
    }
    
    public function get($key, $url = '')
    {
        $row = $this->where('key',$key)->and('domain', $this->domain($url))->first();
        if(!empty($row)){
            return $row['value'];
        }
    }
    
    public function set($key, $value, $url = '')
    {
        if($this->has($key, $url)){
            return $this->where('key',$key)->update(['value'=>$value,'time'=>date('Y-m-d H:i:s', time())]);
            
        }
        else{
            $data = [
                'key'=>$key,
                'value'=>$value,
                'domain'=>$this->domain($url),
                'time'=>date('Y-m-d H:i:s', time()),
            ];
            return $this->insert($data);
            
        }
    }

    public function del($key, $url = '')
    {
        return $this->where('key',$key)->and('domain', $this->domain($url))->delete();
    }
    
    public function domain($url = '')
    {
        if($url == ''){
            $url = $_SERVER['HTTP_REFERER'];
        }
        $arr = parse_url($url);
        return $arr['host'];
    }
    
    public function home($url = '')
    {
        if($url == ''){
            $url = $_SERVER['HTTP_REFERER'];
        }
        $arr = parse_url($url);
        
        return $arr['scheme'] . '://' . $arr['host'];
    }
    
}