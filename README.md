# PhpErrorReporter
A more advanced error reporter for your PHP script.

## Usage

Put this at the beginning of your script:
```php
<?php
// ...

use \Windaishi\ErrorHandler\ErrorHandler;

require 'ErrorHandler.php';

$errorHandler = new ErrorHandler();
$errorHandler->setErrorReportingLevel(E_ALL & ~E_NOTICE & ~ 2048);
$errorHandler->setPrintAsHtml(true);
$errorHandler->setPrintExtendedHtml(true);
$errorHandler->setSendErrorHeader(true);
$errorHandler->setExitAfterEveryError(true);
$errorHandler->setSourceFileBlackListRegex('/(^\\/home\\/manuel\\/VIISON\\/Shopware\\/Installations)|(\\/vendor\\/)|(Components\\/Document\\.php$)/');
$errorHandler->setWriteToFile('lastError.html');
$errorHandler->setSystemCall(sprintf(
    'export DISPLAY=:0; google-chrome "%s" > /dev/null 2> /dev/null &',
    realpath('lastError.html')
));
$errorHandler->register();
?>
```
