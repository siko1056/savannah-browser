<!DOCTYPE html>
<html lang="en">
<head>
  <title>Savannah browser</title>
  <style>
  body { }
  a { color: navy; }
  div#footer {
    text-align: center;
    margin: 20px;
  }
  table { border-collapse: collapse; }
  tr:not([class]) { background-color: powderblue; }
  td, th { padding: 5px; }
  /* From https://savannah.gnu.org/css/internal/base.css */
  .priora { background-color: #fff2f2; }
  .priorb { background-color: #ffe8e8; }
  .priorc { background-color: #ffe0e0; }
  .priord { background-color: #ffd8d8; }
  .priore { background-color: #ffcece; }
  .priorf { background-color: #ffc6c6; }
  .priorg { background-color: #ffbfbf; }
  .priorh { background-color: #ffb7b7; }
  .priori { background-color: #ffadad; }
  </style>
</head>
<body>

<h1>Savannah browser</h1>

<?php

require_once("db.php");
require_once("crawler.php");

$obj = new crawler();
//var_dump($obj->readAllIDs());
print_r($obj->crawl(59979));
?>

<div id="footer">
  <p>
    Get the source code of this page on
    <a href="https://github.com/gnu-octave/release-burn-down-chart">GitHub</a>.
  </p>
</div>

</body>
</html>
