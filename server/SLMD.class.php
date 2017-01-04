<?php

class SLMD
{
    protected $msqli = null;
    protected $writingId = 0;
    protected $settings = null;

    public function __construct()
    {
        $this->mysqli = new mysqli('localhost', 'post.slmd.info', 'post.slmd.info', 'post.sleeplessmind.info');

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
        $result = $this->mysqli->query('SELECT value FROM settings WHERE name = \'facebook\'');
        $row = $result->fetch_assoc();
        $facebookSettings = json_decode($row['value'], true);
        $result->free();

        return $facebookSettings;
    }

    public function getFacebookData()
    {
        $result = $this->mysqli->query('SELECT * FROM writings WHERE is_published = 0');

        if ($result->num_rows === 0) {
            $this->resetWritingsStatus();
            $result = $this->mysqli->query('SELECT * FROM writings WHERE is_published = 0');
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $data = $rows[array_rand($rows)];

        $this->writingId = $data['id'];

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
        $this->mysqli->query('UPDATE writings SET is_published = 1 WHERE id = ' . $this->writingId);
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
