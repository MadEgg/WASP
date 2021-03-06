<?php
/*
This is part of WASP, the Web Application Software Platform.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

$type = $request->chooseResponse(array("text/html", "text/plain"));

$error_code = 500;
$error_title = "Internal Server Error";
$error_lead = "Something unanticipated went wrong. We'll try to fix this as soon as we can.";
$error_description = "The server encountered an error while processing your request\n";

if ($dev || $cli)
{
    $error_description .= 
        "\nDescription: " . $exception->getMessage() . "\n" 
        . WASP\Util\Functions::str($exception);
}

$type_name = str_replace("/", "_" ,$type) . ".php";
$path = $this->getResolver()->template($type_name);
if ($path !== null)
{
    $this->setMimeType($type);
    require $path;
}
else
    require tpl('error/text_html');

?>
