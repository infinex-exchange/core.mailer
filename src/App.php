<?php

require __DIR__.'/Sender.php';
require __DIR__.'/MailQueue.php';
require __DIR__.'/MailStorage.php';

class App extends Infinex\App\Daemon {
    private $pdo;
    private $sender;
    private $queue;
    private $storage;
    
    function __construct() {
        parent::__construct('auth.api-auth');
        
        $this -> pdo = new Infinex\Database\PDO($this -> loop, $this -> log);
        $this -> pdo -> start();
        
        $this -> sender = new Sender($this -> log);
        
        $this -> storage = new MailStorage($this -> log, $this -> pdo);
        $this -> storage -> start();
        
        $this -> queue = new MailQueue($this -> log, $this -> sender, $this -> storage);
        
        $th = $this;
        $this -> amqp -> on('connect', function() use($th) {
            $th -> queue -> bind($th -> amqp);
        });
    }
}

?>