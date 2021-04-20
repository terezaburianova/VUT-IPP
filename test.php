<?php
/**
 * Automatic tests for scripts parsing and interpreting the IPPcode21 language.
 * Brno University of Technology, Faculty of Information Technology
 *
 * @package test.php
 * @author Tereza Burianova <xburia28@vutbr.cz>
 */

ini_set('display_errors', 'stderr');

/**
 * --help argument
 */
if (strcmp($argv[1], '--help') == 0) {
    if ($argc > 2) exit(10);
    echo("Automatic tests for parse.php and interpret.py:\n");
    echo("--directory=path - directory containing tests (default current folder)\n");
    echo("--recursive - recursive folder browsing\n");
    echo("--parse-script=file - parse.php script (default parse.php in current folder)\n");
    echo("--int-script=file - interpret.py script (default interpret.py in current folder)\n");
    echo("--parse-only - test parse.php only\n");
    echo("--int-only - test interpret.py only\n");
    echo("--jexamxml=file - JExamXML .jar file (default /pub/courses/ipp/jexamxml/jexamxml.jar)\n");
    echo("--jexamcfg=file - JExamXML config file (default /pub/courses/ipp/jexamxml/options)\n");
    exit(0);
}

/**
 * Class test_settings
 * Parses the arguments and fills the corresponding variables.
 */
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

    /**
     * test_settings constructor.
     * Parses the arguments and calls functions to confirm their validity and save them to variables.
     */
    function __construct() {
        $optionsList = array("directory:", "recursive", "parse-script:", "int-script:", "parse-only", "int-only", "jexamxml:", "jexamcfg:");
        $options = getopt(null, $optionsList, $optIndex);
        if ($options == false) {
            exit(10);
        }
        $this->opts_validity($options, $optIndex);
        $this->fill_variables($options);
    }

    /**
     * Arguments validity checks.
     * @param $options array Parsed arguments.
     * @param $optIndex int Index where the parser stopped processing the arguments.
     */
    function opts_validity($options, $optIndex) {
        if ($optIndex > sizeof($options) + 1) {
            //! ERROR: invalid options
            exit(10);
        }
        if (array_key_exists("parse-only", $options) and (array_key_exists("int-only", $options) or array_key_exists("int-script", $options))) {
            //! ERROR: Invalid combination of arguments.
            exit(10);
        }
        if (array_key_exists("int-only", $options) and array_key_exists("parse-script", $options)) {
            //! ERROR: Invalid combination of arguments.
            exit(10);
        }
        foreach ($options as $opt) {
            if (gettype($opt) == 'array') {
                //! ERROR: Option used several times.
                exit(10);
            }
        }
    }

    /**
     * Fills class parameters with present option values.
     *
     * @param $options array Parsed arguments.
     */
    function fill_variables($options) {
        if (array_key_exists("directory", $options)) {
            $options["directory"] = str_replace('"', '', $options["directory"]);
            if (!is_dir(realpath($options["directory"] ))) {
                exit(41);
            }
            $this->testdir = realpath($options["directory"]);
        }
        if (array_key_exists("recursive", $options)) {
            $this->recursive = true;
        }
        if (array_key_exists("parse-script", $options)) {
            $options["parse-script"] = str_replace('"', '', $options["parse-script"]);
            if (!file_exists(realpath($options["parse-script"]))) {
                exit(41);
            }
            $this->parsedir = realpath($options["parse-script"]);
        }
        if (array_key_exists("int-script", $options)) {
            $options["int-script"] = str_replace('"', '', $options["int-script"]);
            if (!file_exists(realpath($options["int-script"]))) {
                exit(41);
            }
            $this->intdir = realpath($options["int-script"]);
        }
        if (array_key_exists("parse-only", $options)) {
            $this->parseonly = true;
        }
        if (array_key_exists("int-only", $options)) {
            $this->intonly = true;
        }
        if (array_key_exists("jexamxml", $options)) {
            $options["jexamxml"] = str_replace('"', '', $options["jexamxml"]);
            if (!file_exists(realpath($options["jexamxml"]))) {
                exit(41);
            }
            $this->jexamxml = realpath($options["jexamxml"]);
        }
        if (array_key_exists("jexamcfg", $options)) {
            $options["jexamcfg"] = str_replace('"', '', $options["jexamcfg"]);
            if (!file_exists(realpath($options["jexamcfg"]))) {
                exit(41);
            }
            $this->jexamcfg = realpath($options["jexamcfg"]);
        }
    }
}
/**
 * Class test_run
 * Body of the test - runs the scripts and compares the values.
 */
class test_run {

    private $settings = null;
    private $testsrc = null;
    private $success = 0;
    private $fail = 0;
    private $HTMLgen = null;

    /**
     * test_run constructor.
     * Calls the corresponding scripts and HTML generating.
     */
    function __construct() {
        $this->settings = new test_settings();
        $this->get_tests();
        $this->HTMLgen = new generate_HTML();
        $this->HTMLgen->create_setting('Adresář: ' . $this->settings->testdir);
        if ($this->settings->recursive) {
            $this->HTMLgen->create_setting('Rekurzivní prohledávání');
        }
        if ($this->settings->parseonly) {
            $this->HTMLgen->create_setting('Režim parse-only');
            if ($this->testsrc != null) {
                $this->parse_only();
            }
        } elseif ($this->settings->intonly) {
            $this->HTMLgen->create_setting('Režim int-only');
            if ($this->testsrc != null) {
                $this->int_only();
            }
        } else {
            $this->HTMLgen->create_setting('Režim parse i int');
            if ($this->testsrc != null) {
                $this->both();
            }
        }
        if ($this->testsrc != null) {
            $this->HTMLgen->add_stats(sizeof($this->testsrc), $this->success, $this->fail);
        } else {
            $this->HTMLgen->add_stats(0, 0, 0);
        }
        $this->HTMLgen->print_html();
    }

    /**
     * Loops through test files, calls parse.php, compares the values and generates a HTML test result.
     */
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

    /**
     * Loops through test files, calls interpret.py, compares the values and generates a HTML test result.
     */
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

    /**
     * Loops through test files, calls parse.php and interpret.py, compares the values and generates a HTML test result.
     */
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
            $int_return = $this->int_run(realpath($test), true);
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

    /**
     * Puts all test sources into an array.
     */
    function get_tests() {
        if ($this->settings->recursive) {
            try {
                $iter = new RecursiveDirectoryIterator($this->settings->testdir);
                foreach (new RecursiveIteratorIterator($iter) as $file) {
                    if ($file->getExtension() == 'src') {
                        $this->testsrc[] = $file->getPathname();
                    }
                }
            } catch (Exception $e) {
                exit(11);
            }
        } else {
            try {
                $dir = new DirectoryIterator($this->settings->testdir);
                $iter = new IteratorIterator($dir);
                foreach ($iter as $file) {
                    if ($file->getExtension() == 'src') {
                        $this->testsrc[] = $file->getPathname();
                    }
                }
            } catch (Exception $e) {
                exit(11);
            }
        }

    }

    /**
     * Runs the parse.php script.
     * @param $testsrc string Current test source.
     * @param $both boolean True if both parse and interpret are being tested.
     * @return int Script return code.
     */
    function parse_run($testsrc, $both) {
        unset($out);
        unset($rc);
        try {
            if ($both) {
                $stdout_file = preg_replace('/.[a-z]*$/', '.srctmp', $testsrc);
            } else {
                $stdout_file = preg_replace('/.[a-z]*$/', '.outtmp', $testsrc);
            }
        } catch (Exception $e) {
            exit(99);
        }
        exec("php7.4 '" . $this->settings->parsedir . "' <'" . $testsrc . "' > '" . $stdout_file . "' 2>/dev/null", $out, $rc);
        return $rc;
    }

    /**
     * Runs the interpret.py script.
     * @param $testsrc string Current test source.
     * @param $both boolean True if both parse and interpret are being tested.
     * @return int Script return code.
     */
    function int_run($testsrc, $both) {
        unset($out);
        unset($rc);
        try {
            if ($both) {
                $testsrc = preg_replace('/.[a-z]*$/', '.srctmp', $testsrc);
            }
            $stdout_file = preg_replace('/.[a-z]*$/', '.outtmp', $testsrc);
            $in_file = preg_replace('/.[a-z]*$/', '.in', $testsrc);
        } catch (Exception $e) {
            exit(99);
        }
        if (!file_exists($in_file)) {
            file_put_contents($in_file, '');
        }
        exec("python3.8 '" . $this->settings->intdir . "' --source='" . $testsrc . "' --input='" . $in_file . "'>'" . $stdout_file . "' 2>/dev/null", $out, $rc);
        return $rc;
    }

    /**
     * Compares the result code to the .rc file.
     * @param int $rc Script result code.
     * @param string $src Test .src path.
     * @return bool True if equal.
     */
    function check_rc($rc, $src) {
        try {
            $rc_file = preg_replace('/.[a-z]*$/', '.rc', $src);
        } catch (Exception $e) {
            exit(99);
        }
        if (file_exists($rc_file)) {
            $rc_correct = file_get_contents($rc_file);
        } else {
            try {
                file_put_contents($rc_file, '0');
            } catch (Exception $e) {
                exit(12);
            }
            $rc_correct = '0';
        }
        try {
            $rc_correct = trim($rc_correct);
            $rc = strval($rc);
        } catch  (Exception $e) {
            exit(99);
        }
        if (strcmp($rc, $rc_correct) == 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks interpret output.
     * @param $src string Test source.
     * @return bool True if equal.
     */
    function check_out($src) {
        unset($diffout);
        unset($diffrc);
        try {
            $out_file = preg_replace('/.[a-z]*$/', '.out', $src);
            $stdout_file = preg_replace('/.[a-z]*$/', '.outtmp', $src);
        } catch (Exception $e) {
            exit(99);
        }
        if (!file_exists($out_file)) {
            try {
                file_put_contents($out_file, '');
            } catch (Exception $e) {
                exit(12);
            }
        }
        if (!file_exists($stdout_file)) {
            exit(11);
        }
        exec("diff " . $out_file . " " . $stdout_file, $diffout, $diffrc);
        if ($diffrc == 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks parse output.
     * @param $src string Test source.
     * @return bool True if equal.
     */
    function check_out_xml($src) {
        try {
            $out_file = preg_replace('/.[a-z]*$/', '.out', $src);
            $stdout_file = preg_replace('/.[a-z]*$/', '.outtmp', $src);
        } catch (Exception $e) {
            exit(99);
        }
        $diff = "./delta.xml";
        try {
            $file = fopen($diff, 'w');
        } catch (Exception $e) {
            exit(12);
        }
        if (! $file) {
            exit(12);
        }
        fclose($file);
        $diff = realpath($diff);
        if(!is_file($this->settings->jexamcfg)) {
            exit(41);
        }
        if(!is_file($this->settings->jexamxml)) {
            exit(41);
        }
        exec('java -jar ' . $this->settings->jexamxml . ' ' . $stdout_file . ' ' . $out_file . ' ' . $diff . ' ' . $this->settings->jexamcfg, $output, $diff_code);
        unlink($diff);
        if ($diff_code == 0) {
            return true;
        } else {
            return false;
        }
    }

}

/**
 * Class generate_HTML
 * Generates HTML webpage with results.
 */
class generate_HTML {

    private $tests_list;
    private $html_frame;
    private $test_settings;
    private $test_settings_list;
    private $test_stats;

    /**
     * generate_HTML constructor.
     * Sets the variables to empty strings.
     */
    function __construct() {
        $this->tests_list = '';
        $this->test_settings = '';
        $this->test_settings_list = '';
        $this->test_stats = '';
        $this->html_frame = '';
    }

    /**
     * Prints the final webpage to stdout.
     */
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

    /**
     * Adds a block with test settings.
     */
    function add_settings() {
        $this->test_settings = '<div id="test-properties"><h1>Nastavení</h1><ul>' . $this->test_settings_list . '</ul></div>';
    }

    /**
     * @param $text string Creates a list item with a test parameter.
     */
    function create_setting($text) {
        $this->test_settings_list = $this->test_settings_list . '<li>' . $text . '</li>';
    }

    /**
     * Creates a block with final test stats.
     * @param $all int Amount of tests.
     * @param $succ int Amount of successful tests.
     * @param $fail int Amount of failed tests.
     */
    function add_stats($all, $succ, $fail) {
        if ($all == 0) {
            $result = 0;
        } else {
            $result = $succ / $all * 100;
            $result = round($result, 1);
        }
        $result = strval($result);
        $all = strval($all);
        $succ = strval($succ);
        $fail = strval($fail);

        $this->test_stats = '<div id="test-stats"><h1>Výsledek</h1><ul><li>Celkem: ' . $all . '</li><li>Uspělo: ' . $succ . '</li><li>Selhalo: ' . $fail . '</li><p style="font-size: 30pt; font-weight: bold;">' . $result . ' %</p></ul></div>';
    }

    /**
     * Adds a block with a successful test run.
     * @param $testsrc string Test source.
     * @param $testnum int Test number.
     */
    function add_success($testsrc, $testnum) {
        $this->tests_list = $this->tests_list . '<div class="test_success"><div style="width: 5%;"><h1>' . $testnum . '</h1></div><div style="width: 50%;">' .
        $testsrc . '</div><div style="width: 15%; text-align: right;"><h1 style="color: limegreen;">Úspěch</h1></div></div>';
    }

    /**
     * Adds a block with a failed test run.
     * @param $testsrc string Test source.
     * @param $testnum int Test number.
     */
    function add_fail($testsrc, $testnum) {
        $this->tests_list = $this->tests_list . '<div class="test_fail"><div style="width: 5%;"><h1>' . $testnum . '</h1></div><div style="width: 50%;">' .
            $testsrc . '</div><div style="width: 15%; text-align: right;"><h1 style="color: red;">Selhalo</h1></div></div>';
    }
}

$run = new test_run();

?>