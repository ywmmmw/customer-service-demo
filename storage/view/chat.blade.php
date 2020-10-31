<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hyperf</title>
    <script src="https://s0.pstatp.com/cdn/expire-1-M/zepto/1.2.0/zepto.min.js" type="application/javascript"></script>
</head>
<body>
  <div id="from">
  	<div>
  		<label for="name">名称:</label>
  		<input type="text" id="name">
  		<input type="radio" id="ut-worker" name="usertype" value="worker" checked="checked">
	    <label for="ut-worker">客服</label>
	    <input type="radio" id="ut-customer" name="usertype" value="customer">
	    <label for="ut-customer">顾客</label>
	    <button id="login">登录</button>
  	</div>
  	<div>
  		<label for="msg">消息:</label>
  		<input type="text" id="msg">
  		<button id="send">发送</button>
  	</div>
  </div>
  <div id="msgbox">
   <ul id="msgs">
   </ul>
  </div>
  <script type="text/javascript">
    let socket = new WebSocket('ws://127.0.0.1:9502');
    let chat_to = null;

    // Connection opened
    socket.addEventListener('open', function (event) {
        console.log("websocket opened");
    });
  
    // Listen for messages
    socket.addEventListener('message', function (event) {
        // console.log('Message from server ', event.data);
        var data = JSON.parse(event.data);
        var msg = "ERROR";

        if (data.event == "login"){
          if (data.success){
            msg = data.username + "登录成功";
            $("input[name=usertype]").attr("disabled", "disabled");
            $("#name").val(data.username);
            $("#name").attr("disabled", "disabled");
            $("#login").attr("disabled", "disabled");

            setInterval(function(){
                var username = $("#name").val();
                var user_type = $("input[name=usertype]:checked").val();

                var msg = {
                  "event": "heartbeat", 
                  "username": username, 
                  "user_type": user_type
                };
                socket.send(JSON.stringify(msg));
            }, 6000);
          }
        }else if(data.event == "heartbeat"){
          if (data.success){
            console.log("heartbeat");
          }
          msg = null;
        }else if(data.event == "set_chat_to"){
          if (data.success){
            console.log(data.to);
            chat_to = data.to;
            var user_type = $("input[name=usertype]:checked").val();
            if (user_type == "worker" && data.direct_type == "worker"){
              msg = "您正在服务:" + data.to;
            }else if(user_type == "customer" && data.direct_type == "customer"){
              if (data.to){
                msg = data.to + "正在为您服务";
              }else{
                msg = "暂无空闲客服";
              }
            }
          }
        }else if(data.event == "msg"){
          if (data.success){
            msg = data.from + ":" + data.text;
          }
        }else if(data.event == "init") {
          if (data.success){
            msg = "已连接";
          }
        } else {
          msg = "unknow event";
        }
        if (msg){
          $("ul#msgs").prepend("<li>" + msg + "</li>");
        }
        
    });

    $("#send").on("click", function(){
      // $("ul#msgs").prepend("<li>你好</li>");
      var text = $("#msg").val();
      var username = $("#name").val();
      var msg = {
        "event": "msg",
        "text": text,
        "from": username,
        "to": chat_to
      }
      socket.send(JSON.stringify(msg));
      
      $("ul#msgs").prepend("<li>" + username + ":" + text + "</li>");
    });

    $("#login").on("click", function(){
      var username = $("#name").val();
      var user_type = $("input[name=usertype]:checked").val();
      var msg = {
        "event": "login", 
        "username": username, 
        "user_type": user_type
      };
      socket.send(JSON.stringify(msg));
    });
  </script>
</body>
</html>
