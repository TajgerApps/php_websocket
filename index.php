<?php
    $host = 'localhost';
    $port = '9000';

    $colors = array('#007AFF','#FF7000','#FF7000','#15E25F','#CFC700','#CFC700','#CF1100','#CF00BE','#F00');
    $pickedColor = array_rand($colors);
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style type="text/css">
        .chat-wrapper {
            font: bold 11px/normal 'lucida grande', tahoma, verdana, arial, sans-serif;
            background: #00a6bb;
            padding: 20px;
            margin: 20px auto;
            box-shadow: 2px 2px 2px 0 #00000017;
            max-width: 700px;
            min-width: 500px;
        }

        #message-box {
            width: 97%;
            display: inline-block;
            height: 300px;
            background: #fff;
            box-shadow: inset 0 0 2px #00000017;
            overflow: auto;
            padding: 10px;
        }

        .user-panel {
            margin-top: 10px;
        }

        input[type=text] {
            border: none;
            padding: 5px 5px;
            box-shadow: 2px 2px 2px #0000001c;
        }

        input[type=text]#name {
            width: 20%;
        }

        input[type=text]#message {
            width: 60%;
        }

        button#send-message {
            border: none;
            padding: 5px 15px;
            background: #11e0fb;
            box-shadow: 2px 2px 2px #0000001c;
        }
    </style>
</head>
<body>
<div class="chat-wrapper">
    <div id="message-box"></div>
    <div class="user-panel">
        <input type="text" name="name" id="name" placeholder="Your Name" maxlength="15"/>
        <input type="text" name="message" id="message" placeholder="Type your message here..." maxlength="100"/>
        <button id="send-message">Send</button>
    </div>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script language="javascript" type="text/javascript">
    //create a new WebSocket object.
    const msgBox = $('#message-box');
    const wsUri = "ws://<?php echo $host; ?>:<?php echo $port; ?>/server.php";
    websocket = new WebSocket(wsUri);

    websocket.onopen = function (ev) { // connection is open
        msgBox.append('<div class="system_msg" style="color:#bbbbbb">Welcome to my "Chat box"!</div>'); //notify user
    }
    // Message received from server
    websocket.onmessage = function (ev) {
        const response = JSON.parse(ev.data);

        const responseType = response.type;
        const userMessage = response.message;
        const userName = response.name;
        const userColor = response.color;
        switch (responseType) {
            case 'usermsg':
                msgBox.append('<div><span class="user_name" style="color:' + userColor + '">' + userName + '</span> : <span class="user_message">' + userMessage + '</span></div>');
                break;
            case 'system':
                msgBox.append('<div style="color:#bbbbbb">' + userMessage + '</div>');
                break;
        }
        msgBox[0].scrollTop = msgBox[0].scrollHeight;
    };

    websocket.onerror = function (ev) {
        msgBox.append('<div class="system_error">Error Occurred - ' + ev.data + '</div>');
    };
    websocket.onclose = function (ev) {
        msgBox.append('<div class="system_msg">Connection Closed</div>');
    };
    //Message send button
    $('#send-message').click(function () {
        send_message();
    });

    //User hits enter key
    $("#message").on("keydown", function (event) {
        if (event.which == 13) {
            send_message();
        }
    });

    function send_message() {
        const messageInput = $('#message');
        const nameInput = $('#name');

        if (messageInput.val() == "") {
            alert("Enter your Name please!");
            return;
        }
        if (nameInput.val() == "") {
            alert("Enter Some message Please!");
            return;
        }
        const msg = {
            message: messageInput.val(),
            name: nameInput.val(),
            color: '<?php echo $colors[$pickedColor]; ?>'
        };
        websocket.send(JSON.stringify(msg));
        messageInput.val('');
    }
</script>
</body>
</html>
