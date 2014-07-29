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
$output = '';
register_shutdown_function(function(){
	global $output;
	file_put_contents('log.log',$output."\n\n",FILE_APPEND);
});
define('ROOT','D:/');//dirname(__FILE__).DIRECTORY_SEPARATOR);
include 'simple_html_dom.php';
set_time_limit(0);
$proxy = file('proxy');
$sessions = array();

function find($tag,$attr,$data){
	return (preg_match_all('#<('.$tag.')[^>]+'.$attr.'=(\'|")([^\'"]+)(\'|")#Uui',$data,$list))?array_unique($list[3]):array();
}

function sess(&$ch,$i){
	global $proxy,$output;
	
	if( !count($proxy) )
		exit($output.='<h1>Proxy out of it</h1>'."\n\r");
	
	if( $i==-1 or $i%count($proxy)==0 )
		return '127.0.0.1';

	list($ip,$port) = explode(':',$proxy[$i%count($proxy)]);
	curl_setopt($ch, CURLOPT_PROXY, $ip); 
	curl_setopt($ch, CURLOPT_PROXYPORT, $port);
	curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5); 
    curl_setopt($ch, CURLOPT_PROXYUSERPWD, "RUS107506:xtKCPbSUIL"); 
	curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_NTLM); 
	curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__).'/sessions/cookie'.$i.'.txt'); // сохранять куки в файл 
    curl_setopt($ch, CURLOPT_COOKIEFILE,  dirname(__FILE__).'/sessions/cookie'.$i.'.txt');
	return $ip;
}
function request($url,$ref,$i=0){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url ); // отправляем на 
    curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);// таймаут4
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);// таймаут4
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	$ip = sess($ch,$i);
	if( $ref )
		curl_setopt($ch, CURLOPT_REFERER, $ref);
    $data = curl_exec($ch);
    curl_close($ch);
	echo $url,'-loaded through '.$ip.'<br>'."\n\r";
    return $data;
}
//exit(request('http://xdan.ru/ip',''));
function isOpened( $url ){
	global $opened;
	return in_array($url,$opened);
}
function addOpened( $url ){
	global $opened;
	$opened[] = $url;
	file_put_contents('opened.txt',$url."\n\r",FILE_APPEND);
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
function isBan($data){
	return ((mb_strlen($data)<700) and !preg_match('#Fatal error#uis',$data));
}
$tree = array();

function getUrl($url,$rel){
	$urldata = parse_url($url);
	return $urldata['scheme'].'://'.$urldata['host'].$rel;
}
$sessionid = 0;

$skip = isset($_REQUEST['skip'])?intval($_REQUEST['skip']):0;
$skip = ( !$skip and isset($_SERVER['argv'][1]) )?intval($_SERVER['argv'][1]):0;
$pid = isset($_REQUEST['pid'])?intval($_REQUEST['pid']):0;
$pid = ( !$pid and isset($_SERVER['argv'][2]) )?intval($_SERVER['argv'][2]):0;
$nopid = ( !$pid and isset($_SERVER['argv'][3]) )?intval($_SERVER['argv'][3]):0;
function parsePage( $url,$ref='',&$was=array(),$depth = 1,$percent=0 ){
	global $sessionid,$skip,$proxy,$pid,$output,$nopid;
	if( in_array($url,$was) )
		return true;
		
	$path = createPath( $url );
	
	if( file_exists($path) ){
		$data = file_get_contents($path);
	}
	/*if( !isImage($url) )
		usleep(rand(5,20)*100000);*/
	if( isBan($data) ){		
		$data = request( $url,$ref,isImage($url)?-1:$sessionid++ );
		if( !isImage($url) and isBan($data) ){
			unset($proxy[$sessionid-1]);
			for($k=0;$k<10;$k++){
				$data = request( $url,$ref,$sessionid++ );
				if( !isBan($data) ){
					break;
				}else
					unset($proxy[$sessionid-1]);
			}
		}
		file_put_contents($path,$data);
		//addOpened($url);
	}
	
	if( !isImage($url) and isBan($data) )
		exit($output.='<h1>Ban</h1>'.$url.$data."\n\r");
	
	$was[] = $url;
	
	echo $out = $url.'-'.round($percent,3).'-'.$depth.'<br>'."\n\r";
	flush();
	$dir = dirname($path);
	
	if( isImage($url) or file_exists($dir.'/full') or (!$nopid and (file_exists($dir.'/start') and file_get_contents($dir.'/start')!=$pid) and file_get_contents($dir.'/start')!=1) )
		return true;
	
	if( $depth>505 )
		exit($output.='Very many iteration occurrences'."\n\r");
		
	//$page = str_get_html($data);
	// выкачиваем все фото
	$i=1;
	$resdepth = $depth+1;
	$imgs = find('img','src',$data);
	foreach($imgs as $src){
		parsePage(getUrl($url,$src),$url,$was,$resdepth,($i++)/count($imgs));
	}
		
	if( file_exists(ROOT.'stop.txt') )
		return true;
		
	//идем рекурсивно внутрь
	$urls = array();
	$hrefs = find('a|area','href',$data);

	foreach($hrefs as $href){
		if( !isHttp($href) and $href!='' and $href!='/' and $href!='#' and !isHash($href) )
			$urls[] = $href;
	}

	/*$page->clear();
	unset($page);
	unset($data);*/
	
	if( $depth>=3 )
		file_put_contents($dir.'/start',$pid);
	
	
	foreach($urls as $i=>$href){
		if( file_exists(ROOT.'stop.txt') )
			return true;
		
		if( $skip>0 ){
			$skip--;
			continue;
		}
		
		parsePage(getUrl($url,$href),$url,$was,$resdepth,($i+1)/count($urls));
	}
	
	file_put_contents($dir.'/full','1');
}

parsePage('http://hyundai.epcdata.ru');
?></body>
</html>

	

