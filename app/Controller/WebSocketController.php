<?php
declare(strict_types=1);

namespace App\Controller;

use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Swoole\Http\Request;
use Swoole\Server;
use Swoole\Websocket\Frame;
use Swoole\WebSocket\Server as WebSocketServer;

use App\Services\ChatService;

class WebSocketController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{

    protected $service;

    public function __construct()
    {
        $this->service = new ChatService();
    }

    public function onMessage($server, Frame $frame): void
    {
        $msg = $this->service->parseMessage($frame->data);

        switch ($msg["event"]) {
            case "login":
                $this->service->onLogin($server, $frame->fd, $msg);
                
                break;
            case "heartbeat":
                $this->service->onHeartbeat($server, $frame->fd, $msg);
                break;

            case "msg":
                $this->service->onMsg($server, $frame->fd, $msg);
                break;
            
            default:
                $this->service->onError($server, $frame->fd, $msg);
                $server->push($frame->fd, json_encode($msg));
                break;
        }

        
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        $this->service->onClose($server, $fd);
    }

    public function onOpen($server, Request $request): void
    {
        $this->service->onOpen($server, $request->fd);
    }
}