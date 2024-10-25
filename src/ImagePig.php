<?php

namespace ImagePig;

class APIResult extends \stdClass {
    public $content;
    const DOWNLOAD_ATTEMPTS = 10;
    const DOWNLOAD_INTERRUPTION = 1;

    public function __construct($content) {
        $this->content = $content;
    }

    public function __get($name) {
        switch ($name) {
            case 'content':
                return $this->getContent();
            case 'data':
                return $this->getData();
            case 'image':
                return $this->getImage();
            case 'url':
                return $this->getUrl();
            case 'seed':
                return $this->getSeed();
            case 'mime_type':
                return $this->getMimeType();
            case 'duration':
                return $this->getDuration();
        }

        throw new \Exception('Trying to access unknown property: ' . $name);
    }

    public function getContent() {
        return $this->content;
    }

    public function getData() {
        if (array_key_exists('image_data', $this->content)) {
            return base64_decode($this->content['image_data']);
        }

        if (array_key_exists('image_url', $this->content)) {
            foreach (range(1, self::DOWNLOAD_ATTEMPTS) as $i) {
                $ch = curl_init($this->content['image_url']);

                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'User-Agent: Mozilla/5.0',
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FORBID_REUSE, true);

                $data = curl_exec($ch);
                $status_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

                if ($status_code == 200) {
                    return $data;
                }

                if ($status_code == 404) {
                    sleep(self::DOWNLOAD_INTERRUPTION);
                } else {
                    throw new \Exception('Unexpected response when downloading, got HTTP code ' . $status_code);
                }
            }
        }

        return null;
    }

    public function getImage() {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('The GD extensions is not loaded, make sure you have installed it: https://www.php.net/manual/image.setup.php');
        }

        return imagecreatefromstring(base64_decode($this->data));
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

    public function __construct($api_key, $raise_exception = true, $api_url = 'https://api.imagepig.com') {
        $this->api_key = $api_key;
        $this->api_url = $api_url;
        $this->raise_exception = $raise_exception;

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
            throw new \Exception('cURL error: ' . curl_error($ch));
        }

        $status_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($status_code !== 200 && $this->raise_exception) {
            throw new \Exception('Unexpected response when sending request, got HTTP code ' . $status_code);
        }

        return new APIResult(json_decode($response, true));
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
            throw new \Exception('Unknown proportion value: ' . $proportion);
        }

        $params['proportion'] = $proportion;

        return $this->callApi('flux', $params);
    }

    private function prepareImage($image, $name, $params) {
        if (strncmp($image, 'http://', 7) === 0 || strncmp($image, 'https://', 8) === 0) {
            $params[$name .'_url'] = $image;
        } else {
            if (!extension_loaded('gd')) {
                throw new \RuntimeException('The GD extensions is not loaded, make sure you have installed it: https://www.php.net/manual/image.setup.php');
            }

            $im = @imagecreatefromstring($image);

            if ($im !== false) {
                unset($im);
                $params[$name . '_data'] = base64_encode($image);
            } else {
                throw new \Exception('The ' . $name . ' argument is not a valid URL or image data.');
            }
        }

        return $params;
    }

    public function faceswap($source_image, $target_image, $params = []) {
        $params = $this->prepareImage($source_image, 'source_image', $params);
        $params = $this->prepareImage($target_image, 'target_image', $params);
        return $this->callApi('faceswap', $params);
    }

    public function upscale($image, $upscaling_factor=2, $params = []) {
        $params = $this->prepareImage($image, 'image', $params);

        if (!in_array($upscaling_factor, [2, 4, 8])) {
            throw new \Exception('Unknown upscaling factor value: ' . $upscaling_factor);
        }
        $params['upscaling_factor'] = $upscaling_factor;

        return $this->callApi('upscale', $params);
    }

    public function cutout($image, $params = []) {
        $params = $this->prepareImage($image, 'image', $params);
        return $this->callApi('cutout', $params);
    }

    public function replace($image, $select_prompt, $positive_prompt, $negative_prompt = '', $params = []) {
        $params = $this->prepareImage($image, 'image', $params);
        $params['select_prompt'] = $select_prompt;
        $params['positive_prompt'] = $positive_prompt;
        $params['negative_prompt'] = $negative_prompt;
        return $this->callApi('replace', $params);
    }

    public function outpaint($image, $positive_prompt, $top = 0, $right = 0, $bottom = 0, $left = 0, $negative_prompt = '', $params = []) {
        $params = $this->prepareImage($image, 'image', $params);
        $params['positive_prompt'] = $positive_prompt;
        $params['negative_prompt'] = $negative_prompt;
        $params['top'] = $top;
        $params['right'] = $right;
        $params['bottom'] = $bottom;
        $params['left'] = $left;
        return $this->callApi('outpaint', $params);
    }
}
