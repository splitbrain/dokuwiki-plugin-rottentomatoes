<?php

class rottentomatoes_template {

    private $json;
    private $data;
    private $tpldir;

    public function __construct($json,$data){
        $this->json = $json;
        $this->data = $data;
        $this->tpldir = dirname(__FILE__).'/tpl/';
    }

    public function load($tpl){
        if(!file_exists($this->tpldir.$tpl.'.php')){
            return 'Failed to load template';
        }

        ob_start();
        include($this->tpldir.$tpl.'.php');
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    /**
     * Access data with a dot separated key
     */
    private function getraw($key){
        $keys = explode('.',$key);

        // what data to acess?
        if($keys[0] == 'data'){
            array_shift($keys);
            $from = $this->data;
        }else{
            $from = $this->json;
        }

        // walk the keys
        while($k = array_shift($keys)){
            if(isset($from[$k])){
                $from = $from[$k];
            }else{
                return false;
            }
        }

        return $from;
    }

    private function out($key){
        echo hsc($this->getraw($key));
    }

    private function imgsrc($key,$w=0,$h=0){
        echo ml($this->getraw($key),array('w'=>$w,'h'=>$h));
    }

    private function stars($key){
        $val = $this->getraw($key);
        if(!$val) return;
        $val = round($val/20);
        for($i=0; $i<$val; $i++){
            echo '★';
        }
        for($i=$val; $i<5; $i++){
            echo '☆';
        }
    }
}
