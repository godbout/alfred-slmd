<?php

class SleeplessmindAuth
{
    protected $mysqli = null;
    protected $token = null;

    public function __construct()
    {
        $this->mysqli = new mysqli('localhost', 'post.slmd.info', 'post.slmd.info', 'post.sleeplessmind.info');
        $this->mysqli->set_charset('utf8');

        $this->getAuthToken();

        $this->mysqli->close();
    }

    public function authenticate($token = null)
    {
        $res = false;

        if ($token === $this->getAuthToken()) {
            $res = true;
        }

        return $res;
    }

    private function getAuthToken()
    {
        if ($this->token === null) {
            $result = $this->mysqli->query('SELECT value FROM settings WHERE name = \'token\'');
            $row = $result->fetch_all(MYSQLI_ASSOC);

            if (isset($row[0])) {
                $this->token = $row[0]['value'];
            }

            $result->free();
        }

        return $this->token;
    }
}
