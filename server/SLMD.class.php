<?php

class SLMD
{
    protected $msqli = null;
    protected $writingId = 0;

    public function __construct()
    {
        $this->mysqli = new mysqli('localhost', 'post.slmd.info', 'post.slmd.info', 'post.sleeplessmind.info');
    }

    public function getToken()
    {
        $result = $this->mysqli->query('SELECT value FROM settings WHERE name = \'token\'');
        $row = $result->fetch_assoc();
        $token = $row['value'];
        $result->close();

        return $token;
    }

    public function getFacebookSettings()
    {
        $result = $this->mysqli->query('SELECT value FROM settings WHERE name = \'facebook\'');
        $row = $result->fetch_assoc();
        $facebookSettings = json_decode($row['value'], true);
        $result->close();

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
            'link' => $data['link'],
            'picture' => $data['picture'],
            'name' => $data['title'],
            'caption' => $data['caption'],
            'description' => $data['description'],
        ];

        return $facebookData;
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
