<?php
# Copyright (C) 2020 @Darboo
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with This program. If not, see <http://www.gnu.org/licenses/>.
#
# Version : v1.0
#

// App configuration
$appName = 'eedomus';
$adressPort = '8483';

// Load variable
$action = getArg('action');
$adressIp = getArg('ip');
$serialNumber = getArg('serialNumber');
$password = getArg('password');

// Change the variable to have correct size
$appName = str_pad($appName, 12, ' ', STR_PAD_LEFT);
$serialNumber = str_pad($serialNumber, 6, '0', STR_PAD_LEFT);
$password = str_pad($password, 10, '0', STR_PAD_LEFT);

//Initialize variable
$sequenceNumber = 0;
if ($action == 'installation'){
    // Installation Step 1
    // Check the stove
    $controllerId = getArg('controllerId');
    if ($response = sdk_sendRequest(0, 'NBE Discovery')){
        // Get the RSA Key
        if ($response = sdk_sendRequest(1, 'misc.rsa_key')){
            $arrayResponse = explode('rsa_key=',$response);
            $rsaKey = $arrayResponse[1];
            // Generate new xtea key
            $xtea_key = '';
            $xtea_key_string = '"';
            for ($i = 0; $i < 16; $i++) {
                $rand = rand (0, 255);
                $xtea_key .= chr($rand);
                $xtea_key_string .= '.chr('.$rand.')';
            }
            $xtea_key_string .= '."';
            saveVariable('xtea_key',$xtea_key);
            saveVariable('xtea_key_time',time());
            // Curently not possible to use openssl so display help to do the RSA encryption of this request, for other request will use the xtea encryption
            $phpCode = '<?php'."\n";
            $phpCode .= '$keyFile=\'-----BEGIN PUBLIC KEY-----'."\n";
            $phpCode .= substr($rsaKey,0,64)."\n";
            $phpCode .= substr($rsaKey,64)."\n";
            $phpCode .= '-----END PUBLIC KEY-----\';'."\n";
            $payload = sdk_makeRequestSecondPart(2,'misc.xtea_key='.$xtea_key, true);
            $payload = str_replace(chr(2), '\\002', $payload);
            $payload = str_replace(chr(4), '\\004', $payload);
            $payload = str_replace($xtea_key, $xtea_key_string, $payload);
            $phpCode .= '$input = "'.$payload.'";'."\n";
            $phpCode .= '$pubkey = openssl_get_publickey($keyFile);'."\n";
            $phpCode .= 'openssl_public_encrypt($input,$encrypted,$pubkey, OPENSSL_NO_PADDING);'."\n";
            $phpCode .= '$hexaString = \'\';'."\n";
            $phpCode .= 'for ($i = 0; $i < strlen($encrypted); $i++) {'."\n";
            $phpCode .= '    $hexaString .= str_pad(dechex(ord($encrypted[$i])), 2, \'0\', STR_PAD_LEFT);'."\n";
            $phpCode .= '}'."\n";
            $phpCode .= 'echo $hexaString;'."\n";
            $phpCode .= '?>';

            $urlRedirect = 'https://secure.eedomus.com/box_http_query.php?controller_id='.$controllerId.'&url=http%3A%2F%2Flocalhost%2Fscript%2F%3Fexec%3DAduroApp.php%26action%3DinstallationPart2%26ip%3D'.$adressIp.'%26serialNumber%3D'.$serialNumber.'%26password%3D'.$password;

            echo '<script>';
			echo 'function validate(){';
			echo ' document.getElementById(\'form_id\').action = "'.$urlRedirect.'%26encrypt%3D" + document.getElementById(\'encrypt\').value;';
			echo '}';
			echo '</script>';
            echo 'Copier dans le presse papier the code Php :<br><textarea rows="14" cols="100">'.$phpCode.'</textarea><br>';
            echo 'Et le coller <a href="https://www.jdoodle.com/php-online-editor/" target="_blank">ici</a><br>';
            echo '<form method="POST" id="form_id">';
            echo 'Executer le code et copier le resultat ici : <input type="text" id="encrypt"><br>';
            echo 'Valider le formulaire : <input type="submit" onClick="validate();"><form>';
        }else{
            echo 'Error to get RSA Key';
        }
    }else{
        echo 'Error : No stove on this IP';
    }
}else if ($action == 'installationPart2'){
    // Installation Step 2
    $hexaString = getArg('encrypt');
    $encrypted = '';
    for ($i = 0; $i < strlen($hexaString); $i=$i+2) {
        $encrypted .= chr(hexdec(substr($hexaString,$i,2)));
    }
    $sequenceNumber = 3;  // Set the sequence in the previous installation
    if ($response = sdk_sendRequest(2, $encrypted, true, true)){
        echo 'Installation Terminée';
    }else{
        echo 'Error to set xta Key';
    }
}else if ($action == 'read'){
    sdk_readCustomValues();    
}else if ($action == 'start'){
    if ($response = sdk_sendRequest(2, 'misc.start=1', true)){
        echo 'ok';
    }else{
        echo 'Error of the test';
    }
}else if ($action == 'stop'){
    if ($response = sdk_sendRequest(2, 'misc.stop=1', true)){
        echo 'ok';
    }else{
        echo 'Error of the test';
    }
}else if ($action == 'resetAlarm'){
    if ($response = sdk_sendRequest(2, 'misc.reset_alarm=1', true)){
        echo 'ok';
    }else{
        echo 'Error of the test';
    }
}else{
	echo 'Error : No Action';
}

function sdk_readCustomValues(){
    $customKeys = array (
        'boiler_temp',
        'boiler_ref',
        'content',
        'dhw_temp',
        'dhw_ref',
        'dhw_valve_state',
        'state',
        'substate_sec',
        'substate',
        'ash_clean',
        'compressor_clean',
        'boiler_pump_state',
        'house_valve_state',
        'house_pump_state',
        'house2_pump_state',
        'exhaust_speed',
        'off_on_alarm',
        'chill_out',
        'external_temp',
        'forward_temp',
        'forward_ref',
        'mean_out_temp',
        'distance',
        'pressure',
        'feed_high',
        'feed_low',
        'oxygen',
        'oxygen_ref',
        'photo_level',
        'corr_low',
        'corr_high',
        'power_kw',
        'return_temp',
        'flow1',
        'corr_medium',
        'shaft_temp',
        'power_pct',
        'smoke_temp',
        'internet_uptime',
        'sun_pumpspeed',
        'sun_temp',
        'sun2_temp',
        'sun_power_kw',
        'sun_dhw_temp',
        'city',
        'outdoor_temp',
        'house2_valve_state',
        'clouds',
        'mean2_out_temp',
        'humidity',
        'wind_direction',
        'chill2_out',
        'air_pressure',
        'wind_speed',
        'forward2_temp',
        'forward2_ref',
        'boiler.diff_over',
        'auger.kw_min',
        'auger.kw_max',
        'auger.auger_capacity',
        'hot_water.diff_under',
        'hot_water.output',
        'hot_water.timer',
        'regulation.boiler_gain_i',
        'regulation.boiler_gain_p',
        'hopper.trip1',
        'hopper.trip2',
        'hopper.auger_capacity',
        'wifi.router',
        'cleaning.output_ash',
        'cleaning.output_burner',
        'cleaning.output_boiler1',
        'cleaning.output_boiler2',
        'cleaning.pressure_t7',
        'pump.output',
        'pump.start_temp_run',
        'pump.start_temp_idle',
        'weather.active',
        'weather.output_pump',
        'weather.output_up',
        'weather.output_down',
        'weather2.active',
        'weather2.output_pump',
        'weather2.output_up',
        'weather2.output_down',
        'fan.output_exhaust',
        'fan.exhaust_10',
        'fan.exhaust_50',
        'fan.exhaust_100',
        'sun.output_pump',
        'sun.output_excess',
        'consumption_midnight',
        'consumption_total',
        'consumption_heat_vvb',
        'time',
        'sun_pump_state',
        'sun_surplus_state',
        'state_super',
        'state_sec',
        'regulation.fixed_power',
        'operation_mode',
        'co_yellow',
        'co_red',
        'setup.varmluft_setpunkt',
        'drift.back_pressure',
        'drift.varmeblaeser_pct',
        'drift.t1_temp',
        'drift.wifi_load',
        'drift.co',
        'setup.min_beholdning',
        'compressor_countdown',
        'drift.vacuum_aktiv',
        'vacuum_time',
        'drift.askeskuffekontakt',
        'drift.askeskuffe_minutter',
        'boiler.timer'
        );
    if ($response = sdk_sendRequest(11, '*')){
        $customKeysNumber = count($customKeys);
        $customValues = explode(',', $response);
        $i=0;
        echo '<?xml version="1.0" encoding="utf8" ?>';
        echo '<aduro>';
        foreach ($customValues as $customValue){
            if ($i<$customKeysNumber){
                echo '<'.$customKeys[$i].'>'.$customValue."</".$customKeys[$i].">\n";
            }
            $i++;
        }
        echo '</aduro>';
    }else{
        echo 'error';
    }
}

function sdk_makeRequestSecondPart($functionCode, $payload, $encrypted = false){
    global $password;
    global $sequenceNumber;
    $sequenceNumber++;
    $sequenceNumber = str_pad($sequenceNumber, 2, '0', STR_PAD_LEFT);
    if ($encrypted){
        $passwordToSend = $password;
    }else{
        $passwordToSend = str_pad('', 10, '0');
    }
    $timestamp = time();
    $extra = '    ';
    $payloadsize = strlen($payload);

    $functionCode = str_pad($functionCode, 2, '0', STR_PAD_LEFT);
    $sequenceNumber = str_pad($sequenceNumber, 2, '0', STR_PAD_LEFT);
    $payloadsize = str_pad($payloadsize, 3, '0', STR_PAD_LEFT);
    $frame = "\002".$functionCode.$sequenceNumber.$passwordToSend.$timestamp.$extra.$payloadsize.$payload."\004";
    $frame = str_pad($frame, 64, ' ');
    return $frame;
}

function sdk_sendRequest($functionCode, $payload, $encrypted = false, $alreadyEncrypted = false){
/*
----- Structure ------ 
    app_id	        12	string
    controller id	6	string
    encrypted	    1	' '=No, '*'=RSA, '-'=xtea
    start	        1	STX=2
    function	    2	ascii function code number
    seqnum	        2	ascii sequence number
    pincode	        10	ascii, spaces when unencrypted
    timestamp	    10	ascii seconds since 01.01.1970
    extra	        4	future use
    payloadsize	    3	ascii number
    payload	        n	
    end	            1	EOT=4
----- Function ------ 
    0	Discovery
    1	Read setup value
    2	Set setup value
    3	Read setup range
    4	Read operating data
    5	Read advanced data
    6	Read consumption data
    7	Read chart data
    8	Read event log
    9	Read info
    10	Read avail. programs        
--------------------- 
----- Function ------ 
*/        
    global $appName;
    global $serialNumber;
    global $sequenceNumber;
    global $xtea_key;
    
    if ($encrypted && $alreadyEncrypted){
        // In case of sending xtea so encrypted in RSA format
        $encryptedChar = '*';
    }else if ($encrypted){
        echo 'xtea_key:'.loadVariable('xtea_key').'-';
        echo 'xtea_key_time:'.loadVariable('xtea_key_time').'-';
        $xtea_key = loadVariable('xtea_key');
        $encryptedChar = '-';
        $payload = sdk_makeRequestSecondPart($functionCode, $payload, $encrypted);
        $iv = '';
        for ($i = 0; $i < 8; $i++) {
            $iv .= chr(rand (0, 255));
        }
        $payload = mcrypt_encrypt(MCRYPT_XTEA , $xtea_key, $payload, MCRYPT_MODE_ECB, $iv);

    }else{
        $encryptedChar = ' ';
        $payload = sdk_makeRequestSecondPart($functionCode, $payload, $encrypted);
    }

    $frame = $appName.$serialNumber.$encryptedChar.$payload;
    if ($response = sdk_sendData($frame)){
        /*
        app_id	        12	string
        controller id	6	string
        start	        1	STX=2
        function	    2	ascii function code number
        seqnum	        2	ascii sequence number
        response code	1	
        payloadsize	    3	ascii number
        payload	        n	
        end	            1	EOT=4
        */
        $responseAppName = substr($response, 0, 12);
        $responseSerialNumber = substr($response, 12, 6);
        $responseBegin = substr($response, 18, 1);
        $responseFunctionCode = substr($response, 19, 2);
        $responseSequenceNumber = substr($response, 21, 2);
        $responseCode = substr($response, 23, 1);
        $responsePayloadSize = substr($response, 24, 3);
        $responsePayload = substr($response, 27, $responsePayloadSize);
        $responseEnd = substr($response, 27 + $responsePayloadSize, 1);

        if ($responseAppName != $appName){
            echo 'Error in Response AppName : '.$responseAppName.' / '.$appName."\n";
            echo $response."\n";
            return false;
        }
        if ($responseSerialNumber != $serialNumber){
            echo 'Error in Response SerialNumber : '.$responseSerialNumber.' / '.$serialNumber."\n";
            echo $response."\n";
            return false;
        }
        if ($responseBegin != chr(2)){
            echo 'Error in Response Begin : '.ord($responseBegin).' / 2'."\n";
            echo $response."\n";
            return false;
        }
        if ($responseFunctionCode != $functionCode){
            echo 'Error in Response functionCode : '.$responseFunctionCode.' / '.$functionCode."\n";
            echo $response."\n";
            return false;
        }
        if ($responseSequenceNumber != $sequenceNumber){
            echo 'Error in Response sequenceNumber : '.$responseSequenceNumber.' / '.$sequenceNumber."\n";
            echo $response."\n";
            return false;
        }
        if ($responseCode != '0'){
            echo 'Error in Response responseCode : '.$responseCode.' / 0'."\n";
            echo $response."\n";
            return false;
        }
        if ($responseEnd != chr(4)){
            echo 'Error in Response Begin : '.ord($responseEnd).' / 4'."\n";
            echo $response."\n";
            return false;
        }
        if ($responsePayload == ''){
            return true;
        }else{
            return $responsePayload;
        }
    }else{
        echo 'No Response'."\n";
        return false;
    }
}

function sdk_sendData($data){
    global $adressIp;
    global $adressPort;
	//Create a UDP socket
	if(!($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
	{
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);
		die("Couldn't create socket: [$errorcode] $errormsg \n");
	}
	
    //socket_bind($adressPort, $adressIp);
    socket_bind($sock, 0,8483);
    
    socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>10, "usec"=>0));
    socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, array("sec"=>10, "usec"=>0));
	//open socket
	$result = socket_connect($sock, $adressIp , $adressPort);
	if ($result === false) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        die("socket_connect() failed: [$errorcode] $errormsg \n");
	}
	socket_write($sock, $data, strlen($data));

	$buf = '';
	$bytes = socket_recvfrom($sock, $buf, 1024, MSG_WAITALL, $name, $port);
	socket_close($sock);
	if ($bytes != ''){
	    //verification of the response
	    return $buf;
	}else{
	    return false;
	}
}

?>