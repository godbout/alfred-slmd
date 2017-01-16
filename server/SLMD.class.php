<?php

use Abraham\TwitterOAuth\TwitterOAuth;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Ipalaus\Buffer\Client as BufferClient;
use Ipalaus\Buffer\TokenAuthorization as BufferTokenAuthorization;
use Ipalaus\Buffer\Update as BufferUpdate;

class SLMD
{
    protected $msqli = null;
    protected $writingData = null;
    protected $settings = null;

    public function __construct()
    {
        $this->mysqli = new mysqli('localhost', 'post.slmd.info', 'post.slmd.info', 'post.sleeplessmind.info');
        $this->mysqli->set_charset('utf8');

        $this->loadSettings();
    }

    public function getToken()
    {
        return $this->settings['token'];
    }

    public function postWritingToFacebook()
    {
        $fbSettings = $this->getFacebookSettings();
        $fb = new Facebook($fbSettings);
        $data = $this->getWritingDataForFacebook();

        try {
            $facebookResponse = $fb->post('/me/feed', $data);
            $this->updateWritingStatus();
            $response = 'Facebook: posted!';
        } catch (FacebookResponseException $e) {
            $response = 'Facebook Graph error: ' . $e->getMessage();

        } catch (FacebookSDKException $e) {
            $response = 'Facebook SDK error: ' . $e->getMessage();
        }

        return $response;
    }

    public function postWritingToTwitter()
    {
        $twSettings = $this->getTwitterSettings();
        $tw = new TwitterOAuth($twSettings['consumer_key'], $twSettings['consumer_secret'], $twSettings['access_token'], $twSettings['access_token_secret']);
        $data = $this->getWritingDataForTwitter($tw);

        $tw->post('statuses/update', $data);
        if ($tw->getLastHttpCode() == 200) {
            $this->updateWritingStatus();
            $response = 'Twitter: posted!';
        } else {
            $body = $tw->getLastBody();
            $response = 'Twitter error: (' . $body->errors[0]->code . ') ' . $body->errors[0]->message;
        }

        return $response;
    }

    public function postWritingToGooglePlus()
    {
        $bufSettings = $this->getBufferSettings();
        $bufToken = new BufferTokenAuthorization($bufSettings['access_token']);
        $buf = new BufferClient($bufToken);
        $data = $this->getWritingDataForGooglePlus();

        $bufUpdate = new BufferUpdate;

        $bufUpdate->text = $data['text'];
        $bufUpdate->addMedia('link', $data['link']);
        $bufUpdate->addMedia('description', $data['description']);
        $bufUpdate->addMedia('picture', $data['picture']);
        // $bufUpdate->addMedia('thumbnail', $data['picture']);

        $bufUpdate->shorten = 'false';
        $bufUpdate->now = 'false';

        $bufUpdate->addProfile($bufSettings['googleplus_id']);

        $bufResponse = $buf->createUpdate($bufUpdate);

        if ($bufResponse['success'] === true) {
            $response = 'Google+: posted!';
        } else {
            $response = 'Google+ (Buffer) error: ' . $bufResponse['message'];
        }

        return $response;
    }

    public function clean()
    {
        $this->mysqli->close();
    }

    public static function getWritingSlug($writingTitle = '')
    {
        $slug = str_replace(' ', '-', strtolower($writingTitle));
        $slug = preg_replace('/[^A-Za-z0-9\-\$\_\.\+\!\*\'\(\)\,]/', '', $slug);

        return $slug;
    }

    private function getCurrentWritingData()
    {
        if ($this->writingData === null) {
            $result = $this->mysqli->query('SELECT * FROM writings WHERE is_new = 1 ORDER BY id ASC');

            if ($result->num_rows === 0) {
                $result = $this->mysqli->query('SELECT * FROM writings WHERE is_published = 0');

                if ($result->num_rows === 0) {
                    $this->resetWritingsStatus();
                    $result = $this->mysqli->query('SELECT * FROM writings WHERE is_published = 0');
                }

                $rows = $result->fetch_all(MYSQLI_ASSOC);
                $data = $rows[array_rand($rows)];
            } else {
                $rows = $result->fetch_all(MYSQLI_ASSOC);
                $data = $rows[0];
            }

            $this->writingData = $data;

            $result->free();
        }

        return $this->writingData;
    }

    private function loadSettings()
    {
        $result = $this->mysqli->query('SELECT name, value FROM settings');
        $settings = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($settings as $key => $setting) {
            $this->settings[$setting['name']] = $setting['value'];
        }
        $result->free();
    }

    private function getFacebookSettings()
    {
        return json_decode($this->settings['facebook'], true);
    }

    private function getWritingDataForFacebook()
    {
        $data = $this->getCurrentWritingData();

        $facebookData = [
            'message' => $data['message'],
            'link' => $this->getWritingUrl($data['title']),
            'picture' => $this->getWritingPictureUrl($data['title']),
            'name' => $data['title'],
            'caption' => $data['caption'],
            'description' => $data['description'],
        ];

        return $facebookData;
    }

    private function getTwitterSettings()
    {
        return json_decode($this->settings['twitter'], true);
    }

    private function getWritingDataForTwitter(TwitterOAuth $tw)
    {
        $data = $this->getCurrentWritingData();
        $pictureUrl = $this->getWritingPictureUrl($data['title']);
        $media = $tw->upload('media/upload', ['media' => $pictureUrl]);

        $tweet = strtr($data['twitter_message'], ['{url}' => $this->getWritingUrl($data['title'])]);

        $twData = [
            'status' => $tweet,
            'media_ids' => $media->media_id_string,
        ];

        return $twData;
    }

    private function getBufferSettings()
    {
        return json_decode($this->settings['buffer'], true);
    }

    private function getWritingDataForGooglePlus()
    {
        $data = $this->getCurrentWritingData();

        $gpData = [
            'text' => $data['message'],
            'link' => $this->getWritingUrl($data['title']),
            'picture' => $this->getWritingPictureUrl($data['title']),
            'thumbnail' => $this->getWritingPictureUrl($data['title']),
            'description' => $data['description'],
        ];

        return $gpData;
    }

    private function getWritingUrl($writingTitle = '')
    {
        $slug = $this->getWritingSlug($writingTitle);

        return $this->settings['writings_url'] . $slug . '/';
    }

    private function getWritingPictureUrl($writingTitle = '')
    {
        $pictureName = $this->getWritingSlug($writingTitle);

        return $this->settings['pictures_url'] . $pictureName . '.png';
    }

    private function updateWritingStatus()
    {
        $this->mysqli->query('UPDATE writings SET is_published = 1, is_new = 0 WHERE id = ' . $this->writingData['id']);
    }

    private function resetWritingsStatus()
    {
        $this->mysqli->query('UPDATE writings SET is_published = 0');
    }
}
