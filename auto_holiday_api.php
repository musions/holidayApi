 <?php
 
/**
 * 1. DB 설명 : holiday 테이블 구조
 * 
 * CREATE TABLE `tc_holiday` (
 * `idx` smallint(6) NOT NULL AUTO_INCREMENT,
 * `y` char(4) NOT NULL,
 * `m` char(2) NOT NULL,
 * `d` char(2) NOT NULL,
 * `ment` varchar(50) NOT NULL,
 * `kinds` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '날짜종류',
 * PRIMARY KEY (`idx`),
 * KEY `y` (`y`),
 * KEY `m` (`m`),
 * KEY `kinds` (`kinds`)
 * )  * 
 *   kinds 값은 정부 API 종류에 맞춰서 다음과 같이 설정합니다. 
 *           1. 공휴일  2. 국가기념일 , 3.절기정보 (춘분, 추분, 백로) ,4. 절기정보 (보름, 초복, 말복), 10.손없는 날
 * 
 * 2. 파일명만 브라우저에 입력하여 실행하면 현재 날짜를 체크하여 10월 ~ 12월 이면 다음 연도의 자료를 가져오고 1월 ~ 2월 이면 올해의 자료를 가져옵니다. 
 * 
 * 3. 파일명 뒤에 쿼리를 함께 입력하여 실행할 수 있습니다. 
 *    auto_holiday_api.php?category=[카테고리]   
 *    카테고리 선택하지 않으면 모두 실행 
 *    손없는 날만 필요한 경우 handless  / 공휴일만 필요한 경우 holiday
 * 
 * 4. 특정연월의 자료가 필요한 경우 쿼리에 y: 연도 / m: 월을 입력할 수 있습니다. 
 * 
 * 5. 데이터를 가지고 오는 일자에 동일한 카테고리가 있으면 중복방지를 위해 가져오지 않습니다. 
 */


/** 
 * 손없는 날 계산, 공휴일 API 가져오기
 * 공공데이터를 이용하여 음력일자를 산정하여 9일, 10일을 손없는 날로 반환함. 
 * */
class holiDayInfo()
{

   function __construct()
   {

      global $db_good;
      $this->db_good = $db_good;
      
      $year = isset($_REQUEST["y"]) ? $_REQUEST["y"] : 0;
      $month = isset($_REQUEST["m"]) ? $_REQUEST["m"] : 0;
      $category = isset($_REQUEST["category"]) ? $_REQUEST["category"] : NULL;
      $test = isset($_REQUEST["test"]) ? $_REQUEST["test"] : "n";

      $logMsg = "";


      if ($year == 0 && date("m") >= 7 && date("m") <= 12){
         // 연도를 지정하지 않았고 지금이 7월 ~ 12월달이면 
         $year = date("Y")+1;  
         $month = 0;       
      }elseif ($year == 0 && date("m") <= 6){
         // 연도를 지정하지 않았고 지금이 6월이전이면 
         $year = date("Y");
         $month = 0;
      }elseif ($year == 0){
         return false;
      }
      
      if ($category == NULL || $category == "holiday"){
         $holiDayRes = $this->bringHolidayApi($year, $month);
         if ($holiDayRes["result"] == "success"){
            $holidayInsertCount = 0;
            $holidayErrorCount = 0;
            foreach ($holiDayRes["response"]->item as $holiRes){
               $holiRs = (array)$holiRes;            
               $holiName = $this->changeDateName($holiRs["dateName"]);
               $holiDate = $holiRs["locdate"];
               $holiYear = substr($holiDate,0,4);
               $holiMonth = substr($holiDate, 4,2);
               $holiDay = substr($holiDate, 6,2);
               
               $checkSql = "SELECT idx 
                              FROM db_master.tc_holiday 
                             WHERE y='{$holiYear}' 
                               AND m='{$holiMonth}' 
                               AND d='{$holiDay}' 
                               AND kinds='1'
                             LIMIT 1
                           ";
               $checkQuery = mysql_query($checkSql, $this->db_good) or die (mysql_error());
               if (mysql_num_rows($checkQuery) == 1){
                  $holidayErrorCount ++;
               }else{
                  $sql = "INSERT INTO db_master.tc_holiday
                                  (y ,              m ,             d ,           ment ,       kinds)
                           VALUES ('{$holiYear}', '{$holiMonth}', '{$holiDay}', '{$holiName}', '1'  )
                  ";
                  if ($test == "n"){
                     $query = mysql_query($sql, $this->db_good) or die (mysql_error());
                  }else{
                     echo $sql."<br>";
                  }                  
                  $holidayInsertCount ++;
               }
            }
            $logMsg .= "
               - {$year}년 공휴일 자료 입력 결과 -<br>
               API 공휴일 수 : {$holiDayRes["totalCount"]}<br>
               입력완료 DATa : {$holidayInsertCount}<br>
               오류 또는 중복 DATA : {$holidayErrorCount}<br>
            ";
            
         }else{
            $logMsg .= "공휴일 API 사용 실패 <br>";
         }
      }

      if($category == NULL || $category == "handless"){         
         // 손없는 날 정보 가져와서 저장
         $handlessDayRes = $this->calHandlessDay($year, $month);
         
         if ($handlessDayRes["result"] == "success"){
            $handlessInsertCount = 0;
            $handlessErrorCount = 0;
            $handlessTotal = count($handlessDayRes["response"]);

            foreach($handlessDayRes["response"] as $handlessDayRs){
               $hlRs = (array)$handlessDayRs;
               
               $hcheckSql = "SELECT idx 
                               FROM db_master.tc_holiday 
                              WHERE y='{$hlRs["solYear"]}' 
                                AND m='{$hlRs["solMonth"]}' 
                                AND d='{$hlRs["solDay"]}' 
                                AND kinds='10' 
                              LIMIT 1
               ";
               $hcheckQuery = mysql_query($hcheckSql, $this->db_good) or die (mysql_error());

               if (mysql_num_rows($hcheckQuery) == 1){
                  $handlessErrorCount ++;
               }else{
                  $handSql = "INSERT INTO db_master.tc_holiday 
                                          (y ,                    m ,                   d ,                   ment ,     kinds)
                                 VALUES ('{$hlRs['solYear']}', '{$hlRs['solMonth']}', '{$hlRs['solDay']}', '손없는날', '10'  )
                  ";
                  if ($test == "n"){
                     $handQuery = mysql_query($handSql, $this->db_good) or die(mysql_error());
                  }else{
                     echo $handSql."<br>";
                  }
                  $handlessInsertCount ++;
               }
            }
            $logMsg .="
               - {$year}년 손없는 날 자료 입력 결과 - <br>
               계산한 날 수 : {$handlessTotal} <br>
               입력완료 DATA : {$handlessInsertCount} <br>
               오류 또는 중복 DATA : {$handlessErrorCount} <br>
            ";            
         }else{
            $logMsg .= "손없는 날 자료 등록 실패<br>";
         }
      }
      // echo $logMsg;
   }

   public function changeDateName($val){
      // 입력할 때 표기가 어색한 부분이 있어 수정 하는 함수
      $list = array(
         array("before"=>"1월1일", "after"=>"신정"),
         array("before"=>"기독탄신일", "after"=>"성탄절")
      );
      $result = "";
      foreach ($list as $ls){
         
         if ($ls["before"] == $val){
            $result = $ls["after"];
         }
      }
      if ($result == ""){
         $result = $val;
      }
      return $result;
   }

   /**
    * 손없는 날 산정하기 함수 $year 는 필수 값이며, $month 또는 $day가 없거나 0이면 해당하는 달이나 연도의 모든 손 없는 날을 가져옴. 
    */
   Public function calHandlessDay($year=0, $month=0, $day=0)
   {
      $Result = array();
      if($year > 0){
         if ($month == 0){
            $start_month = 1;
            $end_month = 12;
         }else{
            $start_month = $month;
            $end_month = $month;
         }
         for ($m = $start_month; $m <= $end_month; $m++){          
            $Res = $this->lunarDate($year, $m, $d);
            if($Res["result"] == "success"){
               foreach($Res["response"] as $rs){
                  $rsDay = $rs->lunDay-(floor($rs->lunDay/10)*10);                  
                  if ($rsDay == 9 || $rsDay == 0){
                     $rs->solDate = $rs->solYear."-".$rs->solMonth."-".$rs->solDay;
                     $rs->lunDate = $rs->lunYear."-".$rs->lunMonth."-".$rs->lunDay;
                     array_push($Result, $rs);
                  }
               } 
            }            
         }         
      }
      return array("result"=>"success", "response"=>$Result);
   }



   /** 양력일자에 대하여 음력 값 반환 */
   Public function lunarDate($year=0, $month=0, $day=0){
      
      if ($year == 0 || $month == 0){
         return array("result"=>"error", "msg"=>"입력값 오류");
      }

      $service_key = "--- 개별 키 값 입력 하는 부분 --- ";
      if ($month<10){
         $month = "0".$month;
      }
      if ($day < 10 && $day > 0){
         $day = "0".$day;
      }

      $ch = curl_init();
      $url = 'http://apis.data.go.kr/B090041/openapi/service/LrsrCldInfoService/getLunCalInfo'; /*URL*/
      $queryParams = '?' . urlencode('serviceKey') . '='.$service_key; /*Service Key*/
      $queryParams .= '&' . urlencode('solYear') . '=' . urlencode($year); /**/
      $queryParams .= '&' . urlencode('solMonth') . '=' . urlencode($month); /**/
      if ($day != 0){
         $queryParams .= '&' . urlencode('solDay') . '=' . urlencode($day); /**/
      }
      $queryParams .= '&' . urlencode('numOfRows') . '=' . urlencode(100); /**/

      try{
         curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($ch, CURLOPT_HEADER, FALSE);
         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
         $response = curl_exec($ch);
         curl_close($ch);
         $apiRes = "success";
      }catch(Exception $e){
         $apiRes = "error";
      }
      if ($apiRes == "success"){
         $result = simplexml_load_string($response);
         if($result->body->totalCount > 0){
            $items = $result->body->items->item;
            $result = array("result"=>"success", "response"=>$items, "totalCount"=>$result->body->totalCount);
         }else{
            $result = array("result"=>"error", "msg"=>"No Data");
         }
      }else{
         $result = array("result"=>"error", "msg"=>"Connect Error");
      }
      return $result;
   }


   /**
    * 공공데이터 API를 통해 휴일정보를 가져옴 
    */
   public function bringHolidayApi($year, $month=0){
      
      if ($year == 0){
         return array("result"=>"error", "msg"=>"입력값 오류");
      }

      $service_key = " --- 개별 키값 입력 ---- ";
      if ($month<10 && $month > 0){
         $month = "0".$month;
      }
      

      $ch = curl_init();
      $url = 'http://apis.data.go.kr/B090041/openapi/service/SpcdeInfoService/getRestDeInfo'; /*URL*/
      $queryParams = '?' . urlencode('serviceKey') . '='.$service_key; /*Service Key*/
      $queryParams .= '&' . urlencode('solYear') . '=' . urlencode($year); /**/
      if($month != 0){
         $queryParams .= '&' . urlencode('solMonth') . '=' . urlencode($month); /**/
      }
      $queryParams .= '&' . urlencode('numOfRows') . '=' . urlencode(100); /**/

      try {
         curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
         curl_setopt($ch, CURLOPT_HEADER, FALSE);
         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
         $response = curl_exec($ch);
         curl_close($ch);
         $apiRes = "success";
      }catch(Exception $e){
         $apiRes = "error";
      }
      if ($apiRes == "success"){
         $result = simplexml_load_string($response);
         if(trim($result->body->totalCount) > 0){
            $items = $result->body->items;            
            $result = array("result"=>"success", "response"=>$items, "totalCount"=>$result->body->totalCount);
         }else{
            $result = array("result"=>"error", "msg"=>"No Data");
         }      
      }else{
         $result = array("result"=>"error", "msg"=>"Connect Error");
      }      
      return $result;
   }
}

$holiDayInfo = new holiDayInfo;

