<?php
namespace Windaishi\ErrorHandler;

/**
 * @copyright Copyright (c) 2018 VIISON GmbH
 */
class ErrorHandler
{
    const ERROR_CATEGORY_UNCAUGHT_EXCEPTION = 1;
    const ERROR_CATEGORY_PHP_ERROR = 2;

    const MAX_ARGUMENT_LENGTH = 10000;

    const PHP_ERROR_DESCRIPTIONS = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parsing Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Runtime Notice',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated',
    ];

    /**
     * @var int
     */
    private $errorReportingLevel = E_ALL;

    /**
     * @var bool
     */
    private $exitAfterEveryError = false;

    /**
     * @var bool
     */
    private $sendErrorHeader = false;

    /**
     * @var bool
     */
    private $printAsHtml = false;

    /**
     * @var bool
     */
    private $printExtendedHtml = false;

    /**
     * @var null|string
     */
    private $writeToFile = null;

    /**
     * @var null|string
     */
    private $systemCall = null;

    /**
     * @var null|string
     */
    private $sendToEmail = null;

    private $sourceFileBlackListRegex = null;

    private $sourceFileWhiteListRegex = null;

    public function registerErrorHandler()
    {
        set_error_handler(
            function ($errorCode, $errorMessage, $errorFile, $errorLine) {
                $this->errorHandlerCallback($errorCode, $errorMessage, $errorFile, $errorLine);
            }
        );
        // phpcs:ignore
        ini_set('html_errors', 0);
        error_reporting(E_ALL);
    }

    public function registerExceptionHandler()
    {
        set_exception_handler(
            function ($e) {
                $this->exceptionHandlerCallback($e);
            }
        );
    }

    public function register()
    {
        $this->registerErrorHandler();
        $this->registerExceptionHandler();
    }

    /**
     * @param Exception $e
     */
    private function exceptionHandlerCallback($e)
    {
        $errorCategory = self::ERROR_CATEGORY_UNCAUGHT_EXCEPTION;
        $errorType = get_class($e);
        $errorMessage = $e->getMessage();
        $errorCode = $e->getCode();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        $errorTrace = $e->getTrace();

        $this->handleError($errorCategory, $errorType, $errorMessage, $errorCode, $errorFile, $errorLine, $errorTrace);
    }

    /**
     * @param $errorCode
     * @param $errorMessage
     * @param $errorFile
     * @param $errorLine
     */
    private function errorHandlerCallback($errorCode, $errorMessage, $errorFile, $errorLine)
    {
        // Don't report errors when @ is used
        if (error_reporting() === 0) {
            return;
        }

        if (($errorCode & $this->errorReportingLevel) !== $errorCode) {
            return;
        }

        if ($this->sourceFileWhiteListRegex && !preg_match($this->sourceFileWhiteListRegex, $errorFile)) {
            return;
        }

        if ($this->sourceFileBlackListRegex && preg_match($this->sourceFileBlackListRegex, $errorFile)) {
            return;
        }

        $errorCategory = self::ERROR_CATEGORY_PHP_ERROR;
        $errorType = self::PHP_ERROR_DESCRIPTIONS[$errorCode] ?: 'Unknown';
        $errorTrace = array_slice(debug_backtrace(), 4);

        self::handleError($errorCategory, $errorType, $errorMessage, $errorCode, $errorFile, $errorLine, $errorTrace);
    }

    /**
     * @param $errorCategory
     * @param $errorType
     * @param $errorMessage
     * @param $errorCode
     * @param $errorFile
     * @param $errorLine
     * @param $errorTrace
     */
    private function handleError($errorCategory, $errorType, $errorMessage, $errorCode, $errorFile, $errorLine, $errorTrace)
    {
        if ($this->sendErrorHeader && !headers_sent()) {
            http_response_code(500);
        }

        $html = $this->getHtml(
            $errorCategory,
            $errorType,
            $errorMessage,
            $errorCode,
            $errorFile,
            $errorLine,
            $errorTrace,
            $this->printExtendedHtml
        );

        if ($this->printAsHtml) {
            echo $html;
        }

        if ($this->writeToFile) {
            file_put_contents($this->writeToFile, $html);
        }

        if ($this->systemCall) {
            shell_exec($this->systemCall);
        }

        if ($this->sendToEmail) {
            mb_send_mail($this->sendToEmail, 'Fehler auf Website aufgetreten', $html, 'Content-type: text/html; charset=utf-8');
        }

        if ($this->exitAfterEveryError) {
            // phpcs:ignore
            exit;
        }
    }

    /**
     * @param $errorCategory
     * @param $errorType
     * @param $errorMessage
     * @param $errorCode
     * @param $errorFile
     * @param $errorLine
     * @param $errorTrace
     * @param bool $sensible
     * @return string
     */
    private function getHtml($errorCategory, $errorType, $errorMessage, $errorCode, $errorFile, $errorLine, $errorTrace, $sensible = false)
    {
        $errorCategory = ($errorCategory === self::ERROR_CATEGORY_PHP_ERROR) ? 'Script Error' : 'Uncaught Exception';
        $errorMessage = nl2br(self::escapeHtml($errorMessage));
        $errorTrace = self::formatBacktrace($errorTrace);

        $sensible = (!$sensible) ? '' : <<<SENSIBLE
<h3>{$errorCategory}:</h3>
<p class="errorMessage">{$errorMessage}</p>
<table>
  <tr>
    <th colspan="2">{$errorType}</th>
  </tr>
  <tr>
    <th>Message:</th>
    <td>{$errorMessage}</td>
  </tr>
  <tr>
    <th>Code:</th>
    <td>{$errorCode}</td>
  </tr>
  <tr>
    <th>File:</th>
    <td>{$errorFile}</td>
  </tr>
  <tr>
    <th>Line:</th>
    <td>{$errorLine}</td>
  </tr>
</table>
<h3>Back-Trace:</h3>
<p>{$errorTrace}</p>
<textarea id='argtext' style='display:none' cols='100' rows='15'>

</textarea>
<script type="text/javascript">
//<![CDATA[

var argTextField = $("#argtext");
$(".array, .string").click(function(e){
    argTextField.show();
    argTextField.text($(this).attr("title"));
});

//]]>
</script>
SENSIBLE;

        $output = <<<OUTPUT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<script src="http://code.jquery.com/jquery-1.11.3.min.js" type="text/javascript"></script>
<title>Oops! There occured any error...</title>
<style type="text/css">
<!--
p {
    font-family: "Courier New", Courier, monospace;
    line-height: 1.8;
}
th {
    text-align: right;
    background-color: #CCCCCC;
}
td {
    padding-left: 2px;
    background-color: #DDDDDD;
}
th[colspan = '2'] {
    font-size: 18px;
    font-weight: bold;
    color: #FFFFFF;
    background-color: #FF3333;
    text-align: center;
    margin: 0;
    padding: 6px;
}
body {
    font-family: Verdana, Arial, Helvetica, sans-serif;
    font-size: 12px;
}
.errorMessage {
    margin: 10px;
    padding: 5px;
    border: solid 2px black;
    color: red;
    font-weight: bold;
}
.function {
    color:  #0000FF;
}
.string {
    color: #008200;
}
.number {
    color: #FF0000;
}
.comment {
    color: #808080;
}
.string:hover, .array:hover {
    text-decoration:underline;
    cursor: pointer;
}
.keyword {
    color:  #0000FF;
}
-->
</style>
</head>

<body>
<h1>Oops!</h1>
<h2>There occured any error!</h2>
<p>There occured a critical error while executing the servers code. In general this is not your fault but the servers.</p>
<p>You should try again later. The administrator has been informed about this error.</p>
{$sensible}
</body>
</html>
OUTPUT;

        return $output;
    }

    private function formatBacktrace($errorTrace)
    {
        $formattedStackTrace = '';
        $n = count($errorTrace);
        $i = 0;
        foreach ($errorTrace as $value) {
            $args = [];

            if (isset($value['args'])) {
                foreach ($value['args'] as $arg) {
                    if ($arg === null) {
                        $arg = '<span class="keyword">NULL</span>';
                    } else if (is_string($arg)) {
                        $arg = mb_substr($arg, 0, self::MAX_ARGUMENT_LENGTH);
                        if (mb_strlen($arg) > 120) {
                            $arg =
                                '<span class=\'string\' title=\'' .
                                self::escapeHtml($arg) .
                                '\'>&quot;' .
                                self::escapeHtml(mb_substr($arg, 0, 100)) .
                                '...&quot;</span>';
                        } else {
                            $arg = '<span class=\'string\'>&quot;' . self::escapeHtml($arg) . '&quot;</span>';
                        }
                    } else if (is_bool($arg)) {
                        $arg = '<span class="keyword">' . ($arg ? 'true' : 'false') . '</span>';
                    } else if (is_numeric($arg)) {
                        $arg = '<span class=\'number\'>' . $arg . '</span>';
                    } else if (is_array($arg)) {
                        $title = self::printArrayReadable($arg);
                        $arg = '<span class=\'array\' title=\'' . self::escapeHtml($title) . '\'>Array(' . count($arg) . ')</span>';
                    } else if (is_object($arg)) {
                        $arg = 'Object of ' . get_class($arg);
                    }

                    $args[] = $arg;
                }
            }
            $lineBreakForFunctionArguments = (count($args) > 1) ? "<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" : '';
            $formattedStackTrace .=
                '#' . ($n - $i) . ' '.
                '<span class=\'function\'>' . $value['function'] . '</span>' . '(' . $lineBreakForFunctionArguments .
                 implode(', ' . $lineBreakForFunctionArguments, $args) .
                $lineBreakForFunctionArguments . ')';
            if (isset($value['file'])) {
                $formattedStackTrace .=
                    '<span class=\'comment\'> called at </span>' .
                    self::escapeHtml(str_replace(getcwd() . '/', '', $value['file'])) .
                    ' <span class=\'comment\'>on line</span> ' . $value['line'];
            }
            $formattedStackTrace .= "<br />\n";
            $i += 1;
        }
        $formattedStackTrace .= '#' . 0 . ' {main}';

        return $formattedStackTrace;
    }

    /**
     * @param $html
     * @return string
     */
    private static function escapeHtml($html)
    {
        return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param $array
     * @param int $depth
     */
    private static function printArrayReadable($array, $depth = 0)
    {
        if ($depth === 3) {
            return 'Array(' . count($array) . ')';
        }

        $readableRepresentation = 'array(' . count($array) . ') [';

        foreach ($array as $key => $value) {
            $readableRepresentation .= "\n";

            if (is_string($key)) {
                $readableRepresentation .= '"' . $key . '"';
            } else {
                $readableRepresentation .= $key;
            }

            $readableRepresentation .= ' => ';

            if ($value === null) {
                $readableRepresentation .= 'NULL';
            } else if (is_string($value)) {
                $readableRepresentation .= '"' . $value . '"';
            } else if (is_bool($value)) {
                $readableRepresentation .= ($value ? 'true' : 'false');
            } else if (is_numeric($value)) {
                $readableRepresentation = $value;
            } else if (is_array($value)) {
                $readableRepresentation .= self::printArrayReadable($array, $depth + 1);
            } else if (is_object($value)) {
                $readableRepresentation .= 'Object of ' . get_class($value);
            }

            $readableRepresentation .= ',';
        }

        if (count($array > 0)) {
            $readableRepresentation .= "\n";
        }

        $readableRepresentation .= ']';

        return $readableRepresentation;
    }

    /**
     * @return int
     */
    public function getErrorReportingLevel()
    {
        return $this->errorReportingLevel;
    }

    /**
     * @param int $errorReportingLevel
     */
    public function setErrorReportingLevel($errorReportingLevel)
    {
        $this->errorReportingLevel = $errorReportingLevel;
    }

    /**
     * @return bool
     */
    public function getExitAfterEveryError()
    {
        return $this->exitAfterEveryError;
    }

    /**
     * @param bool $exitAfterEveryError
     */
    public function setExitAfterEveryError($exitAfterEveryError)
    {
        $this->exitAfterEveryError = $exitAfterEveryError;
    }

    /**
     * @return bool
     */
    public function getSendErrorHeader()
    {
        return $this->sendErrorHeader;
    }

    /**
     * @param bool $sendErrorHeader
     */
    public function setSendErrorHeader($sendErrorHeader)
    {
        $this->sendErrorHeader = $sendErrorHeader;
    }

    /**
     * @return bool
     */
    public function getPrintAsHtml()
    {
        return $this->printAsHtml;
    }

    /**
     * @param bool $printAsHtml
     */
    public function setPrintAsHtml($printAsHtml)
    {
        $this->printAsHtml = $printAsHtml;
    }

    /**
     * @return bool
     */
    public function getPrintExtendedHtml()
    {
        return $this->printExtendedHtml;
    }

    /**
     * @param bool $printExtendedHtml
     */
    public function setPrintExtendedHtml($printExtendedHtml)
    {
        $this->printExtendedHtml = $printExtendedHtml;
    }

    /**
     * @return null|string
     */
    public function getWriteToFile()
    {
        return $this->writeToFile;
    }

    /**
     * @param null|string $writeToFile
     */
    public function setWriteToFile($writeToFile)
    {
        $this->writeToFile = $writeToFile;
    }

    /**
     * @return null|string
     */
    public function getSystemCall()
    {
        return $this->systemCall;
    }

    /**
     * @param null|string $systemCall
     */
    public function setSystemCall($systemCall)
    {
        $this->systemCall = $systemCall;
    }

    /**
     * @return null|string
     */
    public function getSendToEmail()
    {
        return $this->sendToEmail;
    }

    /**
     * @param null|string $sendToEmail
     */
    public function setSendToEmail($sendToEmail)
    {
        $this->sendToEmail = $sendToEmail;
    }

    /**
     * @return null
     */
    public function getSourceFileBlackListRegex()
    {
        return $this->sourceFileBlackListRegex;
    }

    /**
     * @param null $sourceFileBlackListRegex
     */
    public function setSourceFileBlackListRegex($sourceFileBlackListRegex)
    {
        $this->sourceFileBlackListRegex = $sourceFileBlackListRegex;
    }

    /**
     * @return null
     */
    public function getSourceFileWhiteListRegex()
    {
        return $this->sourceFileWhiteListRegex;
    }

    /**
     * @param null $sourceFileWhiteListRegex
     */
    public function setSourceFileWhiteListRegex($sourceFileWhiteListRegex)
    {
        $this->sourceFileWhiteListRegex = $sourceFileWhiteListRegex;
    }
}
