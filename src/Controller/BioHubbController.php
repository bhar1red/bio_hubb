<?php 

namespace Drupal\bio_hubb\Controller;

class BioHubbController{

    protected $config = NULL;
    protected $access_token = NULL;
    protected $hubb_root = 'https://ngapi.hubb.me';

    public function __construct() 
    {
        $this->config = \Drupal::config('bio_hubb.settings'); 
    }

    public function get(){ 
        return '';
    }

    /**
     * setAccessToken($auth);
     *   $auth
     *       [clientID] => 
     *       [clientSecret] => 
     *       [scope] => 
     *       [grantType] => 
     * Example: setAccessToken()
     */

    public function setAccessToken($auth){
        $client_id = empty($auth['clientID']) ? $this->config->get('bio_hubb.client_id') : $auth['clientID'];
        $url = $this->hubb_root; //empty($url) ? $this->config->get('bio_hubb.url') : $url;
        $grant_type = empty($auth['grantType']) ? "client_credentials" : $auth['grantType'];
        $scope = empty($auth['scope']) ? $this->config->get('bio_hubb.scope') : $auth['scope'];
        $client_secret= empty($auth['clientSecret']) ? $this->config->get('bio_hubb.client_secret') : $auth['clientSecret'];

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $url."/auth/token",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "client_Id=".$client_id."&grant_type=".$grant_type."&scope=".$scope."&client_secret=".$client_secret,
          CURLOPT_HTTPHEADER => array(
            "Content-Type: application/x-www-form-urlencoded",
            "cache-control: no-cache"
          ),
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
          $this->access_token = NULL;
          return FALSE; //"cURL Error #:" . $err;
        } else {
          $access_token = json_decode($response)->access_token;
          $this->access_token = $access_token;
          return $access_token;
        }

    }

    protected function getHubAPI($curl_url){
        $access_token = $this->access_token;

        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $curl_url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_POSTFIELDS => "",
          CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer ".$access_token,
            "cache-control: no-cache"
          ),
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
          return "cURL Error #:" . $err;
        } else {
          return $response;
        }
    }

    public function getEvents($event_id = NULL, $options = ['expandall' => FALSE]){
         $url = $this->hubb_root;
        $curl_url = $url."/api/v1/Events";
        if($event_id){
            $curl_url = $curl_url."/".$event_id;
        }
        $events = $this->getHubAPI($curl_url);
        return $events;
    }

    public function getSessions($event_id, $session_id = NULL, $options = ['expandall' => TRUE]){

         $url = $this->hubb_root;
        $curl_url = $url."/api/v1/".$event_id."/Sessions";
        if($session_id){
            $curl_url = $curl_url."/".$session_id;
        }
        $curl_url = $curl_url ."?$"."expand=". urlencode("Speakers,Room,TimeSlot,SessionType,Track,PropertyValues,PropertyValues/PropertyMetadata"); 
        $curl_url = $curl_url . "&$". "select=".urlencode("Id,Title,Description,TimeSlot/StartTime,TimeSlot/EndTime,Room/Location,SessionType/Name,Track/Title,PropertyValues/Id,PropertyValues/Value,PropertyValues/PropertyMetadataId,Speakers/Id,PropertyValues/PropertyMetadata/Title");
        $results = json_decode($this->getHubAPI($curl_url));
        $formatted_results = $this->formatSessionResults($results);            
        return $formatted_results;  
    }

    public function getSessionTypes($event_id, $sessiontype_id = NULL){
        $url = $this->hubb_root;
        $curl_url = $url."/api/v1/".$event_id."/SessionTypes";
        if($sessiontype_id){
            $curl_url = $curl_url."/".$sessiontype_id;
        }
        $results = json_decode($this->getHubAPI($curl_url));
        $session_types = [];
        foreach($results as $result){
          $session_types[$result->Id] = $result->Name;
        }

        return $session_types;
    }

    public function getStdTrackTypes($event_id, $track_id = NULL){
        $url = $this->hubb_root;
        $curl_url = $url."/api/v1/".$event_id."/Tracks";
        if($track_id){
            $curl_url = $curl_url."/".$track_id;
        }
        $results = json_decode($this->getHubAPI($curl_url));
        $tracks = [];
        foreach($results as $result){
          $tracks[$result->Id] = $result->Title;
        }
        return $tracks;  
  }
  public function getCustomFieldOptions($event_id, $field_title){
      $url = $this->hubb_root;
      $curl_url = $url."/api/v1/".$event_id."/PropertyMetadata"."?$"."filter"."=".urlencode("Title eq '".$field_title."'");
      $results = json_decode($this->getHubAPI($curl_url));
      $options = !empty($results[0]) ? $results[0]->Options : FALSE;
      return $options;  
  } 
  
  public function getSessionsByDate($event_id, $date){

    $date = new \DateTime($date);

    $month = $date->format('m');
    $day = $date->format('d');
    $year = $date->format('Y');
     $url = $this->hubb_root;
    $curl_url = $url."/api/v1/".$event_id."/Sessions"."?$"."filter"."=".urlencode("Status eq 'Accepted' and VisibleToAnonymousUsers eq true and Enabled eq true and day(TimeSlot/StartTime) eq ".$day." and month(TimeSlot/StartTime) eq ".$month." and year(TimeSlot/StartTime) eq ".$year);
    $curl_url = $curl_url ."&$"."expand=". urlencode("Speakers,Room,TimeSlot,SessionType,Track,PropertyValues,PropertyValues/PropertyMetadata"); 
    $curl_url = $curl_url . "&$". "select=".urlencode("Id,Title,Description,TimeSlot/StartTime,TimeSlot/EndTime,Room/Location,SessionType/Name,Track/Title,PropertyValues/Id,PropertyValues/Value,PropertyValues/PropertyMetadataId,Speakers/Id,PropertyValues/PropertyMetadata/Title");
    $curl_url = $curl_url . "&$". "orderby=".urlencode("TimeSlot/StartTime");
    $results = json_decode($this->getHubAPI($curl_url));
    $formatted_results = $this->formatSessionResults($results);
    return $formatted_results;  
  }

  public function getHubbUsers($event_id, $top = NULL){
     $url = $this->hubb_root;
    $curl_url = $url."/api/v1/".$event_id."/Users";
    $curl_url = $curl_url."?$"."expand=".urlencode("SpeakingAt,PropertyValues,PropertyValues/PropertyMetadata");
    $curl_url = $curl_url."&$"."select=".urlencode("Id,Title,FirstName,LastName,Interests,EmailAddress,Company,Industry,PhotoLink,Biography,Website,Twitter,LinkedIn,Facebook,Blog,Roles,ProfileIsPublic,SpeakingAt,PropertyValues,PropertyValues/PropertyMetadata");
    $curl_url = $curl_url."&$"."filter=".urlencode("IsPending eq false and indexof(Roles,'Speaker') gt -1 and SpeakingAt/any() and PropertyValues/any(pv:(pv/PropertyMetadata/Title eq 'Invitation Status'))");
    if(!empty($top)){
      $curl_url = $curl_url."&$"."top=10";
    }
    $results = json_decode($this->getHubAPI($curl_url));
    if(empty($results)) return [];
    
    $formatted_results = [];
    foreach($results as $result){
      $fresult = [];
      $fresult['id'] = empty($result->Id)? '' : $result->Id;
      $fresult['title'] = empty($result->Title)? '' : $result->Title;
      $fresult['firstName'] = empty($result->FirstName)? '' : $result->FirstName;
      $fresult['lastName'] = empty($result->LastName)? '' : $result->LastName;
      $fresult['interests'] = empty($result->Interests)? [] : $result->Interests;
      $fresult['email'] = empty($result->EmailAddress)? '' : $result->EmailAddress;
      $fresult['company'] = empty($result->Company)? '' : $result->Company;
      $fresult['industry'] = empty($result->Industry)? '' : $result->Industry;
      $fresult['photoLink'] = empty($result->PhotoLink)? '' : $result->PhotoLink;
      $fresult['biography'] = empty($result->Biography)? '' : $result->Biography;
      $fresult['socialChannels']['Website'] = empty($result->Website)? '' : $result->Website;
      $fresult['socialChannels']['Twitter'] = empty($result->Twitter)? '' : $result->Twitter;
      $fresult['socialChannels']['Facebook'] = empty($result->Facebook)? '' : $result->Facebook;
      $fresult['socialChannels']['Blog'] = empty($result->Blog)? '' : $result->Blog;
      $fresult['socialChannels']['LinkedIn'] = empty($result->LinkedIn)? '' : $result->LinkedIn;
      $fresult['roles'] = empty($result->Roles)? '' : $result->Roles;
      $sessions = [];
      foreach($result->SpeakingAt as $session){
        $sessions = $session->Id;
      }
      $fresult['city'] = '';
      $fresult['state'] = '';
      $fresult['country'] = '';
      $fresult['zip'] = '';
      $fresult['prefix'] = '';
      $fresult['suffix'] = '';
      $fresult['credentials'] = '';
      foreach($result->PropertyValues as $property){
        hash_equals($property->PropertyMetadata->Title,"City") ? $fresult['city'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"State") ? $fresult['state'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Country") ? $fresult['country'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Zip") ? $fresult['zip'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Prefix") ? $fresult['prefix'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Suffix") ? $fresult['suffix'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Credentials") ? $fresult['credentials'] = $property->Value : '';
      }     
      $formatted_results[] = $fresult;
    }
    return $formatted_results;  
} 

public function formatSessionResults($results){
  $formatted_results = [];
  foreach($results as $result){
    $fresult = [];
    $fresult['id'] = empty($result->Id)? '' : $result->Id;
    $fresult['title'] = empty($result->Title)? '' : $result->Title;
    $fresult['description'] = empty($result->Description)? '' : $result->Description;
    $fresult['timeslot']['start'] = empty($result->TimeSlot->StartTime->EventTime)? '' : $result->TimeSlot->StartTime->EventTime;
    $fresult['timeslot']['end'] = empty($result->TimeSlot->EndTime->EventTime)? '' : $result->TimeSlot->EndTime->EventTime;
    $fresult['room'] = empty($result->Room->Location)? '' : $result->Room->Location;
    $fresult['speakers'] = [];
    if(!empty($result->Speakers)){
      foreach($result->Speakers as $speaker){
        $fresult['speakers'][] = $speaker->Id;
      }
    }
    $fresult['sessionType'] = empty($result->SessionType->Name)? '' : $result->SessionType->Name;
    $fresult['stdTrack'] = empty($result->Track->Title)? '' : $result->Track->Title; 

    $fresult['bioTrack'] = '';
    $fresult['abilityLevel'] = '';
    $fresult['format'] = '';
    $fresult['sessionKeywords'] = '';
    $fresult['sessionFlags'] = '';
    $fresult['presentationType'] = '';
    $fresult['mainFocus'] = '';
    $fresult['devPhase'] = '';
    $fresult['companyCategory'] = '';

    if(!empty($result->PropertyValues)){
      foreach($result->PropertyValues as $property){
        hash_equals($property->PropertyMetadata->Title,"BIO Track") ? $fresult['bioTrack'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Ability Level") ? $fresult['abilityLevel'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Format") ? $fresult['format'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Session Keywords") ? $fresult['sessionKeywords'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Session Flags") ? $fresult['sessionFlags'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Presentation Application - Presentation Type") ? $fresult['presentationType'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Main Therapeutic Focus") ? $fresult['mainFocus'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Development Phase of Primary Product") ? $fresult['devPhase'] = $property->Value : '';
        hash_equals($property->PropertyMetadata->Title,"Presenting Company Categorization") ? $fresult['companyCategory'] = $property->Value : '';
      }
    }
    $formatted_results[] = $fresult;
  }
  return $formatted_results;
}

public function getCountByTag($event_id, $fieldType, $fieldName, $fieldValue,$multiselect = false){
  $url = $this->hubb_root;
  $curl_url = $url."/api/v1/".$event_id."/Sessions";
  $filter_url = '';
  $filter_url = urlencode("Status eq 'Accepted' and VisibleToAnonymousUsers eq true and Enabled eq true");
  $cond = '(';
  $value = $fieldValue;
  if(gettype($value) === 'string'){
    $value = "'".$value."'";
  }
  if($fieldType === 'standard'){
    $cond = $cond.urlencode($fieldName.' eq '.$value);
    }
  else if ($fieldType == 'custom'){
    if($multiselect){
      $cond = $cond."PropertyValues/any(pv:(pv/PropertyMetadata/Title".urlencode(" eq '" . $fieldName ."' and ")."substringof(".urlencode($value).",pv/Value".")"."))";              
    }
    else{
      $cond = $cond."PropertyValues/any(pv:(pv/PropertyMetadata/Title".urlencode(" eq '" . $fieldName ."' and ")."pv/Value".urlencode(" eq ").urlencode($value)."))";
    }
  }
  $cond = $cond . ')';
  $filter_url = $filter_url. urlencode(' and ') . $cond;
  $curl_url = $curl_url."?$"."filter=".$filter_url; 
  $curl_url = $curl_url."&$"."select=Id,Speakers"."&$"."expand=Speakers";
  $results = json_decode($this->getHubAPI($curl_url), TRUE);
  $count['sessions'] = empty($results) ? 0 : count($results);
  $speakers = [];
  if(!empty($results)){
    foreach($results as $item){
      if(!empty($item['Speakers']))
        foreach($item['Speakers'] as $speaker){
         $speakers[] = $speaker['Id'];
        }
    }
  }
  $speakers = array_unique($speakers);
  $count['speakers'] = empty($speakers) ? 0 : count($speakers);
  return $count; 
}

public function getSessionIdsByFilter($event_id, $condTags = NULL, $condDates = NULL){
   $url = $this->hubb_root;
  $curl_url = $url."/api/v1/".$event_id."/Sessions";
  $filter_url = '';
  $filter_url = urlencode("Status eq 'Accepted' and VisibleToAnonymousUsers eq true and Enabled eq true");
  if(!empty($condTags)) {
    if(count($condTags) > 0){
      for($i=0;$i<count($condTags);$i++){
        $condition = $condTags[$i];
        $cond = '(';
          $value = $condition['values'][0];
          if(gettype($value) === 'string'){
            $value = "'".$value."'";
          }
          if($condition['type'] === 'standard'){
            $cond = $cond.urlencode($condition['name'].' eq '.$value);
          }
          else if ($condition['type'] == 'custom'){
            if($condition['multiselect']){
              $cond = $cond."PropertyValues/any(pv:(pv/PropertyMetadata/Title".urlencode(" eq '" . $condition['name']."' and ")."substringof(".urlencode($value).",pv/Value".")"."))";              
            }
            else{
              $cond = $cond."PropertyValues/any(pv:(pv/PropertyMetadata/Title".urlencode(" eq '" . $condition['name']."' and ")."pv/Value".urlencode(" eq ").urlencode($value)."))";
            }
          }
          if(count($condition['values']) > 1){
            for($j=1;$j<count($condition['values']);$j++){
             $value = $condition['values'][$j];
             if(gettype($value) === 'string'){
              $value = "'".$value."'";
             }
             $cond = $cond.urlencode(' '.$condition['op'].' ');
             if($condition['type'] === 'standard'){
              $cond = $cond.urlencode($condition['name'].' eq '.$value);
              }
              else if ($condition['type'] == 'custom'){
                if($condition['multiselect']){
                  $cond = $cond."PropertyValues/any(pv:(pv/PropertyMetadata/Title".urlencode(" eq '" . $condition['name']."' and ")."substringof(".urlencode($value).",pv/Value".")"."))";
                }
                else{
                  $cond = $cond."PropertyValues/any(pv:(pv/PropertyMetadata/Title".urlencode(" eq '" . $condition['name']."' and ")."pv/Value".urlencode(" eq ").urlencode($value)."))";
                }
              }
            }
          }  

        $cond = $cond . ')';
        $filter_url = $filter_url . urlencode(" and ") . $cond;
      }
    }
  }
  //Date filtering
  if(!empty($condDates)){
    $date_filter_url = '';
    if(count($condDates) > 6){ //added this check to prevent max node_count error
      $dt = new \DateTime($condDates[0]['from']);
      $from_hours = $dt->format('H');
      $dt = new \DateTime($condDates[0]['to']);
      $to_hours = $dt->format('H');
      $to_hours = (int) $to_hours + 1;
      $date_filter_url = "hour(TimeSlot/StartTime)".urlencode(" ge "). $from_hours . urlencode(" and ") . "hour(TimeSlot/StartTime)".urlencode(" lt ") . $to_hours;
      $from_dates = [];
      foreach($condDates as $condDate){
        $dt = new \DateTime($condDate['from']);
        $from_dates[] = $dt->format('Y-m-d');
      }
    }
    else{
      foreach($condDates as $condDate){
        if(!empty($condDate['from']) && !empty($condDate['to'])){
          if(!empty($date_filter_url)) $date_filter_url .= urlencode(' or ');
          $date_filter_url .= urlencode("TimeSlot/StartTime ge DateTime'".$condDate['from']."'").urlencode(" and ").urlencode("TimeSlot/StartTime lt DateTime'".$condDate['to']."'"); 
        }
      }
    }
    
    if(empty($filter_url))
    $filter_url = $date_filter_url;
    else
    $filter_url = $filter_url . urlencode(" and ") . "(" . $date_filter_url . ")";  
  }

  $curl_url = $curl_url."?$"."filter=".$filter_url; 
  $curl_url = $curl_url."&$"."select=Id";
  if(count($condDates) > 6){  //Added this check to prevent max node_count error
    $curl_url = $curl_url.",TimeSlot/StartTime"."&$"."expand=TimeSlot";
    $results = json_decode($this->getHubAPI($curl_url),TRUE);
    // error
    if(!empty($results['Message'])) 
      return $curl_url."\n".$results['Message'];
    $ids = [];
    if(!empty($results)){
      foreach($results as $item){
        if(!empty($item['Id']))
          $dt = new \DateTime($item['TimeSlot']['StartTime']['UTC']);
          $date = $dt->format('Y-m-d');
          if(in_array($date, $from_dates)){
            $ids[] = $item['Id'];
          }
      }
    }
    return $ids; 
  }
  else{
    $curl_url = $curl_url.",TimeSlot/StartTime"."&$"."expand=TimeSlot";
    $results = json_decode($this->getHubAPI($curl_url),TRUE);
    // error
    if(!empty($results['Message'])) 
      return $curl_url."\n".$results['Message'];
    $ids = [];
    if(!empty($results)){
      foreach($results as $item){
        if(!empty($item['Id']))
          $ids[] = $item['Id'];
      }
    }
    return $ids; 
  } 
}

public function getSpeakerIdsByFilter($event_id, $condTags = NULL, $condDates = NULL){
  $url = $this->hubb_root;
 $curl_url = $url."/api/v1/".$event_id."/Sessions";
 $filter_url = '';
 $filter_url = urlencode("Status eq 'Accepted' and VisibleToAnonymousUsers eq true and Enabled eq true");
 if(!empty($condTags)) {
   if(count($condTags) > 0){
     for($i=0;$i<count($condTags);$i++){
       $condition = $condTags[$i];
       $cond = '(';
         $value = $condition['values'][0];
         if(gettype($value) === 'string'){
           $value = "'".$value."'";
         }
         if($condition['type'] === 'standard'){
           if(!empty($condition['speaker_field'])){
             $cond = $cond."Speakers/any(sv:sv/".urlencode($condition['name']." eq ".$value).")";
           }
           else{
             $cond = $cond.urlencode($condition['name'].' eq '.$value);
           }
         }
         else if ($condition['type'] == 'custom'){
           if($condition['multiselect']){
             $cond = $cond."PropertyValues/any(pv:(pv/PropertyMetadata/Title".urlencode(" eq '" . $condition['name']."' and ")."substringof(".urlencode($value).",pv/Value".")"."))";              
           }
           else{
             $cond = $cond."PropertyValues/any(pv:(pv/PropertyMetadata/Title".urlencode(" eq '" . $condition['name']."' and ")."pv/Value".urlencode(" eq ").urlencode($value)."))";
           }
         }
         if(count($condition['values']) > 1){
           for($j=1;$j<count($condition['values']);$j++){
            $value = $condition['values'][$j];
            if(gettype($value) === 'string'){
             $value = "'".$value."'";
            }
            $cond = $cond.urlencode(' '.$condition['op'].' ');
            if($condition['type'] === 'standard'){
              if(!empty($condition['speaker_field'])){
                $cond = $cond."Speakers/any(sv:sv/".urlencode($condition['name']." eq ".$value).")";
              }
              else{
                $cond = $cond.urlencode($condition['name'].' eq '.$value);
              }   
             }
             else if ($condition['type'] == 'custom'){
               if($condition['multiselect']){
                 $cond = $cond."PropertyValues/any(pv:(pv/PropertyMetadata/Title".urlencode(" eq '" . $condition['name']."' and ")."substringof(".urlencode($value).",pv/Value".")"."))";
               }
               else{
                 $cond = $cond."PropertyValues/any(pv:(pv/PropertyMetadata/Title".urlencode(" eq '" . $condition['name']."' and ")."pv/Value".urlencode(" eq ").urlencode($value)."))";
               }
             }
           }
         }  

       $cond = $cond . ')';
       $filter_url = $filter_url . urlencode(" and ") . $cond;
     }
   }
 }
 $curl_url = $curl_url."?$"."filter=".$filter_url; 
 $curl_url = $curl_url."&$"."select=Id,Speakers"."&$"."expand=Speakers";
 $results = json_decode($this->getHubAPI($curl_url),TRUE);
 // error
 if(!empty($results['Message'])) 
   return $curl_url."\n".$results['Message'];
 $speakers = [];
 if(!empty($results)){
   foreach($results as $item){
     if(!empty($item['Speakers']))
       foreach($item['Speakers'] as $speaker){
        $speakers[] =  $speaker['Id'];
       }
   }
 }
 $speakers = array_unique($speakers);
 return $speakers;  
}

}


