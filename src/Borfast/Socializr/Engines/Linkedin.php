<?php

namespace Borfast\Socializr\Engines;

use Borfast\Socializr\Post;
use Borfast\Socializr\Profile;
use Borfast\Socializr\Page;
use Borfast\Socializr\Group;
use Borfast\Socializr\Response;
use Borfast\Socializr\Engines\AbstractEngine;
use OAuth\Common\Storage\TokenStorageInterface;

class Linkedin extends AbstractEngine
{
    public static $provider_name = 'linkedin';


    public function post(Post $post)
    {
        $path = '/people/~/shares?format=json';
        $method = 'POST';
        $params = [
            'visibility' => [
                'code' => 'anyone'
            ],
            'comment' => $post->body,
            'content' => [
                'title' => $post->title,
                'submitted-url' => $post->url,
                'description' => $post->description,
            ]
        ];
        $params = json_encode($params);

        $header = ['Content-Type' => 'application/json'];
        $result = $this->service->request($path, $method, $params, $header);

        $response = new Response;
        $response->setRawResponse(json_encode($result));
        $response->setProvider(static::$provider_name);
        $result_json = json_decode($result);
        $response->setPostId($result_json->updateKey);
        $response->setPostUrl($result_json->updateUrl);

        return $response;
    }


    public function getUid()
    {
        return $this->getProfile()->id;
    }

    public function getProfile($uid = null)
    {
        $path = '/people/~:(id,first-name,last-name,maiden-name,public-profile-url,formatted-name,num-connections,email-address,num-recommenders)?format=json';
        $response = $this->service->request($path);
        $profile_json = json_decode($response, true);

        $mapping = [
            'id' => 'id',
            'email' => 'emailAddress',
            'name' => 'formattedName',
            'first_name' => 'firstName',
            'middle_name' => 'maidenName',
            'last_name' => 'lastName',
            // 'username' => 'username',
            'link' => 'publicProfileUrl'
        ];

        $profile = Profile::create($mapping, $profile_json);
        $profile->provider = static::$provider_name;
        $profile->raw_response = $response;

        return $profile;
    }

    // @todo Get actual statistics from LinkedIn.
    public function getStats($uid = null)
    {
        return 33;
    }


    public function getPages()
    {
        $path = '/companies?is-company-admin=true&format=json';
        $response = $this->service->request($path);
        $companies = json_decode($response, true);

        $pages = [];

        $mapping = [
            'id' => 'id',
            'name' => 'name',
            'picture' => 'squareLogoUrl',
            'link' => 'publicProfileUrl'
        ];

        // Make the page IDs available as the array keys and get their picture
        if (!empty($companies['values'])) {
            foreach ($companies['values'] as $company) {
                $path = '/companies/'.$company['id'].':(id,name,universal-name,square-logo-url,num-followers)?format=json';
                $company_info = json_decode($this->service->request($path), true);

                $pages[$company['id']] = Page::create($mapping, $company_info);
                $pages[$company['id']]->link = 'http://www.linkedin.com/company/'.$company_info['universalName'];
                $pages[$company['id']]->provider = static::$provider_name;
                $pages[$company['id']]->raw_response = $response;
            }
        }

        return $pages;
    }


    public function getGroups()
    {
        $path = '/people/~/group-memberships:(group:(id,name,site-group-url,small-logo-url,num-members,relation-to-viewer))?&format=json';
        $response = $this->service->request($path);
        $groups = json_decode($response, true);

        $group_ = [];

        $mapping = [
            'id' => 'id',
            'name' => 'name',
            'picture' => 'smallLogoUrl',
            'link' => 'siteGroupUrl'
        ];

        // Make the page IDs available as the array keys and get their picture
        if (!empty($groups['values'])) {
            foreach ($groups['values'] as $group) {
                $group_pages[$group['_key']] = Group::create($mapping, $group['group']);
                $group_pages[$group['_key']]->provider = static::$provider_name;
                $group_pages[$group['_key']]->raw_response = $response;

                // Let's check if our user can post to this group.
                // Thank you for this wonder, LinkedIn! It's so fun parsing infinitely nested arrays...
                $actions = $group['group']['relationToViewer']['availableActions']['values'];
                array_walk($actions, function ($value, $key) use ($group, $group_pages) {
                    if ($value['code'] === 'add-post') {
                        $group_pages[$group['_key']]->can_post = true;
                    }
                });
            }
        }

        return $group_pages;
    }
}