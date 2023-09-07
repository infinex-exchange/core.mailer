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
        $sent = false;
        
        try {
            $this -> sender -> mail(
                $body['email'],
                $body['template'],
                $body['context']
            );
            
            $sent = true;
        }
        catch(PHPMailerException $e) {
            //
        }
        
        $this -> storage -> addMail(
            $body['email'],
            $body['template'],
            $body['context'],
            $sent
        );
    }
}

?>