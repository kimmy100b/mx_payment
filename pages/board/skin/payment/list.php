<link rel="stylesheet" type="text/css" href="/pages/board/css/board.css" />
<script type="text/javascript" src="<?php echo __SYSTEM_URL?>/js/util.js"></script>
<script type="text/javascript" src="<?php echo __BOARD_URL?>/js/board.korean.js"></script>
<!-- <script type='text/javascript' src='/application/js/jquery_pop/bpopup.min.js'></script> -->
<!-- <link rel="stylesheet" type="text/css" href="<?php echo __BOARD_URL?>/css/layerpop.css" /> -->

<form name="mainform" action="<?php echo $setting->list_url?>" method="get">
	<input type="hidden" name="menu_code" value="<?php echo $menu_code?>" />
	<input type="hidden" name="board_sid" value="<?php echo $board_sid?>" />
	<input type="hidden" name="page_num" value="<?php echo $_GET['page_num']?>" />
	<input type="hidden" name="category_code1" value="<?php echo $_GET['category_code1']?>" />


	<!-- 게시판 카테고리(탭메뉴) -->
	<?php
	if ( count( $setting->board_category ) > 0 )
	{
	?>

	<ul class="nav nav-tabs">
		<li class="nav-item">
			<a class="nav-link <?php if ($_GET['category_code1'] == "") echo "active" ?>" href="list.php?<?php echo ReplaceQueryStr( $_SERVER['QUERY_STRING'], "category_code1", "" ) ?>">전체</a>
		</li>
	<?php 
		foreach( $setting->board_category as $key => $val ) 
		{ 
			if ( $_GET['category_code1'] == $key )	$selected = "active"; 
			else $selected = ""; 
	?>
		<li class="nav-item">
			<a class="nav-link <?php echo $selected ?>" href="list.php?<?php echo ReplaceQueryStr( $_SERVER['QUERY_STRING'], "category_code1", $key ) ?>"><?php echo $val; ?></a>
		</li>
	<?php 
		} 
	?>
	</ul>
	<?php 
	}
	?>
	<!-- //게시판 카테고리(탭메뉴) -->


	<!-- 검색 -->
	<div class=" form-group board_top_search">
		<div class="col-md-4 col-sm-4  col-xs-4 p-0">
			<select name="skey" class="form-control select-lg select-sm">
				<option value="data_title" <?php if ( $_GET['skey'] == "data_title" ) echo "selected"; ?>>제목</option>
				<option value="user_nick" <?php if ( $_GET['skey'] == "user_nick" ) echo "selected"; ?>>작성자</option>
				<option value="data_content" <?php if ( $_GET['skey'] == "data_content" ) echo "selected"; ?>>내용</option>
			</select>
		</div>
		<div class="col-md-8  col-sm-8 p-0 col-xs-8 in-search">
			<input  type="text" class="form-control" name="sval" id="search_bb" value="<?php echo $_GET['sval']?>" /> 
		</div>
		<a class="btn-search" href="#this" onClick="Search_Board();">
			<i class="fab fa-sistrix"></i>
			<span class="text-hidden">Search</span>
		</a>
	</div>
	<!-- //검색 -->


	<!-- div>Total. <?php echo number_format($board->total_count) ?></div -->
				

	<div class="">
	<!-- 게시판 리스트 -->
	<table class="default-list top-line ">
		<colgroup>
			<col width="10%" />
			<col width="" />
			<col width="15%" />
			<col width="12%" />
		</colgroup>
		<thead>
			<tr>
				<th scope="col">번호</td>
				<th scope="col">제목</td>
				<th scope="col">작성자</td>
				<th scope="col">작성일</td>
			</tr>
		</thead>
		<tbody>
		<?php echo $html; ?>
		</tbody>
	</table>
	<!-- //게시판 리스트 -->
	</div>

	
	<!-- 버튼 -->
	<div class="mgt3 text-right"> 
		<?php if ( $board->isAvail( "write" ) ) { ?>
		<a href="<?php echo $setting->write_url . $board->queryString() ?>" class="btn btn-lg btn-board-01" role="button">글쓰기</a>
		<?php } ?>
	</div>
	<!-- //버튼 -->
	
	
	<!-- 페이징 -->
	<div class="tac">
		<?php echo $page ?>
	</div>
	<!-- //페이징 -->

</form>

<form name="passform" id="passform" method="post" action="<?php echo $setting->view_url?>">
	<input type="hidden" name="menu_code" value="<?php echo $menu_code?>" />
	<input type="hidden" name="board_sid" value="<?php echo $board_sid?>" />
	<input type="hidden" name="data_sid" value="" />
	<input type="hidden" name="user_pw" value="" />
</form>



<div class="_popup_pass" style="display:none;">
	<div class="pop_board_pass form-inline justify-content-center">
		<div class="form-inline">
			<label class="col-form-label mr-2">비밀번호</label>
			<input type="password" name="password_input" class="real_password_input form-control form-control-sm" value=""  placeholder="비밀번호를 입력하세요." title="비밀번호를 입력하세요." maxlength="8" />
		</div>
		<div class="btn-area ml-lg-1"><a href="#this" class="pass_confirm btn btn-danger btn-sm">확인</a> <a href="#this" class="pass_cancel btn btn-secondary btn-sm">취소</a></div>
	</div>
</div>

<script type="text/javascript">
	/* 목록 비밀번호 관련 함수 */
	$("a.pass_title").click(function(){
		// reset 
		passformReset();

		$(this).parent("td").append( $("div > ._popup_pass").clone("true").show() );
		document.passform.data_sid.value = $(this).attr('rel');
	});

	$(".pass_confirm").click(function(){
		$pass = $(this).parents(".pop_board_pass").find(".real_password_input").val();
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
		$("td > ._popup_pass").remove();
	}
</script>