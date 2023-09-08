<?php

class MailStorage {
    private $log;
    private $pdo;
    private $sender;
    
    function __construct($log, $pdo) {
        $this -> log = $log;
        $this -> pdo = $pdo;
        
        $this -> log -> debug('Initialized mail database storage');
    }
    
    public function insert($email, $template, $context) {
        $task = [
            ':email' => $email,
            ':template' => $template,
            ':context' => json_encode($context)
        ];
        
        $sql = 'INSERT INTO mails(
                    email,
                    template,
                    context,
                )
                VALUES(
                    :email,
                    :template,
                    :context,
                )';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
    }
}

?>