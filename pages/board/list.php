<?php
include_once $_SERVER['DOCUMENT_ROOT']."/application/default.php";
include_once __BASE_PATH."/function/util_func.php";

// 게시판 고유키(int)
$board_sid = (int)$_GET['board_sid'];

// 메뉴정보 조회
$_PARAM = $_GET;
include_once __BOARD_PATH."/menu_info.php";

// 게시판 모듈 시작
include_once __MODULE_PATH."/board/Board.php";

// 게시판 모듈
$board = new Board( $board_sid );
// 게시판 설정 정보
$setting = $board->getSetting();

// 목록 허용 불가 처리
if ( !is_board_admin( $setting->user_sid ) && $setting->isuse_list_page == "N" ) movepage( $setting->write_url . $board->queryString() );	

// 사용권한 체크
Check_Page_Use( $board->_avail_level("list") );
// 게시물 html < array( 'html', 'page' );

if($setting->skin_type == "history"){
	$result = $board->_historylist();
	$html	= $result['html'];
}else{
	$result = $board->_list();
	$html	= $result['html'];
	$page	= $result['page'];
}

//갤러리 이미지만 추출
$slideData	= $board->getSlideImge($imgTag, 10, "STRING_CNT" );
/*
// 모바일 체크
if ( is_mobile( "PC" ) )
{
	require __BOARD_PATH."/include/".$setting->m_skin_header;
	require __BOARD_PATH."/skin/".$setting->skin_type."/m.". $setting->list_url;
	require __BOARD_PATH."/include/".$setting->m_skin_footer;
}
else
{*/
	//require __BOARD_PATH."/include/".$setting->skin_header;

	//-------------------------------------------------------------------------------------------------//
	// 헤더정보(레이아웃)
	if ( $layout_info['header_type'] == "INC" ) 
		require __MAP_PATH."/". str_replace( "../", "/", $layout_info['header_content'] );
	else
		echo $layout_info['header_content'];
	//-------------------------------------------------------------------------------------------------//

	require __BOARD_PATH."/skin/".$setting->skin_type."/". $setting->list_url;

	//-------------------------------------------------------------------------------------------------//
	// 푸터정보(레이아웃)
	if ( $layout_info['footer_type'] == "INC" ) 
		require __MAP_PATH."/". str_replace( "../", "/", $layout_info['footer_content'] );
	else
		echo $layout_info['footer_content'];
	//-------------------------------------------------------------------------------------------------//

	//require __BOARD_PATH."/include/".$setting->skin_footer;
//}
?>
