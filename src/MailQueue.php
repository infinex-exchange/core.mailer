<?php

use PHPMailer\PHPMailer\Exception as PHPMailerException;

class MailQueue {
    private $log;
    private $sender;
    private $storage;
    
    function __construct($log, $sender, $storage) {
        $this -> log = $log;
        $this -> sender = $sender;
        $this -> storage = $storage;
        
        $this -> log -> debug('Initialized mail queue worker');
    }
    
    public function bind($amqp) {
        $th = $this;
        
        $amqp -> sub(
            'mail',
            function($body) use($th) {
                return $th -> newMail($body);
            }
        );
    }
    
    public function newMail($body) {
        try {
            $this -> sender -> mail(
                $body['email'],
                $body['template'],
                $body['context']
            );
            
            $this -> log -> info('Sent mail '.$body['template'].' to '.$body['email']);
        }
        catch(PHPMailerException $e) {
            $this -> log -> error('Failed to send mail '.$body['template'].' to '.$body['email'].': '.$e -> getMessage());
            throw $e;
        }
        
        try {
            $this -> storage -> insert(
                $body['email'],
                $body['template'],
                $body['context']
            );
        }
        catch(\Exception $e) {
            $this -> log -> error('Mail sent but not inserted to db: '.json_encode($body));
        }
    }
}

?>