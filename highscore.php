<?php
class config {
    /*DB Configuration*/
    static private $db_driver = "mysql"; //PDO driver
    static private $db_user = ""; //DB USER
    static private $db_password = ""; //DB PASS
    static private $db_name = ""; //DB NAME
    static private $db_server = ""; //DB SERVER

    public static function db_driver(){
        return self::$db_driver;
    }
    public static function db_user(){
        return self::$db_user;
    }
    public static function db_password(){
        return self::$db_password;
    }
    public static function db_name(){
        return self::$db_name;
    }
    public static function db_server(){
        return self::$db_server;
    }
}

class database extends PDO {
    public function __construct(){
        $dns = config::db_driver().":dbname=".config::db_name().";host=".config::db_server();
        parent::__construct($dns, config::db_user(), config::db_password());
        $this->exec("set names utf8");
    }
}
class highScore {
    protected $log;
    protected $score;
    protected $nick;
    protected $json;
    private $db;
    public function __construct()
    {
        $this->db = new database();
        if ($this->getSendJSON()) {
            $this->score = $this->json['score'];
            $this->log = $this->json['log'];
            $this->nick = $this->json['nick'];
            $this->render($this->saveScore());
        }
        if (isset($_GET['highscore'])) {
            $this->render($this->getHighScore());
        }
        $this->render($this->error());
    }
    protected function getSendJSON(){
        if(empty($this->json)){
            $this->json = json_decode(file_get_contents('php://input'),true);
        }
        return $this->json;
    }
    protected function getLog()
    {
        return $this->log;
    }
    protected function reCalcScore()
    {
        $score = 0;
        $strike = 0;
        $timeouts = 0;
        foreach($this->getLog() as $line){
            if ($line['event'] === 'click' && $line['subevent'] == '+ score' && $line['object']=='xmas') {
                $score = $score + 10;
            }
            if ($line['event'] === 'timeout' && $line['subevent'] == '+ timeout' && $line['object']=='xmas') {
                $score = $score - 1;
                $timeouts = $timeouts + 1;
            }
            if ($line['event'] === 'click' && $line['subevent'] == '+ strike' && $line['object']=='grinch') {
                $strike = $strike + 1;
            }
            if ($line['event'] === 'timeout' && $line['subevent'] == 'grinched out' && $line['object']=='grinch') {
                $score = $score + 1;
            }
        }
        return $score;
    }
    protected function saveScore(){
        try{
            $reCalcScore = $this->reCalcScore();
            $data = [
                'nick' => $this->nick,
                'score' => $this->score,
                'recalcscore' => $reCalcScore,
                'log' => json_encode($this->log),
            ];
            $stmt = $this->db->prepare("INSERT INTO xmasgame (nick, score, recalcscore, log) VALUES (:nick, :score, :recalcscore, :log)");
            $stmt->execute($data);
            return [
                'state'=>'ok',
                'message'=>'Vše v pořádku uloženo.',
                'score'=>$reCalcScore,
                'request'=>json_encode($_POST['game']),
            ];
        }catch(Exception $e){
            return [
            'state' => 'error',
            'message' => 'MYSQL CHYBA: '.$e->getMessage(),
            'request' => json_encode(($this->json)?$this->json:null)
            ];
        }
    }
    protected function getHighScore(){
        $highScore = $this->db->query("SELECT nick, recalcscore as score FROM xmasgame ORDER BY score DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
        return [
            'state'=>'ok',
            'message' => 'výsledky naloadovany',
            'data' => json_encode($highScore)
        ];
    }
    protected function error(){
        return [
            'state' => 'error',
            'message' => 'Žádná, nebo nevalidní data.',
            'request' => json_encode(($this->json)?$this->json:null)
        ];
    }
    protected function render($array){
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($array);
        flush();
        exit();
    }
}
$highScore = new highScore();
