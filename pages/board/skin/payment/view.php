<link rel="stylesheet" type="text/css" href="/pages/board/css/board.css" />
<!-- <link rel="stylesheet" type="text/css" href="<?php echo __BOARD_URL?>/css/layerpop.css" /> -->
<script type="text/javascript" src="<?php echo __SYSTEM_URL?>/js/util.js"></script>
<script type="text/javascript" src="<?php echo __BOARD_URL?>/js/board.korean.js"></script>
<!-- <script type='text/javascript' src='/application/js/jquery_pop/bpopup.min.js'></script> -->
<script type="text/javascript">
function deleteArticle()
{
	var frm = document.mainform;
	if ( frm.user_pw )
	{
		if ( $.trim( frm.user_pw.value ) == "" )
		{
			alert( "비밀번호를 입력하세요!" );
			frm.user_pw.focus();
			return;
		}
	}

	if ( confirm( "게시물을 삭제하시겠습니까?" ) )
	{
		frm.action = "<?php echo  $setting->proc_url?>";
		frm.mode.value = "DEL";
		frm.submit();
	}
}

function signPayment()
{
	var url = "./setPayment.php";
	var params = { "board_sid" : "<?php echo $board_sid?>", "data_sid" : "<?php echo $data_sid?>"};
	
	$.ajax({
		url:url,
		type:"POST",
		dataType:'json',
		data:params,
		success:function(args){
			if ( args == true )
			{
				$("#payBtn").text("결재완료");
			}
		},
		
		error:function(e){
			alert( "결재 실패했습니다!" );
		}
	});
}
</script>

<form name="mainform" id="mainform" method="post" action="<?php echo $setting->write_url?>">
	<input type="hidden" name="menu_code" value="<?php echo $menu_code?>" />
	<input type="hidden" name="board_sid" value="<?php echo $board_sid?>" />
	<input type="hidden" name="mode" value="MOD" />
	<input type="hidden" name="data_depth" value="<?php echo $article->data_depth?>" />
	<input type="hidden" name="data_order" value="<?php echo $article->data_order?>" />
	<input type="hidden" name="data_sid" value="<?php echo $data_sid?>" />
	<input type="hidden" name="page_num" value="<?php echo $_PARAM['page_num']?>" />
	<input type="hidden" name="skey" value="<?php echo $_PARAM['skey']?>" />
	<input type="hidden" name="sval" value="<?php echo $_PARAM['sval']?>" />

	
	<div class="container board-view top-line">
		
		<div class="row row-title">
			<h4><?php echo $article->data_title ?></h4>
			<div class="mgt2">
				<span class="bar"><?php echo $article->user_nick?></span> <?php echo dateformat( $article->register_date, "." )?>
			</div>
		</div>

		<table>
			<thead>
				<tr>
					<?php echo $nameHtml; ?>
				</tr>
			<thead>
			<tbody>
				<tr class="tac">
					<?php echo $signHtml; ?>
				</tr>
			</tbody>
		</table>
		
		
<?php if ( $attFilesLink != ""  ) { ?>
		<div class="row row-file">
			<?php echo $attFilesLink?>
		</div>
<?php } ?>


<?php if ( !is_board_admin( $setting->user_sid ) && $board->isGuestArticle( $article->user_sid, $article->user_id ) ) { ?>
		<div class="row">
			<div class="col-2 align-self-center px-0">Password </div>
			<div class="col px-0"><input type="password" name="user_pw" class="form-control" placeholder="" /></div>
		</div>
<?php } ?>
		

		<div class="row row-content d-block">
			<?php echo  stripslashes( $article->data_content ) ?>
		</div>

		<!-- 이전글/ 다음글 -->
		<?php echo $prevNext; ?>
	</div>

</form>


<!-- 이전 다음글의 비밀번호 -->
<form name="passform" id="passform" method="post" action="<?php echo $setting->view_url?>">
	<input type="hidden" name="menu_code" value="<?php echo $menu_code?>" />
	<input type="hidden" name="board_sid" value="<?php echo $board_sid?>" />
	<input type="hidden" name="data_sid" value="" />
	<input type="hidden" name="user_pw" value="" />
</form>

<div class="_popup_pass" style="display:none;padding-top:10px;">
	<div class="pop_board_pass form-row mt-2">
		<div class="">
			<label class="sr-only">비밀번호 확인</label>
			<input type="password" name="password_input" class="real_password_input form-control form-control-sm" placeholder="비밀번호를 입력하세요." value="" title="비밀번호를 입력하세요." maxlength="8" />
		</div>
		<div class="">
			<a href="#this" class="pass_confirm btn btn-danger btn-sm">확인</a>
			<a href="#this" class="pass_cancel btn btn-secondary btn-sm">취소</a>
		</div>
	</div>
</div>

<script type="text/javascript">
	/* 목록 비밀번호 관련 함수 */
	$("a.pass_title").click(function(){
		// reset 
		passformReset();

		$(this).parent("div").append( $("div > ._popup_pass").clone("true").show() );
		document.passform.data_sid.value = $(this).attr('rel');
	});

	$(".pass_confirm").click(function(){
		$pass = $(this).parents("div.pop_board_pass").find(".real_password_input").val();
		if ( $.trim( $pass ) == "" )
		{
			alert( "비밀번호를 입력하세요!" );
			return false;
		}
		else
		{
			document.passform.user_pw.value = $pass;
			document.passform.submit();
		}
	});

	$(".pass_cancel").on("click", function(){
		passformReset();
	});

	function passformReset()
	{
		document.passform.data_sid.value = "";
		document.passform.user_pw.value = "";
		$("div .row > ._popup_pass").remove();
	}
</script>



<div class="text-center tar mgt3">
	<?php echo $userButtons['LIST'] ?> <?php echo $userButtons['REPLY'] ?> <?php echo  $userButtons['MOD'] ?>
</div>


<?php include "./commentView.php"; ?>



<script type="text/javascript">
// daum editor 첨부 이미지들
$(".txc-image").click( function(){
	zoomImage( $(this) );
});
</script>


