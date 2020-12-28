<?php
include $_SERVER['DOCUMENT_ROOT']."/application/default.php";
include __BASE_PATH."/module/sign/Sign.php";

$board_sid		= clean( $_POST['board_sid'] );
$data_sid		= clean( $_POST['data_sid'] );


if($board_sid == "41" || $board_sid == "40" || $board_sid == "43"  || $board_sid == "44"  || $board_sid == "42"){
    $sign = new Sign($data_sid);

    echo $sign->setFlag();
}else{
    echo "실패";
}

?>