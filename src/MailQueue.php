<?php

use React\Promise;

class MailQueue {
    private $log;
    private $amqp;
    private $sender;
    private $storage;
    
    function __construct($log, $amqp, $sender, $storage) {
        $this -> log = $log;
        $this -> amqp = $amqp;
        $this -> sender = $sender;
        $this -> storage = $storage;
        
        $this -> log -> debug('Initialized mail queue consumer');
    }
    
    public function start() {
        $th = $this;
        
        return $this -> amqp -> sub(
            'mail',
            function($body) use($th) {
                return $th -> newMail($body);
            }
        ) -> then(
            function() use($th) {
                $th -> log -> info('Started mail queue consumer');
            }
        ) -> catch(
            function($e) use($th) {
                $th -> log -> error('Failed to start mail queue consumer: '.((string) $e));
                throw $e;
            }
        );
    }
    
    public function stop() {
        $th = $this;
        
        return $this -> amqp -> unsub('mail') -> then(
            function() use ($th) {
                $th -> log -> info('Stopped mail queue consumer');
            }
        ) -> catch(
            function($e) use($th) {
                $th -> log -> error('Failed to stop mail queue consumer: '.((string) $e));
            }
        );
    }
    
    public function newMail($body) {
        $th = $this;
        
        $promise = null;
        if(isset($body['email']))
            $promise = Promise\resolve($body['email']);
        else
            $promise = $this -> amqp -> call(
                'account.accountd',
                'uidToEmail',
                [ 'uid' => $body['uid'] ]
            );
        
        return $promise -> then(
            function($email) use($th, $body) {
                try {
                    $th -> sender -> mail(
                        $email,
                        $body['template'],
                        $body['context']
                    );
                
                    $th -> log -> info('Sent mail '.$body['template'].' to '.$email);
                }
                catch(\Exception $e) {
                    $th -> log -> error('Failed to send mail '.$body['template'].' to '.$email.': '.((string) $e));
                    throw $e;
                }
            
                try {
                    $this -> storage -> insert(
                        $body['uid'],
                        $email,
                        $body['template'],
                        $body['context']
                    );
                }
                catch(\Exception $e) {
                    $th -> log -> error('Mail sent but not inserted to db '.json_encode($body).': '.((string) $e));
                }
            }
        );
    }
}

?>