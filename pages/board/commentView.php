<?php 
// 댓글 기능 사용시
if ( $setting->isuse_comment == "Y" ) {
?>

<form name="comment_form" action="comment_process.php" method="post">
	<input type="hidden" name="board_sid" value="<?php echo $board_sid?>" />
	<input type="hidden" name="data_sid" value="<?php echo $data_sid?>" />
	<input type="hidden" name="menu_code" value="<?php echo $menu_code?>" />
	<input type="hidden" name="mode" value="ADD" />
	<input type="hidden" name="comment_sid" value="" />
	<input type="hidden" name="comment_password" value="" />
	<input type="hidden" name="page_num_com" value="<?php echo $_GET['page_num_com']?>" />

	<div class="comment-write mgt3">

		<div class="form-group">
			<textarea name="comment_content" id="comment_content" onkeyup="check_len( this, 255 );" class="form-control" placeholder="<?php echo  $noticeMsg;?>"></textarea>
		</div>
		
		<div class="form-row">
<?php if ( is_logged() ) { ?>
			<input type="hidden" name="user_nick" value="<?php echo $_SESSION['LOGIN_NAME']?>" />
			<input type="hidden" name="user_sid" value="<?php echo $_SESSION['LOGIN_SID']?>" />
			<input type="hidden" name="user_id" value="<?php echo $_SESSION['LOGIN_ID']?>" />
<?php } else { ?>
			<input type="hidden" name="user_sid" value="0" />
			<input type="hidden" name="user_id" value="guest" />
			
			<div class="col col-lg-5 col-sm-5 col-xs-5">
				<label class="sr-only">작성자</label>
				<input type="text" name="user_nick" value="" title="작성자 입력" maxlength="20" class="form-control form-control-sm" placeholder="작성자" />
			</div>
			<div class="col col-lg-5 col-sm-5 col-xs-5">
				<label class="sr-only">비밀번호</label>
				<input id="comment_password_in" type="password" name="comment_password_in" value="" title="비밀번호 입력" maxlength="8" class="form-control form-control-sm" placeholder="비밀번호" />
			</div>
		
			
<?php } 
$noticeMsg = "";
 if ( !$board->isAvail( "comment" ) ) 
{
	if ( is_logged() )	$noticeMsg = "댓글을 등록할 권한이 없습니다!";
	else						$noticeMsg = "로그인 후 작성가능합니다!";															
}
?>


			<div class="tar">
<?php if ( $board->isAvail( "comment" ) ) { ?>
				<a href="#this" onClick="checkComment(); return false;" class="btn btn-board-01 btn-sm">등록</a>
<?php } else { ?>
<script type="text/javascript">
function boardLogin()
{
		alert("<?php echo  $noticeMsg;?>");
}
</script>
				<a href="#this" onClick="boardLogin(); return false;" class="btn btn-board-01 btn-sm">등록</a>
<?php } ?>
			</div>
		</div>

	</div>

</form>

<ul id="comm_list" class="comment-list mgt1">
	<?php echo $comment_html ?>
</ul>

				
<div class="mt-5 tac">
	<?php echo $comment_page ?>
</div>


<?php // 댓글 비번 확인 ?>
<div id="_popup_comment_pass" style="display:none">
	<div class="pop_comment_pass container">
		<div class="form-row">
			<div class="">
				<label class="sr-only">비밀번호 확인</label>
				<input type="password" name="comment_password_input" class="real_comment_password_input form-control form-control-sm" placeholder="비밀번호를 입력하세요." value="" title="비밀번호를 입력하세요." maxlength="8" />
			</div>
			<div class="pl-0">
				<a href="#this" class="del_comment_confirm btn btn-danger btn-sm">확인</a>
				<a href="#this" class="cancel_comment btn btn-secondary btn-sm">취소</a>
			</div>
		</div>
	</div>
</div>

<div id="_popup_mod_no_user" style="display:none">
	<div class="pop_comment_content container">
		<div class="mb-2">
			<textarea name="comment_content" class="comment_content_input form-control" title="댓글 내용 입력" onkeyup="check_len( this, 255 );"></textarea>
		</div>
		<div class="form-row">
			<div class="col-lg-4">
				<label class="sr-only">비밀번호 확인</label>
				<input type="password" name="comment_password" class="comment_password_input_pass form-control form-control-sm" placeholder="비밀번호를 입력하세요." value=""  title="비밀번호를 입력하세요." maxlength="8" />
			</div>
			<div class="">
				<a href="#this" class="submit_comment btn btn-danger btn-sm">수정</a>
				<a href="#this" class="cancel_comment btn btn-secondary btn-sm">취소</a>
			</div>
		</div>
	</div>
</div>

<div id="_popup_mod_user" style="display:none">
	<div class="pop_comment_content container">
		<div class="mb-2">
			<textarea name="comment_content" class="comment_content_input form-control" title="댓글 내용 입력" onkeyup="check_len( this, 255 );"></textarea>
		</div>
		<div class="text-right">
			<a href="#this" class="submit_comment btn btn-danger btn-sm">수정</a>
			<a href="#this" class="cancel_comment btn btn-secondary btn-sm">취소</a>
		</div>
	</div>
</div>


<form name="modify_comment" action="comment_process.php" method="post">
<input type="hidden" name="mode" value="MOD" />
<input type="hidden" name="comment_sid" value="" />
<input type="hidden" name="comment_content" value="" />
<input type="hidden" name="comment_password" value="" />
<input type="hidden" name="menu_code" value="<?php echo $menu_code?>" />
<input type="hidden" name="board_sid" value="<?php echo $board_sid?>" />
<input type="hidden" name="data_sid" value="<?php echo $data_sid?>" />
<input type="hidden" name="page_num_com" value="<?php echo $_GET['page_num_com']?>" />
</form>



<?php 
// 댓글 끝
}
?>