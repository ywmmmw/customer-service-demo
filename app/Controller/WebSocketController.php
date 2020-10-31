<?php
declare(strict_types=1);

namespace App\Controller;

use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;
use Swoole\Http\Request;
use Swoole\Server;
use Swoole\Websocket\Frame;
use Swoole\WebSocket\Server as WebSocketServer;

class WebSocketController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{

    protected $redis;

    public function __construct()
    {
        $container = ApplicationContext::getContainer();

        $this->redis = $container->get(\Hyperf\Redis\Redis::class);
    }

    public function onMessage($server, Frame $frame): void
    {
        try {
            $msg = json_decode($frame->data, true);
        } catch (Exception $e) {
            $msg = [
                "event" => "error",
                "body" => "Recv: " . $frame->data
            ];
        }
        
        if (!isset($msg["event"])){
            $msg = [
                "event" => "error",
                "body" => "Recv: " . $frame->data
            ];
        }

        switch ($msg["event"]) {
            case "login":
                $user_type = $msg["user_type"] ?? "worker";
                $username = $msg["username"] ?? "worker" . $frame->fd;


                # 记录某个用户的客户端编号，有值表示在线
                $this->redis->set("fd:{$username}", $frame->fd, 125);
                $this->redis->set("username:{$frame->fd}", $username, 125);

                if ($user_type == "worker"){
                    
                    if (empty($username)){
                        $username = "worker" . $frame->fd;
                    }

                    # 空闲的客服进行排队等候分配客户进行工作
                    $this->redis->lpush("pendingworkers", $username);

                }

                $resp = [
                    "event" => "login",
                    "user_type" => $user_type,
                    "username"=> $username,
                    "success"=> true
                ];
                $server->push($frame->fd, json_encode($resp));

                if ($user_type == "customer") {
                    # 用户登录分配空闲的客服进行工作
                    $worker = $this->redis->rpop("pendingworkers");

                    if($worker){
                        $worker_fd = intval(
                            $this->redis->get("fd:{$worker}")
                        );
                        if($worker_fd){
                            $resp3 = [
                                "event" => "set_chat_to",
                                "to" => $worker,
                                "direct_type" => "customer",
                                "success" => true
                            ];
                            $server->push($frame->fd, json_encode($resp3));
                            $resp4 = [
                                "event" => "set_chat_to",
                                "to" => $username,
                                "direct_type" => "worker",
                                "success" => true
                            ];
                            $server->push($worker_fd, json_encode($resp4));
                        }else{
                            $resp3 = [
                                "event" => "set_chat_to",
                                "to" => null,
                                "direct_type" => "customer",
                                "success" => true
                            ];
                            $server->push($frame->fd, json_encode($resp3));
                        }
                    }
                    
                }
                
                break;
            case "heartbeat":
                $user_type = $msg["user_type"];
                $username = $msg["username"];
                $this->redis->set("fd:{$username}", $frame->fd, ['xx', 'ex' => 125]);
                $this->redis->set("username:{$frame->fd}", $username, ['xx', 'ex' => 125]);
                $resp = [
                    "event" => "heartbeat",
                    "user_type" => $user_type,
                    "username"=> $username,
                    "success"=> true
                ];

                $server->push($frame->fd, json_encode($resp));
                break;

            case "msg":
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
                break;
            
            default:
                $server->push($frame->fd, json_encode($msg));
                break;
        }

        
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        $username = $this->redis->get("username:{$fd}");
        if ($username) {
            $this->redis->del("username:{$fd}");
            $this->redis->del("fd:{$username}");
            $this->redis->lrem("pendingworkers", $username, 0);
        }
    }

    public function onOpen($server, Request $request): void
    {
        $resp = [
            "event" => "init",
            "success"=> true
        ];
        $server->push($request->fd, json_encode($resp));
    }
}