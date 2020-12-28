<?php 
/**
 * 결재 처리
 *
 */
if ( ! defined('__BASE_PATH')) exit('No direct script access allowed');

include_once __MODULE_PATH."/core/CoreObject.php";

class Sign extends CoreObject
{
	// DB Connection Object
	var $db;
	// 환경 설정 배열
    var $setting;
    // table
	var $TABLE;
	// 게시판 번호
    var $data_sid;
    // 사용자 번호
    var $user_sid;
    
	function Sign($data_sid)
	{
        $this->TABLE = "sign";
		// DB Object 생성
        $this->db = $this->module( "core/DB" );
        //게시판 번호
        $this->data_sid = $data_sid;
        //사용자 번호
        $this->user_sid = clean($_SESSION['LOGIN_SID']);

    }

    //승인여부 플래그 설정
    function setFlag()
    {   
        $orderQuery = "select sOrder+1 as next from sign where userSid = {$this->user_sid} and dataSid = {$this->data_sid} and target = 'Y'";
        $row = $this->db->fetch($orderQuery);

        $query = "update sign set flag = 'Y', target = 'N', modDate = now() where userSid = {$this->user_sid} and dataSid = {$this->data_sid} and target = 'Y' ";
        if($this->db->query($query)){
            $targetQuery = "update sign set target = 'Y' where dataSid = {$this->data_sid} and sOrder = '".$row['next']."'";
            if($this->db->query($targetQuery)){
                return true;
            }
       }else{
           return false;
       }
    }

    
    //화면 출력
    function view($skin)
    {   
        $tmpRow = "";
        $result = "";
        $query = "select a.userName, a.userPosition, a.userSid, b.sOrder, b.flag, b.target,b.modDate 
                        from user as a, {$this->TABLE} as b
                        where b.dataSid = {$this->data_sid} and a.userSid = b.userSid
                        order by b.sOrder";

        if ( $result = $this->db->query( $query ) )
        {
            while( $row = $this->db->fetch($result) )
            {      
                $tmpRow = $skin;   
                
                $tmpRow = str_replace("[NAME]", $row['userName'], $tmpRow);

                switch($row['userPosition'])
                {

                    case 1:
                        $tmpRow = str_replace("[POSITION]", "대표이사", $tmpRow);
                    break;

                    case 2:
                        $tmpRow = str_replace("[POSITION]", "부장", $tmpRow);
                    break;
                    
                    case 3:
                        $tmpRow = str_replace("[POSITION]", "과장", $tmpRow);
                    break;
                    
                    case 4:
                        $tmpRow = str_replace("[POSITION]", "주임", $tmpRow);
                    break;

                    case 5:
                        $tmpRow = str_replace("[POSITION]", "센터장", $tmpRow);
                    break;

                    case 6:
                        $tmpRow = str_replace("[POSITION]", "팀장", $tmpRow);
                    break;

                    case 7:
                        $tmpRow = str_replace("[POSITION]", "팀원", $tmpRow);
                    break;

                }

                $signQuery = "select userSid from {$this->TABLE} where target='Y' and dataSid = {$this->data_sid}";
                $signRow = $this->db->fetch($signQuery);
                
                if($row['flag']=='N'){
                    if( $this->user_sid == $signRow['userSid'] && $this->user_sid == $row['userSid'] && $row['target'] == 'Y'){
                        $tmpRow = str_replace("[FLAG]", "<a href=\"#this\" id=\"payBtn\"  onClick=\"signPayment()\" class=\"btn btn-lg btn-board-02\" role=\"button\">결재하기</a>", $tmpRow);
                        $tmpRow = str_replace("[DATE]", "-", $tmpRow);
                    }else{
                        $tmpRow = str_replace("[FLAG]", "", $tmpRow);
                        $tmpRow = str_replace("[DATE]", "", $tmpRow);
                    }                    
                }else{
                    $date = explode(" ", $row['modDate']);
                    $tmpRow = str_replace("[FLAG]", "<a href=\"#this\" onClick=\"\" class=\"btn btn-lg btn-board-02\" role=\"button\">결재완료</a>", $tmpRow);
                    $tmpRow = str_replace("[DATE]", $date[0], $tmpRow);
                }
            
                $result_html .= $tmpRow;
            }
        }
        
        return $result_html;
    }

}

?>