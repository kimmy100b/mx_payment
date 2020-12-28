<?php
$emails = @explode( "@", $article->user_email );
?>
<script type="text/javascript" src="<?php echo __SYSTEM_URL?>/js/util.js"></script>
<script type="text/javascript">
function checkform()
{
	var frm = document.mainform;

	if ( frm.category_code1.value  == "" )
	{
			alert( "분류를 선택하세요!" );
			frm.category_code1.focus();
			return;
	}


	if ( $.trim( frm.data_title.value ) == "" )
	{
		alert( "제목을 입력하세요!" );
		frm.data_title.focus();
		return;
	}
	else if ( $.trim( frm.user_nick.value ) == "" )
	{
		alert( "작성자를 입력하세요!" );
		frm.user_nick.focus();
		return;
	}

	if ( frm.user_pw )
	{
		if ( $.trim( frm.user_pw.value ) == "" )
		{
			alert( "비밀번호를 입력하세요!" );
			frm.user_pw.focus();
			return;
		}
	}
	if ( frm.spam_key )
	{
		if ( $.trim( frm.spam_key.value ) == "" )
		{
			alert( "스팸방지코드를 입력하세요!" );
			frm.spam_key.focus();
			return;
		}
	}

<?php // 메일 발송 사용시 필수
if ( $setting->isuse_mail == "Y" )
{ ?>
	if ( frm.user_email1 )
	{
		if ( $.trim( frm.user_email1.value ) == "" || $.trim( frm.user_email2.value ) == "" )
		{
			alert( "이메일을 입력하세요!" );
			frm.user_email1.focus();
			return;
		}
		else if ( !CheckEmailStr( frm.user_email1.value + "@" + frm.user_email2.value ) )
		{
			frm.user_email1.focus();
			return;
		}
		else
			frm.user_email.value = $.trim( frm.user_email1.value ) + "@" + $.trim( frm.user_email2.value );
	}
<?php } ?>

<?php // 에디터 사용시
if ( $setting->isuse_editor == "Y" ) {
?>
		// editor's function
		saveContent();
<?php } else { ?>
	if ( $.trim( frm.data_content.value ) == "" )
	{
		alert( "내용을 입력하세요!" );
		frm.data_content.focus();
		return;
	}
	else
	{
		if ( confirm("등록하시겠습니까?" ) )
			document.mainform.submit();
	}
<?php } ?>
}

/* editor의 함수에서 호출 */
function filterString( contentString )
{
	var frm = document.mainform;
	var findKey = "<?php echo  str_replace( "\r\n", "", $setting->board_ban_content ) ?>";

	if ( $.trim( findKey ) != "" )
	{
		findKeys = findKey.split( "," );
		loopCount = findKeys.length;
		// 특수문자 제거<한,영,공백만 허용>
		var expr = /[^(가-힣ㄱ-ㅎㅏ-ㅣa-zA-Z0-9)|^(\s*)|(\s*$)]/gi;
		titleVal = frm.data_title.value;
		titleVal = titleVal.replace( expr, "" );
		contentString = contentString.replace( expr, "" );
		//content = $( "#data_content" ).text();

		for ( i = 0; i < loopCount; i++ )
		{
			if ( titleVal.indexOf(findKeys[i]) > -1) {
				alert(findKeys[i] + "은(는) 금지어입니다. 등록 할 수 없습니다.");
				return false;
			}
			if ( contentString.indexOf(findKeys[i]) > -1) {
				alert(findKeys[i] + "은(는) 금지어입니다. 등록 할 수 없습니다.");
				return false;
			}
		}
	}

	return true;
}
</script>

<link rel="stylesheet" type="text/css" href="/pages/board/css/board.css" />
<form name="mainform" id="mainform" method="post" action="process.php" enctype="multipart/form-data">
	<input type="hidden" name="menu_code" value="<?php echo $menu_code?>" />
	<input type="hidden" name="board_sid" value="<?php echo $board_sid?>" />
	<input type="hidden" name="data_sid" value="<?php echo $data_sid?>" />
	<input type="hidden" name="mode" value="<?php echo $mode?>" />
	<input type="hidden" name="data_depth" value="<?php echo $data_depth?>" />
	<input type="hidden" name="attach_image" id="attach_image" value="" />
	<input type="hidden" name="attach_file" id="attach_file" value="" />
	<input type="hidden" name="previous_files_count" id="previous_files_count" value="<?php echo $previous_files_count?>" />
	<input type="hidden" name="attach_files_size" id="attach_files_size" value="<?php echo $up_files_size?>" />

	<input type="hidden" name="org_user_email" value="<?php echo $org_user_email?>" />
	<input type="hidden" name="org_user_name" value="<?php echo $org_user_name?>" />


	<div class="container board-write top-line">
		
<?php // 공지 게시 구분
if ( $setting->isuse_notice == "Y" && is_board_admin( $setting->user_sid ) ) { ?>
		<div class="row">
			<label class="col-form-label col-lg-2 text-lg-right">게시구분</label>
			<div class="col-lg-10 align-self-center">
				<input type="radio" name="data_notice_fl" value="X" <?php if ( $article->data_notice_fl == "X" || $article->data_notice_fl == "" ) echo "checked"?>  id="data_notice_fl01"><label for="data_notice_fl01">일반 게시물</label>
				<input type="radio" name="data_notice_fl" value="N" <?php if ( $article->data_notice_fl == "N" ) echo "checked"?> id="data_notice_fl02">  <label for="data_notice_fl02">공지 게시물</label>
			</div>
		</div>

<?php } else echo "<input type='hidden' name='data_notice_fl' value='X' />"; ?>
<?php if ( $setting->isuse_secret == "Y" ) { ?>
		<div class="row">
			<label class="col-form-label col-lg-2 text-lg-right">공개여부</label>
			<div class="col-lg-10 align-self-center">
				<input type="radio" name="data_secret_fl" value="N" id="data_secret_fl_y" <?php if ( $article->data_secret_fl != "Y" ) echo "checked"; ?> /><label for="data_secret_fl_y">공개</label>
				<input type="radio" name="data_secret_fl" value="Y" id="data_secret_fl_n" <?php if ( $article->data_secret_fl == "Y" ) echo "checked"; ?>/><label for="data_secret_fl_n">비공개</label>
			</div>
		</div>

<?php } else echo "<input type=\"hidden\" name=\"data_secret_fl\" value=\"N\" />"; ?>
<?php // 카테고리 
if ( count( $setting->board_category ) > 0 ) { ?>
		<div class="row">
			<label class="col-form-label col-lg-2 text-lg-right require">분류</label>
			<div class="col-lg-10 align-self-center">
				<?php echo MakeRadio( "category_code1", $setting->board_category, $cate_code1, 3 ); ?>
			</div>
		</div>
<?php } ?>
<?php
if ( ( $mode == "ADD" && !is_logged() ) || ( $mode == "MOD" && !is_board_admin( $setting->user_sid ) && $board->isGuestArticle( $article->user_sid, $article->user_id ) ) ) { ?>
		<div class="row">
			<label class="col-form-label col-lg-2 text-lg-right require">비밀번호</label>
			<div class="col-lg-10">
				<input type="password" name="user_pw" maxlength="8" value="" class="form-control" placeholder="비밀번호를 입력하세요!" title="비빌번호 입력" />
			</div>
		</div>

<?php
		// 스팸 방지키는 신규등록시만 사용
		if ( $mode == "ADD" ) {
		// spam 방지키
		$spamKeyArr = array( rand(0,9), rand(0,9), rand(0,9), rand(0,9) );
		$spamKey = AES_Encode( __AES_KEY, $spamKeyArr[0].$spamKeyArr[1].$spamKeyArr[2].$spamKeyArr[3] );
		$mixedSpamKey = str_repeat( rand(0,9), rand(1,2) ) . 
						"<span class=\"text-danger\">". $spamKeyArr[0] ."</span>". 
						str_repeat( rand(0,9), rand(1,2) ) . 
						"<span class=\"text-danger\">". $spamKeyArr[1] ."</span>". 
						str_repeat( rand(0,9), rand(1,2) ) . 
						"<span class=\"text-danger\">". $spamKeyArr[2] ."</span>". 
						str_repeat( rand(0,9), rand(1,2) ) . 
						"<span class=\"text-danger\">". $spamKeyArr[3] ."</span>". 
						str_repeat( rand(0,9), rand(1,2) );
?>
		<input type="hidden" name="spamEncode" value="<?php echo $spamKey?>" />
		<div class="row">
			<label class="col-form-label col-lg-2 text-lg-right require">스팸방지코드</label>
			<div class="col-lg-10">
				<div class="mb-3"><?php echo $mixedSpamKey; ?></div>
				<input type="text" name="spam_key" value="" maxlength="20" class="form-control" placeholder="스팸방지코드 입력" title="스팸방지코드 입력" />
				<p class="mb-0 mt-2">위의 숫자 중 <span class="text-danger">붉은색 숫자</span>만 차례대로 입력하세요.</p>
			</div>
		</div>
<?php	
	}
}
?>
		
		<div class="row">
			<label class="col-form-label col-lg-2 text-lg-right require">작성자</label>
			<div class="col-lg-10">
				<input type="text" name="user_nick" value="<?php echo $user_nick?>" <?php echo $readOnly?> maxlength="15" class="form-control" placeholder="작성자를 입력하세요!" title="작성자 입력" />
			</div>
		</div>

<?php // 메일 발송 사용시 필수
if ( $setting->isuse_mail == "Y" ) { ?>
		<div class="row">
			<label class="col-form-label col-lg-2 text-lg-right">이메일</label>
			<div class="col-lg-10 form-row">
				<div class="col-lg-4 mb-2 mb-lg-0">
					<input type="hidden" name="user_email" value="<?php echo $article->user_email?>" />
					<input type="text" name="user_email1" value="<?php echo $emails[0]  ?>" class="form-control" placeholder="이메일 아이디" title="이메일 아이디 입력" />
				</div>
				<div class="input-group col-6 col-lg-4">
					  <span class="input-group-addon" id="basic-addon1">@</span>
					  <input type="text" name="user_email2" value="" class="form-control" placeholder="이메일 도메인" title="이메일 도메인 입력" />
				 </div>
				 <div class="col-6 col-lg-4">
					<select class="email" name="email3" onChange="document.mainform.user_email2.value = this.value;">
						<option value="">직접입력</option>
						<?php echo getEmails( $emails[1] ) ?>
					</select>
				</div>
			</div>
		</div>
<?php } ?>
		
		<div class="row">
			<label class="col-form-label col-lg-2 text-lg-right require">제목</label>
			<div class="col-lg-10">
				<input type="text" name="data_title" value="<?php echo $data_title ?>" class="form-control" placeholder="제목을 입력 하세요!" title="제목 입력" />
			</div>
		</div>	
		
<?php // 에디터 사용시
if ( $setting->isuse_editor == "Y" ) {
?>
		<div class="row">
			<div class="col">
				<textarea id="data_content" name="data_content" style="display:none; width:100%"><?php echo stripslashes( $data_content )?></textarea>
			
				<?php include __BASE_PATH."/util/daumeditor-7.5.6/editor.php"; ?>
				<?php if ( trim( $data_content ) != "" ) { ?>
				<script type="text/javascript">loadContent();</script>
				<?php } ?>

			</div>
		</div>

<?php } else { ?>
		
		<div class="row">
			<label class="col-form-label col-lg-2 text-lg-right require">내용</label>
			<div class="col-lg-10">
				<textarea id="data_content" name="data_content"  class="form-control" rows="8"><?php echo stripslashes( $article->data_content )?></textarea>
			</div>
		</div>

		<div class="row">
			<label class="col-form-label col-lg-2 text-lg-right">파일첨부</label>
			<div class="col-lg-10">
				<?php
				if ( $setting->isuse_list_img == "Y" ) 
					echo $board->getUploader()->imgUploader( $attImagesRep, "files", "IMAGE_REP", 1, 50, "", "목록용 이미지" );
				if ( $setting->img_upload_count > 0 ) 
					echo $board->getUploader()->imgUploader( $attImages, "files", "IMAGE", $setting->img_upload_count, 50, "", "이미지" );
				if ( $setting->file_upload_count > 0 ) 
					echo $board->getUploader()->imgUploader( $attFiles, "files", "FILE", $setting->file_upload_count, "", "", "파일" );
				?>
			</div>
		</div>	

<?php } ?>
			

	</div>
</form>


<div class="text-center mgt5">
	<a href="#this" onClick="checkform();" class="btn btn-lg btn-board-01" role="button">확인</a>
	<a href="<?php echo $setting->list_url . $board->queryString()?>" class="btn btn-lg btn-board-02" role="button">취소</a>
</div>
