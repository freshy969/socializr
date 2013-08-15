<?php

namespace Borfast\Socializr\Engines;

use Borfast\Socializr\Engines\AbstractEngine;
use OAuth\Common\Storage\TokenStorageInterface;

class Facebook extends AbstractEngine
{
    public static $provider_name = 'Facebook';

    public function post($content)
    {
        $path = '/'.$this->getUid().'/feed';
        $method = 'POST';
        $params = array(
            'message' => $content,
        );

        $response = $this->service->request($path, 'POST', $params);

        return $response;
    }


    public function storeOauthToken($params)
    {
        $this->service->requestAccessToken($params['code']);
    }


    public function getUid()
    {
        $profile = $this->getProfile();

        return $profile['id'];
    }

    public function getProfile()
    {
        $response = $this->service->request('/me');
        return json_decode($response, true);
    }
}