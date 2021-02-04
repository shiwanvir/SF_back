<?php
/**
 * Created by PhpStorm.
 * User: sankap
 * Date: 4/23/2020
 * Time: 11:29 AM
 */

namespace App\Libraries;


class SAPService
{
    // Host name
    private $hanaHost = 'https://172.23.1.232';

    // Port
    private $port = 50000;

    // Login credentials
    private $credentials = [
        "UserName" => "Admin",
        "Password" => "@2019#",
        "CompanyDB" => "STYLE_DB",
    ];

    //SAP version
    private $version = '/b1s/v1/';

    private $curl;

    private $routeId;

    private $headers = array();

    public function connect(){
        $this->curl = curl_init();
    }

    public function disConnect(){
        curl_close($this->curl);
    }

    public function setVersion($versionNo){
        $this->version = $versionNo;
    }

    private function loginRequest(){
        $this->connect();

        curl_setopt($this->curl, CURLOPT_URL, $this->hanaHost . ":" . $this->port.$this->version.'Login');
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_VERBOSE, 1);
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($this->credentials));

        $this->setOpt();
        $response = $this->executeReq();

        $resArray = json_decode($response);
        $this->headers[] = "Cookie: B1SESSION=" . $resArray->SessionId . "; ROUTEID=" . $this->routeId . ";";
        $this->disConnect();
    }

    private function executeReq(){
        return curl_exec($this->curl);
    }

    public function setOpt(){
        curl_setopt($this->curl, CURLOPT_HEADERFUNCTION, function($curl, $string) use (&$routeId){
            $len = strlen($string);

            if(substr($string, 0, 10) == "Set-Cookie"){
                preg_match("/ROUTEID=(.+);/", $string, $match);

                if(count($match) == 2){
                    $this->routeId = $match[1];
                }
            }
            return $len;
        });
    }

    private function postRequest($command, $body=null){
		if($body == null){
			$body = $this->credentials;
		}
		
        $this->connect();

        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($this->curl, CURLOPT_URL, $this->hanaHost . ":" . $this->port.$this->version.$command);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_VERBOSE, 1);
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($body));

        $response = json_decode($this->executeReq());


        if(isset($response->error)){
            $error['error'] = $response->error->message->value;
            return $error;
        }else{
            return true;
        }


        $this->disConnect();
    }

    private function getRequest($command){
        $this->connect();

        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($this->curl, CURLOPT_URL, $this->hanaHost . ":" . $this->port.$this->version.$command);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = $this->executeReq();

        if(isset($response->error)){
            $error['error'] = $response->error->message->value;
            return $error;
        }else{
            return true;
        }

        $this->disConnect();
    }

    public function cancelDoc($param){

        $this->loginRequest();
        return $this->postRequest($param);
  
    }

}