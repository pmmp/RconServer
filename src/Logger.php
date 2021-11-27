<?php

namespace pmmp\RconServer;

class Logger extends \ThreadedLoggerAttachment{
    
    /** @var array */
    private array $messages;

    /** @var Logger */
    private static $instance;

    public function __construct(){
        $this->messages = array();
        self::$instance = $this;
    }

    public function log($level, $message){
        $this->addMessages($message);
        $this->getMessages();
    }

    public static function getInstance(){
        return self::$instance;
    }

    public function getMessages(){
        return $this->messages;
    }

    public function removeMessage($id){
        unset($this->messages[$id]);
    }

    public function removeMessages(){
        $this->messages = array();
    }

    public function addMessages(string $message){
        $this->messages[] = $message;
    }
}