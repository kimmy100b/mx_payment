<?php
// 신규 글 등록시 연속으로 해당 초 이내 글 등록 불가
$property['new_article_interval'] = 20;

$property['pageImages'] = array(
										"FIRST"=>"첫 페이지",
										"PREV"=>"이전 페이지",
										"NEXT"=>"다음 페이지",
										"END"=>"끝 페이지",
										"CURRENT_PAGE"=>"[CURRENT_PAGE_BLOCK]"
									);

$property['query_str']	= array( "menu_code"=>"", "board_sid"=>"", "data_sid"=>"", "page_num"=>"", "skey"=>"", "sval"=>"", "category_code1"=>"" );

$property['list_url']		= "list.php";
$property['view_url']		= "view.php";
$property['write_url']		= "write.php";
$property['proc_url']		= "process.php";
// 자동 메일 발송용 스킨
$property['mail_url']		= "mail.php";

$property['write_btn']		= " <a href=\"#this\" onClick=\"[LINK]\" class=\"btn btn-lg btn-board-01\" role=\"button\">등록</a> ";
// 모바일용 버튼
if ( is_mobile( "PC" ) )
	$property['list_btn']		= " <a href=\"[LINK]\" class=\"btn btn-lg btn-board-01\" role=\"button\">목록</a> ";
else
	$property['list_btn']		= " <a href=\"[LINK]\" class=\"btn btn-lg btn-board-01\" role=\"button\">목록</a> ";

$property['modify_btn']	= " <a href=\"#this\" onClick=\"[LINK]\" class=\"btn btn-lg btn-board-02\" role=\"button\">수정</a> ";
$property['delete_btn']	= " <a href=\"#this\" onClick=\"[LINK]\" class=\"btn btn-lg btn-board-02\" role=\"button\">삭제</a> ";
//$property['reply_btn']		= " <a href=\"[LINK]\" class=\"btn btn-lg btn-board-02\" role=\"button\">답변</a> ";
//$property['delete_btn']		= "&nbsp;<a href=\"#this\" onClick=\"[LINK]\" class=\"btn btn-md-a btn-a-c\">삭제</a>&nbsp;";
//$property['reply_btn']		= "&nbsp;<a href=\"[LINK]\" class=\"btn btn-md-a btn-a-c\">답글</a>&nbsp;";

//$property['reply_list_img'] = "<img src=\"".__BOARD_URL."/images/re.gif\" alt=\"\" />";
$property['reply_list_img'] = "<span class=\"badge-reply\"></span> ";

// 신규글
$property['image_new_article'] = "<span class=\"badge badge-new pulse ml-1\">NEW</span>";
// no image
$property['image_no_photo'] = "".__BOARD_URL."/images/nophoto.gif";
// 비밀 번호
//$property['image_secret'] = "<img src=\"".__BOARD_URL."/images/lock.jpg\" alt=\"비공개 글\" />&nbsp;";
$property['image_secret'] = "<i class=\"fas fa-lock text-secondary mr-2\"></i>";

// 본문 에디터의 크기
$property['editor_size'] = 1000;

// 공지 글일 경우 row의 class
$property['NOTICE_CLS'] = "row-notice";
$property['NOTICE_NUM'] = "<span class=\"badge badge-dark mr-1 d-lg-none\">결재 문서</span>";
$property['NOTICE_ICON'] = "<span class=\"badge badge-dark mr-1 d-lg-none\">결재 전</span>";

// 게시물 목록 > 내용 row html
// [NUM] - 게시글 번호
// [TITLE] - 제목
// [LINK][/LINK] - <a href=''></a>
// [USER] - 등록자
// [DATE] - 등록일
// [COMMENT] - 댓글 수

/*
// 모바일 체크
if ( is_mobile( "PC" ) )
{
	$property['list_html_row'] = "<li>
											<dl>
												<dt>[NUM] [LINK][TITLE][/LINK][COMMENT] [NEW]</dt>
												<p class=\"col_cont\">[CONTENT] </p>
												<dd>[DATE] /</dd>
												<dd>[USER] /</dd>
												<dd>조회 : [COUNT]회</dd>
											</dl>
											<p class=\"board_arrow\">
												[LINK]<img alt=\"내용보기\" src=\"/m/images/sub04/notice_icon.gif\" />[/LINK]
											</p>
										</li>";
	$property['list_html_blank_all'] = "<li><dl><dt>no results.</dt></dl></li>";
}
else
{*/
	$property['list_html_row'] = 
					"<tr class=\"[NOTICE_CLS]\">
						<td class=\"d-none d-lg-table-cell\">
							[NUM]
						</td>
						<td class=\"tal\">
							[LINK] [NOTICE_ICON] [TITLE] <span class=\"badge-comment\">[COMMENT]</span> [NEW]
							<div class=\"hidden-lg hidden-md row-mobile\">
								<span>[USER]</span> [DATE]
							</div>
							[/LINK]
						</td>
						<td class=\"d-none d-lg-table-cell\">[USER]</td>
						<td class=\"d-none d-lg-table-cell\">[DATE]</td>
					</tr>";
	$property['list_html_blank_all'] = "<tr><td colspan=\"4\" class=\"text-center py-3\">등록된 글이 없습니다.</td></tr>";
//}

// 댓글용
$property['list_comment_row'] = 
			"<li id=\"comment_content_[SID]\" class=\"container\">
				<dl class=\"row align-items-center mgt2\">
					<dt class=\"col-md-2\">
						[USER]
					<dd class=\"col-md-10 tar col-data\"><span>[DATE]</span><span class=\"mgl2\">[BUTTON]</span></dd>
					</dt>
					<dd class=\"con_comment col-md-12 w-100\" rel=\"[SID]\" usermod=\"[USERTYPE]\">[CONETNT]</dd>
				</dl>
			</li>";
$property['list_comment_row_blank'] = "<li class=\"comment-none\">등록된 댓글이 없습니다.</li>";
														/*"<tr>
															<td><span class=\"col-blue\">[USER]</span></td>
															<td>[DATE]&nbsp;&nbsp;[BUTTON]</td>
														</tr>
														<tr id=\"comment_content_[SID]\">
															<td colspan=\"2\">[CONETNT]</td>
														</tr>";*/

// 이전 다음 게시글 스킨
$property['list_html_row_prev'] = "<div class=\"row row-next\"><div class=\"col-lg-1 px-lg-0\">이전글</div><div class=\"col-lg-11\">[LINK][TITLE][/LINK]</div></div>";
$property['list_html_row_prev_not'] ="<div class=\"row row-next\"><div class=\"col-lg-1 px-lg-0\">이전글</div><div class=\"col-lg-11\">등록된 글이 없습니다.</div></div>";

$property['list_html_row_next'] =	"<div class=\"row row-next\"><div class=\"col-lg-1 px-lg-0\">다음글</div><div class=\"col-lg-11\">[LINK][TITLE][/LINK]</div></div>";
$property['list_html_row_next_not'] = "<div class=\"row row-next\"><div class=\"col-lg-1 px-lg-0\">다음글</div><div class=\"col-lg-11\">등록된 글이 없습니다.</div></div>";

$property['delete_btn_comment'] = "<a href=\"#this\" class=\"del_comment\"><i class=\"fas fa-times\"></i></a>
												<a href=\"#this\" class=\"mod_comment\"><i class=\"fas fa-eraser ml-2\"></i></a>";


// onClick=\"deleteComment( event, [COMMENT_KEY] ); return false;\"
// onClick=\"modifyComment( event, [COMMENT_KEY] ); return false;\"
?>
