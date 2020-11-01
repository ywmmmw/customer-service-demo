<?php
declare(strict_types=1);

namespace App\Services;

use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;

class ChatService
{
	protected $redis;

	public function __construct()
    {
        $container = ApplicationContext::getContainer();

        $this->redis = $container->get(Redis::class);
    }

    public function parseMessage($data)
    {
    	try {
            $msg = json_decode($data, true);
        } catch (Exception $e) {
            $msg = [
                "event" => "error",
                "body" => "Recv: " . $data
            ];
        }
        
        if (!isset($msg["event"])){
            $msg = [
                "event" => "error",
                "body" => "Recv: " . $data
            ];
        }

        return $msg;
    }

    public function onClose($server, $fd)
    {
    	$username = $this->redis->get("username:{$fd}");
        if ($username) {
            $this->redis->del("username:{$fd}");
            $this->redis->del("fd:{$username}");
            $this->redis->lrem("pendingworkers", $username, 0);
        }
    }

    public function onOpen($server, $fd)
    {
    	$resp = [
            "event" => "init",
            "success"=> true
        ];
        $server->push($fd, json_encode($resp));
    }

    public function onError($server, $fd, $msg)
    {
    	$server->push($fd, json_encode($msg));
    }

    public function onLogin($server, $fd, $msg)
    {

    	$user_type = $msg["user_type"] ?? "worker";
        $username = $msg["username"] ?? "worker" . $fd;


        # 记录某个用户的客户端编号，有值表示在线
        $this->redis->set("fd:{$username}", $fd, 125);
        $this->redis->set("username:{$fd}", $username, 125);

        if ($user_type == "worker"){
            
            if (empty($username)){
                $username = "worker" . $fd;
            }

            # 空闲的客服进行排队等候分配客户进行工作
            $this->redis->lpush("pendingworkers", $username);

        }
        # 登录提示
        $resp = [
            "event" => "login",
            "user_type" => $user_type,
            "username"=> $username,
            "success"=> true
        ];
        $server->push($fd, json_encode($resp));

        if ($user_type == "customer") {

        	if (empty($username)){
                $username = "customer" . $fd;
            }

            # 用户登录分配空闲的客服进行工作
            $worker = $this->redis->rpop("pendingworkers");

            if(empty($worker)) return;

            $worker_fd = intval(
                $this->redis->get("fd:{$worker}")
            );

            if($worker_fd){
            	// 告知顾客侧沟通的客服是谁
                $resp3 = [
                    "event" => "set_chat_to",
                    "to" => $worker,
                    "direct_type" => "customer",
                    "success" => true
                ];
                $server->push($fd, json_encode($resp3));
                // 告知客服侧沟通客户是谁
                $resp4 = [
                    "event" => "set_chat_to",
                    "to" => $username,
                    "direct_type" => "worker",
                    "success" => true
                ];
                $server->push($worker_fd, json_encode($resp4));
            }else{
            	// 暂无空闲的客服，向用户提示
                $resp3 = [
                    "event" => "set_chat_to",
                    "to" => null,
                    "direct_type" => "customer",
                    "success" => true
                ];
                $server->push($fd, json_encode($resp3));
            }
            
        }
    }

    public function onMsg($server, $fd, $msg)
    {
    	$from = $msg["from"];
        $to = $msg["to"];
        $to_fd = intval($this->redis->get("fd:{$to}"));
        $resp = [
            "event" => "msg",
            "from" => $from,
            "to" => $to,
            "text" => $msg["text"] ?? "No Text",
            "success" => true
        ];
        $server->push($to_fd, json_encode($resp));
    }

    public function onHeartbeat($server, $fd, $msg)
    {
    	$user_type = $msg["user_type"];
        $username = $msg["username"];
        // 刷新用户名和客户端的关联关系过期时间
        $this->redis->set("fd:{$username}", $fd, ['xx', 'ex' => 125]);
        $this->redis->set("username:{$fd}", $username, ['xx', 'ex' => 125]);

        $resp = [
            "event" => "heartbeat",
            "user_type" => $user_type,
            "username"=> $username,
            "success"=> true
        ];

        $server->push($fd, json_encode($resp));
    }

}