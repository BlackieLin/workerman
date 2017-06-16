<?php
use \GatewayWorker\Lib\Db;
//require_once('/app.php');//注意，此处可以引入别的框架，进行整合
error_reporting(0);
require_once dirname(dirname(__FILE__)) . '/workerman/vendor/autoload.php';
$status=200;
$img_id=intval($_COOKIE['pre_img_id']);
if($img_id==0){
	$img_id=rand(1,3);
	$expireTimes=30*24*3600+time();
	setcookie('pre_img_id',$img_id,$expireTimes,'/');
}
// 合法的用户名：英文字母、数组、下划线、短横线、中文
function is_args($string){
	return preg_match("!^[@\-\._a-zA-Z0-9\\x{4e00}-\\x{9fa5}]{1,20}$!u",$string);
}
$citycode=$_GET['citycode']?$_GET['citycode']:'sz';//默认深圳
$gzh=$_GET['gzh']?$_GET['gzh']:'qianjiapu';//默认千家铺公众号
if(is_args($citycode)&&is_args($gzh)){
	$chat_id=$citycode.'_'.$gzh;
	Db::instance("blackie_config");//链接
	$gzh_arr=array();
	$gzh_arr=Db::row("blackie_config",$sql);
	if(empty($gzh_arr)){
		$status=404;
	}else{
		$gzh_name=$gzh_arr['gzh_name'];
	}
}else{
	$status=404;	
}
if($status==404){
	header('HTTP/1.1 404 Not Found');
	echo "not find pages power by blackie";
	exit;	
}
?>
<!DOCTYPE HTML>
<html>
<head>
	<title><?php echo $gzh_name; ?>问答中心</title>
	<meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="/workerman/static/css/bootstrap.min.css">    
    <link rel="stylesheet" type="text/css" href="/workerman/static/css/chat-style.css"> 
    <script type="text/javascript" src="/workerman/static/js/jquery-1.9.1.js"></script> 
    <script type="text/javascript" src="/workerman/static/js/mywebsocket.js"></script>
</head>
<body>
<div data-role="page">
	<div data-role="header" class="header linear-g">
        <a href="#panel-left" data-iconpos="notext" class="glyphicon glyphicon-th-large col-xs-2 text-right"> </a>
        <a class="text-center col-xs-8"><?php echo $gzh_name; ?>机器人</a>
        <a href="#panel-right" data-iconpos="notext" class="glyphicon glyphicon-user col-xs-2 text-left"> </a>
    </div>
    <div data-role="content" class="container-fluid" role="main">
        <ul class="content-reply-box mg10" id="message_box">
            <li class="odd">
                <a class="user" href="javascript:void(0);"><img class="img-responsive avatar_" src="/workerman/static/img/blackie.jpg" alt=""><span class="user-name">千家铺</span></a>
                <div class="reply-content-box">
                	<span class="reply-time"><?php echo date("Y-m-d H:i:s",time()); ?></span>
                    <div class="reply-content pr">
                    	<span class="arrow">&nbsp;</span>
                    	<p>您好，我是<?php echo $gzh_name; ?>咨询机器人，为您提供办事等相关咨询。</p>
                        <p class="row" style="line-height:24px; padding:10px 0px;">
                            <span class="col-sm-2 col-xs-4"><a href="javascript:void(0);" onClick="sendClass('教育');">教育</a></span>
                            <span class="col-sm-2 col-xs-4"><a href="javascript:void(0);" onClick="sendClass('美食');">美食</a></span>
                            <span class="col-sm-2 col-xs-4"><a href="javascript:void(0);" onClick="sendClass('活动');">活动</a></span>
                            <span class="col-sm-2 col-xs-4"><a href="javascript:void(0);" onClick="sendClass('景点');">景点</a></span>
                            <span class="col-sm-2 col-xs-4"><a href="javascript:void(0);" onClick="sendClass('办事');">办事</a></span>
                            <span class="col-sm-2 col-xs-4"><a href="javascript:void(0);" onClick="sendClass('医疗');">医疗</a></span>
                            <span class="col-sm-2 col-xs-4"><a href="javascript:void(0);" onClick="sendClass('交通');">交通</a></span>
                            <span class="col-sm-2 col-xs-4"><a href="javascript:void(0);" onClick="sendClass('购物');">购物</a></span>
                            <span class="col-sm-2 col-xs-4"><a href="javascript:void(0);" onClick="sendClass('未分类');">未分类</a></span>
                            <span class="col-sm-2 col-xs-4"><a href="javascript:void(0);" onClick="sendClass('全部');">全部</a></span>
                        </p>
                        <p>(信息仅供参考，具体以实际办理流程公布为准。)</p>
                    </div>
                </div>
            </li>
        </ul>
    </div>
    <section class="note-box">
    	<div class="note-box-li"></div>
    </section>
    <section class="message-box">
    	<input type="hidden" id="img_id" name="img_id" value="<?php echo $img_id; ?>"/>
		<div class="sub-message-box row">
            <div class="col-xs-10 no-padding">
                <div class="col-xs-2 ico no-padding"><img class="ico-input" src="static/img/menu_keyborad.png"/></div>
                <div class="col-xs-10 no-padding"><input id="message" name="message" class="form-control wd-98" placeholder="说点啥吧..." /></div>
            </div>
            <div class="col-xs-2 no-padding"><button type="button" class="btn btn-success sub_but">提 交</button></div>
      </div>
	</section>
</div>
<script type="application/javascript">
	var ws;
	$(document).ready(function(e) {
		ws = new MyWebsocket("ws://"+document.domain+":7272",{chat_id:"<?php echo $chat_id;?>"}, true);
	   var fromname="游客", to_uid, to_uname;
		$('.sub_but').click(function(event){
			$(".note-box").hide();
			if($("#message").val()){
				ws.sendMessage(event, fromname, to_uid, to_uname);
			}
		});
		 /*按下按钮或键盘按键*/
		$("#message").keydown(function(event){
			var e = window.event || event;
			var k = e.keyCode || e.which || e.charCode;
			if(k == 13){
				$(".note-box").hide();
				ws.sendMessage(event, fromname, to_uid, to_uname);
				event.returnValue = false;
				return false;
			}

		});
		$("#message").keyup(function(event){
			var e = window.event || event;
			var k = e.keyCode || e.which || e.charCode;
			if(k!=9&&k!=13&&k!=16&&k!=17&&k!=18&&k!=19&&k!=20&&k!=33&&k!=34&&k!=35&&k!=36&&k!=37&&k!=38&&k!=39&&k!=40&&k!=45&&k!=46&&k!=91&&k!=93&&k!=144){
				if($("#message").val()){//回车不执行，避免重复提交
					var msg = $("#message").val().replace(/"/g, '\\"').replace(/\n/g,'\\n').replace(/\r/g, '\\r').replace(/<[^>]+>/g,"");
					ws.sendMsg('{"type":"note","to_client_id":"all","to_client_name":"Blackie","content":"'+msg+'"}');
				}
			}
		});
		$("#message").click(function(event){
			if($("#message").val()){//回车不执行，避免重复提交
				var msg = $("#message").val().replace(/"/g, '\\"').replace(/\n/g,'\\n').replace(/\r/g, '\\r').replace(/<[^>]+>/g,"");
				ws.sendMsg('{"type":"note","to_client_id":"all","to_client_name":"Blackie","content":"'+msg+'"}');
			}
		})
		$(".content-reply-box").css("height",$(window).height());
		$(".container-fluid").click(function(event){
			$(".note-box").hide();
		})
	})
	function sendKeyWord(keyword){
		var fromname="游客", to_uid, to_uname;
		$(".note-box").hide();
		if(keyword!=""){
			$("#message").val(keyword)
			ws.sendMessage(window.event, fromname, to_uid, to_uname);
		}
	}
	function sendClass(keyword){
		$(".note-box").hide();
		if(keyword){
			ws.sendMsg('{"type":"answer","to_client_id":"all","to_client_name":"Blackie","content":"'+keyword+'"}');
		}
	}
</script>
</body>
</html>