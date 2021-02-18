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
  .prioraclosed { background-color: #f5ffeb; }
  .priorbclosed { background-color: #edffe6; }
  .priorcclosed { background-color: #eeffe1; }
  .priordclosed { background-color: #e0ffd5; }
  .prioreclosed { background-color: #ccffbb; }
  .priorfclosed { background-color: #c6ffb9; }
  .priorgclosed { background-color: #c0ffb2; }
  .priorhclosed { background-color: #adffa4; }
  .prioriclosed { background-color: #a0ff9d; }
  </style>
</head>
<body>

<h1>Savannah browser</h1>

<?php
require_once("api.php");
echo((new api())->processRequest($_GET));
?>

<div id="footer">
  <p>
    Get the source code of this page on
    <a href="https://github.com/gnu-octave/release-burn-down-chart">GitHub</a>.
  </p>
</div>

</body>
</html>
