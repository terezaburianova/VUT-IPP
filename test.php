<?php

ini_set('display_errors', 'stderr');

if (strcmp($argv[1], '--help') == 0) {
    if ($argc > 2) exit(10);
    echo("this is pure misery");
        exit(0);
}

class test_settings {
    public $parseonly = false;
    public $intonly = false;
    public $recursive = false;
    public $testdir = './';
    public $parsedir = 'parse.php';
    public $intdir = 'interpret.py';
    public $jexamxml = '/pub/courses/ipp/jexamxml/jexamxml.jar';
    public $jexamcfg = '/pub/courses/ipp/jexamxml/options';
    public $testsrc = null;
    
    function __construct() {
        $optionsList = array("directory:", "recursive", "parse-script:", "int-script:", "parse-only", "int-only", "jexamxml:", "jexamcfg:");
        $options = getopt(null, $optionsList, $optIndex);

        $this->opts_validity($options, $optIndex);
        $this->fill_variables($options);
    }

    function opts_validity($options, $optIndex) {
    /**
     * Checks options validity
     *
     * @param array $options Parsed options.
     * @param int Index where the argument parsing stopped.
     */
        if ($optIndex > sizeof($options) + 1) {
            //! ERROR: invalid options
            exit(10);
        }
        if (array_key_exists("parse-only", $options) and (array_key_exists("int-only", $options) or array_key_exists("int-script", $options))) {
            //!ERROR: Invalid combination of arguments.
            exit(10);
        }
        if (array_key_exists("int-only", $options) and array_key_exists("parse-script", $options)) {
            //!ERROR: Invalid combination of arguments.
            exit(10);
        }
        foreach ($options as $opt) {
            if (gettype($opt) == 'array') {
                //!ERROR: Option used several times.
                exit(10);
            }
        }
    }

    function fill_variables($options) {
    /**
     * Fills class parameters with present option values.
     * 
     * @param array $options Parsed options.
     */
        if (array_key_exists("directory", $options)) {
            $this->testdir = realpath($options["directory"]);
        }
        if (array_key_exists("recursive", $options)) {
            $this->recursive = true;
        }
        if (array_key_exists("parse-script", $options)) {
            $this->parsedir = realpath($options["parse-script"]);
        }
        if (array_key_exists("int-script", $options)) {
            $this->intdir = realpath($options["int-script"]);
        }
        if (array_key_exists("parse-only", $options)) {
            $this->parseonly = true;
        }
        if (array_key_exists("int-only", $options)) {
            $this->intonly = true;
        }
        if (array_key_exists("jexamxml", $options)) {
            $this->jexamxml = realpath($options["jexamxml"]);
        }
        if (array_key_exists("jexamcfg", $options)) {
            $this->jexamcfg = realpath($options["jexamcfg"]);
        }
    }
}

class test_run {

    private $settings = null;
    private $testsrc = null;
    private $success = 0;
    private $fail = 0;
    private $HTMLgen = null;

    function __construct() {
        $this->settings = new test_settings();
        var_dump($this->settings->testdir);
        $this->get_tests();
        $this->HTMLgen = new generate_HTML();
        $this->HTMLgen->create_setting('Adresář: ' . $this->settings->testdir);
        if ($this->settings->recursive) {
            $this->HTMLgen->create_setting('Rekurzivní prohledávání');
        }
        if ($this->settings->parseonly) {
            $this->HTMLgen->create_setting('Režim parse-only');
            $this->parse_only();
        } elseif ($this->settings->intonly) {
            $this->HTMLgen->create_setting('Režim int-only');
            $this->int_only();
        } else {
            $this->HTMLgen->create_setting('Režim parse i int');
            $this->both();
        }
        $this->HTMLgen->add_stats(sizeof($this->testsrc), $this->success, $this->fail);
        $this->HTMLgen->print_html();
    }

    function parse_only() {
        $test_index = 0;
        foreach ($this->testsrc as $test) {
            $test_index++;
            $parse_return = $this->parse_run(realpath($test), false);
            $rc_eq = $this->check_rc($parse_return, realpath($test));
            if (!$rc_eq) {
                $this->fail++;
                $this->HTMLgen->add_fail($test, $test_index);
                continue;
            }
            if ($parse_return == 0) {
                $out_eq = $this->check_out_xml(realpath($test));
                if (!$out_eq) {
                    $this->fail++;
                    $this->HTMLgen->add_fail($test, $test_index);
                    continue;
                }
            }
            $this->success++;
            $this->HTMLgen->add_success($test, $test_index);
        }
    }

    function int_only() {
        $test_index = 0;
        foreach ($this->testsrc as $test) {
            $test_index++;
            $int_return = $this->int_run(realpath($test), false);
            $rc_eq = $this->check_rc($int_return, realpath($test));
            if (!$rc_eq) {
                $this->fail++;
                $this->HTMLgen->add_fail($test, $test_index);
                continue;
            }
            if ($int_return == 0) {
                $out_eq = $this->check_out(realpath($test));
                if (!$out_eq) {
                    $this->fail++;
                    $this->HTMLgen->add_fail($test, $test_index);
                    continue;
                }
            }
            $this->success++;
            $this->HTMLgen->add_success($test, $test_index);
        }
    }

    function both() {
        $test_index = 0;
        foreach ($this->testsrc as $test) {
            $test_index++;
            $parse_return = $this->parse_run(realpath($test), true);
            if ($parse_return != 0) {
                $rc_eq = $this->check_rc($parse_return, realpath($test));
                if (!$rc_eq) {
                    $this->fail++;
                    $this->HTMLgen->add_fail($test, $test_index);
                    continue;
                }
            }
            $int_return = $this->int_run(realpath($test), false);
            $rc_eq = $this->check_rc($int_return, realpath($test));
            if (!$rc_eq) {
                $this->fail++;
                $this->HTMLgen->add_fail($test, $test_index);
                continue;
            }
            if ($int_return == 0) {
                $out_eq = $this->check_out(realpath($test));
                if (!$out_eq) {
                    $this->fail++;
                    $this->HTMLgen->add_fail($test, $test_index);
                    continue;
                }
            }
            $this->success++;
            $this->HTMLgen->add_success($test, $test_index);
        }
    }

    function get_tests() {
        if ($this->settings->recursive) {
            $iter = new RecursiveDirectoryIterator($this->settings->testdir);
            foreach (new RecursiveIteratorIterator($iter) as $file) {
                if ($file->getExtension() == 'src') {
                    $this->testsrc[] = $file->getPathname();
                }
            }
        } else {
            $dir = new DirectoryIterator($this->settings->testdir);
            $iter = new IteratorIterator($dir);
            foreach ($iter as $file) {
                if ($file->getExtension() == 'src') {
                    $this->testsrc[] = $file->getPathname();
                }
            }
        }
    }

    function parse_run($testsrc, $both) {
        unset($out);
        unset($rc);
        if ($both) {
            $stdout_file = preg_replace('/.[a-z]*$/', '.srctmp', $testsrc);
        } else {
            $stdout_file = preg_replace('/.[a-z]*$/', '.outtmp', $testsrc);
        }
        exec("php7.4 '" . $this->settings->parsedir . "' <'" . $testsrc . "' > '" . $stdout_file . "'", $out, $rc);
        return $rc;
    }

    function int_run($testsrc, $both) {
        unset($out);
        unset($rc);
        if ($both) {
            $testsrc = preg_replace('/.[a-z]*$/', '.srctmp', $testsrc);
        }
        $stdout_file = preg_replace('/.[a-z]*$/', '.outtmp', $testsrc);
        $in_file = preg_replace('/.[a-z]*$/', '.in', $testsrc);
        if (!file_exists($in_file)) {
            file_put_contents($in_file, '');
        }
        exec("python3.8 '" . $this->settings->intdir . "' --source='" . $testsrc . "' --input='" . $in_file . "'>'" . $stdout_file . "'", $out, $rc);
        return $rc;
    }

    /**
     * Compares the result code to the .rc file.
     * @param int $rc Script result code.
     * @param string $src Test .src path.
     * @return bool True if equal.
     */
    function check_rc($rc, $src) {
        $rc_file = preg_replace('/.[a-z]*$/', '.rc', $src);
        if (file_exists($rc_file)) {
            $rc_correct = file_get_contents($rc_file);
        } else {
            file_put_contents($rc_file, '0');
            $rc_correct = '0';
        }
        $rc_correct = trim($rc_correct);
        $rc = strval($rc);
        if (strcmp($rc, $rc_correct) == 0) {
            return true;
        } else {
            return false;
        }
    }

    function check_out($src) {
        unset($diffout);
        unset($diffrc);
        $out_file = preg_replace('/.[a-z]*$/', '.out', $src);
        $stdout_file = preg_replace('/.[a-z]*$/', '.outtmp', $src);
        if (!file_exists($out_file)) {
            file_put_contents($out_file, '');
        }
        exec("diff " . $out_file . " " . $stdout_file, $diffout, $diffrc);
        if ($diffrc == 0) {
            return true;
        } else {
            return false;
        }
    }

    function check_out_xml($src) {
        $out_file = preg_replace('/.[a-z]*$/', '.out', $src);
        $stdout_file = preg_replace('/.[a-z]*$/', '.outtmp', $src);
        $diff = "./delta.xml";
        $file = fopen($diff, 'w');
        if (! $file) {
            exit(99);
        }
        fclose($file);
        $diff = realpath($diff);
        exec('java -jar ' . $this->settings->jexamxml . ' ' . $stdout_file . ' ' . $out_file . ' ' . $diff . ' ' . $this->settings->jexamcfg, $output, $diff_code);
        unlink($diff);
        if ($diff_code = 0) {
            return true;
        } else {
            return false;
        }
    }

}

class generate_HTML {

    private $tests_list;
    private $html_frame;
    private $test_settings;
    private $test_settings_list;
    private $test_stats;

    function __construct() {
        $this->tests_list = '';
        $this->test_settings = '';
        $this->test_settings_list = '';
        $this->test_stats = '';
        $this->html_frame = '';
    }

    function print_html() {
        $this->add_settings();
        $this->html_frame =
            '<html lang="cs"><head><style>body{font-family:Monaco,monospace; font-size: 10pt;}.box{-webkit-box-shadow:3px 3px 5px 3px #d3d3d3;-moz-box-shadow:3px 3px 5px 3px #d3d3d3;box-shadow:3px 3px 5px 3px #d3d3d3;-moz-border-radius:10px;' .
            '-webkit-border-radius:10px;border-radius:10px;-khtml-border-radius:10px;padding:10px;margin:20px}h1{font-family:Monaco,monospace;font-size:20px}#test-properties{width:70%}' .
            '#test-stats{width:20%}.test_success{border-bottom:1.5px solid #20b2aa;display:flex;justify-content:space-between;margin-top:10px}.test_fail{border-bottom:1.5px solid #20b2aa;'.
            'display:flex;justify-content:space-between;margin-top:10px}</style><title>Automatické testy IPPcode21</title></head><body style="margin:0;"><div style="height: 60px; background-color: lightgray;' .
            ' padding-left: 30px; padding-top: 10px;"><h1>Automatické testy IPPcode21</h1></div><div class="box" style="display: flex; justify-content: space-around;">' . $this->test_settings . $this->test_stats . '</div>' .
            '<div class="box">' . $this->tests_list . '</div></body></html>';
        echo $this->html_frame;
    }

    function add_settings() {
        $this->test_settings = '<div id="test-properties"><h1>Nastavení</h1><ul>' . $this->test_settings_list . '</ul></div>';
    }

    function create_setting($text) {
        $this->test_settings_list = $this->test_settings_list . '<li>' . $text . '</li>';
    }

    function add_stats($all, $succ, $fail) {
        $result = $succ / $all * 100;
        $result = strval($result);
        $all = strval($all);
        $succ = strval($succ);
        $fail = strval($fail);

        $this->test_stats = '<div id="test-stats"><h1>Výsledek</h1><ul><li>Celkem: ' . $all . '</li><li>Uspělo: ' . $succ . '</li><li>Selhalo: ' . $fail . '</li><p style="font-size: 30pt; font-weight: bold;">' . $result . ' %</p></ul></div>';
    }

    function add_success($testsrc, $testnum) {
        $this->tests_list = $this->tests_list . '<div class="test_success"><div style="width: 5%;"><h1>' . $testnum . '</h1></div><div style="width: 50%;">' .
        $testsrc . '</div><div style="width: 15%; text-align: right;"><h1 style="color: limegreen;">Úspěch</h1></div></div>';
    }

    function add_fail($testsrc, $testnum) {
        $this->tests_list = $this->tests_list . '<div class="test_fail"><div style="width: 5%;"><h1>' . $testnum . '</h1></div><div style="width: 50%;">' .
            $testsrc . '</div><div style="width: 15%; text-align: right;"><h1 style="color: red;">Selhalo</h1></div></div>';
    }
}

$run = new test_run();

?>