<?php

class JenkinsHelper
{
    private $j_url = JENKINS_URL;
    private $j_project = JENKINS_PROJECT;
    private $j_user_name = JENKINS_USER;
    private $j_pwd = JENKINS_PWD;

    public function __construct()
    {
        
    }

    public function getCrumb()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => "$this->j_url/crumbIssuer/api/json",
            CURLOPT_USERPWD => "$this->j_user_name:$this->j_pwd",
        ));
        $resp = curl_exec($curl);
        $crumbResult = json_decode($resp);
        curl_close($curl);
        return $crumbResult;
    }

    public function buildJob($cmd, $related_schema, $process_id)
    {
        $headers = [];
        $curl = curl_init();
        $data = array('build_cmd' => $cmd, 'plugin' => $related_schema, 'process_id' => $process_id);
        $crumbResult = $this->getCrumb();

        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => array(
                "$crumbResult->crumbRequestField:$crumbResult->crumb",
            ),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => "$this->j_url/job/$this->j_project/buildWithParameters",
            CURLOPT_POST => 1,
            CURLOPT_USERPWD => "$this->j_user_name:$this->j_pwd",
            CURLOPT_HEADER => 1,
            CURLOPT_POSTFIELDS => $data,
        ));

        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$headers) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) { // ignore invalid headers
                return $len;
            }

            $name = strtolower(trim($header[0]));
            if (!array_key_exists($name, $headers)) {
                $headers[$name] = [trim($header[1])];
            } else {
                $headers[$name][] = trim($header[1]);
            }
            
            return $len;
        });

        $resp = curl_exec($curl);
        if ($headers['location']) {
            $Q_URL = $headers['location'][0] . 'api/json?depth=1';
            $queue_id = explode('/', $headers['location'][0])[5];
        }
        curl_close($curl);

        return $queue_id;
    }

    public function getConsoleText($build_id, $start = 0)
    {
        $res = array();
        $res['console_text'] = "";
        if($_POST['interval_counter'] == 1) {
            $ct = $this->getFullConsoleText($build_id);
            // $ct_a = preg_replace("/(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9\-]+\.((?:\.?)(?:[a-zA-Z0-9_\=?\-\/]+))+)/", "<a href='$1'>$1</a>", $ct);
            $body = $this->suppressOutput($ct);

            $ct_size = $this->getStringSize($ct);
            $start = $ct_size + 271 ;
            $res['console_text'] .= $body;
        }
        $p_url = "$this->j_url/job/$this->j_project/${build_id}/logText/progressiveText";
        $ct = $this->getProgressiveHTML($p_url, $start);
	$res['ct_url'] = $p_url;
        $res['console_text'] .= $ct['body'];
        $res['response_header'] = $ct['header'];
        $res['ct_response_size'] = $start;
        return $res;
    }

    public function getFullConsoleText($build_id):string
    {
        $ct_url = "$this->j_url/job/$this->j_project/${build_id}/consoleText";
        return $ct = @file_get_contents($ct_url);
    }

    public function getProgressiveHTML($ct_url, $start = 0)
    {
        $curl = curl_init();
        $data = array('start' => $start);
        $crumbResult = $this->getCrumb();
        curl_setopt_array($curl, array (
            CURLOPT_HTTPHEADER => array (
                "$crumbResult->crumbRequestField:$crumbResult->crumb",
            ),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $ct_url,
            CURLOPT_POST => 1,
            CURLOPT_USERPWD => "$this->j_user_name:$this->j_pwd",
            CURLOPT_HEADER => 1,
            CURLOPT_POSTFIELDS => $data,
        ));
        $resp = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($resp, 0, $header_size);
        $response_body = substr($resp, $header_size);
        // $response_body = preg_replace('/<a (.*)>(.*)<\/a>/i', '$2', $response_body);
       
        $header_arr = $this->get_headers_from_curl_response($header);
        return array("header" => array("http_code" => $header_arr['http_code'], "x_text_size" => $header_arr['X-Text-Size']), "body" => $response_body);
    }

    public function getStringSize($content)
    {
        // return strlen($content);
        return mb_strlen($content, "utf-8");
    }

    public function suppressOutput($ct) 
    {
        $suppressed_size = null;
        $response_size = $this->getStringSize($ct); 
        $maximum_buffer = 1024 * 200;
        if($response_size > $maximum_buffer) {
            // $suppressed_size = $this->getStringSize(substr($ct, 0, strlen($ct)-$maximum_buffer));
            $suppressed_size = strlen($ct) - $maximum_buffer;
            $suppressed_size = round($suppressed_size / 1024, 0) . "KB";
            $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $cur_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            // $body = "Skipping {$suppressed_size} .. " . "<a href='javascript:void(0)'>Full Log</a> \n";
            $body = "Skipping {$suppressed_size}.. \n";
            $body .= substr($ct, -$maximum_buffer);
        } else {
            $body = $ct;
        }
        return $body;
    }

    public function stopJob($build_id)
    {
        $crumbResult = $this->getCrumb();
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => array(
                "$crumbResult->crumbRequestField:$crumbResult->crumb",
            ),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => "$this->j_url/job/$this->j_project/${build_id}/stop",
            CURLOPT_POST => 1,
            CURLOPT_USERPWD => "$this->j_user_name:$this->j_pwd",
        ));
        $resp = curl_exec($curl);
        curl_close($curl);
    }

    public function cancelJob($queue_id)
    {
        $crumbResult = $this->getCrumb();
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => array(
                "$crumbResult->crumbRequestField:$crumbResult->crumb",
            ),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => "$this->j_url/queue/cancelItem?id=${queue_id}",
            CURLOPT_POST => 1,
            CURLOPT_USERPWD => "$this->j_user_name:$this->j_pwd",
        ));
        $resp = curl_exec($curl);
        curl_close($curl);
    }

    public function get_headers_from_curl_response($header_content)
    {
        $headers = array();
        $arrRequests = explode("\r\n\r\n", $header_content);
        for ($index = 0; $index < count($arrRequests) -1; $index++) {
            foreach (explode("\r\n", $arrRequests[$index]) as $i => $line) {
                if ($i === 0) {
                    preg_match('|HTTP/\d\.\d\s+(\d+)\s+.*|', $line, $match);
                    $headers['http_code'] = $match[1];
                } else {
                    list ($key, $value) = explode(': ', $line);
                    $headers[$key] = $value;
                }
            }
        }
        return $headers;
    }
}
