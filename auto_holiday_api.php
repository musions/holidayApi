 <?php
 
/**
 * 1. DB ���� : holiday ���̺� ����
 * 
 * CREATE TABLE `tc_holiday` (
 * `idx` smallint(6) NOT NULL AUTO_INCREMENT,
 * `y` char(4) NOT NULL,
 * `m` char(2) NOT NULL,
 * `d` char(2) NOT NULL,
 * `ment` varchar(50) NOT NULL,
 * `kinds` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '��¥����',
 * PRIMARY KEY (`idx`),
 * KEY `y` (`y`),
 * KEY `m` (`m`),
 * KEY `kinds` (`kinds`)
 * )  * 
 *   kinds ���� ���� API ������ ���缭 ������ ���� �����մϴ�. 
 *           1. ������  2. ��������� , 3.�������� (���, �ߺ�, ���) ,4. �������� (����, �ʺ�, ����), 10.�վ��� ��
 * 
 * 2. ���ϸ� �������� �Է��Ͽ� �����ϸ� ���� ��¥�� üũ�Ͽ� 10�� ~ 12�� �̸� ���� ������ �ڷḦ �������� 1�� ~ 2�� �̸� ������ �ڷḦ �����ɴϴ�. 
 * 
 * 3. ���ϸ� �ڿ� ������ �Բ� �Է��Ͽ� ������ �� �ֽ��ϴ�. 
 *    auto_holiday_api.php?category=[ī�װ�]   
 *    ī�װ� �������� ������ ��� ���� 
 *    �վ��� ���� �ʿ��� ��� handless  / �����ϸ� �ʿ��� ��� holiday
 * 
 * 4. Ư�������� �ڷᰡ �ʿ��� ��� ������ y: ���� / m: ���� �Է��� �� �ֽ��ϴ�. 
 * 
 * 5. �����͸� ������ ���� ���ڿ� ������ ī�װ��� ������ �ߺ������� ���� �������� �ʽ��ϴ�. 
 */


/** 
 * �վ��� �� ���, ������ API ��������
 * ���������͸� �̿��Ͽ� �������ڸ� �����Ͽ� 9��, 10���� �վ��� ���� ��ȯ��. 
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
         // ������ �������� �ʾҰ� ������ 7�� ~ 12�����̸� 
         $year = date("Y")+1;  
         $month = 0;       
      }elseif ($year == 0 && date("m") <= 6){
         // ������ �������� �ʾҰ� ������ 6�������̸� 
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
               - {$year}�� ������ �ڷ� �Է� ��� -<br>
               API ������ �� : {$holiDayRes["totalCount"]}<br>
               �Է¿Ϸ� DATa : {$holidayInsertCount}<br>
               ���� �Ǵ� �ߺ� DATA : {$holidayErrorCount}<br>
            ";
            
         }else{
            $logMsg .= "������ API ��� ���� <br>";
         }
      }

      if($category == NULL || $category == "handless"){         
         // �վ��� �� ���� �����ͼ� ����
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
                                 VALUES ('{$hlRs['solYear']}', '{$hlRs['solMonth']}', '{$hlRs['solDay']}', '�վ��³�', '10'  )
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
               - {$year}�� �վ��� �� �ڷ� �Է� ��� - <br>
               ����� �� �� : {$handlessTotal} <br>
               �Է¿Ϸ� DATA : {$handlessInsertCount} <br>
               ���� �Ǵ� �ߺ� DATA : {$handlessErrorCount} <br>
            ";            
         }else{
            $logMsg .= "�վ��� �� �ڷ� ��� ����<br>";
         }
      }
      // echo $logMsg;
   }

   public function changeDateName($val){
      // �Է��� �� ǥ�Ⱑ ����� �κ��� �־� ���� �ϴ� �Լ�
      $list = array(
         array("before"=>"1��1��", "after"=>"����"),
         array("before"=>"�⵶ź����", "after"=>"��ź��")
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
    * �վ��� �� �����ϱ� �Լ� $year �� �ʼ� ���̸�, $month �Ǵ� $day�� ���ų� 0�̸� �ش��ϴ� ���̳� ������ ��� �� ���� ���� ������. 
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



   /** ������ڿ� ���Ͽ� ���� �� ��ȯ */
   Public function lunarDate($year=0, $month=0, $day=0){
      
      if ($year == 0 || $month == 0){
         return array("result"=>"error", "msg"=>"�Է°� ����");
      }

      $service_key = "--- ���� Ű �� �Է� �ϴ� �κ� --- ";
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
    * ���������� API�� ���� ���������� ������ 
    */
   public function bringHolidayApi($year, $month=0){
      
      if ($year == 0){
         return array("result"=>"error", "msg"=>"�Է°� ����");
      }

      $service_key = " --- ���� Ű�� �Է� ---- ";
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

