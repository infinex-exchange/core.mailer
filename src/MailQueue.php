<?php

use Infinex\Exceptions\Error;
use function Infinex\Validation\validateId;
use function Infinex\Validation\validateEmail;
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
        
        if(!isset($body['uid']) && !isset($body['email'])) {
            $this -> log -> error('Ignoring mail without uid or email');
            return;
        }
        
        if(!isset($body['template'])) {
            $this -> log -> error('Ignoring mail without template');
            return;
        }
        
        if(isset($body['uid']) && !validateId($body['uid'])) {
            $this -> log -> error('Ignoring mail with invalid uid');
            return;
        }
        
        if(isset($body['email'] && !validateEmail($body['email'])) {
            $this -> log -> error('Ignoring mail with invalid email address');
            return;
        }
        
        if(!is_string($body['template'])) {
            $this -> log -> error('Ignoring mail with invalid template');
            return;
        }
        
        if(isset($body['context'] && !is_array($body['context'])) {
            $this -> log -> error('Ignoring mail with non-array context');
            return;
        }
        
        $promise = null;
        if(isset($body['email']))
            $promise = Promise\resolve([
                'email' => $body['email']
            ]);
        else
            $promise = $this -> amqp -> call(
                'account.account',
                'getUser',
                [ 'uid' => $body['uid'] ]
            );
        
        return $promise -> then(
            function($user) use($th, $body) {
                try {
                    $th -> sender -> mail(
                        $user['email'],
                        $body['template'],
                        @$body['context']
                    );
                
                    $th -> log -> info('Sent mail '.$body['template'].' to '.$user['email']);
                }
                catch(\Exception $e) {
                    $th -> log -> error('Failed to send mail '.$body['template'].' to '.$user['email'].': '.((string) $e));
                    throw $e;
                }
            
                try {
                    $this -> storage -> insert(
                        @$body['uid'],
                        $user['email'],
                        $body['template'],
                        @$body['context']
                    );
                }
                catch(\Exception $e) {
                    $th -> log -> error('Mail sent but not inserted to db '.json_encode($body).': '.((string) $e));
                }
            },
            
            function(Error $e) use($th, $body) {
                if($e -> getStrCode() == 'NOT_FOUND') {
                    $th -> log -> error('Ignoring mail to non existing user '.$body['uid']);
                    return;
                }
                throw $e;
            }
        );
    }
}

?>