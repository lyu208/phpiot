<?php
	use PHPiot\Worker;
	require  '../Autoloader.php';
	require 'nlecloudsdk.php';//使用新大陆sdk
	// 创建一个Worker监听2347端口，不使用任何应用层协议
	$tcp_worker = new Worker("tcp://0.0.0.0:2347");

	// 启动4个进程对外提供服务
	$tcp_worker->count = 4;

	// 当客户端发来数据时
	$tcp_worker->onMessage = function($connection, $data)
	{
		$apiurl = '';
		$token = '';
		$userName = '17852270080';       //测试帐号
		$password = 'yan1997guo';           //测试密码
		$projectID = "9988";			//测试的项目ID
		$gatewayID = "9947";			//测试的设备ID
		$gatewayTag = "device1";     //测试的设备标识
		$sensorApiTag = "device1";       //测试的传感器ApiTag
		$sensorApiTag1 = "device2";
		$len=strlen($data);
		if($len>3){//判断是否为心跳包
		// 向客户端发送hello 
		$connection->send('hello');
		
		/*
		数据处理部分
		$data 为客户端发送的数据，类型是字符串
		格式为重量逗号价格逗号客户端标识
		*/	
		$data_basic = explode(',',$data); //将数据分割写入数组
		$Weight_basic=$data_basic[0];
		$Price_basic=$data_basic[1];
		$Name_basic=$data_basic[2];
		$Weight=floatval($Weight_basic);//强转为float
		$Price=floatval($Price_basic);
		/*新大陆接口对接*/
		//创建api对象
		$nleApi = new NLECloudSDK($apiurl);
		//echo "logon\n";
		/*获取token*/
		$loginInfo = new Account();
		$loginInfo->Account=$userName;
		$loginInfo->Password=$password;
		$loginInfo->IsRememberMe=false;
		$response = $nleApi->user_login($loginInfo);
		if ($response)
		{
			$token = $response->ResultObj->AccessToken;
		}
		else
		{
			//处理错误信息
			$error_code = $nleApi->error_no();
			$error = $nleApi->error();
		}
		if (empty($token)) {
			echo "TOKEN null\n";
			return;
		}
		
		/*
		添加数据
		sensorDataArray 数组要上传的数据包
		ApiTag 传感器标识
		Value  数据
		RecordTime 时间戳	
		使用北京时间需要更改php.ini为date.timezone = PRC
		*/
		//echo "add\n";	
		$sensorDataArray=  array(new SensorDataAdd(), new SensorDataAdd());
		$sensorDataArray[0]->ApiTag = $sensorApiTag;
		$sensorDataArray[1]->ApiTag = $sensorApiTag1;
		$sensorDataArray[0]->PointDTO = array(new SensorDataAddPoint());
		$sensorDataArray[1]->PointDTO = array(new SensorDataAddPoint());
		$sensorDataArray[0]->PointDTO[0]->Value=$Weight;
		$sensorDataArray[0]->PointDTO[0]->RecordTime=date('y-m-d h:i:s',time());
		$sensorDataArray[1]->PointDTO[0]->Value=$Price;
		$sensorDataArray[1]->PointDTO[0]->RecordTime=date('y-m-d h:i:s',time());
		$response = $nleApi->add_sensor_data($gatewayID,$sensorDataArray, $token);
		if (!$response)
		{
			//处理错误信息
			$error_code = $nleApi->error_no();
			$error = $nleApi->error();
		}
		
		
		/*上传数据库*/	
			$con=mysqli_connect('localhost','root','') or die(mysqli_error());
			mysqli_select_db($con,'test')or die('123');
			mysqli_query('set names utf8');
			$sql = "INSERT INTO `test` (`ID`, `weight`, `jia`, `name`) VALUES (NULL, '$Weight','$Price','$Name_basic')";
			if(mysqli_query($con,$sql)){
				echo 'success';
			}
			else{
				echo 'error';
			}
		}

	};

	// 运行worker
	Worker::runAll();