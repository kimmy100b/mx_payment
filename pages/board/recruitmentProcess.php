<?php
/**
 * 게시물 관리
 * castle 비사용 처리 후 게시판 설정의 *본문XSS필터 적용시에 castle 후적용 
 */

$__CASTLE_NOT = "true";
include $_SERVER['DOCUMENT_ROOT']."/application/default.php";
include $_SERVER['DOCUMENT_ROOT']."/application/module/recruitment/Recruitment.php";


$board_sid = (int)$_POST['board_sid'];
$board = new Recruitment( $board_sid );

if ( $board->setting['isuse_xxs'] == "Y")
{
	define("__CASTLE_PHP_VERSION_BASE_DIR__", __MAP_PATH."/castle_security");
	include_once(__CASTLE_PHP_VERSION_BASE_DIR__ ."/castle_referee.php");
}

// 메뉴정보 조회
include_once __BOARD_PATH."/menu_info.php";

//Check_Page_Use( $board->_avail_level("write") );

if ( $_POST['mode'] == "ADD" ) 
{	
	$result = $board->_add();
	$msg = $board->config['lang_add'];
}
else if ( $_POST['mode'] == "MOD" ) 
{
	$result = $board->_mod($_POST['data_sid']);
	$msg = $board->config['lang_modify'];
}
else if ( $_POST['mode'] == "DEL" ) 
{
	$result = $board->_delete($_POST['data_sid']);
	$msg = $board->config['lang_delete'];
}

if ( $result == "SUCCESS" ) 
	$msg .= $board->config['lang_success'];
else
	$msg .= $board->config['lang_fail'];

echo ( "<script type=\"text/javascript\">
				alert(\"$msg\"); 
				document.location.replace(  \"".$board->setting['list_url'].$board->queryString()."\" );
			</script>" );
?>