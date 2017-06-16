function MyWebsocket(wsUri,argObj, autoConn) {
	$("#message").attr("disabled",true).attr("placeholder","拼命加载..."); 
	this.wsUri = wsUri;
	this.argObj = argObj;
	var websocket;
	if (window.WebSocket == undefined)
		this.noWebSocket();
	else {
		if (autoConn)
			this.doConn();
	}
	return this;
}
//格式化数字函数
function formatNumber(n) {
  n = n.toString()
  return n[1] ? n : '0' + n
}
//格式化时间
function formatTime(date) {
  var year = date.getFullYear()
  var month = date.getMonth() + 1
  var day = date.getDate()
  var hour = date.getHours()
  var minute = date.getMinutes()
  var second = date.getSeconds()
  return [hour, minute].map(formatNumber).join(':')
}
function formatDate(str,f){
	if(f==""){
		f="Y-m-d H:i:s";
	}
    if (str==""){
      var date = new Date();
    }else{
      var date = new Date(parseInt(str) * 1000);
    }
    var year = date.getFullYear();
    var month = date.getMonth() + 1 < 10 ? "0" + (date.getMonth() + 1) : date.getMonth() + 1;
    var day = date.getDate() < 10 ? "0" + date.getDate() : date.getDate();
    var hour = date.getHours()<10?"0"+date.getHours():date.getHours();
    var minute = date.getMinutes()<10?"0"+date.getMinutes():date.getMinutes();
    var second = date.getSeconds()<10?"0"+date.getSeconds():date.getSeconds();
    return f.replace(/Y/,year).replace(/m/,month).replace(/d/,day).replace(/H/,hour).replace(/i/,minute).replace(/s/,second);
}
//不支持websocket
MyWebsocket.prototype.noWebSocket = function () {
	this.alertMsg("danger", "你使用的浏览器不支持websocket");
};
//建立连接
MyWebsocket.prototype.doConn = function () {
	var mys = this;
	this.websocket = new WebSocket(this.wsUri);
	if (this.websocket.readyState == this.websocket.CONNECTING);
	this.websocket.addEventListener("open", function (evt) {
		//登录socket
	   var login_data = '{"type":"login","client_name":"游客","chat_id":"'+mys.argObj.chat_id+'"}';
	   mys.sendMsg(login_data);
	   //console.log("连接成功");
	});
	this.websocket.addEventListener("close", function (evt) {
		console.log("连接关闭，定时重连");
	  	connect();
	});
	this.websocket.addEventListener("message", function (evt) {;
		mys.appendMsg(false, evt);
	});
	this.websocket.addEventListener("error", function (evt) {
		console.log("出现错误");
	});
};
//接收服务器消息显示到页面上
MyWebsocket.prototype.appendMsg = function (isMyMsg, evt) {
	var mys = this;
	var data=JSON.parse(evt.data);
	switch(data['type']){
		// 服务端ping客户端
		case 'ping':
			mys.websocket.send('{"type":"pong"}');
			break;;
		// 登录 更新用户列表
		case 'login':
			//console.log(data['client_name']+"登录成功");
			$("#message").attr("disabled",false).attr("placeholder","说点啥吧..."); 
			break;
		// 发言
		case 'say':
			$("#message").attr("disabled",false).attr("placeholder","说点啥吧..."); 
			var htmlData =   '<li class="odd">'
						   + '   <a class="user" href="javascript:void(0);"><img class="img-responsive avatar_" src="/workerman/static/img/blackie.jpg" alt=""><span class="user-name">千家铺</span></a>'
						   + '   <div class="reply-content-box">'
						   + '     <span class="reply-time">'+data['time']+'</span>'
						   + '	   <div class="reply-content pr">'
						   + '	       <span class="arrow">&nbsp;</span>'
						   + '         <p>' + data['content'].replace(/\n/g,'<BR>') + '</p>'
						   + '     </div>'
						   + '   </div>'
						   + '</li>';
			$("#message_box").append(htmlData);
			$('#message_box').scrollTop($("#message_box")[0].scrollHeight + 20);
			break;
		// 提示
		case 'note':
			if(data['content']!=""&&data['content']!=null){
				var listArr = JSON.parse(data['content']);
				if(listArr.length>0){
					var htmlData = '';
					for(var i=0;i<listArr.length;i++){
						htmlData += '<p><a href="javascript:void(0);" onClick="sendKeyWord(\''+listArr[i].keyword+'\');">▪ '+listArr[i].keyword+'</a></p>';
					}
					$(".note-box-li").html(htmlData);
					$(".note-box").show();
				}else{
					$(".note-box-li").html("")
					$(".note-box").hide();
				}
			}else{
				$(".note-box-li").html("")
				$(".note-box").hide();
			}
			break;
		// 自答
		case 'answer':
			var msg="抱歉，未找到内容";
			if(data['content']!=""&&data['content']!=null){
				var listArr = JSON.parse(data['content']);
				if(listArr.length>0){
					msg='<p>为您查到了以下内容：</p>'
						+'<p style="line-height:24px; padding:10px 0px;">';
					for(var i=0;i<listArr.length;i++){
						msg += '<a href="javascript:void(0);" onClick="sendKeyWord(\''+listArr[i].group_name+'\');">'+listArr[i].group_name+'</a>&nbsp;&nbsp;'
					}
					msg+='</p>'
					+'<p>(信息仅供参考，具体以实际办理流程公布为准。)</p>';
				}
			}
			var htmlData =   '<li class="odd">'
				   + '   <a class="user" href="javascript:void(0);"><img class="img-responsive avatar_" src="/workerman/static/img/blackie.jpg" alt=""><span class="user-name">千家铺</span></a>'
				   + '   <div class="reply-content-box">'
				   + '     <span class="reply-time">'+data['time']+'</span>'
				   + '	   <div class="reply-content pr">'
				   + '	       <span class="arrow">&nbsp;</span>'
				   + '         ' + msg
				   + '     </div>'
				   + '   </div>'
				   + '</li>';
			$("#message_box").append(htmlData);
			$('#message_box').scrollTop($("#message_box")[0].scrollHeight + 20);
			break;
		// 用户退出 更新用户列表
		case 'logout':
			$("#message").attr("disabled",true).attr("placeholder","退出登录"); 
			//console.log(data['client_name']+"退出登录");
			break;
	}
	$(document).scrollTop($(document).height()-$(window).height());
	$("#message").focus();
};
//发送消息操作
MyWebsocket.prototype.sendMessage = function(event, from_name, to_uid, to_uname){
	var mys = this;
	var time = formatTime(new Date());
	var msg = $("#message").val().replace(/"/g, '\\"').replace(/\n/g,'\\n').replace(/\r/g, '\\r').replace(/<[^>]+>/g,"");
	if(msg==""){//为空不发送
		return;
	}
	$("#message").attr("disabled",true).attr("placeholder","拼命传送..."); 
	var htmlData =   '<li class="even">'
				   + '   <a class="user" href="javascript:void(0);"><img class="img-responsive avatar_" src="/workerman/static/img/'+$("#img_id").val()+'.jpg" alt=""><span class="user-name">'+from_name+'</span></a>'
				   + '   <div class="reply-content-box">'
				   + '     <span class="reply-time">'+time+'</span>'
				   + '	   <div class="reply-content pr">'
				   + '	       <span class="arrow">&nbsp;</span>'
				   + '         <p>' + msg + '</p>'
				   + '     </div>'
				   + '   </div>'
				   + '</li>';
	var data={
		type:"say",
		to_client_id:'all',
		to_client_name:to_uname,
		content:msg
	};
	mys.websocket.send(JSON.stringify(data));//发送到服务器
	$("#message_box").append(htmlData);
	$(document).scrollTop($(document).height()-$(window).height());
	$("#message").val("");
	$("#message").focus();
}
MyWebsocket.prototype.sendMsg = function (str) {
	var mys = this;
	mys.websocket.send(str);//发送到服务器
};
//公共方法
MyWebsocket.prototype.alertMsg = function (type,msg) {
	alert(msg);
};