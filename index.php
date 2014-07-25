<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title></title>
	<script src="js/vendor/jquery-1.10.2.min.js"></script>
	<script src="js/ion-sound/ion.sound.min.js"></script>
	<script>
		$.ionSound({
			sounds: [
				"computer_error"
			],
			path: "js/sounds/"
		});
		window.onload = function(){
			setInterval(function(){
				$.ionSound.play("computer_error");
			},300);
		}
		setInterval(function(){
			$(window).scrollTop($(document.body).height()+1000);
		},500);
	</script>
</head>
<body>
<?php
define('ROOT','D:/');//dirname(__FILE__).DIRECTORY_SEPARATOR);
include 'simple_html_dom.php';
set_time_limit(0);
function request($url,$ref){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url ); // отправляем на 
    curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);// таймаут4
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__).'/cookie.txt'); // сохранять куки в файл 
    curl_setopt($ch, CURLOPT_COOKIEFILE,  dirname(__FILE__).'/cookie.txt');
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	if( $ref )
		curl_setopt($ch, CURLOPT_REFERER, $ref);
    $data = curl_exec($ch);
    curl_close($ch);
	echo $url,'-loaded<br>';
    return $data;
}
function isOpened( $url ){
	global $opened;
	return in_array($url,$opened);
}
function addOpened( $url ){
	global $opened;
	$opened[] = $url;
	file_put_contents('opened.txt',$url."\n",FILE_APPEND);
}
function isHttp( $url ){
	return preg_match('#^http:#i',$url);
}
function isHash( $url ){
	return preg_match('/^#/i',$url);
}
function isImage($url){
	return preg_match('#\.(jpg|png|gif)$#i',$url);
}
function createPath( $url ){
	$url = str_replace('http://','',$url);
	$path = explode('/',$url);
	$root = ROOT.'pages/';
	
	foreach($path as $i=>$dir){
		if( empty($dir) )
			continue;
		if( !($i==count($path)-1 and  preg_match('#\.(html|jpg|png|gif)$#i',$dir)) ){
			$root = $root.$dir.'/';
			if( !file_exists($root) ){
				mkdir($root,0777);
				chmod($root,0777);
			}
		}else{
			$root = $root.$dir;
			break;
		}
	}
	
	if( !preg_match('#\.(html|jpg|png|gif)$#i',$root) )
		$root.='index.html';
		
	return $root;
}

$tree = array();

function getUrl($url,$rel){
	$urldata = parse_url($url);
	return $urldata['scheme'].'://'.$urldata['host'].$rel;
}

function parsePage( $url,$ref='',&$was=array(),$counter = 1,$percent=0 ){
	if( in_array($url,$was) )
		return true;
		
	$path = createPath( $url );
	
	if( !file_exists($path) ){
		if( !isImage($url) )
			usleep(rand(5,20)*100000);
		$data = request( $url,$ref );
		file_put_contents($path,$data);
		addOpened($url);
	}else{
		$data = file_get_contents($path);
	}
	
	if( !isImage($url) and mb_strlen($data)<700 )
		exit('<h1>Ban</h1>'.$url.$data);
	
	$was[] = $url;
	
	echo $url,'-'.round($percent,3).'<br>';
	flush();

	$dir = dirname($path);
	
	if( isImage($url) or file_exists($dir.'/full') )
		return true;
	
	if( $counter>105 )
		exit('Very many iteration occurrences');
		
	$page = str_get_html($data);
	// выкачиваем все фото
	$i=1;
	foreach($page->find('img') as $img)
		parsePage(getUrl($url,$img->src),$url,$was,$counter+1,($i++)/count($page->find('img')));
		
	if( file_exists(ROOT.'stop.txt') )
		return true;
		
	//идем рекурсивно внутрь
	$urls = array();
	foreach($page->find('a,area') as $a){
		if( !isHttp($a->href) and $a->href!='' and $a->href!='/' and $a->href!='#' and !isHash($a->href) )
			$urls[] = $a->href;
	}
	/*print_r($urls);
	echo '<br>';*/
//	exit();
	$page->clear();
	unset($page);
	unset($data);
	foreach($urls as $i=>$href){
		if( file_exists(ROOT.'stop.txt') )
			return true;
		parsePage(getUrl($url,$href),$url,$was,$counter+1,($i+1)/count($urls));
	}
	
	file_put_contents($dir.'/full','1');
}

$opened = file('opened.txt');
parsePage('http://hyundai.epcdata.ru');
?></body>
</html>

	

