
<?php

class VaultREAPI {
    
private $base_url = 'https://ap-southeast-2.api.vaultre.com.au/api/v1.2';
    
                public function __construct($api_key, $bearer_token) {
        $this->api_key = $api_key;
        $this->bearer_token = $bearer_token;
    }
    public function get($endpoint) {
        $ch = curl_init($this->base_url . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'X-Api-Key: ' . $this->api_key, 'Authorization: Bearer ' . $this->bearer_token));
        $result = curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array($response_code, $result);
    }
    
    
    
}

function getRequest($req)
{

                $api_key = ''; //BASE 64 Encoded key
                $access_token = ''; //BASE 64 Encoded Token

                $api = new VaultREAPI($api_key, $access_token);

                // Get categories
                list($code, $result) = $api->get($req);
                if ($code == 403) {
                    echo "HTTP 403: Invalid API Key";
                    exit();
                } elseif ($code == 401) {
                    echo "HTTP 401: Invalid bearer token\n";
                    exit();
                }

        return $result;



}



//Paste code under here
function getAll(){
        $result = getRequest("/properties/residential/lease");
        $Strresult = json_decode( $result );
        $json_string = json_encode($Strresult, JSON_PRETTY_PRINT);
        echo $json_string;
}

getAll();

?>
