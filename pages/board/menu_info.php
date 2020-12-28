<?php
// 게시판 모듈에서 메뉴 정보 조회 공통

if ( $_SERVER['REQUEST_METHOD'] == "POST" )	$_PARAM = $_POST;
else															$_PARAM = $_GET;

// 메뉴 코드가 없다면 default 메뉴 코드 설정
$menu_code = clean( $_PARAM['menu_code'] );
if ( $menu_code == "" ) errorMsg("필수 정보가 없습니다!");;

include_once __MODULE_PATH."/menu/Menu.php";
$menu = new Menu();
$layout = new Layout();

// 선택한 메뉴 정보
$menuInfo = $menu->getInfo( $menu_code );
// 레이아웃 html
$layout_info = $layout->getInfo( $menuInfo['lay_sid'] );

// 사용자 로그인 페이지
define( "__LOGIN_URL",		"/?menu_code=".$menu->getPage( "LOGIN" ) );

//------------------------------------------------------------------------------//
// 원래 등록된 메뉴 코드의 게시판이 맞는지 확인
// /pages/board/list.php?board_sid=1
$tmp_urlArr = explode( "?", $menuInfo['content'] );
$tmp_paramArr = explode( "=", $tmp_urlArr[1] );
if ( $tmp_paramArr[1] != $board_sid ) errorMsg( "잘못된 요청입니다!" );
//------------------------------------------------------------------------------//

//-------------------------------------------------------------------------------------------------//
// 사이트 기본 정보
include_once __BASE_PATH."/lib/SiteProperty.php";
$object = new SiteProperty( "site" );
$property	= $object->_load();

// 타이틀
$_PAGE_TITLE_		= $property->shop_name;
$_SITE_NM= $property->shop_name;
// 메인페이지 제외한 서브페이지 타이틀
if ( $menu_code > $_main_page_code_ && $menuInfo['mname'] != "" ) $_PAGE_TITLE_ = $menuInfo['mname'] . " | " . $property->shop_name;

$_PAGE_META_		= "";
$_PAGE_ADDRESS_	= $property->shop_address;
$_PAGE_TEL_		= $property->shop_tel;
$_PAGE_PHONE_	= $property->shop_phone;
$_PAGE_FAX_		= $property->shop_fax;
$_PAGE_EMAIL_		= $property->shop_mail;
$_PAGE_SSN_		= $property->co_ssn;
$_PAGE_SALE_NO_ = $property->co_sale_num_01;
$_PAGE_CEO_		= $property->co_ceo;
$_PAGE_MANAGER_		= $property->shop_manager;
$_PAGE_SITE_NAME_EN	= $property->shop_name_en;
//-------------------------------------------------------------------------------------------------//
?>