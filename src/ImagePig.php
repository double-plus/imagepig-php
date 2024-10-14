<?php

namespace ImagePig;

class APIResult extends \stdClass {
    public $content;

    public function __construct($content) {
        $this->content = $content;
    }

    public function __get($name) {
        switch ($name) {
            case 'data':
                return $this->getData();
            case 'url':
                return $this->getUrl();
            case 'seed':
                return $this->getSeed();
            case 'mime_type':
                return $this->getMimeType();
            case 'duration':
                return $this->getDuration();
        }

        throw new Exception('Trying to access unknown property: ' . $name);
    }

    public function getData() {
        if (array_key_exists('image_data', $this->content)) {
            return base64_decode($this->content['image_data']);
        }

        if (array_key_exists('image_url', $this->content)) {
            $ch = curl_init($this->content['image_url']);

            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: Mozilla/5.0',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);

            return curl_exec($ch);
        }

        return null;
    }

    public function getUrl() {
        return $this->content['image_url'] ?? null;
    }

    public function getSeed() {
        return $this->content['seed'] ?? null;
    }

    public function getMimeType() {
        return $this->content['mime_type'] ?? null;
    }

    public function getDuration() {
        if (array_key_exists('started_at', $this->content) && array_key_exists('completed_at', $this->content)) {
            $started_at = new \DateTime($this->content['started_at']);
            $completed_at = new \DateTime($this->content['completed_at']);
            return $started_at->diff($completed_at)->format('%f') / pow(10, 6);
        }

        return null;
    }

    public function save($path) {
        $handle = fopen($path, 'wb');
        fwrite($handle, $this->data);
        fclose($handle);
    }
}


class ImagePig {
    public $api_key;
    public $api_url;

    public function __construct($api_key, $api_url = 'https://api.imagepig.com') {
        $this->api_key = $api_key;
        $this->api_url = $api_url;

        if (!extension_loaded('curl')) {
            throw new \RuntimeException('The cURL extensions is not loaded, make sure you have installed it: https://php.net/manual/curl.setup.php');
        }
    }

    private function callApi($endpoint, $payload) {
        $ch = curl_init($this->api_url . '/' . $endpoint);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Api-Key: ' . $this->api_key,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        return new APIResult(json_decode($response, true));
    }

    private function checkUrl($string) {
        $url = parse_url($string);

        if (!in_array($url['scheme'], ['http', 'https']) || !$url['host']) {
            throw new Exception('Invalid URL: ' . $string);
        }
    }

    public function default($prompt, $negative_prompt = '', $params = []) {
        $params['positive_prompt'] = $prompt;
        $params['negative_prompt'] = $negative_prompt;

        return $this->callApi('', $params);
    }

    public function xl($prompt, $negative_prompt = '', $params = []) {
        $params['positive_prompt'] = $prompt;
        $params['negative_prompt'] = $negative_prompt;

        return $this->callApi('xl', $params);
    }

    public function flux($prompt, $proportion = 'landscape', $params = []) {
        $params['positive_prompt'] = $prompt;

        if (!in_array($proportion, ['landscape', 'portrait', 'square', 'wide'])) {
            throw new Exception('Unknown proportion value: ' . $proportion);
        }

        $params['proportion'] = $proportion;

        return $this->callApi('flux', $params);
    }

    public function faceswap($source_image_url, $target_image_url, $params = []) {
        $this->checkUrl($source_image_url);
        $this->checkUrl($target_image_url);

        $params['source_image_url'] = $source_image_url;
        $params['target_image_url'] = $target_image_url;

        return $this->callApi('faceswap', $params);
    }
}