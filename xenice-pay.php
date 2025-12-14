<?php

/**
 * Plugin Name: Xenice Pay
 * Plugin URI: https://www.xenice.com
 * Description: Rest api pay
 * Version: 1.0.0
 * Author: Xenice
 * Author URI: https://www.xenice.com
 * Text Domain: xenice-pay
 * Domain Path: /languages
 */


namespace xenice\pay;

 /**
 * autoload class
 */
function __autoload($classname){
    $classname = str_replace('\\','/',$classname);
    $namespace = 'xenice/pay';
    if(strpos($classname, $namespace) === 0){
        $filename = str_replace($namespace, '', $classname);
        require  __DIR__ .  $filename . '.php';
    }
}




 /**
 * get option
 */
function get($name, $key='xenice_pay')
{
    
    static $option = [];
    if(!$option){
        $options = get_option($key)?:[];
        foreach($options as $o){
            $option = array_merge($option, $o);
        }
    }
    return $option[$name]??'';
}


 /**
 * set option
 */
function set($name, $value, $key='xenice_pay')
{
    $options = get_option($key)?:[];
    foreach($options as $id=>&$o){
        if(isset($o[$name])){
            $o[$name] = $value;
            update_option($key, $options);
            return;
        }
    }
}



function admin_footer()
{
    $msg = [
        'success' => __('Successfully copied to clipboard', 'xenice-pay'),
        'failed' => __('The browser does not support link clicks to copy to the clipboard', 'xenice-pay')
    ];
    echo <<<EOT
<script>

function xenice_pay_copy (obj) {
var text = obj.href;
var textArea = document.createElement("textarea");
  textArea.style.position = 'fixed';
  textArea.style.top = '0';
  textArea.style.left = '0';
  textArea.style.width = '2em';
  textArea.style.height = '2em';
  textArea.style.padding = '0';
  textArea.style.border = 'none';
  textArea.style.outline = 'none';
  textArea.style.boxShadow = 'none';
  textArea.style.background = 'transparent';
  textArea.value = text;
  document.body.appendChild(textArea);
  textArea.select();

  try {
    var successful = document.execCommand('copy');
    var msg = successful ? '{$msg['success']}' : '{$msg['failed']}';
   alert(msg);
  } catch (err) {
    alert('{$msg['failed']}');
  }

  document.body.removeChild(textArea);
}
</script>
EOT;
    
}



function register()
{
    $controllers = [
        'xenice\pay\controllers\PayWaysController',
        'xenice\pay\controllers\PayNotifyController',
    ];
    
    foreach($controllers as $class){
        $class = '\\' . $class;
        (new $class)->register_routes();
    }
}


/**
* auto execute when active this plugin
*/
register_activation_hook( __FILE__, function(){
    spl_autoload_register('xenice\pay\__autoload');
    (new Config)->active();
    
    //(new models\Clients)->create();
});


add_action( 'plugins_loaded', function(){
    spl_autoload_register('xenice\pay\__autoload');
    require __DIR__ . '/vendor/autoload.php';
    $plugin_name = basename(__DIR__);
    load_plugin_textdomain( $plugin_name, false , $plugin_name . '/languages/' );
    // add setting menus
    add_action( 'admin_menu', function(){
        add_options_page(__('Pay','xenice-pay'), __('Pay','xenice-pay'), 'manage_options', 'xenice-pay', function(){
            //var_dump(get('enable_title'));
            (new Config)->show();

        });
        
    });
    
    // Add setting button
    $plugin = plugin_basename (__FILE__);
    add_filter("plugin_action_links_$plugin" , function($links)use($plugin_name){
        $settings_link = '<a href="options-general.php?page='.$plugin_name.'">' . __( 'Settings', 'xenice-pay') . '</a>' ;
        array_push($links , $settings_link);
        return $links;
    });
    
    //new api\TestApi;
    //new api\AuthApi;
    
    add_action('admin_footer', 'xenice\pay\admin_footer');
    add_action('rest_api_init', 'xenice\pay\register', 30);
    //add_filter( 'determine_current_user', 'xenice\auth\verify', 100);
    
});






