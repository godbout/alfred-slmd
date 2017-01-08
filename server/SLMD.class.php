<?php

/**
 * TODO: 
 * move all the facebook and twitter crap into SLMD
 * make functions to post on each of the social networks and call from there, all the rest is handled by SLMD class
 * rename methods getDataForFacebook
 */

// use Abraham\TwitterOAuth\TwitterOAuth;
// // use Facebook\Facebook;

// $tw = new TwitterOAuth('KfPs0MpXnGjYHqdcH6gWtsAmf', 'HvxTa6FpPT5dTIdKF4QR0XViGIX2E9eK225QzSoakhLhs8m4Uf', '4882789453-qFGwYt34jUIVVT8TtCvpFZ75BSXU2tOhMCFLlu2', 'Gu4rCY7jy4FKLM2nNaFuVUuxdv3utX6kX7oUvRBu4K4eo');
// // $content = $tw->get('account/verify_credentials');
// $content = $tw->get("statuses/home_timeline", ["count" => 25, "exclude_replies" => true]);
// var_dump($content);
// die();


use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;

class SLMD
{
    protected $msqli = null;
    protected $writingId = 0;
    protected $settings = null;

    public function __construct()
    {
        $this->mysqli = new mysqli('localhost', 'post.slmd.info', 'post.slmd.info', 'post.sleeplessmind.info');
        $this->mysqli->set_charset('utf8');

        $this->loadSettings();
    }

    public function loadSettings()
    {
        $result = $this->mysqli->query('SELECT name, value FROM settings');
        $settings = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($settings as $key => $setting) {
            $this->settings[$setting['name']] = $setting['value'];
        }
        $result->free();
    }

    public function getToken()
    {
        return $this->settings['token'];
    }

    public function getFacebookSettings()
    {
        return json_decode($this->settings['facebook'], true);
    }

    public function postWritingToFacebook()
    {
        $fbSettings = $this->getFacebookSettings();

        $fb = new Facebook($fbSettings);

        $data = $this->getWritingDataForFacebook();

        try {
            $facebookResponse = $fb->post('/me/feed', $data);
            $this->updateWritingStatus();
            $response = 'Successfully posted on Facebook!';
        } catch (FacebookResponseException $e) {
            $response = 'Graph returned an error: ' . $e->getMessage();

        } catch (FacebookSDKException $e) {
            $response = 'Facebook SDK returned an error: ' . $e->getMessage();
        }

        return $response;
    }

    public function getWritingDataForFacebook()
    {
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

        $this->writingId = $data['id'];

        $facebookData = [
            'message' => $data['message'],
            'link' => $this->getWritingUrl($data['title']),
            'picture' => $this->getWritingPictureUrl($data['title']),
            'name' => $data['title'],
            'caption' => $data['caption'],
            'description' => $data['description'],
        ];

        $result->free();

        return $facebookData;
    }

    public function postWritingToTwitter()
    {
        $response = 'Not implemented yet.';

        return $response;
    }

    public function getWritingUrl($writingTitle = '')
    {
        $slug = $this->getWritingSlug($writingTitle);

        return $this->settings['writings_url'] . $slug . '/';
    }

    public function getWritingPictureUrl($writingTitle = '')
    {
        $pictureName = $this->getWritingSlug($writingTitle);

        return $this->settings['pictures_url'] . $pictureName . '.png';
    }

    public static function getWritingSlug($writingTitle = '')
    {
        $slug = str_replace(' ', '-', strtolower($writingTitle));
        $slug = preg_replace('/[^A-Za-z0-9\-\$\_\.\+\!\*\'\(\)\,]/', '', $slug);

        return $slug;
    }

    public function updateWritingStatus()
    {
        $this->mysqli->query('UPDATE writings SET is_published = 1, is_new = 0 WHERE id = ' . $this->writingId);
    }

    public function resetWritingsStatus()
    {
        $this->mysqli->query('UPDATE writings SET is_published = 0');
    }

    public function clean()
    {
        $this->mysqli->close();
    }
}
