<?php
/* Ajax */
// 댓글 정보 가져오기

include $_SERVER['DOCUMENT_ROOT']."/application/default.php";
include $_SERVER['DOCUMENT_ROOT']."/application/module/board/Board.php";

$board_sid = (int)$_POST['board_sid'];
$data_sid = clean($_POST['data_sid']);

// 게시판 클래스
$board = new Board( $board_sid );

// 댓글 작성 권한 체크
Check_Page_Use( $board->_avail_level("comment") );

$comment = $board->module( "board/Comment", array( "data_sid"=>$data_sid, "db"=>&$board->db, "setting"=>&$board->setting, "config"=>&$board->config ) );

echo $comment->getComment( $_POST['comment_sid'] );
?>
