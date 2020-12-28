<?php
include $_SERVER['DOCUMENT_ROOT']."/application/default.php";
include $_SERVER['DOCUMENT_ROOT']."/application/module/board/Board.php";

$board_sid = (int)$_POST['board_sid'];
$data_sid = clean($_POST['data_sid']);
$menu_code = clean($_POST['menu_code']);
$group_sid = (int)$_POST['group_sid'];

// 게시판 클래스
$board = new Board( $board_sid );

// 메뉴정보 조회
include_once __BOARD_PATH."/menu_info.php";

// 댓글 작성 권한 체크
Check_Page_Use( $board->_avail_level("comment") );

$comment = $board->module( "board/Comment", array( "menu_code"=>$menu_code, "data_sid"=>$data_sid, "db"=>&$board->db, "setting"=>&$board->setting, "config"=>&$board->config ) );
//$comment = new Comment( $data_sid, $board->db, $board->setting );

if ( $_POST['mode'] == "ADD" ) 
{
	$result = $comment->_add();
	$msg = $board->config['lang_add_comment'];
}
else if ( $_POST['mode'] == "MOD" ) 
{
	$result = $comment->_modify($_POST['comment_sid']);
	$msg = $board->config['lang_modify_comment'];
}
else if ( $_POST['mode'] == "DEL" ) 
{
	$result = $comment->_delete($_POST['comment_sid']);
	$msg = $board->config['lang_delete_comment'];
}

if ( $result == "SUCCESS" ) 
	$msg .= $board->config['lang_success'];
else
	$msg .= $board->config['lang_fail'];

//[190125] 비밀번호 게시물일 경우 원본글의 비밀번호 누락시 댓글 등록/삭제 후 게시물 보기화면 돌아올때 에러 대응
/*echo ( "<script type=\"text/javascript\">
				alert(\"$msg\"); 
				document.location.replace(  \"".$next_url."\" );
			</script>" );*/
?>

<form name="mainform" action="<?php echo $board->setting['view_url'] ?>" method="post">
<input type="hidden" name="menu_code" value="<?php echo $menu_code ?>" />
<input type="hidden" name="board_sid" value="<?php echo $board_sid ?>" />
<input type="hidden" name="data_sid" value="<?php echo $data_sid ?>" />
<input type="hidden" name="group_sid" value="<?php echo $group_sid ?>" />
<input type="hidden" name="user_pw" value="<?php echo $_SESSION['TMP_BOARD_PASS']?>" />
</form>
<script type="text/javascript">
	alert("<?php echo $msg?>"); 
	document.mainform.submit();
</script>