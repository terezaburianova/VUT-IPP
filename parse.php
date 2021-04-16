<?php
/**
 * This program parses the IPPcode21 language to its XML representation.
 * Brno University of Technology, Faculty of Information Technology
 *
 * @package    parser.php
 * @author     Tereza Burianova <xburia28@vutbr.cz>
 */

ini_set('display_errors', 'stderr');

/**
 * Removes the inline comments
 *
 * @param string $str A line of the IPPcode21 language
 * @return string The $str param with inline comments removed
 */
function removeComments($str) {
    $lineArr = explode('#', $str);
    return $lineArr[0];
}

/**
 * Checks if a valid header is present
 *
 * @param string $str A line of the IPPcode21 language
 * @return bool TRUE if $str is a valid header
 */
function checkHeader($str) {
    $str = strtoupper($str);
    if (strcmp($str, '.IPPCODE21') == 0) {
        echo("\n<program language=\"IPPcode21\">");
        return TRUE;
    } else {
        //! ERROR: missing header
        exit(21);
    }
}

/**
 * Checks if the number of arguments is correct
 *
 * @param array $arr Parsed opcode and arguments
 * @param int $args Expected number of arguments + 1 (opcode)
 */
function checkArg($arr, $args) {
    if (count($arr) !== $args) {
        //! ERROR: wrong number of arguments
        exit(23);
    }
}

/**
 * Replaces the chars reserved for XML with entities
 *
 * @param string $value A string value or a var/label name
 * @return string The $value param with replaced characters
 */
function replaceEntities($value) {
    $value = str_replace('&', '&amp;', $value);
    $value = str_replace('"', '&quot;', $value);
    $value = str_replace('\'', '&apos;', $value);
    $value = str_replace('<', '&lt;', $value);
    $value = str_replace('>', '&gt;', $value);
    return $value;
}

/**
 * Creates the XML lines representing variables
 *
 * @param int $argNum Order of the current argument
 * @param string $value Currently parsed argument
 */
function matchVar($argNum, $value) {
    $regVar = '/^(GF|LF|TF)@[a-žA-Ž_\-$&%*!?][a-žA-Ž0-9_\-$&%*!?]*$/';
    if (preg_match($regVar ,$value) === 1) {
        $value = replaceEntities($value);
        echo("\n\t\t<arg$argNum type=\"var\">$value</arg$argNum>");
    } else {
        //! ERROR: invalid argument
        exit(23);
    }
}

/**
 * Creates the XML lines representing labels
 *
 * @param int $argNum Order of the current argument
 * @param string $value Currently parsed argument
 */
function matchLabel($argNum, $value) {
    $regLabel = '/^[a-žA-Ž_\-$&%*!?][a-žA-Ž0-9_\-$&%*!?]*$/';
    if (preg_match($regLabel ,$value) === 1) {
        $value = replaceEntities($value);
        echo("\n\t\t<arg$argNum type=\"label\">$value</arg$argNum>");
    } else {
        //! ERROR: invalid argument
        exit(23);
    }
}

/**
 * Creates the XML lines representing types
 *
 * @param int $argNum Order of the current argument
 * @param string $value Currently parsed argument
 */
function matchType($argNum, $value) {
    $regType = '/^(int|string|bool)$/';
    if (preg_match($regType ,$value) === 1) {
        echo("\n\t\t<arg$argNum type=\"type\">$value</arg$argNum>");
    } else {
        //! ERROR: invalid argument
        exit(23);
    }
}

/**
 * Creates the XML lines representing symbols
 *
 * @param int $argNum Order of the current argument
 * @param string $value Currently parsed argument
 */
function matchSymb($argNum, $value) {
    $regVar = '/^(GF|LF|TF)@[a-žA-Ž_\-$&%*!?][a-žA-Ž0-9_\-$&%*!?]*$/';
    $regInt = '/^int@[\-]?[0-9]+$/';
    $regBool = '/^bool@(true|false)$/';
    $regString = '/^string@([^\s#\\\\]|\\\\[0-9]{3})*$/';
    if (preg_match($regVar ,$value) === 1) {
        $value = replaceEntities($value);
        echo("\n\t\t<arg$argNum type=\"var\">$value</arg$argNum>");
    } else if (preg_match($regBool, $value) === 1) {
        $const = explode('@', $value, 2);
        echo("\n\t\t<arg$argNum type=\"bool\">".$const[1]."</arg$argNum>");
    } else if (preg_match($regInt, $value) === 1) {
        $const = explode('@', $value, 2);
        echo("\n\t\t<arg$argNum type=\"int\">".$const[1]."</arg$argNum>");
    } else if (preg_match($regString, $value) === 1) {
        $const = explode('@', $value, 2);
        $const[1] = replaceEntities($const[1]);
        echo("\n\t\t<arg$argNum type=\"string\">".$const[1]."</arg$argNum>");
    } else if (strcmp('nil@nil', $value) == 0) {
        echo("\n\t\t<arg$argNum type=\"nil\">nil</arg$argNum>");
    } else {
        //! ERROR: invalid argument
        exit(23);
    }
}

if ($argc > 1) {
    if (strcmp($argv[1], "--help") == 0) {
        echo("This program parses the IPPcode21 language (STDIN) to its XML representation (STDOUT).\n");
        //echo("Usage: parser.php <IPPcode21 [>XMLfile]\n");
        //echo("Usage: parser.php --help\n");
        exit(0);
    } else {
        //! ERROR: wrong parameter combination
        exit(10);
    }
}

$header = FALSE;
$order = 1; // instruction order

echo("<?xml version=\"1.0\" encoding=\"UTF-8\"?>");

// process the whole document
while($line = fgets(STDIN)) {

    // remove comments from current line
    if (strpos($line, '#') !== FALSE) {
        $line = removeComments($line);
    }

    // trim whitespace and skip line if empty
    $line = trim($line);
    if (strcmp($line, '') == 0) {
        continue;
    }

    // replace more whitespace characters with one
    $line = preg_replace('/\s+/', ' ', $line);
    $line = preg_replace('/\t+/', ' ', $line);

    // check the header if not yet present
    if ($header !== TRUE) {
        $header = checkHeader($line);
        continue;
    }

    // split the line and adjust the opcode + operands
    $words = explode(" ", $line);
    $words = array_map('trim', $words);
    $words[0] = strtoupper($words[0]);

    echo("\n\t<instruction order=\"".$order++."\" opcode=\"$words[0]\">");

    // arguments
    switch ($words[0]) {
        //? no arg
        case 'CREATEFRAME':
        case 'PUSHFRAME':
        case 'POPFRAME':
        case 'RETURN':
        case 'BREAK':
            checkArg($words, 1);
            break;
        
        //? 1 arg, var
        case 'DEFVAR':
        case 'POPS':
            checkArg($words, 2);
            matchVar(1, $words[1]);
            break;
        
        //? 1 arg, label
        case 'CALL':
        case 'LABEL':
        case 'JUMP':
            checkArg($words, 2);
            matchLabel(1, $words[1]);
            break;
        
        //? 1 arg, symb
        case 'PUSHS':
        case 'WRITE':
        case 'EXIT':
        case 'DPRINT':
            checkArg($words, 2);
            matchSymb(1, $words[1]);
            break;
        
        //? 2 args, var symb
        case 'MOVE':
        case 'INT2CHAR':
        case 'STRLEN':
        case 'TYPE':
        case 'NOT':
            checkArg($words, 3);
            matchVar(1, $words[1]);
            matchSymb(2, $words[2]);
            break;
        
        //? 2 args, var type
        case 'READ':
            checkArg($words, 3);
            matchVar(1, $words[1]);
            matchType(2, $words[2]);
            break;

        //? 3 args, var symb symb
        case 'ADD':
        case 'SUB':
        case 'MUL':
        case 'IDIV':
        case 'LT':
        case 'GT':
        case 'EQ':
        case 'AND':
        case 'OR':
        case 'STRI2INT':
        case 'CONCAT':
        case 'GETCHAR':
        case 'SETCHAR':
            checkArg($words, 4);
            matchVar(1, $words[1]);
            matchSymb(2, $words[2]);
            matchSymb(3, $words[3]);
            break;
        
        //? 3 args, label symb symb
        case 'JUMPIFEQ':
        case 'JUMPIFNEQ':
            checkArg($words, 4);
            matchLabel(1, $words[1]);
            matchSymb(2, $words[2]);
            matchSymb(3, $words[3]);
            break;

        default:
            //! ERROR: unknown or incorrect opcode
            exit(22);
        
    }

    echo("\n\t</instruction>");

}

if ($header !== TRUE) {
    //! ERROR: missing header (empty file)
    exit(21);
}

echo("\n</program>");


?>