<?php 
/**
 * 게시판
 *
 * modified Jan 21 2013
 * - 메일 발송시 각 게시판별 메일 수신 주소 설정
 * modified July 7 2014
 * - 목록에서 보이는 데로 순번 출력
 * modified Jan 19 2015
 * - 비밀글의 답글 일 경우. 원글 작성자도 해당 글을 볼 수 있게 수정. Board_data : org_user_sid 추가.
 * - 공지글이 본글에 다시 나오지 않게 작업
 * [2016-06.27] 이전다음 게시물 출력시 검색결과 적용
 * [18.05.23] 목록전용 이미지 사용 여부 적용
 */
if ( ! defined('__BASE_PATH')) exit('No direct script access allowed');

include_once __MODULE_PATH."/core/CoreObject.php";


class Board extends CoreObject
{
	// DB Connection Object
	var $db;
	// 환경 설정 배열
	var $setting;
	// 게시판 키
	var $board_sid;
	// 사용자 아이디
	var $user_sid;
	// FileUpload Object
	var $uploader;
	// 페이지 이미지
	var $pageImages;
	// 검색어
	var $keywords;
	// 총게시물 수
	var $total_count;

	function Board( $board_sid )
	{
		// DB Object 생성
		$this->db = $this->module( "core/DB" );
		$this->board_sid = (int)$board_sid;
		$this->user_sid = clean($_SESSION['LOGIN_SID']);
		if ( $this->board_sid == 0 ) errorMsg("존재하지 않는 게시판입니다!","NONE");
		// 게시판 환경설정 정보 로드
		$this->_setting( $this->board_sid );
		// 게시판 언어셋 정보 로드 <ex: korean-euc-kr.php>
		$this->language( strtolower( $this->setting['board_lang']."-".__CHAR_SET ) );
	}
	
	/**
	 * 게시판 설정 정보 load
	*/
	function _setting()
	{
		// 게시판 설정 정보 table 자료 fetch
		$query = "select * from board_config where board_sid = '".$this->board_sid."' and delete_state = 'N' ";
		$rs = $this->db->query( $query );
		if ( $this->db->num_rows( $rs ) > 0 )
			$this->setting = $this->db->fetch( $rs );
		else
			errorMsg("Not exist Board!","NONE");

		//---------------------------------------------------------------------------------------------------------//
		// [15.09.14] 카테고리 추가
		$category = array();
		$query = "select category_sid, category_name from board_category where board_sid = '".$this->board_sid."' order by category_order";
		if ( $rs = $this->db->query( $query ) ) 
		{
			while ( $row = $this->db->fetch( $rs ) )
				$category[ $row['category_sid'] ] = $row['category_name'];
		}
		if ( count( $category ) > 0 )
			$this->setting['board_category'] = $category;
		//---------------------------------------------------------------------------------------------------------//
		
		// skin directory/property.php 의 설정 변수를 $setting 변수로 저장
		include_once ( __BOARD_PATH."/skin/".$this->setting['skin_type']."/property.php" );
		foreach( $property as $key => $value )	$this->setting[$key] = $value;
	}

	/**
	 * 기능별 사용가능 레벨 정보
	 * param - $type : list_level / view_level / write_level / reply_level / comment_level
	 * return - array( 1, 2, 3, 4, 5 )  <사용가능 회원 레벨키 배열>
	*/
	function _avail_level( $type )
	{
		if ( $type != "" ) return explode( ",", $this->setting[ $type ."_level" ] );
	}

	/**
	 * 해당 액션 사용가능 여부
	 * param - $action<String> : 요청 액션
	 * return - true | false<Boolean> : 사용가능 회원 레벨배열에 사용자 세션레벨 포함여부
	 */
	function isAvail( $action )
	{
		if ( !in_array( $action, array( "list", "view", "write", "reply", "comment", "apply" ) ) ) 
			return false;
		return in_array( $_SESSION['LOGIN_LEVEL'], $this->_avail_level($action) );
	}

	/**
	 * 공통 검색 조건 SQL문
	 */
	function getSearchQuery() 
	{
		$searchQuery1 = "";

		if ( trim( $_GET['skey'] ) != "" && trim( $_GET['sval'] ) != "" )
		{
			$tmpKeys = explode( " ", trim( $_GET['sval'] ) );
			
			$searchQuery1 = " and ( ";
			for ( $i = 0; $i < count( $tmpKeys ); $i++ )
			{
				if ( $i > 0 ) $searchQuery1 .= " or ";

				$searchQuery1 .= " b.".clean( trim( $_GET['skey'] ) ) ." like '%".clean( trim( $tmpKeys[$i] ) )."%' ";

				$this->keywords[] = clean( trim( $tmpKeys[$i] ) );
			}
			$searchQuery1 .= " ) ";
		}
		// [15.09.14] 카테고리 선택시
		if ( trim( $_GET['category_code1'] ) != "" )
			$searchQuery1 .= " and b.category_code1 = '".clean( $_GET['category_code1'], "HTML" ) ."' ";
		// [16.02.25] 내가 쓴 게시물만 출력 여보
		if ( !is_admin() && $this->setting['ismy_article'] == "Y" ) 
			$searchQuery1 .= " and b.user_sid = '".clean( $_SESSION['LOGIN_SID'], "HTML" ) ."' ";

		//TODO : 권한, 스킨이름 같은 걸로 조건 and 조건 주기(할 수 있으면 하기)
		return $searchQuery1;
	}

	/**
	@ 게시물 목록 
	@ board_data의 인덱스 활용 : 
		where 절 처음에 사용되는 column과 order by column 의 복합인덱스 생성
		- board_data index 
		( 
			PK : data_sid 
			INDEX 1 : board_sid + data_order
		)
		1) where board_sid = 1 and .. order by data_order
	**/
	function _list()
	{
		$result	= "";		// 출력물
		$temp	= "";		// 임시
		$searchQuery1 = "";
		$searchQuery2 = "";

		// 포토갤러리 가로형
		$photoList = ( $this->setting['board_type'] == "GALLERY_H" ) ? true : false;

		// 기본 row skin
		$skin = $this->setting['list_html_row'];

		// paging variables
		$pageNum = 	( trim( $_GET["page_num"] ) != "" ) ? intval( $_GET["page_num"] )  : intval( $_POST["page_num"] );
		if ( $pageNum == 0 ) $pageNum = 1;

		//----------------------------------------------------------------------------------
		// 공지글 Start
		//
		// 갤러리 가로형은 제외
		// 1 페이지 이면서 공지글 사용시 공지글 따로 가져오는 부분
		// 1페이지에서 총 20개 게시물 출력시 공지 17개 라면 일반은 3개만 조회
		//
		//-----------------------------------------------------------------------------------
		$notice_count = 0;
		//if ( $photoList == false && $this->setting['isuse_notice'] == "Y" ) 
		if ( $photoList == false ) 
		{
			if ( $pageNum == 1 )
			{
				if($this->board_sid == "41" || $this->board_sid == "40" || $this->board_sid == "43"  || $this->board_sid == "44"  || $this->board_sid == "42"){
					
					$noticeQuery = "select b.data_sid, board_sid, data_no, data_order, data_depth, data_title, 
							 			comment_count, view_count, register_date, user_pw, 
							 			user_sid, user_id, user_nick, user_email, user_homepage, 
							 			delete_state, data_notice_fl, data_secret_fl, org_user_sid, category_code1, tmp_field1, tmp_field2, tmp_field3, tmp_field4, tmp_field5, tmp_field6, tmp_field7, tmp_field8
							 			from board_data a 
							 			inner join 
							 			(
							 				select data_sid from board_data b where board_sid = '".$this->board_sid."' and delete_state = 'N' and data_notice_fl = 'X'
							 				AND data_sid in ( select dataSid FROM sign WHERE userSid = '".$this->user_sid."' and target='Y' )
							 				order by data_order 
							 				limit 0, 20
							 			) b 
										 on ( a.data_sid = b.data_sid )";		
			}else{

				$noticeQuery	= "select data_sid, board_sid, data_no, data_order, data_depth, user_sid, user_nick, user_email, user_homepage, 
									data_title, delete_state, data_notice_fl, data_secret_fl, comment_count, view_count, register_date
									from board_data 
									where board_sid = '".$this->board_sid."' 
									and data_notice_fl = 'N' and delete_state = 'N' ";
			}


				if ( $rs = $this->db->query( $noticeQuery ) )
				{
					while ( $row = $this->db->fetch( $rs ) )
					{
						$temp = $skin;
						$result .= $this->_makeList( $temp, $row, "Y" );
						// 공지 글 수 증가
						$notice_count++;
					}
				}
			}
			 else {
				$noticeQuery	= "select count(*) count from board_data 
												where board_sid = '".$this->board_sid."' 
												and data_notice_fl = 'N' and delete_state = 'N' ";
				$row = $this->db->fetch($noticeQuery);
				$notice_count = $row['count'];
			}
		}
		//-------------------------------------------------------------------------------
		// 공지글 End
		//-------------------------------------------------------------------------------
		
		/*
		// 공지 게시물 존재시 1페이지의 게시물 출력수를 공지글 출력수 제외한 만큼 출력
		if ( $pageNum == 1 ) 
		{
			$startNum	= 0;
			$endNum		= $this->setting['board_row'] - $notice_count;
		}
		else
		{
			$startNum = ( $pageNum - 1 ) * $this->setting['board_row'] - $notice_count;
			$endNum		= $this->setting['board_row'];
		}
		*/
			$startNum = ( $pageNum - 1 ) * $this->setting['board_row'];
			$endNum		= $this->setting['board_row'];

		$searchQuery1 = $this->getSearchQuery();
		
		//---------------------------------------------------------------------------------
		// Paging Start
		//---------------------------------------------------------------------------------
		// paging query
		$queryPaging	= "select count(*) as count from board_data b
									 where b.board_sid = '".$this->board_sid."' and b.delete_state = 'N' and data_notice_fl = 'X' {$searchQuery1} ";
		$pageRow		= $this->db->fetch( $queryPaging );
		// 총 게시물 수 : 검색된 count + 공지글 count ( 1페이지에 원래보다 더 출력되는 공지글 수만큼 플러스 )
		//$totalCount		= $pageRow['count'] + $notice_count;
		$totalCount		= $pageRow['count'];
		$pageHtml		= $this->paging2( $pageNum, $totalCount, $this->setting['board_row'], $this->setting['board_page_block'], "page_num", "Q", "", $this->setting['pageImages'] );
		//---------------------------------------------------------------------------------
		// Paging End
		//---------------------------------------------------------------------------------
		
		// 총 게시물 수 전역변수
		$this->total_count = $totalCount;

		// mysql version check
		$vQuery = "select version() as version ";
		$vRow = $this->db->fetch( $vQuery );
//$vRow['version'] = "4";

		// 포토 게시판, FAQ일 경우에만 content 필드 쿼리에 포함
		if ( $this->setting['board_type'] == "GALLERY_H" || $this->setting['board_type'] == "GALLERY_V" || $this->setting['board_type_sub'] == "FAQ" ) 
			$query_content = "data_content,";


		// mysql 4.x
		if ( intval( substr( $vRow['version'], 0, 1 ) ) < 5 )
		{
			/* normal query	*/
			$query = "select b.data_sid, b.board_sid, b.data_no, b.data_order, b.data_depth, b.data_title, {$query_content}
										b.comment_count, b.view_count, b.register_date, user_pw, 
										b.user_sid, b.user_id, b.user_nick, b.user_email, b.user_homepage, 
										b.delete_state, b.data_notice_fl, b.data_secret_fl, org_user_sid, category_code1, tmp_field1, tmp_field2, tmp_field3, tmp_field4, tmp_field5, tmp_field6, tmp_field7, tmp_field8
								from board_data b 
								where b.board_sid = '".$this->board_sid."' 
										 and b.delete_state = 'N' 
										 and data_notice_fl = 'X'
										 {$searchQuery1}
								order by b.data_order 
								limit ".$startNum.", " . $endNum;
			//delete_state in ( 'N' , 'F' )
		}
		// mysql 5.x
		else
		{
			if($this->board_sid == "41" || $this->board_sid == "40" || $this->board_sid == "43"  || $this->board_sid == "44"  || $this->board_sid == "42"){
				$query = "select b.data_sid, board_sid, data_no, data_order, data_depth, data_title, 
								comment_count, view_count, register_date, user_pw, 
								user_sid, user_id, user_nick, user_email, user_homepage, 
								delete_state, data_notice_fl, data_secret_fl, org_user_sid, category_code1, tmp_field1, tmp_field2, tmp_field3, tmp_field4, tmp_field5, tmp_field6, tmp_field7, tmp_field8
								from board_data a 
								inner join 
								(
									select data_sid from board_data b where board_sid = '".$this->board_sid."' and delete_state = 'N' and data_notice_fl = 'X'
									AND data_sid in ( select dataSid FROM sign WHERE user_sid = '".$this->user_sid."' and sOrder = '1')
									order by data_order 
									limit 0, 20
								) b 
								on ( a.data_sid = b.data_sid )";
			}else{
				$query = "
							select b.data_sid, board_sid, data_no, data_order, data_depth, data_title, {$query_content}
										comment_count, view_count, register_date, user_pw, 
										user_sid, user_id, user_nick, user_email, user_homepage, 
										delete_state, data_notice_fl, data_secret_fl, org_user_sid, category_code1, tmp_field1, tmp_field2, tmp_field3, tmp_field4, tmp_field5, tmp_field6, tmp_field7, tmp_field8
							from board_data a 
							inner join 
							(
								select data_sid from board_data b where board_sid = '".$this->board_sid."' and delete_state = 'N' and data_notice_fl = 'X'
								{$searchQuery1}
								order by data_order 
								limit ".$startNum.", " . $endNum . "
							) b 
							on ( a.data_sid = b.data_sid )
							";
			}
			// join query 
			
		}

		// db query
		if ( $rs = $this->db->query( $query ) )
		{
			$count = 0;
			while ( $row = $this->db->fetch( $rs ) )
			{

				// 게시물 순서대로 출력
				//$list_num = $totalCount - $startNum - $count - $notice_count;
				$list_num = $totalCount - $startNum - $count;
				$row['listNo'] = $list_num;

				// 포토 가로형 경우
				if ( $photoList ) 
				{
					// 새로운 row 시작
					if ( $count % $this->setting['board_cell'] == 0 ) 
					{
						$result .= $this->setting['list_html_header'];
						
						// 첫번째 row 
						$temp = $this->setting['list_html_row_first'];
						$result .= $this->_makeList( $temp, $row,'',$count );						
					}
					// 한 row의 마지막 cell
					else if ( $count % $this->setting['board_cell'] == intval( $this->setting['board_cell'] - 1 ) ) 
					{
						$temp = $this->setting['list_html_row_last'];
						$result .= $this->_makeList( $temp, $row,'',$count );

						$result .= $this->setting['list_html_footer'];
					}
					// 한 row의 마지막 cell XX
					else
					{
						$temp = $this->setting['list_html_row'];
						$result .= $this->_makeList( $temp, $row,'',$count );
					}
				}

				// 일반, 포토 세로형 경우
				else
				{
					$temp = $skin;
					$result .= $this->_makeList( $temp, $row,'',$count );
				}

				$count++;
			}

			// 가로형 포토 게시판에서 한 row 의 모자라는 cell 추가
			if ( $photoList ) 
			{
				while ( $count % $this->setting['board_cell'] != 0 ) {
					$result .= $this->setting['list_html_blank'];

					if ( $count % $this->setting['board_cell'] == intval( $this->setting['board_cell'] - 1 ) ) 
						$result .= $this->setting['list_html_footer'];

					$count++;
				}
			}
		}

		// data 없을 경우 출력!
		if ( $result == "" ) $result = $this->setting['list_html_blank_all'];

		return array( "html"=>$result, "page"=>$pageHtml );
	}

	/**
	@ 목록 html 생성
	**/
	function _makeList( $temp, $row, $notice = "",$count = "" )
	{
		if ( !is_array( $row ) || count( $row ) == 0 || is_null( $row ) ) return "";

		// keyArr과 valArr의 값들이 인덱스가 일치해야 함
		$keyArr = array("[NUM]", "[CATE]", "[TITLE]", "[NEW]","[USER]","[COMMENT]","[COUNT]","[IMAGE]", "[DATE]", "[LINK]", "[/LINK]", "[CONTENT]","[USER_X]","[SID]","[NOTICE_CLS]","[YEAR]","[MON]","[DAY]","[NOTICE_ICON]","[CHECK]", "[LINK_HOMEPAGE]", "[TMP_FIELD1]", "[TMP_FIELD2]", "[TMP_FIELD3]", "[TMP_FIELD4]", "[TMP_FIELD5]", "[TMP_FIELD6]", "[TMP_FIELD7]","[STATE]");
		$valArr = array();
		
		$data_content = nl2br( stripslashes( $row['data_content'] ) );
		$row = array_map( "stripslashes", $row );
		$row = array_map( "strip_tags", $row );
		
		// 공지 글 표시
		if ( $row['data_notice_fl'] == "N" && $notice == "Y" )
			$valArr[] = $this->config['lang_notice'];
		else if( $row['data_notice_fl'] == "X" && $notice == "Y" ){
				$valArr[] = $this->setting['NOTICE_NUM'];;
		}
		else
		{
			// 게시물 보이는 순서대로
			$valArr[] = $row['listNo'];
			// 게시물 DB 순번 출력
			//$valArr[] = $row['data_no'];
		}
		// [15.09.14] 카테고리 표시
		if ( $row['category_code1'] > 0 )
			$valArr[] = "[".$this->setting['board_category'][ $row['category_code1'] ]."] ";
		else
			$valArr[] = "";

		/* 제목 세팅 */
		$tmpTitle = "";
		// 제목에 검색 결과 표시
		$tmpTitle .= cut_str( $row['data_title'], $this->setting['board_title_len'] );
		if ( is_array( $this->keywords ) )
		{
			for ( $i = 0; $i < count( $this->keywords ); $i++ ) 
			{
				$tmpTitle =preg_replace("/(".$this->keywords[$i]."(?![^<]*>))/i", "<span style='color:#ff0702; background-color:#FFFa86;'>\\1</span>", $tmpTitle); 
			}
		}
		$titleArr = $this->titleLink( $tmpTitle, $row, $temp );
		// 답글 이미지 + 제목
		$valArr[] = str_repeat( $this->setting['reply_list_img'], intval( $row['data_depth'] ) - 1 ). $titleArr['title'];
		// 신규 글 
		$valArr[]= "&nbsp;" . $this->CheckNewAritcle( $row['register_date'] );		 
		// 등록자
		if($this->board_sid == "39"){
			if ( $_SESSION['LOGIN_LEVEL'] < 3 ){
				$valArr[] = $row['user_nick'];
			}else{
				$strlen = mb_strlen($row['user_nick'], 'utf-8');
				if($strlen > 3){
					$showlen = 2;
				}
				else{
					$showlen = 1;
				}
				$valArr[] = mb_substr($row['user_nick'], 0, $showlen, 'utf-8') . str_repeat('ㅇ', $strlen-$showlen);
			}
		}else{
			$valArr[] = $row['user_nick'];
		}
		
		// 댓글 수
		if ( $this->setting['isuse_comment'] == "Y" ) 
			$valArr[] = " ".number_format($row['comment_count'])." ";
		else
			$valArr[] = "";
		// 조회 수
		$valArr[] = number_format($row['view_count']);
		// 이미지
		$valArr[] = $this->getImage( $temp, $row );
		// 날짜
		$valArr[] = substr( dateformat( $row['register_date'], "." ), 0, 10 );
		// 링크
		if ( $_SESSION['LOGIN_LEVEL'] < 3 ){
			$valArr[] = $titleArr['link'];
			$valArr[] = "</a>";
		}else{
			if($this->setting['skin_type'] == "press"){
				$valArr[] = "<a href=\"http://[TMP_FIELD3]\" class=\"btn-a btn-a-c\" target='_blank'>";
				$valArr[] = "</a>";
			}else if($this->setting['skin_type'] == "history"){
				$valArr[] = "";
				$valArr[] = "";
			}else if($this->setting['skin_type'] == "certificate"){
				$valArr[] = "<a href=\"#this\" class=\"showDetail\">";
			}else if($this->setting['skin_type'] == "gallery_slide"){
				$valArr[] = "<a href=\"javascript:move_slide(".$count.");\">";
			}else if($this->board_sid == "39"){
				$valArr[] = "";
				$valArr[] = "";
			}else{
				$valArr[] = $titleArr['link'];
				$valArr[] = "</a>";
			}
		}

		if ( $this->setting['skin_type'] == "faq_new" )
			$valArr[] = $data_content;
		// 갤러리 게시판 content
		else
			$valArr[] = cut_str( $row['data_content'], $this->setting['board_content_len'] );

		$valArr[] = string_star($row['user_nick'],1,'mid');
		$valArr[] = $row['data_sid'];

		//결재해야하는 지 여부
		if($this->board_sid == "41" || $this->board_sid == "40" || $this->board_sid == "43"  || $this->board_sid == "44"  || $this->board_sid == "42"){
			$targetQuery = "select userSid  from sign where dataSid = '".$row['data_sid']."' and target = 'Y' ";
			$targetRow = $this->db->fetch($targetQuery);
		}

		// [17.05.12] 공지 글 class
		if ( $row['data_notice_fl'] == "N" && $notice == "Y" || $this->user_sid == $targetRow['userSid'] )
			$valArr[] = $this->setting['NOTICE_CLS'];
		else
			$valArr[] = "";

		$tmpDate = substr( $row['register_date'], 0, 10 );
		$tmpDateArr = explode( "-", $tmpDate );
		// year
		$valArr[] = $tmpDateArr[0];
		// mon
		$valArr[] = $tmpDateArr[1];
		// day
		$valArr[] = $tmpDateArr[2];
		// 공지 아이콘 표시


		if ( $row['data_notice_fl'] == "N" && $notice == "Y" || $this->user_sid == $targetRow['userSid'] )
			$valArr[] = $this->setting['NOTICE_ICON'];
		else
			$valArr[] = "";

		//관리자일때 선택삭제용
		if ( is_admin() ) 
			$valArr[] = "<input type='checkbox' name='check[]' value='".$row['data_sid']."' style=\"display:block;vertical-align:middle;\" />";
		else
			$valArr[] = "";

		// 홈페이지 링크
		// 관리자 일 경우 본문 보기
		if ( is_admin() )
		{
			$valArr[] = $titleArr['link'];
		}
		// 일반인일 경우 홈페이지 링크
		else
		{
			if ( $row['user_homepage'] != "" )
			{
				if ( stripos( $row['user_homepage'], "http://" ) === false && stripos( $row['user_homepage'], "https://" ) === false )
					$valArr[] = "<a href=\"http://".str_ireplace("http://", "", $row['user_homepage'])."\" target=\"_blank\">";
				else
					$valArr[] = "<a href=\"".$row['user_homepage']."\" target=\"_blank\">";
			}
			else
				$valArr[] = "<a href=\"#none\" onclick=\"return false;\">";
		}

		$valArr[] = $row['tmp_field1'];

		$valArr[] = $row['tmp_field2'];

		$valArr[] = $row['tmp_field3'];

		$valArr[] = $row['tmp_field4'];
		
		$valArr[] = $row['tmp_field5'];

		$valArr[] = $row['tmp_field6'];

		$valArr[] = $row['tmp_field7'];

		$time = date("Y-m-d", time());

		if($time>=$row['tmp_field5']&&$time<=$row['tmp_field6']){
			$valArr[] = "접수중";
		}else{
			$valArr[] = "마감";
		}

		$temp = str_replace( $keyArr, $valArr, $temp );

		return $temp;
	}

	/* 
	 * 이미지
	 */
	function getImage( $skin, $row )
	{
		$result = "";

		if ( $this->setting['board_type'] == "NORMAL" )	return "";
		else
		{
			// [18.05.23] 목록전용 이미지 사용시 가장 최근 파일 검색
			if ( $this->setting['isuse_list_img'] == "Y" ) 
				$query = "select file_name_down from board_file where data_sid = '".clean( $row['data_sid'] )."' and file_delete_state = 'N' and file_gubun = 'IMAGE_REP' order by file_sid desc limit 1 ";
			// Youtube일 경우 유튜브의 썸네일
			else if ( $this->setting['board_type_sub'] == "YOUTUBE" && $row['user_homepage'] != "" )
			{
				$urls = explode("/",$row['user_homepage']);
				$youtube_key = array_pop( $urls );
				$result = "<img src=\"https://img.youtube.com/vi/".$youtube_key."/0.jpg\" alt=\"첨부 이미지\" />";
				return $result;
			}
			// 가장 처음 등록한 본문 이미지 파일 검색
			else
				$query = "select file_name_down from board_file where data_sid = '".clean( $row['data_sid'] )."' and file_delete_state = 'N' and file_gubun = 'IMAGE' limit 1 ";

			$rs = $this->db->query( $query );
			// 이미지 없을 경우 null 이미지
			if ( $this->db->num_rows( $rs ) == 0 ) 
				return "<span class=\"no-image\">No Image</span>";

			$row = $this->db->fetch( $rs );
			$img_file = $row['file_name_down'];

			// 썸네일 사용시
			if ( $this->setting['isuse_thumbnail'] == "Y" ) 
			{
				/* [16.06.02] 사용방식 변경
				// 실제 이미지 주소
				$img_file_arr = explode( "/", $img_file );
				// $img_file_arr에서 파일명만 추출한 결과
				$file_end_name = array_pop( $img_file_arr );
				//$result = "<img src=\"imgView.php?image=".str_replace( "/", "/thumb_", $img_file )."\" alt=\"첨부 이미지\" />";
				$result = "<img src=\"".__FILE_DIR."board/". join( "/", $img_file_arr ) . "/thumb_".$file_end_name ."\" alt=\"첨부 이미지\" />";
				*/

				//$result = "<img src=\"".__FILE_DIR."board/". getThumbUrl($img_file, is_mobile("PC") ) ."\" alt=\"첨부 이미지\" />";
				// [18.09.28] 모바일 목록에서 PC 썸네일 이미지 사용. 상세에서는 mobile 이미지 사용
				$result = "<img src=\"".__FILE_DIR."board/". getThumbUrl($img_file, false ) ."\" alt=\"첨부 이미지\" />";
			}
			// 썸네일 미사용시 원본 이미지를 썸네일 크기로 resizing해서 보여줌
			else
			{
				$reSize = imgReSize( _FILE_UPLOAD_ROOT."board/" . $img_file, $this->setting['thumbnail_width'], $this->setting['thumbnail_height'] );
				//$result = "<img src=\"imgView.php?image=".$img_file."\" alt=\"첨부 이미지\" width=\"".$reSize['width']."\" height=\"".$reSize['height']."\" />";
				$result = "<img src=\"".__FILE_DIR."board/".$img_file."\" alt=\"첨부 이미지\" width=\"".$reSize['width']."\" height=\"".$reSize['height']."\" />";
			}
		}
		
		return $result;
	}

	/* 
	 * 제목 링크 설정
	 */
	function titleLink( $data_title, $row )
	{
		$link_url = "<a href=\"#this\" title=\"비공개 게시물\" class=\"link_secret\">";

		/*
		 * 필수 쿼리 스트링 생성
		 * /skin/property.php -> $property['query_str'] 항목에 기본 파라미터로 지정된 항목 사용
		 */
		//$requireQuery = $this->setting['query_str'];

		// 쿼리 스트링 값 배열
		/*$valueQuery = array( "board_sid"=>$this->board_sid, "data_sid"=>$row['data_sid'] );
		$queryString = setQueryStr( $requireQuery, $valueQuery );*/
		
		// [12.12.17 변경]
		$queryString = $this->queryString($row['data_sid']);

		// 비밀글 일 경우
		if ( $row['data_secret_fl'] == "Y" )
		{
			// 회원 작성글
			if ( is_board_admin( $this->setting['user_sid'] ) || $row['user_sid'] == $_SESSION['LOGIN_SID'] )
				$link_url = "<a href=\"".$this->setting['view_url']."".$queryString."\" title=\"".addslashes(str_replace("\"","", $row['data_title'] ))." - ".$this->config['lang_view_article']."\">";

			// [2015-01-19] 비밀글의 답글 일 경우. 원글 작성자도 해당 글을 볼수 있어야 함.
			else if ( trim( $row['org_user_sid'] ) == $_SESSION['LOGIN_SID']  )
				$link_url = "<a href=\"".$this->setting['view_url']."".$queryString."\" title=\"".addslashes(str_replace("\"","", $row['data_title'] ))." - ".$this->config['lang_view_article']."\">";

			// [2013-02-08]비밀번호 존재시 무조건 비밀번호 팝업
			// 비회원 작성 글 -> 비번 입력 창 팝업
			else if ( trim( $row['user_pw'] ) != "" || $this->isGuestArticle( $row['user_sid'], $row['user_id'] ) )
				$link_url = "<a href=\"#this\" title=\"".addslashes(str_replace("\"","", $row['data_title'] ))." - 게시글의 비밀번호 입력\" class=\"pass_title\" rel=\"".$row['data_sid']."\">"; 

			// 비밀번호 이미지
			$link_url .= $this->setting['image_secret'];
		}
		// 공개 글
		else{
			//$link_url = "<a href=\"".$this->setting['view_url']."".$queryString."\" title=\"".addslashes(str_replace("\"","", $row['data_title'] ))." - ".$this->config['lang_view_article']."\">";		
			
			if ( $_SESSION['LOGIN_LEVEL'] < 3 ){
				$link_url = "<a href=\"".$this->setting['view_url']."".$queryString."\" title=\"".addslashes(str_replace("\"","", $row['data_title'] ))." - ".$this->config['lang_view_article']."\">";		
			}else{
				if($this->setting['skin_type'] == "history"){
					$link_url = "";
				}else{
					$link_url = "<a href=\"".$this->setting['view_url']."".$queryString."\" title=\"".addslashes(str_replace("\"","", $row['data_title'] ))." - ".$this->config['lang_view_article']."\">";		
				}
			}
		}

		return array( "title"=>$data_title, "link"=>$link_url );
	}

	/** 
	@ 게시물 등록
	@ 트랜잭션 : InnoDb Row Level Lock
						게시물 고유번호를 구하기 위해 해당되는 게시판의 항목들(row)에 대해 update lock 생성
	**/
	function _add()
	{
		// 연속 글 등록 방지
		$this->CheckDupWrite();
	
		/*
		 * 방문자 글 작성 가능 게시판 
		 * 방문자 일 경우 user_sid = 0, user_id = guest 로 기본 설정
		 */
		if ( !is_logged() ) 
		{
			// 방문자 글쓰기 가능시
			if ( strpos( $this->setting['write_level'], __USER_GUEST ) !== false )
			{
				$iUserSid  = 0;
				$iUserId	= "guest";
				// 비밀 번호 입력 검증
				if ( trim( $_POST['user_pw'] ) == "" && $this->board_sid != "34" ) 
					 postBack( $this->config['lang_require_password'], "write.php" );
				// 스팸방지 코드 검증
				if ( trim( $_POST['spamEncode'] ) != "" && AES_Decode( __AES_KEY, $_POST['spamEncode'] ) != $_POST['spam_key'] ) 
					 postBack( $this->config['lang_spamkey_not_match'], "write.php" );
			}
			// 방문자 글쓰기 불가
			else	postBack( $this->config['lang_error_write'], "write.php" );

		}
		// 회원 글쓰기
		else
		{
			$iUserSid		= clean($_SESSION['LOGIN_SID']);
			$iUserId		= clean($_SESSION['LOGIN_ID']);
		}

		// 필수 입력 필드 체크
		$this->requireFields( array( "user_nick", "data_title", "data_content" ), "write.php" );

		// 금지어 체크
		$this->filter();
		
		$content_org = $_POST['data_content'];
		if($this->setting['isuse_xxs'] == "Y")
			$content_org_option = xss_filter(addslashes($content_org), "");
		else
			$content_org_option = xss_filter(addslashes($content_org), "DEL");

		// post sql injection 
		$_POST = clean( $_POST );

		/* 트랜잭션 시작 */
		//$this->db->startTrans();

		// 원본 글 등록
		if ( (double)$_POST['data_sid'] == 0 )
		{
			// 해당 게시판 그룹의 max( data_no ) 구하기		
			$query = "select max(data_no) maxNo, min(data_order) minOrder from board_data 
							where board_sid = '".$this->board_sid."' 
							for update";
			$row = $this->db->fetch( $query );
			$data_no		= intval( $row['maxNo'] + 1 );
			$data_order = intval( $row['minOrder'] - 1 );
			
			// 원본 글 작성시는 입력한 그대로
			$user_pw				=	$_POST['user_pw'];
			$data_secret_fl	=	strip_tags($_POST['data_secret_fl']);
			//[2015.01.19] 원글의 작성자 키 저장
			$org_user_sid	= $iUserSid;
		}
		// 답글 등록
		else
		{
			// 원본(다음) 글의 정보 / 비밀번호 / 공개 여부
			$query1 = "select data_order, user_pw, data_secret_fl, user_sid from board_data where data_sid = '".(double)$_POST['data_sid']."' ";
			$row1 = $this->db->fetch( $query1 );
			$prevDataOrder = $row1['data_order'];
			// 이전 글의 정보
			$query2 = "select data_order from board_data where board_sid = '".$this->board_sid."' and data_order > ".$prevDataOrder." limit 1 ";
			$row2 = $this->db->fetch( $query2 );
			$nextDataOrder = (double)$row2['data_order'];
			
			// 다음 글 순서 + 이전 글 순서 / 2 => 현재 글 순서
			$data_order = ( $prevDataOrder + $nextDataOrder ) / 2;

			// 해당 게시판 그룹의 max( data_no ) 구하기		
			$query = "select max(data_no) maxNo from board_data 
							where board_sid = '".$this->board_sid."' 
							for update";
			$row = $this->db->fetch( $query );
			$data_no		= intval( $row['maxNo'] + 1 );

			// 답글 작성시는 원본 글의 비번/공개옵션 속성
			$user_pw			=	$row1['user_pw'];
			$data_secret_fl	=	$row1['data_secret_fl'];
			//[2015.01.19] 원글의 작성자 키 저장
			$org_user_sid	= $row1['user_sid'];
		}

		// 삽입
		//data_content		=	'".xss_filter( $_POST['data_content'] )."',
		$query = "insert into board_data set
							board_sid			= '".$this->board_sid."',
							data_no				= '".$data_no."',
							data_order			= '".$data_order."', 
							data_depth			=	'".intval($_POST['data_depth'])."',
							user_sid				=	'".$iUserSid."',
							org_user_sid			= '".$org_user_sid."',
							user_id				=	'".$iUserId."',
							user_pw				=	'".$user_pw."',
							user_nick				=	'".strip_tags($_POST['user_nick'])."',
							user_ip				=	inet_aton('".$_SERVER['REMOTE_ADDR']."'),
							user_email			=	'".strip_tags($_POST['user_email'])."',
							user_homepage	=	'".strip_tags($_POST['user_homepage'])."',
							data_title				=	'".strip_tags($_POST['data_title'])."',
							delete_state			= 'N',
							data_content		=	'".$content_org_option."',
							data_notice_fl		=	'".strip_tags($_POST['data_notice_fl'])."',
							data_secret_fl		=	'".strip_tags($data_secret_fl)."',
							register_date		=	now(),
							category_code1		=	'".strip_tags($_POST['category_code1'])."',
							tmp_field1			=	'".strip_tags($_POST['tmp_field1'])."',
							tmp_field2			=	'".strip_tags($_POST['tmp_field2'])."',
							tmp_field3			=	'".strip_tags($_POST['tmp_field3'])."',
							tmp_field4			=	'".strip_tags($_POST['tmp_field4'])."',
							tmp_field5			=	'".strip_tags($_POST['tmp_field5'])."',
							tmp_field6			=	'".strip_tags($_POST['tmp_field6'])."',
							tmp_field7			=	'".strip_tags($_POST['tmp_field7'])."',
							tmp_field8			=	'".strip_tags($_POST['tmp_field8'])."',
							start_date			=	'".strip_tags($_POST['start_date'])."',
							end_date				=	'".strip_tags($_POST['end_date'])."' ";

		$result = $this->db->query( $query );
		

		if ( $result )	
		{
			$insertKey = $this->db->insert_id();

			// sign DB에 저장
			if($this->board_sid == "41" || $this->board_sid == "40" || $this->board_sid == "43"  || $this->board_sid == "44"  || $this->board_sid == "42")
			$this->addSign($insertKey);

			/* [180615] 미사용처리
			if ( $this->updateFileInfo($insertKey) ) 
			{
				$this->db->commit();
				$this->_mail($content_org);
			}
			else
				$this->db->rollback();
			*/

			// [180616] 모바일 혹은 일반 파일 업로드 처리
			if ( is_mobile( "PC" ) || $this->setting['isuse_editor'] == "N" )
				$this->getUploader()->DoUploadNormal( $this->board_sid, "files", $insertKey, true );
			// 에디터 파일 이미지 처리
			else if ( $this->setting['isuse_editor'] == "Y" )
				$this->updateFileInfo($insertKey);
			
			$this->_mail($content_org);

			return "SUCCESS";
		}
		else 
		{
			//$this->db->rollback();
			return "FAIL";
		}
	}

	//권한자 구하기
    function getPermit()
    {
        //부서 구하기
        $dpQuery = "select dpParent from department where userSid = {$this->user_sid} and delState = 'N'";
        $dpRs = $this->db->fetch( $dpQuery );
        
        //팀장 구하기
        $tQuery = "select userSid from department where dpParent = '".$dpRs['dpParent']."' and delState = 'N' ";
        if ( $tRs = $this->db->query( $tQuery ) )
		{
            while( $tRow = $this->db->fetch($tRs) )
			{
				$userQuery = "select userSid, permit from user where userSid = '".$tRow['userSid']."' and delState = 'N'";
				$userRs = $this->db->fetch( $userQuery );
				
                if($userRs['permit']=="Y")
                {
                    return $userRs['userSid'];
                }
            }
        }

        $dpQuery = "select dpParent from department where dpSid = '".$dpRs['dpParent']."'";
        $dpRs = $this->db->fetch( $dpQuery );
        
        //팀장 구하기(연수원, 용인 부분)
        $tQuery = "select userSid from department where dpParent = '".$dpRs['dpParent']."'";
        if ( $tRs = $this->db->query( $tQuery ) )
		{
            while( $tRow = $this->db->fetch($tRs) )
			{
                $userQuery = "select userSid, permit from user where userSid = '".$tRow['userSid']."'";
                $userRs = $this->db->fetch( $userQuery );

                if($userRs['permit']=='Y')
                {
                    return $userRs['userSid'];
                }
            }
		}
		
		return false;
	}
	
	//Sign DB 등록
    function addSign($dataSid)
    {

        $order = 1;
        //결재 서류 신청자 등록
        
        $aquery = "insert into sign set 
                        dataSid = '".$dataSid."',
                        userSid = {$this->user_sid},
                        flag = 'Y',
						sOrder = ".$order.",
						target = 'N',
						regDate = now(),
						modDate = now()";
						
        if($this->db->query( $aquery )){
            $order++;
        }else{
            errorMsg("등록 실패");
		}
		
		
        //팀장, 결재 권한자 등록
        $tuser = $this->getPermit();
		
		// 현재 년도 구하기
		$year =  date("Y");
		
        $bquery = "insert into sign set 
                        dataSid = '".$dataSid."',
                        userSid = '".$tuser."',
                        flag = 'N',
						sOrder = ".$order.",
						target = 'Y',
						regDate = now()";

        if($this->db->query( $bquery )){
            $order++;
        }else{
            errorMsg("등록 실패");
		}

        //경영지원부 결재 권한자 등록
        $userQuery = "select userSid from department where delState = 'N' and dpLevel = 2 and morder1 = 001 and dpYear = '".$year."' order by dpCode desc";
      
        if ( $userRs = $this->db->query( $userQuery ) )
		{
            while( $userRow = $this->db->fetch($userRs) )
			{
                $cquery = "insert into sign set
                                dataSid = '".$dataSid."',
                                userSid = '".$userRow['userSid']."',
								flag = 'N',
								target = 'N',
                                sOrder = ".$order.",
                                regDate = now()";

                if($this->db->query( $cquery )){
                    $order++;
                }else{              
                    errorMsg("등록 실패");
                }
            }
        }

    }

	// 메일 발송
	function _mail( $content )
	{
		if ( $this->setting['isuse_mail'] == "N" ) return;

		// 사이트 설정 정보 조회
		$propertyObj = $this->library( "SiteProperty", "site" );
		$property = $propertyObj->_load();

		// [2013-01-21]이메일 주소. 원본 글 작성자가 관리자에게 발송
		if ( (double)$_POST['data_sid'] == 0 )
		{
			$to_email = trim( $this->setting['mail_address'] );
			if ( $to_email == "" ) $to_email = trim( $property->shop_mail );
			$to_name = trim( $property->shop_name );
		}
		// [2013-01-21]이메일 주소. 답글 작성자(관리자)가 회원에게 발송
		else
		{
			$to_email = trim($_POST['org_user_email']);
			$to_name = trim($_POST['org_user_name']);
		}

		if ( trim( $property->shop_mail ) != "" && $to_email != "" && trim( $_POST['user_email'] ) != "" )
		{
			$mail = $this->library( "PHPMailer" );

			$body = $mail->getFile( __BOARD_PATH."/skin/". $this->setting['skin_type']."/". $this->setting['mail_url'] );
			$body = @eregi_replace("[\]",'',$body);
			//$body = preg_replace("[\]",'',$body);
			
			$findArr = array( "[data_title]", "[user_nick]", "[register_date]", "[user_email]", 
										"[tmp_field1]", "[tmp_field2]", "[tmp_field3]", "[tmp_field4]", "[tmp_field5]", "[data_content]","[state]" );
			$replaceArr = array( strip_tags($_POST['data_title']), 
											strip_tags($_POST['user_nick']), 
											date("Y-m-d H:i:s"), 
											strip_tags($_POST['user_email']), 
											str_replace( "@|@", "/", strip_tags($_POST['tmp_field1']) ), 
											strip_tags($_POST['tmp_field2']), 
											strip_tags($_POST['tmp_field3']), 
											strip_tags($_POST['tmp_field4']), 
											strip_tags($_POST['tmp_field5']), 
											nl2br( $content ) );
			$body = str_replace( $findArr, $replaceArr, $body );

			//$mail->IsSMTP(); // telling the class to use SMTP
			/*$mail->Host     = ""; // SMTP server
			$mail->Username = "";
			$mail->Password = "";*/

			$mail->From				= strip_tags($_POST['user_email']);
			$mail->FromName		= strip_tags($_POST['user_nick']);
			$mail->Sender			= strip_tags($_POST['user_email']);
			$mail->Subject			= strip_tags($_POST['data_title']);
			$mail->AltBody		= "";
			$mail->MsgHTML($body);

			// 받는 사람 주소
			$mail->AddAddress( $to_email, $to_name );
			if ( !$mail->Send() ) $error = "Mailer Error: " . $mail->ErrorInfo;
			$mail->delAddress();
		}
	}

	/**
	 * 게시물 수정
	 */
	function _mod( $data_sid )
	{
		$data_sid = clean( $data_sid );

		// 필수 입력 필드 체크
		$this->requireFields( array( "user_nick", "data_title", "data_content" ), "write.php" );

		// 금지어 체크
		$this->filter();

		$content_org = $_POST['data_content'];

		if($this->setting['isuse_xxs'] == "Y"){
			$content_org_option = xss_filter(addslashes($content_org), "");
		}else{
			$content_org_option = xss_filter(addslashes($content_org), "DEL");
		}

		$_POST	= clean( $_POST );

		/* 게시물 owner 체크 */
		$query = "select data_sid, board_sid, user_sid, user_id, user_pw, user_nick, data_notice_fl, data_secret_fl 
						from board_data where data_sid = '".$data_sid."' ";
		// 글 정보 없을 경우 error
		if ( $this->db->num_rows( $query ) == 0 )
			errorMsg( $this->config['lang_error_exist'], "BACK" );
		// 게시물 정보 row
		$row = $this->db->fetch( $query );
		// 수정가능 권한 & 레벨 체크
		$isMineRes = $this->isMine( $row, "MOD", $_POST['user_pw'], "" );
		$isMine = $isMineRes['isMine'];
		$isAvail = $isMineRes['isAvail'];

		if($this->board_sid == "33"){
			$tmp_field7 = "";
			for($i=0;$i<count($_POST['tmp_field7']);$i++){
				if($i==0){
					$tmp_field7.= $_POST['tmp_field7'][$i];
				}else{
					$tmp_field7.= ",".$_POST['tmp_field7'][$i];
				}
			}
		}else{
			$tmp_field7 = strip_tags($_POST['tmp_field7']);
		}

//							data_content		=	'".xss_filter( $_POST['data_content'] )."',
		// 삽입
		$query = "update board_data set
							user_email			=	'".strip_tags($_POST['user_email'])."',
							user_homepage		=	'".strip_tags($_POST['user_homepage'])."',
							data_title				=	'".strip_tags($_POST['data_title'])."',
							data_content		=	'".$content_org_option."',
							data_notice_fl		=	'".strip_tags($_POST['data_notice_fl'])."',
							data_secret_fl		=	'".strip_tags($_POST['data_secret_fl'])."',
							modify_date			=	now(),
							category_code1		=	'".strip_tags($_POST['category_code1'])."',
							tmp_field1			=	'".strip_tags($_POST['tmp_field1'])."',
							tmp_field2			=	'".strip_tags($_POST['tmp_field2'])."',
							tmp_field3			=	'".strip_tags($_POST['tmp_field3'])."',
							tmp_field4			=	'".strip_tags($_POST['tmp_field4'])."',
							tmp_field5			=	'".strip_tags($_POST['tmp_field5'])."',
							tmp_field6			=	'".strip_tags($_POST['tmp_field6'])."',
							tmp_field7			=	'".$tmp_field7."',
							tmp_field8			=	'".strip_tags($_POST['tmp_field8'])."',
							start_date			=	'".strip_tags($_POST['start_date'])."',
							end_date				=	'".strip_tags($_POST['end_date'])."' 
						where data_sid = '".$data_sid."' ";

		if ( $this->db->query( $query ) )
		{

			// 모바일 혹은 일반 파일 업로드 처리
			if ( is_mobile( "PC" ) || $this->setting['isuse_editor'] == "N" )
				$this->getUploader()->DoUploadNormal( $this->board_sid, "files", $data_sid, true );
			/* 에디터 파일 업로드/수정 처리 */
			else if ( $this->setting['isuse_editor'] == "Y" )
			{
				// 파라미터로 넘어오는 attach_image, attach_file의 키 값을 비교. 넘어온 키 값만 유효. DB에 없는 항목은 삭제 처리
				$file_keys = $this->getUploadKeys();

				if ( $_POST['previous_files_count'] > 0 || trim( $file_keys ) != "" ) 
				{
						// 추가된 파일이 있다면 승인 처리
						$this->updateFileInfo($data_sid, $file_keys);

						// Uploader Object
						$uploader = $this->getUploader();

						// 이전에 등록된 파일이 1개 이상이면서 파일key 가 없다는 것은 전체 삭제
						if ( $_POST['previous_files_count'] > 0 && trim( $file_keys ) == "" )
							$delQuery = "select file_sid from board_file where data_sid = '".$data_sid."' ";
						// 유효한 키 값이 아닌 파일 정보를 삭제
						else
							$delQuery = "select file_sid from board_file where data_sid = '".$data_sid."' and file_sid not in ( $file_keys ) ";

						if ( $delRs = $this->db->query( $delQuery ) )
						{
							while ( $delRow = $this->db->fetch( $delRs ) )
								$uploader->deleteInfoOne( $this->board_sid, $delRow['file_sid'] );
						}
				}
			}

			return "SUCCESS";
		}
		return "FAIL";
	}

	/*
	 * 게시물 삭제
	 *
	 */
	function _delete( $data_sid )
	{
		/* 게시물 owner 체크 */
		$query = "select data_sid, board_sid, user_sid, user_id, user_pw, user_nick, data_notice_fl, data_secret_fl 
						from board_data where data_sid = '".$data_sid."' ";
		// 글 정보 없을 경우 error
		if ( $this->db->num_rows( $query ) == 0 )
			errorMsg( $this->config['lang_error_exist'], "BACK" );
		// 게시물 정보 row
		$row = $this->db->fetch( $query );
		// 수정가능 권한 & 레벨 체크
		$isMineRes = $this->isMine( $row, "MOD", $_POST['user_pw'], "" );
		$isMine = $isMineRes['isMine'];
		$isAvail = $isMineRes['isAvail'];

		$query = "update board_data set delete_state = 'D' where data_sid = '".clean( $data_sid )."' ";
		if ( $this->db->query( $query ) )
		{
			// 파일 DB 지움 상태 변경
			//$query2 = "update board_file set file_delete_state = 'D' where data_sid = '".clean( $data_sid )."' ";
			//$this->db->query( $query2 );
			
			// 파일을 실제로 지움
			$this->getUploader()->deleteInfo( $this->board_sid, $data_sid );

			return "SUCCESS";
		}
		else
			return "FAIL";
	}

	/**
	 * 게시물 정보
	 * param : data_sid - 게시물 고유키
	 *				mode - MOD		: 글 수정시 작성자 확인
	 *							VIEW	: 글 조회시 조회수 증가
	 *				input_password : 입력한 비밀번호
	 *				type	: none - 글 조회용, CHECK - 작성자 체크용
 	 * [2015.01.19] org_user_sid 필드 추가
	 */
	function getData( $data_sid, $mode, $input_password, $type = "" )
	{
		if ( trim( $data_sid ) == "" ) errorMsg( $this->config['lang_error_exist'], "BACK" );

		//, tmp_field1, tmp_field2, tmp_field3, tmp_field4, tmp_field5, start_date, end_date 
		$query = "select data_sid, board_sid, data_no, data_order, data_depth, user_sid, user_id, user_pw, user_nick, user_email, user_homepage, 
									data_title, delete_state, data_content, data_notice_fl, data_secret_fl, comment_count, view_count, register_date, modify_date, category_code1, tmp_field1, tmp_field2, tmp_field3, tmp_field4, tmp_field5, tmp_field6, tmp_field7, tmp_field8,
									inet_ntoa( user_ip ) as user_ip , start_date
									,org_user_sid
						from board_data 
						where data_sid = '".$data_sid."' and delete_state = 'N' ";
		
		// 글 정보 없을 경우 error
		if ( $this->db->num_rows( $query ) == 0 )
			errorMsg( $this->config['lang_error_exist'], "BACK" );

		// 게시물 정보 row
		$row = $this->db->fetch( $query );
		$row = array_map( "stripslashes", $row );

		// [15.10.05] 모바일용 content 이미지 변환
		//if ( is_mobile( "PC" ) )
		//	$row['data_content'] = str_replace( "/board_".$this->board_sid."/", "/board_".$this->board_sid."/_mobile_", $row['data_content'] );

		// 본인 글 확인 
		$isMineRes = $this->isMine( $row, $mode, $input_password, $type );
		$isMine = $isMineRes['isMine'];
		$isAvail = $isMineRes['isAvail'];

		if ( !$isMine )
		{
			// 조회수 증가
			if ( $mode == "VIEW" )
			{
				// 중복 조회수 증가 방지
				if ( $_COOKIE['BOARD_COUNT_'.$data_sid] == "" )
				{
					// 10분 후 만료 쿠키 세팅
					$cookie = setcookie("BOARD_COUNT_".$data_sid, time(),time()+60*10);

					$cQuery = "update board_data set view_count = view_count + 1 where  data_sid = '".$data_sid."' ";
					$this->db->query( $cQuery );

					// row 객체 조회수 필드 증가
					$row['view_count'] = intval( $row['view_count'] ) + 1;
				}
			}
		}

		// 파일 다운로드에서 단순 본인글 확인용으로 사용시
		if ( $type == "CHECK" ) return $isAvail;
		
		// 모드별 가능 버튼 > 게시물 정보에 추가
		$buttons = $this->getButtons( $mode, $isMine );
		$row['buttons'] = $buttons;

		/*
		 * 이미지/파일 첨부 정보 추가
		 */
		$images_rep	= array();
		$images	= array();
		$files		= array();
		$fileSizeSum = 0;
		$query = "select file_sid, data_sid, board_sid, file_name_down, file_name_org, file_size, file_down_count, file_gubun, image_width
						from board_file 
						where data_sid = '".$data_sid."' and board_sid = '".$this->board_sid."' ";
		if ( $file_rs = $this->db->query( $query ) )
		{
			while ( $file_row = $this->db->fetch( $file_rs ) )
			{
				$fileSizeSum += intval($file_row['file_size']);	//kb
				if ( $file_row['file_gubun'] == "IMAGE_REP" ) 
					$images_rep[] = $file_row;
				else if ( $file_row['file_gubun'] == "IMAGE" ) 
					$images[] = $file_row;
				else
					$files[] = $file_row;
			}
		}
		// 게시물 정보 + 파일첨부 정보
		$row['attach_image_rep']	= $images_rep;
		$row['attach_image']	= $images;
		$row['attach_file']		= $files;
		$row['attach_fileSize']	= $fileSizeSum;

		// object 구조로 변경해서 return
		return (object)$row;
	}

	/*
	 * 이전 다음 게시물
	 * data_order 의 이전 앞
	 * [2015-01-19] org_user_sid 필드 추가
	 */
	function getPrevNext( $data_sid, $data_order )
	{
		$result = "";
		
		// 다음글
		$skin = $this->setting['list_html_row_next'];
		$query = "select  data_sid, board_sid, data_no, data_order, data_depth, user_sid, user_id, user_nick, user_email, user_homepage, data_title,
									delete_state, data_notice_fl, data_secret_fl, comment_count, view_count, register_date, user_pw, org_user_sid 
						from board_data b
						where board_sid = '".$this->board_sid."' and data_order < ".clean($data_order)." and delete_state = 'N' " . $this->getSearchQuery() ."
						order by data_order desc limit 1 ";
		$row = $this->db->fetch( $query );
		$temp = $this->_makeList( $skin, $row );
		if ( $temp == "" ) $temp = $this->setting['list_html_row_next_not'];
		$result = $temp;

		// 이전글
		$skin = $this->setting['list_html_row_prev'];
		$query = "select  data_sid, board_sid, data_no, data_order, data_depth, user_sid, user_id, user_nick, user_email, user_homepage, data_title,
									delete_state, data_notice_fl, data_secret_fl, comment_count, view_count, register_date, user_pw, org_user_sid 
						from board_data b
						where board_sid = '".$this->board_sid."' and data_order > ".clean($data_order)." and delete_state = 'N' " . $this->getSearchQuery() ." limit 1 ";
		$row = $this->db->fetch( $query );
		$temp = $this->_makeList( $skin, $row );
		if ( $temp == "" ) $temp = $this->setting['list_html_row_prev_not'];
		$result .= $temp;

		return $result;
	}

	/* 
	 * 필수 입력 항목 체크
	 * param : $reqires<array>	- 필수 입력항목 필드 name
	 *				$url<string>		- 에러시 이동 url
	 */
	function requireFields( $requires, $url )
	{
		$_PARAM = $_GET;
		$method	= "get";
		if ( $_SERVER['REQUEST_METHOD'] == "POST" )
		{
			$_PARAM = $_POST;
			$method	= "post";
		}

		for ( $i = 0; $i < count( $requires ); $i++ )
		{
			if ( trim( $_PARAM[$requires[$i]] ) == "" ) 
			{
				if ( $method == "get" )
				{
					errorMsg( $this->config['lang_require_'.$requires[$i]], "BACK" );
					exit;
				}
				else if ( $method == "post" )
				{
					postBack( $this->config['lang_require_'.$requires[$i]], $url );
					exit;
				}
			}
		}
	}

	/**
	 * 에디터 파일 업로드
	 */
	function uploadFile( $board_sid, $file_name, $file_gubun, $data_sid )
	{
		$uploader = $this->getUploader();
		$result = $uploader->DoUpload( $board_sid, $file_name, $file_gubun, $data_sid );
		return $result;
	}

	/**
	 * 에디터 임시 저장된 파일 승인
	 * - data_sid = 0 이면서 해당 게시판 그룹의 첨부파일 정보
	 */
	function updateFileInfo( $data_sid, $file_keys = "" ) 
	{
		if ( $file_keys == "" ) $file_keys = $this->getUploadKeys();

		if ( trim( $file_keys ) != "" ) 
		{
			// 게시글의 data_sid	 로 세팅
			$query = "update board_file set data_sid = '".clean($data_sid)."' 
							where file_sid in ( $file_keys )
										and data_sid = 0 and board_sid = '".$this->board_sid."' ";
			return $this->db->query( $query );
		}
		else return true;
	}

	/*
	 * 업로드 된 파일의 키 정보
	 */
	function getUploadKeys()
	{
		// 31,32,33,  <error key : ,33,,,31,,3>
		$images = $_POST['attach_image'];
		$files	 = $_POST['attach_file'];

		$tmp_keys = explode( ",", $images . $files );
		// 에러 키 방지를 위해 순환 후 재가공
		for ( $i = 0; $i <count( $tmp_keys ); $i++ )
		{
			if ( trim( $tmp_keys[$i] ) != "" && intval( $tmp_keys[$i] ) > 0 ) 
				$file_keys .= intval( $tmp_keys[$i] ) .",";
		}
		// 끝자리 , 제거
		$file_keys = preg_replace( "/[\,]$/", "", $file_keys );

		return $file_keys;
	}

	/**
	 * 파일 업로더 
	 *
	 */
	function getUploader()
	{
		if ( $this->uploader == null || $this->uploder == "" )
		{
			// param : $setting, 하위 폴더명, 정보저장 table
			$params = array( "setting"=>$this->setting, "config"=>$this->config, 
										"folder"=>"board", "subfolder"=>"board_".$this->board_sid."/", "table"=>"board_file" );
			$this->uploader = $this->module( "util/FileUploadBoard", $params );
		}
		
		return $this->uploader;
	}

	/*
	 * 모드별 버튼 링크 출력
	 * - 작성 화면은 스킨에서..
	 */
	function getButtons( $mode, $isMine )
	{
		$result = "";
		
		// 목록 버튼
		if ( $mode == "LIST" )
		{
			// 게시물 작성 권한자 일 경우 쓰기 버튼
			if ( strpos( $this->setting['write_level'], $_SESSION['LOGIN_LEVEL'] ) !== false ) 
			{
				$url		= $this->setting['write_url'] . $this->queryString();
				$result .= str_replace( "[LINK]", $url, $this->setting['write_btn'] );
			}
			// 게시판 관리자 일 경우 다중 삭제 버튼
			if ( is_board_admin( $this->setting['user_sid'] ) ) 
			{
				$url		= "deleteCheck();";
				$result .= str_replace( "[LINK]", $url, $this->setting['delete_btn'] );
			}
		}

		// 글 보기 화면 버튼
		else if ( $mode == "VIEW" )
		{
			$result = array();

			// 목록 버튼
			$url		= $this->setting['list_url'] . $this->queryString();
			$result['LIST'] = str_replace( "[LINK]", $url, $this->setting['list_btn'] );

			// 지원하기 버튼
			$apply_url = $this->setting['apply_url'] . $this->queryString( getParam('data_sid') );
			$temp = str_replace( "[LINK]", $apply_url, $this->setting['apply_btn'] );
			$result['APPLY'] = $temp;

			// 본인 글
			if ( $isMine ) 
			{
				$mod_url = "modifyArticle()";
				$del_url	= "deleteArticle()";

				// 수정 버튼
				$temp = str_replace( "[LINK]", $mod_url, $this->setting['modify_btn'] );
				// 삭제 버튼
				$temp .= str_replace( "[LINK]", $del_url, $this->setting['delete_btn'] );
				$result['MOD'] = $temp;
			}

			if ( $this->isAvail( "reply" ) )
			{
				$reply_url = $this->setting['write_url'] . $this->queryString( getParam('data_sid') );
				// 답글 버튼
				$temp = str_replace( "[LINK]", $reply_url, $this->setting['reply_btn'] );
				$result['REPLY'] = $temp;
			}

		}

		return $result;
	}

	/*
	 * [글 수정] 본인 글 확인
	 * 1) 회원 일 경우		: 게시글의 user_sid <> 0 
	 * 2) 비회원일 경우   : 게시글의 user_sid = 0 && user_id = guest
	 * param : data - 게시글 정보 row <$data_sid - 글 고유키, $user_sid - 글 작성자 고유키>
	 *				mode - MOD : 수정, VIEW : 보기
	 *				type - CHECK : 다운로드 창에서 호출
	 * return : array( "isMine"=> true, "isAvail"=>false )
	 */
	function isMine( $data, $mode, $input_password, $type = "" )
	{
		if ( trim( $data['data_sid'] ) != "" && trim( $data['user_sid'] ) != "" && trim( $data['user_id'] ) != "" ) 
		{
			// 에러시 다음 action
			$errorHandle = ( $type == "CHECK" ) ? "CLOSE" : "BACK";

			/* 
			 * 사이트 전체 관리자 혹은 게시판 관리자 혹은 게시글 작성자 
			 */
			if ( is_board_admin( $this->setting['user_sid'] ) || ( $_SESSION['LOGIN_SID'] == $data['user_sid'] && $_SESSION['LOGIN_ID'] == $data['user_id'] ) ) 
				return array( "isMine"=>true, "isAvail"=>true );

			/* 비회원 작성 글일 경우
			 *  - 보기 모드 : 비공개 글 + 비밀번호 불일치시 보기 불가
			 *  - 수정 모드 : 비밀번호 불일치시 에러
			 */
			if ( $this->isGuestArticle( $data['user_sid'], $data['user_id'] ) )
			{
				if ( $mode == "VIEW" )
				{
					// 비공개 글 일 경우 비밀번호 불일치시 에러
					if ( $data['data_secret_fl'] == 'Y' && $data['user_pw'] != $input_password ) 
						errorMsg( $this->config['lang_password_not_match'], "LOCATION", "list.php".$this->queryString() );
						//errorMsg( $this->config['lang_password_not_match'], "LOCATION", "list.php?board_sid=".$this->board_sid."&page_num=".$_POST['page_num'] );
					else 
						return array( "isMine"=>true, "isAvail"=>true );
				}
				else if ( $mode == "MOD" )
				{
					// 비번 에러
					if ( $data['user_pw'] != $input_password )
						errorMsg( $this->config['lang_password_not_match'], "LOCATION", "list.php".$this->queryString() );
						//errorMsg( $this->config['lang_password_not_match'], "LOCATION", "list.php?board_sid=".$this->board_sid."&page_num=".$_POST['page_num'] );
					// 스팸 코드 에러
					//else if ( trim( $_POST['spamEncode'] ) != "" && Decode( $_POST['spamEncode'] ) != $_POST['spam_key'] )
					//	errorMsg( $this->config['lang_spamkey_not_match'], "LOCATION", "view.php?board_sid=".$this->board_sid."&data_sid=".$_POST['data_sid'] );
					// 일치
					else
						return  array( "isMine"=>true, "isAvail"=>true );
				}
			}
			
			/* 본인의 글이 아닌 회원의 글일 경우 */
			else
			{
				// 글 보기
				if ( $mode == "VIEW")
				{
					// [2015.09.14] 답글에 비번 입력한 상황
					if ( $data['data_secret_fl'] == 'Y' && trim( $data['user_pw'] ) != "" )
					{
						// 비밀번호 일치시
						if ( trim( $data['user_pw'] ) == $input_password )
							return  array( "isMine"=>false, "isAvail"=>true );
						// 비밀번호 오류시
						else
							errorMsg( $this->config['lang_password_not_match'], $errorHandle ); //lang_error_view
					}

					// [2015-01-19] 비밀글의 답글 일 경우. 원글 작성자도 해당 글을 볼수 있어야 함.
					else if ( $data['data_secret_fl'] == 'Y' && trim( $data['org_user_sid'] ) != $_SESSION['LOGIN_SID'] )
						errorMsg( $this->config['lang_password_not_match'], $errorHandle ); //lang_error_view

					// 공개글 혹은 비밀번호 일치 -> 허용
					else 
						return  array( "isMine"=>false, "isAvail"=>true );
				}
				// 글 수정 -> 에러
				else if ( $mode == "MOD" )
					errorMsg( $this->config['lang_error_modify'], $errorHandle );
			}

		}

		return  array( "isMine"=>false, "isAvail"=>false );
	}

	/*
	 * 비회원 작성 글 체크
	 * - user_sid = 0 && user_id = 'guest' 인 게시글
	 * - 비회원 작성 글일 경우 보기/수정 화면을 비밀번호 입력 검증으로 허용/차단
	 * param : user_sid = 게시글 작성자 고유키
	 *				user_id = 게시글 작성자 아이디
	 */
	function isGuestArticle( $user_sid, $user_id )
	{
		if ( $user_sid == 0 && $user_id == "guest" )
			return true;
		else
			return false;
	}

	/* 
	 * 댓글 정보 출력
	 */
	function getComment( $data_sid )
	{
		$comment = $this->module( 
														"board/Comment", 
														array( "data_sid"=>$data_sid, "db"=>&$this->db, "setting"=>&$this->setting, "config"=>&$this->config ) 
													);
		$result = $comment->_list( $this->setting['list_comment_row'] );
		return $result;
	}

	/**
	@ 게시판 설정 정보 get
	**/
	function getSetting()
	{
		return (object)$this->setting;
	}

	/*
	 * QueryString
	 * data_sid = null [글쓰기시 기존에 선택되었던 data_sid가 남아 있을 경우 오류 발생. 글보기시만 세팅]
	 */
	function queryString($data_sid="")
	{
		$result = "";

		if ( $_SERVER['REQUEST_METHOD'] == "POST" )
			$valueQuery = array(		"menu_code"=>$_POST['menu_code'], 
											"board_sid"=>$this->board_sid, "data_sid"=>$data_sid, 												
											"page_num"=>$_POST['page_num'], "sval"=>$_POST['sval'], "skey"=>$_POST['skey'], "category_code1"=>$_POST['category_code1'] );
		else
		{
			$valueQuery = array();
			parse_str( $_SERVER['QUERY_STRING'], $valueQuery );
			$valueQuery['board_sid'] = $this->board_sid;
			$valueQuery['data_sid'] = $data_sid;
			$valueQuery['category_code1'] = $_GET['category_code1'];
		}

		return "?". setQueryStr( $this->setting['query_str'], $valueQuery );
	}

	/* 신규 자료시 new */
	function CheckNewAritcle( $date )
	{	
		//새글 이미지 
		$new_img = $this->setting['image_new_article'];

		if ( intval( strtotime( dateformat( $date, "-" ) ) + 86400 ) > intval( strtotime( date("Y-m-d H:i:s" ) ) ) )
			return $new_img;
	}

	/* 스팸게시글 단속 */
	function CheckDupWrite() 
	{
		$dTime = $this->setting['new_article_interval'];
		if ( $_SESSION['last_write_time'] != "" ) 
		{
			if ( time(0) - $_SESSION['last_write_time'] < $dTime ) 
				postBack( $dTime . $this->config['lang_error_add_time_limit'], "write.php" );
		}
		$_SESSION["last_write_time"] = time(0);
	}

	/* 금지어 체크 */
	function filter() 
	{
		$banKeyword = $this->setting['board_ban_content'];
		if ( trim( $banKeyword ) != "" )
		{
			$filters = explode( ",", $banKeyword );

			$findKeyword = filterString( $filters, array( $_POST['data_title'], $_POST['data_content'] ) );
			if ( trim( $findKeyword ) != "" )	postBack( $this->config['lang_error_bad_title'] ."(금지어 : ".$findKeyword.")", "write.php" );
		}
	}

	// 게시물 수 관리
	function setCompanyCount($value, $group_sid, $mentor_sid)
	{
		if ( intval($group_sid) > 0 )
		{
			// 기업 게시글 카운트 증감
			$query = "update company set co_article_count = co_article_count {$value} where co_sid = '".intval($group_sid)."' ";
			$this->db->query( $query );
		}

		if ( intval($mentor_sid) > 0 )
		{
			// 멘토 게시글 카운트 증감
			$query = "update mentor set article_count  = article_count {$value} where mentor_sid = '".intval($mentor_sid)."' ";
			$this->db->query( $query );
		}
	}

	// 가상 데이터 입력
	function _test()
	{
		exit;
		for ( $i = 0; $i < 2000; $i++ )
		{
			$_POST['data_title'] = "test $i";
			$_POST['user_nick'] = "관리자";
			$_POST['data_content'] = "일이삼사오육칠팔구십일이삼사";

			$this->_add( rand( 1, 10 ) );
		}
	}

	/* 
	 * 이미지
	 */
	function getImage_slide( $skin, $row )
	{
		$result = "";

		if ( $this->setting['board_type'] == "NORMAL" )	return "";
		else
		{
			// 가장 처음 등록한 이미지 파일 검색
			$query = "select file_name_down from board_file where data_sid = '".clean( $row['data_sid'] )."' and file_delete_state = 'N' and file_gubun = 'IMAGE' limit 1 ";
			$rs = $this->db->query( $query );
			// 이미지 없을 경우 null 이미지
			if ( $this->db->num_rows( $rs ) == 0 ) {
				return "<img src=\"".$this->setting['image_no_photo']."\" border=\"0\" alt=\"\" width=\"".$this->setting['thumbnail_width']."\" height=\"".$this->setting['thumbnail_height']."\" />";
			}

			$sub_row = $this->db->fetch( $rs );
			$img_file = $sub_row['file_name_down'];

			// 썸네일 사용시
			/*
			if ( $this->setting['isuse_thumbnail'] == "Y" ) 
			{
				*/
				$result = "<img class='".clean( $row['data_sid'] )."' src=\"imgView.php?image=".str_replace( "/", "/", $img_file )."\" alt=\"첨부 이미지\" />";
			/*}
			else
			{
				$reSize = imgReSize( _FILE_UPLOAD_ROOT."board/" . $img_file, $this->setting['thumbnail_width'], $this->setting['thumbnail_height'] );
				$result = "<img class='".clean( $row['data_sid'] )."' src=\"imgView.php?image=".$img_file."\" alt=\"첨부 이미지\" width=\"".$reSize['width']."\" height=\"".$reSize['height']."\" />";
			}
			*/
		}
		
		return $result;
	}

	function getSlideImge( $imgTag, $limit = "")
	{
		$return_row = "";
		
		$pageNum = 	( trim( $_GET["page_num"] ) != "" ) ? intval( $_GET["page_num"] )  : intval( $_POST["page_num"] );
		if ( $pageNum == 0 ) $pageNum = 1;

		$startNum = ( $pageNum - 1 ) * $this->setting['board_row'];
		$endNum		= $this->setting['board_row'];

		$query = "
			select b.data_sid, board_sid, data_no, data_order, data_depth, data_title, {$query_content}
						comment_count, view_count, register_date, user_pw, 
						user_sid, user_id, user_nick, user_email, user_homepage, 
						delete_state, data_notice_fl, data_secret_fl, org_user_sid, category_code1, tmp_field1, tmp_field2, tmp_field3, tmp_field4, tmp_field5, tmp_field6, tmp_field7, tmp_field8, start_date,end_date
			from board_data a 
			inner join 
			(
				select data_sid from board_data b where board_sid = '".$this->board_sid."' and delete_state = 'N' and data_notice_fl = 'X'
				{$searchQuery1}
				order by data_order 
				limit ".$startNum.", " . $endNum . "
			) b 
			on ( a.data_sid = b.data_sid )
			";

		if ( $rs = $this->db->query( $query ) )
		{
			$count = 0;
			while ( $row = $this->db->fetch( $rs ) )
			{
				$queryString = $this->queryString($row['data_sid']);
				if ( $_SESSION['LOGIN_LEVEL'] < 3 ){
					$return_row.= "<li><div class='li_stbox'><a href=\"".$this->setting['view_url']."".$queryString."\" title=\"".addslashes(str_replace("\"","", $row['data_title'] ))." - ".$this->config['lang_view_article']."\">";
				}else{
					$return_row.= "<li><div class='li_stbox'><a href=\"#this\" title=\"".addslashes(str_replace("\"","", $row['data_title'] ))." - ".$this->config['lang_view_article']."\">";
				}				
				$return_row.= $this->getImage_slide( "", $row );

				if($this->board_sid == "23"){
					$div_text = "<div class='floor_txt'><p class='flt_t01'>STEKOREA </p><p class='flt_t02'>Solar Tech Energy</p></div>";
				}

				$return_row.= "</a><div>".$div_text."<p class='stbox_t01'>".$row['data_title']."</p><p class='stbox_t02'></p></div></div></li>";
			}
		}
		return $return_row;
	}
	
	//회사연혁 관련 리스트
	function _historylist(){
		$query = "SELECT *
			FROM board_data b
			WHERE board_sid = '".$this->board_sid."' AND delete_state = 'N'
			ORDER BY data_title";
	
		$tmp_year = "";
		$html = "";
		$next_year = "";
		$data = array();
		if ( $rs = $this->db->query( $query ) )
		{
			while ( $row = $this->db->fetch( $rs ) )
			{
				$data[] = $row;
			}
		}

		for($i=0;$i<count($data);$i++){
			$next_year = $data[$i+1]['tmp_field4'];
			
			/* 제목 세팅 */
			$tmpTitle = "";
			// 제목에 검색 결과 표시
			$tmpTitle .= cut_str( $data[$i]['data_title'], $this->setting['board_title_len'] );
			if ( is_array( $this->keywords ) )
			{
				for ( $i = 0; $i < count( $this->keywords ); $i++ ) 
				{
					$tmpTitle =preg_replace("/(".$this->keywords[$i]."(?![^<]*>))/i", "<span style='color:#ff0702; background-color:#FFFa86;'>\\1</span>", $tmpTitle); 
				}
			}
			$titleArr = $this->titleLink( $tmpTitle, $data[$i], $temp );
		

			if($tmp_year == $data[$i]['tmp_field4']){
				$html.= "		<dl>";
				$html.= "			<dt>".$titleArr['link'].$data[$i]['data_title']."</a></dt>";
				$html.= "			<dd>".$titleArr['link'].stripslashes(strip_tags($data[$i]['data_content']))."</a></dd>";
				$html.= "		</dl>";
			}else{
				$html.= "<div class=\"his_listbox\">";
				$html.= "	<div class=\"his_box\">";
				$html.= "		<p>".$data[$i]['tmp_field4']."</p>";
				$html.= "		<dl>";
				$html.= "			<dt>".$titleArr['link'].$data[$i]['data_title']."</a></dt>";
				$html.= "			<dd>".$titleArr['link'].stripslashes(strip_tags($data[$i]['data_content']))."</a></dd>";
				$html.= "		</dl>";
			}
			//prt("T ".$row['tmp_field4']." N ".$row['next_year']);
			if($data[$i]['tmp_field4'] != $next_year){
				$html.= "	</div>";
				$html.= "</div>";
			}

		
			$tmp_year = $data[$i]['tmp_field4'];
		}
		

		return array( "html"=>$html);
	}
}
?>