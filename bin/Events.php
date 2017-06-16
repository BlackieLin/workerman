<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

/**
 * 聊天主逻辑
 * 主要是处理 onMessage onClose 
 */
use \GatewayWorker\Lib\Gateway;
use \GatewayWorker\Lib\Db;

class Events
{
   /**
    * 有消息时
    * @param int $client_id
    * @param mixed $message
    */
   public static function onMessage($client_id, $message)
   {
        // debug
        //echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:".json_encode($_SESSION)." onMessage:".$message."\n";
        
        // 客户端传递的是json数据
        $message_data = json_decode($message, true);
        if(!$message_data)
        {
            return ;
        }
        
        // 根据类型执行不同的业务
        switch($message_data['type'])
        {
            // 客户端回应服务端的心跳
            case 'pong':
                return;
            // 客户端登录 message格式: {type:login, name:xx, room_id:1} ，添加到客户端，广播给所有客户端xx进入聊天室
            case 'login':
                // 判断是否有房间号
                if(!isset($message_data['chat_id']))
                {
                    throw new \Exception("city not set. client_ip:{$_SERVER['REMOTE_ADDR']} \$message:$message");
                }
                // 把房间号昵称放到session中
                $room_id = htmlspecialchars($message_data['chat_id']);
                $client_name = htmlspecialchars($message_data['client_name']);
                $_SESSION['room_id'] = $room_id;
                $_SESSION['client_name'] = $client_name;
              
                // 获取房间内所有用户列表 
                $clients_list = Gateway::getClientSessionsByGroup($room_id);
                foreach($clients_list as $tmp_client_id=>$item)
                {
                    $clients_list[$tmp_client_id] = $item['client_name'];
                }
                $clients_list[$client_id] = $client_name;
                
                // 转播给当前房间的所有客户端，xx进入聊天室 message {type:login, client_id:xx, name:xx} 
                $new_message = array('type'=>$message_data['type'], 'client_id'=>$client_id, 'client_name'=>htmlspecialchars($client_name), 'time'=>date('Y-m-d H:i:s'));
                Gateway::sendToGroup($room_id, json_encode($new_message));
                Gateway::joinGroup($client_id, $room_id);
               
                // 给当前用户发送用户列表 
                $new_message['client_list'] = $clients_list;
                Gateway::sendToCurrentClient(json_encode($new_message));
                return;
                
            // 客户端发言 message: {type:say, to_client_id:xx, content:xx}
            case 'say':
                // 非法请求
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];
                
				//系统回复
				$req_msg=$reply_msg="";
				$req_msg=nl2br(htmlspecialchars($message_data['content']));
				
				//还原session 为gzh\citycode
				$citycode=$gzh="";
				$chatArr = array();
				$chatArr = explode("_",$room_id);
				$citycode=$chatArr[0];
				array_shift($chatArr);
				$gzh=implode($chatArr,"_");
				
				//链接数据库
				$kw_id_arr=array();
				Db::instance("blackie_config");//链接
				$kw_id_arr=Db::row("blackie_config",$sql);
				if(!empty($kw_id_arr)&&isset($kw_id_arr['group_id'])){
					$reply_arr=array();
					$reply_arr=Db::row("blackie_config",$sql);
					if(!empty($reply_arr)&&isset($reply_arr['content'])){
						$reply_msg=$reply_arr['content'];
						//内容替换链接
						$str='';
						$array=array();
						$str=preg_replace(array('/[\x{4e00}-\x{9fa5}]/u','/[，。、！？：；﹑•… ）（]/u'),' 本',$reply_msg);
						preg_match_all("/http(.*)[本|\n| ]/U",$str." 本",$array);
						if(!empty($array)&&is_array($array[0])&&!empty($array[0])){
							foreach($array[0] as $key=>$val){
								$val=trim($val);
								$reply_msg=str_replace($val,'<a href="'.$val.'" target="_blank">'.$val.'</a>',$reply_msg);
							}
						}
					}else{
						$reply_msg="抱歉，不能回答您的问题！";	
					}
				}else{
					$reply_msg="抱歉，不能回答您的问题！";
				}
				$new_message = array(
                    'type'=>'say', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'content'=>$reply_msg,
                    'time'=>date('Y-m-d H:i:s'),
                );
				return Gateway::sendToCurrentClient(json_encode($new_message));
				exit;
                // 私聊
                if($message_data['to_client_id'] != 'all')
                {
                    $new_message = array(
                        'type'=>'say',
                        'from_client_id'=>$client_id, 
                        'from_client_name' =>$client_name,
                        'to_client_id'=>$message_data['to_client_id'],
                        'content'=>"<b>对你说: </b>".nl2br(htmlspecialchars($message_data['content'])),
                        'time'=>date('Y-m-d H:i:s'),
                    );
                    Gateway::sendToClient($message_data['to_client_id'], json_encode($new_message));
                    $new_message['content'] = "<b>你对".htmlspecialchars($message_data['to_client_name'])."说: </b>".nl2br(htmlspecialchars($message_data['content']));
                    return Gateway::sendToCurrentClient(json_encode($new_message));
                }
                //群聊
                $new_message = array(
                    'type'=>'say', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'content'=>nl2br(htmlspecialchars($message_data['content'])),
                    'time'=>date('Y-m-d H:i:s'),
                );
                return Gateway::sendToGroup($room_id ,json_encode($new_message));
			// 客户端发言 message: {type:say, to_client_id:xx, content:xx}
            case 'note':
                // 非法请求
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];
                
				//系统回复
				$req_msg=$reply_msg="";
				$req_msg=nl2br(htmlspecialchars($message_data['content']));
				
				//还原session 为gzh\citycode
				$citycode=$gzh="";
				$chatArr = array();
				$chatArr = explode("_",$room_id);
				$citycode=$chatArr[0];
				array_shift($chatArr);
				$gzh=implode($chatArr,"_");
				
				//链接数据库
				$kw_id_arr=array();
				Db::instance("blackie_config");//链接
				$kw_id_arr=Db::fetchAll("blackie_config",$sql);
				if(!empty($kw_id_arr)){
					$reply_msg = json_encode($kw_id_arr);
				}
				$new_message = array(
                    'type'=>'note', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'content'=>$reply_msg,
                    'time'=>date('Y-m-d H:i:s'),
                );
				return Gateway::sendToCurrentClient(json_encode($new_message));
				exit;
			//自答模式
			case 'answer':
                // 非法请求
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];
                
				//系统回复
				$req_msg=$reply_msg="";
				$req_msg=nl2br(htmlspecialchars($message_data['content']));
				
				//还原session 为gzh\citycode
				$citycode=$gzh="";
				$chatArr = array();
				$chatArr = explode("_",$room_id);
				$citycode=$chatArr[0];
				array_shift($chatArr);
				$gzh=implode($chatArr,"_");
				
				//链接数据库
				$kw_id_arr=array();
				Db::instance("blackie_config");//链接
				$kw_id_arr=Db::fetchAll("blackie_config",$sql);
				print_r($kw_id_arr);
				if(!empty($kw_id_arr)){
					$reply_msg = json_encode($kw_id_arr);
				}
				$new_message = array(
                    'type'=>'answer', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'content'=>$reply_msg,
                    'time'=>date('Y-m-d H:i:s'),
                );
				return Gateway::sendToCurrentClient(json_encode($new_message));
				exit;
        }
   }
   
   /**
    * 当客户端断开连接时
    * @param integer $client_id 客户端id
    */
   public static function onClose($client_id)
   {
       // debug
       //echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";
       
       // 从房间的客户端列表中删除
       if(isset($_SESSION['room_id']))
       {
           $room_id = $_SESSION['room_id'];
           $new_message = array('type'=>'logout', 'from_client_id'=>$client_id, 'from_client_name'=>$_SESSION['client_name'], 'time'=>date('Y-m-d H:i:s'));
           Gateway::sendToGroup($room_id, json_encode($new_message));
       }
   }
  
}
