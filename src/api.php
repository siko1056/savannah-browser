<?php

class api
{
  public function itemListAsHTML($data)
  {
    $str = '';
    foreach ($data as $row) {
      $row_str = '';
      foreach ($row as $col) {
        $row_str .= "<td>$col</td>";
      }
      $str .= "<tr>$row_str</tr>";
    }
    return "<table>$str</table>";
  }
}

?>
