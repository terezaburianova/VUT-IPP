<?php

if (strcmp($argv[1], '--help') == 0) {
    if ($argc > 2) exit(10);
    echo("this is pure misery");
        exit(0);
}


$test = new testSettings($argc);

class testSettings {
    public $parseonly = false;
    public $intonly = false;
    public $recursive = false;
    public $testdir = './';
    public $parsedir = './parse.php';
    public $intdir = './interpret.py';
    public $jexamxml = '';
    public $jexamcfg = '';
    
    function __construct($argc) {
        $optionsList = array("directory:", "recursive", "parse-script:", "int-script:", "parse-only", "int-only", "jexamxml:", "jexamcfg:");
        $options = getopt(null, $optionsList, $optIndex);
        var_dump($options);
        var_dump($optIndex);
        var_dump($argc);
        //TODO ------------------
    }

}

class testRun {
    private $testSettings;
    
    function __construct() {
        $this->testSettings = new testSettings;
        
    }

    function parseRun() {
        exec("php7.4 " . $this->testSettings . "<" . $testName . ".src");
    }

    function testRun() {

    }
}


?>