<?php

require __DIR__.'/Sender.php';
require __DIR__.'/MailQueue.php';
require __DIR__.'/MailStorage.php';

class App extends Infinex\App\App {
    private $pdo;
    private $sender;
    private $queue;
    private $storage;
    
    function __construct() {
        parent::__construct('core.mailer');
        
        $this -> pdo = new Infinex\Database\PDO(
            $this -> loop,
            $this -> log,
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );
        
        $this -> sender = new Sender(
            $this -> log,
            SMTP_HOST,
            SMTP_PORT,
            SMTP_USER,
            SMTP_PASS,
            MAIL_FROM,
            MAIL_FROM_NAME
        );
        
        $this -> storage = new MailStorage(
            $this -> log,
            $this -> pdo
        );
        
        $this -> queue = new MailQueue(
            $this -> log,
            $this -> amqp,
            $this -> sender,
            $this -> storage
        );
    }
    
    public function start() {
        $th = $this;
        
        parent::start() -> then(
            function() use($th) {
                return $th -> pdo -> start();
            }
        ) -> then(
            function() use($th) {
                return $th -> queue -> start();
            }
        ) -> catch(
            function($e) {
                $th -> log -> error('Failed start app: '.((string) $e));
            }
        );
    }
    
    public function stop() {
        $th = $this;
        
        $this -> queue -> stop() -> then(
            function() use($th) {
                return $th -> pdo -> stop();
            }
        ) -> then(
            function() use($th) {
                $th -> parentStop();
            }
        );
    }
    
    private function parentStop() {
        parent::stop();
    }
}

?>