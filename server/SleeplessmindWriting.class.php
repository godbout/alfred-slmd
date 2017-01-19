<?php

use Abraham\TwitterOAuth\TwitterOAuth;
use DirkGroenen\Pinterest\Exceptions\PinterestException;
use DirkGroenen\Pinterest\Pinterest;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Ipalaus\Buffer\Client as BufferClient;
use Ipalaus\Buffer\TokenAuthorization as BufferTokenAuthorization;
use Ipalaus\Buffer\Update as BufferUpdate;

class SleeplessmindWriting
{
    protected $msqli = null;
    protected $data = null;
    protected $settings = null;

    public function __construct()
    {
        $this->mysqli = new mysqli('localhost', 'post.slmd.info', 'post.slmd.info', 'post.sleeplessmind.info');
        $this->mysqli->set_charset('utf8');

        $this->loadSettings();
    }

    public function __destruct()
    {
        $this->mysqli->close();
    }

    public function postToFacebook()
    {
        $fbSettings = $this->getFacebookSettings();
        $fb = new Facebook($fbSettings);
        $data = $this->getDataForFacebook();

        try {
            $facebookResponse = $fb->post('/me/feed', $data);
            $response = 'Facebook: posted!';
        } catch (FacebookResponseException $e) {
            $response = 'Facebook Graph error: ' . $e->getMessage();

        } catch (FacebookSDKException $e) {
            $response = 'Facebook SDK error: ' . $e->getMessage();
        }

        return $response;
    }

    public function posttoTwitter()
    {
        $twSettings = $this->getTwitterSettings();
        $tw = new TwitterOAuth($twSettings['consumer_key'], $twSettings['consumer_secret'], $twSettings['access_token'], $twSettings['access_token_secret']);
        $data = $this->getDataForTwitter($tw);

        $tw->post('statuses/update', $data);
        if ($tw->getLastHttpCode() == 200) {
            $response = 'Twitter: posted!';
        } else {
            $body = $tw->getLastBody();
            $response = 'Twitter error: (' . $body->errors[0]->code . ') ' . $body->errors[0]->message;
        }

        return $response;
    }

    public function postToGooglePlus()
    {
        $bufSettings = $this->getBufferSettings();
        $bufToken = new BufferTokenAuthorization($bufSettings['access_token']);
        $buf = new BufferClient($bufToken);
        $data = $this->getDataForGooglePlus();

        $bufUpdate = new BufferUpdate;

        $bufUpdate->text = $data['text'];
        $bufUpdate->addMedia('photo', $data['photo']);
        $bufUpdate->shorten = $data['shorten'];
        $bufUpdate->now = $data['now'];

        $bufUpdate->addProfile($bufSettings['googleplus_id']);

        $bufResponse = $buf->createUpdate($bufUpdate);

        if ($bufResponse['success'] === true) {
            $response = 'Google+: posted!';
        } else {
            $response = 'Google+ (Buffer) error: ' . $bufResponse['message'];
        }

        return $response;
    }

    public function postToInstagram()
    {
        $bufSettings = $this->getBufferSettings();
        $bufToken = new BufferTokenAuthorization($bufSettings['access_token']);
        $buf = new BufferClient($bufToken);
        $data = $this->getDataForInstagram();

        $bufUpdate = new BufferUpdate;

        $bufUpdate->text = $data['text'];
        $bufUpdate->addMedia('photo', $data['photo']);
        $bufUpdate->shorten = $data['shorten'];
        $bufUpdate->now = $data['now'];

        $bufUpdate->addProfile($bufSettings['instagram_id']);

        $bufResponse = $buf->createUpdate($bufUpdate);

        if ($bufResponse['success'] === true) {
            $response = 'Instagram: posted!';
        } else {
            $response = 'Instagram (Buffer) error: ' . $bufResponse['message'];
        }

        return $response;
    }

    public function postToPinterest()
    {
        $pint = new Pinterest('4879426962216466139', '9c2031b2ac6319741b9a8327af0bc4e7c903db7e3faa6185b6952dea5fd66921');
        $pint->auth->setOAuthToken('Aa_9dMT3G8TSgb8J4iJBS5NNcLKlFJqZ38UeOYxDtzXTHeAx7AAAAAA');

        $data = $this->getDataForPinterest();

        try {
            $pin = $pint->pins->create($data);
            $response = 'Pinterest: posted!';
        } catch (PinterestException $e) {
            $response = 'Pinterest error: ' . $e->getMessage();
        }

        return $response;
    }

    public function postToAllPlatforms()
    {
        $response = $this->postToFacebook();
        $response .= "\r\n" . $this->postToTwitter();
        $response .= "\r\n" . $this->postToGooglePlus();
        $response .= "\r\n" . $this->postToInstagram();
        $response .= "\r\n" . $this->postToPinterest();

        return $response;
    }

    public static function getSlug($title = '')
    {
        $slug = str_replace(' ', '-', strtolower($title));
        $slug = preg_replace('/[^A-Za-z0-9\-\$\_\.\+\!\*\'\(\)\,]/', '', $slug);

        return $slug;
    }

    public function updateStatus()
    {
        $this->mysqli->query('UPDATE writings SET is_published = 1, is_new = 0 WHERE id = ' . $this->data['id']);
    }

    private function getData()
    {
        if ($this->data === null) {
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

            $this->data = $data;

            $result->free();
        }

        return $this->data;
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

    private function getDataForFacebook()
    {
        $data = $this->getData();

        $message = '';
        $message .= ($data['is_new'] == true ? '#NEW: ' : '');
        $message .= $data['message'];
        $message .= (empty($data['hashtags']) ? '' : ("\r\n\r\n" . $data['hashtags']));

        $facebookData = [
            'message' => $message,
            'link' => $this->getUrl($data['title']),
            'picture' => $this->getPictureUrl($data['title']),
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

    private function getDataForTwitter(TwitterOAuth $tw)
    {
        $data = $this->getData();
        $pictureUrl = $this->getPictureUrl($data['title']);
        $media = $tw->upload('media/upload', ['media' => $pictureUrl]);

        $tweet = strtr($data['tweet'], ['{url}' => $this->getUrl($data['title'])]);

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

    private function getDataForGooglePlus()
    {
        $data = $this->getData();

        $message = '';
        $message .= ($data['is_new'] == true ? '#NEW: ' : '');
        $message .= $data['message'];
        $message .= "\r\n\r\n";
        $message .= $this->getUrl($data['title']);
        $message .= "\r\n\r\n";
        $message .= '"' . $data['description'] . '"';
        $message .= (empty($data['hashtags']) ? '' : ("\r\n\r\n" . $data['hashtags']));

        $gpData = [
            'text' => $message,
            'photo' => $this->getPictureUrl($data['title']),
            'shorten' => 'false',
            'now' => 'true',
        ];

        return $gpData;
    }

    private function getDataForInstagram()
    {
        $data = $this->getData();

        $message = '';
        $message .= ($data['is_new'] == true ? '#NEW: ' : '');
        $message .= $data['message'];
        $message .= "\r\n.\r\n";
        $message .= $this->getUrl($data['title']);
        $message .= "\r\n.\r\n";
        $message .= '"' . $data['description'] . '"';
        $message .= (empty($data['hashtags']) ? '' : ("\r\n.\r\n" . $data['hashtags']));

        $instData = [
            'text' => $message,
            'photo' => $this->getPictureUrl($data['title']),
            'shorten' => 'false',
            'now' => 'true',
        ];

        return $instData;
    }

    private function getDataForPinterest()
    {
        $data = $this->getData();

        $message = '';
        $message .= ($data['is_new'] == true ? '#NEW: ' : '');
        $message .= $data['message'];
        $message .= (empty($data['hashtags']) ? '' : (' ' . $data['hashtags']));

        $pintData = [
            'note' => $message,
            'image_url' => $this->getPictureUrl($data['title']),
            'board' => 'slmd_info/writings',
            'link' => $this->getUrl($data['title']),
        ];

        return $pintData;
    }

    private function getUrl($title = '')
    {
        $slug = $this->getSlug($title);

        return $this->settings['writings_url'] . $slug . '/';
    }

    private function getPictureUrl($title = '')
    {
        $pictureName = $this->getSlug($title);

        return $this->settings['pictures_url'] . $pictureName . '.png';
    }

    private function resetStatus()
    {
        $this->mysqli->query('UPDATE writings SET is_published = 0');
    }
}
