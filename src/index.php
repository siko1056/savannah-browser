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
